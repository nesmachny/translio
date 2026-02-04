# Changelog

All notable changes to Translio plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.1] - 2026-02-04

### Fixed
- **Meta Fields Translation**: Fixed custom meta fields (like "Hero Title", etc.) not being translated when clicking "Auto-translate all fields". Aligned filtering patterns between admin display and API translation.

---

## [2.3.0] - 2026-02-04

### Added
- **Multi-Language Support**: Now supports up to 4 secondary (translation) languages instead of just one
  - New checkbox-based language selection in Settings page
  - Select up to 4 languages to translate your content into
  - URL prefixes for each language (/es/, /de/, /fr/, etc.)
  - Language selector tabs in admin pages (Dashboard, All Content, etc.)
  - User preference saved per admin user
- **New Methods**:
  - `translio()->get_secondary_languages()` - returns array of all secondary languages
  - `translio()->is_secondary_language($code)` - check if code is a secondary language
  - `Translio_Admin::get_admin_language()` - get current admin panel language
  - `Translio_Admin::render_language_selector()` - render language tabs UI

### Changed
- **Router**: Now creates rewrite rules for all configured secondary languages
- **hreflang Tags**: Now outputs hreflang for all languages with translations
- **Language Switcher**: Shows all configured languages in frontend widget
- **AJAX Handlers**: All 28+ handlers now accept `language_code` parameter
- **Admin Pages**: Dashboard, All Content pages now have language selector tabs
- **JavaScript**: Added `getAjaxData()` helper to include language_code in AJAX calls

### Fixed
- **API Class**: `translate_post()` and `translate_term()` now accept target language parameter
- **Frontend Content**: All translation filters now use dynamic language detection from URL
- **Admin Pages**: All admin page renders now use `Translio_Admin::get_admin_language()` for proper language context
- **AJAX Handlers**: `translate_single`, `translate_all`, `translate_changes` now pass language to API methods

### Migration
- Automatic migration from single `translio_secondary_language` option to new `translio_secondary_languages` array
- Existing installations will have their single language migrated automatically
- Backward compatibility maintained: `get_secondary_language()` returns first language

---

## [2.2.2] - 2026-01-14

### Added
- **Feedback Button**: Added "Feedback" button to header of all plugin admin pages
  - Opens modal dialog with feedback form
  - Pre-fills email with WordPress admin email
  - Character counter for message (max 2000 characters)
  - Sends feedback to go@translio.to with site info (URL, WP version, plugin version)
  - Success confirmation after sending

---

## [2.2.1] - 2026-01-14

### Fixed
- **Bug #1: Wrong object_type for pages**: Pages were saved with `object_type='post'` instead of `'page'`, causing REST API `/page/{slug}` endpoint to not find translations
- **Bug #2: Missing parent field**: REST API responses now include `parent` field for page hierarchy support in headless CMS

### Added
- **Migration**: Automatic one-time migration to fix existing page translations in database (changes `object_type` from 'post' to 'page' for all page translations)

### Changed
- **Translation saving**: Now uses actual `post_type` (page, post, product, etc.) instead of hard-coded 'post'
- **Translation reading**: All content filters now use correct post type when querying translations

---

## [2.2.0] - 2026-01-13

### Added
- **Credit Packages Breakdown**: Settings page now shows all purchased credit packages with:
  - Package name
  - Balance remaining
  - Expiration date with days countdown
  - Warning highlight for packages expiring within 7 days
- **Credits for BYOAI Users**: BYOAI users now see their purchased credits balance (previously hidden)

### Changed
- **API Response**: `/validate` endpoint now returns `credit_packages` array with full breakdown

---

## [2.1.9] - 2026-01-12

### Changed
- **Buy Credits Always Visible**: Credit packages now shown for ALL users (including Pro BYOAI) for upsell
- **Pro BYOAI Card**: For BYOAI users shows "ACTIVE" badge, expiry date, and "Renew" buttons
- **Pro BYOAI Card**: For non-BYOAI users shows price and "Subscribe" buttons

---

## [2.1.8] - 2026-01-12

### Fixed
- **Subscription Card CSS**: Fixed CSS specificity issue causing white background instead of gradient
- Card now properly shows purple gradient with white text

---

## [2.1.7] - 2026-01-12

### Added
- **Refresh Button**: Added refresh license button for all users (previously only for non-BYOAI)
- **Cache Auto-Clear**: License cache now cleared on plugin version update

### Fixed
- **Pro BYOAI Subscription Card**: Fixed subscription card not showing due to stale cache
- **Settings Page**: Subscription info now properly displayed for BYOAI users

---

