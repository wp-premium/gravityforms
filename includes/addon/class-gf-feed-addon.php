<?php
/**
 * Specialist Add-On class designed for use by Add-Ons that require form feed settings
 * on the form settings tab.
 *
 * @package GFFeedAddOn
 */

require_once('class-gf-addon.php' );
abstract class GFFeedAddOn extends GFAddOn {
    /**
     * @var string Version number of the Add-On Framework
     */
    private $_feed_version = "0.11";
    
    private $_feed_settings_fields = array();
    
    public function init() {
        
        parent::init();
        
        add_action( 'gform_after_submission', array( $this, 'maybe_process_feed' ), 10, 2 );
        
    }
    
    protected function setup(){
        parent::setup();

        //upgrading Feed Add-On base class
        $installed_version = get_option("gravityformsaddon_feed-base_version");
        if ($installed_version != $this->_feed_version)
            $this->upgrade_base($installed_version);

        update_option("gravityformsaddon_feed-base_version", $this->_feed_version);
    }

    private function upgrade_base($previous_version) {
        global $wpdb;

        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
        if ( ! empty($wpdb->charset) )
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if ( ! empty($wpdb->collate) )
            $charset_collate .= " COLLATE $wpdb->collate";

        $sql = "CREATE TABLE {$wpdb->prefix}gf_addon_feed (
                  id mediumint(8) unsigned not null auto_increment,
                  form_id mediumint(8) unsigned not null,
                  is_active tinyint(1) not null default 1,
                  meta longtext,
                  addon_slug varchar(50),
                  PRIMARY KEY  (id),
                  KEY addon_form (addon_slug,form_id)
                ) $charset_collate;";

        //Fixes issue with dbDelta lower-casing table names, which cause problems on case sensitive DB servers.
        add_filter( 'dbdelta_create_queries', array("RGForms", "dbdelta_fix_case"));

        dbDelta($sql);

