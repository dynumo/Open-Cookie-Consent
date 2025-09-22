<?php

defined('ABSPATH') or die('Direct access not allowed.');

class OCC_Core {
    
    private static $instance = null;
    private $settings;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = OCC_Settings::getInstance();
        $this->initHooks();
    }
    
    private function initHooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_head', [$this, 'outputGCMDefaults'], 1);
        add_action('wp_footer', [$this, 'outputConsentData']);
        add_action('init', [$this, 'loadTextDomain']);
        add_action('init', [$this, 'ensureSchedules']);
        add_action('occ_daily_scan', [$this, 'runScheduledScan']);
        add_filter('cron_schedules', [$this, 'registerCronSchedules']);
        add_action('occ_monthly_ocd_update', [$this, 'runMonthlyOCDUpdate']);
        
        add_action('wp_ajax_occ_manual_scan', [$this, 'handleManualScan']);
        add_action('wp_ajax_occ_update_ocd', [$this, 'handleOCDUpdate']);
    }

    public function registerCronSchedules($schedules) {
        if (!isset($schedules['monthly'])) {
            $schedules['monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Once Monthly', 'open-cookie-consent')
            ];
        }
        return $schedules;
    }

    public function runMonthlyOCDUpdate() {
        $scanner = OCC_Scanner::getInstance();
        $scanner->updateOCD();
    }

    public function ensureSchedules() {
        $scanner = OCC_Settings::getInstance()->get('scanner', []);
        $ocd_auto = $scanner['ocd_auto_update'] ?? true;
        if ($ocd_auto) {
            if (!wp_next_scheduled('occ_monthly_ocd_update')) {
                wp_schedule_event(time() + DAY_IN_SECONDS, 'monthly', 'occ_monthly_ocd_update');
            }
        } else {
            wp_clear_scheduled_hook('occ_monthly_ocd_update');
        }
    }
    
    public function enqueueScripts() {
        wp_enqueue_script(
            'occ-consent-engine',
            OCC_PLUGIN_URL . 'assets/js/consent-engine.js',
            [],
            OCC_VERSION,
            true
        );
        
        wp_enqueue_script(
            'occ-ui',
            OCC_PLUGIN_URL . 'assets/js/ui.js',
            ['occ-consent-engine'],
            OCC_VERSION,
            true
        );
        
        wp_enqueue_style(
            'occ-styles',
            OCC_PLUGIN_URL . 'assets/css/styles.css',
            [],
            OCC_VERSION
        );
        
        $ui_settings = $this->settings->get('ui', []);
        $categories = apply_filters('occ_categories', $this->settings->get('categories', []));
        $integrations = $this->settings->get('integrations', []);
        $inventory = $this->settings->get('inventory', []);
        
        $localize_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('occ_nonce'),
            'settings' => [
                'ui' => $ui_settings,
                'categories' => $categories,
                'gcmv2' => $integrations['gcmv2'] ?? true,
                'plausible' => $integrations['plausible'] ?? [],
                'integrations' => [
                    'wp_consent_api' => [
                        'enabled' => $integrations['wp_consent_api']['enabled'] ?? true
                    ]
                ],
                'datalayerEvent' => $integrations['datalayer_event'] ?? 'occ_consent_update',
                'advanced' => $this->settings->get('advanced', [])
            ],
            'inventory' => [
                'version' => $inventory['version'] ?? '',
                'hasNonEssential' => $this->hasNonEssentialCookies()
            ],
            'strings' => [
                'banner_title' => __('Cookie Consent', 'open-cookie-consent'),
                'banner_text' => __('We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'open-cookie-consent'),
                'accept_all' => __('Accept All', 'open-cookie-consent'),
                'reject_nonessential' => __('Reject Non-Essential', 'open-cookie-consent'),
                'preferences' => __('Preferences', 'open-cookie-consent'),
                'save_preferences' => __('Save Preferences', 'open-cookie-consent'),
                'updated_cookies_title' => __('Updated Cookie Use', 'open-cookie-consent'),
                'updated_cookies_text' => __('We\'ve updated our cookie use. New cookies were added since you set your preferences.', 'open-cookie-consent'),
                'review_cookies' => __('Review Cookies', 'open-cookie-consent'),
                'keep_current' => __('Keep Current Settings', 'open-cookie-consent'),
                'cookie_preferences' => __('Cookie Preferences', 'open-cookie-consent'),
                'all_cookies_accepted' => __('All cookies accepted', 'open-cookie-consent'),
                'non_essential_cookies_rejected' => __('Non-essential cookies rejected', 'open-cookie-consent'),
                'cookie_preferences_saved' => __('Cookie preferences saved', 'open-cookie-consent'),
                'current_settings_kept' => __('Current settings kept', 'open-cookie-consent')
            ]
        ];
        
        wp_localize_script('occ-consent-engine', 'occData', $localize_data);
    }
    
    public function outputGCMDefaults() {
        $integrations = $this->settings->get('integrations', []);
        
        if (!($integrations['gcmv2'] ?? true)) {
            return;
        }
        
        echo "\n<!-- Open Cookie Consent - Google Consent Mode v2 Defaults -->\n";
        echo "<script>\n";
        echo "window.dataLayer = window.dataLayer || [];\n";
        echo "function gtag(){dataLayer.push(arguments);}\n";
        echo "gtag('consent', 'default', {\n";
        echo "  'ad_storage': 'denied',\n";
        echo "  'ad_user_data': 'denied',\n";
        echo "  'ad_personalization': 'denied',\n";
        echo "  'analytics_storage': 'denied',\n";
        echo "  'security_storage': 'granted'\n";
        echo "});\n";
        echo "</script>\n";
    }
    
    public function outputConsentData() {
        $inventory = $this->settings->get('inventory', []);
        
        echo "\n<!-- Open Cookie Consent - Inventory Data -->\n";
        echo "<script>\n";
        echo "window.occInventoryVersion = " . json_encode($inventory['version'] ?? '') . ";\n";
        echo "</script>\n";
    }
    
    private function hasNonEssentialCookies() {
        $inventory = $this->settings->get('inventory', []);
        $cookies = $inventory['cookies'] ?? [];
        
        foreach ($cookies as $cookie) {
            $cat = strtolower($cookie['category'] ?? '');
            if (!in_array($cat, ['necessary','functional','security',''])) {
                return true;
            }
        }
        // Fallback before first scan: quick heuristic via enqueued scripts
        if (empty($inventory['version'])) {
            if (class_exists('OCC_Scanner') && method_exists('OCC_Scanner', 'getInstance') && method_exists('OCC_Scanner', 'hasLikelyNonEssential')) {
                $scanner = OCC_Scanner::getInstance();
                return (bool)$scanner->hasLikelyNonEssential();
            }
        }
        return false;
    }
    
    public function runScheduledScan() {
        $scanner = OCC_Scanner::getInstance();
        $scanner->performScan();
    }
    
    public function handleManualScan() {
        check_ajax_referer('occ_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'open-cookie-consent'));
        }
        
        $scanner = OCC_Scanner::getInstance();
        $results = $scanner->performScan();
        
        wp_send_json_success($results);
    }
    
    public function handleOCDUpdate() {
        check_ajax_referer('occ_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'open-cookie-consent'));
        }
        
        $scanner = OCC_Scanner::getInstance();
        $result = $scanner->updateOCD();
        
        if ($result) {
            wp_send_json_success(['message' => __('OCD database updated successfully', 'open-cookie-consent')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update OCD database', 'open-cookie-consent')]);
        }
    }
    
    public function loadTextDomain() {
        load_plugin_textdomain('open-cookie-consent', false, dirname(plugin_basename(OCC_PLUGIN_FILE)) . '/languages');
    }
}
