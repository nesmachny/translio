<?php
/**
 * Translio Database Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_DB {

    /**
     * In-memory cache for translations within single request
     * @var array
     */
    private static $translation_cache = array();

    /**
     * Clear the translation cache (useful for testing)
     */
    public static function clear_cache() {
        self::$translation_cache = array();
    }

    /**
     * Get cache key for translation
     */
    private static function get_cache_key($object_id, $object_type, $field_name, $language_code) {
        return "{$object_id}:{$object_type}:{$field_name}:{$language_code}";
    }

    public static function get_translations_table() {
        global $wpdb;
        return $wpdb->prefix . 'translio_translations';
    }

    public static function get_languages_table() {
        global $wpdb;
        return $wpdb->prefix . 'translio_languages';
    }

    public static function get_strings_table() {
        global $wpdb;
        return $wpdb->prefix . 'translio_strings';
    }

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $translations_table = self::get_translations_table();
        $languages_table = self::get_languages_table();
        $strings_table = self::get_strings_table();

        $sql_translations = "CREATE TABLE {$translations_table} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            object_id BIGINT(20) UNSIGNED NOT NULL,
            object_type VARCHAR(50) NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            language_code VARCHAR(10) NOT NULL,
            original_content LONGTEXT,
            translated_content LONGTEXT,
            is_auto_translated TINYINT(1) DEFAULT 0,
            original_hash VARCHAR(32),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_object (object_id, object_type, language_code),
            INDEX idx_language (language_code),
            INDEX idx_hash (original_hash),
            UNIQUE KEY unique_translation (object_id, object_type, field_name, language_code)
        ) {$charset_collate};";

        $sql_languages = "CREATE TABLE {$languages_table} (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(10) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            native_name VARCHAR(100),
            is_default TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT(11) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) {$charset_collate};";

        // Table for scanned gettext strings (auto-discovered)
        $sql_strings = "CREATE TABLE {$strings_table} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            object_id BIGINT(20) UNSIGNED NOT NULL,
            string_text TEXT NOT NULL,
            domain VARCHAR(100) NOT NULL DEFAULT 'default',
            context VARCHAR(255) DEFAULT '',
            page_url VARCHAR(500) DEFAULT '',
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_string (object_id),
            INDEX idx_domain (domain),
            INDEX idx_last_seen (last_seen)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_translations);
        dbDelta($sql_languages);
        dbDelta($sql_strings);
    }

    public static function seed_languages() {
        global $wpdb;
        $table = self::get_languages_table();

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            return;
        }

        $languages = Translio::get_available_languages();
        $sort_order = 0;

        foreach ($languages as $code => $data) {
            $wpdb->insert(
                $table,
                array(
                    'code' => $code,
                    'name' => $data['name'],
                    'native_name' => $data['native'],
                    'is_default' => ($code === 'en') ? 1 : 0,
                    'is_active' => 1,
                    'sort_order' => $sort_order++,
                ),
                array('%s', '%s', '%s', '%d', '%d', '%d')
            );
        }

        update_option('translio_default_language', 'en');
    }

    public static function get_translation($object_id, $object_type, $field_name, $language_code) {
        // Check in-memory cache first
        $cache_key = self::get_cache_key($object_id, $object_type, $field_name, $language_code);
        if (isset(self::$translation_cache[$cache_key])) {
            return self::$translation_cache[$cache_key];
        }

        global $wpdb;
        $table = self::get_translations_table();

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_id = %d AND object_type = %s AND field_name = %s AND language_code = %s",
                $object_id, $object_type, $field_name, $language_code
            )
        );

        // Cache the result (even if null, to avoid repeated queries)
        self::$translation_cache[$cache_key] = $result;

        return $result;
    }

    public static function get_translations_for_object($object_id, $object_type, $language_code) {
        global $wpdb;
        $table = self::get_translations_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_id = %d AND object_type = %s AND language_code = %s",
                $object_id, $object_type, $language_code
            )
        );

        // Cache individual results
        foreach ($results as $row) {
            $cache_key = self::get_cache_key($row->object_id, $row->object_type, $row->field_name, $row->language_code);
            self::$translation_cache[$cache_key] = $row;
        }

        return $results;
    }

    /**
     * Batch load translations for multiple objects
     * Reduces N+1 queries to a single query
     *
     * @param array $items Array of items with 'object_id', 'object_type', 'field_name'
     * @param string $language_code Target language
     * @return array Keyed by "object_id:object_type:field_name"
     */
    public static function get_translations_batch($items, $language_code) {
        if (empty($items)) {
            return array();
        }

        global $wpdb;
        $table = self::get_translations_table();

        // Check which items are already cached
        $to_fetch = array();
        $results = array();

        foreach ($items as $item) {
            $cache_key = self::get_cache_key($item['object_id'], $item['object_type'], $item['field_name'], $language_code);

            if (isset(self::$translation_cache[$cache_key])) {
                $results[$cache_key] = self::$translation_cache[$cache_key];
            } else {
                $to_fetch[] = $item;
            }
        }

        // If all items were cached, return early
        if (empty($to_fetch)) {
            return $results;
        }

        // Build WHERE conditions for uncached items
        $conditions = array();
        foreach ($to_fetch as $item) {
            $conditions[] = $wpdb->prepare(
                "(object_id = %d AND object_type = %s AND field_name = %s)",
                $item['object_id'], $item['object_type'], $item['field_name']
            );
        }

        $where = implode(' OR ', $conditions);
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE ({$where}) AND language_code = %s",
            $language_code
        );

        $rows = $wpdb->get_results($query);

        // Index results and cache them
        foreach ($rows as $row) {
            $cache_key = self::get_cache_key($row->object_id, $row->object_type, $row->field_name, $row->language_code);
            self::$translation_cache[$cache_key] = $row;
            $results[$cache_key] = $row;
        }

        // Cache null for items that weren't found
        foreach ($to_fetch as $item) {
            $cache_key = self::get_cache_key($item['object_id'], $item['object_type'], $item['field_name'], $language_code);
            if (!isset(self::$translation_cache[$cache_key])) {
                self::$translation_cache[$cache_key] = null;
            }
        }

        return $results;
    }

    /**
     * Preload translations for menu items (optimization)
     *
     * @param array $menu_item_ids Array of menu item IDs
     * @param string $language_code Target language
     */
    public static function preload_menu_translations($menu_item_ids, $language_code) {
        if (empty($menu_item_ids)) {
            return;
        }

        global $wpdb;
        $table = self::get_translations_table();

        $placeholders = implode(',', array_fill(0, count($menu_item_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE object_id IN ({$placeholders})
             AND object_type IN ('menu_item', 'post')
             AND language_code = %s",
            array_merge($menu_item_ids, array($language_code))
        );

        $rows = $wpdb->get_results($query);

        // Cache all results
        foreach ($rows as $row) {
            $cache_key = self::get_cache_key($row->object_id, $row->object_type, $row->field_name, $row->language_code);
            self::$translation_cache[$cache_key] = $row;
        }
    }

    public static function save_translation($object_id, $object_type, $field_name, $language_code, $original_content, $translated_content, $is_auto = false) {
        global $wpdb;
        $table = self::get_translations_table();

        $original_hash = md5($original_content);

        $existing = self::get_translation($object_id, $object_type, $field_name, $language_code);

        // Invalidate cache for this translation
        $cache_key = self::get_cache_key($object_id, $object_type, $field_name, $language_code);
        unset(self::$translation_cache[$cache_key]);

        if ($existing) {
            return $wpdb->update(
                $table,
                array(
                    'original_content' => $original_content,
                    'translated_content' => $translated_content,
                    'is_auto_translated' => $is_auto ? 1 : 0,
                    'original_hash' => $original_hash,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%d', '%s', '%s'),
                array('%d')
            );
        }

        return $wpdb->insert(
            $table,
            array(
                'object_id' => $object_id,
                'object_type' => $object_type,
                'field_name' => $field_name,
                'language_code' => $language_code,
                'original_content' => $original_content,
                'translated_content' => $translated_content,
                'is_auto_translated' => $is_auto ? 1 : 0,
                'original_hash' => $original_hash,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }

    public static function delete_translation($id) {
        global $wpdb;
        $table = self::get_translations_table();

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    public static function delete_translations_for_object($object_id, $object_type) {
        global $wpdb;
        $table = self::get_translations_table();

        return $wpdb->delete(
            $table,
            array('object_id' => $object_id, 'object_type' => $object_type),
            array('%d', '%s')
        );
    }

    public static function has_translation($object_id, $object_type, $language_code) {
        global $wpdb;
        $table = self::get_translations_table();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE object_id = %d AND object_type = %s AND language_code = %s",
                $object_id, $object_type, $language_code
            )
        );

        return $count > 0;
    }

    public static function get_translation_languages_for_object($object_id, $object_type) {
        global $wpdb;
        $table = self::get_translations_table();

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT language_code FROM {$table} WHERE object_id = %d AND object_type = %s",
                $object_id, $object_type
            )
        );
    }

    public static function needs_update($object_id, $object_type, $language_code, $current_content_hash) {
        global $wpdb;
        $table = self::get_translations_table();

        $stored_hash = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT original_hash FROM {$table} WHERE object_id = %d AND object_type = %s AND field_name = 'content' AND language_code = %s",
                $object_id, $object_type, $language_code
            )
        );

        if (!$stored_hash) {
            return true;
        }

        return $stored_hash !== $current_content_hash;
    }

    public static function get_untranslated_posts($language_code, $limit = 50) {
        global $wpdb;
        $table = self::get_translations_table();

        $post_types = Translio::get_translatable_post_types();
        if (empty($post_types)) {
            return array();
        }
        $post_types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

        $query = "SELECT p.ID, p.post_title, p.post_type
                  FROM {$wpdb->posts} p
                  LEFT JOIN {$table} t ON p.ID = t.object_id AND t.object_type = 'post' AND t.language_code = %s
                  WHERE (p.post_status = 'publish' OR (p.post_type IN ('wp_navigation', 'wp_template_part', 'wp_block') AND p.post_status IN ('publish', 'draft')))
                  AND p.post_type IN ({$post_types_placeholder})
                  AND t.id IS NULL
                  LIMIT %d";

        $args = array_merge(array($language_code), $post_types, array($limit));

        return $wpdb->get_results($wpdb->prepare($query, $args));
    }

    public static function count_untranslated_posts($language_code) {
        global $wpdb;
        $table = self::get_translations_table();

        $post_types = Translio::get_translatable_post_types();
        if (empty($post_types)) {
            return 0;
        }
        $post_types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

        $query = "SELECT COUNT(*)
                  FROM {$wpdb->posts} p
                  LEFT JOIN {$table} t ON p.ID = t.object_id AND t.object_type = 'post' AND t.language_code = %s
                  WHERE (p.post_status = 'publish' OR (p.post_type IN ('wp_navigation', 'wp_template_part', 'wp_block') AND p.post_status IN ('publish', 'draft')))
                  AND p.post_type IN ({$post_types_placeholder})
                  AND t.id IS NULL";

        $args = array_merge(array($language_code), $post_types);

        return (int) $wpdb->get_var($wpdb->prepare($query, $args));
    }

    public static function get_posts_needing_update($language_code, $limit = 50) {
        global $wpdb;
        $table = self::get_translations_table();

        $post_types = Translio::get_translatable_post_types();
        if (empty($post_types)) {
            return array();
        }
        $post_types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

        $query = "SELECT p.ID, p.post_title, p.post_type, t.original_hash, MD5(p.post_content) as current_hash
                  FROM {$wpdb->posts} p
                  INNER JOIN {$table} t ON p.ID = t.object_id AND t.object_type = 'post' AND t.field_name = 'content' AND t.language_code = %s
                  WHERE (p.post_status = 'publish' OR (p.post_type IN ('wp_navigation', 'wp_template_part', 'wp_block') AND p.post_status IN ('publish', 'draft')))
                  AND p.post_type IN ({$post_types_placeholder})
                  AND t.original_hash != MD5(p.post_content)
                  LIMIT %d";

        $args = array_merge(array($language_code), $post_types, array($limit));

        return $wpdb->get_results($wpdb->prepare($query, $args));
    }

    public static function count_posts_needing_update($language_code) {
        global $wpdb;
        $table = self::get_translations_table();

        $post_types = Translio::get_translatable_post_types();
        if (empty($post_types)) {
            return 0;
        }
        $post_types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

        $query = "SELECT COUNT(*)
                  FROM {$wpdb->posts} p
                  INNER JOIN {$table} t ON p.ID = t.object_id AND t.object_type = 'post' AND t.field_name = 'content' AND t.language_code = %s
                  WHERE (p.post_status = 'publish' OR (p.post_type IN ('wp_navigation', 'wp_template_part', 'wp_block') AND p.post_status IN ('publish', 'draft')))
                  AND p.post_type IN ({$post_types_placeholder})
                  AND t.original_hash != MD5(p.post_content)";

        $args = array_merge(array($language_code), $post_types);

        return (int) $wpdb->get_var($wpdb->prepare($query, $args));
    }

    public static function get_translation_stats($language_code) {
        global $wpdb;
        $table = self::get_translations_table();

        $post_types = Translio::get_translatable_post_types();
        $post_types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

        $total_query = "SELECT COUNT(*) FROM {$wpdb->posts}
                        WHERE (post_status = 'publish' OR (post_type IN ('wp_navigation', 'wp_template_part', 'wp_block') AND post_status IN ('publish', 'draft')))
                        AND post_type IN ({$post_types_placeholder})";
        $total = $wpdb->get_var($wpdb->prepare($total_query, $post_types));

        $translated_query = "SELECT COUNT(DISTINCT object_id) FROM {$table} WHERE language_code = %s AND object_type = 'post'";
        $translated = $wpdb->get_var($wpdb->prepare($translated_query, $language_code));

        return array(
            'total' => (int) $total,
            'translated' => (int) $translated,
            'percentage' => $total > 0 ? round(($translated / $total) * 100) : 0,
        );
    }

    public static function drop_tables() {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS " . self::get_translations_table());
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_languages_table());
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_strings_table());
    }

    /**
     * Record a scanned gettext string to database
     */
    public static function record_scanned_string($object_id, $text, $domain, $context = '') {
        global $wpdb;
        $table = self::get_strings_table();

        // Use INSERT IGNORE to skip if already exists (by object_id)
        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (object_id, string_text, domain, context, page_url, first_seen, last_seen)
                VALUES (%d, %s, %s, %s, %s, NOW(), NOW())",
                $object_id,
                $text,
                $domain,
                $context,
                isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], 0, 500) : ''
            )
        );

        // Update last_seen if already exists
        if ($wpdb->rows_affected === 0) {
            $wpdb->update(
                $table,
                array('last_seen' => current_time('mysql')),
                array('object_id' => $object_id),
                array('%s'),
                array('%d')
            );
        }
    }

    /**
     * Get all scanned strings with optional filters
     */
    public static function get_scanned_strings($args = array()) {
        global $wpdb;
        $table = self::get_strings_table();
        $translations_table = self::get_translations_table();

        $defaults = array(
            'domain' => '',
            'search' => '',
            'translated' => '', // 'yes', 'no', or ''
            'orderby' => 'last_seen',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0,
            'language_code' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $join = '';

        if (!empty($args['domain'])) {
            $where[] = $wpdb->prepare("s.domain = %s", $args['domain']);
        }

        if (!empty($args['search'])) {
            $where[] = $wpdb->prepare("s.string_text LIKE %s", '%' . $wpdb->esc_like($args['search']) . '%');
        }

        // Join with translations table to check translation status
        if (!empty($args['language_code'])) {
            $join = $wpdb->prepare(
                "LEFT JOIN {$translations_table} t ON s.object_id = t.object_id AND t.object_type = 'string' AND t.field_name = 'text' AND t.language_code = %s",
                $args['language_code']
            );

            if ($args['translated'] === 'yes') {
                $where[] = "t.translated_content IS NOT NULL AND t.translated_content != ''";
            } elseif ($args['translated'] === 'no') {
                $where[] = "(t.translated_content IS NULL OR t.translated_content = '')";
            }
        }

        $where_sql = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'last_seen DESC';

        $query = "SELECT s.*, " . (!empty($args['language_code']) ? "t.translated_content" : "NULL as translated_content") . "
                  FROM {$table} s
                  {$join}
                  WHERE {$where_sql}
                  ORDER BY {$orderby}
                  LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($query, $args['limit'], $args['offset']));
    }

    /**
     * Get count of scanned strings
     */
    public static function get_scanned_strings_count($args = array()) {
        global $wpdb;
        $table = self::get_strings_table();
        $translations_table = self::get_translations_table();

        $defaults = array(
            'domain' => '',
            'search' => '',
            'translated' => '',
            'language_code' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $join = '';

        if (!empty($args['domain'])) {
            $where[] = $wpdb->prepare("s.domain = %s", $args['domain']);
        }

        if (!empty($args['search'])) {
            $where[] = $wpdb->prepare("s.string_text LIKE %s", '%' . $wpdb->esc_like($args['search']) . '%');
        }

        if (!empty($args['language_code'])) {
            $join = $wpdb->prepare(
                "LEFT JOIN {$translations_table} t ON s.object_id = t.object_id AND t.object_type = 'string' AND t.field_name = 'text' AND t.language_code = %s",
                $args['language_code']
            );

            if ($args['translated'] === 'yes') {
                $where[] = "t.translated_content IS NOT NULL AND t.translated_content != ''";
            } elseif ($args['translated'] === 'no') {
                $where[] = "(t.translated_content IS NULL OR t.translated_content = '')";
            }
        }

        $where_sql = implode(' AND ', $where);

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} s {$join} WHERE {$where_sql}");
    }

    /**
     * Get unique domains from scanned strings
     */
    public static function get_scanned_domains() {
        global $wpdb;
        $table = self::get_strings_table();

        return $wpdb->get_col("SELECT DISTINCT domain FROM {$table} ORDER BY domain ASC");
    }

    /**
     * Delete scanned string
     */
    public static function delete_scanned_string($object_id) {
        global $wpdb;
        $table = self::get_strings_table();

        return $wpdb->delete($table, array('object_id' => $object_id), array('%d'));
    }

    /**
     * Clear all scanned strings
     */
    public static function clear_scanned_strings() {
        global $wpdb;
        $table = self::get_strings_table();

        return $wpdb->query("TRUNCATE TABLE {$table}");
    }

    /**
     * ============================================
     * TRANSLATION MEMORY (TM) METHODS
     * ============================================
     */

    /**
     * Find exact match in translation memory
     *
     * @param string $text Original text to find
     * @param string $language_code Target language
     * @return object|null Translation row or null
     */
    public static function tm_find_exact($text, $language_code) {
        global $wpdb;
        $table = self::get_translations_table();

        $hash = md5($text);

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE original_hash = %s
                AND language_code = %s
                AND translated_content IS NOT NULL
                AND translated_content != ''
                LIMIT 1",
                $hash, $language_code
            )
        );
    }

    /**
     * In-memory cache for TM fuzzy results within single request
     */
    private static $tm_fuzzy_cache = array();

    /**
     * Find fuzzy matches in translation memory
     * Optimized version with caching and early exit
     *
     * @param string $text Original text to find similar
     * @param string $language_code Target language
     * @param int $min_similarity Minimum similarity percentage (0-100)
     * @param int $limit Maximum results to return
     * @return array Array of matches with similarity scores
     */
    public static function tm_find_fuzzy($text, $language_code, $min_similarity = 70, $limit = 5) {
        // Normalize text for comparison
        $normalized_text = self::tm_normalize_text($text);
        $text_length = mb_strlen($normalized_text);

        // Skip very short or very long texts for fuzzy matching
        if ($text_length < 10 || $text_length > 5000) {
            return array();
        }

        // Check cache first
        $cache_key = md5($normalized_text . ':' . $language_code . ':' . $min_similarity);
        if (isset(self::$tm_fuzzy_cache[$cache_key])) {
            return array_slice(self::$tm_fuzzy_cache[$cache_key], 0, $limit);
        }

        global $wpdb;
        $table = self::get_translations_table();

        // Get candidates with similar length (±30%) - reduced limit for performance
        $min_length = (int) ($text_length * 0.7);
        $max_length = (int) ($text_length * 1.3);

        // Extract first few significant words for better filtering
        $words = array_slice(array_filter(explode(' ', $normalized_text)), 0, 3);
        $word_conditions = '';
        if (!empty($words) && count($words) >= 2) {
            // Add LIKE conditions for first words to reduce candidates
            $like_conditions = array();
            foreach ($words as $word) {
                if (mb_strlen($word) >= 4) {
                    $like_conditions[] = $wpdb->prepare(
                        "LOWER(original_content) LIKE %s",
                        '%' . $wpdb->esc_like($word) . '%'
                    );
                }
            }
            if (!empty($like_conditions)) {
                $word_conditions = ' AND (' . implode(' OR ', $like_conditions) . ')';
            }
        }

        $candidates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, original_content, translated_content, object_type, field_name
                FROM {$table}
                WHERE language_code = %s
                AND translated_content IS NOT NULL
                AND translated_content != ''
                AND CHAR_LENGTH(original_content) BETWEEN %d AND %d
                {$word_conditions}
                ORDER BY ABS(CHAR_LENGTH(original_content) - %d) ASC
                LIMIT 30",
                $language_code, $min_length, $max_length, $text_length
            )
        );

        if (empty($candidates)) {
            self::$tm_fuzzy_cache[$cache_key] = array();
            return array();
        }

        $matches = array();

        foreach ($candidates as $candidate) {
            $candidate_normalized = self::tm_normalize_text($candidate->original_content);

            // Quick pre-check: if lengths differ too much, skip
            $len_diff = abs(mb_strlen($candidate_normalized) - $text_length);
            if ($len_diff > $text_length * 0.4) {
                continue;
            }

            $similarity = self::tm_calculate_similarity($normalized_text, $candidate_normalized);

            if ($similarity >= $min_similarity) {
                $matches[] = array(
                    'id' => $candidate->id,
                    'original' => $candidate->original_content,
                    'translated' => $candidate->translated_content,
                    'similarity' => $similarity,
                    'object_type' => $candidate->object_type,
                    'field_name' => $candidate->field_name,
                );

                // Early exit if we found a very good match (95%+)
                if ($similarity >= 95 && $limit === 1) {
                    break;
                }
            }
        }

        // Sort by similarity (highest first)
        usort($matches, function($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });

        // Cache the results
        self::$tm_fuzzy_cache[$cache_key] = $matches;

        return array_slice($matches, 0, $limit);
    }

    /**
     * Clear TM fuzzy cache
     */
    public static function clear_tm_cache() {
        self::$tm_fuzzy_cache = array();
    }

    /**
     * Get translation from memory (exact match first, then fuzzy)
     *
     * @param string $text Original text
     * @param string $language_code Target language
     * @param int $min_similarity Minimum similarity for fuzzy match (100 = exact only, skip fuzzy)
     * @return array|null ['translation' => string, 'similarity' => int, 'source' => 'exact'|'fuzzy']
     */
    public static function tm_get_translation($text, $language_code, $min_similarity = 100) {
        // Try exact match first
        $exact = self::tm_find_exact($text, $language_code);
        if ($exact) {
            return array(
                'translation' => $exact->translated_content,
                'similarity' => 100,
                'source' => 'exact',
                'original' => $exact->original_content,
            );
        }

        // Skip fuzzy matching if threshold is 100 (exact only mode)
        if ($min_similarity >= 100) {
            return null;
        }

        // Try fuzzy match
        $fuzzy = self::tm_find_fuzzy($text, $language_code, $min_similarity, 1);
        if (!empty($fuzzy)) {
            return array(
                'translation' => $fuzzy[0]['translated'],
                'similarity' => $fuzzy[0]['similarity'],
                'source' => 'fuzzy',
                'original' => $fuzzy[0]['original'],
            );
        }

        return null;
    }

    /**
     * Normalize text for comparison (remove extra whitespace, lowercase)
     */
    private static function tm_normalize_text($text) {
        // Remove HTML tags
        $text = wp_strip_all_tags($text);
        // Convert to lowercase
        $text = mb_strtolower($text);
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Calculate similarity between two texts (0-100)
     * Uses combination of similar_text and word-based comparison
     */
    private static function tm_calculate_similarity($text1, $text2) {
        // Quick length check
        $len1 = mb_strlen($text1);
        $len2 = mb_strlen($text2);

        if ($len1 === 0 || $len2 === 0) {
            return 0;
        }

        // For short texts, use character-based similarity
        if ($len1 < 100 && $len2 < 100) {
            similar_text($text1, $text2, $percent);
            return (int) $percent;
        }

        // For longer texts, use word-based Jaccard similarity
        $words1 = array_filter(explode(' ', $text1));
        $words2 = array_filter(explode(' ', $text2));

        if (empty($words1) || empty($words2)) {
            return 0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        $jaccard = count($intersection) / count($union);

        // Also consider word order with similar_text on truncated versions
        $truncated1 = mb_substr($text1, 0, 500);
        $truncated2 = mb_substr($text2, 0, 500);
        similar_text($truncated1, $truncated2, $char_percent);

        // Weighted average: 60% word overlap, 40% character similarity
        return (int) (($jaccard * 100 * 0.6) + ($char_percent * 0.4));
    }

    /**
     * Get translation memory statistics
     */
    public static function tm_get_stats($language_code) {
        global $wpdb;
        $table = self::get_translations_table();

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_entries,
                    COUNT(DISTINCT original_hash) as unique_originals,
                    SUM(CHAR_LENGTH(original_content)) as total_chars
                FROM {$table}
                WHERE language_code = %s
                AND translated_content IS NOT NULL
                AND translated_content != ''",
                $language_code
            )
        );

        return $stats;
    }
}
