<?php

defined('ABSPATH') or die('Direct access not allowed.');

class OCC_Settings {
    
    private static $instance = null;
    private $settings = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $this->settings = get_option('occ_settings', []);
    }
    
    public function get($key = null, $default = null) {
        if ($key === null) {
            return $this->settings;
        }
        
        $keys = explode('.', $key);
        $value = $this->settings;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public function set($key, $value) {
        $keys = explode('.', $key);
        $current = &$this->settings;
        
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
        $this->save();
    }
    
    public function update($newSettings) {
        $this->settings = $this->sanitizeSettings(array_merge($this->settings, $newSettings));
        $this->save();
    }
    
    private function save() {
        update_option('occ_settings', $this->settings);
    }
    
    private function sanitizeSettings($settings) {
        $sanitized = [];
        
        if (isset($settings['ui'])) {
            $ui = $settings['ui'];

            $baseColour = sanitize_hex_color($ui['color'] ?? ($ui['color_light'] ?? '#2797DD')) ?: '#2797DD';
            $colorLight = sanitize_hex_color($ui['color_light'] ?? $baseColour) ?: $baseColour;
            $colorDark = sanitize_hex_color($ui['color_dark'] ?? $colorLight) ?: $colorLight;

            $sanitized['ui'] = [
                'position' => sanitize_text_field($ui['position'] ?? 'modal_center'),
                'theme' => sanitize_text_field($ui['theme'] ?? 'auto'),
                'color' => $baseColour,
                'color_light' => $colorLight,
                'color_dark' => $colorDark,
                'radius' => intval($ui['radius'] ?? 10),
                'conditional_banner' => (bool)($ui['conditional_banner'] ?? true),
                'updated_notice_focus' => sanitize_text_field($ui['updated_notice_focus'] ?? 'review'),
                'links' => [
                    'privacy_page_id' => intval($ui['links']['privacy_page_id'] ?? 0),
                    'cookie_page_id' => intval($ui['links']['cookie_page_id'] ?? 0)
                ]
            ];
        }
        
        if (isset($settings['categories'])) {
            $sanitized['categories'] = [];
            foreach ($settings['categories'] as $key => $category) {
                $sanitized['categories'][sanitize_key($key)] = [
                    'label' => sanitize_text_field($category['label'] ?? ''),
                    'description' => sanitize_textarea_field($category['description'] ?? ''),
                    'enabled' => (bool)($category['enabled'] ?? true),
                    'locked' => (bool)($category['locked'] ?? false)
                ];
            }
        }
        
        if (isset($settings['scanner'])) {
            $sanitized['scanner'] = [
                'interval' => sanitize_text_field($settings['scanner']['interval'] ?? 'daily'),
                'time_local' => sanitize_text_field($settings['scanner']['time_local'] ?? '03:00'),
                'custom_cron' => sanitize_text_field($settings['scanner']['custom_cron'] ?? ''),
                'pages' => array_map('esc_url_raw', $settings['scanner']['pages'] ?? ['/']),
                'ocd_source_url' => esc_url_raw($settings['scanner']['ocd_source_url'] ?? ''),
                'ocd_auto_update' => (bool)($settings['scanner']['ocd_auto_update'] ?? true),
                'runtime_sampler' => (bool)($settings['scanner']['runtime_sampler'] ?? false),
                'notify' => [
                    'email' => (bool)($settings['scanner']['notify']['email'] ?? false),
                    'webhook' => esc_url_raw($settings['scanner']['notify']['webhook'] ?? '')
                ],
                'ocd_meta' => [
                    'updated_at' => sanitize_text_field($settings['scanner']['ocd_meta']['updated_at'] ?? ''),
                    'checksum' => sanitize_text_field($settings['scanner']['ocd_meta']['checksum'] ?? ''),
                    'vendors' => intval($settings['scanner']['ocd_meta']['vendors'] ?? 0),
                    'records' => intval($settings['scanner']['ocd_meta']['records'] ?? 0),
                    'mapped_domains' => intval($settings['scanner']['ocd_meta']['mapped_domains'] ?? 0)
                ],
                'custom_mappings' => $this->sanitizeCustomMappings($settings['scanner']['custom_mappings'] ?? [])
            ];
        }
        
        if (isset($settings['inventory'])) {
            $sanitized['inventory'] = [
                'version' => sanitize_text_field($settings['inventory']['version'] ?? ''),
                'cookies' => $this->sanitizeCookies($settings['inventory']['cookies'] ?? []),
                'knownNames' => array_map('sanitize_text_field', $settings['inventory']['knownNames'] ?? [])
            ];
        }
        
        if (isset($settings['integrations'])) {
            $sanitized['integrations'] = [
                'gcmv2' => (bool)($settings['integrations']['gcmv2'] ?? true),
                'plausible' => [
                    'enabled' => (bool)($settings['integrations']['plausible']['enabled'] ?? false),
                    'event_name' => sanitize_text_field($settings['integrations']['plausible']['event_name'] ?? 'cookie_consent')
                ],
                'wp_consent_api' => [
                    'enabled' => (bool)($settings['integrations']['wp_consent_api']['enabled'] ?? true)
                ],
                'datalayer_event' => sanitize_text_field($settings['integrations']['datalayer_event'] ?? 'occ_consent_update')
            ];
        }
        
        if (isset($settings['advanced'])) {
            $sanitized['advanced'] = [
                'custom_css' => wp_strip_all_tags($settings['advanced']['custom_css'] ?? ''),
                'test_cookie' => (bool)($settings['advanced']['test_cookie'] ?? false),
                'debug' => (bool)($settings['advanced']['debug'] ?? false),
                'cloudflare' => [
                    'token' => sanitize_text_field($settings['advanced']['cloudflare']['token'] ?? ''),
                    'zone' => sanitize_text_field($settings['advanced']['cloudflare']['zone'] ?? '')
                ]
            ];
        }
        
        return array_merge($this->settings, $sanitized);
    }
    
    private function sanitizeCustomMappings($mappings) {
        $out = [];
        if (!is_array($mappings)) return $out;
        foreach ($mappings as $m) {
            $type = in_array(($m['type'] ?? ''), ['domain','cookie_name','script_url']) ? $m['type'] : '';
            $value = sanitize_text_field($m['value'] ?? '');
            $category = sanitize_text_field($m['category'] ?? '');
            if (!$type || !$value || !$category) continue;
            $out[] = [
                'type' => $type,
                'value' => $value,
                'category' => $category,
                'vendor' => sanitize_text_field($m['vendor'] ?? ''),
                'description' => sanitize_text_field($m['description'] ?? '')
            ];
        }
        return $out;
    }

    private function sanitizeCookies($cookies) {
        $sanitized = [];
        
        foreach ($cookies as $cookie) {
            $sanitized[] = [
                'name' => sanitize_text_field($cookie['name'] ?? ''),
                'category' => sanitize_text_field($cookie['category'] ?? 'necessary'),
                'vendor' => sanitize_text_field($cookie['vendor'] ?? ''),
                'description' => sanitize_textarea_field($cookie['description'] ?? ''),
                'source' => esc_url_raw($cookie['source'] ?? ''),
                'confidence' => floatval($cookie['confidence'] ?? 0),
                'manual' => !empty($cookie['manual'])
            ];
        }
        
        return $sanitized;
    }
    
    public function export() {
        return $this->settings;
    }
    
    public function import($settings) {
        $this->settings = $this->sanitizeSettings($settings);
        $this->save();
        return true;
    }
    
    public function reset() {
        delete_option('occ_settings');
        $this->loadSettings();
        return true;
    }
}
