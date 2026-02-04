<?php
/**
 * Translio Admin Content
 *
 * All Content list page and Translate Post page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Content {

    /**
     * Render All Content page
     */
    public function render_content_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language(); // Use selected language

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Translio', 'translio'); ?></h1>
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

        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-list-table.php';

        $list_table = new Translio_List_Table($secondary_language);
        $list_table->prepare_items();

        $untranslated_count = Translio_DB::count_untranslated_posts($secondary_language);
        $needs_update_count = Translio_DB::count_posts_needing_update($secondary_language);

        ?>
        <div class="wrap">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('All Content', 'translio'); ?></h1>

            <?php Translio_Admin::render_language_selector('translio-content'); ?>

            <div class="translio-content-actions" class="translio-actions-bar-wrap">
                <button type="button" class="button button-primary" id="translio-translate-selected" disabled>
                    <span class="dashicons dashicons-yes translio-icon"></span>
                    <?php esc_html_e('Translate Selected', 'translio'); ?>
                    <span id="translio-selected-count">(0)</span>
                </button>
                <button type="button" class="button button-primary" id="translio-translate-all"
                    <?php echo $untranslated_count === 0 ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-translation translio-icon"></span>
                    <?php printf(__('Translate All Untranslated (%d)', 'translio'), $untranslated_count); ?>
                </button>
                <button type="button" class="button" id="translio-translate-changes"
                    <?php echo $needs_update_count === 0 ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-update translio-icon"></span>
                    <?php printf(__('Translate Changes (%d)', 'translio'), $needs_update_count); ?>
                </button>
                <span id="translio-content-status" class="translio-text-muted"></span>
            </div>

            <form method="get" id="translio-content-form">
                <input type="hidden" name="page" value="translio-content">
                <?php
                $list_table->search_box(__('Search', 'translio'), 'translio-search');
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Translate Post page
     */
    public function render_translate_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $object_type = isset($_GET['object_type']) ? sanitize_text_field($_GET['object_type']) : 'post';

        if (!$post_id) {
            wp_die(__('Invalid post ID', 'translio'));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('Post not found', 'translio'));
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language(); // Use selected language
        $languages = Translio::get_available_languages();

        // Handle case when no secondary language is configured
        if (empty($secondary_language) || !isset($languages[$secondary_language])) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Translate', 'translio'); ?></h1>
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

        $translations = Translio_DB::get_translations_for_object($post_id, $post->post_type, $secondary_language);
        $translations_by_field = array();
        foreach ($translations as $t) {
            $translations_by_field[$t->field_name] = $t;
        }

        $translatable_fields = array(
            'title' => array(
                'label' => __('Title', 'translio'),
                'original' => $post->post_title,
                'type' => 'text',
            ),
            'content' => array(
                'label' => __('Content', 'translio'),
                'original' => $post->post_content,
                'type' => 'editor',
            ),
            'excerpt' => array(
                'label' => __('Excerpt', 'translio'),
                'original' => $post->post_excerpt,
                'type' => 'textarea',
            ),
        );

        // Add SEO fields if a SEO plugin is active
        $seo_title = '';
        $seo_description = '';

        if (defined('WPSEO_VERSION')) {
            $seo_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
            $seo_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        } elseif (class_exists('RankMath')) {
            $seo_title = get_post_meta($post_id, 'rank_math_title', true);
            $seo_description = get_post_meta($post_id, 'rank_math_description', true);
        } elseif (defined('AIOSEO_VERSION')) {
            $seo_title = get_post_meta($post_id, '_aioseo_title', true);
            $seo_description = get_post_meta($post_id, '_aioseo_description', true);
        }

        if (!empty($seo_title) || !empty($seo_description)) {
            if (!empty($seo_title)) {
                $translatable_fields['seo_title'] = array(
                    'label' => __('SEO Title', 'translio'),
                    'original' => $seo_title,
                    'type' => 'text',
                );
            }
            if (!empty($seo_description)) {
                $translatable_fields['seo_description'] = array(
                    'label' => __('SEO Description', 'translio'),
                    'original' => $seo_description,
                    'type' => 'textarea',
                );
            }
        }

        // Add custom meta fields
        $custom_meta_fields = $this->get_translatable_meta_fields($post_id);
        foreach ($custom_meta_fields as $meta_key => $meta_data) {
            $translatable_fields['meta_' . $meta_key] = $meta_data;
        }

        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();

        ?>
        <div class="wrap translio-translate">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-content')); ?>" class="translio-back">
                    ← <?php esc_html_e('Back to list', 'translio'); ?>
                </a>
                <?php esc_html_e('Translate:', 'translio'); ?> <?php echo esc_html($post->post_title); ?>
            </h1>

            <?php Translio_Admin::render_language_selector('translio-translate'); ?>

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
                <button type="button" class="button button-primary" id="translio-translate-page"
                        data-post-id="<?php echo esc_attr($post_id); ?>">
                    <?php esc_html_e('Auto-translate all fields', 'translio'); ?>
                </button>
                <?php endif; ?>
            </div>

            <div class="translio-editor" data-post-id="<?php echo esc_attr($post_id); ?>" data-object-type="post">
                <?php foreach ($translatable_fields as $field_name => $field) :
                    $translation = isset($translations_by_field[$field_name]) ? $translations_by_field[$field_name] : null;
                    $translated_value = $translation ? $translation->translated_content : '';
                    $is_auto = $translation && $translation->is_auto_translated;
                    $needs_update = false;

                    if ($translation && $field_name === 'content') {
                        $current_hash = md5($field['original']);
                        $needs_update = $translation->original_hash !== $current_hash;
                    }
                ?>
                <div class="translio-field-row <?php echo $needs_update ? 'translio-needs-update' : ''; ?>">
                    <div class="translio-field-header">
                        <h3><?php echo esc_html($field['label']); ?></h3>
                        <?php if ($needs_update) : ?>
                            <span class="translio-update-badge"><?php esc_html_e('Content changed', 'translio'); ?></span>
                        <?php endif; ?>
                        <?php if ($is_auto) : ?>
                            <span class="translio-auto-badge"><?php esc_html_e('Auto-translated', 'translio'); ?></span>
                        <?php endif; ?>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                                <button type="button" class="translio-collapse-toggle" title="<?php esc_attr_e('Toggle source panel', 'translio'); ?>">
                                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    <span class="collapse-text"><?php esc_html_e('Collapse', 'translio'); ?></span>
                                    <span class="expand-text"><?php esc_html_e('Expand', 'translio'); ?></span>
                                </button>
                            </div>
                            <div class="translio-panel-content">
                                <?php if ($field['type'] === 'editor') : ?>
                                    <div class="translio-content-preview"><?php echo wp_kses_post($field['original']); ?></div>
                                <?php else : ?>
                                    <div class="translio-text-preview"><?php echo esc_html($field['original']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-field"
                                        data-field="<?php echo esc_attr($field_name); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <?php if ($field['type'] === 'editor') : ?>
                                    <?php
                                    wp_editor($translated_value, 'translio_' . $field_name, array(
                                        'textarea_name' => 'translio_' . $field_name,
                                        'textarea_rows' => 15,
                                        'media_buttons' => false,
                                        'teeny' => false,
                                        'quicktags' => true,
                                    ));
                                    ?>
                                <?php elseif ($field['type'] === 'textarea') : ?>
                                    <textarea name="translio_<?php echo esc_attr($field_name); ?>"
                                              class="translio-textarea"
                                              data-field="<?php echo esc_attr($field_name); ?>"
                                              rows="4"><?php echo esc_textarea($translated_value); ?></textarea>
                                <?php else : ?>
                                    <input type="text" name="translio_<?php echo esc_attr($field_name); ?>"
                                           class="translio-input"
                                           data-field="<?php echo esc_attr($field_name); ?>"
                                           value="<?php echo esc_attr($translated_value); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get translatable custom meta fields for a post
     */
    private function get_translatable_meta_fields($post_id) {
        $fields = array();

        $all_meta = get_post_meta($post_id);
        if (empty($all_meta)) {
            return $fields;
        }

        $excluded_prefixes = array(
            '_edit_', '_wp_', '_menu_', '_thumbnail', '_encloseme', '_pingme', '_oembed_',
            '_elementor_', '_wpb_', '_yoast_', '_aioseo_', 'rank_math_', '_genesis_',
            '_et_', '_fl_', '_bricks_', '_translio_',
        );

        $excluded_exact = array(
            'classic-editor-remember', 'inline_featured_image', '_edit_lock', '_edit_last',
            '_wp_page_template', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_old_slug', '_wp_old_date',
        );

        foreach ($all_meta as $meta_key => $meta_values) {
            if (empty($meta_values[0])) {
                continue;
            }

            $meta_value = $meta_values[0];

            $skip = false;
            foreach ($excluded_prefixes as $prefix) {
                if (strpos($meta_key, $prefix) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            if (in_array($meta_key, $excluded_exact)) continue;

            if (strpos($meta_key, '_') === 0) continue;

            if (is_serialized($meta_value)) {
                $unserialized = maybe_unserialize($meta_value);
                if (is_array($unserialized)) {
                    $array_content = $this->extract_translatable_from_array($unserialized);
                    if (!empty($array_content)) {
                        $fields[$meta_key] = array(
                            'label' => $this->format_meta_key_label($meta_key),
                            'original' => wp_json_encode($unserialized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                            'type' => 'textarea',
                            'is_json' => true,
                        );
                    }
                }
                continue;
            }

            if (is_numeric($meta_value)) continue;
            if (strlen($meta_value) < 3) continue;
            if (filter_var($meta_value, FILTER_VALIDATE_URL)) continue;
            if (in_array(substr(trim($meta_value), 0, 1), array('{', '[', 'a:', 'O:'))) continue;
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $meta_value)) continue;
            if (strpos($meta_value, '/') === 0) continue;

            $skip_key_patterns = array(
                '_link', '_url', '_id', '_class', '_style', '_color', '_size',
                '_image', '_icon', '_video', '_file', '_media', '_background',
                '_width', '_height', '_margin', '_padding', '_border',
            );
            $skip_by_key = false;
            foreach ($skip_key_patterns as $pattern) {
                if (stripos($meta_key, $pattern) !== false) {
                    $skip_by_key = true;
                    break;
                }
            }
            if ($skip_by_key) continue;

            $skip_values = array(
                'primary', 'secondary', 'default', 'none', 'left', 'right', 'center',
                'top', 'bottom', 'true', 'false', 'yes', 'no', 'on', 'off',
                'inherit', 'auto', 'normal', 'bold', 'italic',
            );
            if (in_array(strtolower(trim($meta_value)), $skip_values)) continue;

            $type = strlen($meta_value) > 200 || strpos($meta_value, "\n") !== false ? 'textarea' : 'text';

            if ($meta_value !== strip_tags($meta_value)) {
                $type = 'editor';
            }

            $fields[$meta_key] = array(
                'label' => $this->format_meta_key_label($meta_key),
                'original' => $meta_value,
                'type' => $type,
            );
        }

        return $fields;
    }

    /**
     * Format meta key as human-readable label
     */
    private function format_meta_key_label($meta_key) {
        $label = preg_replace('/^(meta_|custom_|field_|acf_)/', '', $meta_key);
        $label = str_replace(array('_', '-'), ' ', $label);
        $label = ucwords($label);
        return $label;
    }

    /**
     * Extract translatable strings from nested arrays
     */
    private function extract_translatable_from_array($array, $depth = 0) {
        if ($depth > 5) {
            return array();
        }

        $translatable = array();

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $nested = $this->extract_translatable_from_array($value, $depth + 1);
                $translatable = array_merge($translatable, $nested);
            } elseif (is_string($value) && strlen($value) > 2 && !is_numeric($value)) {
                if (!filter_var($value, FILTER_VALIDATE_URL) && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                    $translatable[] = $value;
                }
            }
        }

        return $translatable;
    }
}
