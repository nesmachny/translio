<?php
/**
 * Translio Admin Strings
 *
 * Theme & Plugin Strings page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Strings {

    /**
     * Render Theme Strings page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $secondary_language = Translio_Admin::get_admin_language();
        $default_language = translio()->get_setting('default_language');
        $languages = Translio::get_available_languages();
        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();
        $scan_enabled = get_option('translio_scan_strings', true);

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Theme Strings', 'translio'); ?></h1>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('Please configure your translation language in', 'translio'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=translio-settings')); ?>">
                            <?php esc_html_e('Settings', 'translio'); ?>
                        </a>.
                    </p>
                </div>
            </div>
            <?php
            return;
        }

        // Get filters
        $filter_domain = isset($_GET['domain']) ? sanitize_text_field($_GET['domain']) : '';
        $filter_translated = isset($_GET['translated']) ? sanitize_text_field($_GET['translated']) : '';
        $filter_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;

        $scanned_strings = Translio_DB::get_scanned_strings(array(
            'domain' => $filter_domain,
            'search' => $filter_search,
            'translated' => $filter_translated,
            'language_code' => $secondary_language,
            'limit' => $per_page,
            'offset' => ($paged - 1) * $per_page,
        ));

        $total_strings = Translio_DB::get_scanned_strings_count(array(
            'domain' => $filter_domain,
            'search' => $filter_search,
            'translated' => $filter_translated,
            'language_code' => $secondary_language,
        ));

        $total_pages = ceil($total_strings / $per_page);
        $domains = Translio_DB::get_scanned_domains();

        // Stats
        $total_all = Translio_DB::get_scanned_strings_count(array('language_code' => $secondary_language));
        $total_translated = Translio_DB::get_scanned_strings_count(array('language_code' => $secondary_language, 'translated' => 'yes'));
        $total_untranslated = $total_all - $total_translated;

        ?>
        <div class="wrap translio-strings">
            <h1><?php esc_html_e('Theme & Plugin Strings', 'translio'); ?></h1>

            <?php Translio_Admin::render_language_selector('translio-strings'); ?>

            <div class="translio-translate-header">
                <div class="translio-lang-indicator">
                    <span class="translio-lang-badge">
                        <?php echo esc_html(strtoupper($default_language)); ?>
                    </span>
                    →
                    <span class="translio-lang-badge translio-lang-secondary">
                        <?php echo esc_html(strtoupper($secondary_language)); ?>
                    </span>
                </div>
            </div>

            <!-- Scan Files Section -->
            <div class="translio-scan-section" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #2271b1;">
                <strong><?php esc_html_e('Scan Theme & Plugin Files', 'translio'); ?></strong><br>
                <p style="margin: 8px 0;">
                    <?php esc_html_e('Scan PHP files to find translatable strings. Works with headless WordPress setups.', 'translio'); ?>
                </p>
                <button type="button" class="button button-primary" id="translio-scan-theme-files">
                    <span class="dashicons dashicons-search" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Scan Active Theme', 'translio'); ?>
                </button>
                <button type="button" class="button" id="translio-scan-plugin-files">
                    <span class="dashicons dashicons-admin-plugins" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Scan All Plugins', 'translio'); ?>
                </button>
                <span class="translio-scan-status" style="margin-left: 10px;"></span>
            </div>

            <?php
            $has_filters = !empty($filter_domain) || !empty($filter_search) || !empty($filter_translated);
            if ($total_all === 0 && !$has_filters):
            ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('No strings found yet', 'translio'); ?></strong><br>
                    <?php esc_html_e('Use "Scan Active Theme" or "Scan All Plugins" buttons above to find translatable strings from PHP files.', 'translio'); ?>
                    <br><br>
                    <?php esc_html_e('Or enable auto-scanning to collect strings when visitors browse your site:', 'translio'); ?>
                    <br><br>
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="button"><?php esc_html_e('Visit Homepage', 'translio'); ?></a>
                </p>
            </div>
            <?php else: ?>

            <!-- Stats -->
            <div class="translio-strings-stats" style="margin: 15px 0; padding: 10px 15px; background: #fff; border: 1px solid #ccd0d4;">
                <strong><?php esc_html_e('Statistics:', 'translio'); ?></strong>
                <?php printf(
                    __('%d total strings | %d translated | %d untranslated', 'translio'),
                    $total_all, $total_translated, $total_untranslated
                ); ?>
            </div>

            <!-- Filters -->
            <form method="get" class="translio-filters" class="translio-my-15">
                <input type="hidden" name="page" value="translio-strings">

                <select name="domain">
                    <option value=""><?php esc_html_e('All Domains', 'translio'); ?></option>
                    <?php foreach ($domains as $domain): ?>
                    <option value="<?php echo esc_attr($domain); ?>" <?php selected($filter_domain, $domain); ?>><?php echo esc_html($domain); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="translated">
                    <option value=""><?php esc_html_e('All Strings', 'translio'); ?></option>
                    <option value="yes" <?php selected($filter_translated, 'yes'); ?>><?php esc_html_e('Translated', 'translio'); ?></option>
                    <option value="no" <?php selected($filter_translated, 'no'); ?>><?php esc_html_e('Not Translated', 'translio'); ?></option>
                </select>

                <input type="search" name="s" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php esc_attr_e('Search strings...', 'translio'); ?>">

                <button type="submit" class="button"><?php esc_html_e('Filter', 'translio'); ?></button>

                <?php if ($filter_domain || $filter_translated || $filter_search): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-strings')); ?>" class="button"><?php esc_html_e('Clear', 'translio'); ?></a>
                <?php endif; ?>
            </form>

            <!-- Actions -->
            <div class="translio-strings-actions" class="translio-my-15">
                <?php if ($can_translate) : ?>
                <button type="button" class="button button-primary" id="translio-translate-all-strings">
                    <?php esc_html_e('Auto-translate Visible', 'translio'); ?>
                </button>
                <button type="button" class="button" id="translio-translate-untranslated">
                    <?php printf(__('Auto-translate All Untranslated (%d)', 'translio'), $total_untranslated); ?>
                </button>
                <?php endif; ?>
                <button type="button" class="button" id="translio-clear-strings" style="color: #a00;">
                    <?php esc_html_e('Clear All Strings', 'translio'); ?>
                </button>
                <span class="translio-strings-status"></span>
            </div>

            <!-- Actions with Translate Selected -->
            <div class="translio-bulk-actions" class="translio-my-15">
                <button type="button" class="button button-primary" id="translio-translate-selected-strings" disabled>
                    <span class="dashicons dashicons-yes translio-icon"></span>
                    <?php esc_html_e('Translate Selected', 'translio'); ?>
                    <span id="translio-selected-strings-count">(0)</span>
                </button>
                <span class="translio-bulk-status" class="translio-ml-10"></span>
            </div>

            <!-- Table -->
            <table class="wp-list-table widefat fixed striped translio-strings-table">
                <thead>
                    <tr>
                        <th class="translio-col-checkbox"><input type="checkbox" id="translio-select-all-strings"></th>
                        <th style="width: 38%;"><?php echo esc_html($languages[$default_language]['name'] ?? 'Original'); ?></th>
                        <th style="width: 32%;"><?php echo esc_html($languages[$secondary_language]['name'] ?? $secondary_language); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Domain', 'translio'); ?></th>
                        <th style="width: 10%;"><?php esc_html_e('Actions', 'translio'); ?></th>
                    </tr>
                </thead>
                <tbody id="translio-strings-body">
                    <?php if (empty($scanned_strings)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">
                            <?php if ($filter_search): ?>
                                <?php printf(__('No strings found matching "%s"', 'translio'), esc_html($filter_search)); ?>
                            <?php else: ?>
                                <?php esc_html_e('No strings found with current filters.', 'translio'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($scanned_strings as $string):
                        $object_id = $string->object_id;
                        $translated_value = $string->translated_content ?: '';
                    ?>
                    <tr data-string-id="<?php echo esc_attr($object_id); ?>" data-original="<?php echo esc_attr($string->string_text); ?>" data-domain="<?php echo esc_attr($string->domain); ?>" data-type="string">
                        <td><input type="checkbox" class="translio-string-checkbox" value="<?php echo esc_attr($object_id); ?>"></td>
                        <td class="translio-string-original">
                            <?php echo esc_html($string->string_text); ?>
                            <?php if (!empty($string->context)): ?>
                            <br><small class="translio-text-muted">Context: <?php echo esc_html($string->context); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="translio-string-translation">
                            <input type="text" class="translio-string-input"
                                   value="<?php echo esc_attr($translated_value); ?>"
                                   placeholder="<?php esc_attr_e('Enter translation...', 'translio'); ?>">
                        </td>
                        <td class="translio-string-domain"><?php echo esc_html($string->domain); ?></td>
                        <td class="translio-string-actions">
                            <button type="button" class="button button-small translio-save-string"><?php esc_html_e('Save', 'translio'); ?></button>
                            <?php if ($can_translate) : ?>
                            <button type="button" class="button button-small translio-translate-string"><?php esc_html_e('Auto', 'translio'); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf(__('%d items', 'translio'), $total_strings); ?></span>
                    <span class="pagination-links">
                        <?php
                        $base_url = add_query_arg(array(
                            'page' => 'translio-strings',
                            'domain' => $filter_domain,
                            'translated' => $filter_translated,
                            's' => $filter_search,
                        ), admin_url('admin.php'));

                        if ($paged > 1): ?>
                        <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $base_url)); ?>">‹</a>
                        <?php else: ?>
                        <span class="tablenav-pages-navspan button disabled">‹</span>
                        <?php endif; ?>

                        <span class="paging-input">
                            <?php echo $paged; ?> / <?php echo $total_pages; ?>
                        </span>

                        <?php if ($paged < $total_pages): ?>
                        <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $base_url)); ?>">›</a>
                        <?php else: ?>
                        <span class="tablenav-pages-navspan button disabled">›</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>

            <!-- Add custom string form -->
            <h3 class="translio-section-title"><?php esc_html_e('Add Custom String', 'translio'); ?></h3>
            <p class="description"><?php esc_html_e('Manually add strings that were not automatically detected.', 'translio'); ?></p>

            <table class="form-table">
                <tr>
                    <th><label for="translio-new-string"><?php esc_html_e('Original String', 'translio'); ?></label></th>
                    <td>
                        <input type="text" id="translio-new-string" class="regular-text" placeholder="<?php esc_attr_e('Enter original text...', 'translio'); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="translio-new-translation"><?php esc_html_e('Translation', 'translio'); ?></label></th>
                    <td>
                        <input type="text" id="translio-new-translation" class="regular-text" placeholder="<?php esc_attr_e('Enter translation...', 'translio'); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="translio-new-domain"><?php esc_html_e('Domain', 'translio'); ?></label></th>
                    <td>
                        <select id="translio-new-domain">
                            <option value="default">default (WordPress)</option>
                            <?php foreach ($domains as $domain): ?>
                            <option value="<?php echo esc_attr($domain); ?>"><?php echo esc_html($domain); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <button type="button" class="button button-primary" id="translio-add-string"><?php esc_html_e('Add String', 'translio'); ?></button>
        </div>

        <style>
            .translio-strings-table input.translio-string-input {
                width: 100%;
            }
            .translio-strings-actions {
                margin: 15px 0;
            }
            .translio-strings-status {
                margin-left: 10px;
            }
            .translio-string-saved {
                background-color: #d4edda !important;
            }
        </style>
        <?php
    }

    /**
     * Get theme strings that need translation
     */
    private function get_theme_strings() {
        $strings = array();

        $footer_labels = array(
            'About', 'Team', 'History', 'Careers',
            'Privacy', 'Privacy Policy', 'Terms and Conditions', 'Contact Us',
            'Social', 'Facebook', 'Instagram', 'Twitter/X',
            'Blog', 'FAQs', 'Authors', 'Events', 'Shop', 'Patterns', 'Themes',
        );

        foreach ($footer_labels as $str) {
            $strings[] = array('text' => $str, 'domain' => 'footer', 'type' => 'nav_link');
        }

        $theme_strings_25 = array(
            'Twenty Twenty-Five', 'Designed with %s',
        );

        foreach ($theme_strings_25 as $str) {
            $strings[] = array('text' => $str, 'domain' => 'twentytwentyfive');
        }

        $theme_strings_24 = array(
            'A commitment to innovation and sustainability',
            'A passion for creating spaces',
            'Renovation and restoration', 'Continuous Support', 'App Access',
            'Consulting', 'Project Management', 'Architectural Solutions',
            'An array of resources', 'Études Architect App', 'Études Newsletter',
            'Watch, Read, Listen',
            'Join 900+ subscribers', 'Stay in the loop with everything you need to know.',
            'About', 'Privacy', 'Social',
            'Built on a rich history of innovation', 'Études is a pioneering architecture studio',
            'Our services', 'Our work', 'Our story', 'Contact', 'Get in touch',
            'Learn more', 'View project', 'See all projects', 'Subscribe', 'Sign up',
        );

        foreach ($theme_strings_24 as $str) {
            $strings[] = array('text' => $str, 'domain' => 'twentytwentyfour');
        }

        $wc_strings = array(
            'Description', 'Additional information', 'Reviews', 'Category:', 'Categories:',
            'Tag:', 'Tags:', 'SKU:', 'Add to cart', 'Buy now', 'Out of stock',
            'In stock', 'Read more', 'Sale!', 'Related products', 'You may also like',
            'Product', 'Products', 'Price', 'Quantity', 'Total', 'Subtotal',
            'Cart', 'Checkout', 'My account', 'Order', 'Orders',
            'Shipping', 'Billing', 'Payment', 'Coupon', 'Apply coupon',
            'Update cart', 'Proceed to checkout', 'Return to shop',
            'Home', 'Uncategorized',
        );

        foreach ($wc_strings as $str) {
            $strings[] = array('text' => $str, 'domain' => 'woocommerce');
        }

        $wp_strings = array(
            'Search', 'Search Results', 'Nothing Found', 'Read More',
            'Previous', 'Next', 'Page', 'Pages', 'Comment', 'Comments',
            'Leave a Reply', 'Post Comment', 'Name', 'Email', 'Website',
        );

        foreach ($wp_strings as $str) {
            $strings[] = array('text' => $str, 'domain' => 'default');
        }

        return $strings;
    }
}
