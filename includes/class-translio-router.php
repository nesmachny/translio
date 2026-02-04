<?php
/**
 * Translio Router Class
 * Handles URL routing for translated content using WordPress Rewrite API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Router {

    /**
     * Array of secondary (translation) languages
     * @var array
     */
    private $secondary_languages = array();

    public function __construct() {
        $this->secondary_languages = translio()->get_secondary_languages();

        if (empty($this->secondary_languages)) {
            return;
        }

        // Register rewrite rules early
        add_action('init', array($this, 'add_rewrite_rules'), 1);
        add_action('init', array($this, 'add_rewrite_tags'), 1);

        // Query vars
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Resolve translio_slug to post or page
        add_action('pre_get_posts', array($this, 'resolve_lingua_slug'), 1);

        // Set language on template_redirect (after query is parsed)
        add_action('template_redirect', array($this, 'handle_language_redirect'), 1);

        // Filter permalinks to add language prefix
        add_filter('post_link', array($this, 'filter_post_link'), 10, 2);
        add_filter('page_link', array($this, 'filter_page_link'), 10, 2);
        add_filter('post_type_link', array($this, 'filter_post_type_link'), 10, 2);

        // SEO
        add_action('wp_head', array($this, 'output_hreflang_tags'), 5);
    }

    /**
     * Add all rewrite rules for language prefixes
     * These rules map /{lang}/... URLs to standard WordPress query vars
     */
    public function add_rewrite_rules() {
        if (empty($this->secondary_languages)) {
            return;
        }

        // Add rewrite rules for each secondary language
        foreach ($this->secondary_languages as $lang) {
            $this->add_rewrite_rules_for_language($lang);
        }
    }

    /**
     * Add rewrite rules for a specific language
     *
     * @param string $lang Language code
     */
    private function add_rewrite_rules_for_language($lang) {
        if (empty($lang)) {
            return;
        }

        // Collect reserved slugs (CPT slugs that should not be treated as pages)
        $reserved_slugs = array('category', 'tag', 'author', 'search', 'feed');

        // =====================
        // 1. CUSTOM POST TYPES
        // =====================
        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
        foreach ($post_types as $post_type) {
            if (!empty($post_type->rewrite['slug'])) {
                $slug = trim($post_type->rewrite['slug'], '/');
                $reserved_slugs[] = $slug;

                // /{lang}/product/{slug}/
                add_rewrite_rule(
                    '^' . preg_quote($lang, '/') . '/' . preg_quote($slug, '/') . '/([^/]+)/?$',
                    'index.php?translio_lang=' . $lang . '&' . $post_type->name . '=$matches[1]',
                    'top'
                );
            }
        }

        // =====================
        // 2. WOOCOMMERCE SPECIFIC
        // =====================
        if (class_exists('WooCommerce')) {
            // Shop page
            $shop_page_id = wc_get_page_id('shop');
            if ($shop_page_id > 0) {
                $shop_page = get_post($shop_page_id);
                if ($shop_page) {
                    $reserved_slugs[] = $shop_page->post_name;
                    add_rewrite_rule(
                        '^' . preg_quote($lang, '/') . '/' . preg_quote($shop_page->post_name, '/') . '/?$',
                        'index.php?translio_lang=' . $lang . '&page_id=' . $shop_page_id,
                        'top'
                    );
                }
            }

            // Cart, Checkout, My Account pages
            foreach (array('cart', 'checkout', 'myaccount') as $wc_page) {
                $page_id = wc_get_page_id($wc_page);
                if ($page_id > 0) {
                    $page = get_post($page_id);
                    if ($page) {
                        $reserved_slugs[] = $page->post_name;
                        add_rewrite_rule(
                            '^' . preg_quote($lang, '/') . '/' . preg_quote($page->post_name, '/') . '/?$',
                            'index.php?translio_lang=' . $lang . '&page_id=' . $page_id,
                            'top'
                        );
                    }
                }
            }

            // Product category and tag
            $reserved_slugs[] = 'product-category';
            $reserved_slugs[] = 'product-tag';

            add_rewrite_rule(
                '^' . preg_quote($lang, '/') . '/product-category/(.+?)/?$',
                'index.php?translio_lang=' . $lang . '&product_cat=$matches[1]',
                'top'
            );
            add_rewrite_rule(
                '^' . preg_quote($lang, '/') . '/product-tag/(.+?)/?$',
                'index.php?translio_lang=' . $lang . '&product_tag=$matches[1]',
                'top'
            );
        }

        // =====================
        // 3. WORDPRESS ARCHIVES
        // =====================

        // Homepage: /{lang}/
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/?$',
            'index.php?translio_lang=' . $lang,
            'top'
        );

        // Date archives
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$',
            'index.php?translio_lang=' . $lang . '&year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
            'top'
        );
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/([0-9]{4})/([0-9]{1,2})/?$',
            'index.php?translio_lang=' . $lang . '&year=$matches[1]&monthnum=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/([0-9]{4})/?$',
            'index.php?translio_lang=' . $lang . '&year=$matches[1]',
            'top'
        );

        // Category: /{lang}/category/{slug}/
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/category/(.+?)/?$',
            'index.php?translio_lang=' . $lang . '&category_name=$matches[1]',
            'top'
        );

        // Tag: /{lang}/tag/{slug}/
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/tag/(.+?)/?$',
            'index.php?translio_lang=' . $lang . '&tag=$matches[1]',
            'top'
        );

        // Author: /{lang}/author/{name}/
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/author/([^/]+)/?$',
            'index.php?translio_lang=' . $lang . '&author_name=$matches[1]',
            'top'
        );

        // Search: /{lang}/search/{query}/
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/search/(.+)/?$',
            'index.php?translio_lang=' . $lang . '&s=$matches[1]',
            'top'
        );

        // Feed: /{lang}/feed/
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/feed/?$',
            'index.php?translio_lang=' . $lang . '&feed=feed',
            'top'
        );

        // =====================
        // 4. GENERIC PAGE/POST RULES (CATCH-ALL)
        // =====================
        // These must be last and use negative lookahead to exclude reserved slugs

        // Build regex pattern to exclude reserved slugs
        $excluded = implode('|', array_map('preg_quote', array_unique($reserved_slugs)));

        // Single post/page: /{lang}/{slug}/ (exclude reserved slugs)
        // Use translio_slug custom var - we'll resolve post vs page in pre_get_posts
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/(?!' . $excluded . ')([^/]+)/?$',
            'index.php?translio_lang=' . $lang . '&translio_slug=$matches[1]',
            'top'
        );

        // Nested page: /{lang}/parent/child/ (exclude reserved first segment)
        // Hierarchical pages use pagename
        add_rewrite_rule(
            '^' . preg_quote($lang, '/') . '/(?!' . $excluded . ')([^/]+)/(.+?)/?$',
            'index.php?translio_lang=' . $lang . '&pagename=$matches[1]/$matches[2]',
            'top'
        );
    }

    public function add_rewrite_tags() {
        add_rewrite_tag('%translio_lang%', '([a-z]{2})');
        add_rewrite_tag('%translio_slug%', '([^/]+)');
    }

    public function add_query_vars($vars) {
        $vars[] = 'translio_lang';
        $vars[] = 'translio_slug';
        return $vars;
    }

    /**
     * Resolve translio_slug to either a post (name) or page (pagename)
     * This allows /{lang}/{slug}/ to work for both posts and pages
     */
    public function resolve_lingua_slug($query) {
        if (!$query->is_main_query()) {
            return;
        }

        $slug = $query->get('translio_slug');

        if (empty($slug)) {
            return;
        }

        // First try to find a page with this slug
        $page = get_page_by_path($slug);
        if ($page) {
            $query->set('page_id', $page->ID);
            $query->set('translio_slug', '');
            $query->is_page = true;
            $query->is_singular = true;
            $query->is_home = false;
            $query->is_archive = false;
            return;
        }

        // Then try to find a post with this slug
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish' LIMIT 1",
            $slug
        ));

        if ($post_id) {
            $query->set('p', $post_id);
            $query->set('post_type', 'post');
            $query->set('translio_slug', '');
            $query->is_single = true;
            $query->is_singular = true;
            $query->is_home = false;
            $query->is_archive = false;
            return;
        }
        // If nothing found, it will 404
    }

    /**
     * Set current language when viewing translated page
     */
    public function handle_language_redirect() {
        $lang = get_query_var('translio_lang');

        if (!empty($lang) && in_array($lang, $this->secondary_languages, true)) {
            translio()->set_current_language($lang);
            $url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
            Translio_Logger::log_router($url, $lang, 'language_set');
        }
    }

    /**
     * Filter post permalinks to add language prefix
     */
    public function filter_post_link($permalink, $post) {
        if (is_admin() || !$this->should_add_language_prefix()) {
            return $permalink;
        }

        return $this->add_language_prefix($permalink);
    }

    /**
     * Filter page permalinks to add language prefix
     */
    public function filter_page_link($permalink, $post_id) {
        if (is_admin() || !$this->should_add_language_prefix()) {
            return $permalink;
        }

        return $this->add_language_prefix($permalink);
    }

    /**
     * Filter custom post type permalinks to add language prefix
     */
    public function filter_post_type_link($permalink, $post) {
        if (is_admin() || !$this->should_add_language_prefix()) {
            return $permalink;
        }

        return $this->add_language_prefix($permalink);
    }

    /**
     * Check if we should add language prefix to URLs
     */
    private function should_add_language_prefix() {
        $current_lang = translio()->get_current_language();
        $default_lang = translio()->get_default_language();

        return $current_lang !== $default_lang && in_array($current_lang, $this->secondary_languages, true);
    }

    /**
     * Add language prefix to URL
     */
    private function add_language_prefix($url) {
        $url = (string) $url;
        if (empty($url)) {
            return $url;
        }

        $current_lang = translio()->get_current_language();
        $home_url = home_url('/');
        $relative = str_replace($home_url, '', $url);

        // Don't double-add prefix - check all secondary languages
        foreach ($this->secondary_languages as $lang) {
            if (strpos($relative, $lang . '/') === 0) {
                return $url;
            }
        }

        return $home_url . $current_lang . '/' . ltrim($relative, '/');
    }

    /**
     * Output hreflang tags for SEO
     * Outputs tags for default language and all secondary languages with translations
     */
    public function output_hreflang_tags() {
        if (is_admin() || empty($this->secondary_languages)) {
            return;
        }

        $default_lang = translio()->get_default_language();
        $home_url = home_url('/');

        if (is_singular()) {
            global $post;
            if (!$post) return;

            $default_url = get_permalink($post->ID);
            $relative = str_replace($home_url, '', $default_url);

            // Default language
            echo '<link rel="alternate" hreflang="' . esc_attr($default_lang) . '" href="' . esc_url($default_url) . '" />' . "\n";

            // Each secondary language with translation
            foreach ($this->secondary_languages as $lang) {
                $has_translation = Translio_DB::has_translation($post->ID, 'post', $lang);
                if ($has_translation) {
                    $translated_url = $home_url . $lang . '/' . ltrim($relative, '/');
                    echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($translated_url) . '" />' . "\n";
                }
            }

            // x-default
            echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($default_url) . '" />' . "\n";

        } elseif (is_home() || is_front_page()) {
            // Default language
            echo '<link rel="alternate" hreflang="' . esc_attr($default_lang) . '" href="' . esc_url($home_url) . '" />' . "\n";

            // All secondary languages (homepage always has translation)
            foreach ($this->secondary_languages as $lang) {
                $translated_url = $home_url . $lang . '/';
                echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($translated_url) . '" />' . "\n";
            }

            // x-default
            echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($home_url) . '" />' . "\n";
        }
    }

    /**
     * Get URL for specific language
     *
     * @param string $language_code Target language code
     * @param int|null $post_id Post ID (optional, for post-specific URLs)
     * @return string Language-specific URL
     */
    public static function get_language_url($language_code, $post_id = null) {
        $default_lang = translio()->get_default_language();
        $secondary_languages = translio()->get_secondary_languages();
        $home_url = home_url('/');

        // Validate language code - must be default or one of secondary languages
        if ($language_code !== $default_lang && !in_array($language_code, $secondary_languages, true)) {
            return $home_url;
        }

        if ($post_id) {
            $permalink = get_permalink($post_id);
            if (!$permalink) {
                return $home_url;
            }

            // Remove any existing language prefix from permalink
            $relative = str_replace($home_url, '', $permalink);
            foreach ($secondary_languages as $lang) {
                if (strpos($relative, $lang . '/') === 0) {
                    $relative = substr($relative, strlen($lang) + 1);
                    break;
                }
            }

            if ($language_code === $default_lang) {
                return $home_url . ltrim($relative, '/');
            }

            return $home_url . $language_code . '/' . ltrim($relative, '/');
        }

        if ($language_code === $default_lang) {
            return $home_url;
        }

        return $home_url . $language_code . '/';
    }

    /**
     * Flush rewrite rules - call on plugin activation and settings change
     */
    public static function flush_rules() {
        // Re-add rules before flushing
        $instance = new self();

        // Only add rules if there are secondary languages configured
        if (!empty($instance->secondary_languages)) {
            $instance->add_rewrite_rules();
            $instance->add_rewrite_tags();
        }

        flush_rewrite_rules();
    }
}
