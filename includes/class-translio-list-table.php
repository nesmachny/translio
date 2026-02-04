<?php
/**
 * Translio List Table
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Translio_List_Table extends WP_List_Table {

    private $secondary_language;
    private $languages;

    /**
     * Constructor
     *
     * @param string $language_code Optional language code. If not provided, uses admin language.
     */
    public function __construct($language_code = '') {
        parent::__construct(array(
            'singular' => 'item',
            'plural' => 'items',
            'ajax' => false,
        ));

        // Use provided language or fallback to admin language
        $this->secondary_language = !empty($language_code)
            ? $language_code
            : Translio_Admin::get_admin_language();

        $this->languages = Translio::get_available_languages();
    }

    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'title' => __('Title', 'translio'),
            'type' => __('Type', 'translio'),
            'languages' => __('Languages', 'translio'),
            'status' => __('Translation Status', 'translio'),
            'date' => __('Date', 'translio'),
        );
    }

    /**
     * Checkbox column
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="post_ids[]" value="%d" />',
            $item['ID']
        );
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'translate_selected' => __('Translate Selected', 'translio'),
        );
    }

    public function get_sortable_columns() {
        return array(
            'title' => array('title', false),
            'type' => array('type', false),
            'date' => array('date', true),
        );
    }

    public function prepare_items() {
        $per_page = $this->get_items_per_page('translio_per_page', 20);
        $current_page = $this->get_pagenum();

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $post_type_filter = isset($_REQUEST['content_type']) ? sanitize_text_field($_REQUEST['content_type']) : '';
        $status_filter = isset($_REQUEST['translation_status']) ? sanitize_text_field($_REQUEST['translation_status']) : '';

        $data = $this->get_content_items($per_page, $current_page, $search, $post_type_filter, $status_filter);

        $this->items = $data['items'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page),
        ));
    }

    private function get_content_items($per_page, $page, $search = '', $post_type = '', $status_filter = '') {
        global $wpdb;

        $allowed_types = Translio::get_translatable_post_types();

        // Ensure we have allowed types
        if (empty($allowed_types)) {
            return array('items' => array(), 'total' => 0);
        }

        // Include 'publish' and 'draft' for block theme parts and WooCommerce coupons
        $where = array("(p.post_status = 'publish' OR (p.post_type IN ('wp_navigation', 'wp_template_part', 'wp_block', 'shop_coupon') AND p.post_status IN ('publish', 'draft')))");
        $where_values = array();

        if (!empty($post_type) && in_array($post_type, $allowed_types)) {
            $where[] = "p.post_type = %s";
            $where_values[] = $post_type;
        } else {
            $placeholders = implode(',', array_fill(0, count($allowed_types), '%s'));
            $where[] = "p.post_type IN ({$placeholders})";
            $where_values = $allowed_types;
        }

        if (!empty($search)) {
            $where[] = "p.post_title LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_sql = implode(' AND ', $where);

        $orderby_raw = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : '';
        $orderby = !empty($orderby_raw) ? $orderby_raw : 'date';
        $order = isset($_REQUEST['order']) && strtoupper($_REQUEST['order']) === 'ASC' ? 'ASC' : 'DESC';

        $orderby_map = array(
            'title' => 'p.post_title',
            'type' => 'p.post_type',
            'date' => 'p.post_date',
        );

        $orderby_sql = isset($orderby_map[$orderby]) ? $orderby_map[$orderby] : 'p.post_date';

        $count_query = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$where_sql}";
        $total = $wpdb->get_var($wpdb->prepare($count_query, $where_values));

        $offset = ($page - 1) * $per_page;

        // If status filter is applied, get all posts (we'll filter and paginate in PHP)
        if (!empty($status_filter)) {
            $query = "SELECT p.ID, p.post_title, p.post_type, p.post_date, p.post_content, p.post_modified, p.post_excerpt
                      FROM {$wpdb->posts} p
                      WHERE {$where_sql}
                      ORDER BY {$orderby_sql} {$order}";
            $results = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $query = "SELECT p.ID, p.post_title, p.post_type, p.post_date, p.post_content, p.post_modified, p.post_excerpt
                      FROM {$wpdb->posts} p
                      WHERE {$where_sql}
                      ORDER BY {$orderby_sql} {$order}
                      LIMIT %d OFFSET %d";
            $query_values = array_merge($where_values, array($per_page, $offset));
            $results = $wpdb->get_results($wpdb->prepare($query, $query_values));
        }

        $items = array();

        // Get post IDs for batch translation check
        $post_ids = wp_list_pluck($results, 'ID');
        $translations_map = array();

        if (!empty($post_ids) && !empty($this->secondary_language)) {
            $translations_table = $wpdb->prefix . 'translio_translations';
            $ids_placeholder = implode(',', array_fill(0, count($post_ids), '%d'));

            // Get unique post types for the results
            $post_types = array_unique(wp_list_pluck($results, 'post_type'));
            $types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

            // Get translations for all posts in one query (match actual post types)
            $translations_query = $wpdb->prepare(
                "SELECT object_id, field_name, original_hash
                 FROM {$translations_table}
                 WHERE object_id IN ({$ids_placeholder})
                 AND object_type IN ({$types_placeholder})
                 AND language_code = %s
                 AND translated_content IS NOT NULL
                 AND translated_content != ''",
                array_merge($post_ids, $post_types, array($this->secondary_language))
            );

            $translations = $wpdb->get_results($translations_query);

            // Build map: post_id => array of translated fields
            foreach ($translations as $t) {
                if (!isset($translations_map[$t->object_id])) {
                    $translations_map[$t->object_id] = array();
                }
                $translations_map[$t->object_id][$t->field_name] = $t->original_hash;
            }
        }


        foreach ($results as $row) {
            $post_translations = isset($translations_map[$row->ID]) ? $translations_map[$row->ID] : array();
            $needs_update = false;
            $translation_status = 'none'; // none, partial, complete

            // Count total expected fields
            $total_fields = 0;
            $translated_fields = 0;

            // Title field
            if (!empty($row->post_title)) {
                $total_fields++;
                if (isset($post_translations['title'])) {
                    $translated_fields++;
                }
            }

            // Content field
            if (!empty($row->post_content)) {
                $total_fields++;
                if (isset($post_translations['content'])) {
                    $translated_fields++;
                }
            }

            // Excerpt field (if exists)
            if (!empty($row->post_excerpt)) {
                $total_fields++;
                if (isset($post_translations['excerpt'])) {
                    $translated_fields++;
                }
            }

            // Determine status
            if ($translated_fields === 0) {
                $translation_status = 'none';
            } elseif ($translated_fields >= $total_fields) {
                $translation_status = 'complete';
            } else {
                $translation_status = 'partial';
            }

            // Check if content has changed (compare hash)
            if ($translation_status !== 'none' && isset($post_translations['content']) && !empty($row->post_content)) {
                $current_hash = md5($row->post_content);
                $stored_hash = $post_translations['content'];
                if ($stored_hash !== $current_hash) {
                    $needs_update = true;
                }
            }

            $item = array(
                'ID' => $row->ID,
                'title' => $row->post_title,
                'type' => $row->post_type,
                'date' => $row->post_date,
                'translation_languages' => array(),
                'has_translation' => ($translation_status !== 'none'),
                'translation_status' => $translation_status,
                'translated_fields' => $translated_fields,
                'total_fields' => $total_fields,
                'needs_update' => $needs_update,
            );

            // Apply status filter
            if (!empty($status_filter)) {
                if ($status_filter === 'translated' && $translation_status !== 'complete') {
                    continue;
                }
                if ($status_filter === 'partial' && $translation_status !== 'partial') {
                    continue;
                }
                if ($status_filter === 'not_translated' && $translation_status !== 'none') {
                    continue;
                }
            }

            $items[] = $item;
        }

        // If status filter is applied, we need to recalculate total and apply pagination in PHP
        if (!empty($status_filter)) {
            $total = count($items);
            $offset = ($page - 1) * $per_page;
            $items = array_slice($items, $offset, $per_page);
        }

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    public function column_title($item) {
        $edit_url = admin_url(sprintf(
            'admin.php?page=translio-translate&post_id=%d&object_type=post',
            $item['ID']
        ));

        $actions = array(
            'translate' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_url),
                __('Translate', 'translio')
            ),
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(get_edit_post_link($item['ID'])),
                __('Edit original', 'translio')
            ),
            'view' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url(get_permalink($item['ID'])),
                __('View', 'translio')
            ),
        );

        return sprintf(
            '<a href="%s" class="row-title">%s</a>%s',
            esc_url($edit_url),
            esc_html($item['title']),
            $this->row_actions($actions)
        );
    }

    public function column_type($item) {
        $post_type_obj = get_post_type_object($item['type']);

        if ($post_type_obj) {
            return esc_html($post_type_obj->labels->singular_name);
        }

        // Fallback labels for block types
        $labels = array(
            'wp_navigation' => __('Navigation', 'translio'),
            'wp_template_part' => __('Template Part', 'translio'),
            'wp_block' => __('Block', 'translio'),
            'wpcf7_contact_form' => __('Contact Form', 'translio'),
        );

        return isset($labels[$item['type']]) ? $labels[$item['type']] : esc_html($item['type']);
    }

    public function column_languages($item) {
        $default_language = translio()->get_setting('default_language');
        $default_lang_name = isset($this->languages[$default_language]) ? $this->languages[$default_language]['name'] : strtoupper($default_language);
        $secondary_lang_name = isset($this->languages[$this->secondary_language]) ? $this->languages[$this->secondary_language]['name'] : strtoupper($this->secondary_language);

        $output = '<span class="translio-lang-icon translio-lang-default" title="' . esc_attr($default_lang_name) . '">' .
                  strtoupper($default_language) . '</span>';

        if (empty($this->secondary_language)) {
            return $output;
        }

        if ($item['has_translation']) {
            $class = $item['needs_update'] ? 'translio-lang-outdated' : 'translio-lang-translated';
            $title = $item['needs_update']
                ? $secondary_lang_name . ' (' . __('needs update', 'translio') . ')'
                : $secondary_lang_name;

            $output .= '<span class="translio-lang-icon ' . $class . '" title="' . esc_attr($title) . '">' .
                       strtoupper($this->secondary_language) . '</span>';
        } else {
            $output .= '<span class="translio-lang-icon translio-lang-missing" title="' .
                       esc_attr($secondary_lang_name . ' (' . __('not translated', 'translio') . ')') . '">' .
                       strtoupper($this->secondary_language) . '</span>';
        }

        return $output;
    }

    public function column_status($item) {
        $status = isset($item['translation_status']) ? $item['translation_status'] : 'none';
        $translated = isset($item['translated_fields']) ? $item['translated_fields'] : 0;
        $total = isset($item['total_fields']) ? $item['total_fields'] : 0;

        if ($status === 'none') {
            return '<span class="translio-status translio-status-missing">' . __('Not translated', 'translio') . '</span>';
        }

        if ($item['needs_update']) {
            return '<span class="translio-status translio-status-outdated">' . __('Needs update', 'translio') . '</span>';
        }

        if ($status === 'partial') {
            return '<span class="translio-status translio-status-partial">' .
                   sprintf(__('Partial (%d/%d)', 'translio'), $translated, $total) . '</span>';
        }

        return '<span class="translio-status translio-status-ok">' . __('Translated', 'translio') . '</span>';
    }

    public function column_date($item) {
        return mysql2date(get_option('date_format'), $item['date']);
    }

    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $allowed_types = Translio::get_translatable_post_types();
        $current_type = isset($_REQUEST['content_type']) ? sanitize_text_field($_REQUEST['content_type']) : '';

        // Build type labels
        $type_labels = array(
            'wp_navigation' => __('Navigation Menus', 'translio'),
            'wp_template_part' => __('Template Parts', 'translio'),
            'wp_block' => __('Reusable Blocks', 'translio'),
            'shop_coupon' => __('Coupons', 'translio'),
            'wpcf7_contact_form' => __('Contact Forms (CF7)', 'translio'),
        );

        $current_status = isset($_REQUEST['translation_status']) ? sanitize_text_field($_REQUEST['translation_status']) : '';

        ?>
        <div class="alignleft actions">
            <select name="content_type" id="filter-by-type">
                <option value=""><?php esc_html_e('All content types', 'translio'); ?></option>
                <?php foreach ($allowed_types as $type_name) :
                    $type_obj = get_post_type_object($type_name);
                    $label = $type_obj ? $type_obj->labels->name : (isset($type_labels[$type_name]) ? $type_labels[$type_name] : $type_name);
                ?>
                    <option value="<?php echo esc_attr($type_name); ?>" <?php selected($current_type, $type_name); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="translation_status" id="filter-by-status">
                <option value=""><?php esc_html_e('All statuses', 'translio'); ?></option>
                <option value="translated" <?php selected($current_status, 'translated'); ?>><?php esc_html_e('Translated', 'translio'); ?></option>
                <option value="partial" <?php selected($current_status, 'partial'); ?>><?php esc_html_e('Partial', 'translio'); ?></option>
                <option value="not_translated" <?php selected($current_status, 'not_translated'); ?>><?php esc_html_e('Not translated', 'translio'); ?></option>
            </select>

            <select name="per_page" id="filter-per-page">
                <option value="20" <?php selected($this->get_items_per_page('translio_per_page', 20), 20); ?>>20</option>
                <option value="50" <?php selected($this->get_items_per_page('translio_per_page', 20), 50); ?>>50</option>
            </select>

            <?php submit_button(__('Filter', 'translio'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    public function no_items() {
        esc_html_e('No content found.', 'translio');
    }
}
