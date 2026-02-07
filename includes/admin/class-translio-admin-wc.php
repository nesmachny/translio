<?php
/**
 * Translio Admin WooCommerce
 *
 * WooCommerce attributes translation page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_WC {

    /**
     * Render WooCommerce page
     */
    public function render_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (!class_exists('WooCommerce')) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('WooCommerce', 'translio'); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e('WooCommerce is not installed or activated.', 'translio'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        $secondary_language = Translio_Admin::get_admin_language();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('WooCommerce', 'translio'); ?></h1>
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

        $default_language = translio()->get_setting('default_language');
        $languages = Translio::get_available_languages();

        // Get WooCommerce product attributes
        global $wpdb;
        $attributes = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies ORDER BY attribute_name"
        );

        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();

        ?>
        <div class="wrap translio-translate">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('WooCommerce Attributes', 'translio'); ?></h1>

            <?php Translio_Admin::render_language_selector('translio-wc'); ?>

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

            <?php if (empty($attributes)) : ?>
                <div class="notice notice-info">
                    <p>
                        <?php esc_html_e('No product attributes found.', 'translio'); ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=product_attributes')); ?>">
                            <?php esc_html_e('Create attributes', 'translio'); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <?php
                // Count untranslated
                $total_untranslated = 0;
                foreach ($attributes as $attr) {
                    $trans = Translio_DB::get_translation($attr->attribute_id, 'wc_attribute', 'label', $secondary_language);
                    if (empty($trans) || empty($trans->translated_content)) {
                        $total_untranslated++;
                    }
                }
                ?>

                <p class="description">
                    <?php esc_html_e('Translate WooCommerce product attribute labels. Attribute values (terms) should be translated in the Taxonomies section.', 'translio'); ?>
                </p>

                <?php if ($can_translate) : ?>
                <div class="translio-bulk-actions" style="margin: 15px 0; display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="button" id="translio-wc-translate-selected" disabled>
                        <span class="dashicons dashicons-yes" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Translate Selected', 'translio'); ?>
                        <span id="translio-wc-selected-count">(0)</span>
                    </button>
                    <button type="button" class="button button-primary" id="translio-wc-translate-all">
                        <span class="dashicons dashicons-translation" style="margin-top: 3px;"></span>
                        <?php printf(__('Translate All Untranslated (%d)', 'translio'), $total_untranslated); ?>
                    </button>
                    <span class="translio-wc-status"></span>
                </div>
                <?php endif; ?>

                <div class="translio-editor" style="margin-top: 20px;">
                    <table class="wp-list-table widefat fixed striped" id="translio-wc-attrs-table">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column" style="width: 30px;">
                                    <input type="checkbox" id="translio-wc-select-all">
                                </td>
                                <th style="width: 25%;"><?php esc_html_e('Attribute Name', 'translio'); ?></th>
                                <th style="width: 25%;"><?php echo esc_html($languages[$default_language]['name']); ?></th>
                                <th><?php echo esc_html($languages[$secondary_language]['name']); ?></th>
                                <th style="width: 100px;"><?php esc_html_e('Actions', 'translio'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attributes as $attr) :
                                $attr_id = $attr->attribute_id;
                                $attr_name = $attr->attribute_name;
                                $attr_label = $attr->attribute_label;

                                $translation = Translio_DB::get_translation($attr_id, 'wc_attribute', 'label', $secondary_language);
                                $translated_label = $translation ? $translation->translated_content : '';

                                $is_translated = !empty($translated_label);
                                $status_class = $is_translated ? 'translio-status-ok' : 'translio-status-missing';
                            ?>
                            <tr data-attr-id="<?php echo esc_attr($attr_id); ?>" data-original="<?php echo esc_attr($attr_label); ?>" data-translated="<?php echo $is_translated ? '1' : '0'; ?>">
                                <th scope="row" class="check-column">
                                    <input type="checkbox" class="translio-wc-attr-checkbox" value="<?php echo esc_attr($attr_id); ?>">
                                </th>
                                <td>
                                    <strong><?php echo esc_html($attr_name); ?></strong>
                                    <br>
                                    <small class="translio-text-muted">pa_<?php echo esc_html($attr_name); ?></small>
                                </td>
                                <td>
                                    <div class="translio-text-preview">
                                        <?php echo esc_html($attr_label); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="text"
                                               class="regular-text translio-wc-attr-input"
                                               data-attr-id="<?php echo esc_attr($attr_id); ?>"
                                               data-original="<?php echo esc_attr($attr_label); ?>"
                                               value="<?php echo esc_attr($translated_label); ?>"
                                               placeholder="<?php esc_attr_e('Translation', 'translio'); ?>">
                                        <span class="translio-save-status"></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($can_translate) : ?>
                                    <button type="button"
                                            class="button button-small translio-translate-wc-attr"
                                            data-attr-id="<?php echo esc_attr($attr_id); ?>"
                                            data-original="<?php echo esc_attr($attr_label); ?>">
                                        <?php esc_html_e('Translate', 'translio'); ?>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('Additional WooCommerce Translation', 'translio'); ?></h3>
                    <p>
                        <?php esc_html_e('To translate product attribute values (e.g., colors, sizes), go to:', 'translio'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=translio-taxonomies')); ?>">
                            <?php esc_html_e('Taxonomies', 'translio'); ?>
                        </a>
                    </p>
                    <p>
                        <?php esc_html_e('To translate products, categories, and tags, go to:', 'translio'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=translio')); ?>">
                            <?php esc_html_e('All Content', 'translio'); ?>
                        </a>
                    </p>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }
}
