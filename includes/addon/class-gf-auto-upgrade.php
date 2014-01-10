<?php
class GFAutoUpgrade{
    protected $_version;
    protected $_min_gravityforms_version;
    protected $_slug;
    protected $_title;
    protected $_full_path;
    protected $_path;
    protected $_url;
    protected $_is_gravityforms_supported;


    public function __construct($slug, $version, $min_gravityforms_version, $title, $full_path, $path, $url, $is_gravityforms_supported){
        $this->_slug = $slug;
        $this->_version = $version;
        $this->_min_gravityforms_version = $min_gravityforms_version;
        $this->_title = $title;
        $this->_full_path = $full_path;
        $this->_path = $path;
        $this->_url = $url;
        $this->_is_gravityforms_supported = $is_gravityforms_supported;
        add_action('init', array($this, 'init'));
    }

    public function init(){
        if (is_admin()) {
            load_plugin_textdomain($this->_slug, FALSE, $this->_slug . '/languages');

            add_filter('transient_update_plugins', array($this, 'check_update'));
            add_filter('site_transient_update_plugins', array($this, 'check_update'));
            add_action('install_plugins_pre_plugin-information', array($this, 'display_changelog'));
            add_action('gform_after_check_update', array($this, 'flush_version_info'));

            if (RG_CURRENT_PAGE == "plugins.php")
                add_action('after_plugin_row_' . $this->_path, array($this, 'rg_plugin_row'));

        }

        // ManageWP premium update filters
        add_filter('mwp_premium_update_notification', array($this, 'premium_update_push'));
        add_filter('mwp_premium_perform_update', array($this, 'premium_update'));
    }

    public function rg_plugin_row() {

        if (!$this->_is_gravityforms_supported) {
            $message = sprintf(__("Gravity Forms " . $this->_min_gravityforms_version . " is required. Activate it now or %spurchase it today!%s", "gravityforms"), "<a href='http://www.gravityforms.com'>", "</a>");
            GFAddOn::display_plugin_message($message, true);
        } else {
            $version_info = $this->get_version_info($this->_slug);

            if (!$version_info["is_valid_key"]) {
                $title       = $this->_title;
                $new_version = version_compare($this->_version, $version_info["version"], '<') ? __("There is a new version of {$title} available.", 'gravityforms') . " <a class='thickbox' title='{$title}' href='plugin-install.php?tab=plugin-information&plugin=" . $this->_slug . "&TB_iframe=true&width=640&height=808'>" . sprintf(__('View version %s Details', 'gravityforms'), $version_info["version"]) . '</a>. ' : '';
                $message     = $new_version . sprintf(__('%sRegister%s your copy of Gravity Forms to receive access to automatic upgrades and support. Need a license key? %sPurchase one now%s.', 'gravityforms'), '<a href="admin.php?page=gf_settings">', '</a>', '<a href="http://www.gravityforms.com">', '</a>') . '</div></td>';
                GFAddOn::display_plugin_message($message);
            }
        }
    }

    //Integration with ManageWP
    public function premium_update_push($premium_update) {

        if (!function_exists('get_plugin_data'))
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        $update = $this->get_version_info($this->_slug);
        if ($update["is_valid_key"] == true && version_compare($this->_version, $update["version"], '<')) {
            $plugin_data                = get_plugin_data($this->_full_path);
            $plugin_data['type']        = 'plugin';
            $plugin_data['slug']        = $this->_path;
            $plugin_data['new_version'] = isset($update['version']) ? $update['version'] : false;
            $premium_update[]           = $plugin_data;
        }

        return $premium_update;
    }

    //Integration with ManageWP
    public function premium_update($premium_update) {

        if (!function_exists('get_plugin_data'))
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        $update = $this->get_version_info($this->_slug);
        if ($update["is_valid_key"] == true && version_compare($this->_version, $update["version"], '<')) {
            $plugin_data         = get_plugin_data($this->_full_path);
            $plugin_data['slug'] = $this->_path;
            $plugin_data['type'] = 'plugin';
            $plugin_data['url']  = isset($update["url"]) ? $update["url"] : false; // OR provide your own callback function for managing the update

            array_push($premium_update, $plugin_data);
        }

        return $premium_update;
    }

