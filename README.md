# Open Cookie Consent

A privacy-first, GDPR-compliant cookie consent plugin for WordPress with Google Consent Mode v2 support.

![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.8.2-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)

## Overview

Open Cookie Consent is designed with ethical defaults, accessibility, and performance at its core. Built specifically for UK/EU markets with GDPR/UK GDPR + PECR compliance in mind.

### Key Features

- **Conditional Display**: Only shows banner when non-essential cookies are detected
- **Privacy by Design**: Blocks non-essential cookies by default until explicit consent
- **Google Consent Mode v2**: Automatic integration with Google Analytics and Ads
- **Accessibility First**: WCAG 2.2 AA compliant with full keyboard and screen reader support
- **Performance Optimized**: Lightweight and efficient, zero origin writes on user actions
- **Edge Compatible**: Works seamlessly with Cloudflare Enterprise and other CDNs
- **Open Cookie Database**: Automatic cookie detection and categorization
- **Developer Friendly**: Extensive hooks, events, and JavaScript API

## Quick Start

1. **Install the plugin** in your WordPress site
2. **Run an initial scan** to detect cookies
3. **Configure categories** and UI settings
4. **Gate your scripts** using the provided patterns
5. **Test consent flows** to ensure proper functionality

## Script Gating

Convert tracking scripts to respect user consent:

```html
<!-- Before: Standard Google Analytics -->
<script src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>

<!-- After: Gated for Analytics consent -->
<script type="text/plain" data-occ-category="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>

<!-- Inline scripts -->
<script type="text/plain" data-occ-category="advertising">
  fbq('init', '123456789');
  fbq('track', 'PageView');
</script>
```

## JavaScript API

### Consent Management

```javascript
// Get current consent state
const consent = occ.consent.get();
console.log(consent); // { version: "...", choices: {...}, timestamp: ... }

// Set consent for specific category
occ.consent.set('analytics', 'granted');
occ.consent.set('advertising', 'denied');

// Grant all non-necessary categories
occ.consent.grantAllNonNecessary();

// Reject all non-essential categories
occ.consent.rejectAllNonEssential();

// Update consent version (triggers update notice if changed)
occ.consent.updateVersion('2025-01-01T10:00:00Z:abc123');
```

### UI Control

```javascript
// Show/hide banner
occ.ui.showBanner();
occ.ui.hideBanner();

// Open/close preferences modal
occ.ui.openPreferences();
occ.ui.closePreferences();

// Show updated cookies notice
occ.ui.showUpdatedNotice();
```

### Event Handling

```javascript
// Listen for consent events
document.addEventListener('occ:accept_all', function(e) {
  console.log('User accepted all cookies', e.detail);
  // e.detail = { categories: {...}, source: 'banner'|'modal'|'update' }
});

document.addEventListener('occ:reject_nonessential', function(e) {
  console.log('User rejected non-essential cookies', e.detail);
});

document.addEventListener('occ:preferences_saved', function(e) {
  console.log('User saved custom preferences', e.detail);
});

// Updated cookies notice events
document.addEventListener('occ:updated_accept_all', function(e) {
  console.log('User accepted all in update notice', e.detail);
});

document.addEventListener('occ:updated_review', function(e) {
  console.log('User chose to review in update notice', e.detail);
});

document.addEventListener('occ:updated_keep', function(e) {
  console.log('User kept current settings', e.detail);
});
```

### State Monitoring

```javascript
// Listen for consent changes
occ.consent.onChange(function(state) {
  console.log('Consent state changed:', state);
  
  // Update your analytics accordingly
  if (state.choices.analytics === 'granted') {
    // Initialize analytics
  }
});
```

## WordPress Hooks

### Actions

```php
// After scan completion
add_action('occ_after_scan', function($results) {
  error_log('Cookie scan found: ' . count($results['cookies']) . ' cookies');
});

// Before rendering banner
add_action('occ_before_render_banner', function($state) {
  // Modify state before banner display
});

// After consent update
add_action('occ_after_consent_update', function($data) {
  // $data = ['consent' => [...], 'source' => '...', 'timestamp' => ...]
  error_log('Consent updated: ' . json_encode($data));
});
```

### Filters

```php
// Add custom cookie categories
add_filter('occ_categories', function($categories) {
  $categories['marketing'] = [
    'label' => 'Marketing',
    'description' => 'Cookies for marketing campaigns',
    'enabled' => true,
    'locked' => false
  ];
  return $categories;
});

// Extend known tracker detection
add_filter('occ_known_trackers', function($trackers) {
  $trackers['/customTracker\s*\(/'] = [
    'name' => 'Custom Tracker',
    'category' => 'analytics',
    'vendor' => 'My Company'
  ];
  return $trackers;
});

// Override banner display logic
add_filter('occ_should_show_banner', function($should_show, $has_non_essential) {
  // Custom logic for when to show banner
  return $should_show;
}, 10, 2);
```

