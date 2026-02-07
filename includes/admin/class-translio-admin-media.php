<?php
/**
 * Translio Admin Media
 *
 * Media library translation page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Media {

    /**
     * Render Media page
     */
    public function render_page() {
        if (!current_user_can('upload_files')) {
            return;
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Media', 'translio'); ?></h1>
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

        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if (!empty($search_query)) {
            $args['s'] = $search_query;
        }

        $query = new WP_Query($args);

        // Count untranslated media
        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();
        $untranslated_count = 0;
        $all_attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        foreach ($all_attachments as $att_id) {
            $title_trans = Translio_DB::get_translation($att_id, 'attachment', 'title', $secondary_language);
            if (empty($title_trans->translated_content)) {
                $untranslated_count++;
            }
        }

        ?>
        <div class="wrap translio-media-page">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Media', 'translio'); ?></h1>

            <?php Translio_Admin::render_language_selector('translio-media'); ?>

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

            <div class="translio-filters" style="margin: 20px 0;">
                <form method="get">
                    <input type="hidden" name="page" value="translio-media">
                    <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search media...', 'translio'); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Search', 'translio'); ?></button>
                </form>
            </div>

            <?php if ($can_translate) : ?>
            <div class="translio-bulk-actions" style="margin: 15px 0;">
                <button type="button" class="button button-primary" id="translio-translate-selected-media" disabled>
                    <?php esc_html_e('Translate Selected', 'translio'); ?>
                    <span id="translio-selected-media-count">(0)</span>
                </button>
                <button type="button" class="button" id="translio-translate-all-untranslated-media">
                    <?php printf(__('Translate All Untranslated (%d)', 'translio'), $untranslated_count); ?>
                </button>
                <span class="translio-bulk-status" style="margin-left: 10px;"></span>
            </div>
            <?php endif; ?>

            <?php
            // Pagination variables
            $total_items = $query->found_posts;
            $total_pages = $query->max_num_pages;

            // Top pagination
            $this->render_tablenav('top', $total_items, $total_pages, $paged, $per_page);
            ?>

            <table class="wp-list-table widefat fixed striped translio-media-table">
                <thead>
                    <tr>
                        <th class="column-cb" style="width: 30px;"><input type="checkbox" id="translio-select-all-media"></th>
                        <th class="column-thumbnail"><?php esc_html_e('Thumbnail', 'translio'); ?></th>
                        <th class="column-title"><?php esc_html_e('Title', 'translio'); ?></th>
                        <th class="column-type"><?php esc_html_e('Type', 'translio'); ?></th>
                        <th class="column-status"><?php esc_html_e('Translation Status', 'translio'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'translio'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->have_posts()) : ?>
                        <?php while ($query->have_posts()) : $query->the_post();
                            $attachment_id = get_the_ID();
                            $mime_type = get_post_mime_type();
                            $is_image = strpos($mime_type, 'image/') === 0;

                            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                            $title = get_the_title();
                            $caption = get_the_excerpt();
                            $description = get_the_content();

                            // Check translation status
                            $title_trans = Translio_DB::get_translation($attachment_id, 'attachment', 'title', $secondary_language);
                            $alt_trans = Translio_DB::get_translation($attachment_id, 'attachment', 'alt', $secondary_language);
                            $caption_trans = Translio_DB::get_translation($attachment_id, 'attachment', 'caption', $secondary_language);
                            $desc_trans = Translio_DB::get_translation($attachment_id, 'attachment', 'description', $secondary_language);

                            // Calculate overall translation status
                            $total_fields = 0;
                            $translated_fields = 0;

                            // Title is always present
                            if (!empty($title)) {
                                $total_fields++;
                                if (!empty($title_trans->translated_content)) $translated_fields++;
                            }
                            // Alt text for images
                            if ($is_image && !empty($alt_text)) {
                                $total_fields++;
                                if (!empty($alt_trans->translated_content)) $translated_fields++;
                            }
                            // Caption
                            if (!empty($caption)) {
                                $total_fields++;
                                if (!empty($caption_trans->translated_content)) $translated_fields++;
                            }
                            // Description
                            if (!empty($description)) {
                                $total_fields++;
                                if (!empty($desc_trans->translated_content)) $translated_fields++;
                            }

                            // Determine status
                            if ($total_fields === 0) {
                                $status_class = 'translio-status-none';
                                $status_text = __('No Content', 'translio');
                            } elseif ($translated_fields === 0) {
                                $status_class = 'translio-status-untranslated';
                                $status_text = __('Untranslated', 'translio');
                            } elseif ($translated_fields === $total_fields) {
                                $status_class = 'translio-status-translated';
                                $status_text = __('Translated', 'translio');
                            } else {
                                $status_class = 'translio-status-partial';
                                $status_text = sprintf(__('Partial (%d/%d)', 'translio'), $translated_fields, $total_fields);
                            }

                            $is_untranslated = $translated_fields < $total_fields;
                        ?>
                        <tr data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-untranslated="<?php echo $is_untranslated ? '1' : '0'; ?>">
                            <td class="column-cb">
                                <input type="checkbox" class="translio-media-checkbox" value="<?php echo esc_attr($attachment_id); ?>">
                            </td>
                            <td class="column-thumbnail">
                                <?php if ($is_image) : ?>
                                    <?php echo wp_get_attachment_image($attachment_id, array(80, 80)); ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-media-default" style="font-size: 48px; width: 48px; height: 48px;"></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-title">
                                <strong><?php echo esc_html($title); ?></strong>
                                <br>
                                <small class="filename"><?php echo esc_html(basename(get_attached_file($attachment_id))); ?></small>
                            </td>
                            <td class="column-type"><?php echo esc_html($mime_type); ?></td>
                            <td class="column-status">
                                <span class="translio-status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            <td class="column-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-translate-media&attachment_id=' . $attachment_id)); ?>"
                                   class="button button-small">
                                    <?php esc_html_e('Edit', 'translio'); ?>
                                </a>
                                <?php if ($is_untranslated && $can_translate) : ?>
                                <button type="button" class="button button-small button-primary translio-translate-media-inline"
                                        data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6">
                                <p style="padding: 20px; text-align: center;">
                                    <?php esc_html_e('No media files found.', 'translio'); ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            // Bottom pagination
            $this->render_tablenav('bottom', $total_items, $total_pages, $paged, $per_page);
            wp_reset_postdata();
            ?>
        </div>
        <?php
    }

    /**
     * Render tablenav with pagination (WordPress standard style)
     */
    private function render_tablenav($which, $total_items, $total_pages, $current_page, $per_page) {
        ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(_n('%s item', '%s items', $total_items, 'translio'), number_format_i18n($total_items)); ?>
                </span>
                <?php if ($total_pages > 1) : ?>
                <span class="pagination-links">
                    <?php
                    // First page link
                    if ($current_page > 1) {
                        printf(
                            '<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                            esc_url(remove_query_arg('paged')),
                            __('First page', 'translio'),
                            '&laquo;'
                        );
                    } else {
                        printf(
                            '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
                            '&laquo;'
                        );
                    }

                    // Previous page link
                    if ($current_page > 1) {
                        printf(
                            '<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                            esc_url(add_query_arg('paged', max(1, $current_page - 1))),
                            __('Previous page', 'translio'),
                            '&lsaquo;'
                        );
                    } else {
                        printf(
                            '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
                            '&lsaquo;'
                        );
                    }
                    ?>

                    <span class="paging-input">
                        <label for="current-page-selector-<?php echo esc_attr($which); ?>" class="screen-reader-text">
                            <?php esc_html_e('Current Page', 'translio'); ?>
                        </label>
                        <input class="current-page" id="current-page-selector-<?php echo esc_attr($which); ?>"
                               type="text" name="paged" value="<?php echo esc_attr($current_page); ?>"
                               size="<?php echo strlen($total_pages); ?>" aria-describedby="table-paging">
                        <span class="tablenav-paging-text">
                            <?php esc_html_e('of', 'translio'); ?>
                            <span class="total-pages"><?php echo number_format_i18n($total_pages); ?></span>
                        </span>
                    </span>

                    <?php
                    // Next page link
                    if ($current_page < $total_pages) {
                        printf(
                            '<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                            esc_url(add_query_arg('paged', min($total_pages, $current_page + 1))),
                            __('Next page', 'translio'),
                            '&rsaquo;'
                        );
                    } else {
                        printf(
                            '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
                            '&rsaquo;'
                        );
                    }

                    // Last page link
                    if ($current_page < $total_pages) {
                        printf(
                            '<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                            esc_url(add_query_arg('paged', $total_pages)),
                            __('Last page', 'translio'),
                            '&raquo;'
                        );
                    } else {
                        printf(
                            '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
                            '&raquo;'
                        );
                    }
                    ?>
                </span>
                <?php endif; ?>
            </div>
            <br class="clear">
        </div>
        <?php
    }

    /**
     * Render Translate Media page
     */
    public function render_translate_media_page() {
        if (!current_user_can('upload_files')) {
            return;
        }

        $attachment_id = isset($_GET['attachment_id']) ? absint($_GET['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_die(__('Invalid attachment ID', 'translio'));
        }

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_die(__('Attachment not found', 'translio'));
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();
        $languages = Translio::get_available_languages();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Translate Media', 'translio'); ?></h1>
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

        $mime_type = get_post_mime_type($attachment_id);
        $is_image = strpos($mime_type, 'image/') === 0;

        $alt_text = $is_image ? get_post_meta($attachment_id, '_wp_attachment_image_alt', true) : '';
        $title = $attachment->post_title;
        $caption = $attachment->post_excerpt;
        $description = $attachment->post_content;

        $title_trans = Translio_DB::get_translation($attachment_id, 'attachment', 'title', $secondary_language);
        $alt_trans = Translio_DB::get_translation($attachment_id, 'attachment', 'alt', $secondary_language);
        $caption_trans = Translio_DB::get_translation($attachment_id, 'attachment', 'caption', $secondary_language);
        $desc_trans = Translio_DB::get_translation($attachment_id, 'attachment', 'description', $secondary_language);

        // Check if translation is available (BYOAI with API key OR proxy mode with valid license)
        $api = Translio_API::instance();
        $can_translate = $api->is_configured();

        ?>
        <div class="wrap translio-translate">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-media')); ?>" class="translio-back">
                    ← <?php esc_html_e('Back to Media', 'translio'); ?>
                </a>
                <?php esc_html_e('Translate Media:', 'translio'); ?> <?php echo esc_html($title); ?>
            </h1>

            <?php Translio_Admin::render_language_selector('translio-translate-media'); ?>

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
                <button type="button" class="button button-primary" id="translio-translate-media-all"
                        data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
                    <?php esc_html_e('Auto-translate all fields', 'translio'); ?>
                </button>
                <?php endif; ?>
            </div>

            <div style="margin: 20px 0;">
                <?php if ($is_image) : ?>
                    <?php echo wp_get_attachment_image($attachment_id, 'medium'); ?>
                <?php else : ?>
                    <p><strong><?php echo esc_html($mime_type); ?></strong></p>
                    <p><?php echo esc_html(basename(get_attached_file($attachment_id))); ?></p>
                <?php endif; ?>
            </div>

            <div class="translio-editor" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">

                <!-- Alt Text (Images only) -->
                <?php if ($is_image) : ?>
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Alt Text', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($alt_text); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate && !empty($alt_text)) : ?>
                                <button type="button" class="button button-small translio-translate-field"
                                        data-field="alt">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <input type="text"
                                       class="translio-input"
                                       data-field="alt"
                                       value="<?php echo esc_attr($alt_trans ? $alt_trans->translated_content : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Title -->
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Title', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($title); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-field"
                                        data-field="title">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <input type="text"
                                       class="translio-input"
                                       data-field="title"
                                       value="<?php echo esc_attr($title_trans ? $title_trans->translated_content : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Caption -->
                <?php if (!empty($caption)) : ?>
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Caption', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($caption); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-field"
                                        data-field="caption">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <textarea class="translio-textarea"
                                          data-field="caption"
                                          rows="3"><?php echo esc_textarea($caption_trans ? $caption_trans->translated_content : ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Description -->
                <?php if (!empty($description)) : ?>
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Description', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($description); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($can_translate) : ?>
                                <button type="button" class="button button-small translio-translate-field"
                                        data-field="description">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <textarea class="translio-textarea"
                                          data-field="description"
                                          rows="5"><?php echo esc_textarea($desc_trans ? $desc_trans->translated_content : ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }
}