## [2.1.6] - 2026-01-12

### Fixed (API Server)
- **Batch Translation Key Mapping**: Fixed critical bug where server returned numeric indices instead of field IDs
  - Before: `{'0': 'Hallo', '1': 'Welt'}` (translations didn't save)
  - After: `{'title': 'Hallo', 'content': 'Welt'}` (correct)
- **JSON Parse Errors**: Improved handling of Claude's JSON responses
  - Extracts JSON from markdown code blocks
  - Repairs truncated JSON by adding missing brackets
  - Converts array responses to objects using original keys

### Changed (API Server)
- **Model Switch**: Changed from Claude Sonnet to Haiku for 5-10x faster translations
- **Content Chunking**: Batches >5K characters now translated field-by-field
  - Prevents JSON truncation on large content
  - Each field translated individually with proper key preservation
- **Server Timeouts**: Increased nginx/PHP timeouts to 300 seconds

---

## [2.1.5] - 2025-01-11

### Changed
- **Documentation**: Updated CLAUDE.md and CHANGELOG.md with accurate version history
- **Code Sync**: Synchronized all development environments

### Deployed
- Uploaded to api.translio.to update server
- Version updated in API (translio_plugin_translio_version option)

---

## [2.1.4] - 2025-01-10

### Added
- **Buy Credits Section**: In-plugin credit package purchasing
  - Displays available credit packages from API (100K, 500K, 1M, etc.)
  - Shows package name, price, and validity period
  - "Popular" badge for recommended packages
  - Direct checkout links to api.translio.to
- **Pro BYOAI Subscription**: Unlimited translations with own API key
  - Monthly subscription option ($12/month)
  - Yearly subscription option ($99/year - 31% savings)
  - Dedicated card in Settings with pricing
- **Email Verification Bonus**: 15,000 bonus credits for verified emails
  - Verification banner for unverified users
  - Verified badge next to email in license info
  - Resend verification email button
- **BYOAI Mode**: Pro BYOAI subscribers use their own Anthropic API key
  - Automatic detection of BYOAI subscription plans
  - Dedicated API key section in Settings for BYOAI users
  - Unlimited translations (no credit limits)
  - API mode indicator shows "BYOAI (Own API Key)" vs "Credits (Translio Proxy)"

### Changed
- **API Class Refactored**: Dual-mode translation system
  - Translio proxy (credits-based) for standard users
  - Direct Anthropic API for BYOAI users
  - Automatic mode selection based on subscription type
  - Credit balance refresh after proxy translations
- **Settings Page Enhanced**: New layout with credit packages and subscription options

---

## [2.1.3] - 2025-01-09

### Added
- **Auto-Update System**: New `Translio_Updater` class for automatic plugin updates
  - Checks api.translio.to for new versions every 12 hours
  - Displays update notifications in WordPress admin
  - Plugin info popup with changelog, banners, and icons
  - Seamless one-click updates from Plugins page
  - License key passed for authenticated update checks
- **Credit Packages API**: New `get_packages()` method in License class
  - Fetches available packages from api.translio.to/packages
  - 1-hour caching for performance
  - Returns package details: name, credits, price, validity, checkout URL

### Changed
- **License System Improvements**:
  - Added `get_credits()` method for real-time balance checking
  - Added `ajax_register_free` AJAX handler for free registration
  - Added `email_verified` status tracking
  - Plan names now include BYOAI variants
  - Credits cache cleared after successful translations

---

## [2.1.2] - 2025-01-09

### Fixed
- **PHP 8.1 Compatibility**: Fixed deprecation warnings for strpos/str_replace with null parameters
  - Added type coercion (string cast) to all filter functions that receive content from WordPress hooks
  - Fixed potential null values in: filter_title, filter_content, filter_excerpt, filter_seo_title, filter_seo_description, filter_widget_title, filter_widget_text, filter_post_meta, and 20+ other filters
  - Fixed class-translio-router.php URL handling functions
  - Fixed class-translio-api.php content chunking functions
  - Fixed class-translio-admin.php API key encryption/decryption
  - Fixed class-translio-utils.php meta translation helper

---

## [2.1.1] - 2025-01-09

### Changed
- **Plugin URI**: Updated to https://translio.to

---

## [1.2.5] - 2025-01-07

### Changed
- **Menu order**: Moved "Settings" to the end of the Translio menu for better UX

---

## [1.2.4] - 2025-01-07

### Improved
- **Media & Taxonomies pagination**: Standardized pagination to match WordPress admin style
  - Added pagination above and below tables (like All Content page)
  - Shows "X items" count, first/prev/next/last navigation buttons
  - Page number input field for direct navigation
  - Proper disabled states for edge pages
  - Taxonomies page now paginates 20 items per page (was 50 without pagination)

---

## [1.2.3] - 2025-01-07

### Added
- **Media list translation status**: Added "Translation Status" column to Media page
  - Shows overall status: Translated (green), Partial (yellow), Untranslated (red), No Content (gray)
  - Considers all fields: title, alt text (images), caption, description
  - Replaced individual Alt/Caption columns with single status badge
  - Consistent styling with All Content page

### Fixed
- **Media translate page save**: Attachment handler now returns response directly
  - Fixed issue where save showed error but data was saved

---

## [1.2.2] - 2025-01-07

### Fixed
- **Media translate page save error**: Added proper `attachment` object type handler in `save_translation` AJAX
  - Fixed "Error saving" when editing media translations (alt, title, caption, description)
  - Previously fell into generic `post` handler which saved with wrong object type
  - Now correctly maps attachment fields (alt from meta, title/caption/description from post)

---

## [1.2.1] - 2025-01-07

### Fixed
- **Media progress bar not showing**: Added missing `Translio.resetStop()` method that was causing JavaScript error and preventing progress bar from displaying

---

## [1.2.0] - 2025-01-07

### Added
- **Media bulk actions**: Checkboxes and bulk translation buttons for Media page
  - "Select All" checkbox in table header
  - "Translate Selected" button with count
  - "Translate All Untranslated (X)" button
  - Inline "Translate" button for each untranslated media item
  - Progress bar with Stop button for bulk operations
  - Real-time row highlighting during translation

---

## [1.1.9] - 2025-01-07

### Added
- **Scan Files feature for headless WordPress**: New buttons to scan theme and plugin PHP files for translatable strings
  - "Scan Active Theme" - scans current theme (and parent if child theme)
  - "Scan All Plugins" - scans all active plugins
  - Extracts strings from `__()`, `_e()`, `esc_html__()`, `esc_html_e()`, `esc_attr__()`, `esc_attr_e()` functions
  - Works without frontend, perfect for headless CMS setups
  - Automatically detects text domains from theme/plugin headers

---

## [1.1.8] - 2025-01-07

### Improved
- **Media list table**: Fixed thumbnail and title column overlap
  - Reduced thumbnail size to 80x80 for cleaner display
  - Added proper column widths and CSS styling
  - Removed redundant Description column (kept Caption)
  - Added cache invalidation in `save_translation()` for better reliability

---

## [1.1.7] - 2025-01-07

### Fixed
- **Term translation save error**: Fixed "Error saving" when editing term translations manually
  - Added missing `term` object type handler in `save_translation` AJAX function
  - Now properly saves name and description translations for taxonomy terms

---

## [1.1.6] - 2025-01-07

### Added
- **Taxonomies page improvements**:
  - Inline "Translate" button for each untranslated term (instant translation)
  - "Translate All Untranslated (X)" bulk action button
  - Progress bar with Stop button for bulk operations
  - Real-time row highlighting during translation
  - Renamed "Edit Translation" to shorter "Edit"

---

## [1.1.5] - 2025-01-07

### Changed
- **Unified progress bar across all sections**: All translation operations now use the same global progress bar with animation and Stop button
  - Removed old inline progress bar from All Content page
  - Updated "Translate Selected", "Translate All Untranslated", "Translate Changes" to use global progress
  - Added Stop button support to all bulk translation operations
  - Consistent UX across: All Content, Theme Strings, Taxonomies, Media, Contact Forms, Elementor, Divi, Avada

---

## [1.1.4] - 2025-01-07

### Added
- **Collapsible source panel**: Improved UX for translation editor
  - "Collapse/Expand" toggle button in source panel header
  - Collapsed mode: source column shrinks to 180px, translation expands to fill space
  - Hover over collapsed source to see full original text in overlay
  - Preference saved to localStorage and persists across sessions
  - All panels on page toggle together for consistency

---

## [1.1.3] - 2025-01-07

### Fixed
- **Progress bar animation not visible**: Added indeterminate wave animation on wrapper element that shows even at 0% progress
  - Wave animation runs on `.translio-progress-bar-wrapper` when translation starts
  - Stripe animation on `.translio-progress-bar-fill` shows as progress increases
  - Better visual feedback for users during long API calls

---

## [1.1.2] - 2025-01-07

### Added
- **Chunked translation for very long content**: Automatically splits content exceeding threshold into smaller chunks
  - Preserves Gutenberg block boundaries when splitting using WordPress `parse_blocks()` and `serialize_block()`
  - Falls back to paragraph-based splitting for classic editor content
  - Translates each chunk sequentially and reassembles
  - Handles content of any length without API token limits
- **Animated progress bar**: Visual feedback during translation process
  - Diagonal stripe animation using CSS keyframes
  - `animated` class added/removed via JavaScript during translation
  - Better UX for long-running translations

### Changed
- **Model-dependent configuration**: Different limits for Haiku vs Sonnet models
  - Haiku: 4096 max_tokens, 12000 char chunking threshold
  - Sonnet: 8192 max_tokens, 20000 char chunking threshold
  - Prevents "max_tokens exceeds limit" error on Haiku model
- **Improved HTML preservation in translations**: Enhanced system prompt
  - Explicit instructions to preserve HTML tags, attributes, WordPress blocks (`<!-- wp:... -->`), and shortcodes
  - Better handling of nested Gutenberg blocks with depth tracking
  - Prevents loss of formatting during translation
- Refactored `translate_text()` to use new `translate_text_direct()` internal method
- Added `get_max_content_length()` method for model-aware chunking threshold

### Fixed
- **Translation truncation**: Long content no longer cut off mid-sentence
- **Lost HTML formatting**: Translations now preserve original HTML structure

---

## [1.1.1] - 2025-01-07

### Fixed
- **Long content truncation**: Increased `max_tokens` from 4096 to 8192 to prevent translation output from being cut off on long posts/pages

---

## [1.1.0] - 2025-01-07

### Added
- **REST API for Headless CMS**: New `Translio_REST_API` class provides endpoints for external applications
  - `GET /wp-json/translio/v1/page/{slug}?lang=ru` - Get translated page
  - `GET /wp-json/translio/v1/post/{slug}?lang=ru` - Get translated post
  - `GET /wp-json/translio/v1/posts?lang=ru&per_page=10&page=1` - Get translated posts list
  - `GET /wp-json/translio/v1/languages` - Get available languages
  - Batch translation loading for improved performance
  - Compatible with Next.js, Gatsby, Nuxt, and other headless frameworks
  - Includes author, featured image, categories, and tags in response

---

## [1.0.42] - 2024-12-28

### Fixed
- **Rewrite rules not registered on activation**: Fixed issue where language prefix URLs (e.g., `/de/page-slug/`) would return 404 after plugin activation. The `flush_rewrite_rules()` was called during activation before the Router had a chance to register its rules on `init` hook. Now uses transient to defer the flush until after Router adds rules.

---

## [1.0.41] - 2024-12-20

### Added
- **Avada/Fusion Builder Integration**: Full support for Avada theme page builder
  - New `Translio_Avada` class with shortcode parsing for 50+ Fusion elements
  - Admin pages: Avada pages list and translation editor
  - AJAX handlers for save, translate single field, translate all
  - JavaScript module `TranslioTranslateAvada`
  - Frontend filter for displaying translations
  - Supported elements:
    - Text, Title, Button, Alert
    - Toggle/Accordion, Tabs
    - Slider, Content Box, Tagline Box
    - Testimonials, Person/Team Member
    - Flip Boxes, Pricing Table
    - Modal, Popover, Tooltip
    - Progress Bar, Counters (circle, box)
    - Forms (text, textarea, email, phone, select, checkbox, radio)
    - Image alt, Gallery, Map, Search
    - Checklist, Highlight, Dropcap
    - And many more...

---

## [1.0.40] - 2024-12-19

### Added
- **Divi Builder Integration**: Full support for Divi/Elegant Themes page builder
  - New `Translio_Divi` class with shortcode parsing for 40+ Divi modules
  - Admin pages: Divi pages list and translation editor
  - AJAX handlers for save, translate single field, translate all
  - JavaScript module `TranslioTranslateDivi`
  - Frontend filter for displaying translations
  - Supported modules:
    - Text, Blurb, CTA, Button
    - Accordion, Toggle, Tabs
    - Slider, Fullwidth Slider
    - Testimonial, Pricing Table
    - Contact Form, Email Optin
    - Image, Gallery, Video
    - And many more...

### Changed
- Custom WordPress-styled modal dialogs replace browser confirm/alert
- Improved error handling in translation AJAX responses

---

## [1.0.39] - 2024-12-19

### Fixed
- Dashboard now correctly detects edited posts needing re-translation using MD5 hash comparison
- API error messages now properly returned to frontend instead of silent failures

---

## [1.0.38] - 2024-12-19

### Changed
- Default API model changed to `claude-3-haiku-20240307` for cost-effective testing

---

## [1.0.29-1.0.37] - 2024-12-19

### Added
- WP Lingua REST API integration for headless CMS
- TranslatePress REST API integration
- Newsletter form field detection fixes
- Various bug fixes and improvements

---

## [1.0.28] - 2024-12-19

### Added
- **Frontend Language Switcher**: Complete language switching UI for non-headless WordPress sites
  - WordPress Widget: "Translio Language Switcher" widget for sidebars
  - Shortcode: `[translio_switcher]` for arbitrary placement with parameters:
    - `style="dropdown|inline|flags"` - display style
    - `show_flags="yes|no"` - show country flags
    - `show_names="yes|no"` - show language names
  - WP Nav Menu integration: automatically add languages to navigation menu
- **Language Switcher Settings** in Settings page:
  - Enable/disable menu integration
  - Select menu location (primary, footer, etc.)
  - Choose menu style (dropdown or inline)
  - Toggle flags and language names display
- **CSS Styles**: Responsive design with mobile support
  - Dropdown with smooth animations
  - Inline list with separators
  - Flags-only compact mode
  - Dark theme support (prefers-color-scheme)
  - RTL support for Arabic/Hebrew
  - SVG flag icons for 20 languages
- **JavaScript**: Dropdown toggle with keyboard (Escape) and outside-click close

---

## [1.0.27] - 2024-12-18

### Added
- **Freemius SDK**: Installed official Freemius WordPress SDK
- Configured with Plugin ID: 22441
- Added Account, Pricing, Contact menu items
- 7-day free trial enabled

---

## [1.0.26] - 2024-12-18

### Added
- **Freemius SDK Integration**: Prepared structure for subscription management
  - New `Translio_License` class for license and quota management
  - `translio_license()` helper function for easy access
  - Plan definitions: Free (20K), Starter (500K), Business (2M), Agency (10M) tokens/month
- **Subscription Card in Settings**: Visual subscription status widget
  - Shows current plan with colored badge
  - Usage progress bar (green/yellow/red based on usage)
  - Tokens used vs quota display
  - Upgrade button for free users
  - Warning when quota exceeded
- **Token Usage Tracking**: Automatic tracking of API token consumption
  - Tracks input + output tokens from Anthropic API responses
  - Monthly usage stored in wp_options (auto-cleanup of old months)
  - Quota check before API requests (blocks translation if exceeded)

### Changed
- API requests now check quota before translating
- Settings page reorganized with subscription card at top

---

## [1.0.25] - 2024-12-18

### Added
- **License Domain Field**: New required field in Settings for website domain
  - Domain is sanitized (removes protocol, trailing slashes, paths)
  - Shows warning notice on all Translio pages if not configured
  - Preparation for future subscription-based licensing system
- Added Translio logo to Settings page header

---

## [1.0.24] - 2024-12-18

### Added
- **WC Attributes Bulk Actions**: WooCommerce Attributes page now has full bulk translation support
  - Checkboxes for selecting individual attributes
  - "Select All" checkbox in table header
  - "Translate Selected" button with selected count
  - "Translate All Untranslated" button with count of pending items
  - Progress bar with Stop support during bulk translation
  - New `save_wc_attribute` AJAX handler

### Changed
- Project moved to dedicated development folder: `/Users/sergeynesmachny/Software Development/Translio`

---

## [1.0.23] - 2024-12-18

### Added
- **Taxonomies Progress Bar**: Added progress bar with stop support to Taxonomies page
  - "Translate All Untranslated" now shows real-time progress
  - Sequential translation with progress updates
  - Stop button support to abort mid-translation

---

## [1.0.22] - 2024-12-18

### Added
- **Stop Translation Button**: Progress bar now includes "Stop Translation" button to abort ongoing translations
  - Aborts current AJAX request immediately
  - Preserves already completed translations
  - Shows "Translation stopped" status message
  - Red styled button with hover state

### Changed
- Progress bar layout updated with flex footer containing message and stop button

---

## [1.0.21] - 2024-12-18

### Changed
- **Logo Size**: Increased logo size by 50% for better readability (28px → 42px)

---

## [1.0.20] - 2024-12-18

### Added
- **Branding**: Added Translio logo to all admin page headers
  - Logo stored in `admin/images/logo.png`
  - Replaces "Translio -" text prefix on all pages
  - CSS styling for consistent logo display

---

## [1.0.19] - 2024-12-18

### Fixed
- **Progress Bar Animation**: "Auto-translate Visible" now shows animated progress while waiting for API response
  - Fake progress increments every 2 seconds during API call
  - 5-minute timeout for large batches
  - Better error handling for timeout and abort cases
  - Alert when no translations returned

---

## [1.0.18] - 2024-12-18

### Fixed
- **Missing Handler**: Added JavaScript handler for "Auto-translate Visible" button (`#translio-translate-all-strings`) in Theme Strings page - button was non-functional

### Changed
- "Auto-translate All Untranslated" button now uses progress bar instead of just loader

---

## [1.0.17] - 2024-12-18

### Added
- **Progress Bar Integration**: Added global progress bar to all "Auto-translate all fields" buttons:
  - CF7 form translation page
  - Media translation page
  - Elementor translation page
- Animated field filling with progress updates after API response

---

## [1.0.16] - 2024-12-18

### Fixed
- **JavaScript Selectors**: Fixed mismatched button IDs for "Auto-translate all fields":
  - CF7: `#translio-translate-all-cf7` → `#translio-translate-cf7-all`
  - Elementor: `#translate-all-elementor` → `#translio-translate-elementor-all`

---

## [1.0.15] - 2024-12-18

### Fixed
- **Navigation Links**: Fixed "Translate" button links pointing to wrong pages:
  - CF7: `translio-cf7&action=translate` → `translio-translate-cf7`
  - Elementor: `translio-elementor&action=translate` → `translio-translate-elementor`
  - Media: `translio-media&action=translate` → `translio-translate-media`
  - Taxonomies: `translio-taxonomies&action=translate` → `translio-translate-term`

---

## [1.0.14] - 2024-12-18

### Added
- **UI Consistency**: Added language indicator block (EN → DE) to all list pages:
  - All Content page
  - Theme Strings page
  - Taxonomies page
  - Media page
  - Contact Form 7 page
  - Elementor page
- Now matches WC Attributes page style

---

## [1.0.13] - 2024-12-18

### Changed
- **JavaScript Consolidation**: Moved all inline `<script>` blocks from PHP modules to centralized `admin-script.js`
  - Removed ~1000 lines of inline JavaScript from 11 PHP files
  - Added page-specific modules: TranslioContent, TranslioStrings, TranslioTaxonomies, etc.
  - Added TranslioUtils for common patterns (checkboxes, sequential translation)
  - Page detection and routing via `Translio.initPage()`
- Added localization strings to `wp_localize_script()` for all UI messages

---

## [1.0.12] - 2024-12-18

### Changed
- **Major Refactoring**: Split monolithic `class-translio-admin.php` (6454 lines) into 12 modular files:

#### New Module Structure (`includes/admin/`):
| File | Lines | Responsibility |
|------|-------|----------------|
| `class-translio-admin.php` | 378 | Main class, menu registration, script loading |
| `class-translio-admin-ajax.php` | 620 | All 25+ AJAX handlers |
| `class-translio-admin-dashboard.php` | 180 | Dashboard page with statistics |
| `class-translio-admin-settings.php` | 200 | Settings page, API key management |
| `class-translio-admin-content.php` | 400 | All Content list, Translate Post editor |
| `class-translio-admin-strings.php` | 350 | Theme Strings scanner and editor |
| `class-translio-admin-taxonomies.php` | 320 | Taxonomies list, Translate Term editor |
| `class-translio-admin-media.php` | 380 | Media list, Translate Attachment editor |
| `class-translio-admin-options.php` | 300 | Site Options (blogname, widgets) |
| `class-translio-admin-wc.php` | 350 | WooCommerce Attributes translation |
| `class-translio-admin-elementor.php` | 400 | Elementor pages list and editor |
| `class-translio-admin-cf7.php` | 450 | Contact Form 7 forms list and editor |

#### Benefits:
- Single Responsibility Principle - each module handles one feature
- Easier maintenance and debugging
- Reduced memory footprint (only needed modules loaded conceptually)
- Clear separation of concerns
- Testable units

---

## [1.0.11] - 2024-12-17

### Fixed
- **Infinite Recursion Bug**: Fixed PHP fatal error (memory exhaustion) when saving posts - `self::$instance` now set at START of constructor to prevent recursion when child classes call `translio()` during initialization

## [1.0.10] - 2024-12-17

### Changed
- **Centralized Settings Usage**: Replaced 40+ direct `get_option()` calls with `translio()->get_setting()` across all classes
- Settings now cached in memory - single DB query per request instead of 40+
- Affected files: class-translio-admin.php, class-translio-api.php, class-translio-router.php, class-translio-content.php, class-translio-list-table.php

## [1.0.9] - 2024-12-17

### Changed
- **Inline Styles → CSS Classes**: Replaced 79 inline styles with reusable CSS utility classes
- New utility classes: `.translio-icon`, `.translio-text-muted`, `.translio-flex-1`, `.translio-actions-bar`, `.translio-filter-form`, `.translio-section-title`, `.translio-col-*`, margin/width utilities
- Improved maintainability and consistency of admin UI styling

## [1.0.8] - 2024-12-17

### Changed
- **Translio_API Singleton**: API class now uses singleton pattern - `Translio_API::instance()` instead of `new Translio_API()` (16 instantiations reduced to 1)
- **Centralized Settings**: New `translio()->get_settings()` and `translio()->get_setting($key)` methods with caching - cleaner architecture for settings access
- Updated all API instantiations across codebase

## [1.0.7] - 2024-12-17

### Changed
- **Unified Progress UI**: Replaced fullscreen overlay loader with inline progress bar across all pages - consistent UX matching the All Content page style
- Removed overlay CSS and JavaScript code
- New `showProgress()`, `updateProgress()`, `hideProgress()` methods with legacy `showLoader()`/`hideLoader()` compatibility

## [1.0.6] - 2024-12-17

### Fixed
- **Widget Translation Error**: Fixed "Post not found" error when translating widgets on Site Options page - `ajax_translate_single()` now correctly handles widget object type by using POST content instead of looking up non-existent post ID

## [1.0.5] - 2024-12-17

### Improved
- **Dashboard UI**: Hide "Pending" counter when value is 0 - cleaner interface for fully translated content types

## [1.0.4] - 2024-12-17

### Fixed
- **Block Theme Types Draft Status**: Dashboard and `count_fully_translated_posts()` now include draft status for `wp_navigation`, `wp_template_part`, and `wp_block` post types - Navigation Menus now show correct translation status

## [1.0.3] - 2024-12-17

### Fixed
- **Dashboard Stats Sync**: Removed meta fields from `count_fully_translated_posts()` to match list-table logic - Dashboard and Content List now show consistent translation status

## [1.0.2] - 2024-12-17

### Fixed
- **Batch Translation Bug**: Fixed `translate_batch()` not accepting simple associative array format `['field' => 'text']`, causing bulk translations to silently fail
- **JSON Parse Fallback**: Added automatic fallback to individual translation when batch JSON response fails to parse (e.g., complex Gutenberg content)
- **Translation Status Count**: Removed meta fields from status calculation in content list - now counts only title/content/excerpt for accurate "Translated" vs "Partial" status

## [1.0.1] - 2024-12-15

### Added
- Migration system from WP Lingua to Translio
- API key encryption fix for `sk-ant-` prefix

### Fixed
- `array_filter` TypeError in `translate_batch()`
- JS variable references (`translio.` → `translioAdmin.`)

## [1.0.0] - 2024-12-15

### Changed
- Plugin renamed from WP Lingua to Translio
- All classes, constants, options, and DB tables renamed

## [0.9.21] - 2024-12-15

### Fixed
- **Recursion Guards**: Replaced `remove_filter`/`add_filter` pattern with static boolean guards to prevent potential infinite loops in meta filters (`filter_post_meta_alt`, `filter_attachment_alt_meta`)

### Added
- **Theme Strings Translation**: New output buffer-based approach for translating theme strings in HTML patterns (headings, titles, attributes) without slow gettext filters

### Changed
- Improved stability of meta field translation filters

## [0.9.20] - 2024-12-15

### Added
- **Post Preloading**: New `preload_post_translations()` method that batch-loads post, featured image, and gallery translations on `the_post` hook
- **Elementor Preloading**: New `Translio_Elementor::preload_translations()` for batch preloading widget translations

### Improved
- **TM Fuzzy Matching Performance**:
  - Reduced candidate limit from 100 to 30
  - Added keyword-based pre-filtering
  - Sort candidates by length proximity
  - Early exit on 95%+ similarity match
  - Results cached per request via `$tm_fuzzy_cache`

## [0.9.19] - 2024-12-15

### Added
- **In-Memory Cache**: Translation cache in `Translio_DB` to avoid repeated database queries within the same request
- **Batch Loading Methods**:
  - `Translio_DB::get_translations_batch()` - Load multiple translations in single query
  - `Translio_DB::preload_menu_translations()` - Preload all menu item translations
- **Cache Management**:
  - `Translio_DB::clear_cache()` - Clear translation cache
  - `Translio_DB::clear_tm_cache()` - Clear TM fuzzy match cache

### Fixed
- **N+1 Query Problem**: Menu items now use batch preloading (50 items = 1 query instead of 50)

## [0.9.18] - 2024-12-14

### Security
- **XSS Fix**: Added `wp_kses_post()` sanitization to `str_replace()` output in content filters
- **SHA256 Migration**: Replaced all CRC32 hash functions with SHA256 via `Translio_Utils::generate_hash_id()` to eliminate collision risk:
  - Widget titles: `generate_hash_id('widget', $title)`
  - Widget content: `generate_hash_id('widget_text', md5($content))`
  - Navigation labels: `generate_hash_id('nav_link', $label)`
  - Gettext strings: `generate_hash_id('gettext', $text, $domain)`
- **Server-Side Hash**: String ID calculation moved from client-side JavaScript to server-side PHP in AJAX handlers

## [0.9.17] - 2024-12-14

### Added
- **Translio_Logger**: Centralized logging system with categories (translation, api, content, router, elementor, cf7, db) and levels (debug, info, warning, error)
- **Translio_Translatable Interface**: Abstract interface for content types with standard methods (get_object_type, extract_fields, get_translation, save_translation, etc.)
- **SHA256 for Navigation**: Navigation link labels now use SHA256 hashing

### Changed
- Logging can be enabled via `WP_LINGUA_DEBUG` constant or `translio_debug` option

## [0.9.16] - 2024-12-13

### Fixed
- **Elementor Element IDs**: Corrected element ID extraction for nested Elementor widgets
- **Permission Checks**: Added proper capability checks for site options translation

## [0.9.15] - 2024-12-13

### Added
- **Translio_Utils Class**: Centralized utility methods:
  - `should_skip_meta_translation()` - Consistent meta field filtering
  - `extract_post_fields()` - Post field extraction
  - `extract_post_meta_fields()` - Meta field extraction with smart filtering
  - `get_translation_context()` - Context strings for API translation

### Fixed
- **Contact Form 7**: Improved context for CF7 form fields (form template, mail template, messages)
- **Batch API Context**: Context now properly passed in batch translation calls

## [0.8.0] - 2024-12-10

### Performance
- **Disabled Gettext Filters**: Removed slow `gettext` filter hooks that caused performance issues
- Added alternative approach for theme string translation via database scanning

## [0.7.0] - 2024-12-08

### Added
- **WooCommerce Support**: Translation of product attributes, attribute labels
- **Media Translation**: Alt text, captions, titles for attachments
- **Block Editor Support**: `render_block` filter for translating block content

## [0.6.0] - 2024-12-05

### Added
- **Translation Memory (TM)**:
  - Exact match lookup via `Translio_DB::tm_find_exact()`
  - Fuzzy match with configurable similarity threshold via `Translio_DB::tm_find_fuzzy()`
  - Automatic TM lookup before API calls via `Translio_DB::tm_get_translation()`

### Changed
- API translation now checks TM before making external calls

## [0.5.0] - 2024-12-03

### Added
- **Batch Translation**: Multiple texts translated in single API call via `Translio_API::translate_batch()`
- **Retry Logic**: Exponential backoff with jitter for API failures (max 5 retries)
- **Retryable Errors**: HTTP codes 429, 500, 502, 503, 529

### Changed
- "Translate All" now uses batch processing for efficiency

## [0.4.0] - 2024-12-01

### Added
- **Elementor Integration**: Full support for Elementor page builder
  - Extract translatable strings from widgets
  - Translate text, headings, buttons, icons, etc.
  - Handle nested sections and columns

## [0.3.0] - 2024-11-28

### Added
- **Contact Form 7 Integration**: Translate form fields, mail templates, messages
- **SEO Plugins Support**: Yoast SEO, Rank Math, All in One SEO meta titles and descriptions

## [0.2.0] - 2024-11-25

### Added
- **Navigation Menus**: Translate menu item titles and custom links
- **Widgets**: Translate widget titles and text content
- **Taxonomy Terms**: Translate category/tag names and descriptions

## [0.1.0] - 2024-11-20

### Added
- Initial release
- **Core Translation**: Posts, pages, custom post types (title, content, excerpt)
- **URL Routing**: Language prefix routing (`/{lang}/original-slug/`)
- **Admin Interface**: 2-panel translation editor with autosave
- **Anthropic API**: Integration with Claude claude-sonnet-4-20250514 model
- **Language Management**: Support for top 20 world languages
- **Database Schema**: Custom tables for translations and languages

---

## Upgrade Notes

### Upgrading to 0.9.18+
- All hash-based IDs are automatically regenerated using SHA256
- No data migration required - translations are matched by original content

### Upgrading to 0.9.19+
- In-memory cache is automatic, no configuration needed
- Significant performance improvement for pages with many translations

### Upgrading to 0.9.20+
- TM fuzzy matching is faster but may return slightly different results
- Batch preloading is automatic for posts and Elementor content
