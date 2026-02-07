<?php
/**
 * Translio Admin Divi
 *
 * Divi pages list and translation editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Divi {

    /**
     * Render Divi pages list
     */
    public function render_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        if (!Translio_Divi::is_divi_active()) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Divi', 'translio'); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e('Divi Theme or Divi Builder plugin is not installed or activated.', 'translio'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Divi', 'translio'); ?></h1>
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

        // Get all posts that use Divi
        global $wpdb;
        $post_ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_et_pb_use_builder' AND meta_value = 'on'"
        );

        ?>
        <div class="wrap">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Divi Pages', 'translio'); ?></h1>

            <?php Translio_Admin::render_language_selector('translio-divi'); ?>

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

            <p class="description">
                <?php esc_html_e('Translate Divi page builder content including text modules, buttons, CTAs, slides, and more.', 'translio'); ?>
            </p>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th style="width: 40%;"><?php esc_html_e('Title', 'translio'); ?></th>
                        <th><?php esc_html_e('Type', 'translio'); ?></th>
                        <th><?php esc_html_e('Status', 'translio'); ?></th>
                        <th><?php esc_html_e('Translation Status', 'translio'); ?></th>
                        <th><?php esc_html_e('Actions', 'translio'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($post_ids)) : ?>
                        <?php foreach ($post_ids as $post_id) :
                            $post = get_post($post_id);
                            if (!$post || $post->post_status === 'trash') continue;

                            $post_type_obj = get_post_type_object($post->post_type);

                            // Get Divi fields
                            $fields = Translio_Divi::extract_fields($post_id);
                            $total_fields = count($fields);

                            if ($total_fields === 0) {
                                continue;
                            }

                            // Count translated fields
                            $translated_count = Translio_Divi::count_translated_fields($post_id, $secondary_language);

                            $is_translated = $translated_count === $total_fields;
                            $status_class = $is_translated ? 'translio-status-ok' : ($translated_count > 0 ? 'translio-status-partial' : 'translio-status-missing');
                            $status_text = sprintf(__('%d/%d translated', 'translio'), $translated_count, $total_fields);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($post->post_title); ?></strong>
                            </td>
                            <td><?php echo esc_html($post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type); ?></td>
                            <td><?php echo esc_html($post->post_status); ?></td>
                            <td>
                                <span class="translio-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-translate-divi&post_id=' . $post_id)); ?>"
                                   class="button button-small">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">
                                <p style="padding: 20px; text-align: center;">
                                    <?php esc_html_e('No Divi pages found.', 'translio'); ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        wp_reset_postdata();
    }

    /**
     * Render Divi translation editor
     */
    public function render_translate_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $post = get_post($post_id);

        if (!$post) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Translate Divi Content', 'translio'); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e('Post not found.', 'translio'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Translate Divi', 'translio'); ?></h1>
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

        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();

        // Get fields status
        $fields_status = Translio_Divi::get_fields_status($post_id, $secondary_language);

        ?>
        <div class="wrap translio-translate-page">
            <h1>
                <img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo">
                <?php esc_html_e('Translate Divi:', 'translio'); ?>
                <?php echo esc_html($post->post_title); ?>
            </h1>

            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-divi')); ?>" class="button">
                    ← <?php esc_html_e('Back to list', 'translio'); ?>
                </a>
            </p>

            <?php Translio_Admin::render_language_selector('translio-translate-divi'); ?>

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

                <?php if ($can_translate) : ?>
                <div class="translio-translate-actions">
                    <button type="button" id="translio-translate-all-divi" class="button button-primary">
                        <?php esc_html_e('Auto-translate All', 'translio'); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($fields_status)) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No translatable Divi content found in this post.', 'translio'); ?></p>
                </div>
            <?php else : ?>
                <form id="translio-divi-form" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

                    <table class="wp-list-table widefat fixed translio-divi-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;"><?php esc_html_e('Module', 'translio'); ?></th>
                                <th style="width: 35%;"><?php esc_html_e('Original', 'translio'); ?></th>
                                <th style="width: 35%;"><?php esc_html_e('Translation', 'translio'); ?></th>
                                <th style="width: 15%;"><?php esc_html_e('Actions', 'translio'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields_status as $field_id => $field) :
                                $row_class = $field['is_translated'] ? '' : 'translio-needs-translation';
                                if ($field['needs_update']) $row_class = 'translio-needs-update';

                                // Format module name
                                $module_name = str_replace(array('et_pb_', '_'), array('', ' '), $field['module']);
                                $module_name = ucwords($module_name);

                                // Truncate long content for display
                                $display_original = wp_strip_all_tags($field['original']);
                                $is_long = strlen($display_original) > 200;
                            ?>
                            <tr class="<?php echo esc_attr($row_class); ?>" data-field-id="<?php echo esc_attr($field_id); ?>">
                                <td>
                                    <strong><?php echo esc_html($module_name); ?></strong>
                                    <br><small class="description"><?php echo esc_html($field['field']); ?></small>
                                    <?php if ($field['needs_update']) : ?>
                                        <br><span class="translio-update-badge"><?php esc_html_e('Needs update', 'translio'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="translio-original-text <?php echo $is_long ? 'translio-text-long' : ''; ?>">
                                        <?php echo esc_html($display_original); ?>
                                    </div>
                                    <?php if ($field['type'] === 'VISUAL' && $field['original'] !== $display_original) : ?>
                                        <small class="description"><?php esc_html_e('(Contains HTML)', 'translio'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($field['type'] === 'VISUAL') : ?>
                                        <textarea name="translio_divi[<?php echo esc_attr($field_id); ?>]"
                                                  class="translio-divi-input large-text"
                                                  rows="3"
                                                  data-original="<?php echo esc_attr($field['original']); ?>"><?php echo esc_textarea($field['translated']); ?></textarea>
                                    <?php else : ?>
                                        <input type="text"
                                               name="translio_divi[<?php echo esc_attr($field_id); ?>]"
                                               class="translio-divi-input regular-text"
                                               value="<?php echo esc_attr($field['translated']); ?>"
                                               data-original="<?php echo esc_attr($field['original']); ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($can_translate) : ?>
                                    <button type="button" class="button button-small translio-translate-divi-field"
                                            data-field-id="<?php echo esc_attr($field_id); ?>">
                                        <?php esc_html_e('Translate', 'translio'); ?>
                                    </button>
                                    <?php endif; ?>
                                    <span class="translio-field-status"></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Translations', 'translio'); ?>
                        </button>
                    </p>
                </form>
            <?php endif; ?>
        </div>

        <style>
            .translio-divi-table .translio-needs-translation td {
                background-color: #fff8e5;
            }
            .translio-divi-table .translio-needs-update td {
                background-color: #fef2f2;
            }
            .translio-original-text {
                max-height: 100px;
                overflow-y: auto;
                padding: 8px;
                background: #f6f7f7;
                border-radius: 4px;
                font-size: 13px;
            }
            .translio-text-long {
                max-height: 150px;
            }
            .translio-divi-input {
                width: 100% !important;
            }
            .translio-update-badge {
                display: inline-block;
                padding: 2px 6px;
                background: #dc3232;
                color: #fff;
                font-size: 11px;
                border-radius: 3px;
            }
            .translio-field-status .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
        </style>
        <?php
    }
}
