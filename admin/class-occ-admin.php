<?php

defined('ABSPATH') or die('Direct access not allowed.');

class OCC_Admin {

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
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_ajax_occ_save_settings', [$this, 'handleSaveSettings']);
        add_action('wp_ajax_occ_export_settings', [$this, 'handleExportSettings']);
        add_action('wp_ajax_occ_import_settings', [$this, 'handleImportSettings']);
        add_action('wp_ajax_occ_reset_settings', [$this, 'handleResetSettings']);
    }

    public function addAdminMenu() {
        add_menu_page(
            __('Open Cookie Consent', 'open-cookie-consent'),
            __('Cookie Consent', 'open-cookie-consent'),
            'manage_options',
            'open-cookie-consent',
            [$this, 'renderAdminPage'],
            'dashicons-privacy',
            30
        );
    }

    public function enqueueAdminAssets($hook) {
        if ($hook !== 'toplevel_page_open-cookie-consent') { return; }
        wp_enqueue_script('occ-admin', OCC_PLUGIN_URL.'admin/js/admin.js', ['jquery'], OCC_VERSION, true);
        wp_enqueue_style('occ-admin', OCC_PLUGIN_URL.'admin/css/admin.css', [], OCC_VERSION);
        wp_localize_script('occ-admin', 'occAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('occ_admin_nonce'),
            'strings' => [
                'saving' => __('Saving...', 'open-cookie-consent'),
                'saved' => __('Settings saved successfully', 'open-cookie-consent'),
                'error' => __('Error saving settings', 'open-cookie-consent'),
                'scanning' => __('Scanning...', 'open-cookie-consent'),
                'scan_complete' => __('Scan completed', 'open-cookie-consent'),
            ]
        ]);
    }

    public function renderAdminPage() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        $settings = $this->settings->get();
        ?>
        <div class="wrap occ-admin">
            <h1>
                <?php echo esc_html(__('Open Cookie Consent', 'open-cookie-consent')); ?>
                <span class="occ-plugin-author">
                    <?php esc_html_e('By', 'open-cookie-consent'); ?> 
                    <a href="https://www.adammcbride.co.uk" target="_blank" rel="noopener">Adam McBride</a>
                </span>
            </h1>
            <nav class="nav-tab-wrapper">
                <?php $this->tabLink('overview', $tab, __('Overview','open-cookie-consent')); ?>
                <?php $this->tabLink('general', $tab, __('General','open-cookie-consent')); ?>
                <?php $this->tabLink('categories', $tab, __('Categories','open-cookie-consent')); ?>
                <?php $this->tabLink('scanner', $tab, __('Scanner','open-cookie-consent')); ?>
                <?php $this->tabLink('inventory', $tab, __('Inventory','open-cookie-consent')); ?>
                <?php $this->tabLink('integrations', $tab, __('Integrations','open-cookie-consent')); ?>
                <?php $this->tabLink('advanced', $tab, __('Advanced','open-cookie-consent')); ?>
                <?php $this->tabLink('help', $tab, __('Help','open-cookie-consent')); ?>
            </nav>
            <div class="occ-admin-content">
                <?php
                switch ($tab) {
                    case 'general':
                        $this->renderGeneralTab($settings);
                        break;
                    case 'categories':
                        $this->renderCategoriesTab($settings);
                        break;
                    case 'scanner':
                        $this->renderScannerTab($settings);
                        break;
                    case 'inventory':
                        $this->renderInventoryTab($settings);
                        break;
                    case 'integrations':
                        $this->renderIntegrationsTab($settings);
                        break;
                    case 'advanced':
                        $this->renderAdvancedTab($settings);
                        break;
                    case 'help':
                        $this->renderHelpTab();
                        break;
                    case 'overview':
                    default:
                        $this->renderOverviewTab($settings);
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function tabLink($slug, $current, $label) {
        $class = $slug === $current ? 'nav-tab nav-tab-active' : 'nav-tab';
        printf('<a href="%s" class="%s">%s</a>', esc_url(admin_url('admin.php?page=open-cookie-consent&tab='.$slug)), esc_attr($class), esc_html($label));
    }

    private function renderOverviewTab($settings) {
        $inventory = $settings['inventory'] ?? [];
        $scanner = $settings['scanner'] ?? [];
        $last_scan = !empty($inventory['version']) ? date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime(explode(':', $inventory['version'])[0])) : __('Never','open-cookie-consent');
        $count = count($inventory['cookies'] ?? []);
        ?>
        <div class="occ-overview">
            <div class="occ-status-cards">
                <div class="occ-status-card">
                    <h3><?php esc_html_e('Plugin Status','open-cookie-consent'); ?></h3>
                    <div class="occ-status-indicator occ-status-active"><?php esc_html_e('Active','open-cookie-consent'); ?></div>
                    <p><?php esc_html_e('Cookie consent is active and monitoring your site.','open-cookie-consent'); ?></p>
                </div>
                <div class="occ-status-card">
                    <h3><?php esc_html_e('Last Scan','open-cookie-consent'); ?></h3>
                    <div class="occ-scan-date"><?php echo esc_html($last_scan); ?></div>
                    <p><?php printf(esc_html__('%d cookies found','open-cookie-consent'), $count); ?></p>
                </div>
                <div class="occ-status-card">
                    <h3><?php esc_html_e('Scan Schedule','open-cookie-consent'); ?></h3>
                    <div class="occ-scan-schedule"><?php echo esc_html(ucfirst($scanner['interval'] ?? 'daily')); ?><?php if(($scanner['interval'] ?? 'daily')==='daily'): ?> — <?php echo esc_html($scanner['time_local'] ?? '03:00'); ?><?php endif; ?></div>
                    <p><?php esc_html_e('Automatic cookie detection schedule','open-cookie-consent'); ?></p>
                </div>
            </div>

            <div class="occ-quick-actions">
                <h3><?php esc_html_e('Quick Actions','open-cookie-consent'); ?></h3>
                <div class="occ-action-buttons">
                    <button type="button" id="occ-run-scan" class="button button-primary"><?php esc_html_e('Run Scan Now','open-cookie-consent'); ?></button>
                    <button type="button" id="occ-update-ocd" class="button"><?php esc_html_e('Update Cookie Database','open-cookie-consent'); ?></button>
                    <button type="button" id="occ-clear-cache" class="button"><?php esc_html_e('Clear Consent Cache','open-cookie-consent'); ?></button>
                    <button type="button" id="occ-reset-settings" class="button button-link-delete"><?php esc_html_e('Reset All Settings','open-cookie-consent'); ?></button>
                </div>
                <div id="occ-scan-status"></div>
            </div>

            <?php if (!empty($inventory['cookies'])): ?>
            <div class="occ-recent-cookies">
                <h3><?php esc_html_e('Recently Detected Cookies','open-cookie-consent'); ?></h3>
                <div class="occ-cookie-list">
                    <?php foreach (array_slice($inventory['cookies'], 0, 5) as $cookie): ?>
                        <div class="occ-cookie-item">
                            <strong><?php echo esc_html($cookie['name'] ?? ''); ?></strong>
                            <?php if (!empty($cookie['category'])): ?><span class="occ-cookie-category occ-category-<?php echo esc_attr($cookie['category']); ?>"><?php echo esc_html(ucfirst($cookie['category'])); ?></span><?php endif; ?>
                            <?php if (!empty($cookie['vendor'])): ?><span class="occ-cookie-vendor"><?php echo esc_html($cookie['vendor']); ?></span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderGeneralTab($settings) {
        $ui = $settings['ui'] ?? [];
        ?>
        <form method="post" id="occ-settings-form" data-tab="general">
            <?php wp_nonce_field('occ_admin_nonce','occ_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Display Position','open-cookie-consent'); ?></th>
                    <td>
                        <?php $pos = $ui['position'] ?? 'modal_center'; ?>
                        <select name="ui[position]">
                            <option value="modal_left" <?php selected($pos,'modal_left'); ?>><?php esc_html_e('Small Card (Bottom Left)','open-cookie-consent'); ?></option>
                            <option value="modal_right" <?php selected($pos,'modal_right'); ?>><?php esc_html_e('Small Card (Bottom Right)','open-cookie-consent'); ?></option>
                            <option value="modal_center" <?php selected($pos,'modal_center'); ?>><?php esc_html_e('Small Card (Center)','open-cookie-consent'); ?></option>
                            <option value="banner_full" <?php selected($pos,'banner_full'); ?>><?php esc_html_e('Full-width Bar (Bottom)','open-cookie-consent'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Theme','open-cookie-consent'); ?></th>
                    <td>
                        <select name="ui[theme]">
                            <option value="auto" <?php selected($ui['theme'] ?? 'auto','auto'); ?>><?php esc_html_e('Auto (System)','open-cookie-consent'); ?></option>
                            <option value="light" <?php selected($ui['theme'] ?? 'auto','light'); ?>><?php esc_html_e('Light','open-cookie-consent'); ?></option>
                            <option value="dark" <?php selected($ui['theme'] ?? 'auto','dark'); ?>><?php esc_html_e('Dark','open-cookie-consent'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Accent Colour (Light mode)','open-cookie-consent'); ?></th>
                    <td>
                        <input type="color" name="ui[color_light]" value="<?php echo esc_attr($ui['color_light'] ?? ($ui['color'] ?? '#2797DD')); ?>">
                        <p class="description"><?php esc_html_e('Used when the banner follows light appearance.', 'open-cookie-consent'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Accent Colour (Dark mode)','open-cookie-consent'); ?></th>
                    <td>
                        <input type="color" name="ui[color_dark]" value="<?php echo esc_attr($ui['color_dark'] ?? ($ui['color'] ?? '#2797DD')); ?>">
                        <p class="description"><?php esc_html_e('Used when the banner follows dark appearance.', 'open-cookie-consent'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Conditional Banner','open-cookie-consent'); ?></th>
                    <td>
                        <label><input type="checkbox" name="ui[conditional_banner]" value="1" <?php checked($ui['conditional_banner'] ?? true); ?>> <?php esc_html_e('Only show when non-essential detected','open-cookie-consent'); ?></label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function renderAdvancedTab($settings) {
        $adv = $settings['advanced'] ?? [];
        ?>
        <form method="post" id="occ-settings-form" data-tab="advanced">
            <?php wp_nonce_field('occ_admin_nonce','occ_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Test Cookie','open-cookie-consent'); ?></th>
                    <td>
                        <label><input type="checkbox" name="advanced[test_cookie]" value="1" <?php checked($adv['test_cookie'] ?? false); ?>> <?php esc_html_e('Enable synthetic non-essential detection for testing','open-cookie-consent'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Custom CSS','open-cookie-consent'); ?></th>
                    <td>
                        <textarea name="advanced[custom_css]" rows="6" class="large-text code" placeholder="/* Custom styles for cookie consent UI */"><?php echo esc_textarea($adv['custom_css'] ?? ''); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Debug Mode','open-cookie-consent'); ?></th>
                    <td>
                        <label><input type="checkbox" name="advanced[debug]" value="1" <?php checked($adv['debug'] ?? false); ?>> <?php esc_html_e('Enable debug logging (do not use in production)','open-cookie-consent'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cloudflare API Token','open-cookie-consent'); ?></th>
                    <td><input type="password" name="advanced[cloudflare][token]" class="regular-text" value="<?php echo esc_attr($adv['cloudflare']['token'] ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cloudflare Zone','open-cookie-consent'); ?></th>
                    <td><input type="text" name="advanced[cloudflare][zone]" class="regular-text" value="<?php echo esc_attr($adv['cloudflare']['zone'] ?? ''); ?>"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function renderCategoriesTab($settings) {
        $cats = $settings['categories'] ?? [];
        ?>
        <form method="post" id="occ-settings-form" data-tab="categories">
            <?php wp_nonce_field('occ_admin_nonce','occ_nonce'); ?>
            <div class="occ-categories-list">
            <?php foreach ($cats as $key => $cat): ?>
                <div class="occ-category-row">
                    <h3><?php echo esc_html($cat['label'] ?? ucfirst($key)); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Label','open-cookie-consent'); ?></th>
                            <td><input type="text" name="categories[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($cat['label'] ?? ''); ?>" class="regular-text" <?php echo ($cat['locked'] ?? false) ? 'readonly' : ''; ?>></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Description','open-cookie-consent'); ?></th>
                            <td><textarea name="categories[<?php echo esc_attr($key); ?>][description]" rows="3" class="large-text" <?php echo ($cat['locked'] ?? false) ? 'readonly' : ''; ?>><?php echo esc_textarea($cat['description'] ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enabled','open-cookie-consent'); ?></th>
                            <td>
                                <label><input type="checkbox" name="categories[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($cat['enabled'] ?? true); ?> <?php echo ($cat['locked'] ?? false) ? 'disabled' : ''; ?>> <?php esc_html_e('Show this category','open-cookie-consent'); ?></label>
                                <input type="hidden" name="categories[<?php echo esc_attr($key); ?>][locked]" value="<?php echo ($cat['locked'] ?? false) ? '1':'0'; ?>">
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
            </div>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function renderScannerTab($settings) {
        $scan = $settings['scanner'] ?? [];
        $meta = $scan['ocd_meta'] ?? [];
        ?>
        <div class="occ-scanner-tab">
            <div class="occ-scanner-actions">
                <button type="button" id="occ-run-scan" class="button button-primary"><?php esc_html_e('Run Scan Now','open-cookie-consent'); ?></button>
                <button type="button" id="occ-update-ocd" class="button"><?php esc_html_e('Update Cookie Database','open-cookie-consent'); ?></button>
                <div id="occ-scan-status" class="occ-scan-status"></div>
            </div>
            <div class="occ-cookies-table-wrapper">
                <h3><?php esc_html_e('Open Cookie Database Status','open-cookie-consent'); ?></h3>
                <p><?php esc_html_e('Last Updated','open-cookie-consent'); ?>: <strong><?php echo esc_html($meta['updated_at'] ?? __('Unknown','open-cookie-consent')); ?></strong></p>
                <p><?php esc_html_e('Checksum','open-cookie-consent'); ?>: <code><?php echo esc_html($meta['checksum'] ?? '-'); ?></code></p>
                <p><?php esc_html_e('Total Vendors','open-cookie-consent'); ?>: <strong><?php echo intval($meta['vendors'] ?? 0); ?></strong></p>
                <p><?php esc_html_e('Total Cookie Entries','open-cookie-consent'); ?>: <strong><?php echo intval($meta['records'] ?? 0); ?></strong></p>
                <p><?php esc_html_e('Mapped Domains','open-cookie-consent'); ?>: <strong><?php echo intval($meta['mapped_domains'] ?? 0); ?></strong></p>
                <p><?php printf(esc_html__('Source: %s','open-cookie-consent'), '<a href="'.esc_url($scan['ocd_source_url'] ?? '').'" target="_blank" rel="noopener">'.esc_html($scan['ocd_source_url'] ?? '').'</a>'); ?></p>
                <p><?php printf(esc_html__('Cookie lists are fetched from %s.','open-cookie-consent'), '<a href="https://github.com/jkwakman/Open-Cookie-Database" target="_blank" rel="noopener">Open Cookie Database</a>'); ?></p>
            </div>
            <form method="post" id="occ-settings-form" data-tab="scanner">
                <?php wp_nonce_field('occ_admin_nonce','occ_nonce'); ?>
                <h3><?php esc_html_e('Scan Settings','open-cookie-consent'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('OCD Source URL','open-cookie-consent'); ?></th>
                        <td>
                            <input type="url" name="scanner[ocd_source_url]" class="regular-text" value="<?php echo esc_attr($scan['ocd_source_url'] ?? ''); ?>">
                            <p class="description"><?php esc_html_e('Set a custom source for cookie definitions (e.g., self-hosted JSON).','open-cookie-consent'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Interval','open-cookie-consent'); ?></th>
                        <td>
                            <select name="scanner[interval]">
                                <?php $ival = $scan['interval'] ?? 'daily'; ?>
                                <option value="hourly" <?php selected($ival,'hourly'); ?>><?php esc_html_e('Hourly','open-cookie-consent'); ?></option>
                                <option value="daily" <?php selected($ival,'daily'); ?>><?php esc_html_e('Daily','open-cookie-consent'); ?></option>
                                <option value="weekly" <?php selected($ival,'weekly'); ?>><?php esc_html_e('Weekly','open-cookie-consent'); ?></option>
                                <option value="custom" <?php selected($ival,'custom'); ?>><?php esc_html_e('Custom CRON','open-cookie-consent'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Daily Time','open-cookie-consent'); ?></th>
                        <td><input type="time" name="scanner[time_local]" value="<?php echo esc_attr($scan['time_local'] ?? '03:00'); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom CRON','open-cookie-consent'); ?></th>
                        <td><input type="text" name="scanner[custom_cron]" value="<?php echo esc_attr($scan['custom_cron'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Pages to Scan','open-cookie-consent'); ?></th>
                        <td><textarea name="scanner[pages_text]" rows="5" class="large-text" placeholder="/\n/about\n/contact"><?php echo esc_textarea(implode("\n", $scan['pages'] ?? ['/'])); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Runtime Sampler','open-cookie-consent'); ?></th>
                        <td><label><input type="checkbox" name="scanner[runtime_sampler]" value="1" <?php checked($scan['runtime_sampler'] ?? false); ?>> <?php esc_html_e('Enable JavaScript runtime detection','open-cookie-consent'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-update Cookie DB','open-cookie-consent'); ?></th>
                        <td><label><input type="checkbox" name="scanner[ocd_auto_update]" value="1" <?php checked($scan['ocd_auto_update'] ?? true); ?>> <?php esc_html_e('Monthly auto-update','open-cookie-consent'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Notify','open-cookie-consent'); ?></th>
                        <td>
                            <label><input type="checkbox" name="scanner[notify][email]" value="1" <?php checked(($scan['notify']['email'] ?? false)); ?>> <?php esc_html_e('Email admin on scan','open-cookie-consent'); ?></label><br>
                            <input type="url" name="scanner[notify][webhook]" class="regular-text" placeholder="https://..." value="<?php echo esc_attr($scan['notify']['webhook'] ?? ''); ?>">
                        </td>
                    </tr>
                </table>
                <h3><?php esc_html_e('Custom Cookie Mappings','open-cookie-consent'); ?></h3>
                <p class="description"><?php esc_html_e('Override categories/vendors for cookies or scripts not covered by the database. Provide JSON array of mappings.','open-cookie-consent'); ?></p>
                <p><code>[{"type":"domain","value":"example.com","category":"analytics","vendor":"Example","description":"Custom"},{"type":"cookie_name","value":"_custom","category":"advertising"}]</code></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Mappings JSON','open-cookie-consent'); ?></th>
                        <td>
                            <textarea name="scanner[custom_mappings_text]" rows="6" class="large-text code" placeholder='[{"type":"domain","value":"example.com","category":"analytics","vendor":"Example"}]'><?php echo esc_textarea(json_encode($scan['custom_mappings'] ?? [], JSON_PRETTY_PRINT)); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function renderIntegrationsTab($settings) {
        $int = $settings['integrations'] ?? [];
        ?>
        <form method="post" id="occ-settings-form" data-tab="integrations">
            <?php wp_nonce_field('occ_admin_nonce','occ_nonce'); ?>
            <h3><?php esc_html_e('Google Consent Mode v2','open-cookie-consent'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable GCMv2','open-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="integrations[gcmv2]" value="1" <?php checked($int['gcmv2'] ?? true); ?>> <?php esc_html_e('Manage consent signals for Google tags','open-cookie-consent'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('DataLayer Event Name','open-cookie-consent'); ?></th>
                    <td><input type="text" name="integrations[datalayer_event]" class="regular-text" value="<?php echo esc_attr($int['datalayer_event'] ?? 'occ_consent_update'); ?>"></td>
                </tr>
            </table>
            <h3><?php esc_html_e('Plausible','open-cookie-consent'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Plausible','open-cookie-consent'); ?></th>
                    <td><label><input type="checkbox" name="integrations[plausible][enabled]" value="1" <?php checked($int['plausible']['enabled'] ?? false); ?>> <?php esc_html_e('Send client-side consent events','open-cookie-consent'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Event Name','open-cookie-consent'); ?></th>
                    <td><input type="text" name="integrations[plausible][event_name]" class="regular-text" value="<?php echo esc_attr($int['plausible']['event_name'] ?? 'cookie_consent'); ?>"></td>
                </tr>
            </table>
            
            <h3><?php esc_html_e('WP Consent API','open-cookie-consent'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('WP Consent API Integration','open-cookie-consent'); ?></th>
                    <td>
                        <?php if (function_exists('wp_has_consent')): ?>
                            <label>
                                <input type="checkbox" name="integrations[wp_consent_api][enabled]" value="1" <?php checked($int['wp_consent_api']['enabled'] ?? true); ?>>
                                <?php esc_html_e('Enable WP Consent API integration','open-cookie-consent'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Automatically sync consent decisions with other plugins using the WP Consent API standard.','open-cookie-consent'); ?>
                                <br><strong><?php esc_html_e('✅ WP Consent API plugin detected and active.','open-cookie-consent'); ?></strong>
                            </p>
                        <?php else: ?>
                            <p class="description">
                                <?php esc_html_e('❌ WP Consent API plugin not detected.','open-cookie-consent'); ?>
                                <a href="https://wordpress.org/plugins/wp-consent-api/" target="_blank"><?php esc_html_e('Install WP Consent API','open-cookie-consent'); ?></a>
                                <?php esc_html_e('to enable integration with other consent-aware plugins.','open-cookie-consent'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function renderInventoryTab($settings) {
        $inventory = $settings['inventory'] ?? [];
        $cookies = $inventory['cookies'] ?? [];
        $categories = apply_filters('occ_categories', $settings['categories'] ?? []);
        ?>
        <form method="post" id="occ-settings-form" data-tab="inventory">
            <?php wp_nonce_field('occ_admin_nonce','occ_nonce'); ?>
            <h3><?php esc_html_e('Detected Cookies','open-cookie-consent'); ?></h3>
            <?php if (!empty($cookies)): ?>

                <div class="occ-inventory-list">
                    <?php foreach ($cookies as $index => $cookie): 
                        $categoryKey = sanitize_key($cookie['category'] ?? '');
                        if (in_array($categoryKey, ['functional','security'], true)) {
                            $categoryKey = 'necessary';
                        }
                        $categoryLabel = $categories[$categoryKey]['label'] ?? ucfirst($categoryKey ?: 'necessary');
                        $isManual = !empty($cookie['manual']);
                    ?>
                        <div class="occ-inventory-item">
                            <div class="occ-inventory-item-header">
                                <h4 class="occ-cookie-name"><?php echo esc_html($cookie['name'] ?? ''); ?></h4>
                                <div class="occ-inventory-actions">
                                    <?php if ($isManual): ?>
                                        <span class="occ-manual-badge"><?php esc_html_e('Manual', 'open-cookie-consent'); ?></span>
                                    <?php endif; ?>
                                    <label class="occ-inventory-remove">
                                        <input type="checkbox" name="inventory[existing][<?php echo esc_attr($index); ?>][remove]" value="1">
                                        <?php esc_html_e('Remove','open-cookie-consent'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="occ-inventory-fields">
                                <div class="occ-field-row">
                                    <div class="occ-field-col">
                                        <label>
                                            <span class="occ-field-label"><?php esc_html_e('Category','open-cookie-consent'); ?></span>
                                            <select name="inventory[existing][<?php echo esc_attr($index); ?>][category]" class="occ-select">
                                                <?php foreach ($categories as $key => $data): ?>
                                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $categoryKey); ?>><?php echo esc_html($data['label'] ?? ucfirst($key)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="occ-field-col">
                                        <label>
                                            <span class="occ-field-label"><?php esc_html_e('Vendor','open-cookie-consent'); ?></span>
                                            <input type="text" name="inventory[existing][<?php echo esc_attr($index); ?>][vendor]" value="<?php echo esc_attr($cookie['vendor'] ?? ''); ?>" class="occ-input" placeholder="<?php esc_attr_e('e.g. Google', 'open-cookie-consent'); ?>">
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="occ-field-row">
                                    <div class="occ-field-col occ-field-col-full">
                                        <label>
                                            <span class="occ-field-label"><?php esc_html_e('Description','open-cookie-consent'); ?></span>
                                            <textarea name="inventory[existing][<?php echo esc_attr($index); ?>][description]" rows="3" class="occ-textarea" placeholder="<?php esc_attr_e('Brief description of what this cookie does...', 'open-cookie-consent'); ?>"><?php echo esc_textarea($cookie['description'] ?? ''); ?></textarea>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="occ-field-row">
                                    <div class="occ-field-col occ-field-col-full">
                                        <label>
                                            <span class="occ-field-label"><?php esc_html_e('Source URL','open-cookie-consent'); ?></span>
                                            <input type="url" name="inventory[existing][<?php echo esc_attr($index); ?>][source]" value="<?php echo esc_attr($cookie['source'] ?? ''); ?>" class="occ-input" placeholder="https://example.com/script.js">
                                        </label>
                                    </div>
                                </div>
                                
                                <?php if (!empty($cookie['confidence'])): ?>
                                    <div class="occ-field-meta">
                                        <small class="occ-confidence"><?php printf(esc_html__('Detection confidence: %d%%', 'open-cookie-consent'), round($cookie['confidence'] * 100)); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="inventory[existing][<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($cookie['name'] ?? ''); ?>">
                            <input type="hidden" name="inventory[existing][<?php echo esc_attr($index); ?>][manual]" value="<?php echo $isManual ? '1' : '0'; ?>">
                            <input type="hidden" name="inventory[existing][<?php echo esc_attr($index); ?>][confidence]" value="<?php echo esc_attr($cookie['confidence'] ?? 0); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <p><?php esc_html_e('No cookies detected yet. Run a scan or add a cookie below.', 'open-cookie-consent'); ?></p>
            <?php endif; ?>

            <h3><?php esc_html_e('Add Cookie Manually','open-cookie-consent'); ?></h3>
            <div class="occ-add-cookie-form">
                <div class="occ-field-row">
                    <div class="occ-field-col">
                        <label for="occ-inventory-name">
                            <span class="occ-field-label"><?php esc_html_e('Cookie Name','open-cookie-consent'); ?> <span class="required">*</span></span>
                            <input type="text" id="occ-inventory-name" name="inventory[new][name]" class="occ-input" placeholder="<?php esc_attr_e('e.g. _ga','open-cookie-consent'); ?>" required>
                        </label>
                    </div>
                    <div class="occ-field-col">
                        <label for="occ-inventory-category">
                            <span class="occ-field-label"><?php esc_html_e('Category','open-cookie-consent'); ?></span>
                            <select id="occ-inventory-category" name="inventory[new][category]" class="occ-select">
                                <?php foreach ($categories as $key => $category): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($category['label'] ?? ucfirst($key)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
                
                <div class="occ-field-row">
                    <div class="occ-field-col">
                        <label for="occ-inventory-vendor">
                            <span class="occ-field-label"><?php esc_html_e('Vendor','open-cookie-consent'); ?></span>
                            <input type="text" id="occ-inventory-vendor" name="inventory[new][vendor]" class="occ-input" placeholder="<?php esc_attr_e('e.g. Google', 'open-cookie-consent'); ?>">
                        </label>
                    </div>
                    <div class="occ-field-col">
                        <label for="occ-inventory-source">
                            <span class="occ-field-label"><?php esc_html_e('Source URL','open-cookie-consent'); ?></span>
                            <input type="url" id="occ-inventory-source" name="inventory[new][source]" class="occ-input" placeholder="https://example.com/script.js">
                        </label>
                    </div>
                </div>
                
                <div class="occ-field-row">
                    <div class="occ-field-col occ-field-col-full">
                        <label for="occ-inventory-description">
                            <span class="occ-field-label"><?php esc_html_e('Description','open-cookie-consent'); ?></span>
                            <textarea id="occ-inventory-description" name="inventory[new][description]" rows="3" class="occ-textarea" placeholder="<?php esc_attr_e('Brief description of what this cookie does...', 'open-cookie-consent'); ?>"></textarea>
                        </label>
                    </div>
                </div>
                
                <p class="description"><?php esc_html_e('Functional and security entries are treated as necessary. Manual entries are preserved during automatic scans.', 'open-cookie-consent'); ?></p>
            </div>

            <?php submit_button(__('Save Inventory', 'open-cookie-consent')); ?>
        </form>
        <?php
    }

    private function renderHelpTab() {
        ?>
        <div class="occ-help">
            <h3><?php esc_html_e('Getting Started', 'open-cookie-consent'); ?></h3>
            <ol>
                <li><?php esc_html_e('Run a manual scan after activation to populate your cookie inventory.', 'open-cookie-consent'); ?></li>
                <li><?php esc_html_e('Review detected categories and adjust labels and descriptions under the Categories tab.', 'open-cookie-consent'); ?></li>
                <li><?php esc_html_e('Gate marketing and analytics scripts using the OCC data attributes or JavaScript API.', 'open-cookie-consent'); ?></li>
            </ol>

            <h3><?php esc_html_e('Key Resources', 'open-cookie-consent'); ?></h3>
            <ul>
                <li><a href="https://github.com/dynumo/open-cookie-consent" target="_blank" rel="noopener"><?php esc_html_e('Project README and changelog', 'open-cookie-consent'); ?></a></li>
                <li><a href="https://github.com/dynumo/open-cookie-consent/wiki" target="_blank" rel="noopener"><?php esc_html_e('Documentation wiki and implementation guides', 'open-cookie-consent'); ?></a></li>
                <li><a href="https://github.com/dynumo/open-cookie-consent/wiki/JavaScript-API" target="_blank" rel="noopener"><?php esc_html_e('JavaScript API & consent events', 'open-cookie-consent'); ?></a></li>
                <li><a href="https://github.com/dynumo/open-cookie-consent/wiki/Hooks" target="_blank" rel="noopener"><?php esc_html_e('WordPress hooks and filters reference', 'open-cookie-consent'); ?></a></li>
            </ul>

            <h3><?php esc_html_e('Troubleshooting', 'open-cookie-consent'); ?></h3>
            <ul>
                <li><?php esc_html_e('Enable Debug Mode under Advanced settings to surface OCC notices in the browser console.', 'open-cookie-consent'); ?></li>
                <li><?php esc_html_e('Trigger a manual scan if new cookies appear or categories look outdated.', 'open-cookie-consent'); ?></li>
                <li><?php esc_html_e('Use the occ.consent.get() helper in your browser console to confirm the stored consent state.', 'open-cookie-consent'); ?></li>
            </ul>

            <p><?php esc_html_e('Need help or want to request a feature? Reach out on GitHub:', 'open-cookie-consent'); ?>
                <a href="https://github.com/dynumo/open-cookie-consent/issues" target="_blank" rel="noopener"><?php esc_html_e('Issues', 'open-cookie-consent'); ?></a> |
                <a href="https://github.com/dynumo/open-cookie-consent/discussions" target="_blank" rel="noopener"><?php esc_html_e('Discussions', 'open-cookie-consent'); ?></a>
            </p>
            <p><?php esc_html_e('Version', 'open-cookie-consent'); ?>: <?php echo esc_html(OCC_VERSION); ?></p>
        </div>
        <?php
    }

    public function handleSaveSettings() {
        check_ajax_referer('occ_admin_nonce','nonce');
        if (!current_user_can('manage_options')) { wp_die(__('Insufficient permissions','open-cookie-consent')); }

        $tab = sanitize_text_field($_POST['tab'] ?? '');
        $new = [];
        if ($tab === 'general') {
            $ui = $_POST['ui'] ?? [];
            $baseColour = sanitize_hex_color($ui['color'] ?? ($ui['color_light'] ?? '#2797DD')) ?: '#2797DD';
            $colorLight = sanitize_hex_color($ui['color_light'] ?? $baseColour) ?: $baseColour;
            $colorDark = sanitize_hex_color($ui['color_dark'] ?? $colorLight) ?: $colorLight;
            $new['ui'] = [
                'position' => sanitize_text_field($ui['position'] ?? 'modal_center'),
                'theme' => sanitize_text_field($ui['theme'] ?? 'auto'),
                'color' => $baseColour,
                'color_light' => $colorLight,
                'color_dark' => $colorDark,
                'conditional_banner' => !empty($ui['conditional_banner']),
            ];
        } elseif ($tab === 'categories') {
            $cats = $_POST['categories'] ?? [];
            $san = [];
            foreach ($cats as $k => $c) {
                $key = sanitize_key($k);
                $san[$key] = [
                    'label' => sanitize_text_field($c['label'] ?? ''),
                    'description' => sanitize_textarea_field($c['description'] ?? ''),
                    'enabled' => !empty($c['enabled']),
                    'locked' => !empty($c['locked'])
                ];
            }
            $new['categories'] = $san;
        } elseif ($tab === 'scanner') {

            $sc = $_POST['scanner'] ?? [];

            $pages_text = $_POST['scanner']['pages_text'] ?? '';

            $pages = array_filter(array_map('trim', preg_split('/\r?\n/', (string)$pages_text)));

            $maps_text = $_POST['scanner']['custom_mappings_text'] ?? '';

            $maps_arr = json_decode((string)$maps_text, true);

            if (!is_array($maps_arr)) { $maps_arr = []; }

            $new['scanner'] = [

                'interval' => sanitize_text_field($sc['interval'] ?? 'daily'),

                'time_local' => sanitize_text_field($sc['time_local'] ?? '03:00'),

                'custom_cron' => sanitize_text_field($sc['custom_cron'] ?? ''),

                'pages' => array_map('esc_url_raw', $pages ?: ['/']),

                'ocd_auto_update' => !empty($sc['ocd_auto_update']),

                'runtime_sampler' => !empty($sc['runtime_sampler']),

                'notify' => [

                    'email' => !empty($sc['notify']['email']),

                    'webhook' => esc_url_raw($sc['notify']['webhook'] ?? '')

                ],

                'custom_mappings' => $maps_arr

            ];

        } elseif ($tab === 'inventory') {

            $settings_instance = OCC_Settings::getInstance();

            $inventory = $settings_instance->get('inventory', []);



            $existingInput = $_POST['inventory']['existing'] ?? [];

            $compiled = [];



            foreach ($existingInput as $row) {

                $name = sanitize_text_field($row['name'] ?? '');

                if ($name === '' || !empty($row['remove'])) {

                    continue;

                }



                $compiled[] = [

                    'name' => $name,

                    'category' => sanitize_key($row['category'] ?? 'necessary'),

                    'vendor' => sanitize_text_field($row['vendor'] ?? ''),

                    'description' => sanitize_textarea_field($row['description'] ?? ''),

                    'source' => esc_url_raw($row['source'] ?? ''),

                    'confidence' => floatval($row['confidence'] ?? 0),

                    'manual' => !empty($row['manual'])

                ];

            }



            $newCookie = $_POST['inventory']['new'] ?? [];

            $newName = sanitize_text_field($newCookie['name'] ?? '');

            if ($newName !== '') {

                $compiled[] = [

                    'name' => $newName,

                    'category' => sanitize_key($newCookie['category'] ?? 'necessary'),

                    'vendor' => sanitize_text_field($newCookie['vendor'] ?? ''),

                    'description' => sanitize_textarea_field($newCookie['description'] ?? ''),

                    'source' => esc_url_raw($newCookie['source'] ?? ''),

                    'confidence' => 1.0,

                    'manual' => true

                ];

            }



            $combined = [];

            foreach ($compiled as $cookie) {

                $key = strtolower($cookie['name'] ?? '') . '|' . strtolower($cookie['vendor'] ?? '');

                if (isset($combined[$key])) {

                    $existing = $combined[$key];

                    $cookie['manual'] = !empty($cookie['manual']) || !empty($existing['manual']);

                    if (empty($cookie['description']) && !empty($existing['description'])) {

                        $cookie['description'] = $existing['description'];

                    }

                    if (empty($cookie['vendor']) && !empty($existing['vendor'])) {

                        $cookie['vendor'] = $existing['vendor'];

                    }

                }

                $combined[$key] = $cookie;

            }



            $deduped = array_values($combined);



            $inventory['cookies'] = $deduped;

            $inventory['knownNames'] = array_values(array_filter(array_unique(array_map(static function($cookie) {

                return $cookie['name'] ?? '';

            }, $deduped)), 'strlen'));



            $canonical = [];

            foreach ($deduped as $cookie) {

                $canonical[] = ($cookie['name'] ?? '') . '|' . ($cookie['category'] ?? '') . '|' . ($cookie['vendor'] ?? '');

            }

            if (!empty($canonical)) {

                sort($canonical);

                $inventory['version'] = hash('sha256', implode("

", $canonical));

            } else {

                $inventory['version'] = '';

            }



            $new['inventory'] = $inventory;

        } elseif ($tab === 'integrations') {
            $it = $_POST['integrations'] ?? [];
            $new['integrations'] = [
                'gcmv2' => !empty($it['gcmv2']),
                'plausible' => [
                    'enabled' => !empty($it['plausible']['enabled']),
                    'event_name' => sanitize_text_field($it['plausible']['event_name'] ?? 'cookie_consent')
                ],
                'datalayer_event' => sanitize_text_field($it['datalayer_event'] ?? 'occ_consent_update')
            ];
        } elseif ($tab === 'advanced') {
            $adv = $_POST['advanced'] ?? [];
            $new['advanced'] = [
                'test_cookie' => !empty($adv['test_cookie']),
                'custom_css' => wp_strip_all_tags($adv['custom_css'] ?? ''),
                'debug' => !empty($adv['debug']),
                'cloudflare' => [
                    'token' => sanitize_text_field($adv['cloudflare']['token'] ?? ''),
                    'zone' => sanitize_text_field($adv['cloudflare']['zone'] ?? '')
                ]
            ];
        }

        if (!empty($new)) {
            $this->settings->update($new);
            wp_send_json_success(['message' => __('Settings saved successfully','open-cookie-consent')]);
        }
        wp_send_json_error(['message' => __('No settings to save','open-cookie-consent')]);
    }

    public function handleExportSettings() {
        check_ajax_referer('occ_admin_nonce','nonce');
        if (!current_user_can('manage_options')) { wp_die(__('Insufficient permissions','open-cookie-consent')); }
        $settings = $this->settings->export();
        $filename = 'occ-settings-'.date('Y-m-d-H-i-s').'.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }

    public function handleImportSettings() {
        check_ajax_referer('occ_admin_nonce','nonce');
        if (!current_user_can('manage_options')) { wp_die(__('Insufficient permissions','open-cookie-consent')); }
        if (empty($_FILES['settings_file']['tmp_name'])) { wp_send_json_error(['message' => __('No file uploaded','open-cookie-consent')]); }
        $data = json_decode(file_get_contents($_FILES['settings_file']['tmp_name']), true);
        if (json_last_error() !== JSON_ERROR_NONE) { wp_send_json_error(['message' => __('Invalid JSON file','open-cookie-consent')]); }
        OCC_Settings::getInstance()->import($data);
        wp_send_json_success(['message' => __('Settings imported successfully','open-cookie-consent')]);
    }

    public function handleResetSettings() {
        check_ajax_referer('occ_admin_nonce','nonce');
        if (!current_user_can('manage_options')) { wp_die(__('Insufficient permissions','open-cookie-consent')); }
        $defaults = OpenCookieConsent::get_default_settings();
        update_option('occ_settings', $defaults);
        wp_send_json_success(['message' => __('Settings reset to defaults','open-cookie-consent')]);
    }
}
