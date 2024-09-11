<?php
/**
 * Plugin Name: w-p.ai: Wordpress Agent
 * Description: A plugin for w-p.ai to manage the site via GPT prompts.
 * Version: 0.0.6
 * Author: w-p.ai
 * Author URI: https://w-p.ai
 * License URI: https://raw.githubusercontent.com/w-p-ai/wpsage-plugin/main/LICENSE
 * Text Domain: wpsage-api
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WPSage_Options_Access {
    private $api_key;

    public function __construct() {
        $this->api_key = get_option('wpsage_api_key', '');
        add_action('rest_api_init', array($this, 'register_api_endpoints'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_api_endpoints() {
        register_rest_route('wpsage/v1', '/site-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site_info'),
            'permission_callback' => array($this, 'check_permission')
        ));

        register_rest_route('wpsage/v1', '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_health_check'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('wpsage/v1', '/run-sql', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_sql_query'),
            'permission_callback' => array($this, 'check_permission')
        ));

        register_rest_route('wpsage/v1', '/run-php', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_php_code'),
            'permission_callback' => array($this, 'check_permission')
        ));
    }

    public function check_permission() {
        $provided_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
        if ($provided_key !== $this->api_key) {
            return new WP_Error('rest_forbidden', 'Invalid API Key', array('status' => 403));
        }
        return true;
    }

    public function get_site_info() {
        $response = array(
            'agent_name' => 'WPSage',
            'general_info' => $this->get_general_info(),
            'theme_info' => $this->get_theme_info(),
            'plugin_info' => $this->get_plugin_info(),
            'posts' => $this->get_wordpress_posts(),
            // 'pages' => $this->get_wordpress_pages(),
            // 'users' => $this->get_user_info(),
            // 'media' => $this->get_media_info(),
            // 'comments' => $this->get_comment_info(),
            // 'taxonomies' => $this->get_taxonomy_info(),
            'menus' => $this->get_menu_info(),
            'widgets' => $this->get_widget_info(),
            'favicon' => $this->get_favicon(),
            'site_logo' => $this->get_site_logo(),
        );
        return new WP_REST_Response($response, 200);
    }

    public function get_health_check($request) {
        $response = array(
            'status' => 'ok',
            'message' => 'WPSage API is functioning correctly',
            'timestamp' => current_time('mysql')
        );

        $provided_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
        if (!empty($provided_key)) {
            $response['api_key_valid'] = ($provided_key === $this->api_key);
        }

        return new WP_REST_Response($response, 200);
    }

    public function run_sql_query($request) {
        global $wpdb;

        $params = $request->get_json_params();
        $query = isset($params['query']) ? $params['query'] : '';

        if (empty($query)) {
            return new WP_Error('invalid_query', 'SQL query is required', array('status' => 400));
        }

        // Whitelist allowed SQL operations for security
        $allowed_operations = array('SELECT', 'SHOW', 'DESCRIBE', 'DESC');
        $operation = strtoupper(substr(trim($query), 0, 6));
        
        // if (!in_array($operation, $allowed_operations)) {
        //     return new WP_Error('forbidden_operation', 'Only SELECT, SHOW, DESCRIBE, and DESC operations are allowed', array('status' => 403));
        // }

        $results = $wpdb->get_results($query, ARRAY_A);

        if ($results === null) {
            return new WP_Error('query_error', $wpdb->last_error, array('status' => 500));
        }

        return new WP_REST_Response($results, 200);
    }

    public function run_php_code($request) {
        $params = $request->get_json_params();
        $code = isset($params['code']) ? $params['code'] : '';

        if (empty($code)) {
            return new WP_Error('invalid_code', 'PHP code is required', array('status' => 400));
        }

        // Execute the PHP code
        ob_start();
        $return_value = eval($code);
        $output = ob_get_clean();

        $response = array(
            'output' => $output,
            'return_value' => $return_value
        );

        return new WP_REST_Response($response, 200);
    }

    private function get_general_info() {
        return array(
            'site_title' => get_bloginfo('name'),
            'tagline' => get_bloginfo('description'),
            'wp_version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'admin_email' => get_option('admin_email'),
            'language' => get_bloginfo('language'),
            'timezone' => get_option('timezone_string'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'posts_per_page' => get_option('posts_per_page'),
        );
    }

    private function get_theme_info() {
        $current_theme = wp_get_theme();
        return array(
            'name' => $current_theme->get('Name'),
            'version' => $current_theme->get('Version'),
            'author' => $current_theme->get('Author'),
            'author_uri' => $current_theme->get('AuthorURI'),
            'template' => $current_theme->get_template(),
            'stylesheet' => $current_theme->get_stylesheet(),
            'screenshot' => $current_theme->get_screenshot(),
            'description' => $current_theme->get('Description'),
            'tags' => $current_theme->get('Tags')
        );
    }

    private function get_plugin_info() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        
        $plugin_info = array();
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $plugin_info[] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'description' => $plugin_data['Description'],
                'is_active' => in_array($plugin_path, $active_plugins)
            );
        }
        return $plugin_info;
    }

    private function get_wordpress_posts() {
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => 10,
            'post_status' => 'publish'
        );
        $posts = get_posts($args);
        $post_data = array();
        foreach ($posts as $post) {
            $post_data[] = array(
                'ID' => $post->ID,
                'title' => $post->post_title,
                'date' => $post->post_date,
                'author' => get_the_author_meta('display_name', $post->post_author),
                'excerpt' => get_the_excerpt($post)
            );
        }
        return $post_data;
    }

    private function get_wordpress_pages() {
        $args = array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );
        $pages = get_posts($args);
        $page_data = array();
        foreach ($pages as $page) {
            $page_data[] = array(
                'ID' => $page->ID,
                'title' => $page->post_title,
                'date' => $page->post_date,
                'author' => get_the_author_meta('display_name', $page->post_author),
                'parent' => $page->post_parent
            );
        }
        return $page_data;
    }

    private function get_user_info() {
        $users = get_users(array('fields' => array('ID', 'user_login', 'user_email', 'display_name', 'user_registered')));
        $user_data = array();
        foreach ($users as $user) {
            $user_data[] = array(
                'ID' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'registered_date' => $user->user_registered,
                'roles' => get_user_role($user->ID)
            );
        }
        return $user_data;
    }

    private function get_media_info() {
        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'inherit'
        );
        $attachments = get_posts($args);
        $media_data = array();
        foreach ($attachments as $attachment) {
            $media_data[] = array(
                'ID' => $attachment->ID,
                'title' => $attachment->post_title,
                'mime_type' => $attachment->post_mime_type,
                'url' => wp_get_attachment_url($attachment->ID)
            );
        }
        return $media_data;
    }

    private function get_comment_info() {
        $args = array(
            'status' => 'approve',
            'number' => 10
        );
        $comments = get_comments($args);
        $comment_data = array();
        foreach ($comments as $comment) {
            $comment_data[] = array(
                'ID' => $comment->comment_ID,
                'author' => $comment->comment_author,
                'date' => $comment->comment_date,
                'content' => $comment->comment_content,
                'post_ID' => $comment->comment_post_ID
            );
        }
        return $comment_data;
    }

    private function get_taxonomy_info() {
        $taxonomies = get_taxonomies(array(), 'objects');
        $taxonomy_data = array();
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_data[] = array(
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'hierarchical' => $taxonomy->hierarchical,
                'public' => $taxonomy->public
            );
        }
        return $taxonomy_data;
    }

    private function get_menu_info() {
        $menus = wp_get_nav_menus();
        $menu_data = array();
        foreach ($menus as $menu) {
            $menu_data[] = array(
                'ID' => $menu->term_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'locations' => get_nav_menu_locations()
            );
        }
        return $menu_data;
    }

    private function get_widget_info() {
        global $wp_registered_sidebars, $wp_registered_widgets;
        
        $widget_data = array();
        foreach ($wp_registered_sidebars as $sidebar) {
            $widgets = wp_get_sidebars_widgets();
            $sidebar_widgets = array();
            if (isset($widgets[$sidebar['id']])) {
                foreach ($widgets[$sidebar['id']] as $widget) {
                    if (isset($wp_registered_widgets[$widget])) {
                        $sidebar_widgets[] = $wp_registered_widgets[$widget]['name'];
                    }
                }
            }
            $widget_data[] = array(
                'name' => $sidebar['name'],
                'id' => $sidebar['id'],
                'widgets' => $sidebar_widgets
            );
        }
        return $widget_data;
    }

    private function get_favicon() {
        $favicon_url = get_site_icon_url();
        return $favicon_url ? $favicon_url : '';
    }

    private function get_site_logo() {
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        return $logo_url ? $logo_url : '';
    }

    public function add_admin_menu() {
        add_options_page(
            'w-p.ai - WPSage API Settings',
            'w-p.ai - WPSage API',
            'manage_options',
            'wpsage-api-settings',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('wpsage_api_settings', 'wpsage_api_key');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>WPSage API Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpsage_api_settings');
                do_settings_sections('wpsage_api_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">API Key</th>
                        <td><input type="text" name="wpsage_api_key" value="<?php echo esc_attr(get_option('wpsage_api_key')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new WPSage_Options_Access();