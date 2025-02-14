<?php
/**
 * Require ZIP plugin or theme
 */

if (!class_exists('RequireZipPlugin')) {
    class RequireZipPlugin {
    
        public $required = [];
        public $strings = [];
        public $type;
    
        /**
         * Constructor
         *
         * @param string $type Typeof script that are calling ('plugin' | 'theme')
         */
        public function __construct($type = 'plugin') {
            $this->type = $type;
    
            add_action('plugins_loaded', [$this, 'load_translations']);
            add_action('wp_loaded', [$this, 'do_download']);
            add_action('admin_notices', [$this, 'add_notices']);
        }
        
        /**
         * Add required plugin to queue
         *
         * @param [string] $dependent   Name of the script that are requiring
         * @param [string] $required    Name of the requested plugin/theme
         * @param [string] $zip_url     URL to the ZIP file
         * @param [string] $plugin_id   Plugin ID (directory_name/file_name)
         * @param [string] $type        'plugin' or 'theme' - default: 'plugin'
         * @return void
         */
        public function require($dependent, $required, $zip_url, $plugin_id, $type = 'plugin') {
            $arr = compact('dependent', 'required', 'zip_url', 'plugin_id', 'type');
            $arr = $this->update_status($arr);
            $this->required[] = $arr;
        }
        
        public function load_translations() {
            if ($this->type == 'plugin') {
                load_plugin_textdomain('rzp', false, dirname(__FILE__) . '/langs');
            }  else {
                $locale = determine_locale(); // WP 5.0+ ou get_locale() para versões anteriores
                $mofile = dirname(__FILE__) . '/langs/rzp-' . $locale . '.mo';
                if (file_exists($mofile)) {
                    load_textdomain('rzp', $mofile);
                }
            }
            // For translators (valid for all strings below)
            // %1$s - requesting script
            // %2$s - required plugin/theme
            // %3$s - open link (if there is a link)
            // %4$s - close link (if there is a link)
            $this->strings['plugin-absent'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> plugin, please click the following link to %3$sinstall %2$s%4$s.', 'rzp');
            $this->strings['plugin-installed'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> plugin, please activate it in %3$splugins page%4$s.', 'rzp');
            $this->strings['plugin-installed_plugins'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> plugin, please activate it.', 'rzp');
            $this->strings['theme-absent'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> theme, please click the following link to %3$sinstall %2$s%4$s.', 'rzp');
            $this->strings['theme-installed'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> theme, please activate it in %3$themes page%4$s.', 'rzp');
            $this->strings['theme-installed_themes'] = __('<strong>%1$s</strong> depends on <strong>%2$s</strong> theme, please activate it.', 'rzp');
        }
        
        private function update_status($item) {
            $item['status'] = $item['type'] . ($this->check($item) ? '-installed' : '-absent');
            if ($item['type'] == 'plugin') {
                if (!function_exists('is_plugin_active')) {
                    include_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                if (is_plugin_active($item['plugin_id'])) {
                    $item['status'] = 'plugin-active';
                }
            } else {
                $theme = wp_get_theme();
                if ($item['required'] == $theme->get('Name')) {
                    $item['status'] = 'theme-active';
                }
            }
            return $item;
        }
    
        private function check($item) {
            if ($item['type'] == 'theme') {
                return file_exists(ABSPATH . "/wp-content/themes/{$item['plugin_id']}");
            }
            return file_exists(ABSPATH . "/wp-content/plugins/{$item['plugin_id']}");
        }
    
        public function add_notices() {
            foreach ($this->required as $info) {
                $this->{"add_{$info['type']}_notice"}($info);
            }
        }
    
        public function add_theme_notice($info) {
            $info = $this->update_status($info);
            if ($info['status'] != 'theme-active') {
                print '<div class="notice notice-error"><p>';
                if ($info['status'] == 'theme-absent') {
                    $params = [$info['dependent'], $info['required'], '<a href="themes.php?rzp_install=' . urlencode($info['required']) . '">', '</a>'];
                    printf($this->strings['theme-absent'], ...$params);
                }
                if ($info['status'] == 'theme-installed') {
                    global $pagenow;
                    $str_id = 'theme-' . ($pagenow == 'themes.php' ? 'installed_themes' : 'installed');
                    $params = [$info['dependent'], $info['required'], '<a href="themes.php">', '</a>'];
                    printf($this->strings[$str_id], ...$params);
                }
                print '</p></div>';
            }
        }
    
        public function add_plugin_notice($info) {
            $info = $this->update_status($info);
            if ($info['status'] != 'plugin-active') {
                print '<div class="notice notice-error"><p>';
                if ($info['status'] == 'plugin-absent') {
                    $params = [$info['dependent'], $info['required'], '<a href="plugins.php?rzp_install=' . urlencode($info['required']) . '">', '</a>'];
                    printf($this->strings['plugin-absent'], ...$params);
                }
                if ($info['status'] == 'plugin-installed') {
                    global $pagenow;
                    $str_id = 'plugin-' . ($pagenow == 'plugins.php' ? 'installed_plugins' : 'installed');
                    $params = [$info['dependent'], $info['required'], '<a href="plugins.php">', '</a>'];
                    printf($this->strings[$str_id], ...$params);
                }
                print '</p></div>';
            }
        }
    
        private function get_by($value, $field = 'required', $ret = 'object') {
            foreach ($this->required as $ind => $info) {
                if ($value == $info[$field]) {
                    return $ret == 'index' ? $ind : $info;
                }
            }
            return null;
        }
        
        public function do_download() {
            $ok = false;
            if (!empty($_GET['rzp_install'])) {
                $info = $this->get_by($_GET['rzp_install']);
                if (null !== $info) {
                    $ok = $this->download($info);
                    wp_redirect(admin_url($info['type'] . 's.php'));
                    exit;
                }
            }
            return $ok;
        }
    
        private function download($item) {
            $dir_name = explode("/", $item['plugin_id'])[0];
            $parts = explode("/", $item['zip_url']);
            $name = $parts[count($parts) - 1];
            $local_file = ABSPATH . "wp-content/uploads/{$name}";
            $wp_filesystem = $this->filesystem();
        
            $data = wp_remote_get($item['zip_url']);
            $zip = $data['body'];
            $wp_filesystem->put_contents($local_file, $zip);
        
            $unzip_dir = ABSPATH . "wp-content/uploads/{$dir_name}/";
            if (unzip_file($local_file, $unzip_dir)) {
                $dirs = array_values(array_diff(scandir($unzip_dir), ['.', '..']));
                $dir = $dirs[0] ?? '';
                if (!empty($dir) && is_dir($unzip_dir . $dir)) {
                    $wp_filesystem->move($unzip_dir . $dir, ABSPATH . "/wp-content/{$item['type']}s/{$dir_name}");
                    unlink($local_file);
                    rmdir($unzip_dir);
                    return true;
                }
            }
            return false;
        }
        
        public function filesystem() {
            global $wp_filesystem;
            if (is_null($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            return $wp_filesystem;
        }
    }
}