# Translations

This folder contains translation files for Open Cookie Consent.

## Files

- `open-cookie-consent.pot` - Template file for creating new translations
- `en_GB.po` - English (UK) translation with "biscuits" terminology
- `en_GB.mo` - Compiled English (UK) translation
- `compile-translations.php` - PHP script to compile PO files to MO files

## Creating New Translations

1. Copy the `open-cookie-consent.pot` file
2. Rename it to your locale code (e.g., `fr_FR.po` for French)
3. Translate all the `msgstr` fields
4. Compile to `.mo` format using one of these methods:

### Method 1: Using gettext tools (recommended)
```bash
msgfmt your_locale.po -o your_locale.mo
```

### Method 2: Using the included PHP script
```bash
php compile-translations.php
```

### Method 3: Using WordPress tools
Upload the `.po` file and WordPress will automatically generate the `.mo` file.

## Available Translations

- **English (UK)** (`en_GB`) - Uses "biscuits" instead of "cookies" as per UK terminology
- **English (US)** - Default, no translation file needed

## Translation Notes

### UK English Localization
The `en_GB` translation changes "cookies" to "biscuits" throughout the interface, which is more appropriate for UK audiences and regulatory requirements.

Key changes:
- "Cookie Consent" → "Biscuit Consent"
- "Cookie Policy" → "Biscuit Policy" 
- "Cookie Preferences" → "Biscuit Preferences"
- "We use cookies..." → "We use biscuits..."

### Adding New Languages

When creating translations for other languages, consider:

1. **Legal terminology** - Use appropriate legal terms for your jurisdiction
2. **Cultural context** - Adapt messaging to local privacy expectations
3. **GDPR compliance** - Ensure translations maintain legal accuracy
4. **Accessibility** - Keep screen reader compatibility in mind

### Text Domain

All translatable strings use the text domain: `open-cookie-consent`

### Context

Some strings have context to help translators:
- UI elements (buttons, labels)
- Admin interface text
- Help and documentation
- Error messages

## Contributing Translations

To contribute a new translation:

1. Create a new `.po` file based on the `.pot` template
2. Translate all strings accurately
3. Test in WordPress with your locale
4. Submit via GitHub pull request

## File Naming Convention

- POT file: `open-cookie-consent.pot`
- PO files: `{locale}.po` (e.g., `fr_FR.po`, `de_DE.po`)
- MO files: `{locale}.mo` (e.g., `fr_FR.mo`, `de_DE.mo`)

Where `{locale}` follows WordPress locale codes (language_COUNTRY format).

## WordPress Integration

The plugin automatically loads the appropriate translation based on:
1. Site language setting (`WPLANG`)
2. User language preference
3. Browser language headers

Translations are loaded in this order:
1. `wp-content/languages/plugins/open-cookie-consent-{locale}.mo`
2. `wp-content/plugins/open-cookie-consent/languages/{locale}.mo`
3. Default English text