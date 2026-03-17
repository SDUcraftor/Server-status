<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 后台设置页面
 */
class MCSM_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_mcsm-server-status') {
            return;
        }

        wp_enqueue_script(
            'mcsm-admin-settings',
            MCSM_SS_PLUGIN_URL . 'assets/js/admin-settings.js',
            [],
            MCSM_SS_VERSION,
            true
        );
    }

    public function add_menu() {
        add_options_page(
            'MCSM 服务器状态',
            'MCSM 服务器状态',
            'manage_options',
            'mcsm-server-status',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('mcsm_settings_group', 'mcsm_panel_url', [
            'sanitize_callback' => 'esc_url_raw',
        ]);
        register_setting('mcsm_settings_group', 'mcsm_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('mcsm_settings_group', 'mcsm_servers', [
            'sanitize_callback' => [$this, 'sanitize_servers_json'],
        ]);
        register_setting('mcsm_settings_group', 'mcsm_refresh_interval', [
            'sanitize_callback' => 'intval',
        ]);
        register_setting('mcsm_settings_group', 'mcsm_cache_ttl', [
            'sanitize_callback' => 'intval',
        ]);

        add_settings_section('mcsm_section_main', '面板连接设置', null, 'mcsm-server-status');
        add_settings_field('mcsm_panel_url', '面板地址', [$this, 'field_panel_url'], 'mcsm-server-status', 'mcsm_section_main');
        add_settings_field('mcsm_api_key', 'API Key', [$this, 'field_api_key'], 'mcsm-server-status', 'mcsm_section_main');

        add_settings_section('mcsm_section_servers', '服务器配置', [$this, 'section_servers_desc'], 'mcsm-server-status');
        add_settings_field('mcsm_servers', '逐条服务器 JSON', [$this, 'field_servers'], 'mcsm-server-status', 'mcsm_section_servers');

        add_settings_section('mcsm_section_cache', '刷新与缓存', null, 'mcsm-server-status');
        add_settings_field('mcsm_refresh_interval', '前端刷新间隔（秒）', [$this, 'field_refresh_interval'], 'mcsm-server-status', 'mcsm_section_cache');
        add_settings_field('mcsm_cache_ttl', '后端缓存时间（秒）', [$this, 'field_cache_ttl'], 'mcsm-server-status', 'mcsm_section_cache');
    }

    // ======================== 字段渲染 ========================

    public function field_panel_url() {
        $val = get_option('mcsm_panel_url', '');
        echo '<input type="url" name="mcsm_panel_url" value="' . esc_attr($val) . '" class="regular-text" placeholder="https://mcsm.example.com" />';
        echo '<p class="description">MCSManager 面板访问地址，不要以 / 结尾</p>';
    }

    public function field_api_key() {
        $val = get_option('mcsm_api_key', '');
        echo '<input type="password" name="mcsm_api_key" value="' . esc_attr($val) . '" class="regular-text" autocomplete="off" />';
        echo '<p class="description">在 MCSManager 面板 -> 用户中心 -> API Key 中生成</p>';
    }

    public function section_servers_desc() {
        echo '<p>推荐使用可视化编辑器维护服务器列表。每条服务器必须包含 daemonId 和 instanceId（或 instanceUuid），支持 children 子服务器。</p>';
    }

    public function field_servers() {
        $val = get_option('mcsm_servers', '');
        if (empty($val)) {
            $val = wp_json_encode([
                [
                    'daemonId'    => 'daemon-id-1',
                    'instanceId'  => 'instance-uuid-1',
                    'name'        => 'MUA Lobby',
                    'icon'        => 'https://example.com/icon.png',
                    'link'        => '/server/lobby',
                    'tag'         => 'SJMC',
                    'description' => '大厅服务器',
                    'children'    => [
                        [
                            'daemonId'   => 'daemon-id-1',
                            'instanceId' => 'instance-uuid-2',
                            'name'       => 'MUA Lobby-2',
                        ],
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        echo '<div id="mcsm-servers-editor" class="mcsm-servers-editor"></div>';
        echo '<p><button type="button" class="button button-secondary" id="mcsm-add-server-btn">添加服务器</button></p>';

        echo '<input type="hidden" name="mcsm_servers" id="mcsm_servers" value="' . esc_attr($val) . '" />';

        echo '<details style="margin-top:10px;">';
        echo '<summary>高级模式：JSON 导入/查看</summary>';
        echo '<p class="description">可先编辑 JSON，再点击“从 JSON 导入到可视化编辑器”。保存时以可视化编辑器内容为准。</p>';
        echo '<textarea id="mcsm_servers_manual" rows="14" class="large-text code">' . esc_textarea($val) . '</textarea>';
        echo '<p><button type="button" class="button" id="mcsm-import-json-btn">从 JSON 导入到可视化编辑器</button></p>';
        echo '</details>';

        echo '<style>
            .mcsm-servers-editor{display:flex;flex-direction:column;gap:12px}
            .mcsm-server-item,.mcsm-child-item{border:1px solid #dcdcde;background:#fff;padding:12px;border-radius:6px}
            .mcsm-child-list{margin-top:10px;padding-top:10px;border-top:1px dashed #dcdcde;display:flex;flex-direction:column;gap:8px}
            .mcsm-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px}
            .mcsm-field{display:flex;flex-direction:column;gap:4px}
            .mcsm-field label{font-size:12px;color:#50575e}
            .mcsm-item-actions{margin-top:10px;display:flex;gap:8px}
        </style>';
    }

    public function field_refresh_interval() {
        $val = get_option('mcsm_refresh_interval', 30);
        echo '<input type="number" name="mcsm_refresh_interval" value="' . esc_attr($val) . '" min="10" max="300" step="1" /> 秒';
    }

    public function field_cache_ttl() {
        $val = get_option('mcsm_cache_ttl', 30);
        echo '<input type="number" name="mcsm_cache_ttl" value="' . esc_attr($val) . '" min="5" max="300" step="1" /> 秒';
        echo '<p class="description">后端缓存可减少对 MCSManager 面板的请求频率</p>';
    }

    public function sanitize_servers_json($input) {
        $decoded = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            add_settings_error('mcsm_servers', 'invalid_servers_json', '服务器 JSON 格式不正确: ' . json_last_error_msg());
            return get_option('mcsm_servers', '');
        }

        $normalized = [];
        foreach ($decoded as $idx => $item) {
            $normalized_item = $this->sanitize_server_item($item, (string) $idx);
            if ($normalized_item) {
                $normalized[] = $normalized_item;
            }
        }

        return wp_json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sanitize_server_item($item, $path) {
        if (!is_array($item)) {
            return null;
        }

        $daemon_id = isset($item['daemonId']) ? trim((string) $item['daemonId']) : '';
        $instance_id = '';
        if (isset($item['instanceId'])) {
            $instance_id = trim((string) $item['instanceId']);
        } elseif (isset($item['instanceUuid'])) {
            $instance_id = trim((string) $item['instanceUuid']);
        }

        if ($daemon_id === '' || $instance_id === '') {
            add_settings_error('mcsm_servers', 'missing_required_fields_' . md5($path), '配置项 ' . $path . ' 缺少 daemonId 或 instanceId/instanceUuid');
            return null;
        }

        $normalized = [
            'daemonId'     => $daemon_id,
            'instanceUuid' => $instance_id,
            'name'         => isset($item['name']) ? sanitize_text_field($item['name']) : '',
            'icon'         => isset($item['icon']) ? esc_url_raw($item['icon']) : '',
            'link'         => isset($item['link']) ? esc_url_raw($item['link']) : '',
            'tag'          => isset($item['tag']) ? sanitize_text_field($item['tag']) : '',
            'description'  => isset($item['description']) ? sanitize_text_field($item['description']) : '',
        ];

        if (!empty($item['children']) && is_array($item['children'])) {
            $normalized['children'] = [];
            foreach ($item['children'] as $child_idx => $child_item) {
                $child = $this->sanitize_server_item($child_item, $path . '.children.' . $child_idx);
                if ($child) {
                    $normalized['children'][] = $child;
                }
            }
        }

        return $normalized;
    }

    // ======================== 页面渲染 ========================

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>MCSM 服务器状态设置</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('mcsm_settings_group');
                do_settings_sections('mcsm-server-status');
                submit_button('保存设置');
                ?>
            </form>

            <hr>
            <h2>使用方法</h2>
            <p>在任意页面或文章中插入短代码：</p>
            <code>[mcsm_server_status]</code>
            <p>可选地筛选某个节点：</p>
            <code>[mcsm_server_status daemon="你的DaemonID"]</code>
        </div>
        <?php
    }
}
