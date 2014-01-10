<?php

/**
 * Handles all tasks mostly common to any Gravity Forms Add-On, including third party ones
 *
 *
 * @package GFAddOn
 * @author Rocketgenius
 */
abstract class GFAddOn {

    /**
     * @var string Version number of the Add-On
     */
    protected $_version;
    /**
     * @var string Gravity Forms minimum version requirement
     */
    protected $_min_gravityforms_version;
    /**
     * @var string URL-friendly identifier used for form settings, add-on settings, text domain localization...
     */
    protected $_slug;
    /**
     * @var string Relative path to the plugin from the plugins folder. Example "gravityforms/gravityforms.php"
     */
    protected $_path;
    /**
     * @var string Full path the the plugin. Example: __FILE__
     */
    protected $_full_path;
    /**
     * @var string URL to the Gravity Forms website. Example: "http://www.gravityforms.com" OR affiliate link.
     */
    protected $_url;
    /**
     * @var string Title of the plugin to be used on the settings page, form settings and plugins page. Example: "Gravity Forms MailChimp Add-On"
     */
    protected $_title;
    /**
     * @var string Short version of the plugin title to be used on menus and other places where a less verbose string is useful. Example: "MailChimp"
     */
    protected $_short_title;
    /**
     * @var array Members plugin integration. List of capabilities to add to roles.
     */
    protected $_capabilities = array();

    private $_saved_settings = array();
    private $_previous_settings = array();

    /**
    * @var array Stores a copy of setting fields that failed validation; only populated after validate_settings() has been called.
    */
    private $_setting_field_errors = array();

    // ------------ Permissions -----------
    /**
     * @var string|array A string or an array of capabilities or roles that have access to the settings page
     */
    protected $_capabilities_settings_page = array();
    /**
     * @var string|array A string or an array of capabilities or roles that have access to the form settings
     */
    protected $_capabilities_form_settings = array();
    /**
     * @var string|array A string or an array of capabilities or roles that have access to the plugin page
     */
    protected $_capabilities_plugin_page = array();
    /**
     * @var string|array A string or an array of capabilities or roles that can uninstall the plugin
     */
    protected $_capabilities_uninstall = array();

    // ------------ RG Autoupgrade -----------

    /**
     * @var bool Used by Rocketgenius plugins to activate auto-upgrade.
     * @ignore
     */
    protected $_enable_rg_autoupgrade = false;

    // ------------ Private -----------

    private $_no_conflict_scripts = array();
    private $_no_conflict_styles = array();
    private $_preview_styles = array();
    private $_print_styles = array();

    /**
     * Class constructor which hooks the instance into the WordPress init action
     */
    function __construct() {

        add_action('init', array($this, 'init'));
        if ($this->_enable_rg_autoupgrade) {
            require_once("class-gf-auto-upgrade.php");
            $is_gravityforms_supported = $this->is_gravityforms_supported($this->_min_gravityforms_version);
            new GFAutoUpgrade($this->_slug, $this->_version, $this->_min_gravityforms_version, $this->_title, $this->_full_path, $this->_path, $this->_url, $is_gravityforms_supported);
        }

        $this->pre_init();
    }

    /**
     * Gets executed before all init functions. Override this function to perform initialization tasks that must be done prior to init
     */
    public function pre_init(){

        if($this->is_gravityforms_supported()){

            //Entry meta
            if ($this->method_is_overridden('get_entry_meta'))
                add_filter('gform_entry_meta', array($this, 'get_entry_meta'), 10, 2);

        }
    }

    /**
     * Plugin starting point. Handles hooks and loading of language files.
     */
    public function init() {

        load_plugin_textdomain($this->_slug, FALSE, $this->_slug . '/languages');

        if(RG_CURRENT_PAGE == 'admin-ajax.php') {

            //If gravity forms is supported, initialize AJAX
            if($this->is_gravityforms_supported()){
                $this->init_ajax();
            }

        }
        else if (is_admin()) {

            $this->init_admin();

        }
        else {

            if($this->is_gravityforms_supported()){
                $this->init_frontend();
            }
        }


    }

    /**
    * Override this function to add initialization code (i.e. hooks) for the admin site (WP dashboard)
    */
    protected function init_admin(){

        // enqueues admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'), 10, 0);

        // message enforcing min version of Gravity Forms
        if (isset($this->_min_gravityforms_version) && RG_CURRENT_PAGE == "plugins.php" && false === $this->_enable_rg_autoupgrade)
            add_action('after_plugin_row_' . $this->_path, array($this, 'plugin_row'));

        // STOP HERE IF GRAVITY FORMS IS NOT SUPPORTED
        if (isset($this->_min_gravityforms_version) && !$this->is_gravityforms_supported($this->_min_gravityforms_version))
            return;

        $this->setup();

        // Add form settings only when there are form settings fields configured or form_settings() method is implemented
        if (self::has_form_settings_page()) {
            $this->form_settings_init();
        }

        // Add plugin page when there is a plugin page configured or plugin_page() method is implemented
        if (self::has_plugin_page()) {
            $this->plugin_page_init();
        }

        // Add addon settings page only when there are addon settings fields configured or settings_page() method is implemented
        if (self::has_plugin_settings_page()) {
            if ($this->current_user_can_any($this->_capabilities_settings_page)) {
                $this->plugin_settings_init();
            }
        }

        // Members plugin integration
        if (self::has_members_plugin())
            add_filter('members_get_capabilities', array($this, 'members_get_capabilities'));

        // Results page
        if ($this->method_is_overridden('get_results_page_config')) {
            $results_page_config = $this->get_results_page_config();
            $results_capabilities = rgar($results_page_config, "capabilities");
            if($results_page_config && $this->current_user_can_any($results_capabilities))
                $this->results_page_init($results_page_config);
        }

        // Locking
        if ($this->method_is_overridden('get_locking_config')) {
            require_once(GFCommon::get_base_path() . "/includes/locking/class-gf-locking.php");
            require_once("class-gf-addon-locking.php");
            $config = $this->get_locking_config();
            new GFAddonLocking($config, $this);
        }

        // No conflict scripts
        add_filter('gform_noconflict_scripts', array($this, 'register_noconflict_scripts'));
        add_filter('gform_noconflict_styles', array($this, 'register_noconflict_styles'));

    }

    /**
    * Override this function to add initialization code (i.e. hooks) for the public (customer facing) site
    */
    protected function init_frontend(){

        $this->setup();

        add_filter('gform_preview_styles', array($this,  "enqueue_preview_styles"), 10, 2);
        add_filter('gform_print_styles', array($this,  "enqueue_print_styles"), 10, 2);
        add_action("gform_enqueue_scripts", array($this, "enqueue_scripts"), 10, 2);

    }

    /**
    * Override this function to add AJAX hooks or to add initialization code when an AJAX request is being performed
    */
    protected function init_ajax(){
        if (rgpost("view") == "gf_results_" . $this->_slug) {
            require_once(GFCommon::get_base_path() . "/tooltips.php");
            require_once("class-gf-results.php");
            $gf_results = new GFResults($this->_slug, $this->get_results_page_config());
            add_action('wp_ajax_gresults_get_results_gf_results_' . $this->_slug, array($gf_results, 'ajax_get_results'));
            add_action('wp_ajax_gresults_get_more_results_gf_results_' . $this->_slug, array($gf_results, 'ajax_get_more_results'));
        } elseif ($this->method_is_overridden('get_locking_config')) {
            require_once(GFCommon::get_base_path() . "/includes/locking/class-gf-locking.php");
            require_once("class-gf-addon-locking.php");
            $config = $this->get_locking_config();
            new GFAddonLocking($config, $this);
        }
    }


    //--------------  Setup  ---------------

    /**
     * Performs upgrade tasks when the version of the Add-On changes. To add additional upgrade tasks, override the upgrade() function, which will only get executed when the plugin version has changed.
     */
    protected function setup() {

        //Upgrading add-on
        $installed_version = get_option("gravityformsaddon_" . $this->_slug . "_version");

        //Making sure version has really changed. Gets around aggressive caching issue on some sites that cause setup to run multiple times.
        if ($installed_version != $this->_version){
            $installed_version = GFForms::get_wp_option("gravityformsaddon_" . $this->_slug . "_version");
        }

        //Upgrade if version has changed
        if ($installed_version != $this->_version){

            $this->upgrade($installed_version);
            update_option("gravityformsaddon_" . $this->_slug . "_version", $this->_version);
        }
    }

    /**
     * Override this function to add to add database update scripts or any other code to be executed when the Add-On version changes
     */
    protected function upgrade($previous_version) {
        return;
    }


    //--------------  Script enqueuing  ---------------

    /**
    * Override this function to provide a list of styles to be enqueued.
    * When overriding this function, be sure to call parent::styles() to ensure the base class scripts are enqueued.
    * See scripts() for an example of the format expected to be returned.
    */
    protected function styles(){
        return array(
            array(  "handle" => "gaddon_form_settings_css",
                    "src" => GFAddOn::get_gfaddon_base_url() . "/css/gaddon_form_settings.css",
                    "version" => GFCommon::$version,
                    "enqueue" => array(
                        array("admin_page" => array("form_settings", "plugin_settings", "plugin_page") )
                    )
            ),
            array(  "handle" => "gaddon_results_css",
                    "src" => GFAddOn::get_gfaddon_base_url() . "/css/gaddon_results.css",
                    "version" => GFCommon::$version,
                    "enqueue" => array(
                        array("admin_page" => array("results") )
                    )
            )
        );
    }


