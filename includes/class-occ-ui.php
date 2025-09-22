<?php

defined('ABSPATH') or die('Direct access not allowed.');

class OCC_UI {
    
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
        add_action('wp_footer', [$this, 'renderUI']);
        add_action('wp_head', [$this, 'addCustomCSS']);
    }
    
    public function renderUI() {
        $ui_settings = $this->settings->get('ui', []);
        $advanced = $this->settings->get('advanced', []);
        $categories = apply_filters('occ_categories', $this->settings->get('categories', []));
        $inventory = $this->settings->get('inventory', []);

        $hasNonEssential = false;
        foreach (($inventory['cookies'] ?? []) as $cookie) {
            if (($cookie['category'] ?? 'necessary') !== 'necessary') {
                $hasNonEssential = true;
                break;
            }
        }

        // Fallback before first scan: detect likely non-essential by enqueued scripts
        if (!$hasNonEssential && empty($inventory['version'])) {
            if (method_exists('OCC_Scanner', 'getInstance') && method_exists('OCC_Scanner', 'hasLikelyNonEssential')) {
                $scanner = OCC_Scanner::getInstance();
                $hasNonEssential = $scanner->hasLikelyNonEssential();
            }
        }

        if ($advanced['test_cookie'] ?? false) { $hasNonEssential = true; }
        $should_show = ($hasNonEssential || !($ui_settings['conditional_banner'] ?? true)) ? true : false;
        $should_show = apply_filters('occ_should_show_banner', $should_show, [
            'ui' => $ui_settings,
            'categories' => $categories,
            'inventory' => $inventory
        ]);
        if (!$should_show) { return; }

        do_action('occ_before_render_banner', [
            'ui' => $ui_settings,
            'categories' => $categories,
            'inventory' => $inventory
        ]);

        $position = $ui_settings['position'] ?? 'modal_center';
        if ($position === 'modal_center') {
            echo '<div id="occ-banner-overlay" class="occ-banner-overlay" style="display:none;" aria-hidden="true"></div>';
        }

        $this->renderBannerHTML($ui_settings, $categories);
        $this->renderPreferencesModal($ui_settings, $categories);
        $this->renderFloatingIcon($ui_settings);
        $this->renderUpdatedNotice($ui_settings);
    }
    
    private function renderBannerHTML($ui_settings, $categories) {
        $position = $ui_settings['position'] ?? 'modal_center';
        $pos_class = 'occ-pos-center';
        if ($position === 'modal_left') { $pos_class = 'occ-pos-left'; }
        elseif ($position === 'modal_right') { $pos_class = 'occ-pos-right'; }
        elseif ($position === 'banner_full') { $pos_class = 'occ-bar'; }
        $privacy_link = '';
        $cookie_link = '';
        
        if (!empty($ui_settings['links']['privacy_page_id'])) {
            $privacy_url = get_permalink($ui_settings['links']['privacy_page_id']);
            $privacy_link = sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url($privacy_url),
                __('Privacy Policy', 'open-cookie-consent')
            );
        }
        
        if (!empty($ui_settings['links']['cookie_page_id'])) {
            $cookie_url = get_permalink($ui_settings['links']['cookie_page_id']);
            $cookie_link = sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url($cookie_url),
                __('Cookie Policy', 'open-cookie-consent')
            );
        }
        
        ?>
        <div id="occ-banner" class="occ-banner <?php echo esc_attr($pos_class); ?>" role="dialog" aria-modal="true" aria-labelledby="occ-banner-title" style="display: none;">
            <div class="occ-banner-content">
                <h2 id="occ-banner-title" class="occ-banner-title">
                    <?php echo esc_html(__('Cookie Consent', 'open-cookie-consent')); ?>
                </h2>
                <div class="occ-banner-text">
                    <p><?php echo esc_html(__('We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'open-cookie-consent')); ?></p>
                    <?php if ($privacy_link || $cookie_link): ?>
                        <p class="occ-banner-links">
                            <?php echo $privacy_link; ?>
                            <?php if ($privacy_link && $cookie_link): ?>
                                <span class="occ-separator"> | </span>
                            <?php endif; ?>
                            <?php echo $cookie_link; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="occ-banner-actions">
                    <button type="button" id="occ-accept-all" class="occ-button occ-button-primary">
                        <?php echo esc_html(__('Accept All', 'open-cookie-consent')); ?>
                    </button>
                    <button type="button" id="occ-open-preferences" class="occ-button occ-button-tertiary">
                        <?php echo esc_html(__('Preferences', 'open-cookie-consent')); ?> ›
                    </button>
                    <button type="button" id="occ-reject-nonessential" class="occ-button occ-button-secondary">
                        <?php echo esc_html(__('Reject Non-Essential', 'open-cookie-consent')); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function renderPreferencesModal($ui_settings, $categories) {
        $inventory = $this->settings->get('inventory', []);
        $cookies = $inventory['cookies'] ?? [];
        $byCat = [];

        foreach ($cookies as $ck) {
            $key = strtolower(sanitize_key($ck['category'] ?? ''));
            if (!$key) {
                $key = 'functional';
            }
            // Don't force functional/security into necessary - keep them separate
            $byCat[$key][] = $ck;
        }

        $locked = [];
        $open = [];

        foreach ($categories as $key => $category) {
            if (!is_array($category)) {
                continue;
            }
            $normalized = sanitize_key($key);
            if ($normalized === '') {
                continue;
            }
            if ($category['locked'] ?? false) {
                $locked[$normalized] = $category;
            } else {
                $open[$normalized] = $category;
            }
        }

        ?>
        <div id="occ-preferences-modal" class="occ-modal" role="dialog" aria-modal="true" aria-labelledby="occ-preferences-title" style="display: none;">
            <div class="occ-modal-backdrop"></div>
            <div class="occ-modal-content">
                <header class="occ-modal-header">
                    <h2 id="occ-preferences-title" class="occ-modal-title">
                        <?php echo esc_html(__('Cookie Preferences', 'open-cookie-consent')); ?>
                    </h2>
                    <button type="button" id="occ-close-preferences" class="occ-close-button" aria-label="<?php echo esc_attr(__('Close', 'open-cookie-consent')); ?>">&times;</button>
                </header>

                <div class="occ-modal-body">
                    <?php if (!empty($locked)): ?>
                        <div class="occ-necessary-section">
                            <h3 class="occ-necessary-heading"><?php echo esc_html(__('Always Active', 'open-cookie-consent')); ?></h3>
                            <p class="occ-necessary-description"><?php echo esc_html(__('These cookies are essential for the website to function and cannot be disabled.', 'open-cookie-consent')); ?></p>
                            
                            <div class="occ-locked-categories">
                                <?php foreach ($locked as $lockedKey => $lockedCategory): ?>
                                    <div class="occ-locked-category">
                                        <h4 class="occ-locked-title"><?php echo esc_html($lockedCategory['label']); ?></h4>
                                        <?php if (!empty($lockedCategory['description'])): ?>
                                            <p class="occ-locked-description"><?php echo esc_html($lockedCategory['description']); ?></p>
                                        <?php endif; ?>
                                        <?php $this->renderCookieList($byCat[$lockedKey] ?? [], true); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($open as $key => $category): 
                        $categoryEnabled = $category['enabled'] ?? true;
                        $hasCookies = !empty($byCat[$key]);
                        if (!$categoryEnabled && !$hasCookies) {
                            continue;
                        }
                        $toggleAriaDisabled = $categoryEnabled ? '' : ' aria-disabled="true"';
                        $toggleInputDisabled = $categoryEnabled ? '' : ' disabled';
                        $toggleTabIndex = $categoryEnabled ? '0' : '-1';
                    ?>
                        <div class="occ-category occ-category-toggle">
                            <div class="occ-category-header">
                                <label class="occ-category-label" for="occ-category-<?php echo esc_attr($key); ?>">
                                    <span class="occ-category-title"><?php echo esc_html($category['label']); ?></span>
                                    <div class="occ-toggle" role="switch" aria-checked="false" tabindex="<?php echo esc_attr($toggleTabIndex); ?>" data-category="<?php echo esc_attr($key); ?>"<?php echo $toggleAriaDisabled; ?>>
                                        <input type="checkbox" id="occ-category-<?php echo esc_attr($key); ?>" class="occ-sr-only" data-category="<?php echo esc_attr($key); ?>"<?php echo $toggleInputDisabled; ?>>
                                        <span class="occ-toggle-track"><span class="occ-toggle-thumb"></span></span>
                                    </div>
                                </label>
                            </div>
                            <div class="occ-category-description">
                                <?php if (!empty($category['description'])): ?>
                                    <p><?php echo esc_html($category['description']); ?></p>
                                <?php endif; ?>
                                <?php $this->renderCookieList($byCat[$key] ?? [], true); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <footer class="occ-modal-footer">
                    <button type="button" id="occ-accept-all-preferences" class="occ-button occ-button-primary">
                        <?php echo esc_html(__('Accept All', 'open-cookie-consent')); ?>
                    </button>
                    <button type="button" id="occ-reject-nonessential-preferences" class="occ-button occ-button-secondary">
                        <?php echo esc_html(__('Reject Non-Essential', 'open-cookie-consent')); ?>
                    </button>
                    <button type="button" id="occ-save-preferences" class="occ-button occ-button-primary">
                        <?php echo esc_html(__('Save Preferences', 'open-cookie-consent')); ?>
                    </button>
                </footer>
            </div>
        </div>
        <?php
    }

    private function renderCookieList($cookies, $showEmpty = false) {

        if (empty($cookies)) {

            if ($showEmpty) {

                echo '<p class="occ-cookie-empty">' . esc_html__('No cookies detected in this category yet.', 'open-cookie-consent') . '</p>';

            }

            return;

        }



        echo '<ul class="occ-cookie-items">';

        foreach ($cookies as $cookie) {

            $name = trim($cookie['name'] ?? '');

            if ($name === '') {

                continue;

            }

            $meta = [];

            if (!empty($cookie['vendor'])) {

                $meta[] = $cookie['vendor'];

            }

            if (!empty($cookie['description'])) {

                $meta[] = $cookie['description'];

            }

            echo '<li><span class="occ-cookie-name">' . esc_html($name) . '</span>';

            if (!empty($meta)) {

                echo '<span class="occ-cookie-meta"> - ' . esc_html(implode(' | ', $meta)) . '</span>';

            }

            echo '</li>';

        }

        echo '</ul>';

    }



    private function renderFloatingIcon($ui_settings) {
        ?>
        <div id="occ-floating-icon" class="occ-floating-icon" style="display: none;">
            <button type="button" id="occ-reopen-preferences" class="occ-icon-button" aria-label="<?php echo esc_attr(__('Cookie Preferences', 'open-cookie-consent')); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3a4.6 4.6 0 0 0 6.58 6.58A4.6 4.6 0 0 0 21 12.79Z"/>
                    <circle cx="10" cy="8.5" r="1.25"/>
                    <circle cx="7.5" cy="12" r="1.25"/>
                    <circle cx="12" cy="15" r="1.25"/>
                </svg>
            </button>
        </div>
        <?php
    }
    
    private function renderUpdatedNotice($ui_settings) {
        $focus_button = $ui_settings['updated_notice_focus'] ?? 'review';
        ?>
        <div id="occ-updated-notice" class="occ-updated-notice" role="dialog" aria-modal="true" aria-labelledby="occ-updated-title" style="display: none;">
            <div class="occ-updated-content">
                <h2 id="occ-updated-title" class="occ-updated-title">
                    <?php echo esc_html(__('Updated Cookie Use', 'open-cookie-consent')); ?>
                </h2>
                <div class="occ-updated-text">
                    <p><?php echo esc_html(__('We\'ve updated our cookie use. New cookies were added since you set your preferences.', 'open-cookie-consent')); ?></p>
                </div>
                <div class="occ-updated-actions">
                    <button type="button" id="occ-updated-accept-all" class="occ-button occ-button-primary" <?php echo $focus_button === 'accept' ? 'data-initial-focus="true"' : ''; ?>>
                        <?php echo esc_html(__('Accept All', 'open-cookie-consent')); ?>
                    </button>
                    <button type="button" id="occ-updated-review" class="occ-button occ-button-tertiary" <?php echo $focus_button === 'review' ? 'data-initial-focus="true"' : ''; ?>>
                        <?php echo esc_html(__('Review Cookies', 'open-cookie-consent')); ?>
                    </button>
                    <button type="button" id="occ-updated-keep" class="occ-button occ-button-secondary" <?php echo $focus_button === 'keep' ? 'data-initial-focus="true"' : ''; ?>>
                        <?php echo esc_html(__('Keep Current Settings', 'open-cookie-consent')); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function addCustomCSS() {
        $ui_settings = $this->settings->get('ui', []);
        $advanced = $this->settings->get('advanced', []);

        $fallbackColour = $ui_settings['color'] ?? '#2797DD';
        $colorLight = $ui_settings['color_light'] ?? $fallbackColour;
        $colorDark = $ui_settings['color_dark'] ?? $colorLight;
        $radius = $ui_settings['radius'] ?? 10;
        $theme = $ui_settings['theme'] ?? 'auto';
        $custom_css = $advanced['custom_css'] ?? '';

        $computeTextColour = static function($hexColour) {
            $hex = ltrim($hexColour, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
            return ($yiq >= 128) ? '#000000' : '#ffffff';
        };

        $lightText = $computeTextColour($colorLight);
        $darkText = $computeTextColour($colorDark);

        echo "\n<style id='occ-dynamic-css'>\n";
        echo ":root {\n";
        echo "  --occ-primary-color: {$colorLight};\n";
        echo "  --occ-button-primary-text: {$lightText};\n";
        echo "  --occ-border-radius: {$radius}px;\n";
        echo "}\n";

        if ($theme === 'dark') {
            echo ":root {\n";
            echo "  --occ-primary-color: {$colorDark};\n";
            echo "  --occ-button-primary-text: {$darkText};\n";
            echo "  --occ-bg-primary: #1a1a1a;\n";
            echo "  --occ-bg-secondary: #2d2d2d;\n";
            echo "  --occ-text-primary: #ffffff;\n";
            echo "  --occ-text-secondary: #b0b0b0;\n";
            echo "  --occ-border-color: #404040;\n";
            echo "  --occ-button-secondary-bg: #2d2d2d;\n";
            echo "  --occ-button-secondary-text: #ffffff;\n";
            echo "  --occ-overlay-bg: rgba(0, 0, 0, 0.7);\n";
            echo "}\n";
        } elseif ($theme === 'light') {
            echo ":root {\n";
            echo "  --occ-primary-color: {$colorLight};\n";
            echo "  --occ-button-primary-text: {$lightText};\n";
            echo "  --occ-bg-primary: #ffffff;\n";
            echo "  --occ-bg-secondary: #f8f9fa;\n";
            echo "  --occ-text-primary: #212529;\n";
            echo "  --occ-text-secondary: #6c757d;\n";
            echo "  --occ-border-color: #dee2e6;\n";
            echo "  --occ-button-secondary-bg: #f8f9fa;\n";
            echo "  --occ-button-secondary-text: #495057;\n";
            echo "  --occ-overlay-bg: rgba(0, 0, 0, 0.5);\n";
            echo "}\n";
        } else {
            echo "@media (prefers-color-scheme: dark) {\n";
            echo "  :root {\n";
            echo "    --occ-primary-color: {$colorDark};\n";
            echo "    --occ-button-primary-text: {$darkText};\n";
            echo "  }\n";
            echo "}\n";
        }

        if (!empty($custom_css)) {
            echo wp_strip_all_tags($custom_css) . "\n";
        }

        echo "</style>\n";
    }
}
