<?php
/*
Plugin Name: Gravity Forms
Plugin URI: http://www.gravityforms.com
Description: Easily create web forms and manage form entries within the WordPress admin.
Version: 1.8.19
Author: rocketgenius
Author URI: http://www.rocketgenius.com
Text Domain: gravityforms
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2014 Rocketgenius Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

//------------------------------------------------------------------------------------------------------------------
//---------- Gravity Forms License Key -----------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------
//If you hardcode a Gravity Forms License Key here, it will automatically populate on activation.
$gf_license_key = "";

//-- OR ---//

//You can also add the Gravity Forms license key to your wp-config.php file to automatically populate on activation
//Add the code in the comment below to your wp-config.php to do so:
//define('GF_LICENSE_KEY','YOUR_KEY_GOES_HERE');
//------------------------------------------------------------------------------------------------------------------

//------------------------------------------------------------------------------------------------------------------
//---------- reCAPTCHA Keys -----------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------
//If you hardcode your reCAPTCHA Keys here, it will automatically populate on activation.
$gf_recaptcha_private_key = "";
$gf_recaptcha_public_key = "";

//-- OR ---//

//You can also add the reCAPTCHA keys to your wp-config.php file to automatically populate on activation
//Add the two lines of code in the comment below to your wp-config.php to do so:
//define('GF_RECAPTCHA_PRIVATE_KEY','YOUR_PRIVATE_KEY_GOES_HERE');
//define('GF_RECAPTCHA_PUBLIC_KEY','YOUR_PUBLIC_KEY_GOES_HERE');
//------------------------------------------------------------------------------------------------------------------

if(!defined("ABSPATH")){
    die();
}

if(!defined("RG_CURRENT_PAGE"))
    define("RG_CURRENT_PAGE", basename($_SERVER['PHP_SELF']));

if(!defined("IS_ADMIN")){
    define("IS_ADMIN",  is_admin());
}

define("RG_CURRENT_VIEW", RGForms::get("view"));
define("GF_MIN_WP_VERSION", '3.7');
define("GF_SUPPORTED_WP_VERSION", version_compare(get_bloginfo("version"), GF_MIN_WP_VERSION, '>='));

if(!defined("GRAVITY_MANAGER_URL"))
    define("GRAVITY_MANAGER_URL", "http://www.gravityhelp.com/wp-content/plugins/gravitymanager");

if(!defined("GRAVITY_MANAGER_PROXY_URL"))
    define('GRAVITY_MANAGER_PROXY_URL', 'http://proxy.gravityplugins.com');

require_once( plugin_dir_path( __FILE__ ) . 'common.php' );
require_once( plugin_dir_path( __FILE__ ) . 'forms_model.php' );
require_once( plugin_dir_path( __FILE__ ) . 'widget.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/api.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/webapi/webapi.php' );

// GFCommon::$version is deprecated, set it to current version for backwards compat
GFCommon::$version = GFForms::$version;

add_action('init',  array('RGForms', 'init'));
add_action('wp',  array('RGForms', 'maybe_process_form'), 9);
add_action('wp',  array('RGForms', 'process_exterior_pages'));
add_filter('upgrader_pre_install', array("GFForms", 'validate_upgrade'), 10, 2);

add_filter('user_has_cap', array("RGForms", "user_has_cap"), 10, 3);

//Hooks for no-conflict functionality
if(is_admin() && (RGForms::is_gravity_page() || RGForms::is_gravity_ajax_action())){
    add_action("wp_print_scripts", array("RGForms", "no_conflict_mode_script"), 1000);
    add_action("admin_print_footer_scripts", array("RGForms", "no_conflict_mode_script"), 9);

    add_action("wp_print_styles", array("RGForms", "no_conflict_mode_style"), 1000);
    add_action("admin_print_styles", array("RGForms", "no_conflict_mode_style"), 1);
    add_action("admin_print_footer_scripts", array("RGForms", "no_conflict_mode_style"), 1);
    add_action("admin_footer", array("RGForms", "no_conflict_mode_style"), 1);
}

add_action( 'plugins_loaded', array( 'GFForms', 'loaded' ) );

class GFForms {

	public static $version = '1.8.19';

	public static function loaded(){
		do_action( 'gform_loaded' );

		//initializing Add-Ons if necessary
		if ( class_exists( 'GFAddOn' ) ) {
			GFAddOn::init_addons();
		}
	}

    public static function has_members_plugin(){
        return function_exists( 'members_get_capabilities' );
    }

    //Plugin starting point. Will load appropriate files
    public static function init(){

    	//initializing translations
		load_plugin_textdomain( 'gravityforms', false, '/gravityforms/languages' );

        add_filter("gform_logging_supported", array("RGForms", "set_logging_supported"));
        add_action( 'admin_head', array( 'GFCommon', 'maybe_output_gf_vars' ) );

        self::register_scripts();

        //Maybe set up Gravity Forms: only on admin requests for single site installation and always for multisite
        if( (IS_ADMIN && false === ( defined("DOING_AJAX") && true === DOING_AJAX ) ) || is_multisite() ){
            self::setup();
        }


        if(IS_ADMIN){

            global $current_user;

            //Members plugin integration. Adding Gravity Forms roles to the checkbox list
            if (self::has_members_plugin())
                add_filter('members_get_capabilities', array("RGForms", "members_get_capabilities"));

            if(is_multisite()) {
                add_filter('wpmu_drop_tables', array('GFFormsModel', 'mu_drop_tables'));
            }

            add_action('admin_enqueue_scripts', array('GFForms', 'enqueue_admin_scripts'));

            //Loading Gravity Forms if user has access to any functionality
            if(GFCommon::current_user_can_any(GFCommon::all_caps()))
            {
                require_once(GFCommon::get_base_path() . "/export.php");
                GFExport::maybe_export();

                //imports theme forms if configured to be automatic imported
                self::maybe_import_theme_forms();

                //creates the "Forms" left menu
                add_action('admin_menu',  array('RGForms', 'create_menu'));

                if(GF_SUPPORTED_WP_VERSION){

                    add_action('admin_footer',  array('RGForms', 'check_upload_folder'));
                    add_action('wp_dashboard_setup', array('RGForms', 'dashboard_setup'));

                    // Support modifying the admin page title for settings
                    add_filter( 'admin_title' , array( __class__, 'modify_admin_title' ), 10, 2 );

                    //Adding "embed form" button
                    add_action('media_buttons', array('RGForms', 'add_form_button'), 20);

                    // Add "Form" to the "New" menu in WP admin bar
                    add_action( 'wp_before_admin_bar_render', array('GFForms', 'admin_bar') );

                    //Plugin update actions
                    add_filter("transient_update_plugins", array('RGForms', 'check_update'));
                    add_filter("site_transient_update_plugins", array('RGForms', 'check_update'));

                    require_once(GFCommon::get_base_path() . "/includes/locking/locking.php");

                    if(self::page_supports_add_form_button()){
                        add_action('admin_footer',  array('RGForms', 'add_mce_popup'));
                    }

                    if(self::is_gravity_page()){
                        require_once(GFCommon::get_base_path() . "/tooltips.php");
                        add_action("admin_print_scripts", array('RGForms', 'print_scripts'));
                    }
                    else if(RG_CURRENT_PAGE == 'media-upload.php'){
                        require_once(GFCommon::get_base_path() . "/entry_list.php");
                    }
                    else if(in_array(RG_CURRENT_PAGE, array("admin.php", "admin-ajax.php"))){

                        add_action('wp_ajax_rg_save_form', array('RGForms', 'save_form'));
                        add_action('wp_ajax_rg_change_input_type', array('RGForms', 'change_input_type'));
                        add_action('wp_ajax_rg_add_field', array('RGForms', 'add_field'));
                        add_action('wp_ajax_rg_duplicate_field', array('RGForms', 'duplicate_field'));
                        add_action('wp_ajax_rg_delete_field', array('RGForms', 'delete_field'));
                        add_action('wp_ajax_rg_delete_file', array('RGForms', 'delete_file'));
                        add_action('wp_ajax_rg_select_export_form', array('RGForms', 'select_export_form'));
                        add_action('wp_ajax_rg_start_export', array('RGForms', 'start_export'));
                        add_action('wp_ajax_gf_upgrade_license', array('RGForms', 'upgrade_license'));
                        add_action('wp_ajax_gf_delete_custom_choice', array('RGForms', 'delete_custom_choice'));
                        add_action('wp_ajax_gf_save_custom_choice', array('RGForms', 'save_custom_choice'));
                        add_action('wp_ajax_gf_get_post_categories', array('RGForms', 'get_post_category_values'));
                        add_action('wp_ajax_gf_get_notification_post_categories', array('RGForms', 'get_notification_post_category_values'));
                        add_action('wp_ajax_gf_save_confirmation', array('RGForms', 'save_confirmation'));
                        add_action('wp_ajax_gf_delete_confirmation', array('RGForms', 'delete_confirmation'));
                        add_action('wp_ajax_gf_save_new_form', array('RGForms', 'save_new_form'));

                        //entry list ajax operations
                        add_action('wp_ajax_rg_update_lead_property', array('RGForms', 'update_lead_property'));
                        add_action('wp_ajax_delete-gf_entry', array('RGForms', 'update_lead_status'));

                        //form list ajax operations
                        add_action('wp_ajax_rg_update_form_active', array('RGForms', 'update_form_active'));

                        //notification list ajax operations
                        add_action('wp_ajax_rg_update_notification_active', array('RGForms', 'update_notification_active'));

                        //confirmation list ajax operations
                        add_action('wp_ajax_rg_update_confirmation_active', array('RGForms', 'update_confirmation_active'));

                        //dynamic captcha image
                        add_action('wp_ajax_rg_captcha_image', array('RGForms', 'captcha_image'));

                        //dashboard message "dismiss upgrade" link
                        add_action("wp_ajax_rg_dismiss_upgrade", array('RGForms', 'dashboard_dismiss_upgrade'));

                        // entry detail: resend notifications
                        add_action("wp_ajax_gf_resend_notifications", array('RGForms', 'resend_notifications'));

                    }

                    add_filter("plugins_api", array("RGForms", "get_addon_info"), 100, 3);
                    add_action('after_plugin_row_gravityforms/gravityforms.php', array('RGForms', 'plugin_row') );
                    add_action('install_plugins_pre_plugin-information', array('RGForms', 'display_changelog'));
                    add_filter('plugin_action_links', array('RGForms', 'plugin_settings_link'),10,2);
                }
            }

        }
        else{
            add_action('wp_enqueue_scripts', array('RGForms', 'enqueue_scripts'), 11 );
            add_action('wp', array('RGForms', 'ajax_parse_request'), 10);
        }

        add_shortcode('gravityform', array('RGForms', 'parse_shortcode'));
        add_shortcode('gravityforms', array('RGForms', 'parse_shortcode'));

        // ManageWP premium update filters
        add_filter( 'mwp_premium_update_notification', array('RGForms', 'premium_update_push') );
        add_filter( 'mwp_premium_perform_update', array('RGForms', 'premium_update') );

        // Push Gravity Forms to the top of the list of plugins to make sure it's loaded before any add-ons
        add_action("activated_plugin", array("GFForms", "load_first"));
    }

    public static function load_first() {
        $plugin_path = basename(dirname(__FILE__)) . "/gravityforms.php";
        $active_plugins = get_option('active_plugins');
        $key = array_search($plugin_path, $active_plugins);
        if ($key > 0) {
            array_splice($active_plugins, $key, 1);
            array_unshift($active_plugins, $plugin_path);
            update_option('active_plugins', $active_plugins);
        }
    }

    public static function set_logging_supported($plugins){
        $plugins["gravityforms"] = "Gravity Forms Core";
        return $plugins;
    }

    public static function maybe_process_form(){

        $form_id = isset($_POST["gform_submit"]) ? absint($_POST["gform_submit"]) : 0;
        if($form_id){
            $form_info = RGFormsModel::get_form($form_id);
            $is_valid_form = $form_info && $form_info->is_active;

            if($is_valid_form){
                require_once(GFCommon::get_base_path() . "/form_display.php");
                GFFormDisplay::process_form($form_id);
            }
        }
    }

    public static function process_exterior_pages(){
        if(rgempty("gf_page", $_GET))
            return;

        if("upload" === rgget("gf_page")){
            require_once(GFCommon::get_base_path() . "/includes/upload.php");
            exit();
        }


        //ensure users are logged in
        if(!is_user_logged_in())
            auth_redirect();

        switch(rgget("gf_page")){
            case "preview":
                require_once(GFCommon::get_base_path() . "/preview.php");
            break;

            case "print-entry" :
                require_once(GFCommon::get_base_path() . "/print-entry.php");
            break;

            case "select_columns" :
                require_once(GFCommon::get_base_path() . "/select_columns.php");
            break;
        }
        exit();
    }

    public static function check_update($update_plugins_option){
        if(!class_exists("GFCommon"))
            require_once("common.php");

        return GFCommon::check_update($update_plugins_option, true);
    }

    //Creates or updates database tables. Will only run when version changes
    public static function setup($force_setup = false){
        global $wpdb;

        $has_version_changed = get_option("rg_form_version") != GFCommon::$version;
        if($has_version_changed){
            //Making sure version has really changed. Gets around aggressive caching issue on some sites that cause setup to run multiple times.
            $has_version_changed = self::get_wp_option("rg_form_version") != GFCommon::$version;
        }

        if($has_version_changed || $force_setup){

            $blog_id = get_current_blog_id();

            GFCommon::log_debug("Blog {$blog_id} - Beginning of setup. From version " . get_option("rg_form_version") . " to version " . GFCommon::$version);

            //setting up database structure
            self::setup_database();

            //auto-setting and auto-validating license key based on value configured via the GF_LICENSE_KEY constant or the gf_license_key variable
            //auto-populating reCAPTCHA keys base on constant
            self::maybe_populate_keys();

            //Auto-importing forms based on GF_IMPORT_FILE AND GF_THEME_IMPORT_FILE
            self::maybe_import_forms();

            //The format the version info changed to JSON. Make sure the old format is not cached.
            if(version_compare(get_option("rg_form_version"), "1.8.0.3", "<" )){
                delete_transient("gform_update_info");
            }

            update_option("rg_form_version", GFCommon::$version);

            GFCommon::log_debug("Blog {$blog_id} - End of setup.");

        }
    }


    public static function setup_database()
    {
        global $wpdb;

        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";

        //Fixes issue with dbDelta lower-casing table names, which cause problems on case sensitive DB servers.
        add_filter('dbdelta_create_queries', array("RGForms", "dbdelta_fix_case"));

        //------ FORM -----------------------------------------------
        $form_table_name = RGFormsModel::get_form_table_name();
        $sql = "CREATE TABLE " . $form_table_name . " (
              id mediumint(8) unsigned not null auto_increment,
              title varchar(150) not null,
              date_created datetime not null,
              is_active tinyint(1) not null default 1,
              is_trash tinyint(1) not null default 0,
              PRIMARY KEY  (id)
            ) $charset_collate;";
        dbDelta($sql);

        //droping table that was created by mistake in version 1.6.3.2
        $wpdb->query("DROP TABLE IF EXISTS A" . $form_table_name);

        //------ META -----------------------------------------------
        $meta_table_name = RGFormsModel::get_meta_table_name();
        $sql = "CREATE TABLE " . $meta_table_name . " (
              form_id mediumint(8) unsigned not null,
              display_meta longtext,
              entries_grid_meta longtext,
              confirmations longtext,
              notifications longtext,
              PRIMARY KEY  (form_id)
            ) $charset_collate;";
        dbDelta($sql);

        //droping outdated form_id index (if one exists)
        self::drop_index($meta_table_name, 'form_id');

        //------ FORM VIEW -----------------------------------------------
        $form_view_table_name = RGFormsModel::get_form_view_table_name();
        $sql = "CREATE TABLE " . $form_view_table_name . " (
              id bigint(20) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              date_created datetime not null,
              ip char(15),
              count mediumint(8) unsigned not null default 1,
              PRIMARY KEY  (id),
              KEY form_id (form_id)
            ) $charset_collate;";
        dbDelta($sql);

        //------ LEAD -----------------------------------------------
        $lead_table_name = RGFormsModel::get_lead_table_name();
        $sql = "CREATE TABLE " . $lead_table_name . " (
              id int(10) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              post_id bigint(20) unsigned,
              date_created datetime not null,
              is_starred tinyint(1) not null default 0,
              is_read tinyint(1) not null default 0,
              ip varchar(39) not null,
              source_url varchar(200) not null default '',
              user_agent varchar(250) not null default '',
              currency varchar(5),
              payment_status varchar(15),
              payment_date datetime,
              payment_amount decimal(19,2),
              payment_method varchar(30),
              transaction_id varchar(50),
              is_fulfilled tinyint(1),
              created_by bigint(20) unsigned,
              transaction_type tinyint(1),
              status varchar(20) not null default 'active',
              PRIMARY KEY  (id),
              KEY form_id (form_id),
              KEY status (status)
            ) $charset_collate;";
        dbDelta($sql);

        //------ LEAD NOTES ------------------------------------------
        $lead_notes_table_name = RGFormsModel::get_lead_notes_table_name();
        $sql = "CREATE TABLE " . $lead_notes_table_name . " (
              id int(10) unsigned not null auto_increment,
              lead_id int(10) unsigned not null,
              user_name varchar(250),
              user_id bigint(20),
              date_created datetime not null,
              value longtext,
              note_type varchar(50),
              PRIMARY KEY  (id),
              KEY lead_id (lead_id),
              KEY lead_user_key (lead_id,user_id)
            ) $charset_collate;";
        dbDelta($sql);

        //------ LEAD DETAIL -----------------------------------------
        $lead_detail_table_name = RGFormsModel::get_lead_details_table_name();
        $sql = "CREATE TABLE " . $lead_detail_table_name . " (
              id bigint(20) unsigned not null auto_increment,
              lead_id int(10) unsigned not null,
              form_id mediumint(8) unsigned not null,
              field_number float not null,
              value varchar(" . GFORMS_MAX_FIELD_LENGTH . "),
              PRIMARY KEY  (id),
              KEY form_id (form_id),
              KEY lead_id (lead_id),
              KEY lead_field_number (lead_id,field_number)
            ) $charset_collate;";
        dbDelta($sql);

        //------ LEAD DETAIL LONG -----------------------------------
        $lead_detail_long_table_name = RGFormsModel::get_lead_details_long_table_name();

        $sql = "CREATE TABLE " . $lead_detail_long_table_name . " (
              lead_detail_id bigint(20) unsigned not null,
              value longtext,
              PRIMARY KEY  (lead_detail_id)
            ) $charset_collate;";
        dbDelta($sql);

        //droping outdated form_id index (if one exists)
        self::drop_index($lead_detail_long_table_name, 'lead_detail_key');

        //------ LEAD META -----------------------------------
        $lead_meta_table_name = RGFormsModel::get_lead_meta_table_name();
        $sql = "CREATE TABLE " . $lead_meta_table_name . " (
              id bigint(20) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null default 0,
              lead_id bigint(20) unsigned not null,
              meta_key varchar(255),
              meta_value longtext,
              PRIMARY KEY  (id),
              KEY meta_key (meta_key),
              KEY lead_id (lead_id),
              KEY form_id_meta_key (form_id,meta_key)
            ) $charset_collate;";
        dbDelta($sql);

        remove_filter('dbdelta_create_queries', array("RGForms", "dbdelta_fix_case"));

        //fix form_id value needed to update from version 1.6.11
        self::fix_lead_meta_form_id_values();

        //fix checkbox value. needed for version 1.0 and below but won't hurt for higher versions
        self::fix_checkbox_value();

        //fix leading and trailing spaces in Form objects and entry values
        if(version_compare(get_option("rg_form_version"), "1.8.3.1", "<" )){
            self::fix_leading_and_trailing_spaces();
        }

    }

    private static function fix_leading_and_trailing_spaces(){

        global $wpdb;

        $meta_table_name =  GFFormsModel::get_meta_table_name();
        $lead_details_table = GFFormsModel::get_lead_details_table_name();
        $lead_details_long_table = GFFormsModel::get_lead_details_long_table_name();

        $result = $wpdb->query("UPDATE $lead_details_table SET value = TRIM(value)");
        $result = $wpdb->query("UPDATE $lead_details_long_table SET value = TRIM(value)");


        $results = $wpdb->get_results("SELECT form_id, display_meta, confirmations, notifications FROM {$meta_table_name}", ARRAY_A);

        foreach ($results as &$result) {
            $form_id = $result["form_id"];

            $form = GFFormsModel::unserialize($result["display_meta"]);
            $form_updated = false;
            $form = GFFormsModel::trim_form_meta_values($form, $form_updated);
            if($form_updated){
                GFFormsModel::update_form_meta($form_id, $form);
            }

            $confirmations = GFFormsModel::unserialize($result["confirmations"]);
            $confirmations_updated = false;
            $confirmations = GFFormsModel::trim_conditional_logic_values($confirmations, $form, $confirmations_updated);
            if($confirmations_updated){
                GFFormsModel::update_form_meta($form_id, $confirmations, "confirmations");
            }

            $notifications = GFFormsModel::unserialize($result["notifications"]);
            $notifications_updated = false;
            $notifications = GFFormsModel::trim_conditional_logic_values($notifications, $form, $notifications_updated);
            if($notifications_updated){
                GFFormsModel::update_form_meta($form_id, $notifications, "notifications");
            }

        }

        return $results;
    }

    private static function maybe_import_forms()
    {
        if (defined("GF_IMPORT_FILE") && !get_option("gf_imported_file")) {

            require_once(GFCommon::get_base_path() . "/export.php");

            GFExport::import_file(GF_IMPORT_FILE);

            update_option("gf_imported_file", true);
        }
    }

    private static function maybe_import_theme_forms(){

        //Import theme specific forms if configured. Will only import forms once per theme.
        if(defined("GF_THEME_IMPORT_FILE")){
            $themes = get_option("gf_imported_theme_file");
            if(!is_array($themes))
                $themes = array();

            //if current theme has already imported it's forms, don't import again
            $theme = get_template();
            if(!isset($themes[$theme])){

                require_once(GFCommon::get_base_path() . "/export.php");

                //importing forms
                GFExport::import_file(get_stylesheet_directory() . "/" . GF_THEME_IMPORT_FILE);

                //adding current theme to the list of imported themes. So that forms are not imported again for it.
                $themes[$theme] = true;
                update_option("gf_imported_theme_file", $themes);
            }
        }

    }

    private static function maybe_populate_keys(){

        global $gf_license_key;
        $license_key = defined("GF_LICENSE_KEY") && empty($gf_license_key) ? GF_LICENSE_KEY : $gf_license_key;
        if(!empty($license_key)){
            RGFormsModel::save_key($license_key);
            GFCommon::cache_remote_message();
            GFCommon::get_version_info(false);
        }

        //auto-setting recaptcha keys based on value configured via the constant or global variable
        global $gf_recaptcha_public_key, $gf_recaptcha_private_key;
        $private_key = defined("GF_RECAPTCHA_PRIVATE_KEY") && empty($gf_recaptcha_private_key) ? GF_RECAPTCHA_PRIVATE_KEY : $gf_recaptcha_private_key;
        if(!empty($private_key))
            update_option("rg_gforms_captcha_private_key", $private_key);

        $public_key = defined("GF_RECAPTCHA_PUBLIC_KEY") && empty($gf_recaptcha_public_key) ? GF_RECAPTCHA_PUBLIC_KEY : $gf_recaptcha_public_key;
        if(!empty($public_key))
            update_option("rg_gforms_captcha_public_key", $public_key);

    }

    public static function get_wp_option($option_name){
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name=%s", $option_name));
    }

	//Changes form_id values from default value "0" to the correct value. Needed when upgrading users from 1.6.11
    private static function fix_lead_meta_form_id_values(){
        global $wpdb;

        $lead_meta_table_name = RGFormsModel::get_lead_meta_table_name();
		$lead_table_name = RGFormsModel::get_lead_table_name();

        $sql = "UPDATE $lead_meta_table_name lm,$lead_table_name l SET lm.form_id = l.form_id
				WHERE lm.form_id=0 AND lm.lead_id = l.id;
				";
        $wpdb->get_results($sql);

    }

    public static function dbdelta_fix_case($cqueries){
        foreach ($cqueries as $table => $qry) {
            $table_name = $table;
            if(preg_match("|CREATE TABLE ([^ ]*)|", $qry, $matches)){
                $query_table_name = trim($matches[1], '`' );

                //fix table names that are different just by their casing
                if(strtolower($query_table_name) == $table){
                    $table_name = $query_table_name;
                }
            }
            $queries[$table_name] = $qry;
        }
        return $queries;
    }

    public static function no_conflict_mode_style(){
        if(!get_option("gform_enable_noconflict"))
            return;

        global $wp_styles;
        $wp_required_styles = array("admin-bar", "colors", "ie", "wp-admin", "editor-style");
        $gf_required_styles = array(
            "common" => array(),
            "gf_edit_forms" => array("thickbox", "editor-buttons", "wp-jquery-ui-dialog", "media-views", "buttons", "wp-pointer" ),
            "gf_edit_forms_notification" => array("thickbox", "editor-buttons", "wp-jquery-ui-dialog", "media-views", "buttons"),
            "gf_new_form" => array("thickbox"),
            "gf_entries" => array("thickbox"),
            "gf_settings" => array(),
            "gf_export" => array(),
            "gf_help" => array()
        );

        self::no_conflict_mode($wp_styles, $wp_required_styles, $gf_required_styles, "styles");
    }


    public static function no_conflict_mode_script(){
        if(!get_option("gform_enable_noconflict"))
            return;

        global $wp_scripts;

        $wp_required_scripts = array("admin-bar", "common", "jquery-color", "utils");
        $gf_required_scripts = array(
            "common" => array("gform_tooltip_init", "sack"),
            "gf_edit_forms" => array("backbone", "editor", "gform_floatmenu", "gform_forms", "gform_form_admin", "gform_form_editor", "gform_gravityforms", "gform_json", "gform_menu", "gform_placeholder", "jquery-ui-autocomplete", "jquery-ui-core", "jquery-ui-datepicker", "jquery-ui-sortable", "jquery-ui-tabs", "json2", "media-editor", "media-models", "media-upload", "media-views", "plupload", "plupload-flash", "plupload-html4", "plupload-html5", "quicktags", "rg_currency", "thickbox", "word-count", "wp-plupload", "wpdialogs-popup", "wplink", "wp-pointer"),
            "gf_edit_forms_notification" => array("editor", "word-count", "quicktags", "wpdialogs-popup", "media-upload", "wplink", "backbone", "jquery-ui-sortable", "json2", "media-editor", "media-models", "media-views", "plupload", "plupload-flash", "plupload-html4", "plupload-html5", "plupload-silverlight", "wp-plupload", "gform_placeholder", "gform_json", "jquery-ui-autocomplete"),
            "gf_new_form" => array("thickbox", "jquery-ui-core", "jquery-ui-sortable", "jquery-ui-tabs", "rg_currency", "gform_gravityforms", "gform_json", "gform_form_admin" ),
            "gf_entries" => array("thickbox", "gform_gravityforms", "wp-lists", "gform_json", "gform_field_filter","plupload-all"),
            "gf_settings" => array(),
            "gf_export" => array("gform_form_admin","jquery-ui-datepicker","gform_field_filter"),
            "gf_help" => array(),
        );

        self::no_conflict_mode($wp_scripts, $wp_required_scripts, $gf_required_scripts, "scripts");
    }

    private static function no_conflict_mode(&$wp_objects, $wp_required_objects, $gf_required_objects, $type="scripts"){

        $current_page = trim(strtolower(rgget("page")));
        if(empty($current_page))
            $current_page = trim(strtolower(rgget("gf_page")));
        if(empty($current_page))
            $current_page = RG_CURRENT_PAGE;

        $view = rgempty("view", $_GET) ? "default" : rgget("view");
        $page_objects = isset($gf_required_objects[$current_page . "_" . $view]) ? $gf_required_objects[$current_page . "_" . $view] : rgar($gf_required_objects, $current_page);

        //disable no-conflict if $page_objects is false
        if($page_objects === false)
            return;

        if(!is_array($page_objects))
            $page_objects = array();

        //merging wp scripts with gravity forms scripts
        $required_objects = array_merge($wp_required_objects, $gf_required_objects["common"], $page_objects);

        //allowing addons or other products to change the list of no conflict scripts
        $required_objects = apply_filters("gform_noconflict_{$type}", $required_objects);

        $queue = array();
        foreach($wp_objects->queue as $object){
            if(in_array($object, $required_objects))
                $queue[] = $object;
        }
        $wp_objects->queue = $queue;

        $required_objects = self::add_script_dependencies($wp_objects->registered, $required_objects);

        //unregistering scripts
        $registered = array();
        foreach($wp_objects->registered as $script_name => $script_registration){
            if(in_array($script_name, $required_objects)){
                $registered[$script_name] = $script_registration;
            }
        }
        $wp_objects->registered = $registered;
    }

    private static function add_script_dependencies($registered, $scripts){

        //gets all dependent scripts linked to the $scripts array passed
        do{
            $dependents = array();
            foreach($scripts as $script){
                $deps = isset($registered[$script]) && is_array($registered[$script]->deps) ? $registered[$script]->deps : array();
                foreach($deps as $dep){
                    if(!in_array($dep, $scripts) && !in_array($dep, $dependents)){
                        $dependents[] = $dep;
                    }
                }
            }
            $scripts = array_merge($scripts, $dependents);
        }while(!empty($dependents));

        return $scripts;
    }

    //Integration with ManageWP
    public static function premium_update_push( $premium_update ){

        if( !function_exists( 'get_plugin_data' ) )
            include_once( ABSPATH.'wp-admin/includes/plugin.php');

        $update = GFCommon::get_version_info();
        if( rgar($update, "is_valid_key") == true && version_compare(GFCommon::$version, $update["version"], '<') ){
            $gforms = get_plugin_data( __FILE__ );
            $gforms['type'] = 'plugin';
            $gforms['slug'] = 'gravityforms/gravityforms.php';
            $gforms['new_version'] = !rgempty("version", $update) ? $update['version'] : false ;
            $premium_update[] = $gforms;
        }

        return $premium_update;
    }

    //Integration with ManageWP
    public static function premium_update( $premium_update ){

        if( !function_exists( 'get_plugin_data' ) )
            include_once( ABSPATH.'wp-admin/includes/plugin.php');

        $update = GFCommon::get_version_info();
        if( rgar($update,"is_valid_key") == true && version_compare(GFCommon::$version, $update["version"], '<') ){
            $gforms = get_plugin_data( __FILE__ );
            $gforms['slug'] = 'gravityforms/gravityforms.php'; // If not set by default, always pass theme template
            $gforms['type'] = 'plugin';
            $gforms['url'] = !rgempty("url",$update) ? $update["url"] : false; // OR provide your own callback function for managing the update

            array_push($premium_update, $gforms);
        }
        return $premium_update;
    }

    public static function drop_index($table, $index){
        global $wpdb;
        $has_index = $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name='{$index}'");
        if($has_index){
            $wpdb->query("DROP INDEX {$index} ON {$table}");
        }
    }

    public static function validate_upgrade($do_upgrade, $hook_extra){

        if(rgar($hook_extra,"plugin") == "gravityforms/gravityforms.php" && !GFForms::has_database_permission($error)){
            return new WP_Error("no_db_permission", $error);
        }

        return true;
    }

    private static function has_database_permission(&$error){
        global $wpdb;

        $wpdb->hide_errors();

        $has_permission = true;

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rg_test ( col1 int )";
        $wpdb->query($sql);
        $error = "Current database user does not have necessary permissions to create tables. Gravity Forms requires that the database user has CREATE and ALTER permissions. If you need assistance in changing database user permissions, contact your hosting provider.";
        if(!empty($wpdb->last_error))
            $has_permission = false;

        if($has_permission){
            $sql = "ALTER TABLE {$wpdb->prefix}rg_test ADD COLUMN a" . uniqid() ." int";
            $wpdb->query($sql);
            $error = "Current database user does not have necessary permissions to modify (ALTER) tables. Gravity Forms requires that the database user has CREATE and ALTER permissions. If you need assistance in changing database user permissions, contact your hosting provider.";
            if(!empty($wpdb->last_error))
                $has_permission = false;

            $sql = "DROP TABLE {$wpdb->prefix}rg_test";
            $wpdb->query($sql);
        }

        $wpdb->show_errors();

        return $has_permission;
    }

    //Changes checkbox entry values from "!" to the current choice text. Neededed when upgrading users from 1.0
    private static function fix_checkbox_value(){
        global $wpdb;

        $table_name = RGFormsModel::get_lead_details_table_name();

        $sql = "select * from $table_name where value= '!'";
        $results = $wpdb->get_results($sql);
        foreach($results as $result){
            $form = RGFormsModel::get_form_meta($result->form_id);
            $field = RGFormsModel::get_field($form, $result->field_number);
            if($field["type"] == "checkbox"){
                $input = GFCommon::get_input($field, $result->field_number);
                $wpdb->update($table_name, array("value" => $input["label"]), array("id" => $result->id));
            }
        }
    }

    public static function user_has_cap($all_caps, $cap, $args){
        $gf_caps = GFCommon::all_caps();
        $capability = rgar($cap, 0);
        if($capability != "gform_full_access"){
            return $all_caps;
        }

        if(!self::has_members_plugin()){
            //give full access to administrators if the members plugin is not installed
            if(current_user_can("administrator") || is_super_admin()){
                $all_caps["gform_full_access"] = true;
            }
        }
        else if(current_user_can("administrator")|| is_super_admin()){

            //checking if user has any GF permission.
            $has_gf_cap = false;
            foreach($gf_caps as $gf_cap){
                if(rgar($all_caps, $gf_cap))
                    $has_gf_cap = true;
            }

            if(!$has_gf_cap){
                //give full access to administrators if none of the GF permissions are active by the Members plugin
                $all_caps["gform_full_access"] = true;
            }
        }

        return $all_caps;
    }

    //Target of Member plugin filter. Provides the plugin with Gravity Forms lists of capabilities
    public static function members_get_capabilities( $caps ) {
        return array_merge($caps, GFCommon::all_caps());
    }

    //Tests if the upload folder is writable and displays an error message if not
    public static function check_upload_folder(){
        //check if upload folder is writable
        $folder = RGFormsModel::get_upload_root();
        if(empty($folder))
            echo "<div class='error'>Upload folder is not writable. Export and file upload features will not be functional.</div>";
    }

    //Prints common admin scripts
    public static function print_scripts(){
        wp_enqueue_script("sack");
        wp_print_scripts();
    }

    public static function is_gravity_ajax_action(){
        //Gravity Forms AJAX requests
        $current_action = self::post("action");
        $gf_ajax_actions = array('rg_save_form', 'rg_change_input_type', 'rg_add_field', 'rg_duplicate_field',
                                 'rg_delete_field', 'rg_select_export_form', 'rg_start_export', 'gf_upgrade_license',
                                 'gf_delete_custom_choice', 'gf_save_custom_choice', 'gf_get_notification_post_categories',
                                 'rg_update_lead_property', 'delete-gf_entry', 'rg_update_form_active', 'rg_update_notification_active',
                                 'rg_update_confirmation_active', 'gf_resend_notifications', 'rg_dismiss_upgrade', 'gf_save_confirmation');

        if(defined("DOING_AJAX") && DOING_AJAX && in_array($current_action, $gf_ajax_actions))
            return true;

        //not a gravity forms ajax request.
        return false;
    }

    //Returns true if the current page is one of Gravity Forms pages. Returns false if not
    public static function is_gravity_page(){

        //Gravity Forms pages
        $current_page = trim(strtolower(self::get("page")));
        $gf_pages = array("gf_edit_forms", "gf_new_form", "gf_entries", "gf_settings", "gf_export", "gf_help");

        return in_array($current_page, $gf_pages);
    }

    //Creates "Forms" left nav
    public static function create_menu(){

        $has_full_access = current_user_can("gform_full_access");
        $min_cap = GFCommon::current_user_can_which(GFCommon::all_caps());
        if(empty($min_cap))
            $min_cap = "gform_full_access";

        $addon_menus = array();
        $addon_menus = apply_filters("gform_addon_navigation", $addon_menus);

        $parent_menu = self::get_parent_menu($addon_menus);

        // Add a top-level left nav
        $update_icon = GFCommon::has_update() && current_user_can("install_plugins") ? "<span title='" . esc_attr(__("Update Available", "gravityforms")) . "' class='update-plugins count-1'><span class='update-count'>1</span></span>" : "";

        add_menu_page(__('Forms', "gravityforms"), __("Forms", "gravityforms") . $update_icon , $has_full_access ? "gform_full_access" : $min_cap, $parent_menu["name"] , $parent_menu["callback"], GFCommon::get_base_url() . '/images/gravity-admin-icon.png', apply_filters("gform_menu_position", "16.9"));

        // Adding submenu pages
        add_submenu_page($parent_menu["name"], __("Forms", "gravityforms"), __("Forms", "gravityforms"), $has_full_access ? "gform_full_access" : "gravityforms_edit_forms", "gf_edit_forms", array("RGForms", "forms"));

        add_submenu_page($parent_menu["name"], __("New Form", "gravityforms"), __("New Form", "gravityforms"), $has_full_access ? "gform_full_access" : "gravityforms_create_form", "gf_new_form", array("RGForms", "new_form"));

        add_submenu_page($parent_menu["name"], __("Entries", "gravityforms"), __("Entries", "gravityforms"), $has_full_access ? "gform_full_access" : "gravityforms_view_entries", "gf_entries", array("RGForms", "all_leads_page"));

        if(is_array($addon_menus)){
            foreach($addon_menus as $addon_menu)
                add_submenu_page(esc_html($parent_menu["name"]), esc_html($addon_menu["label"]), esc_html($addon_menu["label"]), $has_full_access ? "gform_full_access" : $addon_menu["permission"], esc_html($addon_menu["name"]), $addon_menu["callback"]);
        }

        add_submenu_page($parent_menu["name"], __("Settings", "gravityforms"), __("Settings", "gravityforms"), $has_full_access ? "gform_full_access" : "gravityforms_view_settings", "gf_settings", array("RGForms", "settings_page"));

        add_submenu_page($parent_menu["name"], __("Import/Export", "gravityforms"), __("Import/Export", "gravityforms"), $has_full_access ? "gform_full_access" : "gravityforms_export_entries", "gf_export", array("RGForms", "export_page"));

        if(current_user_can("install_plugins")){
            add_submenu_page($parent_menu["name"], __("Updates", "gravityforms"), __("Updates", "gravityforms"), $has_full_access ? "gform_full_access" : "gravityforms_view_updates", "gf_update", array("RGForms", "update_page"));
            add_submenu_page($parent_menu["name"], __("Add-Ons", "gravityforms"), __("Add-Ons", "gravityforms"), $has_full_access ? "gform_full_access" : "gravityforms_view_addons", "gf_addons", array("RGForms", "addons_page"));
        }

        add_submenu_page($parent_menu["name"], __("Help", "gravityforms"), __("Help", "gravityforms"), $has_full_access ? "gform_full_access" : $min_cap, "gf_help", array("RGForms", "help_page"));

    }

    //Returns the parent menu item. It needs to be the same as the first sub-menu (otherwise WP will duplicate the main menu as a sub-menu)
    public static function get_parent_menu($addon_menus){

        if(GFCommon::current_user_can_any("gravityforms_edit_forms"))
            $parent = array("name" => "gf_edit_forms", "callback" => array("RGForms", "forms"));

        else if(GFCommon::current_user_can_any("gravityforms_create_form"))
            $parent = array("name" => "gf_new_form", "callback" => array("RGForms", "new_form"));

        else if(GFCommon::current_user_can_any("gravityforms_view_entries"))
            $parent = array("name" => "gf_entries", "callback" => array("RGForms", "all_leads_page"));

        else if(is_array($addon_menus) && sizeof($addon_menus) > 0){
            foreach($addon_menus as $addon_menu)
                if(GFCommon::current_user_can_any($addon_menu["permission"]))
                {
                    $parent = array("name" => $addon_menu["name"], "callback" => $addon_menu["callback"]);
                    break;
                }
        }
        else if(GFCommon::current_user_can_any("gravityforms_view_settings"))
            $parent = array("name" => "gf_settings", "callback" => array("RGForms", "settings_page"));

        else if(GFCommon::current_user_can_any("gravityforms_export_entries"))
            $parent = array("name" => "gf_export", "callback" => array("RGForms", "export_page"));

        else if(GFCommon::current_user_can_any("gravityforms_view_updates"))
            $parent = array("name" => "gf_update", "callback" => array("RGForms", "update_page"));

        else if(GFCommon::current_user_can_any("gravityforms_view_addons"))
            $parent = array("name" => "gf_addons", "callback" => array("RGForms", "addons_page"));

        else if(GFCommon::current_user_can_any(GFCommon::all_caps()))
            $parent = array("name" => "gf_help", "callback" => array("RGForms", "help_page"));

        return $parent;
    }

    public static function modify_admin_title( $admin_title, $title ) {

        $subview = rgget( 'subview' );
        $form_id = rgget( 'id' );

        if( ! $form_id || rgget( 'page' ) != 'gf_edit_forms' || rgget( 'view' ) != 'settings' )
            return $admin_title;

        require_once( GFCommon::get_base_path() . '/form_settings.php' );

        $setting_tabs = GFFormSettings::get_tabs( $form_id );
        $page_title = '';

        foreach( $setting_tabs as $tab ) {
            if( $tab['name'] == $subview )
                $page_title = $tab['label'];
        }

        if( $page_title )
            $admin_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' , 'gravityforms'), $page_title, $admin_title );

        return $admin_title;
    }

    //Parses the [gravityform shortcode and returns the front end form UI
    public static function parse_shortcode( $attributes, $content = null ) {

        extract( shortcode_atts( array(
             'title' => true,
             'description' => true,
             'id' => 0,
             'name' => '',
             'field_values' => "",
             'ajax' => false,
             'tabindex' => 1,
             'action' => 'form'
          ), $attributes, 'gravityforms' ) );

        $shortcode_string = "";

        switch($action) {
            case 'conditional':
                $shortcode_string = GFCommon::conditional_shortcode($attributes, $content);
            break;

            case 'form' :
                //displaying form
                $title = strtolower($title) == "false" ? false : true;
                $description = strtolower($description) == "false" ? false : true;
                $field_values = htmlspecialchars_decode($field_values);
                $field_values = str_replace("&#038;", "&", $field_values);

                $ajax = strtolower($ajax) == "true" ? true : false;

                //using name to lookup form if id is not specified
                if(empty($id))
                    $id = $name;

                parse_str($field_values, $field_value_array); //parsing query string like string for field values and placing them into an associative array
                $field_value_array = stripslashes_deep($field_value_array);

                $shortcode_string = self::get_form($id, $title, $description, false, $field_value_array, $ajax, $tabindex);

            break;
        }

        $shortcode_string = apply_filters("gform_shortcode_{$action}", $shortcode_string, $attributes, $content);

        return $shortcode_string;
    }

    public static function include_addon_framework() {
        require_once( GFCommon::get_base_path() . '/includes/addon/class-gf-addon.php' );
    }

    public static function include_feed_addon_framework() {
        require_once(GFCommon::get_base_path() . '/includes/addon/class-gf-feed-addon.php' );
    }

    public static function include_payment_addon_framework() {
        require_once(GFCommon::get_base_path() . '/includes/addon/class-gf-payment-addon.php' );
    }


    //-------------------------------------------------
    //----------- AJAX --------------------------------

    public static function ajax_parse_request($wp) {

        if (isset($_POST["gform_ajax"])) {
            parse_str($_POST["gform_ajax"]);
            $tabindex = isset($tabindex) ? absint($tabindex) : 1;
            require_once(GFCommon::get_base_path() . "/form_display.php");

            $result = GFFormDisplay::get_form($form_id, $title, $description, false, $_POST["gform_field_values"], true, $tabindex);
            die($result);
        }
    }

    //------------------------------------------------------
    //------------- PAGE/POST EDIT PAGE ---------------------

    public static function page_supports_add_form_button(){
        $is_post_edit_page = in_array(RG_CURRENT_PAGE, array('post.php', 'page.php', 'page-new.php', 'post-new.php'));

        $display_add_form_button = apply_filters("gform_display_add_form_button", $is_post_edit_page);

        return $display_add_form_button;
    }

    //Action target that adds the "Insert Form" button to the post/page edit screen
    public static function add_form_button(){

        $is_add_form_page = self::page_supports_add_form_button();
        if(!$is_add_form_page)
            return;

        // do a version check for the new 3.5 UI
        $version    = get_bloginfo('version');

        if ($version < 3.5) {
            // show button for v 3.4 and below
            $image_btn = GFCommon::get_base_url() . "/images/form-button.png";
            echo '<a href="#TB_inline?width=480&inlineId=select_gravity_form" class="thickbox" id="add_gform" title="' . __("Add Gravity Form", 'gravityforms') . '"><img src="'.$image_btn.'" alt="' . __("Add Gravity Form", 'gravityforms') . '" /></a>';
        } else {
            // display button matching new UI
            echo '<style>.gform_media_icon{
                    background:url(' . GFCommon::get_base_url() . '/images/gravity-admin-icon.png) no-repeat top left;
                    display: inline-block;
                    height: 16px;
                    margin: 0 2px 0 0;
                    vertical-align: text-top;
                    width: 16px;
                    }
                    .wp-core-ui a.gform_media_link{
                     padding-left: 0.4em;
                    }
                 </style>
                  <a href="#TB_inline?width=480&inlineId=select_gravity_form" class="thickbox button gform_media_link" id="add_gform" title="' . __("Add Gravity Form", 'gravityforms') . '"><span class="gform_media_icon "></span> ' . __("Add Form", "gravityforms") . '</a>';
        }
    }


    //Action target that displays the popup to insert a form to a post/page
    public static function add_mce_popup(){
        ?>
        <script>
            function InsertForm(){
                var form_id = jQuery("#add_form_id").val();
                if(form_id == ""){
                    alert("<?php _e("Please select a form", "gravityforms") ?>");
                    return;
                }

                var form_name = jQuery("#add_form_id option[value='" + form_id + "']").text().replace(/[\[\]]/g, '');
                var display_title = jQuery("#display_title").is(":checked");
                var display_description = jQuery("#display_description").is(":checked");
                var ajax = jQuery("#gform_ajax").is(":checked");
                var title_qs = !display_title ? " title=\"false\"" : "";
                var description_qs = !display_description ? " description=\"false\"" : "";
                var ajax_qs = ajax ? " ajax=\"true\"" : "";

                window.send_to_editor("[gravityform id=\"" + form_id + "\" name=\"" + form_name + "\"" + title_qs + description_qs + ajax_qs + "]");
            }
        </script>

        <div id="select_gravity_form" style="display:none;">
            <div class="wrap <?php echo GFCommon::get_browser_class() ?>">
                <div>
                    <div style="padding:15px 15px 0 15px;">
                        <h3 style="color:#5A5A5A!important; font-family:Georgia,Times New Roman,Times,serif!important; font-size:1.8em!important; font-weight:normal!important;"><?php _e("Insert A Form", "gravityforms"); ?></h3>
                        <span>
                            <?php _e("Select a form below to add it to your post or page.", "gravityforms"); ?>
                        </span>
                    </div>
                    <div style="padding:15px 15px 0 15px;">
                        <select id="add_form_id">
                            <option value="">  <?php _e("Select a Form", "gravityforms"); ?>  </option>
                            <?php
                                $forms = RGFormsModel::get_forms(1, "title");
                                foreach($forms as $form){
                                    ?>
                                    <option value="<?php echo absint($form->id) ?>"><?php echo esc_html($form->title) ?></option>
                                    <?php
                                }
                            ?>
                        </select> <br/>
                        <div style="padding:8px 0 0 0; font-size:11px; font-style:italic; color:#5A5A5A"><?php _e("Can't find your form? Make sure it is active.", "gravityforms"); ?></div>
                    </div>
                    <div style="padding:15px 15px 0 15px;">
                        <input type="checkbox" id="display_title" checked='checked' /> <label for="display_title"><?php _e("Display form title", "gravityforms"); ?></label> &nbsp;&nbsp;&nbsp;
                        <input type="checkbox" id="display_description" checked='checked' /> <label for="display_description"><?php _e("Display form description", "gravityforms"); ?></label>&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" id="gform_ajax" /> <label for="gform_ajax"><?php _e("Enable AJAX", "gravityforms"); ?></label>
                    </div>
                    <div style="padding:15px;">
                        <input type="button" class="button-primary" value="<?php _e("Insert Form", "gravityforms"); ?>" onclick="InsertForm();"/>&nbsp;&nbsp;&nbsp;
                    <a class="button" style="color:#bbb;" href="#" onclick="tb_remove(); return false;"><?php _e("Cancel", "gravityforms"); ?></a>
                    </div>
                </div>
            </div>
        </div>

        <?php
    }


    //------------------------------------------------------
    //------------- PLUGINS PAGE ---------------------------
    //------------------------------------------------------

    public static function plugin_settings_link( $links, $file ) {
        if ( $file != plugin_basename( __FILE__ ))
            return $links;

        array_unshift($links, '<a href="' . admin_url("admin.php") . '?page=gf_settings">' . __( 'Settings', 'gravityforms' ) . '</a>');

        return $links;
    }

    //Displays message on Plugin's page
    public static function plugin_row($plugin_name){
        $key = GFCommon::get_key();
        $version_info = GFCommon::get_version_info();

        if(!rgar($version_info, "is_valid_key")){

            $plugin_name = "gravityforms/gravityforms.php";

            $new_version = version_compare(GFCommon::$version, $version_info["version"], '<') ? __('There is a new version of Gravity Forms available.', 'gravityforms') .' <a class="thickbox" title="Gravity Forms" href="plugin-install.php?tab=plugin-information&plugin=gravityforms&TB_iframe=true&width=640&height=808">'. sprintf(__('View version %s Details', 'gravityforms'), $version_info["version"]) . '</a>. ' : '';

			echo '</tr><tr class="plugin-update-tr"><td colspan="3" class="plugin-update"><div class="update-message">' . $new_version . __('<a href="' . admin_url() . 'admin.php?page=gf_settings">Register</a> your copy of Gravity Forms to receive access to automatic upgrades and support. Need a license key? <a href="http://www.gravityforms.com">Purchase one now</a>.', 'gravityforms') . '</div></td>';

        }
    }

    //Displays current version details on Plugin's page
    public static function display_changelog(){
        if($_REQUEST["plugin"] != "gravityforms")
            return;

        $page_text = self::get_changelog();
        echo $page_text;

        exit;
    }

    public static function get_changelog(){
        $key = GFCommon::get_key();
        $body = "key=$key";
        $options = array('method' => 'POST', 'timeout' => 3, 'body' => $body);
        $options['headers'] = array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option('blog_charset'),
            'Content-Length' => strlen($body),
            'User-Agent' => 'WordPress/' . get_bloginfo("version"),
            'Referer' => get_bloginfo("url")
        );

       $raw_response = GFCommon::post_to_manager("changelog.php", GFCommon::get_remote_request_params(), $options);
        if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code']){
            $page_text = __("Oops!! Something went wrong.<br/>Please try again or <a href='http://www.gravityforms.com'>contact us</a>.", 'gravityforms');
        }
        else{
            $page_text = $raw_response['body'];
            if(substr($page_text, 0, 10) != "<!--GFM-->")
                $page_text = "";
        }
        return stripslashes($page_text);
    }

//------------------------------------------------------
//-------------- DASHBOARD PAGE -------------------------

    //Registers the dashboard widget
    public static function dashboard_setup(){
        $dashboard_title = apply_filters("gform_dashboard_title", __("Forms", "gravityforms"));
        wp_add_dashboard_widget('rg_forms_dashboard', $dashboard_title,  array('RGForms', 'dashboard'));
    }

    //Displays the dashboard UI
    public static function dashboard(){
        $forms = RGFormsModel::get_form_summary();

        if(sizeof($forms) > 0){
            ?>
                        <table class="widefat gf_dashboard_view" cellspacing="0" style="border:0px;">
                <thead>
                    <tr>
                        <td class="gf_dashboard_form_title_header" style="text-align:left; padding:8px 18px!important; font-weight:bold;"><i><?php _e("Title", "gravityforms") ?></i></td>
                        <td class="gf_dashboard_entries_unread_header" style="text-align:center; padding:8px 18px!important; font-weight:bold;"><i><?php _e("Unread", "gravityforms") ?></i></td>
                        <td class="gf_dashboard_entries_total_header" style="text-align:center; padding:8px 18px!important; font-weight:bold;"><i><?php _e("Total", "gravityforms") ?></i></td>
                    </tr>
                </thead>

                <tbody class="list:user user-list">
                    <?php
                    foreach($forms as $form){
                        $date_display = GFCommon::format_date($form["last_lead_date"]);
                        if(!empty($form["total_leads"])){
                            ?>
                            <tr class='author-self status-inherit' valign="top">
                                <td class="gf_dashboard_form_title column-title" style="padding:8px 18px;">
                                    <a <?php echo $form["unread_count"] > 0 ? "class='form_title_unread' style='font-weight:bold;'" : "" ?> href="admin.php?page=gf_entries&view=entries&id=<?php echo absint($form["id"]) ?>" title="<?php echo esc_html($form["title"]) ?> : <?php _e("View All Entries", "gravityforms") ?>"><?php echo esc_html($form["title"]) ?></a>
                                </td>
                                <td class="gf_dashboard_entries_unread column-date" style="padding:8px 18px; text-align:center;"><a <?php echo $form["unread_count"] > 0 ? "class='form_entries_unread' style='font-weight:bold;'" : "" ?> href="admin.php?page=gf_entries&view=entries&filter=unread&id=<?php echo absint($form["id"]) ?>" title="<?php printf(__("Last Entry: %s", "gravityforms"), $date_display); ?>"><?php echo absint($form["unread_count"]) ?></a></td>
                                <td class="gf_dashboard_entries_total column-date" style="padding:8px 18px; text-align:center;"><a href="admin.php?page=gf_entries&view=entries&id=<?php echo absint($form["id"]) ?>" title="<?php _e("View All Entries", "gravityforms") ?>"><?php echo absint($form["total_leads"]) ?></a></td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>

            <?php if ( GFCommon::current_user_can_any('gravityforms_edit_forms') ) : ?>
                <p class="textright">
                <a class="gf_dashboard_button button" href="admin.php?page=gf_edit_forms"><?php _e("View All Forms", "gravityforms") ?></a>
            <?php endif; ?>
          </p>
            <?php
        }
        else{
            ?>
            <div class="gf_dashboard_noforms_notice">
                <?php echo sprintf(__("You don't have any forms. Let's go %s create one %s!", 'gravityforms'), '<a href="admin.php?page=gf_new_form">', '</a>'); ?>
            </div>
            <?php
        }

        if(GFCommon::current_user_can_any("gravityforms_view_updates") && (!function_exists("is_multisite") || !is_multisite() || is_super_admin())){
            //displaying update message if there is an update and user has permission
            self::dashboard_update_message();
        }
    }

    public static function dashboard_update_message(){
        $version_info = GFCommon::get_version_info();

        //don't display a message if use has dismissed the message for this version
        $ary_dismissed = get_option("gf_dismissed_upgrades");

        $is_dismissed = !empty($ary_dismissed) && in_array($version_info["version"], $ary_dismissed);

        if($is_dismissed)
            return;

        if(version_compare(GFCommon::$version, $version_info["version"], '<')) {
            $auto_upgrade = "";

            /*if($version_info["is_valid_key"]){
                $plugin_file = "gravityforms/gravityforms.php";
                $upgrade_url = wp_nonce_url('update.php?action=upgrade-plugin&amp;plugin=' . urlencode($plugin_file), 'upgrade-plugin_' . $plugin_file);
                $auto_upgrade = sprintf(__(" or %sUpgrade Automatically%s", "gravityforms"), "<a href='{$upgrade_url}'>", "</a>");
            }*/
            $message = sprintf(__("There is an update available for Gravity Forms. %sView Details%s %s", "gravityforms"), "<a href='admin.php?page=gf_update'>", "</a>", $auto_upgrade);
            ?>
            <div class='updated' style='padding:15px; position:relative;' id='gf_dashboard_message'><?php echo $message ?>
                <a href="javascript:void(0);" onclick="GFDismissUpgrade();" style='float:right;'><?php _e("Dismiss", "gravityforms") ?></a>
            </div>
            <script type="text/javascript">
                function GFDismissUpgrade(){
                    jQuery("#gf_dashboard_message").slideUp();
                    jQuery.post(ajaxurl, {action:"rg_dismiss_upgrade", version:"<?php echo $version_info["version"] ?>"});
                }
            </script>
            <?php
        }
    }

    public static function dashboard_dismiss_upgrade(){
        $ary = get_option("gf_dismissed_upgrades");
        if(!is_array($ary))
            $ary = array();

        $ary[] = $_POST["version"];
        update_option("gf_dismissed_upgrades", $ary);
    }