    /**
    * Override this function to provide a list of scripts to be enqueued.
    * When overriding this function, be sure to call parent::scripts() to ensure the base class scripts are enqueued.
    * Following is an example of the array that is expected to be returned by this function:
    *<pre>
    * <code>
    *
    *    array(
    *        array(  "handle" => "maskedinput",
    *                "src" => GFCommon::get_base_url() . "/js/jquery.maskedinput-1.3.min.js",
    *                "version" => GFCommon::$version,
    *                "deps" => array("jquery"),
    *                "in_footer" => false,
    *
    *                //Determines where the script will be enqueued. The script will be enqueued if any of the conditions match
    *                "enqueue" => array(
    *                                    //admin_page - Specified one or more pages (known pages) where the script is supposed to be enqueued. When this setting is specified, scripts will only be enqueued in those pages.
    *                                    //To enqueue scripts in the front end (public website), simply don't define this setting
    *                                    array("admin_page" => array("form_settings", "plugin_settings") ),
    *
    *                                    //tab - Specifies a form settings or plugin settings tab in which the script is supposed to be enqueued. If none is specified, the script will be enqueued in any of the form settings or plugin_settings page
    *                                    array("tab" => "signature"),
    *
    *                                    //query - Specifies a set of query string ($_GET) values. If all specified query string values match the current requested page, the script will be enqueued
    *                                    array("query" => "page=gf_edit_forms&view=settings&id=_notempty_")
    *
    *                                    //post - Specifies a set of post ($_POST) values. If all specified posted values match the current request, the script will be enqueued
    *                                    array("post" => "posted_field=val")
    *
    *                                    )
    *            ),
    *        array(
    *            "handle" => "super_signature_script",
    *            "src" => $this->get_base_url() . "/super_signature/ss.js",
    *            "version" => $this->_version,
    *            "deps" => array("jquery"),
    *            "callback" => array($this, "localize_scripts"),
    *            "strings" => array(
    *                               // Accessible in JavaScript using the global variable "[script handle]_strings"
    *                               "stringKey1" => __("The string", "gravityforms"),
    *                               "stringKey2" => __("Another string.", "gravityforms")
    *                               )
    *            "enqueue" => array(
    *                                //field_types - Specifies one or more field types that requires this script. The script will only be enqueued if the current form has a field of any of the specified field types. Only applies when a current form is available.
    *                                array("field_types" => array("signature"))
    *                                )
    *        )
    *  );
    *
    * </code>
    * </pre>
    */
    protected function scripts(){
        return array(
            array(
                'handle' => 'gform_form_admin',
                'enqueue' => array( array( "admin_page" => array("form_settings") ) )
            ),
            array(
                'handle' => 'gform_gravityforms',
                'enqueue' => array( array( "admin_page" => array("form_settings") ) )
            ),
            array("handle"   => "google_charts",
                  "src"      => "https://www.google.com/jsapi",
                  "version"  => GFCommon::$version,
                  "enqueue"  => array(
                      array("admin_page" => array("results")),
                  )
            ),
            array("handle"   => "gaddon_results_js",
                  "src"      => GFAddOn::get_gfaddon_base_url() . "/js/gaddon_results.js",
                  "version"  => GFCommon::$version,
                  "deps"     => array('jquery', 'sack', 'jquery-ui-resizable', 'jquery-ui-datepicker' , 'google_charts', 'gform_field_filter'),
                  "callback" => array('GFResults', "localize_results_scripts"),
                  "enqueue"  => array(
                      array("admin_page" => array("results")),
                  )
            )
        );
    }


    /**
    * Target of admin_enqueue_scripts and gform_enqueue_scripts hooks.
    * Not intended to be overridden by child classes.
    * In order to enqueue scripts and styles, override the scripts() and styles() functions
     *
    * @ignore
    */
    public function enqueue_scripts($form="", $is_ajax=false){

        if(empty($form))
            $form = $this->get_current_form();

        //Enqueueing scripts
        $scripts = $this->scripts();
        foreach($scripts as $script){
            $src = isset($script["src"]) ? $script["src"] : false;
            $deps = isset($script["deps"]) ? $script["deps"] : array();
            $version =  isset($script["version"]) ? $script["version"] : false;
            $in_footer =  isset($script["in_footer"]) ? $script["in_footer"] : false;
            wp_register_script($script["handle"],$src, $deps, $version, $in_footer);
            if(isset($script["enqueue"]) && $this->_can_enqueue_script($script["enqueue"], $form, $is_ajax)){
                $this->add_no_conflict_scripts(array($script["handle"]));
                wp_enqueue_script($script["handle"]);
                if(isset($script["strings"]))
                    wp_localize_script($script["handle"], $script["handle"]."_strings", $script["strings"]);
                if(isset($script["callback"]) && is_callable($script["callback"])){
                    $args = compact("form", "is_ajax");
                    call_user_func_array($script["callback"], $args);
                }
            }
        }

        //Enqueueing styles
        $styles =  $this->styles();
        foreach($styles as $style){
            $src = isset($style["src"]) ? $style["src"] : false;
            $deps = isset($style["deps"]) ? $style["deps"] : array();
            $version =  isset($style["version"]) ? $style["version"] : false;
            $media =  isset($style["media"]) ? $style["media"] : "all";
            wp_register_style($style["handle"], $src, $deps, $version, $media);
            if($this->_can_enqueue_script($style["enqueue"], $form, $is_ajax)){
                $this->add_no_conflict_styles(array($style["handle"]));
                if ($this->is_preview()) {
                    $this->_preview_styles[] = $style["handle"];
                } elseif ($this->is_print()) {
                    $this->_print_styles[] = $style["handle"];
                } else {
                    wp_enqueue_style($style["handle"]);
                }
            }
        }
    }

    /**
     * Target of gform_preview_styles. Enqueue styles to the preview page.
     * Not intented to be overriden by child classes
     *
     * @ignore
     */
    public function enqueue_preview_styles($preview_styles, $form) {
        return array_merge($preview_styles, $this->_preview_styles);
    }


    /**
     * Target of gform_print_styles. Enqueue styles to the print entry page.
     * Not intented to be overriden by child classes
     *
     * @ignore
     */
    public function enqueue_print_styles($print_styles, $form) {
        if(false === $print_styles)
            $print_styles = array();

        $styles =  $this->styles();
        foreach($styles as $style){
            if($this->_can_enqueue_script($style["enqueue"], $form, false)){
                $this->add_no_conflict_styles(array($style["handle"]));
                $src = isset($style["src"]) ? $style["src"] : false;
                $deps = isset($style["deps"]) ? $style["deps"] : array();
                $version =  isset($style["version"]) ? $style["version"] : false;
                $media =  isset($style["media"]) ? $style["media"] : "all";
                wp_register_style($style["handle"], $src, $deps, $version, $media);
                $print_styles[] = $style["handle"];
            }
        }

        return array_merge($print_styles, $this->_print_styles);
    }


    /**
     * Adds scripts to the list of white-listed no conflict scripts.
     *
     * @param $scripts
     */
    private function add_no_conflict_scripts($scripts) {
        $this->_no_conflict_scripts = array_merge($scripts, $this->_no_conflict_scripts);

    }

    /**
     * Adds styles to the list of white-listed no conflict styles.
     *
     * @param $styles
     */
    private function add_no_conflict_styles($styles) {
        $this->_no_conflict_styles = array_merge($styles, $this->_no_conflict_styles);
    }

    private function _can_enqueue_script($enqueue_conditions, $form, $is_ajax){
        if(empty($enqueue_conditions))
            return false;

        foreach($enqueue_conditions as $condition){
            if(is_callable($condition)){
                return call_user_func($condition, $form, $is_ajax);
            }
            else{
                $query_matches = isset($condition["query"]) ? $this->_request_condition_matches($_GET, $condition["query"]) : true;
                $post_matches  = isset($condition["post"]) ? $this->_request_condition_matches($_POST, $condition["query"]) : true;
                $admin_page_matches = isset($condition["admin_page"]) ? $this->_page_condition_matches($condition["admin_page"], rgar($condition,"tab")) : true;
                $field_type_matches = isset($condition["field_types"]) ? $this->_field_condition_matches($condition["field_types"], $form) : true;

                //Scripts will only be enqueued in any admin page if "admin_page", "query" or "post" variable is set.
                //Scripts will only be enqueued in the front end if "admin_page" is not set.
                $site_matches = ( isset($condition["admin_page"]) && is_admin() ) || ( !isset($condition["admin_page"]) && !is_admin() ) || ( isset($condition["query"]) || isset($condition["post"]) ) ;

                if($query_matches && $post_matches && $admin_page_matches && $field_type_matches && $site_matches){
                    return true;
                }
            }
        }
        return false;
    }

    private function _request_condition_matches($request, $query){
        parse_str($query, $query_array);
        foreach($query_array as $key => $value){

            switch ($value){
                case "_notempty_" :
                    if(rgempty($key, $request))
                        return false;
                    break;
                case "_empty_" :
                    if(!rgempty($key, $request))
                        return false;
                    break;
                default :
                    if (rgar($request, $key) != $value)
                        return false;
                    break;
            }

        }
        return true;
    }

    private function _page_condition_matches($pages, $tab){
        if(!is_array($pages))
            $pages = array($pages);

        foreach($pages as $page){
            switch($page){
                case "form_editor" :
                    if($this->is_form_editor())
                        return true;

                    break;

                case "form_settings" :
                    if($this->is_form_settings($tab))
                        return true;

                    break;

                case "plugin_settings" :
                    if($this->is_plugin_settings($tab))
                        return true;

                    break;

                case "plugin_page" :
                    if($this->is_plugin_page())
                        return true;

                    break;

                case "entry_view" :
                    if($this->is_entry_view())
                        return true;

                    break;

                case "entry_edit" :
                    if($this->is_entry_edit())
                        return true;

                    break;

                case "results" :
                    if($this->is_results())
                        return true;

                    break;

            }
        }
        return false;

    }

