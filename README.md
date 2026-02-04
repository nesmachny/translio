# Translio

A lightweight WordPress translation plugin with Anthropic API integration for automatic translations.

![Version](https://img.shields.io/badge/version-2.3.1-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-green)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/license-GPL--2.0-orange)

## Description

Translio is a WordPress translation plugin that makes multilingual websites easy. Powered by Anthropic's Claude AI, it provides high-quality automatic translations while giving you full control over your content.

### Key Features

- **Multi-Language Support**: Translate your content into up to 4 languages
- **AI-Powered Translations**: Uses Anthropic Claude API for natural, context-aware translations
- **SEO-Friendly URLs**: Clean URL structure with language prefixes (`/es/`, `/de/`, etc.)
- **Page Builder Support**: Works with Elementor, Divi, and Avada
- **WooCommerce Compatible**: Translate products, categories, and attributes
- **Contact Form 7 Support**: Translate form labels, messages, and emails
- **Manual Override**: Edit any translation manually
- **Translation Memory**: Reuse existing translations automatically

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Valid Translio license (for API access)

## Installation

1. Download the latest release ZIP file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and click "Install Now"
4. Activate the plugin
5. Go to Translio → Settings to configure your languages

## Configuration

### Language Setup

1. Navigate to **Translio → Settings**
2. Select your **Default Language** (the language your content is written in)
3. Select up to 4 **Translation Languages** using the checkboxes
4. Click **Save Settings**

### License Activation

1. Enter your license domain in the settings
2. Purchase credits or a BYOAI subscription at [translio.to](https://translio.to)

## Usage

### Translating Content

1. Go to **Translio → All Content**
2. Select the language tab you want to translate into
3. Click "Translate" next to any post/page
4. Review and edit translations as needed
5. Click "Save" to store translations

### Bulk Translation

- Use "Translate All Untranslated" to automatically translate all content
- Use "Translate Changed" to update translations for modified content

### Language Switcher

Add the language switcher to your site:

- **Widget**: Use the Translio Language Switcher widget
- **Shortcode**: `[translio_switcher]`
- **Floating Switcher**: Enable in Settings → Language Switcher

## File Structure

```
translio/
├── admin/
│   ├── css/           # Admin styles
│   ├── js/            # Admin scripts
│   └── images/        # Admin assets
├── assets/
│   ├── css/           # Frontend styles
│   └── js/            # Frontend scripts
├── includes/
│   ├── admin/         # Admin page classes
│   ├── class-translio.php          # Main plugin class
│   ├── class-translio-api.php      # API integration
│   ├── class-translio-content.php  # Frontend content filters
│   ├── class-translio-router.php   # URL routing
│   ├── class-translio-db.php       # Database operations
│   └── ...
├── languages/         # Translation files
├── translio.php       # Main plugin file
├── uninstall.php      # Cleanup on uninstall
└── CHANGELOG.md       # Version history
```

## Development

### Local Development

The plugin is developed and tested using Local by Flywheel:

| Environment | URL | Path |
|-------------|-----|------|
| Translio Test | http://localhost:10008 | `~/Local Sites/translio-test/` |

The plugin folder is symlinked:
```bash
~/Local Sites/translio-test/app/public/wp-content/plugins/translio -> ~/Software Development/Translio
```

### Building a Release

```bash
# Create release ZIP
cd ~/Software\ Development/Translio
zip -r ~/Desktop/translio-X.X.X.zip . -x "*.git*" -x "*.DS_Store" -x "node_modules/*" -x "*.log"
```

## API Reference

### PHP Functions

```php
// Get Translio instance
$translio = translio();

// Get secondary languages (array of language codes)
$languages = translio()->get_secondary_languages();

// Check if a language is secondary
$is_secondary = translio()->is_secondary_language('es');

// Get current language (from URL)
$current = translio()->get_current_language();

// Get default language
$default = translio()->get_default_language();
```

### Hooks

```php
// Filter translated content before display
add_filter('translio_translated_content', function($content, $post_id, $language) {
    return $content;
}, 10, 3);

// Action after translation is saved
add_action('translio_translation_saved', function($object_id, $field, $language, $translation) {
    // Do something
}, 10, 4);
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history.

### Latest: v2.3.1 (2026-02-04)

- **Fixed**: Meta fields not translating during auto-translate

### v2.3.0 (2026-02-04)

- **Added**: Multi-language support (up to 4 secondary languages)
- **Added**: Language selector tabs in admin pages
- **Changed**: Checkbox-based language selection in Settings
- **Fixed**: API methods now accept target language parameter

## Support

- **Documentation**: [translio.to/docs](https://translio.to/docs)
- **Issues**: [GitHub Issues](https://github.com/nesmachny/translio/issues)
- **Email**: go@translio.to

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## Author

**Sergey Nesmachny**
- Website: [nesmachny.net](https://nesmachny.net)
- Plugin: [translio.to](https://translio.to)