//------------------------------------------------------
//--------------- ALL OTHER PAGES ----------------------

    public static function register_scripts() {

        $base_url = GFCommon::get_base_url();
        $version = GFForms::$version;

        wp_register_script( 'gform_chosen',            $base_url . '/js/chosen.jquery.min.js', array( 'jquery' ), $version );
        wp_register_script( 'gform_conditional_logic', $base_url . '/js/conditional_logic.js', array( 'jquery' ), $version );
        wp_register_script( 'gform_datepicker_init',   $base_url . '/js/datepicker.js', array( 'jquery', 'jquery-ui-datepicker' ), $version, true );
        wp_register_script( 'gform_floatmenu',         $base_url . '/js/floatmenu_init.js', array( 'jquery' ), $version );
        wp_register_script( 'gform_form_admin',        $base_url . '/js/form_admin.js', array( 'jquery', 'jquery-ui-autocomplete', 'gform_placeholder' ), $version );
        wp_register_script( 'gform_form_editor',       $base_url . '/js/form_editor.js', array( 'jquery', 'gform_json', 'gform_placeholder' ), $version );
        wp_register_script( 'gform_forms',             $base_url . '/js/forms.js', array( 'jquery' ), $version );
        wp_register_script( 'gform_gravityforms',      $base_url . '/js/gravityforms.js', array( 'jquery', 'gform_json', 'gform_placeholder' ), $version );
        wp_register_script( 'gform_json',              $base_url . '/js/jquery.json-1.3.js', array( 'jquery' ), $version, true );
        wp_register_script( 'gform_masked_input',      $base_url . '/js/jquery.maskedinput-1.3.1.min.js', array( 'jquery' ), $version );
        wp_register_script( 'gform_menu',              $base_url . '/js/menu.js', array( 'jquery' ), $version );
        wp_register_script( 'gform_placeholder',       $base_url . '/js/jquery.placeholders.2.1.1.min.js', null, $version );
        wp_register_script( 'gform_tooltip_init',      $base_url . '/js/tooltip_init.js', array( 'jquery-ui-tooltip' ), $version );
        wp_register_script( 'gform_textarea_counter',  $base_url . '/js/jquery.textareaCounter.plugin.js', array( 'jquery' ), $version );
        wp_register_script( 'gform_field_filter',      $base_url . '/js/gf_field_filter.js', array( 'jquery' ), $version );

        // only required for WP versions prior to 3.3
        wp_register_script( 'gf_thickbox', $base_url . '/js/thickbox.js', array(), $version );
        wp_register_style( 'gf_thickbox', $base_url . '/js/thickbox.css', array(), $version );
        wp_localize_script( 'gf_thickbox', 'thickboxL10n', array(
            'next' => __( 'Next &gt;', "gravityforms" ),
            'prev' => __( '&lt; Prev', "gravityforms" ),
            'image' => __( 'Image', "gravityforms" ),
            'of' => __( 'of', "gravityforms" ),
            'close' => __( 'Close', "gravityforms" ),
            'noiframes' => __( 'This feature requires inline frames. You have iframes disabled or your browser does not support them.' , 'gravityforms'),
            'loadingAnimation' => includes_url( 'js/thickbox/loadingAnimation.gif' ),
            'closeImage' => includes_url( 'js/thickbox/tb-close.png' )
            ) );
    }

    public static function enqueue_admin_scripts() {

        $scripts = array();
        $page = self::get_page();

        switch($page) {
        case 'new_form' :
        case 'form_list':
            $scripts = array(
                'gform_gravityforms',
                'gform_json',
                'gform_form_admin',
                'thickbox'
                );
            break;

        case 'form_settings':
            $scripts = array(
                'gform_gravityforms',
                'gform_forms',
                'gform_json',
                'gform_form_admin',
                'gform_placeholder',
                'jquery-ui-datepicker',
                'gform_masked_input',
                'jquery-ui-sortable'
                );
            break;

        case 'form_editor':
            $thickbox = !GFCommon::is_wp_version("3.3") ? 'gf_thickbox' : 'thickbox';
            $scripts = array(
                $thickbox,
                'jquery-ui-core',
                'jquery-ui-sortable',
                'jquery-ui-tabs',
                'sack',
                'gform_gravityforms',
                'gform_forms',
                'gform_json',
                'gform_form_admin',
                'gform_floatmenu',
                'gform_menu',
                'gform_placeholder',
                'jquery-ui-autocomplete'
                );

            if ( wp_is_mobile() )
                $scripts[] = 'jquery-touch-punch';

            break;

        case 'entry_detail':
            $scripts = array('gform_json');
            break;

        case 'entry_detail_edit':
            $scripts = array('gform_gravityforms','plupload-all');
            break;

        case 'entry_list':
            $scripts = array(
                'wp-lists',
                'wp-ajax-response',
                'thickbox',
                'gform_json',
                'thickbox',
                'gform_field_filter'
                );
            break;

        case 'notification_list':
            $scripts = array(
                'gform_forms',
                'gform_json',
                'gform_form_admin'
                );
            break;

        case 'notification_new':
        case 'notification_edit':
            $scripts = array(
                'jquery-ui-autocomplete',
                'gform_gravityforms',
                'gform_placeholder',
                'gform_form_admin',
                'gform_forms',
               	'gform_json'
                );
            break;

        case 'confirmation':
            $scripts = array(
                'gform_form_admin',
                'gform_forms',
                'gform_gravityforms',
                'gform_placeholder',
                'gform_json',
                'wp-pointer'
                );
            break;

        case 'addons':
            $scripts = array('thickbox');
            break;

        case 'export_entry':
            $scripts = array(
                'jquery-ui-datepicker',
                'gform_form_admin',
                'gform_field_filter'
                );
            break;

        }

        if(empty($scripts))
            return;

        foreach($scripts as $script){
            wp_enqueue_script($script);
        }


        GFCommon::localize_gform_gravityforms_multifile();


    }

    public static function get_page() {

        /**
        * Page names:
        *
        *   new_form
        *   form_list
        *   form_editor
        *   form_settings
        *   confirmation
        *   notification_list
        *   notification_new
        *   notification_edit
        *   entry_list
        *   entry_detail
        *   entry_detail_edit
        *   settings
        *   addons
        *   export_entry
        *   export_form
        *   import_form
        */

        if( rgget('page') == 'gf_new_form' )
            return 'new_form';

        if( rgget('page') == 'gf_edit_forms' && !rgget('id') )
            return 'form_list';

        if( rgget('page') == 'gf_edit_forms' && !rgget('view') )
            return 'form_editor';

        if( rgget('page') == 'gf_edit_forms' && rgget('view') == 'settings' && ( !rgget('subview') || rgget('subview') == 'settings') )
            return 'form_settings';

        if( rgget('page') == 'gf_edit_forms' && rgget('view') == 'settings' && rgget('subview') == 'confirmation' )
            return 'confirmation';

        if( rgget('page') == 'gf_edit_forms' && rgget('view') == 'settings' && rgget('subview') == 'notification' && rgget('nid') )
            return 'notification_edit';

        if( rgget('page') == 'gf_edit_forms' && rgget('view') == 'settings' && rgget('subview') == 'notification' && isset($_GET['nid']) )
            return 'notification_edit';

        if( rgget('page') == 'gf_edit_forms' && rgget('view') == 'settings' && rgget('subview') == 'notification' )
            return 'notification_list';

        if( rgget('page') == 'gf_entries' && (!rgget('view') || rgget('view') == 'entries' ) )
            return 'entry_list';

        if( rgget('page') == 'gf_entries' && rgget('view') == 'entry' && isset($_POST['screen_mode']) && rgpost('screen_mode') != 'view')
            return 'entry_detail_edit';

        if( rgget('page') == 'gf_entries' && rgget('view') == 'entry')
            return 'entry_detail';

        if( rgget('page') == 'gf_settings')
            return 'settings';

        if(rgget('page') == 'gf_addons')
            return 'addons';

        if(rgget('page') == 'gf_export' && ( rgget('view') == 'export_entry' || !isset($_GET['view']) ) )
            return 'export_entry';

        if(rgget('page') == 'gf_export' && rgget('view') == 'export_form')
            return 'export_form';

        if(rgget('page') == 'gf_export' && rgget('view') == 'import_form')
            return 'import_form';

        return false;
    }

    public static function get_form($form_id, $display_title=true, $display_description=true, $force_display=false, $field_values=null, $ajax=false, $tabindex = 1){
        require_once(GFCommon::get_base_path() . "/form_display.php");
        return GFFormDisplay::get_form($form_id, $display_title, $display_description, $force_display, $field_values, $ajax, $tabindex);
    }

    public static function new_form(){
        self::form_list_page();
    }

    public static function enqueue_scripts(){
        require_once(GFCommon::get_base_path() . "/form_display.php");
        GFFormDisplay::enqueue_scripts();
    }

    public static function print_form_scripts($form, $ajax){
        require_once(GFCommon::get_base_path() . "/form_display.php");
        GFFormDisplay::print_form_scripts($form, $ajax);
    }

    public static function forms_page($form_id){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::forms_page($form_id);
    }

    public static function settings_page(){
        require_once(GFCommon::get_base_path() . "/settings.php");
        GFSettings::settings_page();
    }

    public static function add_settings_page( $name, $handle = '', $icon_path = '' ){
        require_once(GFCommon::get_base_path() . "/settings.php");
        GFSettings::add_settings_page($name, $handle, $icon_path);
    }

    public static function help_page(){
        require_once(GFCommon::get_base_path() . "/help.php");
        GFHelp::help_page();
    }

    public static function export_page(){
        require_once(GFCommon::get_base_path() . "/export.php");
        GFExport::export_page();
    }

    public static function update_page(){
        require_once(GFCommon::get_base_path() . "/update.php");
        GFUpdate::update_page();
    }

    public static function addons_page(){

        wp_print_styles(array("thickbox"));

        $plugins = get_plugins();
        $installed_plugins = array();
        foreach($plugins as $key => $plugin){
            $is_active = is_plugin_active($key);
            $installed_plugin = array("plugin" => $key, "name" => $plugin["Name"], "is_active"=>$is_active);
            $installed_plugin["activation_url"] = $is_active ? "" : wp_nonce_url("plugins.php?action=activate&plugin={$key}", "activate-plugin_{$key}");
            $installed_plugin["deactivation_url"] = !$is_active ? "" : wp_nonce_url("plugins.php?action=deactivate&plugin={$key}", "deactivate-plugin_{$key}");

            $installed_plugins[] = $installed_plugin;
        }

        $nonces = self::get_addon_nonces();

        $body = array("plugins" => urlencode(serialize($installed_plugins)), "nonces" => urlencode(serialize($nonces)), "key" => GFCommon::get_key());
        $options = array('body' => $body, 'headers' => array('Referer' => get_bloginfo("url")), 'timeout' => 15);

        $raw_response = GFCommon::post_to_manager("api.php", "op=plugin_browser&{$_SERVER["QUERY_STRING"]}", $options);

         if ( is_wp_error( $raw_response ) || $raw_response['response']['code'] != 200){
            echo "<div class='error' style='margin-top:50px; padding:20px;'>" . __("Add-On browser is currently unavailable. Please try again later.", "gravityforms") . "</div>";
         }
         else{
            echo GFCommon::get_remote_message();
            echo $raw_response["body"];
         }
    }

    public static function get_addon_info($api, $action, $args){

		if($action == "plugin_information" && empty($api) && ( !rgempty("rg", $_GET) || $args->slug == 'gravityforms')){
			$raw_response = GFCommon::post_to_manager("api.php", "op=get_plugin&slug={$args->slug}", array());

            if ( is_wp_error( $raw_response ) || $raw_response['response']['code'] != 200)
                return false;

            $plugin = unserialize($raw_response["body"]);

            $api = new stdClass();
            $api->name = $plugin["title"];
            $api->version = $plugin["version"];
            $api->download_link = $plugin["download_url"];
			$api->tested = '10.0';
        }
        return $api;
    }

    public static function get_addon_nonces(){
        $raw_response = GFCommon::post_to_manager("api.php", "op=get_plugins", array());

        if ( is_wp_error( $raw_response ) || $raw_response['response']['code'] != 200)
            return false;

        $addons = unserialize($raw_response["body"]);
        $nonces = array();
        foreach($addons as $addon){
            $nonces[$addon["key"]] = wp_create_nonce("install-plugin_{$addon["key"]}");
        }

        return $nonces;
    }

    public static function start_export(){
        require_once(GFCommon::get_base_path() . "/export.php");
        GFExport::start_export();
    }

    public static function get_post_category_values(){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::get_post_category_values();
    }

    public static function get_notification_post_category_values(){
        require_once(GFCommon::get_base_path() . "/notification.php");
        GFNotification::get_post_category_values();
    }

    public static function all_leads_page(){

        $view = rgget("view");
        $lead_id = rgget('lid');

        if($view == 'entry' && (rgget('lid') || !rgblank(rgget('pos')))) {
            require_once(GFCommon::get_base_path() . "/entry_detail.php");
            GFEntryDetail::lead_detail_page();
        } else if ($view == 'entries' || empty($view)){
            require_once(GFCommon::get_base_path() . "/entry_list.php");
            GFEntryList::all_leads_page();
        } else {
            $form_id = rgget('id');
            do_action("gform_entries_view", $view, $form_id, $lead_id);
        }

    }

    public static function form_list_page(){
        require_once(GFCommon::get_base_path() . "/form_list.php");
        GFFormList::form_list_page();
    }

    public static function forms(){
        if(!GFCommon::ensure_wp_version())
            return;

        $id = RGForms::get("id");
        $view = RGForms::get("view");

		if($view == "entries"){
            require_once(GFCommon::get_base_path() . "/entry_list.php");
            GFEntryList::leads_page($id);
        } else if($view == "entry"){
            require_once(GFCommon::get_base_path() . "/entry_detail.php");
            GFEntryDetail::lead_detail_page();
        } else if($view == "notification"){
            require_once(GFCommon::get_base_path() . "/notification.php");
            //GFNotification::notification_page($id);
        } else if($view == 'settings') {
            require_once(GFCommon::get_base_path() . "/form_settings.php");
            GFFormSettings::form_settings_page($id);
        } else if(empty($view)){
            if(is_numeric($id)){
                self::forms_page($id);
            } else{
				self::form_list_page();
			}
		}

        do_action("gform_view", $view, $id);

    }

    public static function get($name, $array=null){
        if(!$array)
            $array = $_GET;

        if(isset($array[$name]))
            return $array[$name];

        return "";
    }

    public static function post($name){
        if(isset($_POST[$name]))
            return $_POST[$name];

        return "";
    }

    // AJAX Function
    public static function resend_notifications(){

        check_admin_referer('gf_resend_notifications', 'gf_resend_notifications');
        $form_id = rgpost('formId');
        $leads = rgpost('leadIds'); // may be a single ID or an array of IDs
        if(0 == $leads){
            // get all the lead ids for the current filter / search
            $filter = rgpost("filter");
            $search = rgpost("search");
            $star = $filter == "star" ? 1 : null;
            $read = $filter == "unread" ? 0 : null;
            $status = in_array($filter, array("trash", "spam")) ? $filter : "active";

            $search_criteria["status"] =  $status;

            if($star)
                $search_criteria["field_filters"][] = array("key" => "is_starred", "value" => (bool) $star );
            if(!is_null($read))
                $search_criteria["field_filters"][] = array("key" => "is_read", "value" => (bool) $read );

            $search_field_id = rgpost("fieldId");

            if(isset($_POST["fieldId"]) && $_POST["fieldId"] !== ''){
                $key = $search_field_id;
                $val = $search;
                $strpos_row_key       = strpos($search_field_id, "|");
                if ($strpos_row_key !== false) { //multi-row
                    $key_array = explode("|", $search_field_id);
                    $key       = $key_array[0];
                    $val       = $key_array[1] . ":" . $val;
                }
                $search_criteria["field_filters"][] = array(
                    "key" => $key,
                    "operator" => rgempty("operator", $_POST) ? "is" : rgpost("operator"),
                    "value" => $val
                );
            }

            $leads = GFFormsModel::search_lead_ids($form_id, $search_criteria);
        } else {
            $leads = !is_array($leads) ? array($leads) : $leads;
        }

        $form = apply_filters("gform_before_resend_notifications_{$form_id}", apply_filters('gform_before_resend_notifications', RGFormsModel::get_form_meta($form_id), $leads), $leads);

        if(empty($leads) || empty($form)) {
            _e("There was an error while resending the notifications.", "gravityforms");
            die();
        };

        $notifications = json_decode(rgpost('notifications'));
        if(!is_array($notifications))
            die(__("No notifications have been selected. Please select a notification to be sent.", "gravityforms"));

        if( ! rgempty( 'sendTo', $_POST ) && ! GFCommon::is_valid_email_list(rgpost('sendTo')))
            die(__("The <strong>Send To</strong> email address provided is not valid.", "gravityforms"));

        foreach($leads as $lead_id){

            $lead = RGFormsModel::get_lead($lead_id);
            foreach($notifications as $notification_id){
                $notification = $form["notifications"][$notification_id];
                if(!$notification)
                    continue;

                //overriding To email if one was specified
                if(rgpost('sendTo')){
                    $notification["to"] = rgpost('sendTo');
                    $notification["toType"] = "email";
                }

                GFCommon::send_notification($notification, $form, $lead);
            }
        }

        die();
    }

