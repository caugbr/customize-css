<?php 

trait CcssAdmin {
    
    /**
     * Add admin page
     *
     * @return void
     */
    public function add_admin_page() {
        add_menu_page(
            __('Customize CSS', 'ccss'),
            __('Customize CSS', 'ccss'),
            'manage_options',
            'ccss-rules',
            [$this, 'admin_page_list'],
            'dashicons-edit'
        );
        add_submenu_page(
            'ccss-rules',
            __('Customization rules', 'ccss'),
            __('Customization rules', 'ccss'),
            'manage_options',
            'ccss-rules',
            [$this, 'admin_page_list']
        );
        add_submenu_page(
            'ccss-rules',
            __('Add custom rule', 'ccss'),
            __('Add custom rule', 'ccss'),
            'manage_options',
            'ccss-add-rule',
            [$this, 'admin_page']
        );
        add_submenu_page(
            'ccss-rules',
            __('Customization Settings', 'ccss'),
            __('Settings', 'ccss'),
            'manage_options',
            'ccss-config',
            [$this, 'admin_page_config']
        );
    }

    /**
     * Buttons
     *
     * @return void
     */
    public function reposition_buttons() {
        $edit_button = get_option("ccss_edit_button", false);
        $save_button = get_option("ccss_save_button", false);
        if ($edit_button || $save_button) {
            $css = "";
            if ($edit_button) {
                $css .= "a.edit-page, a.edit-page:hover { {$edit_button} } ";
            }
            if ($save_button) {
                $css .= "a.save-css, a.save-css:hover { {$save_button} }";
            }
            add_action("wp_print_footer_scripts", function() use($css) {
                print '<style>' . $css . '</style>';
            });
        }
    }

