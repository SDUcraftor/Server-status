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
        
        // Removed redundant link wrapping here as it is handled in template now

        $players_text = '--';
        if ($server['currentPlayers'] >= 0) {
            $players_text = $server['currentPlayers'];
            if ($server['maxPlayers'] > 0) $players_text .= '/' . $server['maxPlayers'];
        }

        $uptime_text = '';
        if ($status_code === 3 && $server['elapsed'] > 0) {
            $uptime_text = mcsm_format_uptime($server['elapsed']);
        }
        
        $has_children = !empty($server['children']) && is_array($server['children']);
?>
    <article class="post post-list-thumb post-list-show mcsm-server-card mcsm-status-<?php echo $status_class; ?>" 
             data-uuid="<?php echo esc_attr($server['instanceUuid']); ?>" 
             data-showing-uuid="<?php echo esc_attr($server['instanceUuid']); ?>"
             itemscope="" itemtype="http://schema.org/BlogPosting" 
             style="height: auto; min-height: 124px; will-change: auto;">
        
        <div class="post-content">
            <div class="img mcsm-server-avatar" name="ServerImg">
                <img class="mcsm-icon" id="favicon_<?php echo $index; ?>" src="<?php echo esc_url($icon); ?>" alt="<?php echo esc_attr($server['name']); ?>">
            </div>
            <div class="mcsm-server-main">
                <h2 class="entry-title">
                    <?php if (!empty($server['link'])): ?>
                        <a href="<?php echo esc_url($server['link']); ?>"><?php echo $name_html; ?></a>
                    <?php else: ?>
                        <?php echo $name_html; ?>
                    <?php endif; ?>
                </h2>
                <div class="post-meta">
                    <div class="mcs-status" name="<?php echo $status_code; ?>">
                        <span class="mcsm-meta-item mcsm-status-badge">
                            <i class="fa fa-circle mcsm-status-indicator"></i><em class="mcsm-status-text"><?php echo esc_html($status_label); ?></em>
                        </span>

                        <span class="mcsm-meta-item mcsm-players">
                            <i class="fa fa-regular fa-user"></i><em class="mcsm-players-text"><?php echo esc_html($players_text); ?></em>
                        </span>

                        <span style="margin-left: 10px; <?php echo empty($uptime_text) ? 'display:none;' : ''; ?>" class="mcsm-meta-item mcsm-uptime">
                            <i class="fa fa-solid fa-stopwatch"></i><em class="mcsm-uptime-text"><?php echo esc_html($uptime_text); ?></em>
                        </span>

                        <?php if (!empty($server['version'])): ?>
                        <span style="margin-left: 10px;" class="mcsm-meta-item mcsm-version">
                            <i class="fa fa-regular fa-tag"></i><em class="mcsm-version-text"><?php echo esc_html($server['version']); ?></em>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($server['tag'])): ?>
                        <span style="margin-left: 10px;" class="mcsm-meta-item mcsm-location">
                            <i class="fa fa-regular fa-location-dot"></i><?php echo esc_html($server['tag']); ?>
                        </span>
                        <?php endif; ?>

                    </div>
                </div>
                <div class="float-content">
                    <p class="mcsm-server-desc"><?php echo esc_html($server['description']); ?></p>
                </div>
                <?php if ($has_children): ?>
                <div class="mcsm-sub-servers">
                    <?php
                    // Parent (Group) Item
                    $p_json = htmlspecialchars(json_encode($server), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="mcsm-sub-item active mcsm-status-<?php echo $server['statusClass']; ?>" 
                         onclick="mcsmSwitchChild(this)" 
                         data-uuid="<?php echo esc_attr($server['instanceUuid']); ?>"
                         data-payload="<?php echo $p_json; ?>">
                        <span class="mcsm-sub-name"><?php echo esc_html($server['name']); ?></span>
                        <span class="mcsm-sub-indicator"></span>
                    </div>

                    <?php foreach ($server['children'] as $child): 
                        $c_json = htmlspecialchars(json_encode($child), ENT_QUOTES, 'UTF-8');
                        $c_status = isset($child['statusClass']) ? $child['statusClass'] : 'unknown';
                    ?>
                    <div class="mcsm-sub-item mcsm-status-<?php echo $c_status; ?>" 
                         onclick="mcsmSwitchChild(this)" 
                         data-uuid="<?php echo esc_attr($child['instanceUuid']); ?>"
                         data-payload="<?php echo $c_json; ?>">
                        <span class="mcsm-sub-name"><?php echo esc_html($child['name']); ?></span>
                        <span class="mcsm-sub-indicator"></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <footer class="entry-footer"></footer>
            </div>
        </div>  
    </article>
<?php
    endforeach;
endif;

if (!empty($debug_trace) && is_array($debug_trace)) : ?>
<details class="mcsm-debug-panel"><summary>调试: 查看后端请求 + MCSM 输出（已隐藏 API Key）</summary><pre><?php echo esc_html(wp_json_encode($debug_trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre></details><?php
endif;
?></div>