//-------------------------------------------------
//----------- AJAX CALLS --------------------------
    //captcha image

    public static function captcha_image(){
        $field = array("simpleCaptchaSize" => $_GET["size"], "simpleCaptchaFontColor"=> $_GET["fg"], "simpleCaptchaBackgroundColor"=>$_GET["bg"]);
        if($_GET["type"] == "math")
            $captcha = GFCommon::get_math_captcha($field, $_GET["pos"]);
        else
            $captcha = GFCommon::get_captcha($field);

        @ini_set('memory_limit', '256M');
        $image = imagecreatefrompng($captcha["path"]);

        include_once( ABSPATH . 'wp-admin/includes/image-edit.php' );
        wp_stream_image($image, "image/png", 0);
        imagedestroy($image);
        die();
    }


    public static function update_form_active(){
        check_ajax_referer('rg_update_form_active','rg_update_form_active');
        RGFormsModel::update_form_active($_POST["form_id"], $_POST["is_active"]);
    }
    public static function update_notification_active(){
        check_ajax_referer('rg_update_notification_active','rg_update_notification_active');
        RGFormsModel::update_notification_active($_POST["form_id"], $_POST["notification_id"], $_POST["is_active"]);
    }
    public static function update_confirmation_active(){
        check_ajax_referer('rg_update_confirmation_active','rg_update_confirmation_active');
        RGFormsModel::update_confirmation_active($_POST["form_id"], $_POST["confirmation_id"], $_POST["is_active"]);
    }
    public static function update_lead_property(){
        check_ajax_referer('rg_update_lead_property','rg_update_lead_property');
        RGFormsModel::update_lead_property($_POST["lead_id"], $_POST["name"], $_POST["value"]);
    }

    public static function update_lead_status(){
        check_ajax_referer('gf_delete_entry');
        $status = rgpost("status");
        $lead_id = rgpost("entry");

        switch($status){
            case "unspam" :
                //TODO: call akismet and set entry as not spam.
                RGFormsModel::update_lead_property($lead_id, "status", "active");
            break;

            case "delete" :
                if(GFCommon::current_user_can_any("gravityforms_delete_entries")){
                    RGFormsModel::delete_lead($lead_id);
                }
            break;

            default :
                RGFormsModel::update_lead_property($lead_id, "status", $status);
            break;
        }
        header("Content-Type: text/xml");
        echo "<?xml version='1.0' standalone='yes'?><wp_ajax></wp_ajax>";
        exit();

    }

    //settings
    public static function upgrade_license(){
        require_once(GFCommon::get_base_path() . "/settings.php");
        GFSettings::upgrade_license();
    }

    //form detail
    public static function save_form(){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::save_form();
    }
    public static function add_field(){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::add_field();
    }
    public static function duplicate_field(){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::duplicate_field();
    }
    public static function delete_field(){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::delete_field();
    }
    public static function change_input_type(){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::change_input_type();
    }
    public static function delete_custom_choice(){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::delete_custom_choice();
    }
    public static function save_custom_choice(){
        require_once(GFCommon::get_base_path() . "/form_detail.php");
        GFFormDetail::save_custom_choice();
    }

    //entry detail
    public static function delete_file(){
        check_ajax_referer("rg_delete_file", "rg_delete_file");
        $lead_id =  intval($_POST["lead_id"]);
        $field_id =  intval($_POST["field_id"]);
        $file_index =  intval($_POST["file_index"]);

        RGFormsModel::delete_file($lead_id, $field_id, $file_index);
        die("EndDeleteFile($field_id, $file_index);");
    }

    //export
    public static function select_export_form(){
        check_ajax_referer("rg_select_export_form", "rg_select_export_form");
        $form_id =  intval($_POST["form_id"]);
        $form = RGFormsModel::get_form_meta($form_id);

        $form = apply_filters("gform_form_export_page_{$form_id}", apply_filters("gform_form_export_page", $form));

        $filter_settings = GFCommon::get_field_filter_settings($form);
        $filter_settings_json = json_encode($filter_settings);
        $fields = array();

        $form = GFExport::add_default_export_fields($form);

        if(is_array($form["fields"])){
            foreach($form["fields"] as $field){
                if(is_array(rgar($field,"inputs"))){
                    foreach($field["inputs"] as $input)
                        $fields[] =  array($input["id"], GFCommon::get_label($field, $input["id"]));
                }
                else if(!rgar($field,"displayOnly")){
                    $fields[] =  array($field["id"], GFCommon::get_label($field));
                }
            }
        }
        $field_json = GFCommon::json_encode($fields);

        die("EndSelectExportForm($field_json, $filter_settings_json);");
    }

    // form settings
    public static function save_confirmation() {
        require_once(GFCommon::get_base_path() . '/form_settings.php');
        GFFormSettings::save_confirmation();
    }

    public static function delete_confirmation() {
        require_once(GFCommon::get_base_path() . '/form_settings.php');
        GFFormSettings::delete_confirmation();
    }

    // form list
    public static function save_new_form() {
        require_once(GFCommon::get_base_path() . '/form_list.php');
        GFFormList::save_new_form();
    }

    public static function top_toolbar(){

        $forms = RGFormsModel::get_forms(null, "title");
        $id = rgempty("id", $_GET) ? count($forms) > 0 ? $forms[0]->id : "0" : rgget("id");

        ?>

        <script type="text/javascript">
            function GF_ReplaceQuery(key, newValue){
                var new_query = "";
                var query = document.location.search.substring(1);
                var ary = query.split("&");
                var has_key=false;
                for (i=0; i < ary.length; i++) {
                    var key_value = ary[i].split("=");

                    if (key_value[0] == key){
                        new_query += key + "=" + newValue + "&";
                        has_key = true;
                    }
                    else if(key_value[0] != "display_settings"){
                        new_query += key_value[0] + "=" + key_value[1] + "&";
                    }
                }

                if(new_query.length > 0)
                    new_query = new_query.substring(0, new_query.length-1);

                if(!has_key)
                    new_query += new_query.length > 0 ? "&" + key + "=" + newValue : "?" + key + "=" + newValue;

                return new_query;
            }

            function GF_RemoveQuery(key, query){
                var new_query = "";
                if (query == "")
                {
                	query = document.location.search.substring(1);
				}
                var ary = query.split("&");
                for (i=0; i < ary.length; i++) {
                    var key_value = ary[i].split("=");

                    if (key_value[0] != key){
                        new_query += key_value[0] + "=" + key_value[1] + "&";
                    }
                }

                if(new_query.length > 0)
                    new_query = new_query.substring(0, new_query.length-1);

                return new_query;
            }

            function GF_SwitchForm(id){
                if(id.length > 0){
                    query = GF_ReplaceQuery("id", id);
                    //remove paging from querystring when changing forms
                    new_query = GF_RemoveQuery("paged", query);
                   	new_query = new_query.replace("gf_new_form", "gf_edit_forms");

                    //remove filter vars from querystring when changing forms
                    new_query = GF_RemoveQuery("s", new_query);
                    new_query = GF_RemoveQuery("operator", new_query);
                    new_query = GF_RemoveQuery("type", new_query);
                    new_query = GF_RemoveQuery("field_id", new_query);

                    //When switching forms within any form settings tab, go back to main form settings tab
                    var is_form_settings = new_query.indexOf("page=gf_edit_forms") >=0 && new_query.indexOf("view=settings");
                    if(is_form_settings){
                        //going back to main form settings tab
                        new_query = "page=gf_edit_forms&view=settings&id=" + id;
                    }

                    document.location = "?" + new_query;
                }
            }

            function ToggleFormSettings(){
                FieldClick(jQuery('#gform_heading')[0]);
            }

            jQuery(document).ready(function(){
                if(document.location.search.indexOf("display_settings") > 0)
                    ToggleFormSettings()

                jQuery('a.gf_toolbar_disabled').click(function(event){
                    event.preventDefault();
                });
            });

        </script>

        <div id="gf_form_toolbar">
            <ul id="gf_form_toolbar_links">

				<?php
				$menu_items = apply_filters("gform_toolbar_menu", self::get_toolbar_menu_items($id), $id);
				echo self::format_toolbar_menu_items($menu_items);
				?>

                <li class="gf_form_switcher">
                    <label for="export_form"><?php _e("Select A Form", "gravityforms") ?></label>

                    <?php
                    if(RG_CURRENT_VIEW != 'entry'){ ?>
                        <select name="form_switcher" id="form_switcher" onchange="GF_SwitchForm(jQuery(this).val());">
                            <option value=""><?php _e("Switch Form", "gravityforms") ?></option>
                            <?php
                            foreach($forms as $form_info){
                                ?>
                                <option value="<?php echo $form_info->id ?>"><?php echo $form_info->title ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    <?php
                    } // end view check ?>

                </li>
            </ul>
        </div>

        <?php

    }

	public static function format_toolbar_menu_items($menu_items, $compact = false){
		if (empty($menu_items))
			return "";

		$output = "";

		$priorities = array();
		foreach($menu_items as $k => $menu_item){
			$priorities[$k] = rgar($menu_item,"priority");
		}

		array_multisort($priorities, SORT_DESC, $menu_items);

        $keys = array_keys($menu_items);
        $last_key = array_pop($keys);  // array_pop(array_keys($menu_items)) causes a Strict Standards warning in WP 3.6 on PHP 5.4

		foreach($menu_items as $key => $menu_item){
            if(is_array($menu_item)){
                if(GFCommon::current_user_can_any(rgar($menu_item, "capabilities"))){
                    $sub_menu_str = "";
                    $count_sub_menu_items = 0;
                    $sub_menu_items = rgar($menu_item, "sub_menu_items");
                    if (is_array($sub_menu_items)){
                        foreach($sub_menu_items as $k => $val){
                            if(false === GFCommon::current_user_can_any(rgar($sub_menu_items[$k], "capabilities")))
                                unset($sub_menu_items[$k]);
                        }
                        $sub_menu_items = array_values($sub_menu_items); //reset numeric keys
                        $count_sub_menu_items = count($sub_menu_items);
                    }

                    $menu_class = rgar($menu_item, "menu_class");

                    if ($count_sub_menu_items == 1){
                        $label = $compact ? rgar($menu_item, "label") : rgar($sub_menu_items[0], "label");
                        $menu_item = $sub_menu_items[0];
                    } else {
                        $label = rgar($menu_item, "label");
                        $sub_menu_str = self::toolbar_sub_menu_items($sub_menu_items, $compact);
                    }
                    $link_class = rgar($menu_item, "link_class");
                    $icon 		= rgar($menu_item, "icon");
                    $url 		= rgar($menu_item, "url");
                    $title 		= rgar($menu_item, "title");
                    $onclick 	= rgar($menu_item, "onclick");

                    $target 	= rgar($menu_item, "target");
                    $link = "<a class='{$link_class}' onclick='{$onclick}' title='{$title}' href='{$url}' target='{$target}'>{$icon} {$label}</a>" . $sub_menu_str;
                    if($compact){
                        if ($key == "delete")
                            $link = apply_filters("gform_form_delete_link", $link);
                        $divider = $key == $last_key ? '' : " | ";
                        if($count_sub_menu_items > 0)
                            $menu_class .= " gf_form_action_has_submenu";
                        $output .= '<span class="' . $menu_class . '">' . $link . $divider . '</span>';
                    } else {

                        $output .= "<li class='{$menu_class}'>{$link}</li>";
                    }

                }
            } elseif($compact) {
                //for backwards compatibility <1.7: form actions only
                $divider = $key == $last_key ? '' : " | ";
                $output .= '<span class="edit">'. $menu_item . $divider . '</span>';
            }
		}

		return $output;
	}

	public static function get_toolbar_menu_items($form_id, $compact = false){
		$menu_items = array();

		//---- Form Editor ----
		$edit_capabilities = array("gravityforms_edit_forms");

		$menu_items['edit'] = array(
			'label' 		=> $compact ? __("Edit", "gravityforms") : __("Form Editor", "gravityforms"),
			'icon' 			=> '<i class="fa fa-pencil-square-o fa-lg"></i>',
			'title'			=> __('Edit this form', 'gravityforms'),
			'url' 			=> '?page=gf_edit_forms&id=' . $form_id,
			'menu_class' 	=> 'gf_form_toolbar_editor',
			'link_class' 	=> self::toolbar_class("editor"),
			'capabilities' 	=> $edit_capabilities,
			'priority'		=> 1000
		);

		//---- Form Settings ----

		$sub_menu_items = self::get_form_settings_sub_menu_items($form_id);

		$menu_items['settings'] = array(
			'label' 			=> $compact ? __("Settings", "gravityforms") : __("Form Settings", "gravityforms"),
			'icon' 			=> '<i class="fa fa-cogs fa-lg"></i>',
			'title'				=> __('Edit settings for this form', 'gravityforms'),
			'url' 				=> '?page=gf_edit_forms&view=settings&id=' . $form_id,
			'menu_class' 		=> 'gf_form_toolbar_settings',
			'link_class' 		=> self::toolbar_class("settings"),
			'sub_menu_items' 	=> $sub_menu_items,
			'capabilities' 		=> $edit_capabilities,
			'priority'			=> 900
		);


		//---- Entries ----

		$entries_capabilities = array('gravityforms_view_entries','gravityforms_edit_entries','gravityforms_delete_entries');

		$menu_items['entries'] = array(
			'label' 		=> __("Entries", "gravityforms"),
			'icon' 			=> '<i class="fa fa-comment-o fa-lg"></i>',
			'title'			=> __('View entries generated by this form', 'gravityforms'),
			'url' 			=> '?page=gf_entries&id=' . $form_id,
			'menu_class' 	=> 'gf_form_toolbar_entries',
			'link_class' 	=> self::toolbar_class("entries"),
			'capabilities' 	=> $entries_capabilities,
			'priority'		=> 800
		);

		//---- Preview ----

		$preview_capabilities = array("gravityforms_edit_forms", "gravityforms_create_form", "gravityforms_preview_forms");

		$menu_items['preview'] = array(
			'label' 		=> __("Preview", "gravityforms"),
			'icon' 			=> '<i class="fa fa-eye fa-lg"></i>',
			'title'			=> __('Preview this form', 'gravityforms'),
			'url' 			=> trailingslashit(site_url()) . '?gf_page=preview&id=' . $form_id,
			'menu_class' 	=> 'gf_form_toolbar_preview',
			'link_class' 	=> self::toolbar_class("preview"),
			'target'		=> '_blank',
			'capabilities' 	=> $preview_capabilities,
			'priority'		=> 700
		);


		return $menu_items;
	}

	public static function toolbar_sub_menu_items($menu_items, $compact = false){
		if (empty($menu_items))
			return "";

		$sub_menu_items_string = "";
		foreach ($menu_items as $menu_item){
			if(GFCommon::current_user_can_any(rgar($menu_item, "capabilities"))){
				$menu_class = rgar($menu_item, "menu_class");
				$link_class = rgar($menu_item, "link_class");
				$url = rgar($menu_item, "url");
				$label = rgar($menu_item, "label");
				$target = rgar($menu_item, "target");
				$sub_menu_items_string .= "<li class='{$menu_class}'><a href='{$url}' class='{$link_class}' target='{$target}'>{$label}</a></li>";
			}
		}
		if($compact){
			$sub_menu_items_string = '<div class="gf_submenu"><ul>' . $sub_menu_items_string . '</ul></div>';
		}else{
			$sub_menu_items_string = '<div class="gf_submenu"><ul>' . $sub_menu_items_string . '</ul></div>';
		}

		return $sub_menu_items_string;
	}

	public static function get_form_settings_sub_menu_items($form_id) {
		require_once(GFCommon::get_base_path() . '/form_settings.php');

		$sub_menu_items = array();
		$tabs = GFFormSettings::get_tabs($form_id);

		foreach($tabs as $tab) {

			if($tab['name'] == 'settings')
				$form_setting_menu_item['label'] = 'Settings';

			$sub_menu_items[] = array(
				'url' 			=> admin_url("admin.php?page=gf_edit_forms&view=settings&subview={$tab['name']}&id={$form_id}"),
				'label' 		=> $tab['label'],
				'capabilities' 	=> array("gravityforms_edit_forms")
			);

		}

		return $sub_menu_items;
	}

	private static function toolbar_class($item){

        switch($item){

            case "editor":
                if(in_array(rgget("page"), array("gf_edit_forms", "gf_new_form")) && rgempty("view", $_GET))
                    return "gf_toolbar_active";
            break;

            case "settings":
                if(rgget('view') == 'settings')
                    return "gf_toolbar_active";
                break;

            case "notifications" :
                if(rgget("page") == "gf_new_form")
                    return "gf_toolbar_disabled";
                else if(rgget("page") == "gf_edit_forms" && rgget("view") == "notification")
                    return "gf_toolbar_active";
            break;

            case "entries" :
                if(rgget("page") == "gf_new_form")
                    return "gf_toolbar_disabled";
                else if(rgget("page") == "gf_entries" && rgempty("view", $_GET))
                    return "gf_toolbar_active";

            break;

            case "preview" :
                if(rgget("page") == "gf_new_form")
                    return "gf_toolbar_disabled";

            break;
        }

        return "";
    }

    public static function admin_bar() {
        global $wp_admin_bar;

        if(!GFCommon::current_user_can_any("gravityforms_create_form"))
            return;

        $wp_admin_bar->add_menu(
            array(
                'id' => 'gravityforms-new-form',
                'parent' => 'new-content',
                'title' => esc_attr__( 'Form', 'gravityforms' ),
                'href' => admin_url( 'admin.php?page="gf_new_form' )
            )
        );

    }

}

