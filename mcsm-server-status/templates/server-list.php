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
        $seconds = intval($ms / 1000);
        if ($seconds < 60) {
            return $seconds . '秒';
        }
        $minutes = intval($seconds / 60);
        if ($minutes < 60) {
            return $minutes . '分钟';
        }
        $hours = intval($minutes / 60);
        $mins  = $minutes % 60;
        if ($hours < 24) {
            return $hours . '小时' . ($mins > 0 ? $mins . '分' : '');
        }
        $days  = intval($hours / 24);
        $hrs   = $hours % 24;
        return $days . '天' . ($hrs > 0 ? $hrs . '小时' : '');
    }
}

/**
 * 状态码对应文本
 */
$status_labels = [
    -1 => '维护中',
    0  => '已关闭',
    1  => '关闭中',
    2  => '启动中',
    3  => '运行中',
];

$default_icon = 'https://patchwiki.biligame.com/images/mc/thumb/5/53/smk9nesqj6bkd5qyd718xxhocic6et0.png/150px-Grass_Block_JE7_BE6.png';
?>

<div class="mcsm-server-list" data-daemon="<?php echo esc_attr($daemon_id); ?>">
<?php if (empty($servers)) : ?>
    <div class="mcsm-empty">
        <p>暂无服务器数据，请检查插件配置。</p>
    </div>
<?php else : ?>
    <?php foreach ($servers as $index => $server) :
        $icon = !empty($server['icon']) ? $server['icon'] : $default_icon;
        $status_code  = intval($server['statusCode']);
        $status_label = isset($status_labels[$status_code]) ? $status_labels[$status_code] : '未知';
        $status_class = esc_attr($server['statusClass']);

        // 在线人数文本
        $players_text = '';
        if ($server['currentPlayers'] >= 0) {
            $players_text = $server['currentPlayers'];
            if ($server['maxPlayers'] > 0) {
                $players_text .= '/' . $server['maxPlayers'];
            }
        }

        // 运行时长
        $uptime_text = '';
        if ($status_code === 3 && $server['elapsed'] > 0) {
            $uptime_text = mcsm_format_uptime($server['elapsed']);
        }
    ?>
    <article class="post post-list-thumb post-list-show mcsm-server-card mcsm-status-<?php echo $status_class; ?>"
             data-uuid="<?php echo esc_attr($server['instanceUuid']); ?>"
             style="height: 154px;">
        <div class="mcsm-post-content">
            <!-- 服务器图标 -->
            <div class="img" style="height:64px;width:64px;position:absolute;left:45px;top:45px;" name="ServerImg">
                <img id="favicon_<?php echo esc_attr($index); ?>"
                     style="height:64px;width:64px;"
                     src="<?php echo esc_url($icon); ?>"
                     alt="<?php echo esc_attr($server['name']); ?>">
            </div>

            <!-- 服务器信息 -->
            <div class="mcsm-server-text" style="margin-left: 154px">
                <h2 class="entry-title">
                    <?php if (!empty($server['link'])) : ?>
                        <a href="<?php echo esc_url($server['link']); ?>"><?php echo esc_html($server['name']); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($server['name']); ?>
                    <?php endif; ?>
                </h2>

                <div class="post-meta">
                    <div class="mcs-status mcs-status-<?php echo $status_class; ?>" name="<?php echo esc_attr($status_code); ?>">
                        <?php if ($players_text !== '') : ?>
                        <span class="mcsm-meta-players">
                            <i class="fa fa-regular fa-user"></i><em class="mcsm-players-text"><?php echo esc_html($players_text); ?></em>
                        </span>
                        <?php endif; ?>

                        <?php if ($uptime_text !== '') : ?>
                        <span style="margin-left: 10px;" class="mcsm-meta-uptime">
                            <i class="fa fa-solid fa-stopwatch"></i><em class="mcsm-uptime-text"><?php echo esc_html($uptime_text); ?></em>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($server['version'])) : ?>
                        <span style="margin-left: 10px;" class="mcsm-meta-version">
                            <i class="fa fa-regular fa-tag"></i><em class="mcsm-version-text"><?php echo esc_html($server['version']); ?></em>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($server['tag'])) : ?>
                        <span style="margin-left: 10px;" class="mcsm-meta-tag">
                            <i class="fa fa-regular fa-location-dot"></i><?php echo esc_html($server['tag']); ?>
                        </span>
                        <?php endif; ?>

                        <span style="margin-left: 10px;" class="mcsm-meta-status mcsm-status-badge">
                            <i class="fa fa-circle mcsm-status-indicator"></i><em class="mcsm-status-text"><?php echo esc_html($status_label); ?></em>
                        </span>
                    </div>
                </div>

                <div class="float-content">
                    <?php if (!empty($server['description'])) : ?>
                    <p class="mcsm-server-desc"><?php echo esc_html($server['description']); ?></p>
                    <?php else : ?>
                    <p></p>
                    <?php endif; ?>
                </div>

                <footer class="entry-footer"></footer>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
<?php endif; ?>
</div>