    private function _field_condition_matches($field_types, $form){
        if(!is_array($field_types))
            $field_types = array($field_types);

        $fields = GFCommon::get_fields_by_type($form, $field_types);
        if(count($fields) > 0)
            return true;

        return false;
    }

    /**
     * Target for the gform_noconflict_scripts filter. Adds scripts to the list of white-listed no conflict scripts.
     *
     * Not intended to be overridden or called directed by Add-Ons.
     *
     * @ignore
     *
     * @param array $scripts Array of scripts to be white-listed
     * @return array
     */
    public function register_noconflict_scripts($scripts) {
        //registering scripts with Gravity Forms so that they get enqueued when running in no-conflict mode
        return array_merge($scripts, $this->_no_conflict_scripts);
    }

    /**
     * Target for the gform_noconflict_styles filter. Adds styles to the list of white-listed no conflict scripts.
     *
     * Not intended to be overridden or called directed by Add-Ons.
     *
     * @ignore
     *
     * @param array $styles Array of styles to be white-listed
     * @return array
     */
    public function register_noconflict_styles($styles) {
        //registering styles with Gravity Forms so that they get enqueued when running in no-conflict mode
        return array_merge($styles, $this->_no_conflict_styles);
    }



    //--------------  Entry meta  --------------------------------------

    /**
     * Override this method to activate and configure entry meta.
     *
     *
     * @param array $entry_meta An array of entry meta already registered with the gform_entry_meta filter.
     * @param int $form_id The form id
     *
     * @return array The filtered entry meta array.
     */
    protected function get_entry_meta($entry_meta, $form_id) {
        return $entry_meta;
    }


    //--------------  Results page  --------------------------------------
    /**
     * Returns the configuration for the results page. By default this is not activated.
     * To activate the results page override this function and return an array with the configuration data.
     *
     * Example:
     * public function get_results_page_config() {
     *      return array(
     *       "title" => "Quiz Results",
     *       "capabilities" => array("gravityforms_quiz_results"),
     *       "callbacks" => array(
     *          "fields" => array($this, "results_fields"),
     *          "calculation" => array($this, "results_calculation"),
     *          "markup" => array($this, "results_markup"),
     *              )
     *       );
     * }
     *
     */
    public function get_results_page_config() {
        return false;
    }

    /**
     * Initializes the result page functionality. To activate result page functionality, override the get_results_page_config() function.
     *
     * @param $results_page_config - configuration returned by get_results_page_config()
     */
    protected function results_page_init($results_page_config) {
        require_once("class-gf-results.php");

        if(isset($results_page_config["callbacks"]["filters"]))
            add_filter("gform_filters_pre_results", $results_page_config["callbacks"]["filters"], 10, 2);

        if(isset($results_page_config["callbacks"]["filter_ui"])){
            add_filter("gform_filter_ui", $results_page_config["callbacks"]["filter_ui"], 10, 5);
        }

        $gf_results = new GFResults($this->_slug, $results_page_config);
        $gf_results->init();
    }


    //--------------  Members plugin integration  --------------------------------------

    /**
     * Checks whether the Members plugin is installed and activated.
     *
     * Not intended to be overridden or called directly by Add-Ons.
     *
     * @ignore
     *
     * @return bool
     */
    protected function has_members_plugin() {
        return function_exists('members_get_capabilities');
    }

    /**
     * Not intended to be overridden or called directly by Add-Ons.
     *
     * @ignore
     *
     * @param $caps
     * @return array
     */
    public function members_get_capabilities($caps) {
        return array_merge($caps, $this->_capabilities);
    }

    //--------------  Permissions: Capabilities and Roles  ----------------------------

    /**
     *  Checks whether the current user is assigned to a capability or role.
     *
     * @param string|array $caps An string or array of capabilities to check
     * @return bool Returns true if the current user is assigned to any of the capabilities.
     */
    protected function current_user_can_any($caps) {
        return GFCommon::current_user_can_any($caps);
    }


    //------- Settings Helper Methods (Common to all settings pages) -------------------

    /***
    * Renders the UI of all settings page based on the specified configuration array $sections
    *
    * @param array $sections - Configuration array containing all fields to be rendered grouped into sections
    */
    protected function render_settings( $sections ) {

        if( ! $this->has_setting_field_type( 'save', $sections ) )
            $sections = $this->add_default_save_button( $sections );

        ?>

        <form id="gform-settings" action="" method="post">

            <?php $this->settings($sections); ?>

        </form>

        <?php
    }

    /***
    * Renders settings fields based on the specified configuration array $sections
    *
    * @param array $sections - Configuration array containing all fields to be rendered grouped into sections
    */
    protected function settings( $sections ){
        $is_first = true;
        foreach( $sections as $section ) {
            if( $this->setting_dependency_met( rgar( $section, 'dependency' ) ) )
                $this->single_section( $section, $is_first);

            $is_first = false;
        }
    }

    /***
    * Displays the UI for a field section
    *
    * @param array $section - The section to be displayed
    * @param bool $is_first - true for the first section in the list, false for all others
    */
    protected function single_section( $section, $is_first = false) {

        extract( wp_parse_args( $section, array(
            'title' => false,
            'description' => false,
            'id' => '',
            'class' => false,
            'style' => '',
            ''
        ) ) );

        $classes = array( 'gaddon-section' );

        if( $is_first )
            $classes[] = 'gaddon-first-section';

        if( $class )
            $classes[] = $class;

        ?>

        <div
            id="<?php echo $id; ?>"
            class="<?php echo implode( ' ', $classes ); ?>"
            style="<?php echo $style; ?>"
            >

            <?php if( $title ): ?>
                <h4 class="gaddon-section-title gf_settings_subgroup_title"><?php echo $title; ?></h4>
            <?php endif; ?>

            <?php if( $description ): ?>
                <p class="gaddon-section-description"><?php echo $description; ?></p>
            <?php endif; ?>

            <table class="form-table">

                <?php
                foreach($section['fields'] as $field) {

                    if( ! $this->setting_dependency_met( rgar( $field, 'dependency' ) ) )
                        continue;

                    if ( is_callable( array( $this, "single_setting_row_{$field["type"]}") ) ) {
                        call_user_func( array( $this, "single_setting_row_{$field["type"]}" ), $field );
                    } else {
                        $this->single_setting_row( $field );
                    }

                }
                ?>

            </table>

        </div>

        <?php
    }

    /***
    * Displays the UI for the field container row
    *
    * @param array $field - The field to be displayed
    */
    protected function single_setting_row( $field ) {

        $display = rgar( $field, 'hidden' ) ? 'style="display:none;"' : '';

        ?>

        <tr id="gaddon-setting-row-<?php echo $field["name"] ?>" <?php echo $display; ?>>
            <th>
                <?php

                    echo $field["label"];

                    if( rgar( $field, 'required' ) )
                        echo ' ' . $this->get_required_indicator( $field );

                    if( isset( $field['tooltip'] ) )
                        echo ' ' . gform_tooltip( $field['tooltip'], rgar( $field, 'tooltip_class'), true );

                ?>
            </th>
            <td>
                <?php $this->single_setting( $field ); ?>
            </td>
        </tr>

        <?php
    }

    protected function single_setting_row_save( $field ) {
        ?>

        <tr>
            <td colspan="2">
                <?php $this->single_setting( $field ); ?>
            </td>
        </tr>

        <?php
    }

    /***
    * Calls the appropriate field function to handle rendering of each specific field type
    *
    * @param array $field - The field to be rendered
    */
    protected function single_setting( $field ) {
        if( is_callable( rgar( $field, 'callback' ) ) ) {
            call_user_func($field["callback"], $field);
        } else if (is_callable(array($this, "settings_{$field["type"]}"))) {
            call_user_func(array($this, "settings_{$field["type"]}"), $field);
        } else {
            printf(__("Field type '%s' has not been implemented", "gravityforms"), $field["type"] );
        }
    }

    /***
    * Sets the current saved settings to a class variable so that it can be accessed by lower level functions in order to initialize inputs with the appropriate values
    *
    * @param array $settings: Settings to be saved
    */
    protected function set_settings( $settings ) {
        $this->_saved_settings = $settings;
    }

    /***
    * Sets the previous settings to a class variable so that it can be accessed by lower level functions providing support for
    * verifying whether a value was changed before executing an action
    *
    * @param array $settings: Settings to be stored
    */
    protected function set_previous_settings( $settings ) {
        $this->_previous_settings = $settings;
    }

    protected function get_previous_settings() {
        return $this->_previous_settings;
    }



    /***
    * Gets settings from $_POST variable, returning a name/value collection of setting name and setting value
    */
    protected function get_posted_settings(){
        global $_gaddon_posted_settings;

        if(isset($_gaddon_posted_settings))
            return $_gaddon_posted_settings;

        $_gaddon_posted_settings = array();
        if(count($_POST) > 0){
            foreach($_POST as $key => $value){
                if(preg_match("|_gaddon_setting_(.*)|", $key, $matches)){
                    $_gaddon_posted_settings[$matches[1]] = self::maybe_decode_json(stripslashes_deep($value));
                }
            }
        }

       return $_gaddon_posted_settings;
    }

    protected static function maybe_decode_json($value) {
        if (self::is_json($value))
            return json_decode($value, ARRAY_A);

        return $value;
    }

    protected static function is_json($value) {
        if (is_string($value) && substr($value, 0, 1) == '{' && is_array(json_decode($value, ARRAY_A)))
            return true;

        return false;
    }

