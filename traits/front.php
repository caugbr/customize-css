<?php

trait CcssFront {
    
    private $css_front_included = false;
    private $css_edit_included = false;
    private $forms = "";

    public function include_css() {
        $rules = $this->get_rules();
        $this->forms = "";
        foreach ($rules as $rule) {
            if ($this->is_page_editable($rule) && $this->is_valid_location($rule)) {
                $self = $this;
                add_action('wp_print_footer_scripts', function() use($self, $rule) {
                    $self->add_rule_css($rule);
                }, 999);
                if ($this->is_user_authorized($rule)) {
                    $this->add_rule_edition($rule);
                    $this->forms .= $this->edition_form($rule);
                }
            }
        }
    }

    public function edition_form($rule) {
        ob_start();
        $id = "rule_" . $rule['id'];
        ?>
        <div class="edit-form hidden" id="<?php print $id; ?>">
            <?php print $this->make_forms($rule, "{$id}_form"); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function add_edition_forms() {
        print $this->forms;
    }

    public function add_rule_edition($rule) {
        if ($this->css_edit_included) {
            return;
        }
        $this->add_front_assets($rule);
        add_action('wp_footer', [$this, 'add_edition_forms']);
        $this->css_edit_included = true;
    }
    
    public function add_front_assets($rule) {
        include_once CCSS_PATH . "frontend-media.php";
        restrict_frontend_media();
        wp_enqueue_media();
        wp_enqueue_style('dashicons');
        wp_enqueue_style('ccss-css', CCSS_URL . 'assets/css/ccss-front.css', [], $this->version);
        wp_add_inline_style('ccss-css', ':root { --edit-text: "' . __("Editable", "ccss") . '"; }');
        wp_enqueue_script('ccss-js', CCSS_URL . 'assets/js/ccss-front.js', [], $this->version, true);
        wp_localize_script('ccss-js', 'ccssStr', $this->localize_data($rule));
        $rules = json_encode($this->get_rules());
        $script = "document.addEventListener('DOMContentLoaded', ";
        $script .= "() => { window.ccss = new CcssFront({$rules}); });";
        wp_add_inline_script('ccss-js', $script);
    }

    public function gfont_code($font) {
        $htm = '';
        if (preg_match("/\"([^\"]+)\"/", $font, $m)) {
            $fname = $m[1];
            if (in_array($fname, $this->google_fonts)) {
                $name = str_replace(" ", "+", $fname);
                $htm = "<link rel='stylesheet' href='https://fonts.googleapis.com/css2?family={$name}&display=swap'>";
            }
        }
        return $htm;
    }

    public function add_rule_css($rule) {
        if ($this->css_front_included) {
            return;
        }
        $fonts = [];
        $htm = "\n<style class=\"ccss-style\" id=\"style_{$rule['id']}\">\n";
        foreach ($rule['rule_data']['areas'] as $area) {
            $htm .= "  {$area['selector']} {\n";
            foreach ($area['properties'] as $key => $value) {
                if (!empty($key) && !empty($value)) {
                    $htm .= "    {$key}: {$value};\n";
                }
                if ($key == 'font-family') {
                    $fonts[] = $value;
                }
            }
            $htm .= "  }\n";
        }
        $htm .= "</style>\n";
        if (count($fonts)) {
            foreach ($fonts as $font) {
                $htm = $this->gfont_code($font) . "\n" . $htm;
            }
        }
        if (!empty($rule['rule_data']['images'])) {
            $htm .= "<script>\ndocument.addEventListener('DOMContentLoaded', () => {\n";
            foreach ($rule['rule_data']['images'] as $img) {
                if (!empty($img['replacement'])) {
                    $url = explode(".", $img['image']);
                    array_pop($url);
                    $url = join('.', $url);
                    $htm .= "    const el = \$single('img[src^=\"{$url}\"]');\n";
                    $htm .= "    if (el) { el.removeAttribute('srcset'); el.removeAttribute('sizes'); el.src = '{$img['replacement']}'; }\n";
                }
            }
            $htm .= "});\n</script>\n";
        }
        print $htm;
        $this->css_front_included = true;
    }

    public function localize_data($rule) {
        $data = [
            "editButtonTitle" => __("Edit page", "ccss"),
            "editorTitle" => __("Editing rule", "ccss"),
            "editingTitle" => __("Editing %s", "ccss"),
            "saveButtonTitle" => __("Save changes", "ccss"),
            "replaceableImage" => __("Replaceable image", "ccss"),
            "selectImage" => __("Select image", "ccss"),
            "chooseReplacement" => __("Choose a replacement image", "ccss"),
            "ajaxurl" => admin_url('admin-ajax.php'),
        ];
        return $data;
    }

    public function make_forms($rule, $id = '') {
        if (empty($id)) {
            $id = $rule['rule_name'];
        }
        $v = function ($val) { return htmlspecialchars($val); };
        $htm = "";
        foreach ($rule['rule_data']['areas'] as $area) {
            $htm .= "<form method=\"POST\" id=\"element_" . $v($area['id']) . "\" data-selector=\"" . $area['selector'] . "\" data-rule=\"" . $rule['id'] . "\">\n";
            $htm .= "<h3>Editando <code>{$area['selector']}</code></h3>\n";
            $htm .= "<input name=\"area_id\" value=\"{$area['id']}\" type=\"hidden\">\n";
            foreach ($area['properties'] as $prop => $value) {
                $val = $this->default_props[$prop];
                $label = $this->turn_legible($prop);
                $name = str_replace("-", "_", $prop);
                $htm .= "<div class=\"formline\">\n";
                $htm .= "<label for=\"{$prop}\">{$label}</label>\n";
                switch ($val) {
                    case 'color':
                        $htm .= $this->make_color($name, $value, $prop);
                        break;
                    case 'number':
                        $htm .= "<div class=\"input\"><input data-id=\"{$prop}\" name=\"{$name}\" type=\"{$val}\"></div>\n";
                        break;
                        
                    case 'css_measure':
                        $htm .= $this->make_measure($name, '0', 'px', $prop);
                        break;
                        
                    case 'font':
                        $htm .= $this->make_select($name, $this->font_list, $value, $prop, '', true);
                        break;
                        
                    default:
                        if (is_array($val)) {
                            $htm .= $this->make_select($name, $val, $value, $prop, '');
                        }
                        break;
                }
                $htm .= "</div>\n";
            }
            $htm .= "<div class=\"buttons\">\n";
            $htm .= "<button type=\"button\" class=\"cancel\">" . __("Cancel", "ccss") . "</button>\n";
            $htm .= "<button type=\"submit\" class=\"apply primary\">" . __("Apply", "ccss") . "</button>\n";
            $htm .= "</div>\n";
            $htm .= "</form>\n";
        }
        return $htm;
    }
    
    public function is_page_editable($rul) {
        global $post;
        $is_eligible = true;
        $rule = $rul['rule_data'];
        if (!empty($rule['postTypes'])) {
            $is_eligible = $is_eligible && in_array($post->post_type, $rule['postTypes']);
        }
        if (!empty($rule['taxonomies'])) {
            foreach ($rule['taxonomies'] as $taxonomy => $terms) {
                if (!count($terms)) {
                    continue;
                }
                $post_terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'slugs']);
                $matches = array_intersect($post_terms, $terms);
                if ($rule['taxRelation'] === 'and') {
                    $is_eligible = $is_eligible && (count($matches) === count($terms));
                } elseif ($rule['taxRelation'] === 'or') {
                    $is_eligible = $is_eligible && !empty($matches);
                }
            }
        }
        if (is_page() && !empty($rule['pages'])) {
            foreach ($rule['pages'] as $page_name) {
                $is_eligible = $is_eligible && (is_page($page_name) || $this->is_subpage_of($page_name));
            }
        }
        if (!empty($rule['postCustomField'])) {
            $has_field = metadata_exists('post', $post->ID, $rule['postCustomField']);
            $is_eligible = $is_eligible && $has_field;
    
            if (!empty($rule['postCustomValue'])) {
                $field_value = get_post_meta($post->ID, $rule['postCustomField'], true);
                $is_eligible = $is_eligible && ($field_value === $rule['postCustomValue']);
            }
        }
        return $is_eligible;
    }
    
    public function is_valid_location($rul) {
        $is_valid = false;
        $rule = $rul['rule_data'];
        if (!empty($rule['places'])) {
            if (is_archive() || is_home()) {
                $is_valid = in_array('list', $rule['places']);
            }
            if (!$is_valid && is_singular()) {
                $is_valid = in_array('single', $rule['places']);
            }
            if (!$is_valid && in_array('subpage', $rule['places']) && !empty($rule['pages']) && is_page()) {
                foreach ($rule['pages'] as $page_name) {
                    if (is_page($page_name) || $this->is_subpage_of($page_name)) {
                        $is_valid = true;
                    }
                }
            }
        }
        return $is_valid;
    }
    
    public function is_user_authorized($rule) {
        $user = wp_get_current_user();
        if (!empty($rule['rule_data']['roles'])) {
            foreach ($rule['rule_data']['roles'] as $role) {
                if (in_array($role, $user->roles)) {
                    return true;
                }
            }
        }
        if (!empty($rule['rule_data']['users'])) {
            if (in_array($user->ID, $rule['rule_data']['users'])) {
                return true;
            }
        }
        if (!empty($rule['rule_data']['userCustomField'])) {
            $field_value = get_user_meta($user->ID, $rule['rule_data']['userCustomField'], true);
            if (!empty($field_value)) {
                $uc = $rule['rule_data']['userCustomValue'];
                if (empty($uc) || $field_value === $uc) {
                    return true;
                }
            }
        }
        return false;
    }

    function is_subpage_of($slug) {
        if (!is_page()) {
            return false;
        }
        $parent_page = get_page_by_path($slug);
        if (!$parent_page) {
            return false;
        }
        $current_page_id = get_queried_object_id();
        $parent_id = wp_get_post_parent_id($current_page_id);
        return ($parent_id === $parent_page->ID);
    }
    
    public function make_color($name, $val = '', $id = '') {
        if (empty($id)) {
            $id = $name;
        }
        $v = function ($v) { return htmlspecialchars($v); };
        ob_start();
        ?>
        <div class="input color">
            <input type="text" data-id="<?php print $v($id) ?>" name="<?php print $v($name) ?>" value="<?php $val ?>">
            <label for="<?php print $v($id) ?>_color"><?php // print $val ?></label>
            <input type="color" data-id="<?php print $v($id) ?>_color">
            <a href="#" class="clear-input" title="<?php _e("Clear", "ccss") ?>">
                <span class="dashicons dashicons-remove"></span>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function make_measure($name, $val = '0', $mtype = 'px', $id = '') {
        if (empty($id)) {
            $id = $name;
        }
        $v = function ($v) { return htmlspecialchars($v); };
        ob_start();
        ?>
        <div class="input measure">
            <input type="text" data-id="<?php print $v($id) ?>" name="<?php print $v($name) ?>" value="<?php $val ?>" min="0" step="1">
            <select data-id="width_mtype" name="width_mtype">
                <option value="px"<?php if ($mtype == "px") print " selected"; ?>>px</option>
                <option value="em"<?php if ($mtype == "em") print " selected"; ?>>em</option>
                <option value="rem"<?php if ($mtype == "rem") print " selected"; ?>>rem</option>
                <option value="%"<?php if ($mtype == "%") print " selected"; ?>>%</option>
                <option value="vw"<?php if ($mtype == "vw") print " selected"; ?>>vw</option>
                <option value="vh"<?php if ($mtype == "vh") print " selected"; ?>>vh</option>
                <option value="cm"<?php if ($mtype == "cm") print " selected"; ?>>cm</option>
                <option value="mm"<?php if ($mtype == "mm") print " selected"; ?>>mm</option>
                <option value="in"<?php if ($mtype == "in") print " selected"; ?>>in</option>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function make_select($name, $arr, $val = '', $id = '', $placeholder = false, $invert = false) {
        if (empty($id)) {
            $id = $name;
        }
        $v = function ($v) { return htmlspecialchars($v); };
        $htm = "\n<div class=\"input\"><select name=\"" . $v($name) . "\" data-id=\"" . $v($id) . "\">\n";
        if (is_string($placeholder)) {
            $htm .= "    <option value=\"\" selected>" . $v($placeholder) . "</option>\n";
        }
        foreach ($arr as $ind => $value) {
            $label = '';
            if (is_int($ind) && is_string($value)) {
                $label = $this->turn_legible($value);
            }
            if (is_string($ind) && is_string($value)) {
                $label = $value;
                $value = $ind;
            }
            $selected = ($val && $val == $value) ? ' selected' : '';
            if ($invert) {
                $htm .= "    <option value=\"" . $v($label) . "\"{$selected}>" . $v($value) . "</option>\n";
            } else {
                $htm .= "    <option value=\"" . $v($value) . "\"{$selected}>" . $v($label) . "</option>\n";
            }
        }
        $htm .= "</select></div>\n";
        return $htm;
    }
    
    // public function make_check_list($name, $arr, $id = '') {
    //     if (empty($id)) {
    //         $id = $name;
    //     }
    //     $v = function ($v) { return htmlspecialchars($v); };
    //     $htm = "\n<div class=\"check-list\">\n";
    //     foreach ($arr as $ind => $value) {
    //         $label = '';
    //         if (is_int($ind) && is_string($value)) {
    //             $label = $this->turn_legible($value);
    //         }
    //         if (is_string($ind) && is_string($value)) {
    //             $label = $value;
    //             $value = $ind;
    //         }
    //         $htm .= "<label><input type=\"checkbox\" value=\"" . $v($value) . "\"> " . $v($label) . "</label>\n";
    //     }
    //     $htm .= "</div>\n";
    //     return $htm;
    // }
    
}