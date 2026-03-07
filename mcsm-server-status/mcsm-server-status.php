<?php
/**
 * Plugin Name: MCSM Server Status
 * Plugin URI:  https://github.com/sduweb/mcsm-server-status
 * Description: 通过 MCSManager API 在 WordPress 页面展示 Minecraft 服务器状态列表
 * Version:     1.0.0
 * Author:      SDUWeb
 * License:     GPL-2.0+
 * Text Domain: mcsm-server-status
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MCSM_SS_VERSION', '1.0.0');
define('MCSM_SS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCSM_SS_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载核心类
require_once MCSM_SS_PLUGIN_DIR . 'includes/class-mcsm-api.php';
require_once MCSM_SS_PLUGIN_DIR . 'includes/class-mcsm-settings.php';

/**
 * 插件主类
 */
class MCSM_Server_Status {

    private static $instance = null;
    private $api;
    private $settings;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api      = new MCSM_API();
        $this->settings = new MCSM_Settings();

        add_shortcode('mcsm_server_status', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_mcsm_get_status', [$this, 'ajax_get_status']);
        add_action('wp_ajax_nopriv_mcsm_get_status', [$this, 'ajax_get_status']);
    }

    /**
     * 注册前端资源
     */
    public function enqueue_assets() {
        wp_register_style(
            'mcsm-server-status',
            MCSM_SS_PLUGIN_URL . 'assets/css/server-status.css',
            [],
            MCSM_SS_VERSION
        );
        wp_register_script(
            'mcsm-server-status',
            MCSM_SS_PLUGIN_URL . 'assets/js/server-status.js',
            [],
            MCSM_SS_VERSION,
            true
        );
        wp_localize_script('mcsm-server-status', 'mcsmConfig', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mcsm_status_nonce'),
            'interval' => intval(get_option('mcsm_refresh_interval', 30)) * 1000,
        ]);
    }

    /**
     * 短代码渲染
     * 用法: [mcsm_server_status] 或 [mcsm_server_status daemon="xxx"]
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'daemon'  => '',
            'debug'   => '0',
            'nocache' => '0',
        ], $atts, 'mcsm_server_status');

        wp_enqueue_style('mcsm-server-status');
        wp_enqueue_script('mcsm-server-status');

        $daemon_id = !empty($atts['daemon']) ? sanitize_text_field($atts['daemon']) : '';
        $debug_mode = in_array((string) $atts['debug'], ['1', 'true', 'yes'], true);
        $no_cache = in_array((string) $atts['nocache'], ['1', 'true', 'yes'], true);

        // 首次加载时直接从后端获取数据（SEO友好 + 避免闪烁）
        $servers = $this->api->get_instances($daemon_id, $debug_mode, $no_cache);
        $debug_trace = $debug_mode ? $this->api->get_debug_trace() : [];

        ob_start();
        include MCSM_SS_PLUGIN_DIR . 'templates/server-list.php';
        return ob_get_clean();
    }

    /**
     * AJAX 接口 —— 前端轮询用
     */
    public function ajax_get_status() {
        check_ajax_referer('mcsm_status_nonce', 'nonce');

        $daemon_id = isset($_GET['daemon']) ? sanitize_text_field($_GET['daemon']) : '';
        $debug_mode = isset($_GET['debug']) && in_array((string) $_GET['debug'], ['1', 'true', 'yes'], true);
        $no_cache = isset($_GET['nocache']) && in_array((string) $_GET['nocache'], ['1', 'true', 'yes'], true);

        $servers = $this->api->get_instances($daemon_id, $debug_mode, $no_cache);
        if ($debug_mode) {
            wp_send_json_success([
                'servers' => $servers,
                'debug'   => $this->api->get_debug_trace(),
            ]);
        }

        wp_send_json_success($servers);
    }
}

// 启动插件
add_action('plugins_loaded', ['MCSM_Server_Status', 'get_instance']);