    /***
    * Gets the "current" settings, which are settings from $_POST variables if this is a postback request, or the current saved settings for a get request.
    */
    protected function get_current_settings(){
        //try getting settings from post
        $settings = $this->get_posted_settings();

        //if nothing has been posted, get current saved settings
        if(empty($settings)){
            $settings = $this->_saved_settings;
        }

        return $settings;
    }

    /***
    * Retrieves the setting for a specific field/input
    *
    * @param string $setting_name The field or input name
    * @param string $default_value  Optional. The default value
    * @return string|array
    */
    protected function get_setting( $setting_name, $default_value = "" , $settings = false ) {

        if( ! $settings )
            $settings = $this->get_current_settings();

        if (false === $settings)
            return $default_value;

        if (strpos($setting_name, "[") !== false) {
            $path_parts = explode("[", $setting_name);
            foreach ($path_parts as $part) {
                $part = trim($part, "]");
                if (false === isset($settings[$part]))
                    return $default_value;
                $settings = rgar($settings, $part);
            }
            $setting = $settings;
        } else {
            if (false === isset($settings[$setting_name]))
                return $default_value;
            $setting = $settings[$setting_name];
        }

        return $setting;
    }

    /***
    * Determines if a dependent field has been populated.
    *
    * @param string $dependency - Field or input name of the "parent" field.
    * @return bool - true if the "parent" field has been filled out and false if it has not.
    *
    */
    protected function setting_dependency_met( $dependency ) {

        // if no dependency, always return true
        if( ! $dependency )
            return true;

        //use a callback if one is specified in the configuration
        if(is_callable($dependency)){
            return call_user_func($dependency);
        }

        if(is_array($dependency)){
            //supports: "dependency" => array("field" => "myfield", "values" => array("val1", "val2"))
            $dependency_field = $dependency["field"];
            $dependency_value = $dependency["values"];
        }
        else{
            //supports: "dependency" => "myfield"
            $dependency_field = $dependency;
            $dependency_value = "_notempty_";
        }

        if(!is_array($dependency_value))
            $dependency_value = array($dependency_value);

        $current_value = $this->get_setting($dependency_field);

        foreach($dependency_value as $val){
            if($current_value == $val)
                return true;

            if($val == "_notempty_" && !rgblank($current_value))
                return true;
        }

        return false;
    }

    protected function has_setting_field_type( $type, $fields ) {

        foreach( $fields as &$section ) {
            foreach( $section['fields'] as $field ) {
                if( rgar( $field, 'type' ) == $type )
                    return true;
            }
        }

        return false;
    }

    protected function add_default_save_button( $sections ) {
        $sections[count($sections) - 1]['fields'][] = array( 'type' => 'save' );
        return $sections;
    }

    protected function get_save_success_message( $sections ) {
        $save_button = $this->get_save_button($sections);
        return isset($save_button["messages"]["success"]) ? $save_button["messages"]["success"] : __("Settings updated", "gravityforms") ;
    }

    protected function get_save_error_message( $sections ) {
        $save_button = $this->get_save_button($sections);
        return isset($save_button["messages"]["error"]) ? $save_button["messages"]["error"] : __("There was an error while saving your settings", "gravityforms") ;
    }

    protected function get_save_button($sections){
        $fields =  $sections[count($sections) - 1]['fields'];

        foreach($fields as $field){
            if ($field["type"] == "save")
                return $field;
        }

        return false;
    }



    //------------- Field Types ------------------------------------------------------

    /***
    * Renders and initializes a text field based on the $field array
    *
    * @param array $field - Field array containing the configuration options of this field
    * @param bool $echo = true - true to echo the output to the screen, false to simply return the contents as a string
    * @return string The HTML for the field
    */
    protected function settings_text($field, $echo = true) {

        $field["type"] = "text"; //making sure type is set to text
        $attributes = $this->get_field_attributes($field);
        $default_value = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
        $value = $this->get_setting($field["name"], $default_value);


        $name = esc_attr($field["name"]);
        $tooltip =  isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class'), true ) : "";
        $html = "";

        $html .= '<input
                    type="text"
                    name="_gaddon_setting_' . esc_attr($field["name"]) . '"
                    value="' . esc_attr($value) . '"' .
                    implode( ' ', $attributes ) .
                ' />';

        $feedback_callback = rgar($field, 'feedback_callback');
        if(is_callable($feedback_callback)){
            $is_valid = call_user_func_array( $feedback_callback, array( $value, $field ) );
            $icon = "";
            if($is_valid === true)
                $icon = "icon-check fa-check gf_valid"; // check icon
            else if($is_valid === false)
                $icon = "icon-remove fa-times gf_invalid"; // x icon

            if(!empty($icon))
                $html .= "&nbsp;&nbsp;<i class=\"fa {$icon}\"></i>";
        }

        if( $this->field_failed_validation( $field ) )
            $html .= $this->get_error_icon( $field );

        if ($echo)
            echo $html;

        return $html;
    }

    /***
    * Renders and initializes a textarea field based on the $field array
    *
    * @param array $field - Field array containing the configuration options of this field
    * @param bool $echo = true - true to echo the output to the screen, false to simply return the contents as a string
    * @return string The HTML for the field
    */
    protected function settings_textarea($field, $echo = true) {
        $field["type"] = "textarea"; //making sure type is set to textarea
        $attributes = $this->get_field_attributes($field);
        $default_value = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
        $value = $this->get_setting($field["name"], $default_value);

        $name = '' . esc_attr($field["name"]);
        $tooltip =  isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class'), true ) : "";
        $html = "";

        $html .= '<textarea
                    name="_gaddon_setting_' . $name . '"' .
                    implode( ' ', $attributes ) .
                 '>' .
                    esc_html($value) .
                '</textarea>';

        if ($echo)
            echo $html;

        return $html;
    }


    /***
    * Renders and initializes a hidden field based on the $field array
    *
    * @param array $field - Field array containing the configuration options of this field
    * @param bool $echo = true - true to echo the output to the screen, false to simply return the contents as a string
    * @return string The HTML for the field
    */
    protected function settings_hidden($field, $echo = true) {
        $field["type"] = "hidden"; //making sure type is set to hidden
        $attributes = $this->get_field_attributes($field);

        $default_value = rgar( $field, 'value' ) ? rgar( $field, 'value' ) : rgar( $field, 'default_value' );
        $value = $this->get_setting($field["name"], $default_value);

        if ( is_array( $value ) )
            $value = json_encode($value);

        $html = '<input
                    type="hidden"
                    name="_gaddon_setting_' . esc_attr($field["name"]) . '"
                    value="' . esc_attr($value) . '"' .
                    implode( ' ', $attributes ) .
                ' />';

        if ($echo)
            echo $html;

        return $html;
    }

    /***
    * Renders and initializes a checkbox field or a collection of checkbox fields based on the $field array
    *
    * @param array $field - Field array containing the configuration options of this field
    * @param bool $echo = true - true to echo the output to the screen, false to simply return the contents as a string
    * @return string The HTML for the field
    */
    protected function settings_checkbox( $field, $echo = true ) {

        $field["type"] = "checkbox"; //making sure type is set to checkbox

        $default_attributes = array("onclick" => 'jQuery(this).siblings("input[type=hidden]").val(jQuery(this).prop("checked") ? 1 : 0);');
        $field_attributes = $this->get_field_attributes($field, $default_attributes);
        $horizontal = rgar($field, "horizontal") ? " gaddon-setting-inline" : "";

        $html = "";

        if(is_array($field["choices"])){
            foreach( $field["choices"] as $choice ) {
                $choice['id'] = $choice['name'];
                $choice_attributes = $this->get_choice_attributes($choice, $field_attributes);
                $value        = $this->get_setting($choice['name'], rgar($choice, "default_value"));
                $tooltip =  isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class'), true ) : "";
                $hidden_field_value = $value == '1' ? '1' : '0';
                $html .= '
                    <div id="gaddon-setting-checkbox-choice-' . $choice['id'] . '" class="gaddon-setting-checkbox' . $horizontal . '">
                        <input type=hidden name="_gaddon_setting_' . esc_attr($choice["name"]) . '" value="' . $hidden_field_value . '" />
                        <input
                            type = "checkbox"' .
                            implode( ' ', $choice_attributes ) . ' ' .
                            checked( $value, "1", false ) .
                        ' />
                        <label for="' . esc_attr($choice['id']) . '">' . esc_html($choice['label']) . ' ' . $tooltip .'</label>
                    </div>
                    ';
            }
        }

        if ($echo)
            echo $html;

        return $html;
    }

    /***
     * Renders and initializes a radio field or a collection of radio fields based on the $field array
     *
     * @param array $field - Field array containing the configuration options of this field
     * @param bool $echo = true - true to echo the output to the screen, false to simply return the contents as a string
     * @return string Returns the markup for the radio buttons
     *
     */
    protected function settings_radio($field, $echo = true) {

        $field["type"] = "radio"; //making sure type is set to radio

        $selected_value = $this->get_setting($field['name'], rgar($field, "default_value"));
        $field_attributes = $this->get_field_attributes($field);
        $horizontal = rgar($field, "horizontal") ? " gaddon-setting-inline" : "";
        $html = "";
        if (is_array($field["choices"])) {
            foreach ($field["choices"] as $i => $choice) {
                $choice['id'] = $field['name'] . $i;
                $choice_attributes = $this->get_choice_attributes($choice, $field_attributes);

                $tooltip =  isset( $choice['tooltip'] ) ? gform_tooltip( $choice['tooltip'], rgar( $choice, 'tooltip_class'), true ) : "";

                $radio_value = isset($choice["value"]) ? $choice["value"] : $choice["label"];
                $checked     = checked($selected_value, $radio_value, false);
                $html .= '
                        <div id="gaddon-setting-radio-choice-' . $choice['id'] . '" class="gaddon-setting-radio' . $horizontal . '">
                        <input
                            id = "' . esc_attr($choice['id']) . '"
                            type = "radio" ' .
                            'name="_gaddon_setting_' . esc_attr($field["name"]) . '" ' .
                            'value="' . $radio_value . '" ' .
                            implode( ' ', $choice_attributes ) . ' ' .
                            $checked .
                            ' />
                            <label for="' . esc_attr($choice['id']) . '">' . esc_html($choice['label']) . ' ' . $tooltip . '</label>
                        </div>
                    ';
            }
        }

        if ($echo)
            echo $html;

        return $html;
    }

