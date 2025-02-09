<?php 

trait CcssAdmin {
    
    /**
     * Add admin page
     *
     * @return void
     */
    public function add_admin_page() {
        add_menu_page(
            __('Customize CSS', 'custom_css'),
            __('Customize CSS', 'custom_css'),
            'manage_options',
            'ccss-rules',
            [$this, 'admin_page_list'],
            'dashicons-edit'
        );
        add_submenu_page(
            'ccss-rules',
            __('Customization rules', 'custom_css'),
            __('Customization rules', 'custom_css'),
            'manage_options',
            'ccss-rules',
            [$this, 'admin_page_list']
        );
        add_submenu_page(
            'ccss-rules',
            __('Add custom rule', 'custom_css'),
            __('Add custom rule', 'custom_css'),
            'manage_options',
            'ccss-add-rule',
            [$this, 'admin_page']
        );
    }

    /**
     * Admin page - rules list
     *
     * @return void
     */
    public function admin_page_list() {
        if (!class_exists('Admin_Table')) {
            require_once CCSS_PATH . 'admin-table.php';
        }
        $msg = "";
        if (isset($_GET['action']) && $_GET['action'] === 'remove') {
            if (!isset($_GET['_wpnonce']) || !check_admin_referer('ccss_nonce_action')) {
                wp_die(__("Invalid nonce. Unauthorized action.", "ccss"));
            }
            if ($this->delete_rule($_GET['rule'])) {
                $msg = __('Customization rule successfully removed!', 'custom_css');
            } else {
                $msg = __('The rule could not be removed', 'custom_css');
            }
            ?>
            <script defer>
                document.addEventListener('DOMContentLoaded', function () {
                    const cleanUrl = new URL(window.location.href);
                    cleanUrl.search = '?page=ccss-rules';
                    window.history.replaceState(null, '', cleanUrl);
                });
            </script>
            <?php
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Customization rules', 'ccss'); ?>
                <a href="<?php print admin_url("admin.php?page=ccss-add-rule"); ?>" class="page-title-action">
                    <?php _e('Add new rule', 'ccss'); ?>
                </a>
            </h1>
            <?php if (!empty($msg)) { print "<div class='notice updated'><p>{$msg}</p></div>"; } ?>
            <?php
                $at = new Admin_Table();
                $at->prepare_items();
                $at->display();
            ?>
        </div>
        <?php
    }

    /**
     * Admin page
     *
     * @return void
     */
    public function admin_page() {
        global $f_inputs;
        if (!$f_inputs) {
            print "<p>\n";
            _e("This page depends on Form inputs plugin. Please install it.", 'ccss');
            print "</p>\n";
            return;
        }
        $ptypes = (array) $this->get_post_types();
        $taxs = (array) $this->get_taxonomies();
        $uroles = (array) $this->get_user_roles();
        $allusers = (array) $this->get_users();
        $allpages = $this->get_published_pages();
        $allprops = [];
        $props = array_keys($this->default_props);
        foreach ($props as $prop) {
            $allprops[$prop] = $this->turn_legible($prop);
        }
        $msg = "";
        $rule = [];
        if (isset($_POST['ccss_nonce_field'])) {
            check_admin_referer('ccss_nonce_action', 'ccss_nonce_field');
            $rule = json_decode(stripslashes($_POST['fullrule']), true);
            if (isset($_POST['ccss_save'])) {
                $this->add_rule($_POST['rule_name'], $rule);
                $msg = __('Customization rule successfully added!', 'custom_css');
            }
            if (isset($_POST['ccss_update'])) {
                $this->update_rule($_POST['rule_id'], $rule, $_POST['rule_name']);
                $msg = __('Customization rule successfully updated!', 'custom_css');
            }
        }
        if (!empty($_GET['rule'])) {
            $rule = $this->get_rule($_GET['rule']);
        }
        $post_types = $rule['rule_data'][$this->js_var_name("post_types")] ?? [];
        foreach ($taxs as $name => $info) {
            $vname = $this->php_var_name($name);
            $$vname = $rule['rule_data']['taxonomies']["{$name}"] ?? [];
        }
        $post_custom_field = $rule['rule_data'][$this->js_var_name("post_custom_field")] ?? '';
        $post_custom_value = $rule['rule_data'][$this->js_var_name("post_custom_value")] ?? '';
        $pages = $rule['rule_data']["pages"] ?? [];
        $places = $rule['rule_data']["places"] ?? ['list', 'single', 'subpage'];
        $tax_relation = $rule['rule_data'][$this->js_var_name("tax_relation")] ?? 'and';
        $roles = $rule['rule_data']["roles"] ?? [];
        $user_custom_field = $rule['rule_data'][$this->js_var_name("user_custom_field")] ?? '';
        $user_custom_value = $rule['rule_data'][$this->js_var_name("user_custom_value")] ?? '';
        $users = $rule['rule_data']["users"] ?? [];
        $properties = $rule['rule_data']["properties"] ?? [];
        $areas = json_encode($rule['rule_data']["areas"] ?? []);
        $images = json_encode($rule['rule_data']["images"] ?? []);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php 
                if (!empty($_GET['rule']))  {
                    _e('Update customization rule', 'custom_css');
                } else {
                    _e('Create new customization rule', 'custom_css');
                }
            ?></h1>
            <?php if (!empty($msg)) { print "<div class='notice updated'><p>{$msg}</p></div>"; } ?>

            <form method="post" action="" class="reaction-form">
                <?php wp_nonce_field('ccss_nonce_action', 'ccss_nonce_field'); ?>
                
                <div class="tabs" data-tab="1">
                    <div class="tab-links">
                        <a class="tab" href="#" data-tab="1">
                            <?php _e("Content types", "ccss"); ?>
                        </a>
                        <a class="tab" href="#" data-tab="2">
                            <?php _e("Authorize users", "ccss"); ?>
                        </a>
                        <a class="tab" href="#" data-tab="3">
                            <?php _e("Editable areas", "ccss"); ?>
                        </a>
                        <a class="tab" href="#" data-tab="4">
                            <?php _e("Replace images", "ccss"); ?>
                        </a>
                        <a class="tab" href="#" data-tab="5">
                            <?php _e("Generate rule", "ccss"); ?>
                        </a>
                    </div>
                    <div class="tab-stage">
                        <div class="tab-content" data-tab="1">
                            <h3><?php _e('Post types', 'custom_css'); ?></h3>
                            <?php
                            $f_inputs->input_line("checkbox", [
                                "id" => "post_types",
                                "name" => "ccss_post_types",
                                "value" => $post_types,
                                "options" => $ptypes,
                                "multiple" => true,
                                "size" => count($ptypes),
                                "description" => sprintf(__("Select the %s you want to make editable", 'ccss'), __("Post types", 'ccss')),
                            ], __("Post types", 'ccss'));

                            $f_inputs->input_line("select", [
                                "id" => "pages",
                                "name" => "ccss_pages",
                                "value" => $pages,
                                "options" => $allpages,
                                "multiple" => true,
                                "size" => 5,
                                "description" => sprintf(__("Select specific pages to apply this rule", 'ccss'), __($info['name'], 'ccss')),
                            ], __("Specific pages", 'ccss'));

                            $f_inputs->input_line("text", [
                                "id" => "post_custom_field",
                                "name" => "ccss_post_custom_field",
                                "value" => $post_custom_field,
                                "description" => __("Only posts that have this custom field set", 'ccss'),
                            ], __("Custom field", 'ccss'));

                            $f_inputs->input_line("text", [
                                "id" => "post_custom_value",
                                "name" => "ccss_post_custom_value",
                                "value" => $post_custom_value,
                                "description" => __("The custom field must have this value (optional)", 'ccss'),
                            ], __("Custom field value", 'ccss'));

                            ?>
                            <h3><?php _e('Taxonomies', 'custom_css'); ?></h3>
                            <?php
                            foreach ($taxs as $name => $info) {
                                $vname = $this->php_var_name($name);
                                $f_inputs->input_line("select", [
                                    "id" => $info['name'],
                                    "name" => "ccss_{$name}",
                                    "value" => $$vname,
                                    "options" => $info['terms'],
                                    "multiple" => true,
                                    "data-tax" => "1",
                                    "size" => count($info['terms']),
                                    "description" => sprintf(__("Select the %s you want to make editable", 'ccss'), __($info['name'], 'ccss')),
                                ], __($info['name'], 'ccss'));
                            }
                            $f_inputs->input_line("radio", [
                                "id" => "tax_relation",
                                "name" => "ccss_tax_relation",
                                "value" => $tax_relation,
                                "options" => ["and" => "AND", "or" => "OR"],
                                "class" => "no-clear",
                                "description" => __("The relation between multiple taxonomy queries", 'ccss'),
                            ], __("Tax queries relation", 'ccss'));
                            ?>
                            <h3><?php _e('Locations', 'custom_css'); ?></h3>
                            <?php

                            $f_inputs->input_line("checkbox", [
                                "id" => "places",
                                "name" => "ccss_places",
                                "value" => $places,
                                "options" => [
                                    "list" => __("Post lists", 'ccss'),
                                    "single" => __("Single posts", 'ccss'),
                                    "subpage" => __("Sub pages", 'ccss'),
                                ],
                                "description" => __("Where these changes should be applied?", 'ccss'),
                            ], __("Apply to", 'ccss'));

                            $this->prev_next();
                            ?>
                        </div>
                        <div class="tab-content" data-tab="2">
                            <h3><?php _e('Users that can edit', 'custom_css'); ?></h3>
                            <?php
                            $f_inputs->input_line("checkbox", [
                                "id" => "roles",
                                "name" => "ccss_roles",
                                "value" => $roles,
                                "options" => $uroles,
                                "multiple" => true,
                                "size" => count($ptypes),
                                "description" => sprintf(__("Select the user levels that can edit", 'ccss'), __("Post types", 'ccss')),
                            ], __("User roles", 'ccss'));

                            $f_inputs->input_line("text", [
                                "id" => "user_custom_field",
                                "name" => "ccss_user_custom_field",
                                "value" => $user_custom_field,
                                "description" => __("Only users that have this custom field can edit", 'ccss'),
                            ], __("User custom field", 'ccss'));

                            $f_inputs->input_line("text", [
                                "id" => "user_custom_value",
                                "name" => "ccss_user_custom_value",
                                "value" => $user_custom_value,
                                "description" => __("The custom field must have this value (optional)", 'ccss'),
                            ], __("Custom field value", 'ccss'));

                            $f_inputs->input_line("select", [
                                "id" => "users",
                                "name" => "ccss_users",
                                "value" => $users,
                                "options" => $allusers,
                                "multiple" => true,
                                "size" => 5,
                                "description" => sprintf(__("Select specific users to edit this rule", 'ccss'), __($info['name'], 'ccss')),
                            ], __("Users", 'ccss'));

                            $this->prev_next();
                            ?>
                        </div>
                        <div class="tab-content" data-tab="3">
                            <div class="areas-section">
                                <h3><?php _e('Editable page areas', 'custom_css'); ?></h3>
                                <div class="areas-wrapper" data-empty="<?php _e('No areas yet', 'custom_css'); ?>"></div>
                                <div class="add-page-area formline">
                                    <button type="button" class="goto-add-area button button-secondary">
                                        <?php _e('Add area', 'custom_css'); ?>
                                    </button>
                                </div>
                                <input type="hidden" id="areas" value='<?php print $areas; ?>'>
                                <?php $this->prev_next(); ?>
                            </div>
                            <div class="add-section">
                                <h3><?php _e('Add editable area', 'custom_css'); ?></h3>
                                <?php
                                $f_inputs->input_line("text", [
                                    "id" => "page_area",
                                    "description" => __("Insert a CSS selector that gets the area of the page that will be editable", 'ccss'),
                                ], __("CSS selector", 'ccss'));
                                $f_inputs->input_line("textarea", [
                                    "id" => "page_area_desc",
                                    "description" => __("Description of this area", 'ccss'),
                                ], __("Description", 'ccss'));
                                $f_inputs->input_line("checkbox", [
                                    "id" => "properties",
                                    "name" => "ccss_properties",
                                    "value" => $properties,
                                    "options" => ["check_all" => __("Check all", "ccss"), ...$allprops],
                                    "description" => __("Select the properties that can be used in this rule", 'ccss'),
                                ], __("Properties", 'ccss'));
                                ?>
                                <div class="add-page-area formline">
                                    <button type="button" class="cancel-add-area button button-secondary">
                                        <?php _e('Cancel', 'custom_css'); ?>
                                    </button>
                                    <button type="button" class="add-area button button-secondary">
                                        <?php _e('Add editable area', 'custom_css'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="tab-content" data-tab="4">
                            <div class="images-section">
                                <h3><?php _e('Image replacements', 'custom_css'); ?></h3>
                                <div class="images-wrapper" data-empty="<?php _e('No replacements yet', 'custom_css'); ?>"></div>
                                <div class="add-image formline">
                                    <button type="button" class="goto-add-image button button-secondary">
                                        <?php _e('Add replacement', 'custom_css'); ?>
                                    </button>
                                </div>
                                <input type="hidden" id="images" value='<?php print $images; ?>'>
                                <?php $this->prev_next(); ?>
                            </div>
                            <div class="add-section">
                                <h3><?php _e('Add image replacement', 'custom_css'); ?></h3>
                                <?php
                                $f_inputs->input_line("text", [
                                    "id" => "image_url",
                                    "description" => sprintf(
                                        __("Insert a URL or part of some image URL - %sselect from site images%s", 'ccss'),
                                        '<a href=\'#\' class=\'open-image-layer\' data-img=\'original\'>',
                                        '</a>'
                                    )
                                ], __("Original URL", 'ccss'));
                                $f_inputs->input_line("text", [
                                    "id" => "image_replacement",
                                    "description" => sprintf(
                                        __("Define the image that will replace the original one (full URL) - %sselect from site images%s", 'ccss'),
                                        '<a href=\'#\' class=\'open-image-layer\' data-img=\'replacement\'>',
                                        '</a>'
                                    )
                                ], __("Replacement URL", 'ccss'));
                                ?>
                                <div class="add-image formline">
                                    <button type="button" class="cancel-add-image button button-secondary">
                                        <?php _e('Cancel', 'custom_css'); ?>
                                    </button>
                                    <button type="button" class="add-image-replacement button button-secondary">
                                        <?php _e('Add image replacement', 'custom_css'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="tab-content" data-tab="5">
                            <h3><?php _e('Review and generate rule', 'custom_css'); ?></h3>
                            <div class="review-rule"></div>
                            <div class="error-messages"></div>
                            <div class="cols rule-name">
                                <div class="col-left">
                                    <input type="text" name="rule_name" id="rule_name" placeholder="<?php _e('Rule name', 'custom_css'); ?>" value="<?php print $rule['rule_name'] ?? ''; ?>">
                                    <input type="hidden" name="fullrule" id="fullrule">
                                </div>
                                <div class="col-right">
                                    <?php 
                                    if (!empty($_GET['rule']))  {
                                        submit_button(__('Update customization rule', 'custom_css'), 'primary', 'ccss_update', false);
                                        print "\n<input type='hidden' value='{$_GET['rule']}' name='rule_id'>\n";
                                    } else {
                                        submit_button(__('Create customization rule', 'custom_css'), 'primary', 'ccss_save', false); 
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php $this->prev_next(true, false); ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function prev_next($prev = true, $next = true) {
        $htm = "<div class=\"prev-next\">\n";
        if ($prev) {
            $htm .= "<button class=\"prev-tab button button-secondary\">" . __("&laquo; Previous", 'ccss') . "</button>\n";
        } else {
            $htm .= "<div></div>";
        }
        if ($next) {
            $htm .= "<button class=\"next-tab button button-secondary\">" . __("Next &raquo;", 'ccss') . "</button>\n";
        }
        $htm .= "</div>\n";
        print $htm;
    }
}