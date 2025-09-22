<?php

defined('ABSPATH') or die('Direct access not allowed.');

class OCC_Analytics {
    
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
    }
    
    public function trackConsentEvent($consent_data, $source) {
        $integrations = $this->settings->get('integrations', []);
        $plausible = $integrations['plausible'] ?? [];
        
        if (!($plausible['enabled'] ?? false)) {
            return;
        }
        
        $event_name = $plausible['event_name'] ?? 'cookie_consent';
        $action = $consent_data['action'] ?? '';
        
        if (empty($action)) {
            return;
        }
        
        $event_data = [
            'name' => $event_name,
            'url' => home_url($_SERVER['REQUEST_URI'] ?? '/'),
            'domain' => parse_url(home_url(), PHP_URL_HOST),
            'props' => [
                'action' => $action,
                'source' => $source,
                'categories' => $this->formatCategoriesForAnalytics($consent_data['categories'] ?? [])
            ]
        ];
        
        $this->sendToPlausible($event_data);
        
        $this->triggerDataLayerEvent($consent_data, $source);
    }
    
    private function formatCategoriesForAnalytics($categories) {
        $formatted = [];
        
        foreach ($categories as $category => $status) {
            $formatted[] = $category . '=' . $status;
        }
        
        return implode(';', $formatted);
    }
    
    private function sendToPlausible($event_data) {
        $plausible_url = 'https://plausible.io/api/event';
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ip = $this->getClientIP();
        
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => $user_agent,
            'X-Forwarded-For' => $ip
        ];
        
        wp_remote_post($plausible_url, [
            'body' => json_encode($event_data),
            'headers' => $headers,
            'timeout' => 5,
            'blocking' => false
        ]);
    }
    
    private function getClientIP() {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    private function triggerDataLayerEvent($consent_data, $source) {
        $integrations = $this->settings->get('integrations', []);
        $event_name = $integrations['datalayer_event'] ?? 'occ_consent_update';
        
        if (empty($event_name)) {
            return;
        }
        
        add_action('wp_footer', function() use ($consent_data, $source, $event_name) {
            echo "\n<script>\n";
            echo "window.dataLayer = window.dataLayer || [];\n";
            echo "window.dataLayer.push({\n";
            echo "  'event': " . json_encode($event_name) . ",\n";
            echo "  'occ_action': " . json_encode($consent_data['action'] ?? '') . ",\n";
            echo "  'occ_source': " . json_encode($source) . ",\n";
            echo "  'occ_categories': " . json_encode($consent_data['categories'] ?? []) . "\n";
            echo "});\n";
            echo "</script>\n";
        }, 999);
    }
    
}