    /***
    * Renders and initializes a drop down field based on the $field array
    *
    * @param array $field - Field array containing the configuration options of this field
    * @param bool $echo = true - true to echo the output to the screen, false to simply return the contents as a string
    *
    * @return string The HTML for the field
    */
    protected function settings_select( $field, $echo = true ) {

        $field["type"] = "select"; //making sure type is set to select
        $attributes = $this->get_field_attributes($field);
        $value = $this->get_setting($field["name"], rgar($field, "default_value"));
        $name = '' . esc_attr($field["name"]);

        $options = "";
        if(is_array($field["choices"])){
            foreach( $field["choices"] as $choice ) {

                if(!isset($choice["value"]))
                    $choice["value"] = $choice["label"];

                $options .= '<option value="' . esc_attr($choice['value']) . '" ' . selected( $value, $choice['value'], false ) . '>' .
                            $choice['label'] .
                         '</option>';
            }
        }

        $html =     '<select
                            name="_gaddon_setting_' . $name . '"' .
                            implode( ' ', $attributes ) .
                            '>' .
                        $options .
                    '</select>';

        if( $this->field_failed_validation( $field ) )
            $html .= $this->get_error_icon( $field );

        if ($echo)
            echo $html;

        return $html;
    }

    /***
     * Renders the save button for settings pages
     *
     * @param array $field - Field array containing the configuration options of this field
     * @param bool $echo = true - true to echo the output to the screen, false to simply return the contents as a string
     *
     * @return string The HTML
     */
    protected function settings_save( $field, $echo = true ) {

        $field['type'] = 'submit';
        $field['name'] = 'gform-settings-save';
        $field['class'] = 'button-primary gfbutton';

        if( ! rgar( $field, 'value' ) )
            $field['value'] = __( 'Update Settings', 'gravityforms' );

        $attributes = $this->get_field_attributes( $field );

        $html = '<input
                    type="' . $field['type'] . '"
                    name="' . esc_attr($field["name"]) . '"
                    value="' . $field['value'] . '" ' .
                    implode( ' ', $attributes ) .
                ' />';

        if ( $echo )
            echo $html;

        return $html;
    }

    /**
    * Helper to create a simple conditional logic set of fields. It creates one row of conditional logic with Field/Operator/Value inputs.
    *
    * @param mixed $setting_name_root - The root name to be used for inputs. It will be used as a prefix to the inputs that make up the conditional logic fields.
    * @return string The HTML
    */
    protected function simple_condition($setting_name_root){

        $conditional_fields = $this->get_conditional_logic_fields();
        $create_condition_value_script = "";

        $str = $this->settings_select(array(
                                    "name" => "{$setting_name_root}_field_id",
                                    "type" => "select",
                                    "choices" => $conditional_fields,
                                    "class" => "optin_select",
                                    "onchange" => "jQuery('#" . esc_attr($setting_name_root) . "_container').html(GetRuleValues('gf_setting', 0, jQuery(this).val(), '', '_gaddon_setting_" . esc_attr($setting_name_root) . "_value'));"
                                ), false);

        $str .= $this->settings_select(array(
                                    "name" => "{$setting_name_root}_operator",
                                    "type" => "select",
                                    "choices" => array(
                                        array(
                                            "value" => "is",
                                            "label" => __("is", "gravityforms")
                                            ),
                                        array(
                                            "value" => "isnot",
                                            "label" => __("is not", "gravityforms")
                                            ),
                                        array(
                                            "value" => ">",
                                            "label" => __("greater than", "gravityforms")
                                            ),
                                        array(
                                            "value" => "<",
                                            "label" => __("less than", "gravityforms")
                                            ),
                                        array(
                                            "value" => "contains",
                                            "label" => __("contains", "gravityforms")
                                            ),
                                        array(
                                            "value" => "starts_with",
                                            "label" => __("starts with", "gravityforms")
                                            ),
                                        array(
                                            "value" => "ends_with",
                                            "label" => __("ends with", "gravityforms")
                                            )
                                        )
                                ), false);

        $str .= "<span id='{$setting_name_root}_container'></span>";

        $field_id = $this->get_setting("{$setting_name_root}_field_id");

        if(!empty($field_id)){
            $current_condition_value = $this->get_setting("{$setting_name_root}_value");
            $str .= "<script type='text/javascript'>jQuery(document).ready(function(){jQuery('#" . esc_attr($setting_name_root) . "_container').html(GetRuleValues('gf_setting', 0, {$field_id}, '" . esc_attr($current_condition_value) . "', '_gaddon_setting_" . esc_attr($setting_name_root) . "_value'));});</script>";
        }
        return $str;
    }

    /**
     * Parses the properties of the $field meta array and returns a set of HTML attributes to be added to the HTML element.
     * @param array $field - current field meta to be parsed.
     * @param array $default - default set of properties. Will be appended to the properties specified in the $field array
     * @return array - resulting HTML attributes ready to be included in the HTML element.
     */
    protected function get_field_attributes($field, $default = array()){

        // each nonstandard property will be extracted from the $props array so it is not auto-output in the field HTML
        $no_output_props = apply_filters( 'gaddon_no_output_field_properties',
                array( 'default_value', 'label', 'choices', 'feedback_callback', 'checked', 'checkbox_label', 'value', 'type',
                    'validation_callback', 'required', 'hidden', 'tooltip', 'dependency' ), $field );

        $default_props = array(
            'class' => '',          // will default to gaddon-setting
            'default_value' => ''  // default value that should be selected or entered for the field
            );

        // Property switch case
        switch( $field['type'] ) {
        case 'select':
            $default_props['choices'] = array();
            break;
        case 'checkbox':
            $default_props['checked'] = false;
            $default_props['checkbox_label'] = '';
            $default_props['choices'] = array();
            break;
        case 'text':
        default:
            break;
        }

        $props = wp_parse_args( $field, $default_props );
        $props['id'] = rgempty("id", $props) ? rgar( $props, 'name' ) : rgar( $props, "id");
        $props['class'] = trim("{$props['class']} gaddon-setting gaddon-{$props['type']}");

        // extract no-output properties from $props array so they are not auto-output in the field HTML
        foreach( $no_output_props as $prop ) {
            if( isset( $props[$prop] ) ) {
                ${$prop} = $props[$prop];
                unset( $props[$prop] );
            }
        }

        //adding default attributes
        foreach($default as $key=>$value){
            if(isset($props[$key]))
                $props[$key] = $value . $props[$key];
            else
                $props[$key] = $value;
        }

        // get an array of property strings, example: name='myFieldName'
        $prop_strings = array();
        foreach( $props as $prop => $value ) {
            $prop_strings[$prop] = "{$prop}='" . esc_attr($value) . "'";
        }

        return $prop_strings;
    }

    /**
     * Parses the properties of the $choice meta array and returns a set of HTML attributes to be added to the HTML element.
     * @param array $choice - current choice meta to be parsed.
     * @param array $field_attributes - current field's attributes.
     * @return array - resulting HTML attributes ready to be included in the HTML element.
     */
    protected function get_choice_attributes($choice, $field_attributes){
        $choice_attributes = $field_attributes;
        foreach( $choice as $prop => $val ) {
            $no_output_choice_attributes = array( 'default_value', 'label', 'checked', 'value', 'type',
                'validation_callback', 'required', 'tooltip' );
            if(in_array($prop, $no_output_choice_attributes))
                unset($choice_attributes[$prop]);
            else
                $choice_attributes[$prop] = "{$prop}='" . esc_attr($val) . "'";
        }
        return $choice_attributes;
    }

    /**
     * Validates settings fields.
     * Validates that all fields are valid. Fields can be invalid when they are blank and marked as required or if it fails a custom validation check.
     * To specify a custom validation, use the 'validation_callback' field meta property and implement the validation function with the custom logic.
     * @param $fields - A list of all fields from the field meta configuration
     * @param $settings - A list of submitted settings values
     * @return bool - Returns true if all fields have passed validation, and false otherwise.
     */
    protected function validate_settings( $fields, $settings ) {

        foreach( $fields as $section ) {

            if( ! $this->setting_dependency_met( rgar( $section, 'dependency' ) ) )
                continue;

            foreach( $section['fields'] as $field ) {

                if( ! $this->setting_dependency_met( rgar( $field, 'dependency' ) ) )
                    continue;

                $field_setting = rgar( $settings, rgar( $field, 'name' ) );

                if( is_callable( rgar( $field, 'validation_callback' ) ) ) {
                    call_user_func( rgar( $field, 'validation_callback' ), $field, $field_setting );
                } else if( rgar( $field, 'required' ) ) {
                    if( rgblank( $field_setting ) )
                        $this->set_field_error( $field, rgar( $field, 'error_message' ) );
                }

            }
        }

        $field_errors = $this->get_field_errors();
        $is_valid = empty( $field_errors );

        return $is_valid;
    }

    /**
     * Sets the validation error message
     * Sets the error message to be displayed when a field fails validation.
     * When implementing a custom validation callback function, use this function to specify the error message to be displayed.
     *
     * @param array $field - The current field meta
     * @param string $error_message - The error message to be displayed
     */
    protected function set_field_error( $field, $error_message = '' ) {

        // set default error message if none passed
        if( !$error_message )
            $error_message = __( 'This field is required.', 'gravityforms' );

        $this->_setting_field_errors[$field['name']] = $error_message;
    }

