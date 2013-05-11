<?php

class GFExport{
    private static $min_import_version = "1.3.12.3";

    public static function maybe_export(){
        if(isset($_POST["export_lead"])){
            check_admin_referer("rg_start_export", "rg_start_export_nonce");
            //see if any fields chosen
            if (empty($_POST["export_field"])){
				echo "<div class='error' style='padding:15px;'>" . __("Please select the fields to be exported", "gravityforms") . "</div>";
                return;
            }
            $form_id=$_POST["export_form"];
            $form = RGFormsModel::get_form_meta($form_id);

            $filename = sanitize_title_with_dashes($form["title"]) . "-" . gmdate("Y-m-d", GFCommon::get_local_timestamp(time())) . ".csv";
            $charset = get_option('blog_charset');
            header('Content-Description: File Transfer');
            header("Content-Disposition: attachment; filename=$filename");
            header('Content-Type: text/plain; charset=' . $charset, true);
            ob_clean();
            GFExport::start_export($form);

            die();
        }
        else if(isset($_POST["export_forms"])){
            check_admin_referer("gf_export_forms", "gf_export_forms_nonce");
            $selected_forms = rgpost("gf_form_id");
            if(empty($selected_forms)){
                echo "<div class='error' style='padding:15px;'>" . __("Please select the forms to be exported", "gravityforms") . "</div>";
                return;
            }

            $forms = RGFormsModel::get_forms_by_id($selected_forms);

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
                "forms/form/fields/field/postCustomFieldName" => array("is_attribute" => true)
            );

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

    public static function export_page(){
        if(!GFCommon::ensure_wp_version())
            return;

        echo GFCommon::get_remote_message();

        $view = RGForms::get("view");
        switch($view){
            case "import_form" :
                self::import_form_page();
            break;

            case "export_form" :
                self::export_form_page();
            break;

            default:
                self::export_lead_page();
            break;
        }


    }

    public static function export_links(){
        $view = RGForms::get("view");

        ?>
        <ul class="subsubsub">
                <li><a href="?page=gf_export&view=export_entry" class="<?php echo $view=="export_entry" || empty($view) ? 'current' : ''; ?>"><?php _e("Export Entries", "gravityforms"); ?></a> | </li>
                <li><a href="?page=gf_export&view=export_form" class="<?php echo $view=="export_form" ? 'current' : ''; ?>"><?php _e("Export Forms", "gravityforms"); ?></a> | </li>
                <li><a href="?page=gf_export&view=import_form" class="<?php echo $view=="import_form" ? 'current' : ''; ?>"><?php _e("Import Forms", "gravityforms"); ?></a></li>
        </ul>
        <br style="clear:both"/>
        <br/>
        <?php
    }

    public static function import_file($filepath){

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
                        "routin"=> array("unserialize_as_array" => true) //routin is for backwards compatibility
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

        foreach($forms as $key => $form){
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
            RGFormsModel::update_form_meta($form_id, $form);
        }
        return sizeof($forms);

    }

    public static function import_form_page(){
        if(isset($_POST["import_forms"])){
            check_admin_referer("gf_import_forms", "gf_import_forms_nonce");

            if(!empty($_FILES["gf_import_file"]["tmp_name"])){

                $count = self::import_file($_FILES["gf_import_file"]["tmp_name"]);

                if($count == 0 ){
                    ?>
                    <div class="error" style="padding:10px;"><?php _e("Forms could not be imported. Please make sure your XML export file is in the correct format.", "gravityforms"); ?></div>
                    <?php
                }
                else if($count == "-1"){
                    ?>
                    <div class="error" style="padding:10px;"><?php _e("Forms could not be imported. Your XML export file is not compatible with your current version of Gravity Forms.", "gravityforms"); ?></div>
                    <?php
                }
                else
                {
                    $form_text = $count > 1 ? __("forms", "gravityforms") : __("form", "gravityforms");
                    ?>
                    <div class="updated" style="padding:10px;"><?php echo sprintf(__("Gravity Forms imported %d {$form_text} successfully", "gravityforms"), $count); ?></div>
                    <?php
                }
            }
        }

        ?>
        <link rel="stylesheet" href="<?php echo GFCommon::get_base_url()?>/css/admin.css"/>
        <div class="wrap">
        <div class="icon32" id="gravity-import-icon"><br></div>
            <h2><?php _e("Import Forms", "gravityforms") ?></h2>

            <p class="textleft"><?php
            self::export_links();

            _e("Select the Gravity Forms XML file you would like to import. When you click the import button below, Gravity Forms will import the forms.", "gravityforms");
            ?></p>
            <div class="hr-divider"></div>




            <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
                <?php echo wp_nonce_field("gf_import_forms", "gf_import_forms_nonce"); ?>
                <table class="form-table">
                  <tr valign="top">

                       <th scope="row"><label for="gf_import_file"><?php _e("Select File", "gravityforms");?></label></th>
                        <td><input type="file" name="gf_import_file" id="gf_import_file"/></td>
                  </tr>
            </table>
            <br /><br />
                <input type="submit" value="<?php _e("Import", "gravityforms")?>" name="import_forms" class="button-primary" />

            </form>
        </div>
        <?php
    }

    public static function export_form_page(){

        ?>
        <link rel="stylesheet" href="<?php echo GFCommon::get_base_url()?>/css/admin.css"/>
        <div class="wrap">
            <div class="icon32" id="gravity-export-icon"><br></div>
            <h2><?php _e("Export Forms", "gravityforms") ?></h2>
            <?php
            self::export_links();
            ?>

            <p class="textleft"><?php _e("Select the forms you would like to export. When you click the download button below, Gravity Forms will create a XML file for you to save to your computer. Once you've saved the download file, you can use the Import tool to import the forms.", "gravityforms"); ?></p>
			<div class="hr-divider"></div>
            <form method="post" style="margin-top:10px;">
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
                <input type="submit" value="<?php _e("Download Export File", "gravityforms")?>" name="export_forms" class="button-primary" />
            </form>
        </div>
        <?php
    }

    public static function export_lead_page(){
        ?>
        <script type='text/javascript' src='<?php echo GFCommon::get_base_url()?>/js/jquery-ui/ui.datepicker.js?ver=<?php echo GFCommon::$version ?>'></script>
        <script type="text/javascript">
            function SelectExportForm(formId){
                if(!formId)
                    return;

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

            function EndSelectExportForm(aryFields){
                if(aryFields.length == 0)
                {
                    jQuery("#export_field_container, #export_date_container, #export_submit_container").hide()
                    return;
                }

                var fieldList = "<li><input type='checkbox' onclick=\"jQuery('.gform_export_field').attr('checked', this.checked); jQuery('#gform_export_check_all').html(this.checked ? '<strong><?php _e("Deselect All", "gravityforms") ?></strong>' : '<strong><?php _e("Select All", "gravityforms") ?></strong>'); \"> <label id='gform_export_check_all'><strong><?php _e("Select All", "gravityforms") ?></strong></label></li>";
                for(var i=0; i<aryFields.length; i++){
                    fieldList += "<li><input type='checkbox' id='export_field_" + i + "' name='export_field[]' value='" + aryFields[i][0] + "' class='gform_export_field'> <label for='export_field_" + i + "'>" + aryFields[i][1] + "</label></li>";
                }
                jQuery("#export_field_list").html(fieldList);
                jQuery("#export_date_start, #export_date_end").datepicker({dateFormat: 'yy-mm-dd'});

                jQuery("#export_field_container, #export_date_container, #export_submit_container").hide().show();
            }
        </script>
        <link rel="stylesheet" href="<?php echo GFCommon::get_base_url()?>/css/admin.css"/>

        <div class="wrap">

            <div class="icon32" id="gravity-export-icon"><br></div>

            <h2><?php _e("Export Form Entries", "gravityforms") ?></h2>
            <?php
            self::export_links();
            ?>

            <p class="textleft"><?php _e("Select a form below to export entries. Once you have selected a form you may select the fields you would like to export and an optional date range. When you click the download button below, Gravity Forms will create a CSV file for you to save to your computer.", "gravityforms"); ?></p>
            <div class="hr-divider"></div>
            <form method="post" style="margin-top:10px;">
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
                        <input type="submit" name="export_lead" value="<?php _e("Download Export File", "gravityforms"); ?>" class="button-primary"/>
                        <span id="please_wait_container" style="display:none; margin-left:15px;">
                            <img src="<?php echo GFCommon::get_base_url()?>/images/loading.gif"> <?php _e("Exporting entries. Please wait...", "gravityforms"); ?>
                        </span>

                        <iframe id="export_frame" width="1" height="1" src="about:blank"></iframe>
                    </li>
                </ul>
            </form>
        </div>
        <?php


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
        while($entry_count > 0){
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
            $entry_count -= $page_size;
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

        $start_date = empty($_POST["export_date_start"]) ? "" : self::get_gmt_date($_POST["export_date_start"] . " 00:00");
        $end_date = empty($_POST["export_date_end"]) ? "" : self::get_gmt_date($_POST["export_date_end"] . " 23:59:59");

        GFCommon::log_debug("start date: {$start_date}");
        GFCommon::log_debug("end date: {$end_date}");

        $form = self::add_default_export_fields($form);

        $entry_count = RGFormsModel::get_lead_count($form_id, "", null, null, $start_date, $end_date);

        $page_size = 200;
        $offset = 0;

        //Adding BOM marker for UTF-8
        $lines = chr(239) . chr(187) . chr(191);

        // set the separater
        $separator = apply_filters('gform_export_separator_' . $form_id, apply_filters('gform_export_separator', ',', $form_id), $form_id);

        $field_rows = self::get_field_row_count($form, $fields, $entry_count);

        //writing header
        foreach($fields as $field_id){
            $field = RGFormsModel::get_field($form, $field_id);
            $value = str_replace('"', '""', GFCommon::get_label($field, $field_id)) ;

            $subrow_count = isset($field_rows[$field_id]) ? intval($field_rows[$field_id]) : 0;
            if($subrow_count == 0){
                $lines .= '"' . $value . '"' . $separator;
            }
            else{
                for($i = 1; $i <= $subrow_count; $i++){
                    $lines .= '"' . $value . " " . $i . '"' . $separator;
                }
            }
        }
        $lines = substr($lines, 0, strlen($lines)-1) . "\n";

        //paging through results for memory issues
        while($entry_count > 0){
            $leads = RGFormsModel::get_leads($form_id,"date_created", "DESC", "", $offset, $page_size, null, null, false, $start_date, $end_date);

            foreach($leads as $lead){
                foreach($fields as $field_id){
                    switch($field_id){
                        case "date_created" :
                            $lead_gmt_time = mysql2date("G", $lead["date_created"]);
                            $lead_local_time = GFCommon::get_local_timestamp($lead_gmt_time);
                            $value = date_i18n("Y-m-d H:i:s", $lead_local_time);
                        break;
                        default :
                            $long_text = "";
                            if(strlen($lead[$field_id]) >= (GFORMS_MAX_FIELD_LENGTH-10)){
                                $long_text = RGFormsModel::get_field_value_long($lead, $field_id, $form);
                            }

                            $value = !empty($long_text) ? $long_text : $lead[$field_id];
							$value = apply_filters("gform_export_field_value", $value, $form_id, $field_id, $lead);
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
                        if(is_array($value))
                            $value = implode("|", $value);

                        $lines .= '"' . str_replace('"', '""', $value) . '"' . $separator;
                    }
                }
                $lines = substr($lines, 0, strlen($lines)-1);
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
        array_push($form["fields"],array("id" => "post_id" , "label" => __("Post Id", "gravityforms")));
        array_push($form["fields"],array("id" => "user_agent" , "label" => __("User Agent", "gravityforms")));
        array_push($form["fields"],array("id" => "ip" , "label" => __("User IP", "gravityforms")));
        $form = self::get_entry_meta($form);
        $form = apply_filters('gform_export_fields', $form);
        return $form;
    }

    private function cleanup(&$forms){
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

                    for($i=1, $count = sizeof($field["choices"]); $i<=$count; $i++){
                        if(!RGForms::get("enableChoiceValue", $field))
                            $field["choices"][$i-1]["value"] = $field["choices"][$i-1]["text"];

                        if($input_type == "checkbox")
                            $field["inputs"][] = array("id" => $field["id"] . "." . $i, "label" => $field["choices"][$i-1]["text"]);
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
}
?>