    /**
     * Admin page - rules list
     *
     * @return void
     */
    public function admin_page_config() {
        global $f_inputs;
        $msg = "";
        if (isset($_POST['ccss_nonce_field'])) {
            check_admin_referer('ccss_nonce_action', 'ccss_nonce_field');
            update_option("ccss_edit_button", $_POST['edit_button']);
            update_option("ccss_save_button", $_POST['save_button']);
            update_option("ccss_image_filter", $_POST['image_filter']);
            update_option("ccss_image_cfg", $_POST['image_cfg'] ?? []);
            $msg = __('Settings successfully saved!', 'ccss');
        }
        $edit_button = get_option("ccss_edit_button", "top: 20px; right: 20px;");
        $save_button = get_option("ccss_save_button", "top: 20px; right: 54px;");
        $image_filter = get_option("ccss_image_filter", "");
        $image_cfg = get_option("ccss_image_cfg", []);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Customize CSS settings', 'ccss'); ?>
            </h1>
            <?php if (!empty($msg)) { print "<div class='notice updated'><p>{$msg}</p></div>"; } ?>

            <form method="post" action="" class="reaction-form">
                <h3><?php _e("Buttons", "ccss"); ?></h3>
                <p><?php _e("You can set the position of our buttons to prevent interference with your site's layout.", "ccss"); ?></p>
                <?php
                    $this->position_fields(
                        "edit_button", 
                        __("Edit Button Position", "ccss"), 
                        __("Set the position of the 'Edit Page' button (in pixels).", "ccss"),
                        $edit_button
                    );
                    $this->position_fields(
                        "save_button", 
                        __("Save Button Position", "ccss"), 
                        __("Set the position of the 'Save Page' button (in pixels).", "ccss"),
                        $save_button
                    );
                ?>
                <h3><?php _e("Image Frame on the Frontend", "ccss"); ?></h3>
                <p><?php _e("You can define rules for how the Image Frame behaves on the frontend.", "ccss"); ?></p>
                <?php
                    $f_inputs->input_line("select", [
                        "id" => "image_filter",
                        "name" => "image_filter",
                        "value" => $image_filter,
                        "options" => [
                            "all_images" => __("All available images", "ccss"),
                            "own_images" => __("Only images uploaded by the editor", "ccss")
                        ],
                        "description" => __("Set the filter for which images the editor can view in the frontend", 'ccss'),
                    ], __("Image Filter", 'ccss'));

                    $f_inputs->input_line("checkbox", [
                        "id" => "image_cfg",
                        "name" => "image_cfg[]",
                        "value" => $image_cfg,
                        "options" => [
                            "delete" => __("Delete images", "ccss"),
                            "edit" => __("Edit images", "ccss"),
                            "upload" => __("Upload images", "ccss")
                        ],
                        "description" => __("Set user permissions for images in the frontend", 'ccss'),
                    ], __("Permissions", 'ccss'));
                    
                    wp_nonce_field('ccss_nonce_action', 'ccss_nonce_field');

                    submit_button(__('Save settings', 'ccss'), 'primary', 'ccss_save');
                ?>
            </form>
        </div>
        <?php
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
                $msg = __('Customization rule successfully removed!', 'ccss');
            } else {
                $msg = __('The rule could not be removed', 'ccss');
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
                $msg = __('Customization rule successfully added!', 'ccss');
            }
            if (isset($_POST['ccss_update'])) {
                $this->update_rule($_POST['rule_id'], $rule, $_POST['rule_name']);
                $msg = __('Customization rule successfully updated!', 'ccss');
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
                    _e('Update customization rule', 'ccss');
                } else {
                    _e('Create new customization rule', 'ccss');
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
                            <h3><?php _e('Post types', 'ccss'); ?></h3>
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
                            <h3><?php _e('Taxonomies', 'ccss'); ?></h3>
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
                            <h3><?php _e('Locations', 'ccss'); ?></h3>
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
                            <h3><?php _e('Users that can edit', 'ccss'); ?></h3>
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
                                <h3><?php _e('Editable page areas', 'ccss'); ?></h3>
                                <div class="areas-wrapper" data-empty="<?php _e('No areas yet', 'ccss'); ?>"></div>
                                <div class="add-page-area formline">
                                    <button type="button" class="goto-add-area button button-secondary">
                                        <?php _e('Add area', 'ccss'); ?>
                                    </button>
                                </div>
                                <input type="hidden" id="areas" value='<?php print $areas; ?>'>
                                <?php $this->prev_next(); ?>
                            </div>
                            <div class="add-section">
                                <h3><?php _e('Add editable area', 'ccss'); ?></h3>
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
                                        <?php _e('Cancel', 'ccss'); ?>
                                    </button>
                                    <button type="button" class="add-area button button-secondary">
                                        <?php _e('Add editable area', 'ccss'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="tab-content" data-tab="4">
                            <div class="images-section">
                                <h3><?php _e('Image replacements', 'ccss'); ?></h3>
                                <div class="images-wrapper" data-empty="<?php _e('No replacements yet', 'ccss'); ?>"></div>
                                <div class="add-image formline">
                                    <button type="button" class="goto-add-image button button-secondary">
                                        <?php _e('Add replacement', 'ccss'); ?>
                                    </button>
                                </div>
                                <input type="hidden" id="images" value='<?php print $images; ?>'>
                                <?php $this->prev_next(); ?>
                            </div>
                            <div class="add-section">
                                <h3><?php _e('Add image replacement', 'ccss'); ?></h3>
                                <?php
                                $f_inputs->input_line("text", [
                                    "id" => "image_url",
                                    "description" => sprintf(
                                        __("Insert some image URL - %sselect from site images%s", 'ccss'),
                                        '<a href=\'#\' class=\'open-image-layer\' data-img=\'original\'>',
                                        '</a>'
                                    )
                                ], __("Original URL", 'ccss'));
                                $f_inputs->input_line("text", [
                                    "id" => "image_replacement",
                                    "description" => sprintf(
                                        __("Define the image that will replace the original one - %sselect from site images%s", 'ccss'),
                                        '<a href=\'#\' class=\'open-image-layer\' data-img=\'replacement\'>',
                                        '</a>'
                                    )
                                ], __("Replacement URL", 'ccss'));
                                ?>
                                <div class="add-image formline">
                                    <button type="button" class="cancel-add-image button button-secondary">
                                        <?php _e('Cancel', 'ccss'); ?>
                                    </button>
                                    <button type="button" class="add-image-replacement button button-secondary">
                                        <?php _e('Add image replacement', 'ccss'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="tab-content" data-tab="5">
                            <h3><?php _e('Review and generate rule', 'ccss'); ?></h3>
                            <div class="review-rule"></div>
                            <div class="error-messages"></div>
                            <div class="cols rule-name">
                                <div class="col-left">
                                    <input type="text" name="rule_name" id="rule_name" placeholder="<?php _e('Rule name', 'ccss'); ?>" value="<?php print $rule['rule_name'] ?? ''; ?>">
                                    <input type="hidden" name="fullrule" id="fullrule">
                                </div>
                                <div class="col-right">
                                    <?php 
                                    if (!empty($_GET['rule']))  {
                                        submit_button(__('Update customization rule', 'ccss'), 'primary', 'ccss_update', false);
                                        print "\n<input type='hidden' value='{$_GET['rule']}' name='rule_id'>\n";
                                    } else {
                                        submit_button(__('Create customization rule', 'ccss'), 'primary', 'ccss_save', false); 
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

    private function prev_next($prev = true, $next = true) {
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

    /**
     * Admin page - rules list
     *
     * @return void
     */
    private function position_fields($name, $label, $desc = '', $value = '') {
        ?>
        <div class="position formline">
            <label for="<?php print $name; ?>_prop_x"><?php print $label; ?></label>
            <div class="input">
                <div class="line">
                    <select id="<?php print $name; ?>_prop_x">
                        <option value="left"><?php _e("Left", "ccss"); ?></option>
                        <option value="right"><?php _e("Right", "ccss"); ?></option>
                    </select>
                    <input type="number" id="<?php print $name; ?>_x">
                    <div class="sufix">px</div>
                </div>
                <div class="line">
                    <select id="<?php print $name; ?>_prop_y">
                        <option value="top"><?php _e("Top", "ccss"); ?></option>
                        <option value="bottom"><?php _e("Bottom", "ccss"); ?></option>
                    </select>
                    <input type="number" id="<?php print $name; ?>_y">
                    <div class="sufix">px</div>
                </div>
                <div class="description"><?php print $desc; ?></div>
            </div>
            <input type="hidden" name="<?php print $name; ?>" value="<?php print $value; ?>">
        </div>
        <?php
    }
}