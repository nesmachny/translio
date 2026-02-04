<?php
/**
 * Translio Admin Taxonomies
 *
 * Taxonomies list page and Translate Term page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Taxonomies {

    /**
     * Render Taxonomies page
     */
    public function render_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Taxonomies', 'translio'); ?></h1>
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

        $taxonomy_filter = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : 'all';
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;

        // Get all public taxonomies
        $taxonomies = get_taxonomies(array('public' => true), 'objects');

        // Determine which taxonomies to query
        if ($taxonomy_filter !== 'all') {
            $tax_names = array($taxonomy_filter);
        } else {
            $tax_names = array_keys($taxonomies);
        }

        // Get total count first (without pagination)
        $count_args = array(
            'taxonomy' => $tax_names,
            'hide_empty' => false,
            'fields' => 'count',
        );
        if (!empty($search_query)) {
            $count_args['search'] = $search_query;
        }
        $total_items = get_terms($count_args);
        if (is_wp_error($total_items)) {
            $total_items = 0;
        }
        $total_pages = ceil($total_items / $per_page);

        // Get terms with pagination
        $args = array(
            'taxonomy' => $tax_names,
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
        );

        if (!empty($search_query)) {
            $args['search'] = $search_query;
        }

        $terms = get_terms($args);
        if (is_wp_error($terms)) {
            $terms = array();
        }

        // Count untranslated terms
        $untranslated_count = 0;
        foreach ($terms as $term) {
            $name_trans = Translio_DB::get_translation($term->term_id, 'term', 'name', $secondary_language);
            if (empty($name_trans->translated_content)) {
                $untranslated_count++;
            }
        }

        $has_api_key = !empty(Translio_Admin::decrypt_api_key());

        ?>
        <div class="wrap">
            <h1><img src="<?php echo esc_url(TRANSLIO_PLUGIN_URL . 'admin/images/logo.png'); ?>" alt="Translio" class="translio-logo"> <?php esc_html_e('Taxonomies', 'translio'); ?></h1>

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
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="translio-taxonomies">
                    <select name="taxonomy" onchange="this.form.submit()">
                        <option value="all" <?php selected($taxonomy_filter, 'all'); ?>>
                            <?php esc_html_e('All Taxonomies', 'translio'); ?>
                        </option>
                        <?php foreach ($taxonomies as $tax) : ?>
                            <option value="<?php echo esc_attr($tax->name); ?>" <?php selected($taxonomy_filter, $tax->name); ?>>
                                <?php echo esc_html($tax->label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <form method="get" style="display: inline-block; margin-left: 10px;">
                    <input type="hidden" name="page" value="translio-taxonomies">
                    <?php if ($taxonomy_filter !== 'all') : ?>
                        <input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy_filter); ?>">
                    <?php endif; ?>
                    <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search terms...', 'translio'); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Search', 'translio'); ?></button>
                </form>
            </div>

            <form method="post" id="translio-taxonomies-form">
                <?php wp_nonce_field('translio_bulk_taxonomies', 'translio_taxonomies_nonce'); ?>

                <?php if ($has_api_key) : ?>
                <div class="translio-actions-bar" style="margin: 15px 0;">
                    <button type="button" class="button" id="translio-translate-selected-terms" disabled>
                        <span class="dashicons dashicons-yes translio-icon"></span>
                        <?php esc_html_e('Translate Selected', 'translio'); ?>
                        <span id="translio-selected-terms-count">(0)</span>
                    </button>
                    <button type="button" class="button" id="translio-translate-all-terms"
                        <?php echo $untranslated_count === 0 ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-translation translio-icon"></span>
                        <?php printf(__('Translate All Untranslated (%d)', 'translio'), $untranslated_count); ?>
                    </button>
                    <span id="translio-taxonomies-status" class="translio-text-muted"></span>
                </div>
                <?php endif; ?>

                <?php $this->render_tablenav('top', $total_items, $total_pages, $paged); ?>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th><?php esc_html_e('Term', 'translio'); ?></th>
                            <th><?php esc_html_e('Taxonomy', 'translio'); ?></th>
                            <th><?php esc_html_e('Description', 'translio'); ?></th>
                            <th><?php esc_html_e('Translation Status', 'translio'); ?></th>
                            <th><?php esc_html_e('Actions', 'translio'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($terms)) : ?>
                            <tr>
                                <td colspan="6">
                                    <p style="padding: 20px; text-align: center;">
                                        <?php esc_html_e('No terms found.', 'translio'); ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($terms as $term) :
                                $name_trans = Translio_DB::get_translation($term->term_id, 'term', 'name', $secondary_language);
                                $desc_trans = Translio_DB::get_translation($term->term_id, 'term', 'description', $secondary_language);

                                $has_name = !empty($name_trans->translated_content);
                                $has_desc = !empty($term->description) ? !empty($desc_trans->translated_content) : true;
                                $is_translated = $has_name && $has_desc;

                                $status_class = $is_translated ? 'translio-status-ok' : 'translio-status-missing';
                                $status_text = $is_translated ? __('Translated', 'translio') : __('Not translated', 'translio');
                            ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="term_ids[]" value="<?php echo esc_attr($term->term_id); ?>" data-taxonomy="<?php echo esc_attr($term->taxonomy); ?>">
                                </th>
                                <td><strong><?php echo esc_html($term->name); ?></strong></td>
                                <td><?php echo esc_html($taxonomies[$term->taxonomy]->label); ?></td>
                                <td>
                                    <?php if (!empty($term->description)) : ?>
                                        <?php echo esc_html(wp_trim_words($term->description, 10)); ?>
                                    <?php else : ?>
                                        <span class="translio-text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="translio-status <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($has_api_key && !$is_translated) : ?>
                                    <button type="button" class="button button-small button-primary translio-translate-term-inline"
                                            data-term-id="<?php echo esc_attr($term->term_id); ?>"
                                            data-taxonomy="<?php echo esc_attr($term->taxonomy); ?>"
                                            data-name="<?php echo esc_attr($term->name); ?>"
                                            data-description="<?php echo esc_attr($term->description); ?>">
                                        <?php esc_html_e('Translate', 'translio'); ?>
                                    </button>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=translio-translate-term&term_id=' . $term->term_id . '&taxonomy=' . $term->taxonomy)); ?>"
                                       class="button button-small">
                                        <?php esc_html_e('Edit', 'translio'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php $this->render_tablenav('bottom', $total_items, $total_pages, $paged); ?>

            </form>
        </div>
        <?php
    }

    /**
     * Render tablenav with pagination (WordPress standard style)
     */
    private function render_tablenav($which, $total_items, $total_pages, $current_page) {
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
     * Render Translate Term page
     */
    public function render_translate_term_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $term_id = isset($_GET['term_id']) ? absint($_GET['term_id']) : 0;
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : '';

        if (!$term_id || !$taxonomy) {
            wp_die(__('Invalid term ID or taxonomy', 'translio'));
        }

        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_die(__('Term not found', 'translio'));
        }

        $default_language = translio()->get_setting('default_language');
        $secondary_language = Translio_Admin::get_admin_language();
        $languages = Translio::get_available_languages();

        if (empty($secondary_language)) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Translate Term', 'translio'); ?></h1>
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

        $name_trans = Translio_DB::get_translation($term_id, 'term', 'name', $secondary_language);
        $desc_trans = Translio_DB::get_translation($term_id, 'term', 'description', $secondary_language);

        $has_api_key = !empty(Translio_Admin::decrypt_api_key());

        ?>
        <div class="wrap translio-translate">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=translio-taxonomies')); ?>" class="translio-back">
                    ← <?php esc_html_e('Back to Taxonomies', 'translio'); ?>
                </a>
                <?php esc_html_e('Translate Term:', 'translio'); ?> <?php echo esc_html($term->name); ?>
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
            </div>

            <div class="translio-editor" data-term-id="<?php echo esc_attr($term_id); ?>" data-taxonomy="<?php echo esc_attr($taxonomy); ?>">

                <!-- Term Name -->
                <div class="translio-field-row">
                    <div class="translio-field-header">
                        <h3><?php esc_html_e('Term Name', 'translio'); ?></h3>
                        <span class="translio-save-status"></span>
                    </div>

                    <div class="translio-panels">
                        <div class="translio-panel translio-panel-original">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$default_language]['name']); ?>
                            </div>
                            <div class="translio-panel-content">
                                <div class="translio-text-preview"><?php echo esc_html($term->name); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($has_api_key) : ?>
                                <button type="button" class="button button-small translio-translate-term-field"
                                        data-field="name"
                                        data-text="<?php echo esc_attr($term->name); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <input type="text"
                                       class="translio-input"
                                       id="translio-name"
                                       data-field="name"
                                       value="<?php echo esc_attr($name_trans ? $name_trans->translated_content : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Term Description -->
                <?php if (!empty($term->description)) : ?>
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
                                <div class="translio-text-preview"><?php echo esc_html($term->description); ?></div>
                            </div>
                        </div>

                        <div class="translio-panel translio-panel-translation">
                            <div class="translio-panel-header">
                                <?php echo esc_html($languages[$secondary_language]['name']); ?>
                                <?php if ($has_api_key) : ?>
                                <button type="button" class="button button-small translio-translate-term-field"
                                        data-field="description"
                                        data-text="<?php echo esc_attr($term->description); ?>">
                                    <?php esc_html_e('Translate', 'translio'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="translio-panel-content">
                                <textarea class="translio-textarea"
                                          id="translio-description"
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
