<?php
/**
 * Translio Admin Elementor
 *
 * Elementor pages list and translation editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Elementor {

    /**
     * Render Elementor pages list
     */
    public function render_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        if (!did_action('elementor/loaded')) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Elementor', 'translio'); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e('Elementor is not installed or activated.', 'translio'); ?></p>
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
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Elementor', 'translio'); ?></h1>
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

        // Get all posts that use Elementor
        $args = array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_elementor_edit_mode',
                    'value' => 'builder',
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query($args);

        ?>
        <div class="wrap">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Elementor Pages', 'translio'); ?></h1>

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
                <?php esc_html_e('Translate Elementor page builder content including headings, text, buttons, and more.', 'translio'); ?>
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
                    <?php if ($query->have_posts()) : ?>
                        <?php while ($query->have_posts()) : $query->the_post();
                            $post_id = get_the_ID();
                            $post_type_obj = get_post_type_object(get_post_type());

                            // Get Elementor strings
                            $strings = Translio_Elementor::extract_translatable_strings($post_id);
                            $total_strings = count($strings);

                            if ($total_strings === 0) {
                                continue;
                            }

                            // Count translated strings
                            $translated_count = 0;
                            foreach ($strings as $string) {
                                $trans = Translio_Elementor::get_translation($post_id, $string['element_id'], $string['field'], $secondary_language);
                                if (!empty($trans->translated_content)) {
                                    $translated_count++;
                                }
                            }

                            $is_translated = $translated_count === $total_strings;
                            $status_class = $is_translated ? 'translio-status-ok' : ($translated_count > 0 ? 'translio-status-partial' : 'translio-status-missing');
                            $status_text = sprintf(__('%d/%d translated', 'translio'), $translated_count, $total_strings);
                        ?>
                        <tr>
                            <td>
                                <strong><?php the_title(); ?></strong>
                            </td>
                            <td><?php echo esc_html($post_type_obj->labels->singular_name); ?></td>
                            <td><?php echo esc_html(get_post_status()); ?></td>
                            <td>
                                <span class="translio-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-translate-elementor&post_id=' . $post_id)); ?>"
                                   class="button button-small">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">
                                <p style="padding: 20px; text-align: center;">
                                    <?php esc_html_e('No Elementor pages found.', 'translio'); ?>
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
     * Render Translate Elementor page
     */
    public function render_translate_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        if (!$post_id) {
            wp_die(__('Invalid post ID', 'translio'));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('Post not found', 'translio'));
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();
        $languages = Translio::get_available_languages();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Translate Elementor', 'translio'); ?></h1>
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

        // Get all translatable strings from Elementor
        $strings = Translio_Elementor::extract_translatable_strings($post_id);

        if (empty($strings)) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Elementor', 'translio'); ?></h1>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No translatable content found in this Elementor page.', 'translio'); ?></p>
                </div>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=translio-elementor')); ?>" class="button">
                        ← <?php esc_html_e('Back to Elementor Pages', 'translio'); ?>
                    </a>
                </p>
            </div>
            <?php
            return;
        }

        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();

        ?>
        <div class="wrap translio-translate">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-elementor')); ?>" class="translio-back">
                    ← <?php esc_html_e('Back to Elementor Pages', 'translio'); ?>
                </a>
                <?php esc_html_e('Translate Elementor:', 'translio'); ?> <?php echo esc_html($post->post_title); ?>
            </h1>

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
                <button type="button" class="button button-primary" id="translio-translate-elementor-all"
                        data-post-id="<?php echo esc_attr($post_id); ?>">
                    <?php esc_html_e('Auto-translate all fields', 'translio'); ?>
                </button>
                <?php endif; ?>
            </div>

            <div class="translio-editor" data-post-id="<?php echo esc_attr($post_id); ?>">
                <?php foreach ($strings as $string) :
                    $element_id = $string['element_id'];
                    $field = $string['field'];
                    $widget_type = $string['widget_type'];
                    $content = $string['content'];

                    $translation = Translio_Elementor::get_translation($post_id, $element_id, $field, $secondary_language);
                    $translated_value = $translation ? $translation->translated_content : '';

                    $widget_label = Translio_Elementor::get_widget_label($widget_type);
                    $field_label = Translio_Elementor::get_field_label($field);
                ?>
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php echo esc_html($widget_label . ' - ' . $field_label); ?></h3>
                        <small class="translio-text-muted">
                            <?php printf(__('Element: %s', 'translio'), esc_html($element_id)); ?>
                        </small>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo wp_kses_post($content); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-elementor-field"
                                        data-element-id="<?php echo esc_attr($element_id); ?>"
                                        data-field="<?php echo esc_attr($field); ?>"
                                        data-widget-type="<?php echo esc_attr($widget_type); ?>"
                                        data-original="<?php echo esc_attr($content); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <?php if (strlen($content) > 200 || strpos($content, '<') !== false) : ?>
                                    <textarea class="translio-textarea translio-elementor-input"
                                              data-element-id="<?php echo esc_attr($element_id); ?>"
                                              data-field="<?php echo esc_attr($field); ?>"
                                              data-original="<?php echo esc_attr($content); ?>"
                                              rows="5"><?php echo esc_textarea($translated_value); ?></textarea>
                                <?php else : ?>
                                    <input type="text"
                                           class="translio-input translio-elementor-input"
                                           data-element-id="<?php echo esc_attr($element_id); ?>"
                                           data-field="<?php echo esc_attr($field); ?>"
                                           data-original="<?php echo esc_attr($content); ?>"
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
}
