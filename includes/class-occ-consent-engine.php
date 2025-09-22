<?php

defined('ABSPATH') or die('Direct access not allowed.');

class OCC_Consent_Engine {
    
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
        add_action('wp_ajax_occ_update_consent', [$this, 'handleConsentUpdate']);
        add_action('wp_ajax_nopriv_occ_update_consent', [$this, 'handleConsentUpdate']);
        add_action('wp_footer', [$this, 'outputScriptGating'], 999);
        
        // WP Consent API integration
        if (function_exists('wp_has_consent') && $this->isWpConsentApiEnabled()) {
            add_filter('wp_get_consent_type', [$this, 'setConsentType']);
            add_action('wp_footer', [$this, 'outputWpConsentIntegration'], 5);
        }
    }
    
    public function handleConsentUpdate() {
        check_ajax_referer('occ_nonce', 'nonce');
        
        $consent_data = json_decode(stripslashes($_POST['consent'] ?? '{}'), true);
        $source = sanitize_text_field($_POST['source'] ?? '');
        
        if (!is_array($consent_data)) {
            wp_send_json_error(['message' => __('Invalid consent data', 'open-cookie-consent')]);
        }
        
        $analytics = OCC_Analytics::getInstance();
        $analytics->trackConsentEvent($consent_data, $source);
        
        do_action('occ_after_consent_update', [
            'consent' => $consent_data,
            'source' => $source,
            'timestamp' => time()
        ]);
        
        wp_send_json_success(['message' => __('Consent updated successfully', 'open-cookie-consent')]);
    }
    
    public function outputScriptGating() {
        echo "\n<!-- Open Cookie Consent - Script Gating -->\n";
        echo "<script>\n";
        echo "document.addEventListener('DOMContentLoaded', function() {\n";
        echo "  if (typeof occ !== 'undefined' && occ.consent) {\n";
        echo "    occ.consent.processGatedScripts();\n";
        echo "  }\n";
        echo "});\n";
        echo "</script>\n";
    }
    
    public function getConsentState() {
        return [
            'version' => $this->settings->get('inventory.version', ''),
            'categories' => $this->getDefaultCategoryStates()
        ];
    }
    
    private function getDefaultCategoryStates() {
        $categories = $this->settings->get('categories', []);
        $states = [];
        
        foreach ($categories as $key => $category) {
            if ($category['locked'] ?? false) {
                $states[$key] = 'granted';
            } else {
                $states[$key] = 'denied';
            }
        }
        
        return $states;
    }
    
    /**
     * WP Consent API Integration
     */
    private function isWpConsentApiEnabled() {
        $integrations = $this->settings->get('integrations', []);
        return $integrations['wp_consent_api']['enabled'] ?? true;
    }
    
    public function setConsentType($consent_type) {
        // We're a consent management platform, so we use optin
        return 'optin';
    }
    
    public function outputWpConsentIntegration() {
        if (!function_exists('wp_has_consent')) {
            return;
        }
        
        // Get current consent state to sync with WP Consent API
        $current_state = $this->getConsentState();
        
        echo "\n<!-- Open Cookie Consent - WP Consent API Integration -->\n";
        echo "<script>\n";
        echo "document.addEventListener('DOMContentLoaded', function() {\n";
        echo "  if (typeof window.wp_set_consent === 'function') {\n";
        echo "    // Sync current state with WP Consent API\n";
        echo "    window.occ.consent.syncWithWpConsentApi();\n";
        echo "    \n";
        echo "    // Listen for OCC consent changes and sync with WP Consent API\n";
        echo "    document.addEventListener('occ:consent_updated', function(e) {\n";
        echo "      window.occ.consent.syncWithWpConsentApi();\n";
        echo "    });\n";
        echo "  }\n";
        echo "});\n";
        echo "</script>\n";
    }
    
    /**
     * Map OCC categories to WP Consent API categories
     */
    private function mapToWpConsentCategories($occ_categories) {
        $mapping = [
            'functional' => 'functional',
            'necessary' => 'functional',
            'security' => 'functional',
            'analytics' => 'statistics',
            'personalization' => 'preferences',
            'marketing' => 'marketing'
        ];
        
        $wp_consent = [];
        foreach ($occ_categories as $occ_cat => $status) {
            if (isset($mapping[$occ_cat])) {
                $wp_cat = $mapping[$occ_cat];
                // Convert granted/denied to allow/deny
                $wp_status = ($status === 'granted') ? 'allow' : 'deny';
                $wp_consent[$wp_cat] = $wp_status;
            }
        }
        
        return $wp_consent;
    }
}
