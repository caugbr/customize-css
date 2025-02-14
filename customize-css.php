<?php
/**
 * Plugin name: Customize CSS
 * Description: Customize CSS is a plugin that allows specific users to edit layout details in predefined areas of your site, directly from the frontend.
 * Version: 1.0
 * Author: Cau Guanabara
 * Author URI: https://cauguanabara.com.br/dev
 * Text Domain: ccss
 * Domain Path: /langs
 * License: Wordpress
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CCSS_URL', plugin_dir_url(__FILE__));
define('CCSS_PATH', plugin_dir_path(__FILE__));

require_once CCSS_PATH . "traits/admin.php";
require_once CCSS_PATH . "traits/front.php";

require_once CCSS_PATH . "/rzp/require-zip-plugin.php";


class CustomizeCSS {

    use CcssAdmin, CcssFront;

    private $version = '1.0.2';

    public $font_list = [
        'Arial' => 'Arial, sans-serif',
        'Verdana' => 'Verdana, sans-serif',
        'Helvetica' => 'Helvetica, sans-serif',
        'Tahoma' => 'Tahoma, sans-serif',
        'Trebuchet MS' => '"Trebuchet MS", sans-serif',
        'Times New Roman' => '"Times New Roman", serif',
        'Georgia' => 'Georgia, serif',
        'Garamond' => 'Garamond, serif',
        'Courier New' => '"Courier New", monospace',
        'Lucida Console' => '"Lucida Console", monospace',
        // Google Fonts
        'Roboto' => '"Roboto", sans-serif',
        'Open Sans' => '"Open Sans", sans-serif',
        'Lato' => '"Lato", sans-serif',
        'Montserrat' => '"Montserrat", sans-serif',
        'Oswald' => '"Oswald", sans-serif',
        'Source Sans Pro' => '"Source Sans Pro", sans-serif',
        'Raleway' => '"Raleway", sans-serif',
        'PT Sans' => '"PT Sans", sans-serif',
        'Merriweather' => '"Merriweather", serif',
        'Playfair Display' => '"Playfair Display", serif',
        'Poppins' => '"Poppins", sans-serif',
        'Nunito' => '"Nunito", sans-serif',
        'Ubuntu' => '"Ubuntu", sans-serif',
        'Dancing Script' => '"Dancing Script", cursive',
        'Pacifico' => '"Pacifico", cursive',
        'Raleway' => '"Raleway", sans-serif',
        'Slabo 27px' => '"Slabo 27px", serif',
        'Roboto Condensed' => '"Roboto Condensed", sans-serif',
    ];

    private $google_fonts = [ 
        'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Oswald', 'Source Sans Pro', 'Raleway', 
        'PT Sans', 'Merriweather', 'Playfair Display', 'Poppins', 'Nunito', 'Ubuntu', 
        'Dancing Script', 'Pacifico', 'Slabo 27px', 'Roboto Condensed'
    ];

    public $default_props = [
        'color' => 'color',
        'background-color' => 'color',
        'font-family' => 'font',
        'font-size' => 'css_measure',
        'font-weight' => 'number',
        'font-style' => ['normal', 'italic', 'oblique', 'inherit'],
        'text-align' => ['left', 'right', 'center', 'justify', 'inherit'],
        'text-transform' => ['none', 'capitalize', 'uppercase', 'lowercase', 'inherit'],
        'text-decoration' => ['none', 'underline', 'overline', 'line-through', 'inherit'],
        'text-indent' => 'css_measure',
        'letter-spacing' => 'css_measure',
        'word-spacing' => 'css_measure',
        'white-space' => ['normal', 'nowrap', 'pre', 'pre-wrap', 'pre-line', 'inherit'],
        'line-height' => 'css_measure',
        'padding' => 'css_measure',
        'border-width' => 'css_measure',
        'border-color' => 'color',
        'direction' => ['inherit', 'ltr', 'rtl'],
    ];

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'customization_rules';

        $require_zip_plugin = new RequireZipPlugin();
        $require_zip_plugin->require(
            'Customize CSS', 
            'Form inputs', 
            'https://github.com/caugbr/form-inputs/archive/refs/heads/main.zip', 
            'form-inputs/form-inputs.php'
        );
        $require_zip_plugin->require(
            'Customize CSS', 
            'WP Helper', 
            'https://github.com/caugbr/wp-helper/archive/refs/heads/main.zip', 
            'wp-helper/wp-helper.php'
        );

        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'include_css']);
        add_action('wp_ajax_save_rule', [$this, 'save_rule']);
        add_action('init', function() {
            load_plugin_textdomain('ccss', false, dirname(plugin_basename(__FILE__)) . '/langs'); 
        });

        register_activation_hook(__FILE__, [$this, 'create_table']);

        if (!is_admin()) {
            $this->reposition_buttons();
        }
    }
    
    /**
     * Load all assets
     *
     * @return void
     */
    public function load_admin_assets($hook) {
        if ($hook == 'customize-css_page_ccss-config') {
            wp_enqueue_style('ccss-css', CCSS_URL . 'assets/css/ccss-admin-config.css', [], $this->version);
            wp_enqueue_script('ccss-js', CCSS_URL . 'assets/js/ccss-admin-config.js', [], $this->version, true);
        }
        if ($hook != 'customize-css_page_ccss-add-rule') {
            return;
        }
        wp_enqueue_script('ccss-js', CCSS_URL . 'assets/js/ccss-admin.js', [], $this->version, true);
        wp_localize_script('ccss-js', 'ccssStr', [
            "selector" => __("Selector", "ccss"),
            "description" => __("Description", "ccss"),
            "properties" => __("Properties", "ccss"),
            "postTypes" => __("Post types", "ccss"),
            "noPostTypes" => __("There is no post types selected", "ccss"),
            "taxonomies" => __("Taxonomies", "ccss"),
            "noTaxonomies" => __("There is no taxonomies selected", "ccss"),
            "taxRelation" => __("Taxonomy queries relation", "ccss"),
            "authUsers" => __("Authorized editors", "ccss"),
            "roles" => __("User roles", "ccss"),
            "noRoles" => __("No user roles defined", "ccss"),
            "userCustomField" => __("Custom user field", "ccss"),
            "noUserCustomField" => __("No custom user field was set", "ccss"),
            "userCustomFieldValue" => __("Custom user field value", "ccss"),
            "noCustomValue" => __("No custom user field value was set", "ccss"),
            "users" => __("Users", "ccss"),
            "noUsers" => __("No users selected", "ccss"),
            "areas" => __("Editable areas", "ccss"),
            "noAreas" => __("No editable areas defined", "ccss"),
            "places" => __("Locations", "ccss"),
            "noPlaces" => __("No locations defined", "ccss"),
            "askRemove" => __("Do you really want to remove this item?", "ccss"),
            "pages" => __("Pages", "ccss"),
            "noPages" => __("No specific pages set", "ccss"),
            "noContent" => __("There is no content (post types, pages, taxonomies) selected to apply", "ccss"),
            "noPlace" => __("The is no place (lists, single, sub pages) defined to show", "ccss"),
            "noUser" => __("There are no users (roles, custom field, specific users) set to edit", "ccss"),
            "noName" => __("Give a name for this rule", "ccss"),
            "postCustomField" => __("Custom post field", "ccss"),
            "noPostCustomField" => __("No custom post field was set", "ccss"),
            "postCustomFieldValue" => __("Custom post field value", "ccss"),
            "noPostCustomValue" => __("No custom post field value was set", "ccss"),
            "chooseImage" => __("Choose an image to replace", "ccss"),
            "chooseReplacement" => __("Choose a replacement image", "ccss"),
            "selectImage" => __("Select image", "ccss"),
            "images" => __("Replaced images", "ccss"),
            "noImages" => __("There is no replaced images", "ccss"),
        ]);
        wp_enqueue_media();
        wp_enqueue_style('ccss-css', CCSS_URL . 'assets/css/ccss-admin.css', [], $this->version);
    }

    /**
     * Get all public post types (excluding 'attachment') as an associative array.
     * Index: post type slug, Value: singular label (human-readable).
     *
     * @return array
     */
    public function get_post_types() {
        $post_types = get_post_types(['public' => true], 'objects');
        $result = [];
        
        foreach ($post_types as $post_type) {
            if ($post_type->name !== 'attachment') {
                $result[$post_type->name] = $post_type->labels->singular_name;
            }
        }
        return $result;
    }

    public function get_taxonomies() {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $taxonomy_terms = [];
        foreach ($taxonomies as $taxonomy_slug => $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy_slug,
                'hide_empty' => false
            ]);
            if (!is_wp_error($terms) && !empty($terms)) {
                $taxonomy_terms[$taxonomy_slug] = [
                    'name'  => $taxonomy->labels->name,
                    'terms' => []
                ];
                foreach ($terms as $term) {
                    $taxonomy_terms[$taxonomy_slug]['terms'][$term->slug] = $term->name;
                }
            }
        }
        return $taxonomy_terms;
    }
    
    public function get_user_roles() {
        $roles = get_editable_roles();
        $user_roles = [];
        foreach ($roles as $role_slug => $role_details) {
            $user_roles[$role_slug] = $role_details['name'];
        }
        return $user_roles;
    }

    public function get_users() {
        $users = get_users();
        $result = [];
        foreach ($users as $user) {
            $result[$user->user_login] = $user->display_name;
        }
        return $result;
    }

    /**
     * Get all published pages in the format ["slug" => "title"].
     *
     * @return array
     */
    public function get_published_pages() {
        $pages = get_pages([
            'post_status' => 'publish',
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
        ]);
        $result = [];
        foreach ($pages as $page) {
            $result[$page->post_name] = $page->post_title;
        }
        return $result;
    }

    public function add_rule($rule_name, $rule_data) {
        global $wpdb;
        $inserted = $wpdb->insert(
            $this->table,
            [
                'rule_name' => $rule_name,
                'rule_data' => maybe_serialize($rule_data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s']
        );
        return $inserted ? $wpdb->insert_id : false;
    }

    public function get_rule($id) {
        global $wpdb;
        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        if ($result) {
            $result['rule_data'] = maybe_unserialize($result['rule_data']);
        }
        return $result;
    }

    public function delete_rule($id) {
        global $wpdb;
        return $wpdb->delete($this->table, ['id' => $id], ['%d']);
    }

    public function merge_images($id, $images) {
        $rule = $this->get_rule($id);
        $rule['rule_data']['images'] = $images;
        $this->update_rule($id, $rule['rule_data']);
        return count($images) > 0;
    }

    public function merge_rule($id, $data) {
        $rule = $this->get_rule($id);
        foreach ($data as $selector => $props) {
            $area_index = array_search($selector, array_column($rule['rule_data']['areas'], "selector"));
            if ($area_index !== false) {
                foreach ($props as $name => $val) {
                    $rule['rule_data']['areas'][$area_index]['properties'][$name] = $val;
                }
                $this->update_rule($id, $rule['rule_data']);
                return true;
            }
        }
        return false;
    }

    public function update_rule($id, $rule_data, $rule_name = null) {
        global $wpdb;
        $data = ['rule_data' => maybe_serialize($rule_data)];
        $formats = ['%s'];
        if (!is_null($rule_name)) {
            $data['rule_name'] = $rule_name;
            $formats[] = '%s';
        }
        return $wpdb->update($this->table, $data, ['id' => $id], $formats, ['%d']);
    }

    public function get_rules() {
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$this->table}", ARRAY_A);

        foreach ($results as &$result) {
            $result['rule_data'] = maybe_unserialize($result['rule_data']);
        }

        return $results;
    }

    public function save_rule() {
        $data = json_decode(stripslashes($_POST['style']), true);
        $images = json_decode(stripslashes($_POST['images']), true);
        $ret1 = $this->merge_images($_POST['rule_id'], $images);
        $ret2 = $this->merge_rule($_POST['rule_id'], $data);
        $msg = $ret1 || $ret2 ? __("Rule successfully saved.", "ccss") 
                              : __("The rule could not be saved.", "ccss");
        wp_send_json(['error' => (!$ret1 && !$ret2), 'message' => $msg]);
    }
   
    public function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'customization_rules';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_name VARCHAR(180) NOT NULL,
            rule_data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    public function turn_legible($prop, $sep = "-", $glue = " ") {
        $parts = explode($sep, $prop);
        $parts = array_map('ucfirst', $parts);
        return implode($glue, $parts);
    }
    
    public function js_var_name($prop, $sep = "_", $glue = "") {
        $parts = explode($sep, $prop);
        $parts = array_map('ucfirst', $parts);
        return lcfirst(implode($glue, $parts));
    }

    public function php_var_name($input) {
        $sanitized = sanitize_key($input);
        $sanitized = str_replace('-', '_', $sanitized);
        if (preg_match('/^\d/', $sanitized)) {
            $sanitized = '_' . $sanitized;
        }
        return $sanitized;
    }
}

global $costumize_css;
$costumize_css = new CustomizeCSS();