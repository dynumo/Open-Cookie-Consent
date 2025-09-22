<?php

defined('ABSPATH') or die('Direct access not allowed.');

class OCC_Scanner {
    
    private static $instance = null;
    private $settings;
    private $ocd_data = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = OCC_Settings::getInstance();
        $this->loadOCD();
    }
    
    private function loadOCD() {
        $ocd_file = OCC_PLUGIN_DIR . 'data/ocd.json';
        if (!file_exists($ocd_file)) { return; }
        $raw = json_decode(file_get_contents($ocd_file), true);
        if (!$raw) { return; }
        $this->ocd_data = $this->normalizeOCD($raw);
    }

    private function normalizeOCD($raw) {
        // Support legacy format: { cookies: [ {name, category, vendor, ...} ] }
        if (isset($raw['cookies']) && is_array($raw['cookies'])) {
            $domains = [];
            foreach ($raw['cookies'] as $c) {
                if (!empty($c['domain'])) {
                    $domains[$c['domain']] = [
                        'vendor' => $c['vendor'] ?? '',
                        'category' => $c['category'] ?? ''
                    ];
                }
            }
            return [ 'domains' => $domains ];
        }
        // New format: top-level map of Vendor => [ { category, cookie, domain, ... } ]
        $domains = [];
        foreach ($raw as $vendor => $list) {
            if (!is_array($list)) continue;
            foreach ($list as $entry) {
                $domain = $entry['domain'] ?? '';
                if ($domain === '') continue;
                $domains[$domain] = [
                    'vendor' => $vendor,
                    'category' => $entry['category'] ?? ''
                ];
            }
        }
        return [ 'domains' => $domains ];
    }
    
    public function performScan() {
        $scanner_settings = $this->settings->get('scanner', []);
        $pages = $scanner_settings['pages'] ?? ['/'];
        
        $detected_cookies = [];
        
        foreach ($pages as $page) {
            $page_cookies = $this->scanPage($page);
            $detected_cookies = array_merge($detected_cookies, $page_cookies);
        }
        
        $detected_cookies = array_merge($detected_cookies, $this->detectLoggedInCookies());
        
        $detected_cookies = $this->deduplicateCookies($detected_cookies);

        $existingInventory = $this->settings->get('inventory', []);
        $existingCookies = $existingInventory['cookies'] ?? [];

        $existingMap = [];
        $manualMap = [];
        foreach ($existingCookies as $cookie) {
            $key = $this->makeCookieKey($cookie);
            $existingMap[$key] = $cookie;
            if (!empty($cookie['manual'])) {
                $manualMap[$key] = $cookie;
            }
        }

        $categorized_cookies = $this->categorizeCookies($detected_cookies);

        $combinedMap = [];
        foreach ($categorized_cookies as $cookie) {
            $key = $this->makeCookieKey($cookie);
            if (isset($existingMap[$key])) {
                $existing = $existingMap[$key];
                if (!empty($existing['description'])) {
                    $cookie['description'] = $existing['description'];
                }
                if (!empty($existing['vendor']) && empty($cookie['vendor'])) {
                    $cookie['vendor'] = $existing['vendor'];
                }
                if (!empty($existing['manual'])) {
                    $cookie['manual'] = true;
                }
            }
            $combinedMap[$key] = $cookie;
        }

        foreach ($manualMap as $key => $manualCookie) {
            $manualCookie['manual'] = true;
            $combinedMap[$key] = $manualCookie;
        }

        $finalCookies = array_values($combinedMap);

        $inventory = $this->buildInventory($finalCookies);

        $this->settings->set('inventory', $inventory);
        
        do_action('occ_after_scan', [
            'cookies' => $finalCookies,
            'inventory' => $inventory,
            'timestamp' => time()
        ]);
        
        $this->sendNotifications($inventory);
        
        return $inventory;
    }
    
    private function scanPage($page_path) {
        $cookies = [];
        
        $url = home_url($page_path);
        
        $cookies = array_merge($cookies, $this->staticScan($url));
        
        $scanner_settings = $this->settings->get('scanner', []);
        if ($scanner_settings['runtime_sampler'] ?? false) {
            $cookies = array_merge($cookies, $this->runtimeScan($url));
        }
        
        return $cookies;
    }
    
    private function staticScan($url) {
        $cookies = [];
        
        $cookies = array_merge($cookies, $this->scanEnqueuedScripts());
        $cookies = array_merge($cookies, $this->scanPageContent($url));
        
        return $cookies;
    }
    
    private function detectLoggedInCookies() {

        $detected = [];

        if (empty($_COOKIE) && !is_user_logged_in()) {

            return $detected;

        }



        $site_url = home_url('/');



        $patterns = [

            'wordpress_logged_in_' => [

                'name' => 'wordpress_logged_in_*',

                'category' => 'security',

                'vendor' => 'WordPress',

                'description' => __('Authentication cookie for logged-in WordPress users.', 'open-cookie-consent')

            ],

            'wordpress_sec_' => [

                'name' => 'wordpress_sec_*',

                'category' => 'security',

                'vendor' => 'WordPress',

                'description' => __('Secure authentication cookie used for admin sessions.', 'open-cookie-consent')

            ],

            'wordpressuser_' => [

                'name' => 'wordpressuser_*',

                'category' => 'security',

                'vendor' => 'WordPress',

                'description' => __('Stores the WordPress username for convenience.', 'open-cookie-consent')

            ],

            'wordpresspass_' => [

                'name' => 'wordpresspass_*',

                'category' => 'security',

                'vendor' => 'WordPress',

                'description' => __('Stores the WordPress password hash for convenience.', 'open-cookie-consent')

            ],

            'wp-settings-' => [

                'name' => 'wp-settings-*',

                'category' => 'functional',

                'vendor' => 'WordPress',

                'description' => __('Remembers admin interface preferences for logged-in users.', 'open-cookie-consent')

            ],

            'wp-settings-time-' => [

                'name' => 'wp-settings-time-*',

                'category' => 'functional',

                'vendor' => 'WordPress',

                'description' => __('Stores the time a WordPress settings value was set.', 'open-cookie-consent')

            ],

            'wp_lang' => [

                'name' => 'wp_lang',

                'category' => 'functional',

                'vendor' => 'WordPress',

                'description' => __('Persists the user interface language selection.', 'open-cookie-consent')

            ],

        ];



        $seen = [];



        foreach ((array) $_COOKIE as $cookie_name => $value) {

            foreach ($patterns as $prefix => $info) {

                if (stripos($cookie_name, $prefix) === 0) {

                    $key = $info['name'] . '|' . $info['vendor'];

                    if (isset($seen[$key])) {

                        continue 2;

                    }

                    $seen[$key] = true;

                    $detected[] = [

                        'name' => $info['name'],

                        'category' => $info['category'],

                        'vendor' => $info['vendor'],

                        'description' => $info['description'],

                        'source' => 'server_logged_in_detection',

                        'confidence' => 1.0

                    ];

                }

            }

        }



        if (is_user_logged_in() && empty($detected)) {

            $detected[] = [

                'name' => 'wordpress_logged_in_*',

                'category' => 'security',

                'vendor' => 'WordPress',

                'description' => __('Authentication cookie for logged-in WordPress users.', 'open-cookie-consent'),

                'source' => 'server_logged_in_detection',

                'confidence' => 0.6

            ];

        }



        return $detected;

    }



    private function scanEnqueuedScripts() {
        global $wp_scripts, $wp_styles;
        $cookies = [];
        
        if (!$wp_scripts) {
            return $cookies;
        }
        
        foreach ($wp_scripts->registered as $handle => $script) {
            $src = $script->src ?? '';
            $detected = $this->detectTrackerFromURL($src);
            if ($detected) {
                $cookies[] = $detected;
            }
        }
        
        return $cookies;
    }
    
    private function scanPageContent($url) {
        $cookies = [];
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'OCC Scanner/1.0'
        ]);
        
        if (is_wp_error($response)) {
            return $cookies;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        $cookies = array_merge($cookies, $this->scanForKnownTrackers($body));
        $cookies = array_merge($cookies, $this->scanForScriptTags($body));
        
        return $cookies;
    }
    
    private function scanForKnownTrackers($content) {
        $cookies = [];
        $known_trackers = $this->getKnownTrackers();
        // Strip OCC's own GCM defaults block to avoid false-positive 'gtag(' detection
        $content = preg_replace('/<!--\s*Open Cookie Consent - Google Consent Mode v2 Defaults\s*-->[\s\S]*?<\/script>/i', '', $content);
        
        foreach ($known_trackers as $pattern => $tracker_info) {
            if (preg_match($pattern, $content)) {
                $cookies[] = [
                    'name' => $tracker_info['name'],
                    'category' => $tracker_info['category'],
                    'vendor' => $tracker_info['vendor'],
                    'source' => 'static_scan',
                    'confidence' => 0.9
                ];
            }
        }
        
        return $cookies;
    }
    
    private function scanForScriptTags($content) {
        $cookies = [];
        
        preg_match_all('/<script[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        
        foreach ($matches[1] as $src) {
            $detected = $this->detectTrackerFromURL($src);
            if ($detected) {
                $cookies[] = $detected;
            }
        }
        
        return $cookies;
    }
    
    private function detectTrackerFromURL($url) {
        if (empty($url)) {
            return null;
        }
        
        $known_domains = [
            'googletagmanager.com' => ['name' => 'Google Tag Manager', 'category' => 'analytics', 'vendor' => 'Google'],
            'google-analytics.com' => ['name' => 'Google Analytics', 'category' => 'analytics', 'vendor' => 'Google'],
            'googleadservices.com' => ['name' => 'Google Ads', 'category' => 'marketing', 'vendor' => 'Google'],
            'facebook.com' => ['name' => 'Facebook Pixel', 'category' => 'marketing', 'vendor' => 'Meta'],
            'connect.facebook.net' => ['name' => 'Facebook Pixel', 'category' => 'marketing', 'vendor' => 'Meta'],
            'hotjar.com' => ['name' => 'Hotjar', 'category' => 'analytics', 'vendor' => 'Hotjar'],
            'plausible.io' => ['name' => 'Plausible', 'category' => 'analytics', 'vendor' => 'Plausible']
        ];
        
        $parsed_url = parse_url($url);
        $domain = $parsed_url['host'] ?? '';
        
        foreach ($known_domains as $known_domain => $info) {
            if (strpos($domain, $known_domain) !== false) {
                return [
                    'name' => $info['name'],
                    'category' => $info['category'],
                    'vendor' => $info['vendor'],
                    'source' => $url,
                    'confidence' => 0.8
                ];
            }
        }
        
        return null;
    }

    // Lightweight runtime heuristic: check enqueued scripts for known trackers
    public function hasLikelyNonEssential() {
        global $wp_scripts;
        if (!$wp_scripts) { return false; }
        foreach ($wp_scripts->registered as $handle => $script) {
            $src = $script->src ?? '';
            $detected = $this->detectTrackerFromURL($src);
            if ($detected && ($detected['category'] ?? 'necessary') !== 'necessary') {
                return true;
            }
        }
        return false;
    }
    
    private function runtimeScan($url) {
        return [];
    }
    
    private function getKnownTrackers() {
        $default_trackers = [
            '/gtag\s*\(/' => ['name' => 'Google Analytics (gtag)', 'category' => 'analytics', 'vendor' => 'Google'],
            '/ga\s*\(/' => ['name' => 'Google Analytics (classic)', 'category' => 'analytics', 'vendor' => 'Google'],
            '/fbq\s*\(/' => ['name' => 'Facebook Pixel', 'category' => 'marketing', 'vendor' => 'Meta'],
            '/hj\s*\(/' => ['name' => 'Hotjar', 'category' => 'analytics', 'vendor' => 'Hotjar']
        ];
        
        return apply_filters('occ_known_trackers', $default_trackers);
    }
    
    private function makeCookieKey($cookie) {
        $name = strtolower(trim($cookie['name'] ?? ''));
        $vendor = strtolower(trim($cookie['vendor'] ?? ''));
        return $name . '|' . $vendor;
    }

    private function deduplicateCookies($cookies) {
        $unique = [];
        $seen = [];
        
        foreach ($cookies as $cookie) {
            $key = $cookie['name'] . '|' . $cookie['vendor'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $cookie;
            }
        }
        
        return $unique;
    }
    
    private function categorizeCookies($cookies) {
        if (!$this->ocd_data || empty($this->ocd_data['domains'])) {
            return $cookies;
        }
        $domainMap = $this->ocd_data['domains'];
        foreach ($cookies as &$cookie) {
            $host = '';
            if (!empty($cookie['source'])) {
                $parsed = parse_url($cookie['source']);
                $host = $parsed['host'] ?? '';
            }
            if ($host && isset($domainMap[$host])) {
                $mapped = $domainMap[$host];
                $cookie['vendor'] = $mapped['vendor'] ?: ($cookie['vendor'] ?? '');
                $cookie['category'] = $this->mapOCDCategory($mapped['category']);
                $cookie['confidence'] = max($cookie['confidence'] ?? 0, 0.95);
            }
        }
        // Apply custom mappings overrides (domain, cookie_name, script_url)
        $cookies = $this->applyCustomMappings($cookies);
        return $cookies;
    }

    private function mapOCDCategory($ocdCategory) {
        $c = strtolower(trim($ocdCategory));
        $direct = ['necessary','functional','security','analytics','personalization','marketing'];
        if (in_array($c, $direct, true)) {
            return $c;
        }

        $map = [
            'advertising' => 'marketing',
            'ads' => 'marketing',
            'targeting' => 'marketing',
            'remarketing' => 'marketing',
            'statistics' => 'analytics',
            'measurement' => 'analytics',
            'preferences' => 'personalization',
            'personalisation' => 'personalization',
            'performance' => 'functional',
            'essential' => 'necessary',
            'strictly necessary' => 'necessary'
        ];

        if (isset($map[$c])) {
            return $map[$c];
        }

        return 'functional';
    }
    
    private function findOCDMatch($cookie_name) {
        if (!$this->ocd_data || !isset($this->ocd_data['cookies'])) {
            return null;
        }
        
        foreach ($this->ocd_data['cookies'] as $ocd_cookie) {
            if (($ocd_cookie['name'] ?? '') === $cookie_name) {
                return $ocd_cookie;
            }
        }
        
        return null;
    }
    
    private function buildInventory($cookies) {
        $canonical_list = $this->buildCanonicalList($cookies);
        $version = hash('sha256', $canonical_list);
        
        $known_names = array_column($cookies, 'name');
        
        return [
            'version' => $version,
            'cookies' => $cookies,
            'knownNames' => $known_names
        ];
    }
    
    private function buildCanonicalList($cookies) {
        $list = [];
        foreach ($cookies as $cookie) {
            $list[] = $cookie['name'] . '|' . $cookie['category'] . '|' . $cookie['vendor'];
        }
        sort($list);
        return implode("\n", $list);
    }
    
    private function sendNotifications($inventory) {
        $scanner_settings = $this->settings->get('scanner', []);
        $notify = $scanner_settings['notify'] ?? [];
        
        if ($notify['email'] ?? false) {
            $this->sendEmailNotification($inventory);
        }
        
        if (!empty($notify['webhook'])) {
            $this->sendWebhookNotification($inventory, $notify['webhook']);
        }
    }
    
    private function sendEmailNotification($inventory) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('[%s] Cookie Scan Completed', 'open-cookie-consent'), $site_name);
        $message = sprintf(
            __("Cookie scan completed.\n\nDetected cookies: %d\nInventory version: %s\n\nView details in the WordPress admin.", 'open-cookie-consent'),
            count($inventory['cookies']),
            $inventory['version']
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    private function sendWebhookNotification($inventory, $webhook_url) {
        $payload = [
            'type' => 'scan_completed',
            'site_url' => home_url(),
            'inventory_version' => $inventory['version'],
            'cookies_count' => count($inventory['cookies']),
            'timestamp' => time()
        ];
        
        wp_remote_post($webhook_url, [
            'body' => json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 15
        ]);
    }
    
    public function updateOCD() {
        $scanner = OCC_Settings::getInstance()->get('scanner', []);
        $ocd_url = !empty($scanner['ocd_source_url'])
            ? $scanner['ocd_source_url']
            : 'https://raw.githubusercontent.com/jkwakman/Open-Cookie-Database/refs/heads/master/open-cookie-database.json';
        
        $response = wp_remote_get($ocd_url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!$data) { return false; }
        
        $ocd_file = OCC_PLUGIN_DIR . 'data/ocd.json';
        $result = file_put_contents($ocd_file, $body);
        
        if ($result !== false) {
            $this->loadOCD();
            // Store metadata so admins can verify the update
            $vendorCount = is_array($data) ? count($data) : 0;
            $recordCount = 0;
            if (is_array($data)) {
                foreach ($data as $vendor => $rows) {
                    if (is_array($rows)) { $recordCount += count($rows); }
                }
            }
            $mappedDomains = 0;
            if (is_array($this->ocd_data) && isset($this->ocd_data['domains']) && is_array($this->ocd_data['domains'])) {
                $mappedDomains = count($this->ocd_data['domains']);
            }
            $meta = [
                'updated_at' => current_time('mysql'),
                'checksum' => hash('sha256', $body),
                'vendors' => $vendorCount,
                'records' => $recordCount,
                'mapped_domains' => $mappedDomains
            ];
            OCC_Settings::getInstance()->update(['scanner' => ['ocd_meta' => $meta]]);
            return true;
        }
        
        return false;
    }

    private function applyCustomMappings($cookies) {
        $scanner = OCC_Settings::getInstance()->get('scanner', []);
        $maps = $scanner['custom_mappings'] ?? [];
        if (empty($maps)) return $cookies;
        foreach ($cookies as &$cookie) {
            $name = $cookie['name'] ?? '';
            $src = $cookie['source'] ?? '';
            $host = '';
            if ($src) { $p = parse_url($src); $host = $p['host'] ?? ''; }
            foreach ($maps as $m) {
                $type = $m['type'] ?? '';
                $val = $m['value'] ?? '';
                $hit = false;
                if ($type === 'domain' && $host && stripos($host, $val) !== false) { $hit = true; }
                elseif ($type === 'cookie_name' && $name === $val) { $hit = true; }
                elseif ($type === 'script_url' && $src && stripos($src, $val) !== false) { $hit = true; }
                if ($hit) {
                    $cookie['category'] = $this->mapOCDCategory($m['category'] ?? 'necessary');
                    if (!empty($m['vendor'])) $cookie['vendor'] = $m['vendor'];
                    if (!empty($m['description'])) $cookie['description'] = $m['description'];
                    break; // first match wins
                }
            }
        }
        return $cookies;
    }
}
