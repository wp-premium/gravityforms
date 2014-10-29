<?php

if(!class_exists('GFForms')){
    die();
}

class GFExport{

    private static $min_import_version = "1.3.12.3";

    public static function maybe_export(){
        if(isset($_POST["export_lead"])){
            check_admin_referer("rg_start_export", "rg_start_export_nonce");
            //see if any fields chosen
            if (empty($_POST["export_field"])){
                GFCommon::add_error_message( __('Please select the fields to be exported', 'gravityforms') );
                return;
            }
            $form_id=$_POST["export_form"];
            $form = RGFormsModel::get_form_meta($form_id);

            $filename = sanitize_title_with_dashes($form["title"]) . "-" . gmdate("Y-m-d", GFCommon::get_local_timestamp(time())) . ".csv";
            $charset = get_option('blog_charset');
            header('Content-Description: File Transfer');
            header("Content-Disposition: attachment; filename=$filename");
            header('Content-Type: text/plain; charset=' . $charset, true);
            $buffer_length = ob_get_length(); //length or false if no buffer
            if ($buffer_length > 1){
            	ob_clean();
			}
            GFExport::start_export($form);

            die();
        }
        else if(isset($_POST["export_forms"])){
            check_admin_referer("gf_export_forms", "gf_export_forms_nonce");
            $selected_forms = rgpost("gf_form_id");
            if(empty($selected_forms)){
                GFCommon::add_error_message( __('Please select the forms to be exported', 'gravityforms') );
                return;
            }

            $forms = RGFormsModel::get_form_meta_by_id($selected_forms);

            //removing the inputs for checkboxes (choices will be used during the import)
            foreach($forms as &$form){

                foreach($form["fields"] as &$field){
                    $inputType = RGFormsModel::get_input_type($field);

                    if(isset($field["pageNumber"]))
                        unset($field["pageNumber"]);

                    if($inputType == "checkbox")
                        unset($field["inputs"]);

                    if($inputType != "address")
                        unset($field["addressType"]);

                    if($inputType != "date"){
                        unset($field["calendarIconType"]);
                        unset($field["dateType"]);
                    }

                    if($inputType != "creditcard")
                        unset($field["creditCards"]);

                    if($field["type"] == rgar($field, "inputType"))
                        unset($field["inputType"]);

                    if(in_array($inputType, array("checkbox", "radio", "select")) && !rgar($field,"enableChoiceValue")){
                        foreach($field["choices"] as &$choice)
                            unset($choice["value"]);
                    }
                }

				// convert associative array to indexed
				if(isset($form['confirmations']))
					$form['confirmations'] = array_values($form['confirmations']);

				if(isset($form['notifications'])){
					$form['notifications'] = array_values($form['notifications']);

					foreach( $form["notifications"] as &$notification ){
						$notification["isActive"] = rgar( $notification, "isActive" ) ? "1" : "0";
					}
				}

                $form = apply_filters( 'gform_export_form', $form );
                $form = apply_filters( "gform_export_form_{$form['id']}", $form );

            }

            require_once("xml.php");

            $options = array(
                "version" => GFCommon::$version,
                "forms/form/id" => array("is_hidden" => true),
                "forms/form/nextFieldId" => array("is_hidden" => true),
                "forms/form/notification/routing" => array("array_tag" => "routing_item"),
                "forms/form/useCurrentUserAsAuthor" => array("is_attribute" => true),
                "forms/form/postAuthor" => array("is_attribute" => true),
                "forms/form/postCategory" => array("is_attribute" => true),
                "forms/form/postStatus" => array("is_attribute" => true),
                "forms/form/postAuthor" => array("is_attribute" => true),
                "forms/form/postFormat" => array("is_attribute" => true),
                "forms/form/labelPlacement" => array("is_attribute" => true),
                "forms/form/confirmation/type" => array("is_attribute" => true),
                "forms/form/lastPageButton/type" => array("is_attribute" => true),
                "forms/form/pagination/type" => array("is_attribute" => true),
                "forms/form/pagination/style" => array("is_attribute" => true),
                "forms/form/button/type" => array("is_attribute" => true),
                "forms/form/button/conditionalLogic/actionType" => array("is_attribute" => true),
                "forms/form/button/conditionalLogic/logicType" => array("is_attribute" => true),
                "forms/form/button/conditionalLogic/rules/rule/fieldId" => array("is_attribute" => true),
                "forms/form/button/conditionalLogic/rules/rule/operator" => array("is_attribute" => true),
                "forms/form/button/conditionalLogic/rules/rule/value" => array("allow_empty" => true),
                "forms/form/fields/field/id" => array("is_attribute" => true),
                "forms/form/fields/field/type" => array("is_attribute" => true),
                "forms/form/fields/field/inputType" => array("is_attribute" => true),
                "forms/form/fields/field/displayOnly" => array("is_attribute" => true),
                "forms/form/fields/field/size" => array("is_attribute" => true),
                "forms/form/fields/field/isRequired" => array("is_attribute" => true),
                "forms/form/fields/field/noDuplicates" => array("is_attribute" => true),
                "forms/form/fields/field/inputs/input/id" => array("is_attribute" => true),
                "forms/form/fields/field/inputs/input/name" => array("is_attribute" => true),
                "forms/form/fields/field/formId" => array("is_hidden" => true),
                "forms/form/fields/field/descriptionPlacement" => array("is_hidden" => true),
                "forms/form/fields/field/allowsPrepopulate" => array("is_attribute" => true),
                "forms/form/fields/field/adminOnly" => array("is_attribute" => true),
                "forms/form/fields/field/enableChoiceValue" => array("is_attribute" => true),
                "forms/form/fields/field/enableEnhancedUI" => array("is_attribute" => true),
                "forms/form/fields/field/conditionalLogic/actionType" => array("is_attribute" => true),
                "forms/form/fields/field/conditionalLogic/logicType" => array("is_attribute" => true),
                "forms/form/fields/field/conditionalLogic/rules/rule/fieldId" => array("is_attribute" => true),
                "forms/form/fields/field/conditionalLogic/rules/rule/operator" => array("is_attribute" => true),
                "forms/form/fields/field/conditionalLogic/rules/rule/value" => array("allow_empty" => true),
                "forms/form/fields/field/previousButton/type" => array("is_attribute" => true),
                "forms/form/fields/field/nextButton/type" => array("is_attribute" => true),
                "forms/form/fields/field/nextButton/conditionalLogic/actionType" => array("is_attribute" => true),
                "forms/form/fields/field/nextButton/conditionalLogic/logicType" => array("is_attribute" => true),
                "forms/form/fields/field/nextButton/conditionalLogic/rules/rule/fieldId" => array("is_attribute" => true),
                "forms/form/fields/field/nextButton/conditionalLogic/rules/rule/operator" => array("is_attribute" => true),
                "forms/form/fields/field/nextButton/conditionalLogic/rules/rule/value" => array("allow_empty" => true),
                "forms/form/fields/field/choices/choice/isSelected" => array("is_attribute" => true),
                "forms/form/fields/field/choices/choice/text" => array("allow_empty" => true),
                "forms/form/fields/field/choices/choice/value" => array("allow_empty" => true),
                "forms/form/fields/field/rangeMin" => array("is_attribute" => true),
                "forms/form/fields/field/rangeMax" => array("is_attribute" => true),
                "forms/form/fields/field/numberFormat" => array("is_attribute" => true),
                "forms/form/fields/field/calendarIconType" => array("is_attribute" => true),
                "forms/form/fields/field/dateFormat" => array("is_attribute" => true),
                "forms/form/fields/field/dateType" => array("is_attribute" => true),
                "forms/form/fields/field/nameFormat" => array("is_attribute" => true),
                "forms/form/fields/field/phoneFormat" => array("is_attribute" => true),
                "forms/form/fields/field/addressType" => array("is_attribute" => true),
                "forms/form/fields/field/hideCountry" => array("is_attribute" => true),
                "forms/form/fields/field/hideAddress2" => array("is_attribute" => true),
                "forms/form/fields/field/disableQuantity" => array("is_attribute" => true),
                "forms/form/fields/field/productField" => array("is_attribute" => true),
                "forms/form/fields/field/enablePrice" => array("is_attribute" => true),
                "forms/form/fields/field/displayTitle" => array("is_attribute" => true),
                "forms/form/fields/field/displayCaption" => array("is_attribute" => true),
                "forms/form/fields/field/displayDescription" => array("is_attribute" => true),
                "forms/form/fields/field/displayAllCategories" => array("is_attribute" => true),
                "forms/form/fields/field/postCustomFieldName" => array("is_attribute" => true),
                "forms/form/confirmations/confirmation/id" => array("is_attribute" => true),
                "forms/form/confirmations/confirmation/type" => array("is_attribute" => true),
                "forms/form/confirmations/confirmation/isDefault" => array("is_attribute" => true),
                "forms/form/confirmations/confirmation/disableAutoformatting" => array("is_attribute" => true),
                "forms/form/notifications/notification/id" => array("is_attribute" => true)
            );

            $options = apply_filters( 'gform_export_options', $options, $forms );

            $serializer = new RGXML($options);
            $xml = $serializer->serialize("forms", $forms);

            if ( !seems_utf8( $xml ) )
                $value = utf8_encode( $xml );

            $filename = "gravityforms-export-" . date("Y-m-d") . ".xml";
            header('Content-Description: File Transfer');
            header("Content-Disposition: attachment; filename=$filename");
            header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);
            echo $xml;
            die();
        }
    }

    public static function export_page() {

        if(!GFCommon::ensure_wp_version())
            return;

        echo GFCommon::get_remote_message();

        $view = rgget('view') ? rgget('view') : 'export_entry';

        switch($view) {

        case "export_entry":
            self::export_lead_page();
            break;

        case "import_form" :
            self::import_form_page();
            break;

        case "export_form" :
            self::export_form_page();
            break;

        default:
            do_action("gform_export_page_{$view}");
            break;

        }


    }

    public static function import_file($filepath, &$forms=null){

        $xmlstr = file_get_contents($filepath);

        require_once("xml.php");

        $options = array(
                        "page" => array("unserialize_as_array" => true),
                        "form"=> array("unserialize_as_array" => true),
                        "field"=> array("unserialize_as_array" => true),
                        "rule"=> array("unserialize_as_array" => true),
                        "choice"=> array("unserialize_as_array" => true),
                        "input"=> array("unserialize_as_array" => true),
                        "routing_item"=> array("unserialize_as_array" => true),
                        "creditCard"=> array("unserialize_as_array" => true),
                        "routin"=> array("unserialize_as_array" => true), //routin is for backwards compatibility
                        "confirmation" => array("unserialize_as_array" => true),
                        "notification" => array("unserialize_as_array" => true)
                        );
        $options = apply_filters('gform_import_form_xml_options', $options);
		$xml = new RGXML($options);
        $forms = $xml->unserialize($xmlstr);

        if(!$forms)
            return 0;   //Error. could not unserialize XML file
        else if(version_compare($forms["version"], self::$min_import_version, "<"))
            return -1;  //Error. XML version is not compatible with current Gravity Forms version

        //cleaning up generated object
        self::cleanup($forms);

        foreach($forms as $key => &$form){

            $title = $form["title"];
            $count = 2;
            while(!RGFormsModel::is_unique_title($title)){
                $title = $form["title"] . "($count)";
                $count++;
            }

            //inserting form
            $form_id = RGFormsModel::insert_form($title);

            //updating form meta
            $form["title"] = $title;
            $form["id"] = $form_id;

            $form = GFFormsModel::trim_form_meta_values($form);

            if(isset($form['confirmations'])) {
                $form['confirmations'] = self::set_property_as_key($form['confirmations'], 'id');
                $form['confirmations'] = GFFormsModel::trim_conditional_logic_values($form['confirmations'], $form);
                GFFormsModel::update_form_meta($form_id, $form['confirmations'], 'confirmations');
                unset($form['confirmations']);
            }

            if(isset($form['notifications'])) {
                $form['notifications'] = self::set_property_as_key($form['notifications'], 'id');
                $form['notifications'] = GFFormsModel::trim_conditional_logic_values($form['notifications'], $form);
                GFFormsModel::update_form_meta($form_id, $form['notifications'], 'notifications');
                unset($form['notifications']);
            }

            RGFormsModel::update_form_meta($form_id, $form);

        }

        return sizeof($forms);
    }

    public static function set_property_as_key($array, $property) {
        $new_array = array();
        foreach($array as $item) {
            $new_array[$item[$property]] = $item;
        }
        return $new_array;
    }

    public static function import_form_page() {

        if(!GFCommon::current_user_can_any("gravityforms_edit_forms"))
            wp_die("You do not have permission to access this page");

        if(isset($_POST["import_forms"])){
            check_admin_referer("gf_import_forms", "gf_import_forms_nonce");

            if(!empty($_FILES["gf_import_file"]["tmp_name"])){
                $count = self::import_file($_FILES["gf_import_file"]["tmp_name"], $forms);

                if($count == 0 ){
                    GFCommon::add_error_message( __("Forms could not be imported. Please make sure your XML export file is in the correct format.", "gravityforms") );
                }
                else if($count == "-1"){
                    GFCommon::add_error_message( __("Forms could not be imported. Your XML export file is not compatible with your current version of Gravity Forms.", "gravityforms") );
                }
                else
                {
                    $form_text = $count > 1 ? __("forms", "gravityforms") : __("form", "gravityforms");
                    $edit_link = $count == 1 ? "<a href='admin.php?page=gf_edit_forms&id={$forms[0]["id"]}'>" . __("Edit Form", "gravityforms") . "</a>" : "";
                    GFCommon::add_message( sprintf(__("Gravity Forms imported %d {$form_text} successfully", "gravityforms"), $count) . ". $edit_link" );
                }
            }
        }

        self::page_header(__('Import Forms', 'gravityforms'));

        ?>

        <p class="textleft">
            <?php _e("Select the Gravity Forms XML file you would like to import. When you click the import button below, Gravity Forms will import the forms.", "gravityforms"); ?>
        </p>

        <div class="hr-divider"></div>

        <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
            <?php echo wp_nonce_field("gf_import_forms", "gf_import_forms_nonce"); ?>
            <table class="form-table">
              <tr valign="top">

                   <th scope="row"><label for="gf_import_file"><?php _e("Select File", "gravityforms");?></label> <?php gform_tooltip("import_select_file") ?></th>
                    <td><input type="file" name="gf_import_file" id="gf_import_file"/></td>
              </tr>
        </table>
        <br /><br />
            <input type="submit" value="<?php _e("Import", "gravityforms")?>" name="import_forms" class="button button-large button-primary" />

        </form>

        <?php

        self::page_footer();

    }

    public static function export_form_page(){

        if(!GFCommon::current_user_can_any("gravityforms_edit_forms"))
            wp_die("You do not have permission to access this page");

        self::page_header(__('Export Forms', 'gravityforms'));

        ?>

        <p class="textleft"><?php _e("Select the forms you would like to export. When you click the download button below, Gravity Forms will create an XML file for you to save to your computer. Once you've saved the download file, you can use the Import tool to import the forms.", "gravityforms"); ?></p>
		<div class="hr-divider"></div>
        <form id="gform_export" method="post" style="margin-top:10px;">
            <?php echo wp_nonce_field("gf_export_forms", "gf_export_forms_nonce"); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="export_fields"><?php _e("Select Forms", "gravityforms"); ?></label> <?php gform_tooltip("export_select_forms") ?></th>
                    <td>
                        <ul id="export_form_list">
                            <?php
                            $forms = RGFormsModel::get_forms(null, "title");
                            foreach($forms as $form){
                                ?>
                                <li>
                                    <input type="checkbox" name="gf_form_id[]" id="gf_form_id_<?php echo absint($form->id)?>" value="<?php echo absint($form->id)?>"/>
                                    <label for="gf_form_id_<?php echo absint($form->id)?>"><?php echo esc_html($form->title) ?></label>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </td>
               </tr>
            </table>

             <br/><br/>
            <input type="submit" value="<?php _e("Download Export File", "gravityforms")?>" name="export_forms" class="button button-large button-primary" />
        </form>

        <?php

        self::page_footer();

    }

    public static function export_lead_page(){

        if(!GFCommon::current_user_can_any("gravityforms_export_entries"))
            wp_die("You do not have permission to access this page");


        self::page_header(__('Export Entries', 'gravityforms'));

        ?>

        <script type="text/javascript">

            var gfSpinner;

            <?php GFCommon::gf_global(); ?>
            <?php GFCommon::gf_vars(); ?>

            function SelectExportForm(formId){

                if(!formId)
                    return;

                gfSpinner = new gfAjaxSpinner(jQuery('select#export_form'), gf_vars.baseUrl + '/images/spinner.gif', 'position: relative; top: 2px; left: 5px;');

                var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "action", "rg_select_export_form" );
                mysack.setVar( "rg_select_export_form", "<?php echo wp_create_nonce("rg_select_export_form") ?>" );
                mysack.setVar( "form_id", formId);
                mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while selecting a form", "gravityforms")) ?>' )};
                mysack.runAJAX();

                return true;
            }

            function EndSelectExportForm(aryFields, filterSettings){

                gfSpinner.destroy();

                if(aryFields.length == 0)
                {
                    jQuery("#export_field_container, #export_date_container, #export_submit_container").hide()
                    return;
                }

                var fieldList = "<li><input id='select_all' type='checkbox' onclick=\"jQuery('.gform_export_field').attr('checked', this.checked); jQuery('#gform_export_check_all').html(this.checked ? '<strong><?php _e("Deselect All", "gravityforms") ?></strong>' : '<strong><?php _e("Select All", "gravityforms") ?></strong>'); \"> <label id='gform_export_check_all' for='select_all'><strong><?php _e("Select All", "gravityforms") ?></strong></label></li>";
                for(var i=0; i<aryFields.length; i++){
                    fieldList += "<li><input type='checkbox' id='export_field_" + i + "' name='export_field[]' value='" + aryFields[i][0] + "' class='gform_export_field'> <label for='export_field_" + i + "'>" + aryFields[i][1] + "</label></li>";
                }
                jQuery("#export_field_list").html(fieldList);
                jQuery("#export_date_start, #export_date_end").datepicker({dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true});

                jQuery("#export_field_container, #export_filter_container, #export_date_container, #export_submit_container").hide().show();

                gf_vars.filterAndAny = '<?php _e("Export entries if {0} of the following match:", "gravityforms") ?>';
                jQuery("#export_filters").gfFilterUI(filterSettings);
            }
            jQuery(document).ready(function(){
                jQuery("#gform_export").submit(function(){
                    if(jQuery(".gform_export_field:checked").length == 0){
                        alert('<?php _e('Please select the fields to be exported', 'gravityforms');  ?>');
                        return false;
                    }
                });
            });


        </script>

        <p class="textleft"><?php _e("Select a form below to export entries. Once you have selected a form you may select the fields you would like to export and then define optional filters for field values and the date range. When you click the download button below, Gravity Forms will create a CSV file for you to save to your computer.", "gravityforms"); ?></p>
        <div class="hr-divider"></div>
        <form id="gform_export" method="post" style="margin-top:10px;">
            <?php echo wp_nonce_field("rg_start_export", "rg_start_export_nonce"); ?>
            <table class="form-table">
              <tr valign="top">

                   <th scope="row"><label for="export_form"><?php _e("Select A Form", "gravityforms"); ?></label> <?php gform_tooltip("export_select_form") ?></th>
                    <td>

                      <select id="export_form" name="export_form" onchange="SelectExportForm(jQuery(this).val());">
                        <option value=""><?php _e("Select a form", "gravityforms"); ?></option>
                        <?php
                        $forms = RGFormsModel::get_forms(null, "title");
                        foreach($forms as $form){
                            ?>
                            <option value="<?php echo absint($form->id) ?>"><?php echo esc_html($form->title) ?></option>
                            <?php
                        }
                        ?>
                    </select>

                    </td>
                </tr>
              <tr id="export_field_container" valign="top" style="display: none;">
                   <th scope="row"><label for="export_fields"><?php _e("Select Fields", "gravityforms"); ?></label> <?php gform_tooltip("export_select_fields") ?></th>
                    <td>
                        <ul id="export_field_list">
                        </ul>
                    </td>
               </tr>
              <tr id="export_filter_container" valign="top" style="display: none;">
                  <th scope="row"><label><?php _e("Conditional Logic", "gravityforms"); ?></label> <?php gform_tooltip("export_conditional_logic") ?></th>
                  <td>
                      <div id="export_filters">
                        <!--placeholder-->
                      </div>

                  </td>
              </tr>
              <tr id="export_date_container" valign="top" style="display: none;">
                   <th scope="row"><label for="export_date"><?php _e("Select Date Range", "gravityforms"); ?></label> <?php gform_tooltip("export_date_range") ?></th>
                    <td>
                        <div>
                            <span style="width:150px; float:left; ">
                                <input type="text" id="export_date_start" name="export_date_start" style="width:90%"/>
                                <strong><label for="export_date_start" style="display:block;"><?php _e("Start", "gravityforms"); ?></label></strong>
                            </span>

                            <span style="width:150px; float:left;">
                                <input type="text" id="export_date_end" name="export_date_end" style="width:90%"/>
                                <strong><label for="export_date_end" style="display:block;"><?php _e("End", "gravityforms"); ?></label></strong>
                            </span>
                            <div style="clear: both;"></div>
                            <?php _e("Date Range is optional, if no date range is selected all entries will be exported.", "gravityforms"); ?>
                        </div>
                    </td>
               </tr>
            </table>
            <ul>
                <li id="export_submit_container" style="display:none; clear:both;">
                    <br/><br/>
                    <input type="submit" name="export_lead" value="<?php _e("Download Export File", "gravityforms"); ?>" class="button button-large button-primary"/>
                    <span id="please_wait_container" style="display:none; margin-left:15px;">
                        <img src="<?php echo GFCommon::get_base_url()?>/images/loading.gif"> <?php _e("Exporting entries. Please wait...", "gravityforms"); ?>
                    </span>

                    <iframe id="export_frame" width="1" height="1" src="about:blank"></iframe>
                </li>
            </ul>
        </form>

        <?php
        self::page_footer(__('Export Entries', 'gravityforms'));

    }

    private static function get_field_row_count($form, $exported_field_ids, $entry_count){
        $list_fields = GFCommon::get_fields_by_type($form, array("list"));

        //only getting fields that have been exported
        $field_ids = "";
        foreach($list_fields as $field){
            if(in_array($field["id"], $exported_field_ids) && rgar($field, "enableColumns"))
                $field_ids .= $field["id"] . ",";
        }

        if(empty($field_ids))
            return array();

        $field_ids = substr($field_ids, 0, strlen($field_ids) -1);

        $page_size = 200;
        $offset = 0;

        $row_counts = array();
        global $wpdb;

        $go_to_next_page = true;

        while($go_to_next_page){
            $sql = "SELECT d.field_number as field_id, ifnull(l.value, d.value) as value
                    FROM {$wpdb->prefix}rg_lead_detail d
                    LEFT OUTER JOIN {$wpdb->prefix}rg_lead_detail_long l ON d.id = l.lead_detail_id
                    WHERE d.form_id={$form["id"]} AND cast(d.field_number as decimal) IN ({$field_ids})
                    LIMIT {$offset}, {$page_size}";

            $results = $wpdb->get_results($sql, ARRAY_A);

            foreach($results as $result){
                $list = unserialize($result["value"]);
                $current_row_count = isset($row_counts[$result["field_id"]]) ? intval($row_counts[$result["field_id"]]) : 0;

                if(is_array($list) && count($list) > $current_row_count ){
                    $row_counts[$result["field_id"]] = count($list);
                }
            }

            $offset += $page_size;

            $go_to_next_page = count($results) == $page_size;
        }

        return $row_counts;
    }

    public static function get_gmt_timestamp($local_timestamp){
        return $local_timestamp - (get_option( 'gmt_offset' ) * 3600 );
    }

    public static function get_gmt_date($local_date){

        $local_timestamp = strtotime($local_date);
        $gmt_timestamp = self::get_gmt_timestamp($local_timestamp);
        $date = gmdate("Y-m-d H:i:s", $gmt_timestamp);

        return $date;
    }

    public static function start_export($form){

        $form_id = $form["id"];
        $fields = $_POST["export_field"];

        $start_date = empty($_POST["export_date_start"]) ? "" : self::get_gmt_date($_POST["export_date_start"] . " 00:00:00");
        $end_date = empty($_POST["export_date_end"]) ? "" : self::get_gmt_date($_POST["export_date_end"] . " 23:59:59");

        $search_criteria["status"] = "active";
        $search_criteria["field_filters"] = GFCommon::get_field_filters_from_post($form);
        if(!empty($start_date))
            $search_criteria["start_date"] = $start_date;

        if(!empty($end_date))
            $search_criteria["end_date"] = $end_date;

        $sorting = array('key' => "date_created", 'direction' => "DESC", "type"=>"info");

        GFCommon::log_debug("start date: {$start_date}");
        GFCommon::log_debug("end date: {$end_date}");

        $form = self::add_default_export_fields($form);

        $entry_count = GFAPI::count_entries($form_id, $search_criteria);

        $page_size = 100;
        $offset = 0;

        //Adding BOM marker for UTF-8
        $lines = chr(239) . chr(187) . chr(191);

        // set the separater
        $separator = apply_filters('gform_export_separator_' . $form_id, apply_filters('gform_export_separator', ',', $form_id), $form_id);

        $field_rows = self::get_field_row_count($form, $fields, $entry_count);

        //writing header
        $headers = array();
        foreach($fields as $field_id){
            $field = RGFormsModel::get_field($form, $field_id);
            $value = str_replace('"', '""', GFCommon::get_label($field, $field_id)) ;

            GFCommon::log_debug("Header for field ID {$field_id}: {$value}");

            $headers[$field_id] = $value;

            $subrow_count = isset($field_rows[$field_id]) ? intval($field_rows[$field_id]) : 0;
            if($subrow_count == 0){
                $lines .= '"' . $value . '"' . $separator;
            }
            else{
                for($i = 1; $i <= $subrow_count; $i++){
                    $lines .= '"' . $value . " " . $i . '"' . $separator;
                }
            }

            GFCommon::log_debug("Lines: {$lines}");
        }
        $lines = substr($lines, 0, strlen($lines)-1) . "\n";

        //paging through results for memory issues
        while($entry_count > 0){
            //$leads = RGFormsModel::get_leads($form_id,"date_created", "DESC", "", $offset, $page_size, null, null, false, $start_date, $end_date);
            $paging = array(
                'offset'    => $offset,
                'page_size' => $page_size
            );
            $leads = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging );

            $leads = apply_filters("gform_leads_before_export_$form_id", apply_filters("gform_leads_before_export", $leads, $form, $paging), $form, $paging);

            foreach($leads as $lead){
                foreach($fields as $field_id){
                    switch($field_id){
                        case "date_created" :
                            $lead_gmt_time = mysql2date("G", $lead["date_created"]);
                            $lead_local_time = GFCommon::get_local_timestamp($lead_gmt_time);
                            $value = date_i18n("Y-m-d H:i:s", $lead_local_time, true);
                        break;
                        default :
                            $long_text = "";
                            if(strlen(rgar($lead,$field_id)) >= (GFORMS_MAX_FIELD_LENGTH-10)){
                                $long_text = RGFormsModel::get_field_value_long($lead, $field_id, $form);
                            }

                            $value = !empty($long_text) ? $long_text : rgar($lead,$field_id);

                            $field = RGFormsModel::get_field($form, $field_id);
                            $input_type = RGFormsModel::get_input_type($field);

                            if($input_type == "checkbox"){
                                $value = GFFormsModel::is_checkbox_checked($field_id, $headers[$field_id], $lead, $form);
                                if($value === false)
                                    $value = "";
                            }
                            else if($input_type == "fileupload" && rgar($field,"multipleFiles") ){
                                $value = !empty($value) ? implode(" , ", json_decode($value, true)) : "";
                            }

                            $value = apply_filters("gform_export_field_value", $value, $form_id, $field_id, $lead);

                            GFCommon::log_debug("Value for field ID {$field_id}: {$value}");
                        break;
                    }

                    if(isset($field_rows[$field_id])){
                        $list = empty($value) ? array() : unserialize($value);

                        foreach($list as $row){
                            $row_values = array_values($row);
                            $row_str = implode("|", $row_values);
                            $lines .= '"' . str_replace('"', '""', $row_str) . '"' . $separator;
                        }

                        //filling missing subrow columns (if any)
                        $missing_count = intval($field_rows[$field_id]) - count($list);
                        for($i=0; $i<$missing_count; $i++)
                            $lines .= '""' . $separator;

                    }
                    else{
                        $value = maybe_unserialize($value);
                        if(is_array($value)){
                            $value = implode("|", $value);
                        }

                        $lines .= '"' . str_replace('"', '""', $value) . '"' . $separator;
                    }
                }
                $lines = substr($lines, 0, strlen($lines)-1);

                GFCommon::log_debug("Lines: {$lines}");

                $lines.= "\n";
            }

            $offset += $page_size;
            $entry_count -= $page_size;

            if ( !seems_utf8( $lines ) )
                $lines = utf8_encode( $lines );

            echo $lines;
            $lines = "";
        }
    }

	public static function add_default_export_fields($form){

        //adding default fields
        array_push($form["fields"],array("id" => "created_by" , "label" => __("Created By (User Id)", "gravityforms")));
        array_push($form["fields"],array("id" => "id" , "label" => __("Entry Id", "gravityforms")));
        array_push($form["fields"],array("id" => "date_created" , "label" => __("Entry Date", "gravityforms")));
        array_push($form["fields"],array("id" => "source_url" , "label" => __("Source Url", "gravityforms")));
        array_push($form["fields"],array("id" => "transaction_id" , "label" => __("Transaction Id", "gravityforms")));
        array_push($form["fields"],array("id" => "payment_amount" , "label" => __("Payment Amount", "gravityforms")));
        array_push($form["fields"],array("id" => "payment_date" , "label" => __("Payment Date", "gravityforms")));
        array_push($form["fields"],array("id" => "payment_status" , "label" => __("Payment Status", "gravityforms")));
        //array_push($form["fields"],array("id" => "payment_method" , "label" => __("Payment Method", "gravityforms"))); //wait until all payment gateways have been released
        array_push($form["fields"],array("id" => "post_id" , "label" => __("Post Id", "gravityforms")));
        array_push($form["fields"],array("id" => "user_agent" , "label" => __("User Agent", "gravityforms")));
        array_push($form["fields"],array("id" => "ip" , "label" => __("User IP", "gravityforms")));
        $form = self::get_entry_meta($form);
        $form = apply_filters('gform_export_fields', $form);
        return $form;
    }

    private static function cleanup(&$forms){
        unset($forms["version"]);

        //adding checkboxes "inputs" property based on "choices". (they were removed from the export
        //to provide a cleaner xml format
        foreach($forms as &$form){
            if(!is_array($form["fields"]))
                continue;

            foreach($form["fields"] as &$field){
                $input_type = RGFormsModel::get_input_type($field);

                if(in_array($input_type, array("checkbox", "radio", "select", "multiselect"))){

                    //creating inputs array for checkboxes
                    if($input_type == "checkbox" && !isset($field["inputs"]))
                        $field["inputs"] = array();

                    $adjust_by = 0;
                    for($i=1, $count = sizeof($field["choices"]); $i<=$count; $i++){

                        if(!RGForms::get("enableChoiceValue", $field))
                            $field["choices"][$i-1]["value"] = $field["choices"][$i-1]["text"];

                        if($input_type == "checkbox"){
                            if( ( ($i + $adjust_by) % 10) == 0)
                                $adjust_by++;

                            $id = $i + $adjust_by;

                            $field["inputs"][] = array("id" => $field["id"] . "." . $id, "label" => $field["choices"][$i-1]["text"]);

                        }
                    }


                }
            }
        }
    }

	private static function get_entry_meta($form){
            $entry_meta = GFFormsModel::get_entry_meta($form["id"]);
            $keys = array_keys($entry_meta);
            foreach ($keys as $key){
                array_push($form["fields"],array("id" => $key , "label" => $entry_meta[$key]['label']));
            }
            return $form;
    }



    public static function page_header($title = ''){

        // register admin styles
        wp_register_style('gform_admin', GFCommon::get_base_url() . '/css/admin.css');
        wp_print_styles(array('jquery-ui-styles', 'gform_admin'));

        $current_tab = rgempty("view", $_GET) ? "export_entry" : rgget("view");
        $setting_tabs = self::get_tabs();

        // kind of boring having to pass the title, optionally get it from the settings tab
        if(!$title) {
            foreach($setting_tabs as $tab) {
                if($tab['name'] == $current_tab)
                    $title = $tab['name'];
            }
        }

        ?>


        <div class="wrap <?php echo $current_tab; ?>">

            <h2><?php echo $title ?></h2>

            <?php GFCommon::display_admin_message(); ?>

            <div id="gform_tab_group" class="gform_tab_group vertical_tabs">
                <ul id="gform_tabs" class="gform_tabs">
                    <?php
                    foreach($setting_tabs as $tab) {

                        $query = array("view" => $tab["name"]);
                        if(isset($tab["query"]))
                            $query = array_merge($query, $tab["query"]);

                        ?>
                        <li <?php echo $current_tab == $tab["name"] ? "class='active'" : ""?>>
                            <a href="<?php echo add_query_arg($query); ?>"><?php echo $tab["label"] ?></a>
                        </li>
                        <?php
                    }
                    ?>
                </ul>

                <div id="gform_tab_container" class="gform_tab_container">
                    <div class="gform_tab_content" id="tab_<?php echo $current_tab ?>">

        <?php
    }

    public static function page_footer(){
        ?>
                    </div> <!-- / gform_tab_content -->
                </div> <!-- / gform_tab_container -->
            </div> <!-- / gform_tab_group -->

            <br class="clear" style="clear: both;" />

        </div> <!-- / wrap -->
        <?php
    }

    public static function get_tabs() {

        $setting_tabs = array();
        if(GFCommon::current_user_can_any("gravityforms_export_entries")){
            $setting_tabs["10"] = array("name" => "export_entry", "label" => __("Export Entries", "gravityforms"));
        }

        if(GFCommon::current_user_can_any("gravityforms_edit_forms")){
            $setting_tabs["20"] = array("name" => "export_form" , "label" => __("Export Forms", "gravityforms"));
            $setting_tabs["30"] = array("name" => "import_form", "label" => __("Import Forms", "gravityforms"));
        }

        $setting_tabs = apply_filters("gform_export_menu", $setting_tabs);
        ksort($setting_tabs, SORT_NUMERIC);

        return $setting_tabs;
    }

}
?>