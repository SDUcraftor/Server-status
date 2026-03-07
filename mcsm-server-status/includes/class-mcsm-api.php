<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MCSManager API 封装
 * API Key 仅在此类（服务端）中使用，绝不暴露到前端
 */
class MCSM_API {

    private $debug_enabled = false;
    private $debug_trace = [];
    private $force_refresh = false;

    private function get_panel_url() {
        return rtrim(get_option('mcsm_panel_url', ''), '/');
    }

    private function get_api_key() {
        return get_option('mcsm_api_key', '');
    }

    /**
     * 读取“逐条服务器配置”
     */
    private function get_configured_servers() {
        $raw = get_option('mcsm_servers', '');
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $servers = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $daemon_id = isset($item['daemonId']) ? trim((string) $item['daemonId']) : '';
            $uuid = '';
            if (isset($item['instanceUuid'])) {
                $uuid = trim((string) $item['instanceUuid']);
            } elseif (isset($item['instanceId'])) {
                $uuid = trim((string) $item['instanceId']);
            }

            if ($daemon_id === '' || $uuid === '') {
                continue;
            }

            $servers[] = [
                'daemonId'     => $daemon_id,
                'instanceUuid' => $uuid,
                'name'         => isset($item['name']) ? (string) $item['name'] : '',
                'icon'         => isset($item['icon']) ? (string) $item['icon'] : '',
                'link'         => isset($item['link']) ? (string) $item['link'] : '',
                'tag'          => isset($item['tag']) ? (string) $item['tag'] : '',
                'description'  => isset($item['description']) ? (string) $item['description'] : '',
            ];
        }