        remove_filter('dbdelta_create_queries', array("RGForms", "dbdelta_fix_case"));
    }

    public function scripts() {

        $scripts = array(
            array(
                'handle' => 'gform_form_admin',
                'enqueue' => array( array( "admin_page" => array("form_settings") ) )
                ),
            array(
                'handle' => 'gform_gravityforms',
                'enqueue' => array( array( "admin_page" => array("form_settings") ) )
                ),
            array(
                'handle' => 'gform_forms',
                'enqueue' => array( array( "admin_page" => array("form_settings") ) )
                ),
            array(
                'handle' => 'json2',
                'enqueue' => array( array( "admin_page" => array("form_settings") ) )
                ),
            array(
                'handle' => 'gform_placeholder',
                'enqueue' => array(
                    array(
                        "admin_page" => array("form_settings"),
                        "field_types" => array("feed_condition")
                        )
                    )
                )
            );

        return array_merge( parent::scripts(), $scripts );
    }

    public function uninstall(){
        global $wpdb;
        $sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}gf_addon_feed WHERE addon_slug=%s", $this->_slug);
        $wpdb->query($sql);

    }

    //-------- Front-end methods ---------------------------
    
    public function maybe_process_feed( $entry, $form, $is_delayed = false ) {
        
        // getting all active feeds
        $feeds = $this->get_feeds( $form['id'] );

        $paypal_feed = $this->get_paypal_feed( $form['id'], $entry );
        if( $paypal_feed && rgar( $paypal_feed['meta'], "delay_{$this->_slug}" ) && $is_delayed ) {
            self::log_debug( "Feed processing delayed pending PayPal payment received for entry {$entry['id']}" );
            return;
        }

        foreach ( $feeds as $feed ) {
            if ( $this->is_feed_condition_met( $feed, $form, $entry ) ) {
                $this->process_feed( $feed, $entry, $form );
            } else {
                self::log_debug( "Opt-in condition not met; not fulfilling entry {$entry["id"]} to list" );
            }
        }
        
    }
    
    public function process_feed( $feed, $entry, $form ) { 
        
        return;
    }
    
    public function is_feed_condition_met( $feed, $form, $entry ) {
        
        $feed_meta = $feed['meta'];
        $is_condition_enabled = rgar( $feed_meta, 'feed_condition_conditional_logic' ) == true;
        $logic = rgars( $feed_meta, 'feed_condition_conditional_logic_object/conditionalLogic' );
        
        if( !$is_condition_enabled || empty( $logic ) )
            return true;

        return GFCommon::evaluate_conditional_logic( $logic, $form, $entry );
    }
    
    public function get_paypal_feed( $form_id, $entry ) {
        
        if( !class_exists( 'GFPayPal' ) )
            return false;
        
        if( method_exists( 'GFPayPal', 'get_config_by_entry' ) ) {
            $feed = GFPayPal::get_config_by_entry( $entry );
        } else {
            $feed = GFPayPal::get_config( $form_id );
        }
        
        return $feed;
    }
    

    //--------  Feed data methods  -------------------------

    public function get_feeds($form_id = null){
        global $wpdb;

        $form_filter = is_numeric($form_id) ? $wpdb->prepare("AND form_id=%d", absint($form_id)) : "";

        $sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gf_addon_feed
                               WHERE addon_slug=%s {$form_filter}", $this->_slug);

        $results = $wpdb->get_results($sql, ARRAY_A);
        foreach($results as &$result){
            $result["meta"] = json_decode($result["meta"], true);
        }
        return $results;
    }

    public function get_current_feed(){
        $feed_id = $this->get_current_feed_id();
        return empty($feed_id) ? false : $this->get_feed( $feed_id );
    }

    public function get_current_feed_id(){
        $settings = $this->get_posted_settings();
        return rgempty('gf_feed_id') ? rgget("fid") : rgpost('gf_feed_id');
    }

    public function get_feed( $id ) {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gf_addon_feed WHERE id=%d", $id);

        $row = $wpdb->get_row($sql, ARRAY_A);
        $row["meta"] = json_decode($row["meta"], true);
        
        return $row;
    }

    public function update_feed_meta($id, $meta){
        global $wpdb;

        $meta = json_encode($meta);
        $wpdb->update("{$wpdb->prefix}gf_addon_feed", array( 'meta' => $meta ), array( 'id' => $id ), array('%s'), array('%d') );

        return $wpdb->rows_affected > 0;
    }

    public function update_feed_active($id, $is_active){
        global $wpdb;
        $is_active = $is_active ? "1" : "0";

        $wpdb->update("{$wpdb->prefix}gf_addon_feed", array('is_active'=>$is_active), array("id" => $id), array('%d'), array('%d'));

        return $wpdb->rows_affected > 0;
    }

    public function insert_feed($form_id, $is_active, $meta){
        global $wpdb;

        $meta = json_encode($meta);
        $wpdb->insert("{$wpdb->prefix}gf_addon_feed", array("addon_slug" => $this->_slug, "form_id"=> $form_id, "is_active"=>$is_active, 'meta'=>$meta), array('%s', '%d', '%d', '%s'));

        return $wpdb->insert_id;
    }

    public function delete_feed($id){
        global $wpdb;

        $wpdb->delete("{$wpdb->prefix}gf_addon_feed", array("id" => $id), array('%d'));
    }

    public function delete_feeds($form_id = null){
        global $wpdb;

        $where = is_numeric($form_id) ? array("addon_slug" => $this->_slug, "form_id" => $form_id) : array("addon_slug" => $this->_slug);
        $format = is_numeric($form_id) ? array('%s','%d') : array('%s');

        $wpdb->delete("{$wpdb->prefix}gf_addon_feed", $where, $format);
    }


    //---------- Form Settings Pages --------------------------

    public function form_settings_init(){
        parent::form_settings_init();

        if (RG_CURRENT_PAGE == "admin-ajax.php") {
            add_action("wp_ajax_gf_feed_is_active_{$this->_slug}", array($this, 'ajax_toggle_is_active'));
        }
    }

    public function ajax_toggle_is_active(){
        $feed_id = rgpost("feed_id");
        $is_active = rgpost("is_active");

        $this->update_feed_active($feed_id, $is_active);
        die();
    }
    public function form_settings_sections() {
        return array();
    }

    public function form_settings($form) {
        if( $this->is_detail_page() ) {

            // feed edit page
            $feed_id = $this->get_current_feed_id();

            $this->feed_edit_page($form, $feed_id);
        }
        else {
            // feed list UI
            $this->feed_list_page($form);
        }
    }

    public function is_list_page(){
        return !isset($_GET["fid"]);
    }

    public function is_detail_page(){
        return !$this->is_list_page();
    }

    public function form_settings_header(){
        if($this->is_list_page()){
            $title = $this->form_settings_title();
            return $title . " <a class='add-new-h2' href='" . add_query_arg(array("fid" => 0)) . "'>" . __("Add New", "gravityforms") . "</a>";
        }
    }

    public function form_settings_title(){
        return $this->_title . " " . __("Feeds", "gravityforms");
    }

    protected function feed_edit_page($form, $feed_id) {

        // Save feed if appropriate
        $feed_id = $this->maybe_save_feed_settings( $feed_id, $form['id'] );

        ?>
        <input type="hidden" name="gf_feed_id" value="<?php echo $feed_id ?>"/>
        <?php

        $feed = $this->get_feed( $feed_id );
        $this->set_settings( $feed['meta'] );
        
        GFCommon::display_admin_message();
        
        $this->render_settings( $this->get_feed_settings_fields() );
    }

    public function feed_list_page($form=null){
        $action = $this->get_bulk_action();
        if($action){
            check_admin_referer("feed_list", "feed_list");
            $this->process_bulk_action($action);
        }

        $single_action = rgpost("single_action");
        if(!empty($single_action)){
            check_admin_referer("feed_list", "feed_list");
            $this->process_single_action($single_action);
        }

        $columns               = $this->feed_list_columns();
        $column_value_callback = array($this, "get_column_value");
        $feeds = $this->get_feeds(rgar($form,"id"));
        $bulk_actions = $this->get_bulk_actions();
        $action_links = $this->get_action_links();

        ?>
        <h3><span><?php echo $this->feed_list_title() ?></span></h3>
        <?php

        $feed_list = new GFAddOnFeedsTable($feeds, $this->_slug, $columns, $bulk_actions, $action_links, $column_value_callback);
        $feed_list->prepare_items();
        $feed_list->display();

        ?>
        <!--Needed to save state after bulk operations-->
        <input type="hidden" value="gf_edit_forms" name="page">
        <input type="hidden" value="settings" name="view">
        <input type="hidden" value="<?php echo $this->_slug; ?>" name="subview">
        <input type="hidden" value="<?php echo rgar($form, "id"); ?>" name="id">
        <input id="single_action" type="hidden" value="" name="single_action">
        <input id="single_action_argument" type="hidden" value="" name="single_action_argument">
        <?php wp_nonce_field("feed_list", "feed_list") ?>

        <script type="text/javascript">
            <?php GFCommon::gf_vars() ?>
        </script>
        <?php
    }

    public function feed_list_title(){
        $url = add_query_arg(array("fid" => "0"));
        return $this->_short_title . " " . __("Feeds", "gravityforms") . " <a class='add-new-h2' href='{$url}'>" . __("Add New", "gravityforms") . "</a>";
    }

    protected function maybe_save_feed_settings( $feed_id, $form_id ){

        if( !rgpost( 'gform-settings-save' ) )
            return $feed_id;
        
        $settings = $this->get_posted_settings();
        $is_valid = $this->validate_settings( $this->get_feed_settings_fields(), $settings );
        $result = false;
        
        if( $is_valid )
            $result = $this->save_feed_settings( $feed_id, $form_id, $settings );
        
        if( $result ) {
            GFCommon::add_message( __('Feed updated successfully.', 'gravityforms') );
        } else {
            GFCommon::add_error_message( __('There was an error updating this feed. Please review all errors below and try again.', 'gravityforms') );
        }
        
        // if no $feed_id is passed, assume that a new feed was created and return new $feed_id
        if( !$feed_id )
            $feed_id = $result;
        
        return $feed_id;
    }
    
    protected function save_feed_settings( $feed_id, $form_id, $settings ) {
        
        if( $feed_id ) {
            $this->update_feed_meta( $feed_id, $settings );
            $result = true;
        } else {
            $result = $this->insert_feed( $form_id, true, $settings );
        }
        
        return $result;
    }
    
    public function get_feed_settings_values(){

        $sections = $this->get_feed_settings_fields();
        $settings = $this->get_settings_values( $sections );

        return $settings;
    }

    public function get_feed_settings_fields() {
        
        if( !empty( $this->_feed_settings_fields ) )
            return $this->_feed_settings_fields;
        
        $this->_feed_settings_fields = $this->add_default_feed_settings_fields_props( $this->feed_settings_fields() );
        
        return $this->_feed_settings_fields;
    }
    
    public function add_default_feed_settings_fields_props( $fields ) {
        
        foreach( $fields as &$section ) {
            foreach( $section['fields'] as &$field ) {
                switch( $field['type'] ) {
                case 'field_map':
                    if( !rgar( $field, 'validation_callback' ) )
                        $field['validation_callback'] = array( $this, 'validate_feed_map_settings' );
                    break;
                case 'hidden':
                    $field['hidden'] = true;
                    break;
                }
            }
        }
        
        return $fields;
    }

    private function get_bulk_action(){
        $action = rgpost("action");
        if(empty($action) || $action == "-1")
            $action = rgpost("action2");

        return empty($action) || $action == "-1" ? false : $action;
    }

    /***
    * Override this function to add custom bulk actions
    */
    protected function get_bulk_actions(){
        $bulk_actions = array('delete' => __('Delete', 'gravityforms'));
        return $bulk_actions;
    }

    /***
    * Override this function to process custom bulk actions added via the get_bulk_actions() function
    *
    * @param string $action: The bulk action selected by the user
    */
    protected function process_bulk_action($action){
        if($action == "delete"){
            $feeds = rgpost("feed_ids");
            if(is_array($feeds)){
                foreach($feeds as $feed_id){
                    $this->delete_feed($feed_id);
                }
            }
        }
    }

    protected function process_single_action($action){
        if($action == "delete"){
            $feed_id = absint(rgpost("single_action_argument"));
            $this->delete_feed($feed_id);
        }
    }

    protected function get_action_links(){
        $feed_id  = "{id}";
        $edit_url = add_query_arg(array("fid" => $feed_id));
        $links = array(
            'edit'   => '<a title="' . __('Edit this feed', 'gravityforms') . '" href="' . $edit_url . '">' . __('Edit', 'gravityforms') . '</a>',
            'delete' => '<a title="' . __('Delete this feed', 'gravityforms') . '" class="submitdelete" onclick="javascript: if(confirm(\'' . __("WARNING: You are about to delete this item.", "gravityforms") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravityforms") . '\')){ gaddon.deleteFeed(\'' . $feed_id . '\'); }" style="cursor:pointer;">' . __('Delete', 'gravityforms') . '</a>'
        );

        return $links;
    }

    protected function feed_list_columns() {
        return array();
    }

    public function get_column_value($item, $column) {
        if(is_callable(array($this, "get_column_value_{$column}"))){
            return call_user_func(array($this, "get_column_value_{$column}"), $item);
        }
        else if(isset($item[$column])){
            return $item[$column];
        }
        else if(isset($item["meta"][$column])){
            return $item["meta"][$column];
        }
    }




    protected function update_form_settings($form, $new_form_settings) {
        $feed_id = rgar($new_form_settings, "id");
        foreach ($new_form_settings as $key => $value) {
            $form[$this->_slug]["feeds"][$feed_id][$key] = $value;
        }

        return $form;
    }

    protected function get_form_settings($form) {
        if ($this->is_detail_page()) {
            $feed_id = rgget("fid");

            $add_on_form_settings = rgar($form, $this->_slug);
            if (is_array($add_on_form_settings) && is_array(rgar($add_on_form_settings, "feeds")))
                return rgar($add_on_form_settings["feeds"], $feed_id);
            else
                return array();
        }

        return parent::get_form_settings($form);
    }

    protected function settings_feed_condition( $field, $echo = true ) {

        $checkbox_field = array(
            'name' => 'feed_condition_conditional_logic',
            'type' => 'checkbox',
            'choices' => array(
                array(
                    'label' => __('Enable Opt-In Condition', 'gravityforms'),
                    'name' => 'feed_condition_conditional_logic'
                    )
                ),
            'onclick' => 'ToggleConditionalLogic( false, "feed_condition" );'
            );

        $conditional_logic = $this->get_setting( 'feed_condition_conditional_logic_object' ) ? json_encode( $this->get_setting( 'feed_condition_conditional_logic_object' ) ) : '{}';
        $hidden_field = array(
            'name' => 'feed_condition_conditional_logic_object',
            'value' => $conditional_logic
            );

        $html = $this->settings_checkbox( $checkbox_field, '', false );
        $html .= $this->settings_hidden( $hidden_field, '', false );
        $html .= '<div id="feed_condition_conditional_logic_container"><!-- dynamically populated --></div>';
        $html .= '<script type="text/javascript"> var feedCondition = new FeedConditionObj({' .
            'strings: { objectDescription: "' . __('Export to MailChimp if', 'gravityforms') . '" },' .
            'logicObject: ' . $conditional_logic .
            '}); </script>';

        if( $echo )
            echo $html;

        return $html;
    }

    public function settings_field_map( $field, $echo = true ) {

        $html = '';
        $field_map = rgar( $field, 'field_map' );
        
        if( empty( $field_map ) )
            return $html;

        $form_id = rgget( 'id' );

        $html .= '
            <table class="settings-field-map-table" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Form Field</th>
                    </tr>
                </thead>
                <tbody>';

        foreach( $field['field_map'] as $child_field ) {

            $child_field['name'] = $this->get_mapped_field_name( $field, $child_field['name'] );
            $required = rgar( $child_field, 'required' ) ? $this->get_required_indicator( $child_field ) : '';

            $html .= '
                <tr>
                    <td>
                        <label for="' . $child_field['name'] . '">' . $child_field['label'] . ' ' . $required . '<label>
                    </td>
                    <td>' .
                        $this->settings_field_map_select( $child_field, $form_id ) .
                    '</td>
                </tr>';
        }

        $html .= '
                </tbody>
            </table>';

        if( $echo )
            echo $html;

        return $html;
    }
    
    public function settings_field_map_select( $field, $form_id ) {

        $field['choices'] = self::get_field_map_choices( $form_id );

        return $this->settings_select( $field, false );
    }

    public static function get_field_map_choices( $form_id ) {

        $form = RGFormsModel::get_form_meta($form_id);
        $fields = array();

        // Adding default fields
        $fields[] = array( "value" => "", "label" => "" );
        $fields[] = array( "value" => "date_created" , "label" => __("Entry Date", "gravityforms") );
        $fields[] = array( "value" => "ip" , "label" => __("User IP", "gravityformsmailchimp") );
        $fields[] = array( "value" => "source_url" , "label" => __("Source Url", "gravityforms") );
        $fields[] = array( "value" => "form_title" , "label" => __("Form Title", "gravityforms") );

        // Populate entry meta
        $entry_meta = GFFormsModel::get_entry_meta( $form["id"] );
        foreach( $entry_meta as $meta_key => $meta ) {
            $fields[] = array( 'value' => $meta_key , 'label' => rgars($entry_meta, "{$meta_key}/label") );
        }

        // Populate form fields
        if( is_array( $form["fields"] ) ) {
            foreach( $form["fields"] as $field ) {
                if( is_array( rgar( $field, "inputs") ) ) {

                    //If this is an address field, add full name to the list
                    if(RGFormsModel::get_input_type($field) == "address")
                        $fields[] =  array( 'value' => $field["id"], 'label' => GFCommon::get_label($field) . " (" . __("Full" , "gravityformsmailchimp") . ")" );

                    //If this is a name field, add full name to the list
                    if(RGFormsModel::get_input_type($field) == "name")
                        $fields[] =  array( 'value' => $field["id"], 'label' => GFCommon::get_label($field) . " (" . __("Full" , "gravityformsmailchimp") . ")" );

                    foreach($field["inputs"] as $input)
                        $fields[] =  array( 'value' => $input["id"], 'label' => GFCommon::get_label($field, $input["id"]) );
                }
                else if(!rgar($field,"displayOnly")){
                    $fields[] =  array( 'value' => $field["id"], 'label' => GFCommon::get_label($field));
                }
            }
        }

        return $fields;
    }
    
    public function get_mapped_field_name( $parent_field, $field_name ) {
        return "field_map_{$parent_field['name']}_{$field_name}";
    }

    public function validate_feed_map_settings( $field ) {
        
        $settings = $this->get_posted_settings();
        $field_map = rgar( $field, 'field_map' );
        
        if( empty( $field_map ) )
            return;
        
        foreach( $field_map as $child_field ) {
            
            $child_field['name'] = $this->get_mapped_field_name( $field, $child_field['name'] );
            $setting_value = rgar( $settings, $child_field['name'] );
            
            if( rgar( $child_field, 'required' ) && rgblank( $setting_value ) )
                $this->set_field_error( $child_field );
                
        }
        
    }
    
    public static function get_field_map_fields( $feed, $field_name ) {
        
        $fields = array();
        $prefix = "field_map_{$field_name}_";
        
        foreach( $feed['meta'] as $name => $value ) {
            if( strpos( $name, $prefix ) === 0 ) {
                $name = str_replace( $prefix, '', $name );
                $fields[$name] = $value;
            }
        }
        
        return $fields;
    }
    
    protected function has_feed_condition_field() {

        $fields = $this->settings_fields_only( 'feed' );

        foreach( $fields as $field ) {
            if( $field['type'] == 'feed_condition' )
                return true;
        }

        return false;
    }
    
    protected function add_delayed_payment_support( $options ) {
        
        $this->delayed_payment_integration = $options;
        
        if( is_admin() ) {
            add_action( 'gform_paypal_action_fields', array( $this, 'add_paypal_settings' ), 10, 2);
            add_filter( 'gform_paypal_save_config', array( $this, 'save_paypal_settings' ) );   
        } else {
            add_action( 'gform_paypal_fulfillment', array( $this, 'paypal_fulfillment' ), 10, 4 );
        }
        
    }
    
    public function add_paypal_settings( $feed, $form ) {

        $form_id = rgar( $form, 'id' );
        $feed_meta = $feed['meta'];
        $settings_style = $this->has_feed_for_this_addon( $form_id ) ? '' : 'display:none;';

        $addon_name = $this->_slug;
        $addon_feeds = array();
        foreach( $this->get_feeds( $form_id ) as $feed ) {
            $addon_feeds[] = $feed['form_id'];
        }
        
        ?>
        
        <li style="<?php echo $settings_style?>" id="delay_<?php echo $addon_name; ?>_container">
            <input type="checkbox" name="paypal_delay_<?php echo $addon_name; ?>" id="paypal_delay_<?php echo $addon_name; ?>" value="1" <?php echo rgar( $feed_meta, "delay_$addon_name" ) ? "checked='checked'" : '' ?> />
            <label class="inline" for="paypal_delay_<?php echo $addon_name; ?>">
                <?php 
                if( rgar( $this->delayed_payment_integration, 'option_label' ) ) {
                    echo rgar( $this->delayed_payment_integration, 'option_label' );
                } else {
                    _e( 'Process ' . $this->get_short_title() . ' feed only when payment is received.', 'gravityforms' );
                }
                ?>
            </label>
        </li>

        <script type="text/javascript">
            jQuery(document).ready(function($){
                
                jQuery(document).bind('paypalFormSelected', function(event, form) {

                    var addonFormIds = <?php echo json_encode( $addon_feeds ); ?>;
                    var isApplicableFeed = false;

                    if( jQuery.inArray( String( form.id ), addonFormIds ) != -1 )
                        isApplicableFeed = true;

                    if( isApplicableFeed ) {
                        jQuery("#delay_<?php echo $addon_name; ?>_container").show();
                    } else {
                        jQuery("#delay_<?php echo $addon_name; ?>_container").hide();
                    }
                    
                });
            });
        </script>

        <?php
    }
    
    public function save_paypal_settings( $feed ) {
        $feed['meta']["delay_{$this->_slug}"] = rgpost( "paypal_delay_{$this->_slug}" );
        return $feed;
    }
    
    public function paypal_fulfillment( $entry, $config, $transaction_id, $amount ) {
        
        self::log_debug( "Checking PayPal fulfillment for transaction {$transaction_id}" );
        
        $is_fulfilled = gform_get_meta( $entry['id'], "{$this->_slug}_is_fulfilled" );

        if ( !$is_fulfilled ) {
            
            self::log_debug( "Entry {$entry['id']} has not been fulfilled." );
            $form = RGFormsModel::get_form_meta( $entry['form_id'] );
            $this->maybe_process_feed( $entry, $form, true );
            
            // updating meta to indicate this entry has been fulfilled for the current add-on
            self::log_debug( "Marking entry {$entry['id']} as fulfilled" );
            gform_update_meta( $entry['id'], "{$this->_slug}_is_fulfilled", true );
            
        } else {
            self::log_debug( "Entry {$entry['id']} is already fulfilled." );
        }
        
    }
    
    // @ALEX Naming might be improved here
    protected function has_feed_for_this_addon( $form_id ) {
        return $this->get_feeds( $form_id ) != false;
    }
    
}


