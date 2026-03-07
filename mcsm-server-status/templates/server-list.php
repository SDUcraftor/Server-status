<?php
/**
 * 服务器列表模板
 * 变量: $servers (array), $daemon_id (string)
 *
 * 样式参考主题 post-list-thumb 卡片风格，保持与博客文章列表一致的视觉效果
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mcsm_format_uptime')) {
    /**
     * 格式化运行时长（毫秒 -> 可读字符串）
     */
    function mcsm_format_uptime($ms) {
        $s = intval($ms / 1000);
        if ($s < 60) return $s . '秒';
        $m = intval($s / 60);
        if ($m < 60) return $m . '分钟';
        $h = intval($m / 60); $rm = $m % 60;
        if ($h < 24) return $h . '小时' . ($rm > 0 ? $rm . '分' : '');
        $d = intval($h / 24); $rh = $h % 24;
        return $d . '天' . ($rh > 0 ? $rh . '小时' : '');
    }
}

$status_labels = [-1=>'维护中', 0=>'已关闭', 1=>'关闭中', 2=>'启动中', 3=>'运行中'];
$default_icon = 'https://patchwiki.biligame.com/images/mc/thumb/5/53/smk9nesqj6bkd5qyd718xxhocic6et0.png/150px-Grass_Block_JE7_BE6.png';
?>
<div class="mcsm-server-list" data-daemon="<?php echo esc_attr($daemon_id); ?>"><?php
if (empty($servers)) : ?>
<div class="mcsm-empty"><p>暂无服务器数据，请检查插件配置。</p></div><?php
else :
    foreach ($servers as $index => $server) :
        $icon         = !empty($server['icon']) ? $server['icon'] : $default_icon;
        $status_code  = intval($server['statusCode']);
        $status_label = isset($status_labels[$status_code]) ? $status_labels[$status_code] : '未知';
        $status_class = esc_attr($server['statusClass']);
        $name_html    = esc_html($server['name']);
        if (!empty($server['link'])) {
            $name_html = '<a href="' . esc_url($server['link']) . '">' . $name_html . '</a>';
        }

        $players_text = '';
        if ($server['currentPlayers'] >= 0) {
            $players_text = $server['currentPlayers'];
            if ($server['maxPlayers'] > 0) $players_text .= '/' . $server['maxPlayers'];
        }

        $uptime_text = '';
        if ($status_code === 3 && $server['elapsed'] > 0) {
            $uptime_text = mcsm_format_uptime($server['elapsed']);
        }

        // 构建 meta spans
        $meta = '';
        if ($players_text !== '') {
            $meta .= '<span class="mcsm-mi"><i class="fa fa-regular fa-user"></i>' . esc_html($players_text) . '</span>';
        }
        if ($uptime_text !== '') {
            $meta .= '<span class="mcsm-mi"><i class="fa fa-solid fa-stopwatch"></i>' . esc_html($uptime_text) . '</span>';
        }
        if (!empty($server['version'])) {
            $meta .= '<span class="mcsm-mi"><i class="fa fa-regular fa-tag"></i>' . esc_html($server['version']) . '</span>';
        }
        if (!empty($server['tag'])) {
            $meta .= '<span class="mcsm-mi"><i class="fa fa-regular fa-location-dot"></i>' . esc_html($server['tag']) . '</span>';
        }
        $meta .= '<span class="mcsm-mi mcsm-meta-status"><i class="fa fa-circle mcsm-status-indicator"></i>' . esc_html($status_label) . '</span>';

        $desc_html = '';
        if (!empty($server['description'])) {
            $desc_html = '<div class="mcsm-server-desc">' . esc_html($server['description']) . '</div>';
        }
?><div class="mcsm-card mcsm-status-<?php echo $status_class; ?>" data-uuid="<?php echo esc_attr($server['instanceUuid']); ?>"><div class="mcsm-inner"><img class="mcsm-icon" src="<?php echo esc_url($icon); ?>" alt="<?php echo esc_attr($server['name']); ?>"><div class="mcsm-info"><div class="mcsm-name"><?php echo $name_html; ?></div><div class="mcsm-meta"><?php echo $meta; ?></div><?php echo $desc_html; ?></div></div></div><?php
    endforeach;
endif;

if (!empty($debug_trace) && is_array($debug_trace)) : ?>
<details class="mcsm-debug-panel"><summary>调试: 查看后端请求（已隐藏 API Key）</summary><pre><?php echo esc_html(wp_json_encode($debug_trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre></details><?php
endif;
?></div>
