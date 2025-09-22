<?php
/**
 * Plugin Name: Open Cookie Consent
 * Plugin URI: https://github.com/dynumo/open-cookie-consent
 * Description: A privacy-first, GDPR-compliant cookie consent plugin with Google Consent Mode v2 support.
 * Version: 0.2.3
 * Author: Adam McBride
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: open-cookie-consent
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8.2
 * Requires PHP: 8.0
 * Network: true
 */

defined('ABSPATH') or die('Direct access not allowed.');

define('OCC_VERSION', '0.2.3');
define('OCC_PLUGIN_FILE', __FILE__);
define('OCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OCC_PLUGIN_URL', plugin_dir_url(__FILE__));

class OpenCookieConsent {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function init() {
        load_plugin_textdomain('open-cookie-consent', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        $this->loadDependencies();
        $this->initHooks();
    }
    
    private function loadDependencies() {
        require_once OCC_PLUGIN_DIR . 'includes/class-occ-core.php';
        require_once OCC_PLUGIN_DIR . 'includes/class-occ-settings.php';
        require_once OCC_PLUGIN_DIR . 'includes/class-occ-ui.php';
        require_once OCC_PLUGIN_DIR . 'includes/class-occ-consent-engine.php';
        require_once OCC_PLUGIN_DIR . 'includes/class-occ-scanner.php';
        require_once OCC_PLUGIN_DIR . 'includes/class-occ-analytics.php';
        require_once OCC_PLUGIN_DIR . 'includes/class-occ-shortcodes.php';
        require_once OCC_PLUGIN_DIR . 'admin/class-occ-admin.php';
    }
    
    private function initHooks() {
        OCC_Core::getInstance();
        OCC_Settings::getInstance();
        OCC_UI::getInstance();
        OCC_Consent_Engine::getInstance();
        OCC_Scanner::getInstance();
        OCC_Analytics::getInstance();
        OCC_Shortcodes::getInstance();
        
        if (is_admin()) {
            OCC_Admin::getInstance();
        }
    }
    
    public function activate() {
        $this->setDefaultSettings();
        wp_schedule_event(strtotime('tomorrow 3:00 AM'), 'daily', 'occ_daily_scan');
        $settings = get_option('occ_settings', []);
        $ocd_auto = $settings['scanner']['ocd_auto_update'] ?? true;
        if ($ocd_auto && !wp_next_scheduled('occ_monthly_ocd_update')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'monthly', 'occ_monthly_ocd_update');
        }
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('occ_daily_scan');
        wp_clear_scheduled_hook('occ_monthly_ocd_update');
    }
    
    // No database tables required; counters removed for simplicity and resilience.
    
    public static function get_default_settings() {
        return [
            'ui' => [
                'position' => 'modal_center',
                'theme' => 'auto',
                'color' => '#2797DD',
                'color_light' => '#2797DD',
                'color_dark' => '#2797DD',
                'radius' => 10,
                'links' => [
                    'privacy_page_id' => 0,
                    'cookie_page_id' => 0
                ],
                'conditional_banner' => true,
                'updated_notice_focus' => 'review'
            ],
            'categories' => [
                'necessary' => [
                    'label' => __('Necessary', 'open-cookie-consent'),
                    'description' => __('Required for basic functionality. Includes Functional and Security.', 'open-cookie-consent'),
                    'enabled' => true,
                    'locked' => true
                ],
                'functional' => [
                    'label' => __('Functional', 'open-cookie-consent'),
                    'description' => __('Preferences and features that are required for the site to function properly.', 'open-cookie-consent'),
                    'enabled' => true,
                    'locked' => true
                ],
                'security' => [
                    'label' => __('Security', 'open-cookie-consent'),
                    'description' => __('Security-related cookies. Treated as necessary.', 'open-cookie-consent'),
                    'enabled' => true,
                    'locked' => true
                ],
                'analytics' => [
                    'label' => __('Analytics', 'open-cookie-consent'),
                    'description' => __('Helps us understand how visitors interact with the website.', 'open-cookie-consent'),
                    'enabled' => true,
                    'locked' => false
                ],
                'personalization' => [
                    'label' => __('Personalisation', 'open-cookie-consent'),
                    'description' => __('Personalised content and features.', 'open-cookie-consent'),
                    'enabled' => true,
                    'locked' => false
                ],
                'marketing' => [
                    'label' => __('Marketing', 'open-cookie-consent'),
                    'description' => __('Advertising and marketing cookies.', 'open-cookie-consent'),
                    'enabled' => true,
                    'locked' => false
                ]
            ],
            'scanner' => [
                'interval' => 'daily',
                'time_local' => '03:00',
                'custom_cron' => '',
                'pages' => ['/'],
                'ocd_source_url' => 'https://raw.githubusercontent.com/jkwakman/Open-Cookie-Database/refs/heads/master/open-cookie-database.json',
                'ocd_auto_update' => true,
                'runtime_sampler' => false,
                'notify' => [
                    'email' => false,
                    'webhook' => ''
                ]
            ],
            'inventory' => [
                'version' => '',
                'cookies' => [],
                'knownNames' => []
            ],
            'integrations' => [
                'gcmv2' => true,
                'plausible' => [
                    'enabled' => false,
                    'event_name' => 'cookie_consent'
                ],
                'wp_consent_api' => [
                    'enabled' => true
                ],
                'datalayer_event' => 'occ_consent_update'
            ],
            'advanced' => [
                'custom_css' => '',
                'debug' => false,
                'cloudflare' => [
                    'token' => '',
                    'zone' => ''
                ]
            ]
        ];
    }

    private function setDefaultSettings() {
        $default_settings = self::get_default_settings();
        
        if (!get_option('occ_settings')) {
            update_option('occ_settings', $default_settings);
        }
    }
}

OpenCookieConsent::getInstance();