    /**
     * Gets the validation errors for a field.
     * Returns validation errors associated with the specified field or a list of all validation messages (if a field isn't specified)
     * @param array|boolean $field - Optional. The field meta. When specified, errors for this field will be returned
     * @return mixed - If a field is specified, a string containing the error message will be returned. Otherwise, an array of all errors will be returned
     */
    protected function get_field_errors( $field = false ) {

        if( !$field )
            return $this->_setting_field_errors;

        return isset( $this->_setting_field_errors[$field['name']] ) ? $this->_setting_field_errors[$field['name']] : array();
    }

    /**
     * Gets the invalid field icon
     * Returns the markup for an alert icon to indicate and highlight invalid fields.
     * @param array $field - The field meta.
     * @return string - The full markup for the icon
     */
    protected function get_error_icon( $field ) {

        $error = $this->get_field_errors( $field );

        return '<span
            class="gf_tooltip tooltip"
            title="<h6>' . __( 'Validation Error', 'gravityforms' ) . '</h6>' . $error . '"
            style="display:inline-block;position:relative;right:-3px;top:1px;font-size:14px;">
                <i class="fa fa-exclamation-circle icon-exclamation-sign gf_invalid"></i>
            </span>';
    }

    /**
     * Gets the required indicator
     * Gets the markup of the required indicator symbol to highlight fields that are required
     * @param $field - The field meta.
     * @return string - Returns markup of the required indicator symbol
     */
    protected function get_required_indicator( $field ) {
        return '<span class="required">*</span>';
    }

    /**
     * Checks if the specified field failed validation
     *
     * @param $field - The field meta to be checked
     * @return bool|mixed - Returns a validation error string if the field has failed validation. Otherwise returns false
     */
    protected function field_failed_validation( $field ) {
        $field_error = $this->get_field_errors( $field );
        return !empty( $field_error ) ? $field_error : false;
    }

    protected function add_field_before( $name, $fields, $settings ) {
        return $this->add_field( $name, $fields, $settings, 'before' );
    }

    protected function add_field_after( $name, $fields, $settings ) {
        return $this->add_field( $name, $fields, $settings, 'after' );
    }

    protected function add_field( $name, $fields, $settings, $pos ) {

        if( rgar( $fields, 'name' ) )
            $fields = array( $fields );

        $pos_mod = $pos == 'before' ? 0 : 1;

        foreach( $settings as &$section ) {
            for( $i = 0; $i < count( $section['fields'] ); $i++ ) {
                if( $section['fields'][$i]['name'] == $name ) {
                    array_splice( $section['fields'], $i + $pos_mod, 0, $fields );
                    break 2;
                }
            }
        }

        return $settings;
    }

    protected function remove_field( $name, $settings ) {

        foreach( $settings as &$section ) {
            for( $i = 0; $i < count( $section['fields'] ); $i++ ) {
                if( $section['fields'][$i]['name'] == $name ) {
                    array_splice( $section['fields'], $i, 1 );
                    break 2;
                }
            }
        }

        return $settings;
    }

    //--------------  Form settings  ---------------------------------------------------

    /**
     * Initializes form settings page
     * Hooks up the required scripts and actions for the Form Settings page
     */
    protected function form_settings_init() {
        $view    = rgget("view");
        $subview = rgget("subview");
        add_action('gform_form_settings_menu', array($this, 'add_form_settings_menu'), 10, 2);
        if (rgget("page") == "gf_edit_forms" && $view == "settings" && $subview == $this->_slug && $this->current_user_can_any($this->_capabilities_form_settings)) {
            require_once(GFCommon::get_base_path() . "/tooltips.php");
            add_action("gform_form_settings_page_" . $this->_slug, array($this, 'form_settings_page'));
        }
    }

    /**
     * Initializes plugin settings page
     * Hooks up the required scripts and actions for the Plugin Settings page
     */
    protected function plugin_page_init(){

        if($this->current_user_can_any($this->_capabilities_plugin_page)){
            //creates the subnav left menu
            add_filter("gform_addon_navigation", array($this, 'create_plugin_page_menu'));
        }

    }

    /**
     * Creates plugin page menu item
     * Target of gform_addon_navigation filter. Creates a menu item in the left nav, linking to the plugin page
     *
     * @param $menus - Current list of menu items
     * @return array - Returns a new list of menu items
     */
    public function create_plugin_page_menu($menus){

        $menus[] = array("name" => $this->_slug, "label" => $this->get_short_title(), "callback" =>  array($this, "plugin_page_container"), "permission" => $this->_capabilities_plugin_page);

        return $menus;
    }

    /**
     * Renders the form settings page.
     *
     * Not intended to be overridden or called directly by Add-Ons.
     * Sets up the form settings page.
     *
     * @ignore
     */
    public function form_settings_page() {

        GFFormSettings::page_header($this->_title);
        ?>
        <div class="gform_panel gform_panel_form_settings" id="form_settings">

            <form id="gform-settings" action="" method="post">

                <?php
                $form = $this->get_current_form();

                $form_id = $form["id"];
                $form = apply_filters("gform_admin_pre_render_{$form_id}", apply_filters("gform_admin_pre_render", $form));

                if($this->method_is_overridden('form_settings')){

                    //enables plugins to override settings page by implementing a form_settings() function
                    $this->form_settings($form);
                } else {

                    //saves form settings if save button was pressed
                    $this->maybe_save_form_settings($form);

                    //reads current form settings
                    $settings = $this->get_form_settings($form);
                    $this->set_settings( $settings );

                    //reading addon fields
                    $sections = $this->form_settings_fields($form);

                    GFCommon::display_admin_message();

                    //rendering settings based on fields and current settings
                    $this->render_settings( $sections );
                }
                ?>

            </form>
            <script type="text/javascript">
                var form = <?php echo json_encode($this->get_current_form()) ?>;
            </script>
        </div>
        <?php
        GFFormSettings::page_footer();
    }

    /***
    * Saves form settings if the submit button was pressed
    * @param array $form The form object
    * @return null|true|false True on success, false on error, null on no action
    */
    public function maybe_save_form_settings($form){

        if( $this->is_save_postback() ){

            // store a copy of the previous settings for cases where action whould only happen if value has changed
            $this->set_previous_settings( $this->get_form_settings( $form ) );

            $settings = $this->get_posted_settings();
            $sections = $this->form_settings_fields($form);

            $is_valid = $this->validate_settings( $sections, $settings );
            $result = false;

            if( $is_valid )
                $result = $this->save_form_settings($form, $settings);

            if( $result ) {
                GFCommon::add_message( $this->get_save_success_message($sections) );
            } else {
                GFCommon::add_error_message( $this->get_save_error_message($sections) );
            }

            return $result;
        }

    }

    /***
    * Saves form settings to form object
    *
    * @param array $form
    * @param array $settings
    * @return true|false True on success or false on error
    */
    public function save_form_settings($form, $settings){
        $form[$this->_slug] = $settings;
        $result = GFFormsModel::update_form_meta($form["id"], $form);
        return ! (false === $result);
    }

    /**
     * Checks whether the current Add-On has a form settings page.
     *
     * @return bool
     */
    private function has_form_settings_page() {
        return $this->method_is_overridden('form_settings_fields') || $this->method_is_overridden('form_settings');
    }

    /**
     * Custom form settings page
     * Override this function to implement a complete custom form settings page.
     * Before overriding this function, consider using the form_settings_fields() and specifying your field meta.
     */
    protected function form_settings($form){}

    /**
     * Checks whether the current Add-On has a plugin page.
     *
     * @return bool
     */
    private function has_plugin_page() {
        return $this->method_is_overridden('plugin_page');
    }

    /**
     * Override this function to create a custom plugin page
     */
    protected function plugin_page(){
    }

    /**
     * Override this function to customize the plugin page icon
     */
    protected function plugin_page_icon(){
        return "";
    }

    protected function plugin_page_title(){
        return $this->_title;
    }

    /**
     * Plugin page container
     * Target of the plugin menu left nav icon. Displays the outer plugin page markup and calls plugin_page() to render the actual page.
     * Override plugin_page() in order to provide a custom plugin page
     */
    public function plugin_page_container(){
        ?>
        <div class="wrap">
            <?php
            $icon = $this->plugin_page_icon();
            if(!empty($icon)){
                ?>
                <img alt="<?php echo $this->get_short_title() ?>" style="margin: 15px 7px 0pt 0pt; float: left;" src="<?php echo $icon ?>"/>
                <?php
            }
            ?>

            <h2 class="gf_admin_page_title"><?php echo $this->plugin_page_title() ?></h2>
            <?php

            $this->plugin_page();
        ?>
        </div>
        <?php
    }


    /**
     * Returns the form settings for the Add-On
     *
     * @param $form
     * @return string
     */
    protected function get_form_settings($form) {
        return rgar( $form, $this->_slug );
    }

    /**
     * Add the form settings tab.
     *
     * Override this function to add the tab conditionally.
     *
     *
     * @param $tabs
     * @param $form_id
     * @return array
     */
    public function add_form_settings_menu($tabs, $form_id) {

        $tabs[] = array("name" => $this->_slug, "label" => $this->get_short_title(), "query" => array("fid" => null));

        return $tabs;
    }

    /**
    * Override this function to specify the settings fields to be rendered on the form settings page
    */
    protected function form_settings_fields($form){
        // should return an array of sections, each section contains a title, description and an array of fields
        return array();
    }

    //--------------  Plugin Settings  ---------------------------------------------------

