<?php

defined("ABSPATH") or die("Direct access not allowed.");

class OCC_Shortcodes {
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
        add_shortcode('occ_detected_cookies', [$this, 'renderDetectedCookies']);
    }

    public function renderDetectedCookies($atts = []) {
        $atts = shortcode_atts([
            'category' => '',
            'show_headings' => 'true',
            'show_vendor' => 'true',
            'show_description' => 'true',
        ], $atts, 'occ_detected_cookies');

        $inventory = $this->settings->get('inventory', []);
        $cookies = $inventory['cookies'] ?? [];

        if (empty($cookies)) {
            return '<div class="occ-cookie-shortcode occ-cookie-shortcode-empty">' . esc_html__('No cookies detected yet.', 'open-cookie-consent') . '</div>';
        }

        $filterCategory = sanitize_key($atts['category']);
        $showHeadings = $this->attToBool($atts['show_headings']);
        $showVendor = $this->attToBool($atts['show_vendor']);
        $showDescription = $this->attToBool($atts['show_description']);

        $grouped = [];
        foreach ($cookies as $cookie) {
            $category = strtolower($cookie['category'] ?? 'functional');
            if (in_array($category, ['functional', 'security'], true)) {
                $category = 'necessary';
            }
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $cookie;
        }

        if ($filterCategory) {
            $filterCategory = in_array($filterCategory, ['functional', 'security'], true) ? 'necessary' : $filterCategory;
            $grouped = array_filter($grouped, static function($items, $key) use ($filterCategory) {
                return $key === $filterCategory;
            }, ARRAY_FILTER_USE_BOTH);
        }

        if (empty($grouped)) {
            return '<div class="occ-cookie-shortcode occ-cookie-shortcode-empty">' . esc_html__('No cookies detected in this category yet.', 'open-cookie-consent') . '</div>';
        }

        $categories = apply_filters('occ_categories', $this->settings->get('categories', []));
        $labels = [];
        foreach ($categories as $key => $data) {
            $labels[sanitize_key($key)] = $data['label'] ?? ucfirst($key);
        }
        if (!isset($labels['necessary'])) {
            $labels['necessary'] = __('Necessary', 'open-cookie-consent');
        }

        $order = [
            'necessary' => 0,
            'functional' => 1,
            'security' => 2,
            'analytics' => 3,
            'personalization' => 4,
            'marketing' => 5,
        ];

        uksort($grouped, static function($a, $b) use ($order) {
            $rankA = $order[$a] ?? 99;
            $rankB = $order[$b] ?? 99;
            if ($rankA === $rankB) {
                return strcmp($a, $b);
            }
            return $rankA <=> $rankB;
        });

        ob_start();
        ?>
        <div class="occ-cookie-shortcode">
            <?php foreach ($grouped as $category => $items): ?>
                <div class="occ-cookie-section">
                    <?php if ($showHeadings): ?>
                        <h3 class="occ-cookie-section-title"><?php echo esc_html($labels[$category] ?? ucfirst($category)); ?></h3>
                    <?php endif; ?>
                    <ul>
                        <?php foreach ($items as $cookie): ?>
                            <?php
                                $name = trim($cookie['name'] ?? '');
                                if ($name === '') {
                                    continue;
                                }
                                $metaParts = [];
                                if ($showVendor && !empty($cookie['vendor'])) {
                                    $metaParts[] = $cookie['vendor'];
                                }
                                if ($showDescription && !empty($cookie['description'])) {
                                    $metaParts[] = $cookie['description'];
                                }
                            ?>
                            <li>
                                <span class="occ-cookie-name"><?php echo esc_html($name); ?></span>
                                <?php if (!empty($metaParts)): ?>
                                    <span class="occ-cookie-meta"> - <?php echo esc_html(implode(' | ', $metaParts)); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function attToBool($value) {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower((string)$value);
        return !in_array($value, ['false', '0', 'no', 'off', ''], true);
    }
}
