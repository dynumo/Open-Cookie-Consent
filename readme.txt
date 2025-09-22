=== Open Cookie Consent ===
Contributors: adammcbride
Tags: cookies, consent, gdpr, privacy, compliance
Requires at least: 5.0
Tested up to: 6.8.2
Requires PHP: 8.0
Stable tag: 0.2.3
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

A privacy-first, GDPR-compliant cookie consent plugin with Google Consent Mode v2 support.

== Description ==

Open Cookie Consent is a WordPress plugin designed for ethical cookie management with a focus on privacy, accessibility, and performance. Built specifically for UK/EU GDPR compliance.

**Key Features:**

* **Privacy First**: Only shows consent banner when non-essential cookies are detected
* **Google Consent Mode v2**: Full integration with Google Analytics and Ads
* **Accessibility**: WCAG 2.2 AA compliant with keyboard navigation and screen reader support
* **Performance**: Lightweight and optimized for speed
* **Edge Friendly**: No origin writes on user actions, works with Cloudflare Enterprise
* **Open Cookie Database**: Automatic cookie detection and categorization
* **Developer Friendly**: Extensive hooks, events, and API

**How It Works:**

1. Automatic scanning detects cookies on your site
2. Only shows consent UI when non-essential cookies are found
3. Blocks non-essential scripts until explicit consent
4. Updates Google Consent Mode signals automatically
5. Provides granular category-based consent options

**Categories:**

* **Necessary**: Essential site functionality (always allowed)
* **Analytics**: Site usage tracking and statistics
* **Advertising**: Personalized ads and marketing cookies

**For Developers:**

The plugin provides extensive customization options through WordPress hooks and a JavaScript API:

```php
// WordPress Hooks
add_action('occ_after_scan', 'my_scan_callback');
add_filter('occ_categories', 'my_custom_categories');
add_filter('occ_should_show_banner', 'my_banner_logic');
```

```javascript
// JavaScript API
occ.consent.get();
occ.consent.set('analytics', 'granted');
occ.ui.openPreferences();

// Event Listeners
document.addEventListener('occ:accept_all', handleAcceptAll);
```

**Script Gating:**

Gate scripts until consent is given:

```html
<script type="text/plain" data-occ-category="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>
<script type="text/plain" data-occ-category="advertising">fbq('init','123456');</script>
```

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/open-cookie-consent/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin in 'Cookie Consent' admin menu
4. Run an initial scan to detect cookies
5. Customize categories and UI settings as needed

== Frequently Asked Questions ==

= Does this plugin work with caching? =

Yes! The plugin is designed to work with all major caching solutions including Cloudflare Enterprise. It uses localStorage for consent state and avoids origin writes during user interactions.

= How does automatic cookie detection work? =

The plugin scans your site's HTML and JavaScript for known tracking codes and cookies. It uses the Open Cookie Database for automatic categorization and can be configured to run on a schedule.

= Is this plugin GDPR compliant? =

The plugin is designed with GDPR compliance in mind, but compliance depends on your specific implementation and use case. Always consult with legal experts for your specific requirements.

= Can I customize the appearance? =

Yes! The plugin includes theme options (auto/light/dark), customizable colors, border radius, and supports custom CSS for advanced styling.

= Does it support Google Consent Mode? =

Yes, the plugin includes full Google Consent Mode v2 support with automatic signal management for analytics_storage, ad_storage, ad_user_data, and ad_personalization.

= How do I gate scripts? =

Change script tags from `type="text/javascript"` to `type="text/plain"` and add `data-occ-category="category_name"`. For external scripts, use `data-src` instead of `src`.

== Screenshots ==

1. Cookie consent banner with accessibility features
2. Preferences modal with category toggles
3. Admin dashboard with scan results
4. Scanner configuration and detected cookies
5. Integration settings for Google Consent Mode

== Changelog ==

= 0.1.7 =
* Fix for cookie icon
* Persistent custom cookies

= 0.1.7 =
* Inventory tab now lets you edit, remove, and add cookies without rescans wiping changes.
* Manual entries persist across scans and their descriptions stay put; scanner merges them with new detections.
* Floating reopen button gets a proper cookie icon & circle, and necessary blocks stack vertically.
* Scanner and admin handlers tightened up to avoid critical errors in 0.1.6.

= 0.1.6 =
* Added overlay for the centred banner and refreshed modal styling so necessary subcategories feel coherent.
* Introduced the [occ_detected_cookies] shortcode plus an Inventory tab for reviewing and adding cookies manually.
* Split accent colours for light and dark modes and tweaked defaults to use UK English wording.
* Improved consent gating so scripts activate immediately on approval and are removed again when revoked.

= 0.1.5 =
* Fix fatal error introduced in 0.1.4 by restoring ternary conditionals in UI renderer
* Ensure dynamic CSS still computes primary text colour correctly

= 0.1.4 =
* Detect and label WordPress logged-in/session cookies in the scanner output
* Show detected cookies inside the preferences modal with clearer category layout
* Polish toggles, close button, and reopen icon styling
* Expand the admin Help tab with inline documentation and quick-start guidance

= 0.1.0 =
* Initial release
* Cookie consent banner and preferences modal
* Google Consent Mode v2 integration
* Automatic cookie scanning with Open Cookie Database
* WCAG 2.2 AA accessibility compliance
* Plausible Analytics integration
* WordPress admin interface with 7 configuration tabs
* Developer hooks and JavaScript API
* Script gating functionality
* Multi-language support ready

== Upgrade Notice ==

= 0.1.0 =
Initial release of Open Cookie Consent plugin.

== Privacy Policy ==

Open Cookie Consent does not collect, store, or transmit any personal data. All consent preferences are stored locally in the user's browser using localStorage. 

Optional features:
* Plausible Analytics integration (when enabled) sends anonymized consent event data
* Aggregate counters (disabled by default) store daily totals with no personal data
* Email/webhook notifications contain only technical scan results

== Support ==

For support, documentation, and to report issues, visit:
https://github.com/dynumo/open-cookie-consent

== Contributing ==

Contributions are welcome! Please see our GitHub repository for guidelines:
https://github.com/dynumo/open-cookie-consent