    protected function plugin_settings_init() {
        $subview = rgget("subview");
        RGForms::add_settings_page( array(
            'name' => $this->get_short_title(),
            'tab_label' => $this->get_short_title(),
            'handler' => array($this, 'plugin_settings_page')
            ) );
        if (rgget("page") == "gf_settings" && $subview == $this->get_short_title() && $this->current_user_can_any($this->_capabilities_settings_page)) {
            require_once(GFCommon::get_base_path() . "/tooltips.php");
        }
    }

    /**
    * Plugin settings page
    */
    public function plugin_settings_page() {
        ?>

        <h3><span><?php echo $this->plugin_settings_title() ?></span></h3>

        <?php

        if($this->method_is_overridden('plugin_settings')){
            //enables plugins to override settings page by implementing a plugin_settings() function
            $this->plugin_settings();
        }
        else if($this->maybe_uninstall()){
            ?>
            <div class="push-alert-gold" style="border-left: 1px solid #E6DB55; border-right: 1px solid #E6DB55;">
                <?php _e(sprintf("%s has been successfully uninstalled. It can be re-activated from the %splugins page%s.", $this->_title, "<a href='plugins.php'>", "</a>"), "gravityforms")?>
            </div>
            <?php
        }
        else {
            //saves settings page if save button was pressed
            $this->maybe_save_plugin_settings();

            //reads main addon settings
            $settings = $this->get_plugin_settings();
            $this->set_settings( $settings );

            //reading addon fields
            $sections = $this->plugin_settings_fields();

            GFCommon::display_admin_message();

            //rendering settings based on fields and current settings
            $this->render_settings( $sections, $settings);

            //renders uninstall section
            $this->render_uninstall();

        }

    }

    public function plugin_settings_title(){
        return $this->get_short_title() . ' ' . __( "Settings", "gravityforms" );
    }

    /**
     * Override this function to add a custom settings page.
     */
    protected function plugin_settings(){
    }


    /**
     * Checks whether the current Add-On has a settings page.
     *
     * @return bool
     */
    public function has_plugin_settings_page() {
        return $this->method_is_overridden('plugin_settings_fields') || $this->method_is_overridden('plugin_settings');
    }

    /**
    * Returns the currently saved plugin settings
    * @return mixed
    */
    protected function get_plugin_settings() {
        return get_option("gravityformsaddon_" . $this->_slug . "_settings");
    }

    /**
     * Get plugin setting
     * Returns the plugin setting specified by the $setting_name parameter
     * @param string $setting_name - Plugin setting to be returned
     * @return mixed  - Returns the specified plugin setting or null if the setting doesn't exist
     */
    protected function get_plugin_setting($setting_name){
        $settings = $this->get_plugin_settings();
        return isset($settings[$setting_name]) ? $settings[$setting_name] : null;
    }

    /**
    * Updates plugin settings with the provided settings
    *
    * @param array $settings - Plugin settings to be saved
    */
    protected function update_plugin_settings($settings){
        update_option("gravityformsaddon_" . $this->_slug . "_settings", $settings);
    }

    /**
    * Saves the plugin settings if the submit button was pressed
    *
    */
    protected function maybe_save_plugin_settings(){

        if( $this->is_save_postback() ) {

            // store a copy of the previous settings for cases where action whould only happen if value has changed
            $this->set_previous_settings( $this->get_plugin_settings() );

            $settings = $this->get_posted_settings();
            $sections = $this->plugin_settings_fields();
            $is_valid = $this->validate_settings( $sections, $settings );

            if( $is_valid )
                $this->update_plugin_settings( $settings );

            GFCommon::add_message( $this->get_save_success_message( $sections ) );
        }

    }

    /**
    * Override this function to specify the settings fields to be rendered on the plugin settings page
    * @return array
    */
    public function plugin_settings_fields(){
        // should return an array of sections, each section contains a title, description and an array of fields
        return array();
    }

