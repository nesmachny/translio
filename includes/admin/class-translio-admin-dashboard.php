<?php
/**
 * Translio Admin Dashboard
 *
 * Dashboard page with translation status widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Dashboard {

    /**
     * Render Dashboard page
     */
    public function render_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language(); // Use selected language
        $languages = Translio::get_available_languages();
        $has_api_key = !empty(Translio_Admin::decrypt_api_key());

        if (empty($secondary_language)) {
            ?>
            <div class="wrap translio-dashboard">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Dashboard', 'translio'); ?></h1>
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

        $default_lang_name = isset($languages[$default_language]) ? $languages[$default_language]['name'] : $default_language;
        $secondary_lang_name = isset($languages[$secondary_language]) ? $languages[$secondary_language]['name'] : $secondary_language;

        $stats = $this->get_dashboard_stats($secondary_language);

        ?>
        <div class="wrap translio-dashboard">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Dashboard', 'translio'); ?></h1>

            <?php Translio_Admin::render_language_selector('translio'); ?>

            <div class="translio-dashboard-header">
                <div class="translio-language-info">
                    <span class="translio-badge translio-badge-default"><?php echo esc_html($default_lang_name); ?></span>
                    <span class="translio-arrow">→</span>
                    <span class="translio-badge translio-badge-secondary"><?php echo esc_html($secondary_lang_name); ?></span>
                </div>
                <?php if (!$has_api_key): ?>
                <div class="translio-api-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('API key not configured.', 'translio'); ?>
                    <a href="<?php echo admin_url('admin.php?page=translio-settings'); ?>"><?php esc_html_e('Configure', 'translio'); ?></a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Overall Progress -->
            <div class="translio-overall-stats">
                <div class="translio-overall-card">
                    <div class="translio-overall-number"><?php echo number_format_i18n($stats['total']['items']); ?></div>
                    <div class="translio-overall-label"><?php esc_html_e('Total Items', 'translio'); ?></div>
                </div>
                <div class="translio-overall-card translio-card-success">
                    <div class="translio-overall-number"><?php echo number_format_i18n($stats['total']['translated']); ?></div>
                    <div class="translio-overall-label"><?php esc_html_e('Translated', 'translio'); ?></div>
                </div>
                <?php if ($stats['total']['items'] - $stats['total']['translated'] > 0): ?>
                <div class="translio-overall-card translio-card-warning">
                    <div class="translio-overall-number"><?php echo number_format_i18n($stats['total']['items'] - $stats['total']['translated']); ?></div>
                    <div class="translio-overall-label"><?php esc_html_e('Pending', 'translio'); ?></div>
                </div>
                <?php endif; ?>
                <div class="translio-overall-card translio-card-info">
                    <div class="translio-overall-number"><?php echo $stats['total']['percentage']; ?>%</div>
                    <div class="translio-overall-label"><?php esc_html_e('Progress', 'translio'); ?></div>
                </div>
            </div>

            <!-- Content Type Widgets -->
            <h2><?php esc_html_e('Content Types', 'translio'); ?></h2>
            <div class="translio-widgets-grid">
                <?php foreach ($stats['content_types'] as $type_key => $type_data):
                    $needs_attention = ($type_data['percentage'] < 100);
                    $card_class = 'translio-widget-card' . ($needs_attention ? ' translio-widget-needs-attention' : '');
                ?>
                <div class="<?php echo esc_attr($card_class); ?>">
                    <div class="translio-widget-header">
                        <span class="dashicons <?php echo esc_attr($type_data['icon']); ?>"></span>
                        <h3><?php echo esc_html($type_data['label']); ?></h3>
                    </div>
                    <div class="translio-widget-body">
                        <div class="translio-widget-stats">
                            <div class="translio-widget-stat">
                                <span class="translio-stat-value"><?php echo number_format_i18n($type_data['total']); ?></span>
                                <span class="translio-stat-label"><?php esc_html_e('Total', 'translio'); ?></span>
                            </div>
                            <div class="translio-widget-stat translio-stat-success">
                                <span class="translio-stat-value"><?php echo number_format_i18n($type_data['translated']); ?></span>
                                <span class="translio-stat-label"><?php esc_html_e('Translated', 'translio'); ?></span>
                            </div>
                            <?php if ($type_data['total'] - $type_data['translated'] > 0): ?>
                            <div class="translio-widget-stat translio-stat-pending">
                                <span class="translio-stat-value"><?php echo number_format_i18n($type_data['total'] - $type_data['translated']); ?></span>
                                <span class="translio-stat-label"><?php esc_html_e('Pending', 'translio'); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="translio-widget-progress">
                            <div class="translio-progress-bar">
                                <div class="translio-progress-fill" style="width: <?php echo esc_attr($type_data['percentage']); ?>%;"></div>
                            </div>
                            <span class="translio-progress-text"><?php echo esc_html($type_data['percentage']); ?>%</span>
                        </div>
                    </div>
                    <div class="translio-widget-footer">
                        <a href="<?php echo esc_url($type_data['url']); ?>" class="button button-small">
                            <?php esc_html_e('Manage', 'translio'); ?>
                        </a>
                        <?php if ($type_data['total'] - $type_data['translated'] > 0 && $has_api_key): ?>
                        <button type="button" class="button button-primary button-small translio-quick-translate"
                            data-type="<?php echo esc_attr($type_key); ?>">
                            <?php esc_html_e('Translate All', 'translio'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Links -->
            <h2><?php esc_html_e('Quick Links', 'translio'); ?></h2>
            <div class="translio-quick-links">
                <a href="<?php echo admin_url('admin.php?page=translio-settings'); ?>" class="translio-quick-link">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Settings', 'translio'); ?>
                </a>
                <a href="<?php echo home_url('/' . $secondary_language . '/'); ?>" target="_blank" class="translio-quick-link">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('View Translated Site', 'translio'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=translio-content'); ?>" class="translio-quick-link">
                    <span class="dashicons dashicons-editor-table"></span>
                    <?php esc_html_e('All Content', 'translio'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Get dashboard statistics for all content types
     */
    private function get_dashboard_stats($language_code) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'translio_translations';

        $stats = array(
            'total' => array('items' => 0, 'translated' => 0, 'percentage' => 0),
            'content_types' => array()
        );

        // Posts & Pages
        $post_types = Translio::get_translatable_post_types();
        foreach ($post_types as $post_type) {
            $pt_obj = get_post_type_object($post_type);
            if (!$pt_obj) continue;

            $total = wp_count_posts($post_type);
            $total_count = isset($total->publish) ? (int)$total->publish : 0;

            // For block theme types, also count draft status
            if (in_array($post_type, array('wp_navigation', 'wp_template_part', 'wp_block'))) {
                $total_count += isset($total->draft) ? (int)$total->draft : 0;
            }

            if ($total_count === 0) continue;

            $translated = $this->count_fully_translated_posts($post_type, $language_code);

            $percentage = $total_count > 0 ? round(($translated / $total_count) * 100) : 0;

            $icon = 'dashicons-admin-post';
            if ($post_type === 'page') $icon = 'dashicons-admin-page';
            elseif ($post_type === 'product') $icon = 'dashicons-cart';
            elseif ($post_type === 'attachment') $icon = 'dashicons-admin-media';

            $url = admin_url('admin.php?page=translio-content&content_type=' . $post_type);

            $stats['content_types']['post_' . $post_type] = array(
                'label' => $pt_obj->labels->name,
                'icon' => $icon,
                'total' => $total_count,
                'translated' => $translated,
                'percentage' => $percentage,
                'url' => $url
            );

            $stats['total']['items'] += $total_count;
            $stats['total']['translated'] += $translated;
        }

        // Taxonomies
        $taxonomies = get_taxonomies(array('public' => true), 'names');
        $tax_total = 0;
        foreach ($taxonomies as $taxonomy) {
            $tax_total += wp_count_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
        }

        $tax_translated = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT object_id) FROM $table_name
             WHERE object_type = 'term'
             AND field_name = 'name'
             AND language_code = %s
             AND translated_content != ''",
            $language_code
        ));

        if ($tax_total > 0) {
            $stats['content_types']['taxonomies'] = array(
                'label' => __('Taxonomies', 'translio'),
                'icon' => 'dashicons-tag',
                'total' => $tax_total,
                'translated' => min($tax_translated, $tax_total),
                'percentage' => round((min($tax_translated, $tax_total) / $tax_total) * 100),
                'url' => admin_url('admin.php?page=translio-taxonomies')
            );
            $stats['total']['items'] += $tax_total;
            $stats['total']['translated'] += min($tax_translated, $tax_total);
        }

        // Media
        $media_total = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
        );

        $media_translated = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT object_id) FROM $table_name
             WHERE object_type = 'attachment'
             AND language_code = %s
             AND translated_content != ''",
            $language_code
        ));

        if ($media_total > 0) {
            $stats['content_types']['media'] = array(
                'label' => __('Media', 'translio'),
                'icon' => 'dashicons-admin-media',
                'total' => $media_total,
                'translated' => min($media_translated, $media_total),
                'percentage' => round((min($media_translated, $media_total) / $media_total) * 100),
                'url' => admin_url('admin.php?page=translio-media')
            );
            $stats['total']['items'] += $media_total;
            $stats['total']['translated'] += min($media_translated, $media_total);
        }

        // Theme Strings
        $strings_total = Translio_DB::get_scanned_strings_count(array('language_code' => $language_code));
        $strings_translated = Translio_DB::get_scanned_strings_count(array('language_code' => $language_code, 'translated' => 'yes'));

        if ($strings_total > 0) {
            $stats['content_types']['strings'] = array(
                'label' => __('Theme Strings', 'translio'),
                'icon' => 'dashicons-editor-code',
                'total' => $strings_total,
                'translated' => $strings_translated,
                'percentage' => round(($strings_translated / $strings_total) * 100),
                'url' => admin_url('admin.php?page=translio-strings')
            );
            $stats['total']['items'] += $strings_total;
            $stats['total']['translated'] += $strings_translated;
        }

        // Site Options
        $options_total = 2;
        $options_translated = 0;
        $blogname_trans = Translio_DB::get_translation(1, 'option', 'blogname', $language_code);
        $blogdesc_trans = Translio_DB::get_translation(1, 'option', 'blogdescription', $language_code);
        if ($blogname_trans && !empty($blogname_trans->translated_content)) $options_translated++;
        if ($blogdesc_trans && !empty($blogdesc_trans->translated_content)) $options_translated++;

        $stats['content_types']['options'] = array(
            'label' => __('Site Options', 'translio'),
            'icon' => 'dashicons-admin-settings',
            'total' => $options_total,
            'translated' => $options_translated,
            'percentage' => round(($options_translated / $options_total) * 100),
            'url' => admin_url('admin.php?page=translio-options')
        );
        $stats['total']['items'] += $options_total;
        $stats['total']['translated'] += $options_translated;

        $stats['total']['percentage'] = $stats['total']['items'] > 0
            ? round(($stats['total']['translated'] / $stats['total']['items']) * 100)
            : 0;

        return $stats;
    }

    /**
     * Count posts that are fully translated (and up-to-date)
     */
    private function count_fully_translated_posts($post_type, $language_code) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'translio_translations';

        $block_types = array('wp_navigation', 'wp_template_part', 'wp_block');
        if (in_array($post_type, $block_types)) {
            $posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title, post_content, post_excerpt
                 FROM {$wpdb->posts}
                 WHERE post_type = %s AND post_status IN ('publish', 'draft')",
                $post_type
            ));
        } else {
            $posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title, post_content, post_excerpt
                 FROM {$wpdb->posts}
                 WHERE post_type = %s AND post_status = 'publish'",
                $post_type
            ));
        }

        if (empty($posts)) {
            return 0;
        }

        $post_ids = wp_list_pluck($posts, 'ID');

        // Get translations WITH original_hash to check for changes
        $translations = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, field_name, original_hash
             FROM {$table_name}
             WHERE object_id IN (" . implode(',', array_fill(0, count($post_ids), '%d')) . ")
             AND object_type = 'post'
             AND language_code = %s
             AND translated_content IS NOT NULL
             AND translated_content != ''",
            array_merge($post_ids, array($language_code))
        ));

        $translations_map = array();
        foreach ($translations as $t) {
            if (!isset($translations_map[$t->object_id])) {
                $translations_map[$t->object_id] = array();
            }
            $translations_map[$t->object_id][$t->field_name] = $t->original_hash;
        }

        $fully_translated = 0;

        foreach ($posts as $post) {
            $post_translations = isset($translations_map[$post->ID]) ? $translations_map[$post->ID] : array();

            $required = 0;
            $translated = 0;

            // Check title - must exist and hash must match
            if (!empty($post->post_title)) {
                $required++;
                if (isset($post_translations['title'])) {
                    $current_hash = md5($post->post_title);
                    if ($post_translations['title'] === $current_hash) {
                        $translated++;
                    }
                }
            }

            // Check content - must exist and hash must match
            if (!empty($post->post_content)) {
                $required++;
                if (isset($post_translations['content'])) {
                    $current_hash = md5($post->post_content);
                    if ($post_translations['content'] === $current_hash) {
                        $translated++;
                    }
                }
            }

            // Check excerpt - must exist and hash must match
            if (!empty($post->post_excerpt)) {
                $required++;
                if (isset($post_translations['excerpt'])) {
                    $current_hash = md5($post->post_excerpt);
                    if ($post_translations['excerpt'] === $current_hash) {
                        $translated++;
                    }
                }
            }

            if (class_exists('Translio_Elementor') && Translio_Elementor::has_elementor_data($post->ID)) {
                $elementor_total = Translio_Elementor::count_translatable_fields($post->ID);
                $elementor_translated = Translio_Elementor::count_translated_fields($post->ID, $language_code);

                if ($elementor_total > 0) {
                    $required += $elementor_total;
                    $translated += $elementor_translated;
                }
            }

            if ($required > 0 && $translated >= $required) {
                $fully_translated++;
            }
        }

        return $fully_translated;
    }
}