if (!class_exists('WP_List_Table'))
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class GFAddOnFeedsTable extends WP_List_Table {

    public $_column_value_callback = array();
    public $_no_items_callback = array();

    private $_feeds;
    private $_slug;
    private $_columns;
    private $_bulk_actions;
    private $_action_links;

    function __construct($feeds, $slug, $columns = array(), $bulk_actions, $action_links, $column_value_callback, $no_items_callback=null) {
        $this->_bulk_actions                = $bulk_actions;
        $this->_feeds                       = $feeds;
        $this->_slug                        = $slug;
        $this->_columns                     = $columns;
        $this->_column_value_callback       = $column_value_callback;
        $this->_action_links                = $action_links;
        $this->_no_items_callback           = $no_items_callback;

        $standard_cols = array(
            'cb'        => __('Checkbox', 'gravityforms'),
            'is_active' => ''
        );

        $all_cols = array_merge($standard_cols, $columns);

        $this->_column_headers = array(
            $all_cols,
            array(),
            array()
        );

        parent::__construct(array(
            'singular' => __('feed', 'gravityforms'),
            'plural'   => __('feeds', 'gravityforms'),
            'ajax'     => false
        ));
    }

    function prepare_items() {
        $this->items = isset($this->_feeds) ? $this->_feeds : array();
    }

    function get_bulk_actions() {
        return $this->_bulk_actions;
    }

    function no_items() {
        $default = sprintf(__("You don't have any feeds configured. Let's go %screate one%s"), "<a href='" . add_query_arg(array("fid" => 0)) . "'>", "</a>");
        $message = is_callable($this->_no_items_callback) ? $value = call_user_func($this->_no_items_callback) : $default;

        echo $message;
    }

    function column_default($item, $column) {
        if (is_callable($this->_column_value_callback)) {
            $value = call_user_func($this->_column_value_callback, $item, $column);
        }

        //adding action links to the first column of the list
        $columns = array_keys($this->_columns);
        if(is_array($columns) && count($columns) > 0 && $columns[0] == $column){
            $value = $this->add_action_links($item, $column, $value);
        }

        return $value;
    }

    function column_cb($item) {
        $feed_id = rgar($item, "id");

        return sprintf(
            '<input type="checkbox" name="feed_ids[]" value="%s" />', $feed_id
        );
    }

    function add_action_links($item, $column, $value){

        $actions  = apply_filters($this->_slug . '_feed_actions', $this->_action_links, $item, $column);

        //replacing {id} merge variable with actual feed id
        foreach($actions as $action => &$link){
            $link = str_replace("{id}", $item["id"], $link);
        }

        return sprintf('%1$s %2$s', $value, $this->row_actions($actions));
    }

    /*function column_feed_name($item) {
        $name     = isset($item["name"]) ? $item["name"] : __("Untitled feed", "gravityforms");
        $feed_id  = rgar($item, "id");
        $edit_url = add_query_arg(array("fid" => $feed_id));
        $actions  = apply_filters($this->_slug . '_feed_actions', array(
            'edit'   => '<a title="' . __('Edit this feed', 'gravityforms') . '" href="' . $edit_url . '">' . __('Edit', 'gravityforms') . '</a>',
            'delete' => '<a title="' . __('Delete this feed', 'gravityforms') . '" class="submitdelete" onclick="javascript: if(confirm(\'' . __("WARNING: You are about to delete this feed.", "gravityforms") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravityforms") . '\')){ gaddon.deleteFeed(\'' . $feed_id . '\'); }" style="cursor:pointer;">' . __('Delete', 'gravityforms') . '</a>'
        ));

        return sprintf('%1$s %2$s', $name, $this->row_actions($actions));
    }*/

    function column_is_active($item) {
        $is_active = intval(rgar($item, "is_active"));
        $src = GFCommon::get_base_url() . "/images/active{$is_active}.png";

        $title = $is_active ? __("Active", "gravityforms") : __("Inactive", "gravityforms");
        $img = "<img src='{$src}' title='{$title}' onclick='gaddon.toggleFeedActive(this, \"{$this->_slug}\", {$item['id']});' style='cursor:pointer';/>";

        return $img;
    }
}
