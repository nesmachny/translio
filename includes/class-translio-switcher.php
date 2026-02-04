<?php
/**
 * Translio Language Switcher
 *
 * Provides language switching UI for frontend:
 * - WordPress Widget
 * - Shortcode [translio_switcher]
 * - WP Nav Menu integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Switcher {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Register widget
        add_action('widgets_init', array($this, 'register_widget'));

        // Register shortcode
        add_shortcode('translio_switcher', array($this, 'shortcode_handler'));

        // Add to nav menu
        add_filter('wp_nav_menu_items', array($this, 'add_to_nav_menu'), 10, 2);

        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Register the widget
     */
    public function register_widget() {
        register_widget('Translio_Switcher_Widget');
    }

    /**
     * Enqueue frontend styles and scripts
     */
    public function enqueue_styles() {
        // Check if we have secondary languages configured
        $secondary_languages = translio()->get_secondary_languages();
        if (empty($secondary_languages)) {
            return;
        }

        wp_enqueue_style(
            'translio-switcher',
            TRANSLIO_PLUGIN_URL . 'assets/css/switcher.css',
            array(),
            TRANSLIO_VERSION
        );

        wp_enqueue_script(
            'translio-switcher',
            TRANSLIO_PLUGIN_URL . 'assets/js/switcher.js',
            array(),
            TRANSLIO_VERSION,
            true
        );
    }

    /**
     * Shortcode handler
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'style'      => 'dropdown', // dropdown, inline, flags
            'show_flags' => 'yes',
            'show_names' => 'yes',
            'show_current' => 'yes',
        ), $atts, 'translio_switcher');

        return $this->render_switcher(array(
            'style'        => $atts['style'],
            'show_flags'   => $atts['show_flags'] === 'yes',
            'show_names'   => $atts['show_names'] === 'yes',
            'show_current' => $atts['show_current'] === 'yes',
        ));
    }

    /**
     * Add language switcher to nav menu
     *
     * @param string $items Menu items HTML
     * @param object $args Menu arguments
     * @return string Modified menu items
     */
    public function add_to_nav_menu($items, $args) {
        $settings = get_option('translio_switcher_settings', array());

        // Check if menu integration is enabled
        if (empty($settings['enable_menu'])) {
            return $items;
        }

        // Check if this is the correct menu location
        $menu_location = isset($settings['menu_location']) ? $settings['menu_location'] : 'primary';
        if ($args->theme_location !== $menu_location) {
            return $items;
        }

        $style = isset($settings['menu_style']) ? $settings['menu_style'] : 'inline';
        $show_flags = isset($settings['show_flags']) ? $settings['show_flags'] : true;
        $show_names = isset($settings['show_names']) ? $settings['show_names'] : true;

        $switcher_html = $this->render_menu_items(array(
            'style'      => $style,
            'show_flags' => $show_flags,
            'show_names' => $show_names,
        ));

        return $items . $switcher_html;
    }

    /**
     * Render language switcher HTML
     *
     * @param array $options Render options
     * @return string HTML
     */
    public function render_switcher($options = array()) {
        $defaults = array(
            'style'        => 'dropdown',
            'show_flags'   => true,
            'show_names'   => true,
            'show_current' => true,
            'class'        => '',
        );
        $options = wp_parse_args($options, $defaults);

        $languages = translio()->get_active_languages();
        $current_lang = translio()->get_current_language();
        $current_url = $this->get_current_page_url();

        if (empty($languages) || count($languages) < 2) {
            return '';
        }

        $html = '<div class="translio-switcher translio-switcher--' . esc_attr($options['style']);
        if ($options['class']) {
            $html .= ' ' . esc_attr($options['class']);
        }
        $html .= '">';

        if ($options['style'] === 'dropdown') {
            $html .= $this->render_dropdown($languages, $current_lang, $current_url, $options);
        } else {
            $html .= $this->render_inline($languages, $current_lang, $current_url, $options);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render dropdown style switcher
     */
    private function render_dropdown($languages, $current_lang, $current_url, $options) {
        $html = '';
        $current_language = null;
        $other_languages = array();

        foreach ($languages as $lang) {
            if ($lang->code === $current_lang) {
                $current_language = $lang;
            } else {
                $other_languages[] = $lang;
            }
        }

        if (!$current_language) {
            return '';
        }

        // Current language button
        $html .= '<button class="translio-switcher__toggle" aria-expanded="false" aria-haspopup="true">';
        if ($options['show_flags']) {
            $html .= '<span class="translio-flag translio-flag--' . esc_attr($current_language->code) . '"></span>';
        }
        if ($options['show_names']) {
            $html .= '<span class="translio-switcher__name">' . esc_html($current_language->native_name) . '</span>';
        }
        $html .= '<span class="translio-switcher__arrow"></span>';
        $html .= '</button>';

        // Dropdown menu
        $html .= '<ul class="translio-switcher__dropdown">';
        foreach ($other_languages as $lang) {
            $url = $this->get_language_url($lang->code, $current_url);
            $html .= '<li class="translio-switcher__item">';
            $html .= '<a href="' . esc_url($url) . '" class="translio-switcher__link" hreflang="' . esc_attr($lang->code) . '">';
            if ($options['show_flags']) {
                $html .= '<span class="translio-flag translio-flag--' . esc_attr($lang->code) . '"></span>';
            }
            if ($options['show_names']) {
                $html .= '<span class="translio-switcher__name">' . esc_html($lang->native_name) . '</span>';
            }
            $html .= '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Render inline style switcher
     */
    private function render_inline($languages, $current_lang, $current_url, $options) {
        $html = '<ul class="translio-switcher__list">';

        foreach ($languages as $lang) {
            $is_current = ($lang->code === $current_lang);
            $url = $this->get_language_url($lang->code, $current_url);

            $item_class = 'translio-switcher__item';
            if ($is_current) {
                $item_class .= ' translio-switcher__item--active';
            }

            $html .= '<li class="' . esc_attr($item_class) . '">';

            if ($is_current && !$options['show_current']) {
                // Skip current language link
                continue;
            }

            if ($is_current) {
                $html .= '<span class="translio-switcher__current">';
            } else {
                $html .= '<a href="' . esc_url($url) . '" class="translio-switcher__link" hreflang="' . esc_attr($lang->code) . '">';
            }

            if ($options['show_flags']) {
                $html .= '<span class="translio-flag translio-flag--' . esc_attr($lang->code) . '"></span>';
            }
            if ($options['show_names']) {
                $html .= '<span class="translio-switcher__name">' . esc_html($lang->native_name) . '</span>';
            }

            if ($is_current) {
                $html .= '</span>';
            } else {
                $html .= '</a>';
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render menu items for nav menu integration
     */
    private function render_menu_items($options) {
        $languages = translio()->get_active_languages();
        $current_lang = translio()->get_current_language();
        $current_url = $this->get_current_page_url();

        if (empty($languages) || count($languages) < 2) {
            return '';
        }

        $html = '';

        if ($options['style'] === 'dropdown') {
            // Single menu item with dropdown
            $current_language = null;
            foreach ($languages as $lang) {
                if ($lang->code === $current_lang) {
                    $current_language = $lang;
                    break;
                }
            }

            $html .= '<li class="menu-item menu-item-translio menu-item-has-children">';
            $html .= '<a href="#">';
            if ($options['show_flags']) {
                $html .= '<span class="translio-flag translio-flag--' . esc_attr($current_language->code) . '"></span>';
            }
            if ($options['show_names']) {
                $html .= '<span>' . esc_html($current_language->native_name) . '</span>';
            }
            $html .= '</a>';
            $html .= '<ul class="sub-menu">';

            foreach ($languages as $lang) {
                if ($lang->code === $current_lang) {
                    continue;
                }
                $url = $this->get_language_url($lang->code, $current_url);
                $html .= '<li class="menu-item menu-item-translio-lang">';
                $html .= '<a href="' . esc_url($url) . '" hreflang="' . esc_attr($lang->code) . '">';
                if ($options['show_flags']) {
                    $html .= '<span class="translio-flag translio-flag--' . esc_attr($lang->code) . '"></span>';
                }
                if ($options['show_names']) {
                    $html .= '<span>' . esc_html($lang->native_name) . '</span>';
                }
                $html .= '</a>';
                $html .= '</li>';
            }

            $html .= '</ul>';
            $html .= '</li>';
        } else {
            // Separate menu items for each language
            foreach ($languages as $lang) {
                $is_current = ($lang->code === $current_lang);
                $url = $this->get_language_url($lang->code, $current_url);

                $class = 'menu-item menu-item-translio-lang';
                if ($is_current) {
                    $class .= ' current-lang';
                }

                $html .= '<li class="' . esc_attr($class) . '">';
                $html .= '<a href="' . esc_url($url) . '" hreflang="' . esc_attr($lang->code) . '">';
                if ($options['show_flags']) {
                    $html .= '<span class="translio-flag translio-flag--' . esc_attr($lang->code) . '"></span>';
                }
                if ($options['show_names']) {
                    $html .= '<span>' . esc_html($lang->native_name) . '</span>';
                }
                $html .= '</a>';
                $html .= '</li>';
            }
        }

        return $html;
    }

    /**
     * Get URL for a specific language
     */
    private function get_language_url($lang_code, $current_url) {
        $default_lang = translio()->get_default_language();
        $secondary_languages = translio()->get_secondary_languages();
        $home_url = home_url('/');

        // Parse current URL
        $parsed = parse_url($current_url);
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        // Remove ALL existing language prefixes
        foreach ($secondary_languages as $lang) {
            $path = preg_replace('#^/' . preg_quote($lang, '#') . '(/|$)#', '/', $path);
        }

        // Add language prefix if not default
        if ($lang_code !== $default_lang) {
            $path = '/' . $lang_code . $path;
        }

        return home_url($path) . $query;
    }

    /**
     * Get current page URL
     */
    private function get_current_page_url() {
        global $wp;
        $current_url = home_url(add_query_arg(array(), $wp->request));

        // Add query string if present
        if (!empty($_SERVER['QUERY_STRING'])) {
            $current_url .= '?' . sanitize_text_field($_SERVER['QUERY_STRING']);
        }

        return $current_url;
    }

    /**
     * Get available menu locations
     */
    public static function get_menu_locations() {
        $locations = get_registered_nav_menus();
        return $locations;
    }
}

/**
 * WordPress Widget for Language Switcher
 */
class Translio_Switcher_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'translio_switcher_widget',
            __('Translio Language Switcher', 'translio'),
            array(
                'description' => __('Display language switcher for your multilingual site.', 'translio'),
                'classname'   => 'widget-translio-switcher',
            )
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $style = isset($instance['style']) ? $instance['style'] : 'dropdown';
        $show_flags = isset($instance['show_flags']) ? $instance['show_flags'] : true;
        $show_names = isset($instance['show_names']) ? $instance['show_names'] : true;

        echo Translio_Switcher::instance()->render_switcher(array(
            'style'      => $style,
            'show_flags' => $show_flags,
            'show_names' => $show_names,
            'class'      => 'translio-switcher--widget',
        ));

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $style = !empty($instance['style']) ? $instance['style'] : 'dropdown';
        $show_flags = isset($instance['show_flags']) ? $instance['show_flags'] : true;
        $show_names = isset($instance['show_names']) ? $instance['show_names'] : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'translio'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('style')); ?>"><?php _e('Style:', 'translio'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('style')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('style')); ?>">
                <option value="dropdown" <?php selected($style, 'dropdown'); ?>><?php _e('Dropdown', 'translio'); ?></option>
                <option value="inline" <?php selected($style, 'inline'); ?>><?php _e('Inline List', 'translio'); ?></option>
                <option value="flags" <?php selected($style, 'flags'); ?>><?php _e('Flags Only', 'translio'); ?></option>
            </select>
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_flags')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_flags')); ?>" <?php checked($show_flags); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_flags')); ?>"><?php _e('Show Flags', 'translio'); ?></label>
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_names')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_names')); ?>" <?php checked($show_names); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_names')); ?>"><?php _e('Show Language Names', 'translio'); ?></label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['style'] = (!empty($new_instance['style'])) ? sanitize_text_field($new_instance['style']) : 'dropdown';
        $instance['show_flags'] = !empty($new_instance['show_flags']);
        $instance['show_names'] = !empty($new_instance['show_names']);
        return $instance;
    }
}

/**
 * Initialize switcher
 */
function translio_switcher() {
    return Translio_Switcher::instance();
}