    /**
     * Returns an flattened array of field settings for the specified settings type ignoring sections.
     * @param string $settings_type The settings type. e.g. 'plugin'
     * @return array
     */
    protected function settings_fields_only($settings_type = 'plugin') {

        $fields = array();

        if (!is_callable(array($this, "{$settings_type}_settings_fields")))
            return $fields;

        $sections = call_user_func(array($this, "{$settings_type}_settings_fields"));

        foreach ($sections as $section) {
            foreach ($section['fields'] as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    //--------------  Uninstall  ---------------

    /**
     * Override this function to customize the markup for the uninstall section on the plugin settings page
     */
    public function render_uninstall(){

        ?>
        <form action="" method="post">
            <?php wp_nonce_field("uninstall", "gf_addon_uninstall") ?>
            <?php if ($this->current_user_can_any($this->_capabilities_uninstall)) { ?>
                <div class="hr-divider"></div>

                <h3><span><?php _e("Uninstall Add-On", "gravityforms") ?></span></h3>
                <div class="delete-alert"><?php _e("Warning! This operation deletes ALL settings.", "gravityforms") ?>
                    <?php
                    $uninstall_button = '<input type="submit" name="uninstall" value="' . __("Uninstall  Add-On", "gravityforms") . '" class="button" onclick="return confirm(\'' . __("Warning! ALL settings will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "gravityforms") . '\');"/>';
                    echo $uninstall_button;
                    ?>
                </div>
            <?php
            }
            ?>
        </form>
        <?php
    }

    /**
     * Not intended to be overridden or called directly by Add-Ons.
     *
     * @ignore
     */
    public function maybe_uninstall(){
        if (rgpost("uninstall")) {
            check_admin_referer("uninstall", "gf_addon_uninstall");
            return $this->uninstall_addon();
        }

        return false;
    }

    /**
     * Removes all settings and deactivates the Add-On.
     *
     * Not intended to be overridden or called directly by Add-Ons.
     *
     * @ignore
     */
    public function uninstall_addon() {

        if (!$this->current_user_can_any($this->_capabilities_uninstall))
            die(__("You don't have adequate permission to uninstall this addon: " . $this->_title, "gravityforms"));

        $continue = $this->uninstall();
        if (false === $continue)
            return false;

        global $wpdb;
        $lead_meta_table = GFFormsModel::get_lead_meta_table_name();

        $forms        = GFFormsModel::get_forms();
        $all_form_ids = array();

        // remove entry meta
        foreach ($forms as $form) {
            $all_form_ids[] = $form->id;
            $entry_meta = $this->get_entry_meta(array(), $form->id);
            if(is_array($entry_meta)){
                foreach(array_keys($entry_meta) as $meta_key){
                    $sql = $wpdb->prepare("DELETE from $lead_meta_table WHERE meta_key=%s", $meta_key);
                    $wpdb->query($sql);
                }
            }
        }

        //remove form settings
        $form_metas = GFFormsModel::get_form_meta_by_id($all_form_ids);
        require_once(GFCommon::get_base_path() . '/form_detail.php');
        foreach ($form_metas as $form_meta) {
            if (isset($form_meta[$this->_slug])) {
                unset($form_meta[$this->_slug]);
                $form_json = json_encode($form_meta);
                GFFormDetail::save_form_info($form_meta["id"], addslashes($form_json));
            }
        }

        //removing options
        delete_option("gravityformsaddon_" . $this->_slug . "_settings");
        delete_option("gravityformsaddon_" . $this->_slug . "_version");


        //Deactivating plugin
        deactivate_plugins($this->_path);
        update_option('recently_activated', array($this->_path => time()) + (array)get_option('recently_activated'));

        return true;

    }

    /**
     * Called when the user chooses to uninstall the Add-On  - after permissions have been checked and before removing
     * all Add-On settings and Form settings.
     *
     * Override this method to perform additional functions such as dropping database tables.
     *
     *
     * Return false to cancel the uninstall request.
     */
    protected function uninstall() {
        return true;
    }

    //--------------  Enforce minimum GF version  ---------------------------------------------------

    /**
     * Target for the after_plugin_row action hook. Checks whether the current version of Gravity Forms
     * is supported and outputs a message just below the plugin info on the plugins page.
     *
     * Not intended to be overridden or called directly by Add-Ons.
     *
     * @ignore
     */
    public function plugin_row() {
        if (!self::is_gravityforms_supported($this->_min_gravityforms_version)) {
            $message = $this->plugin_message();
            self::display_plugin_message($message, true);
        }
    }

    /**
     * Returns the message that will be displayed if the current version of Gravity Forms is not supported.
     *
     * Override this method to display a custom message.
     */
    public function plugin_message() {
        $message = sprintf(__("Gravity Forms " . $this->_min_gravityforms_version . " is required. Activate it now or %spurchase it today!%s", "gravityforms"), "<a href='http://www.gravityforms.com'>", "</a>");

        return $message;
    }

    /**
     * Formats and outs a message for the plugin row.
     *
     * Not intended to be overridden or called directly by Add-Ons.
     *
     * @ignore
     *
     * @param $message
     * @param bool $is_error
     */
    public static function display_plugin_message($message, $is_error = false) {
        $style = $is_error ? 'style="background-color: #ffebe8;"' : "";
        echo '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" ' . $style . '>' . $message . '</div></td>';
    }

    //--------------- Logging -------------------------------------------------------------

    /**
     * Writes an error message to the Gravity Forms log. Requires the Gravity Forms logging Add-On.
     *
     * Not intended to be overridden by Add-Ons.
     *
     * @ignore
     */
    public function log_error($message){
        if (class_exists("GFLogging")) {
            GFLogging::include_logger();
            GFLogging::log_message($this->_slug, $message, KLogger::ERROR);
        }
    }

    /**
     * Writes an error message to the Gravity Forms log. Requires the Gravity Forms logging Add-On.
     *
     * Not intended to be overridden by Add-Ons.
     *
     * @ignore
     */
    public function log_debug($message){
        if (class_exists("GFLogging")) {
            GFLogging::include_logger();
            GFLogging::log_message($this->_slug, $message, KLogger::DEBUG);
        }
    }

    //--------------- Locking ------------------------------------------------------------

    /**
     * Returns the configuration for locking
     *
     * e.g.
     *
     *  array(
     *     "object_type" => "contact",
     *     "capabilities" => array("gravityforms_contacts_edit_contacts"),
     *     "redirect_url" => admin_url("admin.php?page=gf_contacts"),
     *     "edit_url" => admin_url(sprintf("admin.php?page=gf_contacts&id=%d", $contact_id)),
     *     "strings" => $strings
     *     );
     *
     * Override this method to implement locking
     */
    public function get_locking_config() {
        return array();
    }


    /**
     * Returns TRUE if the current page is the edit page. Otherwise, returns FALSE
     *
     * Override this method to implement locking on the edit page.
     */
    public function is_locking_edit_page() {
        return false;
    }
    /**
     * Returns TRUE if the current page is the list page. Otherwise, returns FALSE
     *
     * Override this method to display locking info on the list page.
     */
    public function is_locking_list_page() {
        return false;
    }
    /**
     * Returns TRUE if the current page is the view page. Otherwise, returns FALSE
     *
     * Override this method to display locking info on the view page.
     */
    public function is_locking_view_page() {
        return false;
    }
    /**
     * Returns the ID of the object to be locked. E.g. Form ID
     *
     * Override this method to implement locking
     */
    public function get_locking_object_id() {
        return 0;
    }

    /**
     * Outputs information about the user currently editing the specified object
     *
     * @param int $object_id The Object ID
     * @param bool $echo Whether to echo
     * @return string The markup for the lock info
     */
    public function lock_info($object_id, $echo = true) {
        $gf_locking = new GFAddonLocking($this->get_locking_config(),$this);
        $lock_info = $gf_locking->lock_info($object_id, false);
        if ($echo)
            echo $lock_info;
        return $lock_info;
    }
    /**
     * Outputs class for the row for the specified Object ID on the list page.
     *
     * @param int $object_id The object ID
     * @param bool $echo Whether to echo
     * @return string The markup for the class
     */
    public function list_row_class($object_id, $echo = true) {
        $gf_locking = new GFAddonLocking($this->get_locking_config(),$this);
        $class = $gf_locking->list_row_class($object_id, false);
        if ($echo)
            echo $class;
        return $class;
    }



    //--------------  Helper functions  ---------------------------------------------------

    protected final function method_is_overridden($method_name, $base_class="GFAddOn"){
        $reflector = new ReflectionMethod($this, $method_name);
        $name = $reflector->getDeclaringClass()->getName();
        return $name !== $base_class;
    }

    /**
     * Returns the url of the root folder of the current Add-On.
     *
     * @param string $full_path Optional. The full path the the plugin file.
     * @return string
     */
    protected function get_base_url($full_path = "") {
        if (empty($full_path))
            $full_path = $this->_full_path;

        return plugins_url(null, $full_path);
    }

    /**
     * Returns the url of the Add-On Framework root folder.
     *
     * @return string
     */
    final public static function get_gfaddon_base_url() {
        return plugins_url(null, __FILE__);
    }

    /**
     * Returns the physical path of the Add-On Framework root folder.
     *
     * @return string
     */
    final public static function get_gfaddon_base_path() {
        return self::_get_base_path();
    }

    /**
     * Returns the physical path of the plugins root folder.
     *
     * @param string $full_path
     * @return string
     */
    protected function get_base_path($full_path = "") {
        if (empty($full_path))
            $full_path = $this->_full_path;
        $folder = basename(dirname($full_path));

        return WP_PLUGIN_DIR . "/" . $folder;
    }

    /**
     * Returns the physical path of the Add-On Framework root folder
     *
     * @return string
     */
    private static function _get_base_path() {
        $folder = basename(dirname(__FILE__));

        return GFCommon::get_base_path() . "/includes/" . $folder;
    }

    /**
     * Returns the URL of the Add-On Framework root folder
     *
     * @return string
     */
    private static function _get_base_url() {
        $folder = basename(dirname(__FILE__));

        return GFCommon::get_base_url() . "/includes/" . $folder;
    }

    /**
     * Checks whether the Gravity Forms is installed.
     *
     * @return bool
     */
    public function is_gravityforms_installed() {
        return class_exists("GFForms");
    }

    public function table_exists($table_name){
        global $wpdb;

        $count = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        return !empty($count);
    }

    /**
     * Checks whether the current version of Gravity Forms is supported
     *
     * @param $min_gravityforms_version
     * @return bool|mixed
     */
    public function is_gravityforms_supported($min_gravityforms_version = "") {
        if(isset($this->_min_gravityforms_version) && empty($min_gravityforms_version))
            $min_gravityforms_version = $this->_min_gravityforms_version;

        if(empty($min_gravityforms_version))
            return true;

        if (class_exists("GFCommon")) {
            $is_correct_version = version_compare(GFCommon::$version, $min_gravityforms_version, ">=");

            return $is_correct_version;
        } else {
            return false;
        }
    }

    /**
    * Returns this plugin's short title. Used to display the plugin title in small areas such as tabs
    */
    protected function get_short_title() {
        return isset( $this->_short_title ) ? $this->_short_title : $this->_title;
    }

    /**
    * Returns the URL for the plugin settings tab associated with this plugin
    *
    */
    protected function get_plugin_settings_url() {
        return add_query_arg( array( 'page' => 'gf_settings', 'subview' => $this->get_short_title() ), admin_url('admin.php') );
    }

    /**
    * When called from a page that supports it (i.e. entry detail, form editor and form settings),
    * returns the current form object. Otherwise returns false
    */
    protected function get_current_form(){

//        if( $this->is_entry_edit() ||
//            $this->is_entry_view() ||
//            $this->is_entry_list() ||
//            $this->is_form_editor() ||
//            $this->is_form_settings() )
//        {

        return rgempty("id", $_GET) ? false : GFFormsModel::get_form_meta(rgget('id'));
    }

    /**
    * Returns TRUE if the current request is a postback, otherwise returns FALSE
    */
    protected function is_postback(){
        return is_array($_POST) && count($_POST) > 0;
    }

    /**
    * Returns TRUE if the settings "Save" button was pressed
    */
    protected function is_save_postback(){
        return !rgempty("gform-settings-save");
    }

    /**
    * Returns TRUE if the current page is the form editor page. Otherwise, returns FALSE
    */
    protected function is_form_editor(){

        if(rgget("page") == "gf_edit_forms" && !rgempty("id", $_GET) && rgempty("view", $_GET))
            return true;

        return false;
    }

    /**
    * Returns TRUE if the current page is the form settings page, or a specific form settings tab (specified by the $tab parameter). Otherwise returns FALSE
    *
    * @param string $tab - Specifies a specific form setting page/tab
    * @return bool
    */
    protected function is_form_settings($tab = null){

        $is_form_settings = rgget("page") == "gf_edit_forms" && rgget("view") == "settings";
        $is_tab = $this->_tab_matches($tab);

        if($is_form_settings && $is_tab){
            return true;
        }
        else{
            return false;
        }
    }

    private function _tab_matches($tabs){
        if($tabs == null)
            return true;

        if(!is_array($tabs))
            $tabs = array($tabs);

        $current_tab = rgempty("subview", $_GET) ? "settings" : rgget("subview");

        foreach($tabs as $tab){
            if( strtolower( $tab ) == strtolower( $current_tab ) )
                return true;
        }
    }

    /**
    * Returns TRUE if the current page is the plugin settings main page, or a specific plugin settings tab (specified by the $tab parameter). Otherwise returns FALSE
    *
    * @param string $tab - Specifies a specific plugin setting page/tab.
    * @return bool
    */
    protected function is_plugin_settings($tab = ""){

        $is_plugin_settings = rgget("page") == "gf_settings";
        $is_tab = $this->_tab_matches($tab);

        if($is_plugin_settings && $is_tab){
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * Returns TRUE if the current page is the plugin page. Otherwise returns FALSE
     * @return bool
     */
    protected function is_plugin_page(){

        return strtolower(rgget("page")) == strtolower($this->_slug);
    }

    /**
    * Returns TRUE if the current page is the entry view page. Otherwise, returns FALSE
    * @return bool
    */
    protected function is_entry_view(){
        if(rgget("page") == "gf_entries" && rgget("view") == "entry" && (!isset($_POST["screen_mode"]) || rgpost("screen_mode") == "view"))
            return true;

        return false;
    }

    /**
    * Returns TRUE if the current page is the entry edit page. Otherwise, returns FALSE
    * @return bool
    */
    protected function is_entry_edit(){
        if(rgget("page") == "gf_entries" && rgget("view") == "entry" && rgpost("screen_mode") == "edit")
            return true;

        return false;
    }

    protected function is_entry_list(){
        if( rgget("page") == "gf_entries" && rgempty("view", $_GET) )
            return true;

        return false;
    }

    /**
     * Returns TRUE if the current page is the results page. Otherwise, returns FALSE
     */
    protected function is_results(){
        if(rgget("page") == "gf_entries" && rgget("view") == "gf_results_" . $this->_slug)
            return true;

        return false;
    }

    /**
     * Returns TRUE if the current page is the print page. Otherwise, returns FALSE
     */
    protected function is_print(){
        if(rgget("gf_page") == "print-entry")
            return true;

        return false;
    }

    /**
     * Returns TRUE if the current page is the preview page. Otherwise, returns FALSE
     */
    protected function is_preview(){
        if(rgget("gf_page") == "preview")
            return true;

        return false;
    }

}
