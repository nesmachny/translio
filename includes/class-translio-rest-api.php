<?php
/**
 * Translio REST API Class
 *
 * Provides REST API endpoints for headless CMS integration.
 * Allows external applications (Next.js, Gatsby, etc.) to fetch
 * translated content via WordPress REST API.
 *
 * Endpoints:
 * - GET /wp-json/translio/v1/page/{slug}?lang=ru
 * - GET /wp-json/translio/v1/post/{slug}?lang=ru
 * - GET /wp-json/translio/v1/posts?lang=ru&per_page=10&page=1
 * - GET /wp-json/translio/v1/languages
 *
 * @package Translio
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_REST_API {

    /**
     * REST API namespace
     */
    const API_NAMESPACE = 'translio/v1';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - register REST API routes
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // GET /wp-json/translio/v1/license-info (for testing)
        register_rest_route(self::API_NAMESPACE, '/license-info', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_license_info'),
            'permission_callback' => '__return_true',
        ));

        // POST /wp-json/translio/v1/activate-test (for testing)
        register_rest_route(self::API_NAMESPACE, '/activate-test', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'activate_test'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'key' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
            ),
        ));

        // POST /wp-json/translio/v1/translate-test (for testing)
        register_rest_route(self::API_NAMESPACE, '/translate-test', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'translate_test'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'text' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
                'target' => array(
                    'required' => true,
                    'type'     => 'string',
                    'default'  => 'ru',
                ),
            ),
        ));

        // GET /wp-json/translio/v1/page/{slug}?lang=ru
        register_rest_route(self::API_NAMESPACE, '/page/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_page'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'slug' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_title',
                ),
                'lang' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'en',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // GET /wp-json/translio/v1/post/{slug}?lang=ru
        register_rest_route(self::API_NAMESPACE, '/post/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_post'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'slug' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_title',
                ),
                'lang' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'en',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // GET /wp-json/translio/v1/posts?lang=ru&per_page=10&page=1
        register_rest_route(self::API_NAMESPACE, '/posts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_posts'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'lang' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'en',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'per_page' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ),
                'page' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ),
                'tag' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // GET /wp-json/translio/v1/languages
        register_rest_route(self::API_NAMESPACE, '/languages', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_languages'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Get license info (for testing)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_license_info($request) {
        $license = translio_license();
        $api = Translio_API::instance();

        $info = $license->get_license_info();
        $mode = $api->get_mode_info();

        return rest_ensure_response(array(
            'version' => TRANSLIO_VERSION,
            'license' => $info,
            'api_mode' => $mode,
        ));
    }

    /**
     * Activate license (for testing only - remove in production)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function activate_test($request) {
        $key = $request->get_param('key');
        $license = translio_license();

        $result = $license->activate($key);

        if (is_wp_error($result)) {
            return rest_ensure_response(array(
                'success' => false,
                'error' => $result->get_error_message(),
            ));
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'License activated',
            'license_info' => $license->get_license_info(),
        ));
    }

    /**
     * Test translation (for testing only)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function translate_test($request) {
        $text = $request->get_param('text');
        $target = $request->get_param('target');

        $api = Translio_API::instance();

        if (!$api->is_configured()) {
            return rest_ensure_response(array(
                'success' => false,
                'error' => 'API not configured',
                'mode' => $api->get_mode_info(),
            ));
        }

        $start = microtime(true);
        $result = $api->translate_text($text, $target, '', false); // skip TM
        $duration = round((microtime(true) - $start) * 1000);

        if (is_wp_error($result)) {
            return rest_ensure_response(array(
                'success' => false,
                'error' => $result->get_error_message(),
                'duration_ms' => $duration,
            ));
        }

        return rest_ensure_response(array(
            'success' => true,
            'original' => $text,
            'translated' => $result,
            'target_lang' => $target,
            'duration_ms' => $duration,
            'mode' => $api->get_mode_info(),
        ));
    }

    /**
     * Get translated page by slug
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_page($request) {
        $slug = $request->get_param('slug');
        $lang = $request->get_param('lang');

        $page = $this->get_post_by_slug($slug, 'page');
        if (!$page) {
            return new WP_Error('not_found', __('Page not found', 'translio'), array('status' => 404));
        }

        return rest_ensure_response($this->format_post_response($page, $lang, 'page'));
    }

    /**
     * Get translated post by slug
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_post($request) {
        $slug = $request->get_param('slug');
        $lang = $request->get_param('lang');

        $post = $this->get_post_by_slug($slug, 'post');
        if (!$post) {
            return new WP_Error('not_found', __('Post not found', 'translio'), array('status' => 404));
        }

        return rest_ensure_response($this->format_post_response($post, $lang, 'post'));
    }

    /**
     * Get translated posts list
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_posts($request) {
        $lang     = $request->get_param('lang');
        $per_page = $request->get_param('per_page');
        $page     = $request->get_param('page');
        $tag      = $request->get_param('tag');

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ($tag) {
            $args['tag_id'] = $tag;
        }

        $query = new WP_Query($args);
        $posts = array();

        // Preload translations for all posts in batch (performance optimization)
        $post_ids = wp_list_pluck($query->posts, 'ID');
        $translations = $this->get_batch_translations($post_ids, $lang);

        foreach ($query->posts as $post) {
            $posts[] = $this->format_post_response($post, $lang, 'post', $translations);
        }

        $response = rest_ensure_response($posts);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Get available languages
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_languages($request) {
        global $wpdb;

        $table = Translio_DB::get_languages_table();
        $languages = $wpdb->get_results(
            "SELECT code, name, native_name, is_default FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC"
        );

        if (empty($languages)) {
            // Fallback to default languages
            return rest_ensure_response(array(
                array('code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true),
                array('code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'is_default' => false),
            ));
        }

        $result = array();
        foreach ($languages as $lang) {
            $result[] = array(
                'code'        => $lang->code,
                'name'        => $lang->name,
                'native_name' => $lang->native_name,
                'is_default'  => (bool) $lang->is_default,
            );
        }

        return rest_ensure_response($result);
    }

    /**
     * Get post by slug
     *
     * @param string $slug
     * @param string $post_type
     * @return WP_Post|null
     */
    private function get_post_by_slug($slug, $post_type = 'post') {
        $posts = get_posts(array(
            'name'        => $slug,
            'post_type'   => $post_type,
            'post_status' => 'publish',
            'numberposts' => 1,
        ));

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Format post/page response with translations
     *
     * @param WP_Post $post
     * @param string  $lang
     * @param string  $type
     * @param array   $preloaded_translations Optional preloaded translations for batch
     * @return array
     */
    private function format_post_response($post, $lang, $type = 'post', $preloaded_translations = null) {
        $default_lang = translio()->get_setting('default_language', 'en');

        // Get translations
        $title   = $post->post_title;
        $content = $post->post_content;
        $excerpt = $post->post_excerpt;

        if ($lang !== $default_lang) {
            $translations = $preloaded_translations[$post->ID] ?? $this->get_object_translations($post->ID, $type, $lang);

            if (!empty($translations['title'])) {
                $title = $translations['title'];
            }
            if (!empty($translations['content'])) {
                $content = $translations['content'];
            }
            if (!empty($translations['excerpt'])) {
                $excerpt = $translations['excerpt'];
            }
        }

        // Apply content filters (render blocks, shortcodes)
        $content = apply_filters('the_content', $content);

        // Auto-generate excerpt if empty
        if (empty($excerpt)) {
            $excerpt = wp_trim_words(strip_tags($content), 30, '...');
        }

        // Get author data
        $author_data = null;
        $author = get_userdata($post->post_author);
        if ($author) {
            $author_data = array(
                'name'        => $author->display_name,
                'avatar_urls' => array(
                    '96' => get_avatar_url($author->ID, array('size' => 96)),
                ),
            );
        }

        // Get featured image
        $featured_image = null;
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $featured_image = array(
                'source_url' => wp_get_attachment_url($thumbnail_id),
                'alt_text'   => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
            );
        }

        // Get categories (for posts)
        $categories = array();
        if ($type === 'post') {
            $post_categories = get_the_category($post->ID);
            foreach ($post_categories as $cat) {
                $categories[] = array(
                    'id'   => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                );
            }
        }

        // Get tags (for posts)
        $tags = array();
        if ($type === 'post') {
            $post_tags = get_the_tags($post->ID);
            if ($post_tags) {
                foreach ($post_tags as $tag) {
                    $tags[] = array(
                        'id'   => $tag->term_id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    );
                }
            }
        }

        return array(
            'id'         => $post->ID,
            'slug'       => $post->post_name,
            'parent'     => $post->post_parent,
            'title'      => array('rendered' => $title),
            'content'    => array('rendered' => $content),
            'excerpt'    => array('rendered' => '<p>' . $excerpt . '</p>'),
            'date'       => $post->post_date,
            'modified'   => $post->post_modified,
            'lang'       => $lang,
            'categories' => $categories,
            'tags'       => $tags,
            '_embedded'  => array(
                'author'          => $author_data ? array($author_data) : array(),
                'wp:featuredmedia' => $featured_image ? array($featured_image) : array(),
            ),
        );
    }

    /**
     * Get translations for a single object
     *
     * @param int    $object_id
     * @param string $object_type
     * @param string $lang
     * @return array
     */
    private function get_object_translations($object_id, $object_type, $lang) {
        $result = array(
            'title'   => '',
            'content' => '',
            'excerpt' => '',
        );

        // Use Translio_DB method if available
        if (method_exists('Translio_DB', 'get_translations_for_object')) {
            $translations = Translio_DB::get_translations_for_object($object_id, $object_type, $lang);

            if (!empty($translations)) {
                foreach ($translations as $row) {
                    $field = strtolower($row->field_name);
                    $value = $row->translated_content;

                    if (in_array($field, array('title', 'post_title'))) {
                        $result['title'] = $value;
                    } elseif (in_array($field, array('content', 'post_content'))) {
                        $result['content'] = $value;
                    } elseif (in_array($field, array('excerpt', 'post_excerpt'))) {
                        $result['excerpt'] = $value;
                    }
                }
            }

            return $result;
        }

        // Fallback: Direct database query
        global $wpdb;
        $table = Translio_DB::get_translations_table();

        $translations = $wpdb->get_results($wpdb->prepare(
            "SELECT field_name, translated_content FROM {$table}
             WHERE object_id = %d AND object_type = %s AND language_code = %s",
            $object_id, $object_type, $lang
        ));

        foreach ($translations as $row) {
            $field = strtolower($row->field_name);
            $value = $row->translated_content;

            if (in_array($field, array('title', 'post_title'))) {
                $result['title'] = $value;
            } elseif (in_array($field, array('content', 'post_content'))) {
                $result['content'] = $value;
            } elseif (in_array($field, array('excerpt', 'post_excerpt'))) {
                $result['excerpt'] = $value;
            }
        }

        return $result;
    }

    /**
     * Get translations for multiple objects in batch (performance optimization)
     *
     * @param array  $object_ids
     * @param string $lang
     * @return array Translations indexed by object_id
     */
    private function get_batch_translations($object_ids, $lang) {
        if (empty($object_ids)) {
            return array();
        }

        $default_lang = translio()->get_setting('default_language', 'en');
        if ($lang === $default_lang) {
            return array();
        }

        global $wpdb;
        $table = Translio_DB::get_translations_table();

        $placeholders = implode(',', array_fill(0, count($object_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT t.object_id, t.field_name, t.translated_content FROM {$table} t
             INNER JOIN {$wpdb->posts} p ON t.object_id = p.ID AND t.object_type = p.post_type
             WHERE t.object_id IN ({$placeholders}) AND t.language_code = %s",
            array_merge($object_ids, array($lang))
        );

        $results = $wpdb->get_results($query);
        $translations = array();

        foreach ($results as $row) {
            $id = $row->object_id;
            if (!isset($translations[$id])) {
                $translations[$id] = array(
                    'title'   => '',
                    'content' => '',
                    'excerpt' => '',
                );
            }

            $field = strtolower($row->field_name);
            $value = $row->translated_content;

            if (in_array($field, array('title', 'post_title'))) {
                $translations[$id]['title'] = $value;
            } elseif (in_array($field, array('content', 'post_content'))) {
                $translations[$id]['content'] = $value;
            } elseif (in_array($field, array('excerpt', 'post_excerpt'))) {
                $translations[$id]['excerpt'] = $value;
            }
        }

        return $translations;
    }
}

/**
 * Get REST API instance
 *
 * @return Translio_REST_API
 */
function translio_rest_api() {
    return Translio_REST_API::instance();
}