    public function flush_version_info() {
        $this->set_version_info($this->_slug, false);
    }

    private function set_version_info($plugin_slug, $version_info){
        if ( function_exists('set_site_transient') )
            set_site_transient($plugin_slug . "_version", $version_info, 60*60*12);
        else
            set_transient($plugin_slug . "_version", $version_info, 60*60*12);
    }

    public function check_update($option){

        $key = $this->get_key();

        $version_info = $this->get_version_info($this->_slug);

        if ( rgar($version_info, "is_error") == "1")
            return $option;

        if(empty($option->response[$this->_path]))
            $option->response[$this->_path] = new stdClass();

        //Empty response means that the key is invalid. Do not queue for upgrade
        if(!$version_info["is_valid_key"] || version_compare($this->_version, $version_info["version"], '>=')){
            unset($option->response[$this->_path]);
        }
        else{
            $option->response[$this->_path]->url = $this->_url;
            $option->response[$this->_path]->slug = $this->_slug;
            $option->response[$this->_path]->package = str_replace("{KEY}", $key, $version_info["url"]);
            $option->response[$this->_path]->new_version = $version_info["version"];
            $option->response[$this->_path]->id = "0";
        }

        return $option;

    }

    private function display_upgrade_message($plugin_name, $plugin_title, $version, $message, $localization_namespace){
        $upgrade_message = $message .' <a class="thickbox" title="'. $plugin_title .'" href="plugin-install.php?tab=plugin-information&plugin=' . $plugin_name . '&TB_iframe=true&width=640&height=808">'. sprintf(__('View version %s Details', $localization_namespace), $version) . '</a>. ';
        GFAddOn::display_plugin_message($upgrade_message);
    }

    // Displays current version details on plugins page
    public function display_changelog(){
        if ($_REQUEST["plugin"] != $this->_slug)
            return;
        $key = $this->get_key();
        $body = "key=$key";
        $options = array('method' => 'POST', 'timeout' => 3, 'body' => $body);
        $options['headers'] = array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option('blog_charset'),
            'Content-Length' => strlen($body),
            'User-Agent' => 'WordPress/' . get_bloginfo("version"),
            'Referer' => get_bloginfo("url")
        );

        $raw_response = wp_remote_request(GRAVITY_MANAGER_URL . "/changelog.php?" . $this->get_remote_request_params($this->_slug, $key, $this->_version), $options);

        if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code']){
            $page_text = sprintf(__("Oops!! Something went wrong.%sPlease try again or %scontact us%s.", 'gravityforms'), "<br/>", "<a href='http://www.gravityforms.com'>", "</a>");
        }
        else{
            $page_text = $raw_response['body'];
            if(substr($page_text, 0, 10) != "<!--GFM-->")
                $page_text = "";
        }
        echo stripslashes($page_text);

        exit;
    }

    private function get_version_info($offering, $use_cache=true){

        $version_info = GFCommon::get_version_info($use_cache);
        $is_valid_key = $version_info["is_valid_key"] && rgars($version_info, "offerings/{$offering}/is_available");

        $info = array("is_valid_key" => $is_valid_key, "version" => rgars($version_info, "offerings/{$offering}/version"), "url" => rgars($version_info, "offerings/{$offering}/url"));

        return $info;
    }

    private function get_remote_request_params($offering, $key, $version){
        global $wpdb;
        return sprintf("of=%s&key=%s&v=%s&wp=%s&php=%s&mysql=%s", urlencode($offering), urlencode($key), urlencode($version), urlencode(get_bloginfo("version")), urlencode(phpversion()), urlencode($wpdb->db_version()));
    }

    private function get_key() {
        if ($this->_is_gravityforms_supported)
            return GFCommon::get_key();
        else
            return "";
    }


}