class RGForms extends GFForms { }

//Main function call. Should be used to insert a Gravity Form from code.
function gravity_form($id, $display_title=true, $display_description=true, $display_inactive=false, $field_values=null, $ajax=false, $tabindex = 1, $echo = true){
    if (!$echo)
        return RGForms::get_form($id, $display_title, $display_description, $display_inactive, $field_values, $ajax, $tabindex);

    echo RGForms::get_form($id, $display_title, $display_description, $display_inactive, $field_values, $ajax, $tabindex);
}

//Enqueues the appropriate scripts for the specified form
function gravity_form_enqueue_scripts($form_id, $is_ajax=false){
    if(!is_admin()){
        require_once(GFCommon::get_base_path() . "/form_display.php");
        $form = RGFormsModel::get_form_meta($form_id);
        GFFormDisplay::enqueue_form_scripts($form, $is_ajax);
    }
}

if(!function_exists("rgget")){
function rgget($name, $array=null){
    if(!isset($array))
        $array = $_GET;

    if(isset($array[$name]))
        return $array[$name];

    return "";
}
}

if(!function_exists("rgpost")){
function rgpost($name, $do_stripslashes=true){
    if(isset($_POST[$name]))
        return $do_stripslashes ? stripslashes_deep($_POST[$name]) : $_POST[$name];

    return "";
}
}

if(!function_exists("rgar")){
function rgar($array, $name){
    if(isset($array[$name]))
        return $array[$name];

    return '';
}
}

if(!function_exists("rgars")){
function rgars($array, $name){
    $names = explode("/", $name);
    $val = $array;
    foreach($names as $current_name){
        $val = rgar($val, $current_name);
    }
    return $val;
}
}

if( ! function_exists( 'rgempty' ) ) {
function rgempty( $name, $array = null ) {

    if( is_array( $name ) )
        return empty( $name );

    if( ! $array )
        $array = $_POST;

    $val = rgar( $array, $name );

    return empty( $val );
}
}

if(!function_exists("rgblank")){
function rgblank($text){
    return empty($text) && strval($text) != "0";
}
}

if(!function_exists("rgobj")){
function rgobj($obj, $name){
    if(isset($obj->$name))
        return $obj->$name;

    return '';
}
}
if(!function_exists("rgexplode")){
function rgexplode($sep, $string, $count){
    $ary = explode($sep, $string);
    while(count($ary) < $count)
        $ary[] = "";

    return $ary;
}
}
