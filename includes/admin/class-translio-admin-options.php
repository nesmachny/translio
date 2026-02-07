<?php
/**
 * Translio Admin Options
 *
 * Site options and widgets translation page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Options {

    /**
     * Render Options page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $secondary_language = Translio_Admin::get_admin_language();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Site Options', 'translio'); ?></h1>
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

        // Get site options
        $blogname = get_option('blogname');
        $blogdescription = get_option('blogdescription');

        // Get translations
        $blogname_trans = Translio_DB::get_translation(1, 'option', 'blogname', $secondary_language);
        $blogdesc_trans = Translio_DB::get_translation(1, 'option', 'blogdescription', $secondary_language);

        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();

        // Get widgets
        global $wp_registered_sidebars, $wp_registered_widgets;
        $sidebars_widgets = wp_get_sidebars_widgets();

        ?>
        <div class="wrap translio-translate">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Site Options', 'translio'); ?></h1>

            <?php Translio_Admin::render_language_selector('translio-options'); ?>

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

            <h2><?php esc_html_e('Site Information', 'translio'); ?></h2>

            <div class="translio-editor">
                <!-- Site Title -->
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Site Title', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($blogname); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-option"
                                        data-option="blogname"
                                        data-original="<?php echo esc_attr($blogname); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <input type="text"
                                       class="translio-input translio-option-input"
                                       data-option="blogname"
                                       value="<?php echo esc_attr($blogname_trans ? $blogname_trans->translated_content : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tagline -->
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Tagline', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($blogdescription); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-option"
                                        data-option="blogdescription"
                                        data-original="<?php echo esc_attr($blogdescription); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <input type="text"
                                       class="translio-input translio-option-input"
                                       data-option="blogdescription"
                                       value="<?php echo esc_attr($blogdesc_trans ? $blogdesc_trans->translated_content : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <h2 style="margin-top: 40px;"><?php esc_html_e('Widgets', 'translio'); ?></h2>

            <div class="translio-widgets-list">
                <?php
                if (!empty($sidebars_widgets)) :
                    foreach ($sidebars_widgets as $sidebar_id => $widget_ids) :
                        if ($sidebar_id === 'wp_inactive_widgets' || empty($widget_ids)) {
                            continue;
                        }

                        $sidebar_name = isset($wp_registered_sidebars[$sidebar_id]) ? $wp_registered_sidebars[$sidebar_id]['name'] : $sidebar_id;
                        ?>
                        <h3><?php echo esc_html($sidebar_name); ?></h3>
                        <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
                            <thead>
                                <tr>
                                    <th style="width: 30%;"><?php esc_html_e('Widget', 'translio'); ?></th>
                                    <th><?php esc_html_e('Content', 'translio'); ?></th>
                                    <th style="width: 20%;"><?php esc_html_e('Status', 'translio'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($widget_ids as $widget_id) :
                                    if (!isset($wp_registered_widgets[$widget_id])) {
                                        continue;
                                    }

                                    $widget = $wp_registered_widgets[$widget_id];
                                    $widget_name = $widget['name'];

                                    // Get widget instance data
                                    $widget_obj = $widget['callback'][0] ?? null;
                                    $widget_instance = array();

                                    if ($widget_obj && method_exists($widget_obj, 'get_settings')) {
                                        $all_instances = $widget_obj->get_settings();
                                        $widget_number = $widget['params'][0]['number'] ?? null;
                                        if ($widget_number && isset($all_instances[$widget_number])) {
                                            $widget_instance = $all_instances[$widget_number];
                                        }
                                    }

                                    // Extract translatable content
                                    $translatable_fields = $this->extract_widget_strings($widget_instance);

                                    if (empty($translatable_fields)) {
                                        continue;
                                    }

                                    $widget_hash_id = Translio_Utils::generate_hash_id('widget', $widget_id);

                                    $translated_count = 0;
                                    foreach ($translatable_fields as $field_key => $field_value) {
                                        $trans = Translio_DB::get_translation($widget_hash_id, 'widget', $field_key, $secondary_language);
                                        if (!empty($trans->translated_content)) {
                                            $translated_count++;
                                        }
                                    }

                                    $is_translated = $translated_count === count($translatable_fields);
                                    $status_class = $is_translated ? 'translio-status-ok' : 'translio-status-missing';
                                    $status_text = sprintf(__('%d/%d translated', 'translio'), $translated_count, count($translatable_fields));
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($widget_name); ?></strong></td>
                                        <td>
                                            <?php
                                            $preview_texts = array();
                                            foreach ($translatable_fields as $value) {
                                                if (is_string($value)) {
                                                    $preview_texts[] = wp_trim_words(strip_tags($value), 8);
                                                }
                                            }
                                            echo esc_html(implode(' | ', array_slice($preview_texts, 0, 3)));
                                            ?>
                                        </td>
                                        <td>
                                            <span class="translio-status <?php echo esc_attr($status_class); ?>">
                                                <?php echo esc_html($status_text); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach;
                endif;
                ?>

                <?php if (empty($sidebars_widgets) || count($sidebars_widgets) <= 1) : ?>
                    <p><?php esc_html_e('No widgets found. Add widgets in Appearance > Widgets.', 'translio'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Extract translatable strings from widget instance
     */
    private function extract_widget_strings($instance, $prefix = '') {
        $strings = array();

        if (!is_array($instance)) {
            return $strings;
        }

        foreach ($instance as $key => $value) {
            // Skip certain keys
            if (in_array($key, array('_multiwidget', 'filter', 'nav_menu'))) {
                continue;
            }

            $full_key = $prefix ? $prefix . '_' . $key : $key;

            if (is_array($value)) {
                $nested = $this->extract_widget_strings($value, $full_key);
                $strings = array_merge($strings, $nested);
            } elseif (is_string($value) && !empty($value) && strlen($value) > 2) {
                // Skip URLs, numbers, and technical values
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    continue;
                }
                if (is_numeric($value)) {
                    continue;
                }
                if (preg_match('/^[0-9\-_]+$/', $value)) {
                    continue;
                }

                $strings[$full_key] = $value;
            }
        }

        return $strings;
    }
}
