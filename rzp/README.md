# Require ZIP plugin
Requires one or more plugins or a theme to be active. If the required plugin/theme does not exist in the local folder, the system offers a link to download and install it from a URL that points to a ZIP file, such as the Github download URLs, for example.

### Require a plugin or a theme
To add some plugin or theme to the required items queue, just use `$require_zip_plugin->require`. The plugin creates this global instance and you can use the `require` method, that takes four parameters:

- `$dependent` - Name of the requesting script
- `$required` - Name of the reuired plugin
- `$zip_url` - URL pointing to the ZIP file
- `$plugin_id` - ID of the required plugin after installed, as used internally by WP (folder_name/file_name.php)
- `$type` - Type of requested script ('plugin' | 'theme'). Default: 'plugin'

### Example
The plugin **Inline Edit** depends on some functionalities from **WP Helper**, hosted in my Github. **Inline Edite** does not initialize if **WP Hepler** is not active and until it is activated there will be a warn in all admin pages.

    require_once plugin_dir_path(__FILE__) . "/rzp/require-zip-plugin.php";
    class InlineEdit {
        public function __construct() {
            global $wp_helper;
            $require_zip_plugin = new RequireZipPlugin('plugin');
            $require_zip_plugin->require(
                'Inline Edit', 
                'WP Helper', 
                'https://github.com/caugbr/wp-helper/archive/refs/heads/main.zip', 
                'wp-helper/wp-helper.php'
            );
            // To require a theme
            $require_zip_plugin->require(
                'Inline Edit', 
                'Vue WP Theme', 
                'https://github.com/caugbr/wp-helper/archive/refs/heads/main.zip', 
                'vue-wp-theme/style.css', // ID becames the path to style.css
                'theme' // Specify that it's a theme
            );
            if ($wp_helper) {
                // ...
            }
            // Test if the required theme is active
            if (wp_get_theme() == 'Vue WP Theme') {
                // ...
            }
        }

### The displayed message
First we tell the user that script X is dependent on plugin or theme Y. If it doesn't exist we show a link to download it (install). Then we ask them to activate the plugin/theme. There will be a messagevisible in admin  until the required script is active.

You can change the messages in the translation file (../langs/rzp-[your-language-code].po), observing the order of replacements (`%1$s`, `%2$s`, ...).