## Configuration

### Categories

The plugin includes three default categories:

- **Necessary**: Essential cookies (always granted, cannot be disabled)
- **Analytics**: Website usage statistics and analytics
- **Advertising**: Personalized advertising and marketing cookies

Categories can be extended via the `occ_categories` filter.

### UI Customization

Configure appearance in WordPress admin:

- **Position**: Modal (center) or Banner (bottom)
- **Theme**: Auto (system), Light, or Dark
- **Brand Colour**: Customize primary color
- **Border Radius**: Adjust corner rounding
- **Links**: Privacy Policy and Cookie Policy pages

### Scanner Settings

- **Scan Interval**: Hourly, Daily, Weekly, or Custom cron
- **Pages to Scan**: List of URLs to check for cookies
- **Runtime Sampler**: Optional JavaScript-based detection
- **Notifications**: Email and webhook alerts for changes

## Google Consent Mode v2

The plugin automatically manages Google Consent Mode signals:

```javascript
// Default state (before user interaction)
gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied', 
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'security_storage': 'granted'
});

// Updated when user grants analytics
gtag('consent', 'update', {
  'analytics_storage': 'granted'
});

// Updated when user grants advertising  
gtag('consent', 'update', {
  'ad_storage': 'granted',
  'ad_user_data': 'granted',
  'ad_personalization': 'granted'
});
```

## Accessibility

The plugin is built to WCAG 2.2 AA standards:

- **Keyboard Navigation**: Full keyboard support with focus management
- **Screen Readers**: Proper ARIA labels and announcements
- **High Contrast**: Respects system preferences and custom themes
- **Reduced Motion**: Honors `prefers-reduced-motion` setting
- **Focus Management**: Modal focus trapping and logical tab order

## Performance

Optimized for speed and minimal impact:

- **Lightweight Assets**: Optimized JavaScript and CSS with minimal footprint
- **No Synchronous Calls**: All user interactions are handled client-side
- **Edge Compatible**: No per-click origin writes, works with global CDNs
- **Efficient Scanning**: Batched operations with configurable scheduling
- **Lightweight Storage**: Uses localStorage for consent state
- **High Specificity CSS**: Theme-resistant styling without aggressive resets

## Development

### Project Structure

```
open-cookie-consent/
├── admin/                 # WordPress admin interface
│   ├── css/
│   ├── js/
│   └── class-occ-admin.php
├── assets/               # Frontend assets
│   ├── css/
│   └── js/
├── data/                 # Open Cookie Database
│   └── ocd.json
├── includes/             # Core PHP classes
│   ├── class-occ-core.php
│   ├── class-occ-settings.php
│   ├── class-occ-ui.php
│   ├── class-occ-consent-engine.php
│   ├── class-occ-scanner.php
│   └── class-occ-analytics.php
├── languages/            # Translation files
└── open-cookie-consent.php
```

### Building

The plugin is ready to use as-is. For development:

1. Clone the repository
2. Install in WordPress `/wp-content/plugins/`
3. Activate and configure
4. Run initial cookie scan

### Testing

Test consent flows thoroughly:

1. **Banner Display**: Verify shows only when non-essential cookies exist
2. **Consent Persistence**: Check localStorage saves correctly
3. **Script Gating**: Confirm gated scripts only execute after consent
4. **Google Consent Mode**: Verify signals update properly
5. **Accessibility**: Test with keyboard and screen readers
6. **Updated Notice**: Test version mismatch detection

## Browser Support

- **Modern Browsers**: Chrome, Firefox, Safari, Edge (latest versions)
- **Mobile**: iOS 14+, Android 9+
- **Legacy**: Safari 14+ minimum for full functionality

## License

GPL-2.0+ - See [LICENSE](LICENSE) file for details.

## Contributing

Contributions welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Support

- **GitHub Issues**: [Report bugs and request features](https://github.com/dynumo/open-cookie-consent/issues)
- **Documentation**: [Wiki and guides](https://github.com/dynumo/open-cookie-consent/wiki)
- **Discussions**: [Community support](https://github.com/dynumo/open-cookie-consent/discussions)

## Roadmap

See our [project roadmap](https://github.com/dynumo/open-cookie-consent/projects) for planned features and improvements.

---

**Note**: This plugin provides tools for GDPR compliance but does not guarantee compliance. Always consult with legal experts for your specific requirements.