        return $servers;
    }

    /**
     * 发送 GET 请求到 MCSManager
     */
    private function request($endpoint, $query_params = []) {
        $url = $this->get_panel_url() . $endpoint;

        $query_params['apikey'] = $this->get_api_key();
        $url = add_query_arg($query_params, $url);

        $safe_query = $query_params;
        if (isset($safe_query['apikey']) && $safe_query['apikey'] !== '') {
            $safe_query['apikey'] = '***';
        }
        $this->trace([
            'type' => 'request',
            'endpoint' => $endpoint,
            'query' => $safe_query,
            'url' => add_query_arg($safe_query, $this->get_panel_url() . $endpoint),
        ]);

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Content-Type'     => 'application/json; charset=utf-8',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->trace([
                'type' => 'error',
                'endpoint' => $endpoint,
                'message' => $response->get_error_message(),
            ]);
            return ['error' => $response->get_error_message()];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $this->trace([
            'type' => 'response',
            'endpoint' => $endpoint,
            'status' => $status_code,
            'bodyPreview' => mb_substr((string) $body, 0, 300),
        ]);

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->trace([
                'type' => 'error',
                'endpoint' => $endpoint,
                'message' => 'JSON parse failed: ' . json_last_error_msg(),
            ]);
            return ['error' => '无法解析 API 响应'];
        }

        return $data;
    }

    /**
     * 获取实例列表（仅使用 mcsm_servers 配置）
     */
    public function get_instances($daemon_id = '', $debug = false, $force_refresh = false) {
        $this->debug_enabled = (bool) $debug;
        $this->debug_trace = [];
        $this->force_refresh = (bool) $force_refresh;

        if (empty($this->get_panel_url()) || empty($this->get_api_key())) {
            $this->trace([
                'type' => 'error',
                'message' => '缺少 mcsm_panel_url 或 mcsm_api_key 配置',
            ]);
            return [];
        }

        $configured_servers = $this->get_configured_servers();
        if (empty($configured_servers)) {
            $this->trace([
                'type' => 'error',
                'message' => 'mcsm_servers 为空或格式无效',
            ]);
            return [];
        }

        $this->trace([
            'type' => 'info',
            'message' => 'configured servers: ' . count($configured_servers),
        ]);

        return $this->get_instances_from_configured_servers($configured_servers, $daemon_id);
    }

    /**
     * 获取单个实例详情（/api/instance）
     */
    private function get_instance_detail($daemon_id, $instance_uuid) {
        $ttl = intval(get_option('mcsm_cache_ttl', 30));
        $cache_key = 'mcsm_instance_detail_' . md5($daemon_id . '|' . $instance_uuid);

        if (!$this->force_refresh) {
            $cached = get_transient($cache_key);
            if (false !== $cached) {
                $this->trace([
                    'type' => 'cache_hit',
                    'scope' => 'instance_detail',
                    'daemonId' => $daemon_id,
                    'uuid' => $instance_uuid,
                ]);
                // 使用字符串哨兵避免缓存 null 失效
                if ($cached === '__MCSM_NOT_FOUND__') {
                    return null;
                }
                return is_array($cached) ? $cached : null;
            }
        } else {
            $this->trace([
                'type' => 'info',
                'message' => 'force refresh: skip instance cache',
                'daemonId' => $daemon_id,
                'uuid' => $instance_uuid,
            ]);
        }

        $result = $this->request('/api/instance', [
            'uuid'     => $instance_uuid,
            'daemonId' => $daemon_id,
        ]);

        if (isset($result['status']) && intval($result['status']) === 200 && isset($result['data']) && is_array($result['data'])) {
            set_transient($cache_key, $result['data'], $ttl);
            return $result['data'];
        }

        // 失败短缓存，防止频繁重试打爆面板
        $negative_ttl = max(5, min(15, $ttl));
        set_transient($cache_key, '__MCSM_NOT_FOUND__', $negative_ttl);
        return null;
    }

    /**
     * 从逐条配置中获取实例（按配置顺序输出）
     */
    private function get_instances_from_configured_servers($configured_servers, $daemon_id = '') {
        if (!empty($daemon_id)) {
            $configured_servers = array_values(array_filter($configured_servers, function ($item) use ($daemon_id) {
                return isset($item['daemonId']) && $item['daemonId'] === $daemon_id;
            }));
        }

        if (empty($configured_servers)) {
            return [];
        }

        $cache_key = 'mcsm_instances_cfg_' . md5(wp_json_encode($configured_servers));
        if (!$this->force_refresh) {
            $cached = get_transient($cache_key);
            if (false !== $cached) {
                $this->trace([
                    'type' => 'cache_hit',
                    'scope' => 'instances_aggregate',
                ]);
                return $cached;
            }
        } else {
            $this->trace([
                'type' => 'info',
                'message' => 'force refresh: skip aggregate cache',
            ]);
        }

        $servers = [];
        foreach ($configured_servers as $item) {
            $detail = $this->get_instance_detail($item['daemonId'], $item['instanceUuid']);

            if (is_array($detail)) {
                $server = $this->format_instance($detail, $item['daemonId']);
            } else {
                $server = $this->build_missing_instance($item);
            }

            $servers[] = $this->apply_server_item_overrides($server, $item);
        }

        $ttl = intval(get_option('mcsm_cache_ttl', 30));
        set_transient($cache_key, $servers, $ttl);

        return $servers;
    }

    /**
     * 格式化单个实例数据
     */
    private function format_instance($instance, $daemon_id) {
        $config = isset($instance['config']) ? $instance['config'] : [];
        $info   = isset($instance['info']) ? $instance['info'] : [];
        $proc   = isset($instance['processInfo']) ? $instance['processInfo'] : [];

        $status_code = isset($instance['status']) ? intval($instance['status']) : 0;

        $status_map = [
            -1 => ['label' => '维护中', 'class' => 'busy'],
            0  => ['label' => '已关闭', 'class' => 'stopped'],
            1  => ['label' => '关闭中', 'class' => 'stopping'],
            2  => ['label' => '启动中', 'class' => 'starting'],
            3  => ['label' => '运行中', 'class' => 'running'],
        ];

        $status = isset($status_map[$status_code]) ? $status_map[$status_code] : ['label' => '未知', 'class' => 'unknown'];

        $name = isset($config['nickname']) ? $config['nickname'] : '';
        $version = isset($info['version']) ? $info['version'] : '';
        $current_players = isset($info['currentPlayers']) ? intval($info['currentPlayers']) : -1;
        $max_players = isset($info['maxPlayers']) ? intval($info['maxPlayers']) : -1;
        $cpu = isset($proc['cpu']) ? round($proc['cpu'], 1) : 0;
        $memory = isset($proc['memory']) ? $proc['memory'] : 0;
        $elapsed = isset($proc['elapsed']) ? intval($proc['elapsed']) : 0;

        return [
            'instanceUuid'   => isset($instance['instanceUuid']) ? $instance['instanceUuid'] : '',
            'daemonId'       => $daemon_id,
            'name'           => $name,
            'statusCode'     => $status_code,
            'statusLabel'    => $status['label'],
            'statusClass'    => $status['class'],
            'currentPlayers' => $current_players,
            'maxPlayers'     => $max_players,
            'version'        => $version,
            'cpu'            => $cpu,
            'memory'         => $memory,
            'elapsed'        => $elapsed,
            'icon'           => '',
            'link'           => '',
            'tag'            => '',
            'description'    => '',
        ];
    }

    /**
     * 当配置的实例在 API 返回中不存在时，构造占位数据
     */
    private function build_missing_instance($item) {
        return [
            'instanceUuid'   => $item['instanceUuid'],
            'daemonId'       => $item['daemonId'],
            'name'           => !empty($item['name']) ? $item['name'] : $item['instanceUuid'],
            'statusCode'     => -2,
            'statusLabel'    => '未找到实例',
            'statusClass'    => 'unknown',
            'currentPlayers' => -1,
            'maxPlayers'     => -1,
            'version'        => '',
            'cpu'            => 0,
            'memory'         => 0,
            'elapsed'        => 0,
            'icon'           => '',
            'link'           => '',
            'tag'            => '',
            'description'    => '',
        ];
    }

    /**
     * 将逐条服务器配置字段覆盖到服务数据
     */
    private function apply_server_item_overrides($server, $item) {
        if (!empty($item['name'])) {
            $server['name'] = $item['name'];
        }
        if (!empty($item['icon'])) {
            $server['icon'] = $item['icon'];
        }
        if (!empty($item['link'])) {
            $server['link'] = $item['link'];
        }
        if (!empty($item['tag'])) {
            $server['tag'] = $item['tag'];
        }
        if (!empty($item['description'])) {
            $server['description'] = $item['description'];
        }

        return $server;
    }

    private function trace($entry) {
        if ($this->debug_enabled) {
            $this->debug_trace[] = $entry;
        }
    }

    public function get_debug_trace() {
        return $this->debug_trace;
    }
}
