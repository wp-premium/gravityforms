<?php
class GFCommon{

    public static $version = "1.6.12";
    public static $tab_index = 1;

    public static function get_selection_fields($form, $selected_field_id){

        $str = "";
        foreach($form["fields"] as $field){
            $input_type = RGFormsModel::get_input_type($field);
            $field_label = RGFormsModel::get_label($field);
            if($input_type == "checkbox" || $input_type == "radio" || $input_type == "select"){
                $selected = $field["id"] == $selected_field_id ? "selected='selected'" : "";
                $str .= "<option value='" . $field["id"] . "' " . $selected . ">" . $field_label . "</option>";
            }
        }
        return $str;
    }

    public static function is_numeric($value, $number_format=""){

        switch($number_format){
            case "decimal_dot" :
                return preg_match("/^(-?[0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]+)?)$/", $value);
            break;

            case "decimal_comma" :
                return preg_match("/^(-?[0-9]{1,3}(?:\.?[0-9]{3})*(?:,[0-9]+)?)$/", $value);
            break;

            default :
                return preg_match("/^(-?[0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]{2})?)$/", $value) || preg_match("/^(-?[0-9]{1,3}(?:\.?[0-9]{3})*(?:,[0-9]{2})?)$/", $value);

        }
    }

    public static function trim_all($text){
        $text = trim($text);
        do{
            $prev_text = $text;
            $text = str_replace("  ", " ", $text);
        }
        while($text != $prev_text);

        return $text;
    }

    public static function format_number($number, $number_format){
        if(!is_numeric($number))
            return $number;

        //replacing commas with dots and dots with commas
        if($number_format == "decimal_comma"){
            $number = str_replace(",", "|", $number);
            $number = str_replace(".", ",", $number);
            $number = str_replace("|", ".", $number);
        }

        return $number;
    }

    public static function recursive_add_index_file($dir) {
        if(!is_dir($dir))
            return;

        if(!($dp = opendir($dir)))
            return;

        //ignores all errors
        set_error_handler(create_function("", "return 0;"), E_ALL);

        //creates an empty index.html file
        if($f = fopen($dir . "/index.html", 'w'))
            fclose($f);

        //restores error handler
        restore_error_handler();

        while((false !== $file = readdir($dp))){
           if(is_dir("$dir/$file") && $file != '.' && $file !='..')
               self::recursive_add_index_file("$dir/$file");
        }

        closedir($dp);
    }

    public static function clean_number($number, $number_format=""){
        if(rgblank($number))
            return $number;

        $decimal_char = "";
        if($number_format == "decimal_dot")
            $decimal_char = ".";
        else if($number_format == "decimal_comma")
            $decimal_char = ",";

        $float_number = "";
        $clean_number = "";
        $is_negative = false;

        //Removing all non-numeric characters
        $array = str_split($number);
        foreach($array as $char){
            if (($char >= '0' && $char <= '9') || $char=="," || $char==".")
                $clean_number .= $char;
            else if($char == '-')
                $is_negative = true;
        }

        //Removing thousand separators but keeping decimal point
        $array = str_split($clean_number);
        for($i=0, $count = sizeof($array); $i<$count; $i++)
        {
            $char = $array[$i];
            if ($char >= '0' && $char <= '9')
                $float_number .= $char;
            else if(empty($decimal_char) && ($char == "." || $char == ",") && strlen($clean_number) - $i <= 3)
                $float_number .= ".";
            else if($decimal_char == $char)
                $float_number .= ".";
        }

        if($is_negative)
            $float_number = "-" . $float_number;

        return $float_number;

    }

    public static function json_encode($value){
        return json_encode($value);
    }

    public static function json_decode($str, $is_assoc=true){
        return json_decode($str, $is_assoc);
    }

    //Returns the url of the plugin's root folder
    public static function get_base_url(){
        $folder = basename(dirname(__FILE__));
        return plugins_url($folder);
    }

    //Returns the physical path of the plugin's root folder
    public static function get_base_path(){
        $folder = basename(dirname(__FILE__));
        return WP_PLUGIN_DIR . "/" . $folder;
    }

    public static function get_email_fields($form){
        $fields = array();
        foreach($form["fields"] as $field){
            if(RGForms::get("type", $field) == "email" || RGForms::get("inputType", $field) == "email")
                $fields[] = $field;
        }

        return $fields;
    }

    public static function truncate_middle($text, $max_length){
        if(strlen($text) <= $max_length)
            return $text;

        $middle = intval($max_length / 2);
        return substr($text, 0, $middle) . "..." . substr($text, strlen($text) - $middle, $middle);
    }

    public static function is_invalid_or_empty_email($email){
        return empty($email) || !self::is_valid_email($email);
    }

    public static function is_valid_url($url){
        return preg_match('!^(http|https)://([\w-]+\.?)+[\w-]+(:\d+)?(/[\w- ./?~%&=+\']*)?$!', $url);
    }

    public static function is_valid_email($email){
        return preg_match('/^(([a-zA-Z0-9_.\-+!#$&\'*+=?^`{|}~])+\@((([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+|localhost) *,? *)+$/', $email);
    }

    public static function get_label($field, $input_id = 0, $input_only = false){
        return RGFormsModel::get_label($field, $input_id, $input_only);
    }

    public static function get_input($field, $id){
       return RGFormsModel::get_input($field, $id);
    }

    public static function insert_post_content_variables($fields, $element_id, $callback, $max_label_size=25){
        self::insert_variables($fields, $element_id, true, "", "InsertPostContentVariable('{$element_id}', '{$callback}');", $max_label_size, null, "", "gform_content_template_merge_tags");
        ?>
        &nbsp;&nbsp;
        <select id="<?php echo $element_id?>_image_size_select" onchange="InsertPostImageVariable('<?php echo $element_id ?>', '<?php echo $element_id ?>'); SetCustomFieldTemplate();" style="display:none;">
            <option value=""><?php _e("Select image size", "gravityforms") ?></option>
            <option value="thumbnail"><?php _e("Thumbnail") ?></option>
            <option value="thumbnail:left"><?php _e("Thumbnail - Left Aligned") ?></option>
            <option value="thumbnail:center"><?php _e("Thumbnail - Centered") ?></option>
            <option value="thumbnail:right"><?php _e("Thumbnail - Right Aligned", "gravityforms") ?></option>

            <option value="medium"><?php _e("Medium") ?></option>
            <option value="medium:left"><?php _e("Medium - Left Aligned") ?></option>
            <option value="medium:center"><?php _e("Medium - Centered") ?></option>
            <option value="medium:right"><?php _e("Medium - Right Aligned", "gravityforms") ?></option>

            <option value="large"><?php _e("Large") ?></option>
            <option value="large:left"><?php _e("Large - Left Aligned") ?></option>
            <option value="large:center"><?php _e("Large - Centered") ?></option>
            <option value="large:right"><?php _e("Large - Right Aligned", "gravityforms") ?></option>

            <option value="full"><?php _e("Full Size") ?></option>
            <option value="full:left"><?php _e("Full Size - Left Aligned") ?></option>
            <option value="full:center"><?php _e("Full Size - Centered") ?></option>
            <option value="full:right"><?php _e("Full Size - Right Aligned", "gravityforms") ?></option>
        </select>
        <?php
    }

    public static function insert_variables($fields, $element_id, $hide_all_fields=false, $callback="", $onchange="", $max_label_size=40, $exclude = null, $args="", $class_name=""){
        if($fields == null)
            $fields = array();

        if($exclude == null)
            $exclude = array();

        $exclude = apply_filters("gform_merge_tag_list_exclude", $exclude, $element_id, $fields);

        $onchange = empty($onchange) ? "InsertVariable('{$element_id}', '{$callback}');" : $onchange;
        $class = trim($class_name . " gform_merge_tags");
        ?>
        <select id="<?php echo $element_id?>_variable_select" onchange="<?php echo $onchange ?>" class="<?php echo esc_attr($class)?>">
            <option value=''><?php _e("Insert Merge Tag", "gravityforms"); ?></option>
            <?php
            if(!$hide_all_fields){
                ?>
                <option value='{all_fields}'><?php _e("All Submitted Fields", "gravityforms"); ?></option>
                <?php
            }
            $required_fields = array();
            $optional_fields = array();
            $pricing_fields = array();

            foreach($fields as $field){
                if(rgget("displayOnly", $field))
                    continue;

                $input_type = RGFormsModel::get_input_type($field);

                //skip field types that should be excluded
                if(is_array($exclude) && in_array($input_type, $exclude))
                    continue;

                if($field["isRequired"]){

                    switch($input_type){
                        case "name" :
                            if($field["nameFormat"] == "extended"){
                                $prefix = GFCommon::get_input($field, $field["id"] + 0.2);
                                $suffix = GFCommon::get_input($field, $field["id"] + 0.8);
                                $optional_field = $field;
                                $optional_field["inputs"] = array($prefix, $suffix);

                                //Add optional name fields to the optional list
                                $optional_fields[] = $optional_field;

                                //Remove optional name field from required list
                                unset($field["inputs"][0]);
                                unset($field["inputs"][3]);
                            }

                            $required_fields[] = $field;
                        break;


                        default:
                            $required_fields[] = $field;
                    }
                }
                else{
                   $optional_fields[] = $field;
                }

                if(self::is_pricing_field($field["type"])){
                    $pricing_fields[] = $field;
                }

            }

            if(!empty($required_fields)){
                ?>
                <optgroup label="<?php _e("Required form fields", "gravityforms"); ?>">
                <?php
                foreach($required_fields as $field){
                    self::insert_field_variable($field, $max_label_size, $args);
                }
                ?>
                </optgroup>
                <?php
            }

            if(!empty($optional_fields)){
                ?>
                <optgroup label="<?php _e("Optional form fields", "gravityforms"); ?>">
                <?php
                foreach($optional_fields as $field){
                    self::insert_field_variable($field, $max_label_size, $args);
                }
                ?>
                </optgroup>
                <?php
            }

            if(!empty($pricing_fields)){
                ?>
                <optgroup label="<?php _e("Pricing form fields", "gravityforms"); ?>">
                    <?php
                    if(!$hide_all_fields){
                        ?>
                        <option value='{pricing_fields}'><?php _e("All Pricing Fields", "gravityforms"); ?></option>
                        <?php
                    }?>

                    <?php
                    foreach($pricing_fields as $field){
                        self::insert_field_variable($field, $max_label_size, $args);
                    }
                    ?>
                </optgroup>
                <?php
            }

            ?>
            <optgroup label="<?php _e("Other", "gravityforms"); ?>">
                <option value='{ip}'><?php _e("Client IP Address", "gravityforms"); ?></option>
                <option value='{date_mdy}'><?php _e("Date", "gravityforms"); ?> (mm/dd/yyyy)</option>
                <option value='{date_dmy}'><?php _e("Date", "gravityforms"); ?> (dd/mm/yyyy)</option>
                <option value='{embed_post:ID}'><?php _e("Embed Post/Page Id", "gravityforms"); ?></option>
                <option value='{embed_post:post_title}'><?php _e("Embed Post/Page Title", "gravityforms"); ?></option>
                <option value='{embed_url}'><?php _e("Embed URL", "gravityforms"); ?></option>
                <option value='{entry_id}'><?php _e("Entry Id", "gravityforms"); ?></option>
                <option value='{entry_url}'><?php _e("Entry URL", "gravityforms"); ?></option>
                <option value='{form_id}'><?php _e("Form Id", "gravityforms"); ?></option>
                <option value='{form_title}'><?php _e("Form Title", "gravityforms"); ?></option>
                <option value='{user_agent}'><?php _e("HTTP User Agent", "gravityforms"); ?></option>

                <?php if(self::has_post_field($fields)){ ?>
                    <option value='{post_id}'><?php _e("Post Id", "gravityforms"); ?></option>
                    <option value='{post_edit_url}'><?php _e("Post Edit URL", "gravityforms"); ?></option>
                <?php } ?>

                <option value='{user:display_name}'><?php _e("User Display Name", "gravityforms"); ?></option>
                <option value='{user:user_email}'><?php _e("User Email", "gravityforms"); ?></option>
                <option value='{user:user_login}'><?php _e("User Login", "gravityforms"); ?></option>
            </optgroup>

            <?php
            $custom_merge_tags = apply_filters('gform_custom_merge_tags', array(), rgars($fields, '0/formId'), $fields, $element_id);

            if(is_array($custom_merge_tags) && !empty($custom_merge_tags)) { ?>

                <optgroup label="<?php _e("Custom", "gravityforms"); ?>">

                <?php foreach($custom_merge_tags as $custom_merge_tag) { ?>

                    <option value='<?php echo rgar($custom_merge_tag, 'tag'); ?>'><?php echo rgar($custom_merge_tag, 'label'); ?></option>

                <?php } ?>

                </optgroup>

            <?php } ?>

        </select>
        <?php
    }

    public static function insert_field_variable($field, $max_label_size=40, $args=""){

        $tag_args = RGFormsModel::get_input_type($field) == "list" ? ":{$args}" : ""; //args currently only supported by list field

        if(is_array($field["inputs"]))
        {
            if(RGFormsModel::get_input_type($field) == "checkbox"){
                ?>
                <option value='<?php echo "{" . esc_html(GFCommon::get_label($field, $field["id"])) . ":" . $field["id"] . "{$tag_args}}" ?>'><?php echo esc_html(GFCommon::get_label($field, $field["id"])) ?></option>
                <?php
            }

            foreach($field["inputs"] as $input){
                ?>
                <option value='<?php echo "{" . esc_html(GFCommon::get_label($field, $input["id"])) . ":" . $input["id"] . "{$tag_args}}" ?>'><?php echo esc_html(GFCommon::get_label($field, $input["id"])) ?></option>
                <?php
            }
        }
        else{
            ?>
            <option value='<?php echo "{" . esc_html(GFCommon::get_label($field)) . ":" . $field["id"] . "{$tag_args}}" ?>'><?php echo esc_html(GFCommon::get_label($field)) ?></option>
            <?php
        }
    }

    public static function insert_calculation_variables($fields, $element_id, $onchange = '', $callback = '', $max_label_size=40) {

        if($fields == null)
            $fields = array();

        $onchange = empty($onchange) ? "InsertVariable('{$element_id}', '{$callback}');" : $onchange;
        $class = 'gform_merge_tags';
        ?>

        <select id="<?php echo $element_id?>_variable_select" onchange="<?php echo $onchange ?>" class="<?php echo esc_attr($class)?>">
            <option value=''><?php _e("Insert Merge Tag", "gravityforms"); ?></option>
            <optgroup label="<?php _e("Allowable form fields", "gravityforms"); ?>">

                <?php
                foreach($fields as $field) {

                    if(!self::is_valid_for_calcuation($field))
                        continue;

                    if(RGFormsModel::get_input_type($field) == 'checkbox') {
                        foreach($field["inputs"] as $input){
                            ?>
                            <option value='<?php echo "{" . esc_html(GFCommon::get_label($field, $input["id"])) . ":" . $input["id"] . "}" ?>'><?php echo esc_html(GFCommon::get_label($field, $input["id"])) ?></option>
                            <?php
                        }
                    } else {
                        self::insert_field_variable($field, $max_label_size);
                    }

                }
                ?>

            </optgroup>

            <?php

            $custom_merge_tags = apply_filters('gform_custom_merge_tags', array(), rgars($fields, '0/formId'), $fields, $element_id);

            if(is_array($custom_merge_tags) && !empty($custom_merge_tags)) { ?>

                <optgroup label="<?php _e("Custom", "gravityforms"); ?>">

                <?php foreach($custom_merge_tags as $custom_merge_tag) { ?>

                    <option value='<?php echo rgar($custom_merge_tag, 'tag'); ?>'><?php echo rgar($custom_merge_tag, 'label'); ?></option>

                <?php } ?>

                </optgroup>

            <?php } ?>

        </select>

        <?php
    }

    private static function get_post_image_variable($media_id, $arg1, $arg2, $is_url = false){

        if($is_url){
            $image = wp_get_attachment_image_src($media_id, $arg1);
            if ( $image )
                list($src, $width, $height) = $image;

            return $src;
        }

        switch($arg1){
            case "title" :
                $media = get_post($media_id);
                return $media->post_title;
            case "caption" :
                $media = get_post($media_id);
                return $media->post_excerpt;
            case "description" :
                $media = get_post($media_id);
                return $media->post_content;

            default :

                $img = wp_get_attachment_image($media_id, $arg1, false, array("class" => "size-{$arg1} align{$arg2} wp-image-{$media_id}"));
                return $img;
        }
    }

    public static function replace_variables_post_image($text, $post_images, $lead){

        preg_match_all('/{[^{]*?:(\d+)(:([^:]*?))?(:([^:]*?))?(:url)?}/mi', $text, $matches, PREG_SET_ORDER);
        if(is_array($matches))
        {
            foreach($matches as $match){
                $input_id = $match[1];

                //ignore fields that are not post images
                if(!isset($post_images[$input_id]))
                    continue;

                //Reading alignment and "url" parameters.
                //Format could be {image:5:medium:left:url} or {image:5:medium:url}
                $size_meta = empty($match[3]) ? "full" : $match[3];
                $align = empty($match[5]) ? "none" : $match[5];
                if($align == "url"){
                    $align = "none";
                    $is_url = true;
                }
                else{
                    $is_url = rgar($match,6) == ":url";
                }

                $media_id = $post_images[$input_id];
                $value = is_wp_error($media_id) ? "" : self::get_post_image_variable($media_id, $size_meta, $align, $is_url);

                $text = str_replace($match[0], $value , $text);
            }
        }

        return $text;
    }

    public static function implode_non_blank($separator, $array){

        if(!is_array($array))
            return "";

        $ary = array();
        foreach($array as $item){
            if(!rgblank($item))
                $ary[] = $item;
        }
        return implode($separator, $ary);
    }

    private static function format_variable_value($value, $url_encode, $esc_html, $format){
        if($esc_html)
            $value = esc_html($value);

        if($format == "html")
            $value = nl2br($value);

        if($url_encode)
            $value = urlencode($value);

        return $value;
    }

    public static function replace_variables($text, $form, $lead, $url_encode = false, $esc_html=true, $nl2br = true, $format="html"){
        $text = $nl2br ? nl2br($text) : $text;

        //Replacing field variables
        preg_match_all('/{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/mi', $text, $matches, PREG_SET_ORDER);
        if(is_array($matches))
        {
            foreach($matches as $match){
                $input_id = $match[1];

                $field = RGFormsModel::get_field($form,$input_id);

                $value = RGFormsModel::get_lead_field_value($lead, $field);
                $raw_value = $value;

                if(is_array($value))
                    $value = rgar($value, $input_id);

                $value = self::format_variable_value($value, $url_encode, $esc_html, $format);

                switch(RGFormsModel::get_input_type($field)){

                    case "fileupload" :
                        $value = str_replace(" ", "%20", $value);
                    break;

                    case "post_image" :
                        list($url, $title, $caption, $description) = explode("|:|", $value);
                        $value = str_replace(" ", "%20", $url);
                    break;

                    case "checkbox" :
                    case "select" :
                    case "radio" :

                        $use_value = rgar($match,4) == "value";
                        $use_price = in_array(rgar($match,4), array("price", "currency"));
                        $format_currency = rgar($match,4) == "currency";

                        if(is_array($raw_value) && (string)intval($input_id) != $input_id){
                            $items = array($input_id => $value); //float input Ids. (i.e. 4.1 ). Used when targeting specific checkbox items
                        }
                        else if(is_array($raw_value)){
                            $items = $raw_value;
                        }
                        else{
                            $items = array($input_id => $raw_value);
                        }

                        $ary = array();

                        foreach($items as $input_id => $item){
                            if($use_value){
                                list($val, $price) = rgexplode("|", $item, 2);
                            }
                            else if($use_price){
                                list($name, $val) = rgexplode("|", $item, 2);
                                if($format_currency)
                                    $val = GFCommon::to_money($val, rgar($lead, "currency"));
                            }
                            else if($field["type"] == "post_category"){
                                $use_id = strtolower(rgar($match,4)) == "id";
                                $item_value = self::format_post_category($item, $use_id);

                                $val = RGFormsModel::is_field_hidden($form, $field, array(), $lead) ? "" : $item_value;
                            }
                            else{
                                $val = RGFormsModel::is_field_hidden($form, $field, array(), $lead) ? "" : RGFormsModel::get_choice_text($field, $raw_value, $input_id);
                            }

                            $ary[] = self::format_variable_value($val, $url_encode, $esc_html, $format);
                        }

                        $value = self::implode_non_blank(", ", $ary);

                    break;

                    case "multiselect" :
                        if($field["type"] == "post_category"){
                            $use_id = strtolower(rgar($match,4)) == "id";
                            $items = explode(",", $value);

                            if(is_array($items)){
                                $cats = array();
                                foreach($items as $item){
                                    $cat = self::format_post_category($item, $use_id);
                                    $cats[] = self::format_variable_value($cat, $url_encode, $esc_html, $format);
                                }
                                $value = self::implode_non_blank(", ", $cats);
                            }
                        }

                    break;

                    case "date" :
                        $value = self::date_display($value, rgar($field,"dateFormat"));
                    break;

                    case "total" :
                        $format_numeric = rgar($match,4) == "price";

                        $value = $format_numeric ? GFCommon::to_number($value) : GFCommon::to_money($value);

                        $value = self::format_variable_value($value, $url_encode, $esc_html, $format);
                    break;

                    case "post_category" :
                        $use_id = strtolower(rgar($match,4)) == "id";
                        $value = self::format_post_category($value, $use_id);
                        $value = self::format_variable_value($value, $url_encode, $esc_html, $format);
                    break;

                    case "list" :
                        $output_format = in_array(rgar($match,4), array("text", "html", "url")) ? rgar($match,4) : $format;
                        $value = self::get_lead_field_display($field, $raw_value, $lead["currency"], true, $output_format);
                    break;
                }

                if(rgar($match,4) == "label"){
                    $value = empty($value) ? "" : rgar($field, "label");
                }
                else if(rgar($match,4) == "qty" && $field["type"] == "product"){
                    //getting quantity associated with product field
                    $products = self::get_product_fields($form, $lead, false, false);
                    $value = 0;
                    foreach($products["products"] as $product_id => $product)
                    {
                        if($product_id == $field["id"])
                            $value = $product["quantity"];
                    }
                }

                //filter can change merge code variable
                $value = apply_filters("gform_merge_tag_filter", $value, $input_id, rgar($match,4), $field, $raw_value);
                if($value === false)
                    $value = "";

                $text = str_replace($match[0], $value , $text);
            }
        }

        //replacing global variables
        //form title
        $text = str_replace("{form_title}", $url_encode ? urlencode($form["title"]) : $form["title"], $text);

        $matches = array();
        preg_match_all("/{all_fields(:(.*?))?}/", $text, $matches, PREG_SET_ORDER);
        foreach($matches as $match){
            $options = explode(",", rgar($match,2));
            $use_value = in_array("value", $options);
            $display_empty = in_array("empty", $options);
            $use_admin_label = in_array("admin", $options);

            //all submitted fields using text
            $text = str_replace($match[0], self::get_submitted_fields($form, $lead, $display_empty, !$use_value, $format, $use_admin_label, "all_fields", rgar($match,2)), $text);
        }

        //all submitted fields including empty fields
        $text = str_replace("{all_fields_display_empty}", self::get_submitted_fields($form, $lead, true, true, $format, false, "all_fields_display_empty"), $text);

        //pricing fields
        $text = str_replace("{pricing_fields}", self::get_submitted_pricing_fields($form, $lead, $format), $text);

        //form id
        $text = str_replace("{form_id}", $url_encode ? urlencode($form["id"]) : $form["id"], $text);

        //entry id
        $text = str_replace("{entry_id}", $url_encode ? urlencode(rgar($lead, "id")) : rgar($lead, "id"), $text);

        //entry url
        $entry_url = get_bloginfo("wpurl") . "/wp-admin/admin.php?page=gf_entries&view=entry&id=" . $form["id"] . "&lid=" . rgar($lead,"id");
        $text = str_replace("{entry_url}", $url_encode ? urlencode($entry_url) : $entry_url, $text);

        //post id
        $text = str_replace("{post_id}", $url_encode ? urlencode($lead["post_id"]) : $lead["post_id"], $text);

        //admin email
        $wp_email = get_bloginfo("admin_email");
        $text = str_replace("{admin_email}", $url_encode ? urlencode($wp_email) : $wp_email, $text);

        //post edit url
        $post_url = get_bloginfo("wpurl") . "/wp-admin/post.php?action=edit&post=" . $lead["post_id"];
        $text = str_replace("{post_edit_url}", $url_encode ? urlencode($post_url) : $post_url, $text);

        $text = self::replace_variables_prepopulate($text, $url_encode);

        // hook allows for custom merge tags
        $text = apply_filters('gform_replace_merge_tags', $text, $form, $lead, $url_encode, $esc_html, $nl2br, $format);

        return $text;
    }

    public static function format_post_category($value, $use_id){

        list($item_value, $item_id) = rgexplode(":", $value, 2);

        if($use_id && !empty($item_id))
            $item_value = $item_id;

        return $item_value;
    }

    public static function get_embed_post(){
        global $embed_post, $post, $wp_query;

        if($embed_post){
            return $embed_post;
        }

        if(!rgempty("gform_embed_post")){
            $post_id = absint(rgpost("gform_embed_post"));
            $embed_post = get_postdata($post_id);
        }
        else if($wp_query->is_in_loop){
            $embed_post = $post;
        }
        else{
            $embed_post = array();
        }
    }

    public static function replace_variables_prepopulate($text, $url_encode=false){

        //embed url
        $text = str_replace("{embed_url}", $url_encode ? urlencode(RGFormsModel::get_current_page_url()) : RGFormsModel::get_current_page_url(), $text);

        $local_timestamp = self::get_local_timestamp(time());

        //date (mm/dd/yyyy)
        $local_date_mdy = date_i18n("m/d/Y", $local_timestamp, true);
        $text = str_replace("{date_mdy}", $url_encode ? urlencode($local_date_mdy) : $local_date_mdy, $text);

        //date (dd/mm/yyyy)
        $local_date_dmy = date_i18n("d/m/Y", $local_timestamp, true);
        $text = str_replace("{date_dmy}", $url_encode ? urlencode($local_date_dmy) : $local_date_dmy, $text);

        //ip
        $text = str_replace("{ip}", $url_encode ? urlencode($_SERVER['REMOTE_ADDR']) : $_SERVER['REMOTE_ADDR'], $text);

        global $post;
        $post_array = self::object_to_array($post);
        preg_match_all("/\{embed_post:(.*?)\}/", $text, $matches, PREG_SET_ORDER);
        foreach($matches as $match){
            $full_tag = $match[0];
            $property = $match[1];
            $text = str_replace($full_tag, $url_encode ? urlencode($post_array[$property]) : $post_array[$property], $text);
        }

        //embed post custom fields
        preg_match_all("/\{custom_field:(.*?)\}/", $text, $matches, PREG_SET_ORDER);
        foreach($matches as $match){

            $full_tag = $match[0];
            $custom_field_name = $match[1];
            $custom_field_value = !empty($post_array["ID"]) ? get_post_meta($post_array["ID"], $custom_field_name, true) : "";
            $text = str_replace($full_tag, $url_encode ? urlencode($custom_field_value) : $custom_field_value, $text);
        }

        //user agent
        $text = str_replace("{user_agent}", $url_encode ? urlencode(RGForms::get("HTTP_USER_AGENT", $_SERVER)) : RGForms::get("HTTP_USER_AGENT", $_SERVER), $text);

        //referrer
        $text = str_replace("{referer}", $url_encode ? urlencode(RGForms::get("HTTP_REFERER", $_SERVER)) : RGForms::get("HTTP_REFERER", $_SERVER), $text);

        //logged in user info
        global $userdata, $wp_version, $current_user;
        $user_array = self::object_to_array($userdata);

        preg_match_all("/\{user:(.*?)\}/", $text, $matches, PREG_SET_ORDER);
        foreach($matches as $match){
            $full_tag = $match[0];
            $property = $match[1];

            $value = version_compare($wp_version, '3.3', '>=') ? $current_user->get($property) : $user_array[$property];
            $value = $url_encode ? urlencode($value) : $value;

            $text = str_replace($full_tag, $value, $text);
        }

        return $text;
    }

    public static function object_to_array($object){
        $array=array();
        if(!empty($object)){
            foreach($object as $member=>$data)
                $array[$member]=$data;
        }
        return $array;
    }

    public static function is_empty_array($val){
        if(!is_array($val))
            $val = array($val);

        $ary = array_values($val);
        foreach($ary as $item){
            if(!rgblank($item))
                return false;
        }
        return true;
    }

    public static function get_submitted_fields($form, $lead, $display_empty=false, $use_text=false, $format="html", $use_admin_label=false, $merge_tag="", $options=""){

        $field_data = "";
        if($format == "html"){
            $field_data = '<table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#EAEAEA"><tr><td>
                            <table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">';
        }

        $options_array = explode(",", $options);
        $no_admin = in_array("noadmin", $options_array);
        $no_hidden = in_array("nohidden", $options_array);
        $has_product_fields = false;
        foreach($form["fields"] as $field){
            $field_value = "";

            $field_label = $use_admin_label && !rgempty("adminLabel", $field) ? rgar($field, "adminLabel") : esc_html(GFCommon::get_label($field));

            switch($field["type"]){
                case "captcha" :
                    break;

                case "section" :
                    if(!GFCommon::is_section_empty($field, $form, $lead) || $display_empty){

                        switch($format){
                            case "text" :
                                $field_value = "--------------------------------\n{$field_label}\n\n";
                            break;

                            default:
                                $field_value = sprintf('<tr>
                                                            <td colspan="2" style="font-size:14px; font-weight:bold; background-color:#EEE; border-bottom:1px solid #DFDFDF; padding:7px 7px">%s</td>
                                                       </tr>', $field_label);
                            break;
                        }
                    }

                    $field_value = apply_filters("gform_merge_tag_filter", $field_value, $merge_tag, $options, $field, $field_label);

                    $field_data .= $field_value;

                    break;
                case "password" :
                    //ignore password fields
                break;

                default :

                    //ignore product fields as they will be grouped together at the end of the grid
                    if(self::is_product_field($field["type"])){
                        $has_product_fields = true;
                        continue;
                    }
                    else if(RGFormsModel::is_field_hidden($form, $field, array(), $lead)){
                        //ignore fields hidden by conditional logic
                        continue;
                    }

                    $raw_field_value = RGFormsModel::get_lead_field_value($lead, $field);
                    $field_value = GFCommon::get_lead_field_display($field, $raw_field_value, rgar($lead,"currency"), $use_text, $format, "email");

                    $display_field = true;
                    //depending on parameters, don't display adminOnly or hidden fields
                    if($no_admin && rgar($field, "adminOnly"))
                        $display_field = false;
                    else if($no_hidden && RGFormsModel::get_input_type($field) == "hidden")
                        $display_field = false;

                    //if field is not supposed to be displayed, pass false to filter. otherwise, pass field's value
                    if(!$display_field)
                        $field_value = false;

                    $field_value = apply_filters("gform_merge_tag_filter", $field_value, $merge_tag, $options, $field, $raw_field_value);

                    if($field_value === false)
                        continue;

                    if( !empty($field_value) || strlen($field_value) > 0 || $display_empty){
                        switch($format){
                            case "text" :
                                $field_data .= "{$field_label}: {$field_value}\n\n";
                            break;

                            default:

                                $field_data .= sprintf('<tr bgcolor="#EAF2FA">
                                                            <td colspan="2">
                                                                <font style="font-family: sans-serif; font-size:12px;"><strong>%s</strong></font>
                                                            </td>
                                                       </tr>
                                                       <tr bgcolor="#FFFFFF">
                                                            <td width="20">&nbsp;</td>
                                                            <td>
                                                                <font style="font-family: sans-serif; font-size:12px;">%s</font>
                                                            </td>
                                                       </tr>', $field_label, empty($field_value) && strlen($field_value) == 0 ? "&nbsp;" : $field_value);
                            break;
                        }
                    }
            }
        }

        if($has_product_fields)
            $field_data .= self::get_submitted_pricing_fields($form, $lead, $format, $use_text, $use_admin_label);

        if($format == "html"){
            $field_data .='</table>
                        </td>
                   </tr>
               </table>';
        }

        return $field_data;
    }

    public static function get_submitted_pricing_fields($form, $lead, $format, $use_text=true, $use_admin_label=false){
        $form_id = $form["id"];
        $order_label = apply_filters("gform_order_label_{$form["id"]}", apply_filters("gform_order_label", __("Order", "gravityforms"), $form["id"]), $form["id"]);
        $products = GFCommon::get_product_fields($form, $lead, $use_text, $use_admin_label);
        $total = 0;
        $field_data = "";

        switch($format){
            case "text" :
                if(!empty($products["products"])){
                    $field_data = "--------------------------------\n" . $order_label . "\n\n";
                    foreach($products["products"] as $product){
                        $product_name = $product["quantity"] . " " . $product["name"];
                        $price = self::to_number($product["price"]);
                        if(!empty($product["options"])){
                            $product_name .= " (";
                            $options = array();
                            foreach($product["options"] as $option){
                                $price += self::to_number($option["price"]);
                                $options[] = $option["option_name"];
                            }
                            $product_name .= implode(", ", $options) . ")";
                        }
                        $subtotal = floatval($product["quantity"]) * $price;
                        $total += $subtotal;

                        $field_data .= "{$product_name}: " . self::to_money($subtotal, $lead["currency"]) . "\n\n";
                    }
                    $total += floatval($products["shipping"]["price"]);

                    if(!empty($products["shipping"]["name"]))
                        $field_data .= $products["shipping"]["name"] . ": " . self::to_money($products["shipping"]["price"], $lead["currency"]) . "\n\n";

                    $field_data .= __("Total", "gravityforms") . ": " . self::to_money($total, $lead["currency"]) . "\n\n";
                }
            break;


            default :
                if(!empty($products["products"])){
                    $field_data ='<tr bgcolor="#EAF2FA">
                            <td colspan="2">
                                <font style="font-family: sans-serif; font-size:12px;"><strong>' . $order_label . '</strong></font>
                            </td>
                       </tr>
                       <tr bgcolor="#FFFFFF">
                            <td width="20">&nbsp;</td>
                            <td>
                                <table cellspacing="0" width="97%" style="border-left:1px solid #DFDFDF; border-top:1px solid #DFDFDF">
                                <thead>
                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-family: sans-serif; font-size:12px; text-align:left">' . apply_filters("gform_product_{$form_id}", apply_filters("gform_product", __("Product", "gravityforms"), $form_id), $form_id) . '</th>
                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:50px; font-family: sans-serif; font-size:12px; text-align:center">' . apply_filters("gform_product_qty_{$form_id}", apply_filters("gform_product_qty", __("Qty", "gravityforms"), $form_id), $form_id) . '</th>
                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:12px; text-align:left">' . apply_filters("gform_product_unitprice_{$form_id}", apply_filters("gform_product_unitprice", __("Unit Price", "gravityforms"), $form_id), $form_id) . '</th>
                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:12px; text-align:left">' . apply_filters("gform_product_price_{$form_id}", apply_filters("gform_product_price", __("Price", "gravityforms"), $form_id), $form_id) . '</th>
                                </thead>
                                <tbody>';


                                foreach($products["products"] as $product){

                                    $field_data .= '<tr>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-family: sans-serif; font-size:11px;" >
                                                            <strong style="color:#BF461E; font-size:12px; margin-bottom:5px">' . esc_html($product["name"]) .'</strong>
                                                            <ul style="margin:0">';

                                                                $price = self::to_number($product["price"]);
                                                                if(is_array(rgar($product,"options"))){
                                                                    foreach($product["options"] as $option){
                                                                        $price += self::to_number($option["price"]);
                                                                        $field_data .= '<li style="padding:4px 0 4px 0">' . $option["option_label"] .'</li>';
                                                                    }
                                                                }
                                                                $subtotal = floatval($product["quantity"]) * $price;
                                                                $total += $subtotal;

                                                                $field_data .='</ul>
                                                        </td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:center; width:50px; font-family: sans-serif; font-size:11px;" >' . $product["quantity"] .'</td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:11px;" >' . self::to_money($price, $lead["currency"]) .'</td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:11px;" >' . self::to_money($subtotal, $lead["currency"]) .'</td>
                                                    </tr>';
                                }
                                $total += floatval($products["shipping"]["price"]);
                                $field_data .= '</tbody>
                                <tfoot>';

                                if(!empty($products["shipping"]["name"])){
                                    $field_data .= '
                                    <tr>
                                        <td colspan="2" rowspan="2" style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-size:11px;">&nbsp;</td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:right; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . $products["shipping"]["name"] . '</strong></td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">'. self::to_money($products["shipping"]["price"], $lead["currency"]) . '</strong></td>
                                    </tr>
                                    ';
                                }

                                $field_data .= '
                                    <tr>';

                                if(empty($products["shipping"]["name"])){
                                    $field_data .= '
                                        <td colspan="2" style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-size:11px;">&nbsp;</td>';
                                }

                                $field_data .= '
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:right; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . __("Total:", "gravityforms") . '</strong></td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">'. self::to_money($total, $lead["currency"]) . '</strong></td>
                                    </tr>
                                </tfoot>
                               </table>
                            </td>
                        </tr>';
                }
            break;
        }

        return $field_data;
    }

    public static function send_user_notification($form, $lead, $override_options = false){
        $form_id = $form["id"];

        if(!isset($form["autoResponder"]))
            return;

        //handling autoresponder email
        $to_field = isset($form["autoResponder"]["toField"]) ? rgget($form["autoResponder"]["toField"], $lead) : "";
        $to = apply_filters("gform_autoresponder_email_{$form_id}", apply_filters("gform_autoresponder_email", $to_field, $form), $form);
        $subject = GFCommon::replace_variables(rgget("subject", $form["autoResponder"]), $form, $lead, false, false);

        $message_format = apply_filters("gform_notification_format_{$form["id"]}", apply_filters("gform_notification_format", "html", "user", $form, $lead), "user", $form, $lead);
        $message = GFCommon::replace_variables(rgget("message", $form["autoResponder"]), $form, $lead, false, false, !rgget("disableAutoformat", $form["autoResponder"]), $message_format);
        $message = do_shortcode($message);

        //Running trough variable replacement
        $to = GFCommon::replace_variables($to, $form, $lead, false, false);
        $from = GFCommon::replace_variables(rgget("from", $form["autoResponder"]), $form, $lead, false, false);
        $bcc = GFCommon::replace_variables(rgget("bcc", $form["autoResponder"]), $form, $lead, false, false);
        $reply_to = GFCommon::replace_variables(rgget("replyTo", $form["autoResponder"]), $form, $lead, false, false);
        $from_name = GFCommon::replace_variables(rgget("fromName", $form["autoResponder"]), $form, $lead, false, false);

        // override default values if override options provided
        if($override_options && is_array($override_options)){
            foreach($override_options as $override_key => $override_value){
                ${$override_key} = $override_value;
            }
        }

        $attachments = apply_filters("gform_user_notification_attachments_{$form_id}", apply_filters("gform_user_notification_attachments", array(), $lead, $form), $lead, $form);

        self::send_email($from, $to, $bcc, $reply_to, $subject, $message, $from_name, $message_format, $attachments);

        return compact("to", "from", "bcc", "reply_to", "subject", "message", "from_name", "message_format", "attachments");
    }

    public static function send_admin_notification($form, $lead, $override_options = false){
        $form_id = $form["id"];

        //handling admin notification email
        $subject = GFCommon::replace_variables(rgget("subject", $form["notification"]), $form, $lead, false, false);

        $message_format = apply_filters("gform_notification_format_{$form["id"]}", apply_filters("gform_notification_format", "html", "admin", $form, $lead), "admin", $form, $lead);
        $message = GFCommon::replace_variables(rgget("message", $form["notification"]), $form, $lead, false, false, !rgget("disableAutoformat", $form["notification"]), $message_format);
        $message = do_shortcode($message);

        $version_info = self::get_version_info();
        $is_expired = !rgempty("expiration_time", $version_info) && $version_info["expiration_time"] < time();
        if(!$version_info["is_valid_key"] && $is_expired){
            $message .= "<br/><br/>Your Gravity Forms License Key has expired. In order to continue receiving support and software updates you must renew your license key. You can do so by following the renewal instructions on the Gravity Forms Settings page in your WordPress Dashboard or by <a href='http://www.gravityhelp.com/renew-license/?key=" . self::get_key() . "'>clicking here</a>.";
        }

        $from = rgempty("fromField", $form["notification"]) ? rgget("from", $form["notification"]) : rgget($form["notification"]["fromField"], $lead);

        if(rgempty("fromNameField", $form["notification"])){
            $from_name = rgget("fromName", $form["notification"]);
        }
        else{
            $field = RGFormsModel::get_field($form, rgget("fromNameField", $form["notification"]));
            $value = RGFormsModel::get_lead_field_value($lead, $field);
            $from_name = GFCommon::get_lead_field_display($field, $value);
        }

        $replyTo = rgempty("replyToField", $form["notification"]) ? rgget("replyTo", $form["notification"]): rgget($form["notification"]["replyToField"], $lead);

        if(rgempty("routing", $form["notification"])){
            $email_to = rgget("to", $form["notification"]);
        }
        else{
            $email_to = array();
            foreach($form["notification"]["routing"] as $routing){

                $source_field = RGFormsModel::get_field($form, $routing["fieldId"]);
                $field_value = RGFormsModel::get_lead_field_value($lead, $source_field);
                $is_value_match = RGFormsModel::is_value_match($field_value, $routing["value"], $routing["operator"], $source_field) && !RGFormsModel::is_field_hidden($form, $source_field, array(), $lead);

                if ($is_value_match)
                    $email_to[] = $routing["email"];
            }

            $email_to = join(",", $email_to);
        }

        //Running through variable replacement
        $email_to = GFCommon::replace_variables($email_to, $form, $lead, false, false);
        $from = GFCommon::replace_variables($from, $form, $lead, false, false);
        $bcc = GFCommon::replace_variables(rgget("bcc", $form["notification"]), $form, $lead, false, false);
        $reply_to = GFCommon::replace_variables($replyTo, $form, $lead, false, false);
        $from_name = GFCommon::replace_variables($from_name, $form, $lead, false, false);

        //Filters the admin notification email to address. Allows users to change email address before notification is sent
        $to = apply_filters("gform_notification_email_{$form_id}" , apply_filters("gform_notification_email", $email_to, $lead), $lead);

        // override default values if override options provided
        if($override_options && is_array($override_options)){
            foreach($override_options as $override_key => $override_value){
                ${$override_key} = $override_value;
            }
        }

        $attachments = apply_filters("gform_admin_notification_attachments_{$form_id}", apply_filters("gform_admin_notification_attachments", array(), $lead, $form), $lead, $form);

        self::send_email($from, $to, $bcc, $replyTo, $subject, $message, $from_name, $message_format, $attachments);

        return compact("to", "from", "bcc", "replyTo", "subject", "message", "from_name", "message_format", "attachments");
    }

    public static function has_admin_notification($form){

        return (!empty($form["notification"]["to"]) || !empty($form["notification"]["routing"])) && (!empty($form["notification"]["subject"]) || !empty($form["notification"]["message"]));

    }

    public static function has_user_notification($form){

        return !empty($form["autoResponder"]["toField"]) && (!empty($form["autoResponder"]["subject"]) || !empty($form["autoResponder"]["message"]));

    }

    private static function send_email($from, $to, $bcc, $reply_to, $subject, $message, $from_name="", $message_format="html", $attachments=""){

        $to = str_replace(" ", "", $to);
        $bcc = str_replace(" ", "", $bcc);

        //invalid to email address or no content. can't send email
        if(!GFCommon::is_valid_email($to) || (empty($subject) && empty($message)))
            return;

        if(!GFCommon::is_valid_email($from))
            $from = get_bloginfo("admin_email");

        //invalid from address. can't send email
        if(!GFCommon::is_valid_email($from))
            return;

        $content_type = $message_format == "html" ? "text/html" : "text/plain";

        $name = empty($from_name) ? $from : $from_name;
        $headers = "From: \"$name\" <$from> \r\n";
        $headers .= GFCommon::is_valid_email($reply_to) ? "Reply-To: $reply_to\r\n" :"";
        $headers .= GFCommon::is_valid_email($bcc) ? "Bcc: $bcc\r\n" :"";
        $headers .= "Content-type: {$content_type}; charset=" . get_option('blog_charset') . "\r\n";

        GFCommon::log_debug("Sending email via wp_mail()");
        GFCommon::log_debug(print_r(compact("to", "subject", "message", "headers", "attachments"), true));

        $result = wp_mail($to, $subject, $message, $headers, $attachments);
        $result_text = $result ? "success" : "failed";

        GFCommon::log_debug("Result from wp_mail(): {$result} ({$result_text})");
    }

    public static function has_post_field($fields){
        foreach($fields as $field){
            if(in_array($field["type"], array("post_title", "post_content", "post_excerpt", "post_category", "post_image", "post_tags", "post_custom_field")))
                return true;
        }
        return false;
    }

    public static function has_list_field($form){
        return self::has_field_by_type($form, 'list');
    }

    public static function has_credit_card_field($form){
        return self::has_field_by_type($form, 'creditcard');
    }

    private static function has_field_by_type($form, $type) {
        if(is_array($form["fields"])){
            foreach($form["fields"] as $field){

                if(RGFormsModel::get_input_type($field) == $type)
                    return true;
            }
        }
        return false;
    }

    public static function current_user_can_any($caps){

        if(!is_array($caps))
            return current_user_can($caps) || current_user_can("gform_full_access");

        foreach($caps as $cap){
            if(current_user_can($cap))
                return true;
        }

        return current_user_can("gform_full_access");
    }

    public static function current_user_can_which($caps){

        foreach($caps as $cap){
            if(current_user_can($cap))
                return $cap;
        }

        return "";
    }

    public static function is_pricing_field($field_type){
        return self::is_product_field($field_type) || $field_type == "donation";
    }

    public static function is_product_field($field_type){
        return in_array($field_type, array("option", "quantity", "product", "total", "shipping", "calculation"));
    }

    public static function all_caps(){
        return array(   'gravityforms_edit_forms',
                        'gravityforms_delete_forms',
                        'gravityforms_create_form',
                        'gravityforms_view_entries',
                        'gravityforms_edit_entries',
                        'gravityforms_delete_entries',
                        'gravityforms_view_settings',
                        'gravityforms_edit_settings',
                        'gravityforms_export_entries',
                        'gravityforms_uninstall',
                        'gravityforms_view_entry_notes',
                        'gravityforms_edit_entry_notes',
                        'gravityforms_view_updates',
                        'gravityforms_addon_browser',
                        'gravityforms_preview_forms'
                        );
    }


    public static function delete_directory($dir) {
        if(!file_exists($dir))
            return;

        if ($handle = opendir($dir)){
            $array = array();
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    if(is_dir($dir.$file)){
                        if(!@rmdir($dir.$file)) // Empty directory? Remove it
                            self::delete_directory($dir.$file.'/'); // Not empty? Delete the files inside it
                    }
                    else{
                       @unlink($dir.$file);
                    }
                }
            }
            closedir($handle);
            @rmdir($dir);
        }
    }

    public static function get_remote_message(){
        return stripslashes(get_option("rg_gforms_message"));
    }

    public static function get_key(){
        return get_option("rg_gforms_key");
    }

    public static function has_update($use_cache=true){
        $version_info = GFCommon::get_version_info($use_cache);
        return version_compare(GFCommon::$version, $version_info["version"], '<');
    }

    public static function get_key_info($key){

        $options = array('method' => 'POST', 'timeout' => 3);
        $options['headers'] = array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option('blog_charset'),
            'User-Agent' => 'WordPress/' . get_bloginfo("version"),
            'Referer' => get_bloginfo("url")
        );
        $request_url = GRAVITY_MANAGER_URL . "/api.php?op=get_key&key={$key}";
        $raw_response = wp_remote_request($request_url, $options);
        if ( is_wp_error( $raw_response ) || $raw_response['response']['code'] != 200)
            return array();

        $key_info = unserialize(trim($raw_response["body"]));
        return $key_info ? $key_info : array();
    }

    public static function get_version_info($cache=true){

        $raw_response = get_transient("gform_update_info");
        if(!$cache)
            $raw_response = null;

        if(!$raw_response){
            //Getting version number
            $options = array('method' => 'POST', 'timeout' => 20);
            $options['headers'] = array(
                'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option('blog_charset'),
                'User-Agent' => 'WordPress/' . get_bloginfo("version"),
                'Referer' => get_bloginfo("url")
            );
            $request_url = GRAVITY_MANAGER_URL . "/version.php?" . self::get_remote_request_params();
            $raw_response = wp_remote_request($request_url, $options);

            //caching responses.
            set_transient("gform_update_info", $raw_response, 86400); //caching for 24 hours
        }

        if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code'])
            return array("is_valid_key" => "1", "version" => "", "url" => "");
        else {

            list($is_valid_key, $version, $url, $exp_time) = array_pad(explode("||", $raw_response['body']), 4, false);
            $info = array("is_valid_key" => $is_valid_key, "version" => $version, "url" => $url);
            if($exp_time)
                $info["expiration_time"] = $exp_time;

            return $info;
        }

    }

    public static function get_remote_request_params(){
        global $wpdb;
        return sprintf("of=GravityForms&key=%s&v=%s&wp=%s&php=%s&mysql=%s", urlencode(self::get_key()), urlencode(self::$version), urlencode(get_bloginfo("version")), urlencode(phpversion()), urlencode($wpdb->db_version()));
    }

    public static function ensure_wp_version(){
        if(!GF_SUPPORTED_WP_VERSION){
            echo "<div class='error' style='padding:10px;'>" . sprintf(__("Gravity Forms require WordPress %s or greater. You must upgrade WordPress in order to use Gravity Forms", "gravityforms"), GF_MIN_WP_VERSION) . "</div>";
            return false;
        }
        return true;
    }

    public static function check_update($option, $cache=true){

        $version_info = self::get_version_info($cache);

        if (!$version_info)
            return $option;

        $plugin_path = "gravityforms/gravityforms.php";
        if(empty($option->response[$plugin_path]))
            $option->response[$plugin_path] = new stdClass();

        //Empty response means that the key is invalid. Do not queue for upgrade
        if(!$version_info["is_valid_key"] || version_compare(GFCommon::$version, $version_info["version"], '>=')){
            unset($option->response[$plugin_path]);
        }
        else{
            $option->response[$plugin_path]->url = "http://www.gravityforms.com";
            $option->response[$plugin_path]->slug = "gravityforms";
            $option->response[$plugin_path]->package = str_replace("{KEY}", GFCommon::get_key(), $version_info["url"]);
            $option->response[$plugin_path]->new_version = $version_info["version"];
            $option->response[$plugin_path]->id = "0";
        }

        return $option;

    }

    public static function cache_remote_message(){
        //Getting version number
        $key = GFCommon::get_key();
        $body = "key=$key";
        $options = array('method' => 'POST', 'timeout' => 3, 'body' => $body);
        $options['headers'] = array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option('blog_charset'),
            'Content-Length' => strlen($body),
            'User-Agent' => 'WordPress/' . get_bloginfo("version"),
            'Referer' => get_bloginfo("url")
        );

        $request_url = GRAVITY_MANAGER_URL . "/message.php?" . GFCommon::get_remote_request_params();
        $raw_response = wp_remote_request($request_url, $options);

        if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code'] )
            $message = "";
        else
            $message = $raw_response['body'];

        //validating that message is a valid Gravity Form message. If message is invalid, don't display anything
        if(substr($message, 0, 10) != "<!--GFM-->")
            $message = "";

        update_option("rg_gforms_message", $message);
    }

    public static function get_local_timestamp($timestamp){
        return $timestamp + (get_option( 'gmt_offset' ) * 3600 );
    }

    public static function format_date($gmt_datetime, $is_human = true, $date_format="", $include_time=true){
        if(empty($gmt_datetime))
            return "";

        //adjusting date to local configured Time Zone
        $lead_gmt_time = mysql2date("G", $gmt_datetime);
        $lead_local_time = self::get_local_timestamp($lead_gmt_time);

        if(empty($date_format))
            $date_format = get_option('date_format');

        if($is_human){
            $time_diff = time() - $lead_gmt_time;

            if ($time_diff > 0 && $time_diff < 24*60*60)
                $date_display = sprintf(__('%s ago', 'gravityforms'), human_time_diff($lead_gmt_time));
            else
                $date_display = $include_time ? sprintf(__('%1$s at %2$s', 'gravityforms'), date_i18n($date_format, $lead_local_time, true), date_i18n(get_option('time_format'), $lead_local_time, true)) : date_i18n($date_format, $lead_local_time, true);
        }
        else{
            $date_display = $include_time ? sprintf(__('%1$s at %2$s', 'gravityforms'), date_i18n($date_format, $lead_local_time, true), date_i18n(get_option('time_format'), $lead_local_time, true)) : date_i18n($date_format, $lead_local_time, true);
        }

        return $date_display;
    }

    public static function get_selection_value($value){
        $ary = explode("|", $value);
        $val = $ary[0];
        return $val;
    }

    public static function selection_display($value, $field, $currency="", $use_text=false){
        $ary = explode("|", $value);
        $val = $ary[0];
        $price = count($ary) > 1 ? $ary[1] : "";

        if($use_text)
            $val = RGFormsModel::get_choice_text($field, $val);

        if(!empty($price))
            return "$val (" . self::to_money($price, $currency) . ")";
        else
            return $val;
    }

    public static function date_display($value, $format = "mdy"){
        $date = self::parse_date($value, $format);
        if(empty($date))
            return $value;

        list($position, $separator) = rgexplode("_", $format, 2);
        switch($separator){
            case "dash" :
                $separator = "-";
            break;
            case "dot" :
                $separator = ".";
            break;
            default :
                $separator = "/";
            break;
        }

        switch($position){
            case "ymd" :
                return $date["year"] . $separator . $date["month"] . $separator . $date["day"];
            break;

            case "dmy" :
                return $date["day"] . $separator . $date["month"] . $separator . $date["year"];
            break;

            default :
                return $date["month"] . $separator . $date["day"] . $separator . $date["year"];
            break;

        }
    }

    public static function parse_date($date, $format="mdy"){
        $date_info = array();

        $position = substr($format, 0, 3);

        if(is_array($date)){

            switch($position){
                case "mdy" :
                    $date_info["month"] = rgar($date, 0);
                    $date_info["day"] = rgar($date, 1);
                    $date_info["year"] = rgar($date, 2);
                break;

                case "dmy" :
                    $date_info["day"] = rgar($date, 0);
                    $date_info["month"] = rgar($date, 1);
                    $date_info["year"] = rgar($date, 2);
                break;

                case "ymd" :
                    $date_info["year"] = rgar($date, 0);
                    $date_info["month"] = rgar($date, 1);
                    $date_info["day"] = rgar($date, 2);
                break;
            }
            return $date_info;
        }

        $date = preg_replace("|[/\.]|", "-", $date);
        if(preg_match('/^(\d{1,4})-(\d{1,2})-(\d{1,4})$/', $date, $matches)){

            if(strlen($matches[1]) == 4){
                //format yyyy-mm-dd
                $date_info["year"] = $matches[1];
                $date_info["month"] = $matches[2];
                $date_info["day"] = $matches[3];
            }
            else if ($position == "mdy"){
                //format mm-dd-yyyy
                $date_info["month"] = $matches[1];
                $date_info["day"] = $matches[2];
                $date_info["year"] = $matches[3];
            }
            else{
                //format dd-mm-yyyy
                $date_info["day"] = $matches[1];
                $date_info["month"] = $matches[2];
                $date_info["year"] = $matches[3];
            }
        }

        return $date_info;
    }


    public static function truncate_url($url){
        $truncated_url = basename($url);
        if(empty($truncated_url))
            $truncated_url = dirname($url);

        $ary = explode("?", $truncated_url);

        return $ary[0];
    }

    public static function get_tabindex(){
        return GFCommon::$tab_index > 0 ? "tabindex='" . GFCommon::$tab_index++ . "'" : "";
    }

    public static function get_checkbox_choices($field, $value, $disabled_text){
        $choices = "";

        if(is_array($field["choices"])){
            $choice_number = 1;
            $count = 1;
            foreach($field["choices"] as $choice){
                if($choice_number % 10 == 0) //hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
                    $choice_number++;

                $input_id = $field["id"] . '.' . $choice_number;
                $id = $field["id"] . '_' . $choice_number++;

                if(empty($_POST) && rgar($choice,"isSelected")){
                    $checked = "checked='checked'";
                }
                else if(is_array($value) && RGFormsModel::choice_value_match($field, $choice, rgget($input_id, $value))){
                    $checked = "checked='checked'";
                }
                else if(!is_array($value) && RGFormsModel::choice_value_match($field, $choice, $value)){
                    $checked = "checked='checked'";
                }
                else{
                    $checked = "";
                }

                $logic_event = self::get_logic_event($field, "click");

                $tabindex = self::get_tabindex();
                $choice_value = $choice["value"];
                if(rgget("enablePrice", $field))
                    $choice_value .= "|" . GFCommon::to_number($choice["price"]);

                $choices.= sprintf("<li class='gchoice_$id'><input name='input_%s' type='checkbox' $logic_event value='%s' %s id='choice_%s' $tabindex %s /><label for='choice_%s'>%s</label></li>", $input_id, esc_attr($choice_value), $checked, $id, $disabled_text, $id, $choice["text"]);

                if(IS_ADMIN && RG_CURRENT_VIEW != "entry" && $count >=5)
                    break;

                $count++;
            }

            $total = sizeof($field["choices"]);
            if($count < $total)
                $choices .= "<li class='gchoice_total'>" . sprintf(__("%d of %d items shown. Edit field to view all", "gravityforms"), $count, $total) . "</li>";
        }

        return apply_filters("gform_field_choices_" . rgget("formId", $field), apply_filters("gform_field_choices", $choices, $field), $field);

    }

    public static function get_radio_choices($field, $value="", $disabled_text){
        $choices = "";

        if(is_array($field["choices"])){
            $choice_id = 0;

            // add "other" choice to choices if enabled
            if(rgar($field, 'enableOtherChoice')) {
                $other_default_value = GFCommon::get_other_choice_value();
                $field["choices"][] = array('text' => $other_default_value, 'value' => 'gf_other_choice', 'isSelected' => false, 'isOtherChoice' => true);
            }

            $logic_event = self::get_logic_event($field, "click");
            $count = 1;

            foreach($field["choices"] as $choice){
                $id = $field["id"] . '_' . $choice_id++;

                $field_value = !empty($choice["value"]) || rgar($field, "enableChoiceValue") ? $choice["value"] : $choice["text"];

                if(rgget("enablePrice", $field))
                    $field_value .= "|" . GFCommon::to_number(rgar($choice,"price"));

                if(rgblank($value) && RG_CURRENT_VIEW != "entry"){
                    $checked = rgar($choice,"isSelected") ? "checked='checked'" : "";
                }
                else {
                    $checked = RGFormsModel::choice_value_match($field, $choice, $value) ? "checked='checked'" : "";
                }

                $tabindex = self::get_tabindex();
                $label = sprintf("<label for='choice_%s'>%s</label>", $id, $choice["text"]);
                $input_focus = '';

                // handle "other" choice
                if(rgar($choice, 'isOtherChoice')) {

                    $onfocus = !IS_ADMIN ? 'jQuery(this).prev("input").attr("checked", true); if(jQuery(this).val() == "' . $other_default_value . '") { jQuery(this).val(""); }' : '';
                    $onblur = !IS_ADMIN ? 'if(jQuery(this).val().replace(" ", "") == "") { jQuery(this).val("' . $other_default_value . '"); }' : '';

                    $input_focus = !IS_ADMIN ? "onfocus=\"jQuery(this).next('input').focus();\"" : "";
                    $value_exists = RGFormsModel::choices_value_match($field, $field["choices"], $value);

                    if($value == 'gf_other_choice' && rgpost("input_{$field["id"]}_other")){
                        $other_value = rgpost("input_{$field["id"]}_other");
                    } else if(!$value_exists && !empty($value)){
                        $other_value = $value;
                        $value = 'gf_other_choice';
                        $checked = "checked='checked'";
                    } else {
                        $other_value = $other_default_value;
                    }
                    $label = "<input name='input_{$field["id"]}_other' type='text' value='" . esc_attr($other_value) . "' onfocus='$onfocus' onblur='$onblur' $tabindex $disabled_text />";
                }

                $choices .= sprintf("<li class='gchoice_$id'><input name='input_%d' type='radio' value='%s' %s id='choice_%s' $tabindex %s $logic_event %s />%s</li>", $field["id"], esc_attr($field_value), $checked, $id, $disabled_text, $input_focus, $label);

                if(IS_ADMIN && RG_CURRENT_VIEW != "entry" && $count >=5)
                    break;

                $count++;
            }

            $total = sizeof($field["choices"]);
            if($count < $total)
                $choices .= "<li class='gchoice_total'>" . sprintf(__("%d of %d items shown. Edit field to view all", "gravityforms"), $count, $total) . "</li>";
        }

        return apply_filters("gform_field_choices_" . rgget("formId", $field), apply_filters("gform_field_choices", $choices, $field), $field);
    }

    public static function get_field_type_title($type){
        switch($type){
            case "text" :
                return __("Single Line Text", "gravityforms");
            case "textarea" :
                return __("Paragraph Text", "gravityforms");
            case "select" :
                return __("Drop Down", "gravityforms");
            case "multiselect" :
                return __("Multi Select", "gravityforms");
            case "number" :
                return __("Number", "gravityforms");
            case "checkbox" :
                return __("Checkboxes", "gravityforms");
            case "radio" :
                return __("Radio Buttons", "gravityforms");
            case "hidden" :
                return __("Hidden", "gravityforms");
            case "html" :
                return __("HTML", "gravityforms");
            case "section" :
                return __("Section Break", "gravityforms");
            case "page" :
                return __("Page Break", "gravityforms");
            case "name" :
                return __("Name", "gravityforms");
            case "date" :
                return __("Date", "gravityforms");
            case "time" :
                return __("Time", "gravityforms");
            case "phone" :
                return __("Phone", "gravityforms");
            case "address" :
                return __("Address", "gravityforms");
            case "website" :
                return __("Website", "gravityforms");
            case "email" :
                return __("Email", "gravityforms");
            case "password" :
                return __("Password", "gravityforms");
            case "fileupload" :
                return __("File Upload", "gravityforms");
            case "captcha" :
                return __("CAPTCHA", "gravityforms");
            case "list" :
                return __("List", "gravityforms");
            case "creditcard" :
                return __("Credit Card", "gravityforms");
            case "post_title" :
                return __("Title", "gravityforms");
            case "post_content" :
                return __("Body", "gravityforms");
            case "post_excerpt" :
                return __("Excerpt", "gravityforms");
            case "post_tags" :
                return __("Tags", "gravityforms");
            case "post_category" :
                return __("Category", "gravityforms");
            case "post_image" :
                return __("Image", "gravityforms");
            case "post_custom_field" :
                return __("Custom Field", "gravityforms");
            case "product" :
                return __("Product", "gravityforms");
            case "quantity" :
                return __("Quantity", "gravityforms");
            case "option" :
                return __("Option", "gravityforms");
            case "shipping" :
                return __("Shipping", "gravityforms");
            case "total" :
                return __("Total", "gravityforms");

            default :
                return apply_filters("gform_field_type_title", $type, $type);
        }
    }

    public static function get_select_choices($field, $value=""){
        $choices = "";

        if(RG_CURRENT_VIEW == "entry" && empty($value))
            $choices .= "<option value=''></option>";

        if(is_array(rgar($field, "choices"))){
            foreach($field["choices"] as $choice){

                // needed for users upgrading from 1.0
                $field_value = !empty($choice["value"]) || rgget("enableChoiceValue", $field) || $field['type'] == 'post_category' ? $choice["value"] : $choice["text"];

                if(rgget("enablePrice", $field))
                    $field_value .= "|" . GFCommon::to_number(rgar($choice,"price"));

                if(rgblank($value) && RG_CURRENT_VIEW != "entry"){
                    $selected = rgar($choice,"isSelected") ? "selected='selected'" : "";
                }
                else{
                    if(is_array($value)){
                        $is_match = false;
                        foreach($value as $item){
                            if(RGFormsModel::choice_value_match($field, $choice, $item)){
                                $is_match = true;
                                break;
                            }
                        }
                        $selected = $is_match ? "selected='selected'" : "";
                    }
                    else{
                        $selected = RGFormsModel::choice_value_match($field, $choice, $value) ? "selected='selected'" : "";
                    }
                }

                $choices.= sprintf("<option value='%s' %s>%s</option>", esc_attr($field_value), $selected,  esc_html($choice["text"]));
            }
        }
        return $choices;
    }

    public static function is_section_empty($section_field, $form, $lead){
        $fields = self::get_section_fields($form, $section_field["id"]);
        if(!is_array($fields))
            return true;

        foreach($fields as $field){
            $val = RGFormsModel::get_lead_field_value($lead, $field);
            $val = GFCommon::get_lead_field_display($field, $val, rgar($lead, 'currency'));

            if(!self::is_product_field($field["type"]) && !rgblank($val))
                return false;
        }
        return true;
    }

    public static function get_section_fields($form, $section_field_id){
        $fields = array();
        $in_section = false;
        foreach($form["fields"] as $field){
            if(in_array($field["type"], array("section", "page")) && $in_section)
                return $fields;

            if($field["id"] == $section_field_id)
                $in_section = true;

            if($in_section)
                $fields[] = $field;
        }

        return $fields;
    }


    public static function get_countries(){
        return apply_filters("gform_countries", array(
        __('Afghanistan', 'gravityforms'),__('Albania', 'gravityforms'),__('Algeria', 'gravityforms'), __('American Samoa', 'gravityforms'), __('Andorra', 'gravityforms'),__('Angola', 'gravityforms'),__('Antigua and Barbuda', 'gravityforms'),__('Argentina', 'gravityforms'),__('Armenia', 'gravityforms'),__('Australia', 'gravityforms'),__('Austria', 'gravityforms'),__('Azerbaijan', 'gravityforms'),__('Bahamas', 'gravityforms'),__('Bahrain', 'gravityforms'),__('Bangladesh', 'gravityforms'),__('Barbados', 'gravityforms'),__('Belarus', 'gravityforms'),__('Belgium', 'gravityforms'),__('Belize', 'gravityforms'),__('Benin', 'gravityforms'),__('Bermuda', 'gravityforms'),__('Bhutan', 'gravityforms'),__('Bolivia', 'gravityforms'),__('Bosnia and Herzegovina', 'gravityforms'),__('Botswana', 'gravityforms'),__('Brazil', 'gravityforms'),__('Brunei', 'gravityforms'),__('Bulgaria', 'gravityforms'),__('Burkina Faso', 'gravityforms'),__('Burundi', 'gravityforms'),__('Cambodia', 'gravityforms'),__('Cameroon', 'gravityforms'),__('Canada', 'gravityforms'),__('Cape Verde', 'gravityforms'),__('Central African Republic', 'gravityforms'),__('Chad', 'gravityforms'),__('Chile', 'gravityforms'),__('China', 'gravityforms'),__('Colombia', 'gravityforms'),__('Comoros', 'gravityforms'),__('Congo, Democratic Republic of the', 'gravityforms'),__('Congo, Republic of the', 'gravityforms'),__('Costa Rica', 'gravityforms'),__('C&ocirc;te d\'Ivoire', 'gravityforms'),__('Croatia', 'gravityforms'),__('Cuba', 'gravityforms'),__('Cyprus', 'gravityforms'),__('Czech Republic', 'gravityforms'),__('Denmark', 'gravityforms'),__('Djibouti', 'gravityforms'),__('Dominica', 'gravityforms'),__('Dominican Republic', 'gravityforms'),__('East Timor', 'gravityforms'),__('Ecuador', 'gravityforms'),__('Egypt', 'gravityforms'),__('El Salvador', 'gravityforms'),__('Equatorial Guinea', 'gravityforms'),__('Eritrea', 'gravityforms'),__('Estonia', 'gravityforms'),__('Ethiopia', 'gravityforms'),__('Fiji', 'gravityforms'),__('Finland', 'gravityforms'),__('France', 'gravityforms'),__('Gabon', 'gravityforms'),
        __('Gambia', 'gravityforms'),__('Georgia', 'gravityforms'),__('Germany', 'gravityforms'),__('Ghana', 'gravityforms'),__('Greece', 'gravityforms'),__('Greenland', 'gravityforms'),__('Grenada', 'gravityforms'),__('Guam', 'gravityforms'),__('Guatemala', 'gravityforms'),__('Guinea', 'gravityforms'),__('Guinea-Bissau', 'gravityforms'),__('Guyana', 'gravityforms'),__('Haiti', 'gravityforms'),__('Honduras', 'gravityforms'),__('Hong Kong', 'gravityforms'),__('Hungary', 'gravityforms'),__('Iceland', 'gravityforms'),__('India', 'gravityforms'),__('Indonesia', 'gravityforms'),__('Iran', 'gravityforms'),__('Iraq', 'gravityforms'),__('Ireland', 'gravityforms'),__('Israel', 'gravityforms'),__('Italy', 'gravityforms'),__('Jamaica', 'gravityforms'),__('Japan', 'gravityforms'),__('Jordan', 'gravityforms'),__('Kazakhstan', 'gravityforms'),__('Kenya', 'gravityforms'),__('Kiribati', 'gravityforms'),__('North Korea', 'gravityforms'),__('South Korea', 'gravityforms'),__('Kuwait', 'gravityforms'),__('Kyrgyzstan', 'gravityforms'),__('Laos', 'gravityforms'),__('Latvia', 'gravityforms'),__('Lebanon', 'gravityforms'),__('Lesotho', 'gravityforms'),__('Liberia', 'gravityforms'),__('Libya', 'gravityforms'),__('Liechtenstein', 'gravityforms'),__('Lithuania', 'gravityforms'),__('Luxembourg', 'gravityforms'),__('Macedonia', 'gravityforms'),__('Madagascar', 'gravityforms'),__('Malawi', 'gravityforms'),__('Malaysia', 'gravityforms'),__('Maldives', 'gravityforms'),__('Mali', 'gravityforms'),__('Malta', 'gravityforms'),__('Marshall Islands', 'gravityforms'),__('Mauritania', 'gravityforms'),__('Mauritius', 'gravityforms'),__('Mexico', 'gravityforms'),__('Micronesia', 'gravityforms'),__('Moldova', 'gravityforms'),__('Monaco', 'gravityforms'),__('Mongolia', 'gravityforms'),__('Montenegro', 'gravityforms'),__('Morocco', 'gravityforms'),__('Mozambique', 'gravityforms'),__('Myanmar', 'gravityforms'),__('Namibia', 'gravityforms'),__('Nauru', 'gravityforms'),__('Nepal', 'gravityforms'),__('Netherlands', 'gravityforms'),__('New Zealand', 'gravityforms'),
        __('Nicaragua', 'gravityforms'),__('Niger', 'gravityforms'),__('Nigeria', 'gravityforms'),__('Norway', 'gravityforms'), __('Northern Mariana Islands', 'gravityforms'), __('Oman', 'gravityforms'),__('Pakistan', 'gravityforms'),__('Palau', 'gravityforms'),__('Palestine', 'gravityforms'),__('Panama', 'gravityforms'),__('Papua New Guinea', 'gravityforms'),__('Paraguay', 'gravityforms'),__('Peru', 'gravityforms'),__('Philippines', 'gravityforms'),__('Poland', 'gravityforms'),__('Portugal', 'gravityforms'),__('Puerto Rico', 'gravityforms'),__('Qatar', 'gravityforms'),__('Romania', 'gravityforms'),__('Russia', 'gravityforms'),__('Rwanda', 'gravityforms'),__('Saint Kitts and Nevis', 'gravityforms'),__('Saint Lucia', 'gravityforms'),__('Saint Vincent and the Grenadines', 'gravityforms'),__('Samoa', 'gravityforms'),__('San Marino', 'gravityforms'),__('Sao Tome and Principe', 'gravityforms'),__('Saudi Arabia', 'gravityforms'),__('Senegal', 'gravityforms'),__('Serbia and Montenegro', 'gravityforms'),__('Seychelles', 'gravityforms'),__('Sierra Leone', 'gravityforms'),__('Singapore', 'gravityforms'),__('Slovakia', 'gravityforms'),__('Slovenia', 'gravityforms'),__('Solomon Islands', 'gravityforms'),__('Somalia', 'gravityforms'),__('South Africa', 'gravityforms'),__('Spain', 'gravityforms'),__('Sri Lanka', 'gravityforms'),__('Sudan', 'gravityforms'),__('Sudan, South', 'gravityforms'),__('Suriname', 'gravityforms'),__('Swaziland', 'gravityforms'),__('Sweden', 'gravityforms'),__('Switzerland', 'gravityforms'),__('Syria', 'gravityforms'),__('Taiwan', 'gravityforms'),__('Tajikistan', 'gravityforms'),__('Tanzania', 'gravityforms'),__('Thailand', 'gravityforms'),__('Togo', 'gravityforms'),__('Tonga', 'gravityforms'),__('Trinidad and Tobago', 'gravityforms'),__('Tunisia', 'gravityforms'),__('Turkey', 'gravityforms'),__('Turkmenistan', 'gravityforms'),__('Tuvalu', 'gravityforms'),__('Uganda', 'gravityforms'),__('Ukraine', 'gravityforms'),__('United Arab Emirates', 'gravityforms'),__('United Kingdom', 'gravityforms'),
        __('United States', 'gravityforms'),__('Uruguay', 'gravityforms'),__('Uzbekistan', 'gravityforms'),__('Vanuatu', 'gravityforms'),__('Vatican City', 'gravityforms'),__('Venezuela', 'gravityforms'),__('Vietnam', 'gravityforms'), __('Virgin Islands, British', 'gravityforms'), __('Virgin Islands, U.S.', 'gravityforms'),__('Yemen', 'gravityforms'),__('Zambia', 'gravityforms'),__('Zimbabwe', 'gravityforms')));


    }

    public static function get_country_code($country_name) {
        $codes = array(
            __('AFGHANISTAN', 'gravityforms') => "AF" ,
            __('ALBANIA', 'gravityforms') => "AL" ,
            __('ALGERIA', 'gravityforms') => "DZ" ,
            __('AMERICAN SAMOA', 'gravityforms') => "AS" ,
            __('ANDORRA', 'gravityforms') => "AD" ,
            __('ANGOLA', 'gravityforms') => "AO" ,
            __('ANTIGUA AND BARBUDA', 'gravityforms') => "AG" ,
            __('ARGENTINA', 'gravityforms') => "AR" ,
            __('ARMENIA', 'gravityforms') => "AM" ,
            __('AUSTRALIA', 'gravityforms') => "AU" ,
            __('AUSTRIA', 'gravityforms') => "AT" ,
            __('AZERBAIJAN', 'gravityforms') => "AZ" ,
            __('BAHAMAS', 'gravityforms') => "BS" ,
            __('BAHRAIN', 'gravityforms') => "BH" ,
            __('BANGLADESH', 'gravityforms') => "BD" ,
            __('BARBADOS', 'gravityforms') => "BB" ,
            __('BELARUS', 'gravityforms') => "BY" ,
            __('BELGIUM', 'gravityforms') => "BE" ,
            __('BELIZE', 'gravityforms') => "BZ" ,
            __('BENIN', 'gravityforms') => "BJ" ,
            __('BERMUDA', 'gravityforms') => "BM" ,
            __('BHUTAN', 'gravityforms') => "BT" ,
            __('BOLIVIA', 'gravityforms') => "BO" ,
            __('BOSNIA AND HERZEGOVINA', 'gravityforms') => "BA" ,
            __('BOTSWANA', 'gravityforms') => "BW" ,
            __('BRAZIL', 'gravityforms') => "BR" ,
            __('BRUNEI', 'gravityforms') => "BN" ,
            __('BULGARIA', 'gravityforms') => "BG" ,
            __('BURKINA FASO', 'gravityforms') => "BF" ,
            __('BURUNDI', 'gravityforms') => "BI" ,
            __('CAMBODIA', 'gravityforms') => "KH" ,
            __('CAMEROON', 'gravityforms') => "CM" ,
            __('CANADA', 'gravityforms') => "CA" ,
            __('CAPE VERDE', 'gravityforms') => "CV" ,
            __('CENTRAL AFRICAN REPUBLIC', 'gravityforms') => "CF" ,
            __('CHAD', 'gravityforms') => "TD" ,
            __('CHILE', 'gravityforms') => "CL" ,
            __('CHINA', 'gravityforms') => "CN" ,
            __('COLOMBIA', 'gravityforms') => "CO" ,
            __('COMOROS', 'gravityforms') => "KM" ,
            __('CONGO, DEMOCRATIC REPUBLIC OF THE', 'gravityforms') => "CD" ,
            __('CONGO, REPUBLIC OF THE', 'gravityforms') => "CG" ,
            __('COSTA RICA', 'gravityforms') => "CR" ,
            __('C&OCIRC;TE D\'IVOIRE', 'gravityforms') => "CI" ,
            __('CROATIA', 'gravityforms') => "HR" ,
            __('CUBA', 'gravityforms') => "CU" ,
            __('CYPRUS', 'gravityforms') => "CY" ,
            __('CZECH REPUBLIC', 'gravityforms') => "CZ" ,
            __('DENMARK', 'gravityforms') => "DK" ,
            __('DJIBOUTI', 'gravityforms') => "DJ" ,
            __('DOMINICA', 'gravityforms') => "DM" ,
            __('DOMINICAN REPUBLIC', 'gravityforms') => "DO" ,
            __('EAST TIMOR', 'gravityforms') => "TL" ,
            __('ECUADOR', 'gravityforms') => "EC" ,
            __('EGYPT', 'gravityforms') => "EG" ,
            __('EL SALVADOR', 'gravityforms') => "SV" ,
            __('EQUATORIAL GUINEA', 'gravityforms') => "GQ" ,
            __('ERITREA', 'gravityforms') => "ER" ,
            __('ESTONIA', 'gravityforms') => "EE" ,
            __('ETHIOPIA', 'gravityforms') => "ET" ,
            __('FIJI', 'gravityforms') => "FJ" ,
            __('FINLAND', 'gravityforms') => "FI" ,
            __('FRANCE', 'gravityforms') => "FR" ,
            __('GABON', 'gravityforms') => "GA" ,
            __('GAMBIA', 'gravityforms') => "GM" ,
            __('GEORGIA', 'gravityforms') => "GE" ,
            __('GERMANY', 'gravityforms') => "DE" ,
            __('GHANA', 'gravityforms') => "GH" ,
            __('GREECE', 'gravityforms') => "GR" ,
            __('GREENLAND', 'gravityforms') => "GL" ,
            __('GRENADA', 'gravityforms') => "GD" ,
            __('GUAM', 'gravityforms') => "GU" ,
            __('GUATEMALA', 'gravityforms') => "GT" ,
            __('GUINEA', 'gravityforms') => "GN" ,
            __('GUINEA-BISSAU', 'gravityforms') => "GW" ,
            __('GUYANA', 'gravityforms') => "GY" ,
            __('HAITI', 'gravityforms') => "HT" ,
            __('HONDURAS', 'gravityforms') => "HN" ,
            __('HONG KONG', 'gravityforms') => "HK" ,
            __('HUNGARY', 'gravityforms') => "HU" ,
            __('ICELAND', 'gravityforms') => "IS" ,
            __('INDIA', 'gravityforms') => "IN" ,
            __('INDONESIA', 'gravityforms') => "ID" ,
            __('IRAN', 'gravityforms') => "IR" ,
            __('IRAQ', 'gravityforms') => "IQ" ,
            __('IRELAND', 'gravityforms') => "IE" ,
            __('ISRAEL', 'gravityforms') => "IL" ,
            __('ITALY', 'gravityforms') => "IT" ,
            __('JAMAICA', 'gravityforms') => "JM" ,
            __('JAPAN', 'gravityforms') => "JP" ,
            __('JORDAN', 'gravityforms') => "JO" ,
            __('KAZAKHSTAN', 'gravityforms') => "KZ" ,
            __('KENYA', 'gravityforms') => "KE" ,
            __('KIRIBATI', 'gravityforms') => "KI" ,
            __('NORTH KOREA', 'gravityforms') => "KP" ,
            __('SOUTH KOREA', 'gravityforms') => "KR" ,
            __('KUWAIT', 'gravityforms') => "KW" ,
            __('KYRGYZSTAN', 'gravityforms') => "KG" ,
            __('LAOS', 'gravityforms') => "LA" ,
            __('LATVIA', 'gravityforms') => "LV" ,
            __('LEBANON', 'gravityforms') => "LB" ,
            __('LESOTHO', 'gravityforms') => "LS" ,
            __('LIBERIA', 'gravityforms') => "LR" ,
            __('LIBYA', 'gravityforms') => "LY" ,
            __('LIECHTENSTEIN', 'gravityforms') => "LI" ,
            __('LITHUANIA', 'gravityforms') => "LT" ,
            __('LUXEMBOURG', 'gravityforms') => "LU" ,
            __('MACEDONIA', 'gravityforms') => "MK" ,
            __('MADAGASCAR', 'gravityforms') => "MG" ,
            __('MALAWI', 'gravityforms') => "MW" ,
            __('MALAYSIA', 'gravityforms') => "MY" ,
            __('MALDIVES', 'gravityforms') => "MV" ,
            __('MALI', 'gravityforms') => "ML" ,
            __('MALTA', 'gravityforms') => "MT" ,
            __('MARSHALL ISLANDS', 'gravityforms') => "MH" ,
            __('MAURITANIA', 'gravityforms') => "MR" ,
            __('MAURITIUS', 'gravityforms') => "MU" ,
            __('MEXICO', 'gravityforms') => "MX" ,
            __('MICRONESIA', 'gravityforms') => "FM" ,
            __('MOLDOVA', 'gravityforms') => "MD" ,
            __('MONACO', 'gravityforms') => "MC" ,
            __('MONGOLIA', 'gravityforms') => "MN" ,
            __('MONTENEGRO', 'gravityforms') => "ME" ,
            __('MOROCCO', 'gravityforms') => "MA" ,
            __('MOZAMBIQUE', 'gravityforms') => "MZ" ,
            __('MYANMAR', 'gravityforms') => "MM" ,
            __('NAMIBIA', 'gravityforms') => "NA" ,
            __('NAURU', 'gravityforms') => "NR" ,
            __('NEPAL', 'gravityforms') => "NP" ,
            __('NETHERLANDS', 'gravityforms') => "NL" ,
            __('NEW ZEALAND', 'gravityforms') => "NZ" ,
            __('NICARAGUA', 'gravityforms') => "NI" ,
            __('NIGER', 'gravityforms') => "NE" ,
            __('NIGERIA', 'gravityforms') => "NG" ,
            __('NORTHERN MARIANA ISLANDS', 'gravityforms') => "MP" ,
            __('NORWAY', 'gravityforms') => "NO" ,
            __('OMAN', 'gravityforms') => "OM" ,
            __('PAKISTAN', 'gravityforms') => "PK" ,
            __('PALAU', 'gravityforms') => "PW" ,
            __('PALESTINE', 'gravityforms') => "PS" ,
            __('PANAMA', 'gravityforms') => "PA" ,
            __('PAPUA NEW GUINEA', 'gravityforms') => "PG" ,
            __('PARAGUAY', 'gravityforms') => "PY" ,
            __('PERU', 'gravityforms') => "PE" ,
            __('PHILIPPINES', 'gravityforms') => "PH" ,
            __('POLAND', 'gravityforms') => "PL" ,
            __('PORTUGAL', 'gravityforms') => "PT" ,
            __('PUERTO RICO', 'gravityforms') => "PR" ,
            __('QATAR', 'gravityforms') => "QA" ,
            __('ROMANIA', 'gravityforms') => "RO" ,
            __('RUSSIA', 'gravityforms') => "RU" ,
            __('RWANDA', 'gravityforms') => "RW" ,
            __('SAINT KITTS AND NEVIS', 'gravityforms') => "KN" ,
            __('SAINT LUCIA', 'gravityforms') => "LC" ,
            __('SAINT VINCENT AND THE GRENADINES', 'gravityforms') => "VC" ,
            __('SAMOA', 'gravityforms') => "WS" ,
            __('SAN MARINO', 'gravityforms') => "SM" ,
            __('SAO TOME AND PRINCIPE', 'gravityforms') => "ST" ,
            __('SAUDI ARABIA', 'gravityforms') => "SA" ,
            __('SENEGAL', 'gravityforms') => "SN" ,
            __('SERBIA AND MONTENEGRO', 'gravityforms') => "RS" ,
            __('SEYCHELLES', 'gravityforms') => "SC" ,
            __('SIERRA LEONE', 'gravityforms') => "SL" ,
            __('SINGAPORE', 'gravityforms') => "SG" ,
            __('SLOVAKIA', 'gravityforms') => "SK" ,
            __('SLOVENIA', 'gravityforms') => "SI" ,
            __('SOLOMON ISLANDS', 'gravityforms') => "SB" ,
            __('SOMALIA', 'gravityforms') => "SO" ,
            __('SOUTH AFRICA', 'gravityforms') => "ZA" ,
            __('SPAIN', 'gravityforms') => "ES" ,
            __('SRI LANKA', 'gravityforms') => "LK" ,
            __('SUDAN', 'gravityforms') => "SD" ,
            __('SUDAN, SOUTH', 'gravityforms') => "SS" ,
            __('SURINAME', 'gravityforms') => "SR" ,
            __('SWAZILAND', 'gravityforms') => "SZ" ,
            __('SWEDEN', 'gravityforms') => "SE" ,
            __('SWITZERLAND', 'gravityforms') => "CH" ,
            __('SYRIA', 'gravityforms') => "SY" ,
            __('TAIWAN', 'gravityforms') => "TW" ,
            __('TAJIKISTAN', 'gravityforms') => "TJ" ,
            __('TANZANIA', 'gravityforms') => "TZ" ,
            __('THAILAND', 'gravityforms') => "TH" ,
            __('TOGO', 'gravityforms') => "TG" ,
            __('TONGA', 'gravityforms') => "TO" ,
            __('TRINIDAD AND TOBAGO', 'gravityforms') => "TT" ,
            __('TUNISIA', 'gravityforms') => "TN" ,
            __('TURKEY', 'gravityforms') => "TR" ,
            __('TURKMENISTAN', 'gravityforms') => "TM" ,
            __('TUVALU', 'gravityforms') => "TV" ,
            __('UGANDA', 'gravityforms') => "UG" ,
            __('UKRAINE', 'gravityforms') => "UA" ,
            __('UNITED ARAB EMIRATES', 'gravityforms') => "AE" ,
            __('UNITED KINGDOM', 'gravityforms') => "GB" ,
            __('UNITED STATES', 'gravityforms') => "US" ,
            __('URUGUAY', 'gravityforms') => "UY" ,
            __('UZBEKISTAN', 'gravityforms') => "UZ" ,
            __('VANUATU', 'gravityforms') => "VU" ,
            __('VATICAN CITY', 'gravityforms') => "" ,
            __('VENEZUELA', 'gravityforms') => "VE" ,
            __('VIRGIN ISLANDS, BRITISH', 'gravityforms') => "VG" ,
            __('VIRGIN ISLANDS, U.S.', 'gravityforms') => "VI" ,
            __('VIETNAM', 'gravityforms') => "VN" ,
            __('YEMEN', 'gravityforms') => "YE" ,
            __('ZAMBIA', 'gravityforms') => "ZM" ,
            __('ZIMBABWE', 'gravityforms') => "ZW" );

            return rgar($codes, strtoupper($country_name));
    }

    public static function get_us_states(){
        return array(__("Alabama","gravityforms"),__("Alaska","gravityforms"),__("Arizona","gravityforms"),__("Arkansas","gravityforms"),__("California","gravityforms"),__("Colorado","gravityforms"),__("Connecticut","gravityforms"),__("Delaware","gravityforms"),__("District of Columbia", "gravityforms"), __("Florida","gravityforms"),__("Georgia","gravityforms"),__("Hawaii","gravityforms"),__("Idaho","gravityforms"),__("Illinois","gravityforms"),__("Indiana","gravityforms"),__("Iowa","gravityforms"),__("Kansas","gravityforms"),__("Kentucky","gravityforms"),__("Louisiana","gravityforms"),__("Maine","gravityforms"),__("Maryland","gravityforms"),__("Massachusetts","gravityforms"),__("Michigan","gravityforms"),__("Minnesota","gravityforms"),__("Mississippi","gravityforms"),__("Missouri","gravityforms"),__("Montana","gravityforms"),__("Nebraska","gravityforms"),__("Nevada","gravityforms"),__("New Hampshire","gravityforms"),__("New Jersey","gravityforms"),__("New Mexico","gravityforms"),__("New York","gravityforms"),__("North Carolina","gravityforms"),__("North Dakota","gravityforms"),__("Ohio","gravityforms"),__("Oklahoma","gravityforms"),__("Oregon","gravityforms"),__("Pennsylvania","gravityforms"),__("Rhode Island","gravityforms"),__("South Carolina","gravityforms"),__("South Dakota","gravityforms"),__("Tennessee","gravityforms"),__("Texas","gravityforms"),__("Utah","gravityforms"),__("Vermont","gravityforms"),__("Virginia","gravityforms"),__("Washington","gravityforms"),__("West Virginia","gravityforms"),__("Wisconsin","gravityforms"),__("Wyoming","gravityforms"), __("Armed Forces Americas","gravityforms"), __("Armed Forces Europe","gravityforms"),__("Armed Forces Pacific","gravityforms"));
    }

    public static function get_us_state_code($state_name){
        $states = array(
            strtoupper(__("Alabama","gravityforms")) => "AL",
            strtoupper(__("Alaska","gravityforms")) => "AK",
            strtoupper(__("Arizona","gravityforms")) => "AZ",
            strtoupper(__("Arkansas","gravityforms")) => "AR",
            strtoupper(__("California","gravityforms")) => "CA",
            strtoupper(__("Colorado","gravityforms")) => "CO",
            strtoupper(__("Connecticut","gravityforms")) => "CT",
            strtoupper(__("Delaware","gravityforms")) => "DE",
            strtoupper(__("District of Columbia", "gravityforms")) => "DC",
            strtoupper(__("Florida","gravityforms")) => "FL",
            strtoupper(__("Georgia","gravityforms")) => "GA",
            strtoupper(__("Hawaii","gravityforms")) => "HI",
            strtoupper(__("Idaho","gravityforms")) => "ID",
            strtoupper(__("Illinois","gravityforms")) => "IL",
            strtoupper(__("Indiana","gravityforms")) => "IN",
            strtoupper(__("Iowa","gravityforms")) => "IA",
            strtoupper(__("Kansas","gravityforms")) => "KS",
            strtoupper(__("Kentucky","gravityforms")) => "KY",
            strtoupper(__("Louisiana","gravityforms")) => "LA",
            strtoupper(__("Maine","gravityforms")) => "ME",
            strtoupper(__("Maryland","gravityforms")) => "MD",
            strtoupper(__("Massachusetts","gravityforms")) => "MA",
            strtoupper(__("Michigan","gravityforms")) => "MI",
            strtoupper(__("Minnesota","gravityforms")) => "MN",
            strtoupper(__("Mississippi","gravityforms")) => "MS",
            strtoupper(__("Missouri","gravityforms")) => "MO",
            strtoupper(__("Montana","gravityforms")) => "MT",
            strtoupper(__("Nebraska","gravityforms")) => "NE",
            strtoupper(__("Nevada","gravityforms")) => "NV",
            strtoupper(__("New Hampshire","gravityforms")) => "NH",
            strtoupper(__("New Jersey","gravityforms")) => "NJ",
            strtoupper(__("New Mexico","gravityforms")) => "NM",
            strtoupper(__("New York","gravityforms")) => "NY",
            strtoupper(__("North Carolina","gravityforms")) => "NC",
            strtoupper(__("North Dakota","gravityforms")) => "ND",
            strtoupper(__("Ohio","gravityforms")) => "OH",
            strtoupper(__("Oklahoma","gravityforms")) => "OK",
            strtoupper(__("Oregon","gravityforms")) => "OR",
            strtoupper(__("Pennsylvania","gravityforms")) => "PA",
            strtoupper(__("Rhode Island","gravityforms")) => "RI",
            strtoupper(__("South Carolina","gravityforms")) => "SC",
            strtoupper(__("South Dakota","gravityforms")) => "SD",
            strtoupper(__("Tennessee","gravityforms")) => "TN",
            strtoupper(__("Texas","gravityforms")) => "TX",
            strtoupper(__("Utah","gravityforms")) => "UT",
            strtoupper(__("Vermont","gravityforms")) => "VT",
            strtoupper(__("Virginia","gravityforms")) => "VA",
            strtoupper(__("Washington","gravityforms")) => "WA",
            strtoupper(__("West Virginia","gravityforms")) => "WV",
            strtoupper(__("Wisconsin","gravityforms")) => "WI",
            strtoupper(__("Wyoming","gravityforms")) => "WY",
            strtoupper(__("Armed Forces Americas","gravityforms")) => "AA",
            strtoupper(__("Armed Forces Europe","gravityforms")) => "AE",
            strtoupper(__("Armed Forces Pacific","gravityforms")) => "AP"
            );

            $code = isset($states[strtoupper($state_name)]) ? $states[strtoupper($state_name)] : strtoupper($state_name);

            return $code;
    }


    public static function get_canadian_provinces(){
        return array(__("Alberta","gravityforms"),__("British Columbia","gravityforms"),__("Manitoba","gravityforms"),__("New Brunswick","gravityforms"),__("Newfoundland & Labrador","gravityforms"),__("Northwest Territories","gravityforms"),__("Nova Scotia","gravityforms"),__("Nunavut","gravityforms"),__("Ontario","gravityforms"),__("Prince Edward Island","gravityforms"),__("Quebec","gravityforms"),__("Saskatchewan","gravityforms"),__("Yukon","gravityforms"));

    }

    public static function get_state_dropdown($states, $selected_state=""){
        $str = "";
        foreach($states as $state){
            $selected = $state == $selected_state ? "selected='selected'" : "";
            $str .= "<option value='" . esc_attr($state) . "' $selected>" . $state . "</option>";
        }
        return $str;
    }

    public static function get_us_state_dropdown($selected_state = ""){
        $states = array_merge(array(''), self::get_us_states());
        foreach($states as $state){
            $selected = $state == $selected_state ? "selected='selected'" : "";
            $str .= "<option value='" . esc_attr($state) . "' $selected>" . $state . "</option>";
        }
        return $str;
    }

    public static function get_canadian_provinces_dropdown($selected_province = ""){
        $states = array_merge(array(''), self::get_canadian_provinces());
        foreach($states as $state){
            $selected = $state == $selected_province ? "selected='selected'" : "";
            $str .= "<option value='" . esc_attr($state) . "' $selected>" . $state . "</option>";
        }
        return $str;
    }

    public static function get_country_dropdown($selected_country = ""){
        $str = "";
        $countries = array_merge(array(''), self::get_countries());
        foreach($countries as $country){
            $selected = $country == $selected_country ? "selected='selected'" : "";
            $str .= "<option value='" . esc_attr($country) . "' $selected>" . $country . "</option>";
        }
        return $str;
    }

    public static function is_post_field($field){
        return in_array($field["type"], array("post_title", "post_tags", "post_category", "post_custom_field", "post_content", "post_excerpt", "post_image"));
    }

    public static function get_range_message($field){
        $min = $field["rangeMin"];
        $max = $field["rangeMax"];
        $message = "";

        if(is_numeric($min) && is_numeric($max))
            $message =  sprintf(__("Please enter a value between %s and %s.", "gravityforms"), "<strong>$min</strong>", "<strong>$max</strong>") ;
        else if(is_numeric($min))
            $message = sprintf(__("Please enter a value greater than or equal to %s.", "gravityforms"), "<strong>$min</strong>");
        else if(is_numeric($max))
            $message = sprintf(__("Please enter a value less than or equal to %s.", "gravityforms"), "<strong>$max</strong>");
        else if($field["failed_validation"])
            $message = __("Please enter a valid number", "gravityforms");

        return $message;
    }

    public static function get_fields_by_type($form, $types){
        $fields = array();
        if(!is_array(rgar($form,"fields")))
            return $fields;

        foreach($form["fields"] as $field){
            if(in_array(rgar($field,"type"), $types))
                $fields[] = $field;
        }
        return $fields;
    }

    public static function has_pages($form){
        return sizeof(self::get_fields_by_type($form, array("page"))) > 0;
    }

    public static function get_product_fields_by_type($form, $types, $product_id){
        $fields = array();
        for($i=0, $count=sizeof($form["fields"]); $i<$count; $i++){
            $field = $form["fields"][$i];
            if(in_array($field["type"], $types) && $field["productField"] == $product_id){
                $fields[] = $field;
            }
        }
        return $fields;
    }

    private static function get_month_dropdown($name="", $id="", $selected_value="", $tabindex="", $disabled_text=""){
        return self::get_number_dropdown($name, $id, $selected_value, $tabindex, $disabled_text, __("Month", "gravityforms"), 1, 12);
    }

    private static function get_day_dropdown($name="", $id="", $selected_value="", $tabindex="", $disabled_text=""){
        return self::get_number_dropdown($name, $id, $selected_value, $tabindex, $disabled_text, __("Day", "gravityforms"), 1, 31);
    }

    private static function get_year_dropdown($name="", $id="", $selected_value="", $tabindex="", $disabled_text=""){
        $year_min = apply_filters("gform_date_min_year", "1920");
        $year_max = apply_filters("gform_date_max_year", date("Y") + 1);
        return self::get_number_dropdown($name, $id, $selected_value, $tabindex, $disabled_text, __("Year", "gravityforms"), $year_max, $year_min);
    }

    private static function get_number_dropdown($name, $id, $selected_value, $tabindex, $disabled_text, $placeholder, $start_number, $end_number){
        $str = "<select name='{$name}' id='{$id}' {$tabindex} {$disabled_text} >";
        if($placeholder !== false)
            $str .= "<option value=''>{$placeholder}</option>";

        $increment = $start_number < $end_number ? 1 : -1;

        for($i=$start_number; $i!= ($end_number + $increment); $i += $increment){
            $selected = intval($i) == intval($selected_value) ? "selected='selected'" : "";
            $str .= "<option value='{$i}' {$selected}>{$i}</option>";
        }
        $str .= "</select>";
        return $str;
    }

    private static function get_logic_event($field, $event){
        if(empty($field["conditionalLogicFields"]) || IS_ADMIN)
            return "";

        switch($event){
            case "keyup" :
                return "onchange='gf_apply_rules(" . $field["formId"] . "," . GFCommon::json_encode($field["conditionalLogicFields"]) . ");' onkeyup='clearTimeout(__gf_timeout_handle); __gf_timeout_handle = setTimeout(\"gf_apply_rules(" . $field["formId"] . "," . GFCommon::json_encode($field["conditionalLogicFields"]) . ")\", 300);'";
            break;

            case "click" :
                return "onclick='gf_apply_rules(" . $field["formId"] . "," . GFCommon::json_encode($field["conditionalLogicFields"]) . ");'";
            break;

            case "change" :
                return "onchange='gf_apply_rules(" . $field["formId"] . "," . GFCommon::json_encode($field["conditionalLogicFields"]) . ");'";
            break;
        }
    }

    public static function has_field_calculation($field) {

        if($field['type'] == 'number') {
            return rgar($field, 'enableCalculation') && rgar($field, 'calculationFormula');
        }

        return RGFormsModel::get_input_type($field) == 'calculation';
    }

    public static function get_field_input($field, $value="", $lead_id=0, $form_id=0){

        $id = $field["id"];
        $field_id = IS_ADMIN || $form_id == 0 ? "input_$id" : "input_" . $form_id . "_$id";
        $form_id = IS_ADMIN && empty($form_id) ? rgget("id") : $form_id;

        $size = rgar($field, "size");
        $disabled_text = (IS_ADMIN && RG_CURRENT_VIEW != "entry") ? "disabled='disabled'" : "";
        $class_suffix = RG_CURRENT_VIEW == "entry" ? "_admin" : "";
        $class = $size . $class_suffix;

        $currency = "";
        if(RG_CURRENT_VIEW == "entry"){
            $lead = RGFormsModel::get_lead($lead_id);
            $post_id = $lead["post_id"];
            $post_link = "";
            if(is_numeric($post_id) && self::is_post_field($field)){
                $post_link = "You can <a href='post.php?action=edit&post=$post_id'>edit this post</a> from the post page.";
            }
            $currency = $lead["currency"];
        }

        $field_input = apply_filters("gform_field_input", "", $field, $value, $lead_id, $form_id);
        if($field_input)
            return $field_input;

        //product fields are not editable
        if(RG_CURRENT_VIEW == "entry" && self::is_product_field($field["type"]))
            return "<div class='ginput_container'>" . __("Product fields are not editable", "gravityforms") . "</div>";

        else if(RG_CURRENT_VIEW == "entry" && $field["type"] == "donation")
            return "<div class='ginput_container'>" . __("Donations are not editable", "gravityforms") . "</div>";

        // add categories as choices for Post Category field
        if($field['type'] == 'post_category')
            $field = self::add_categories_as_choices($field, $value);

        $max_length = "";
        $html5_attributes = "";

        switch(RGFormsModel::get_input_type($field)){

            case "total" :
                if(RG_CURRENT_VIEW == "entry")
                    return "<div class='ginput_container'><input type='text' name='input_{$id}' value='{$value}' /></div>";
                else
                    return "<div class='ginput_container'><span class='ginput_total ginput_total_{$form_id}'>" . self::to_money("0") . "</span><input type='hidden' name='input_{$id}' id='{$field_id}' class='gform_hidden'/></div>";
            break;

            case "calculation" :
            case "singleproduct" :

                $product_name = !is_array($value) || empty($value[$field["id"] . ".1"]) ? esc_attr($field["label"]) : esc_attr($value[$field["id"] . ".1"]);
                $price = !is_array($value) || empty($value[$field["id"] . ".2"]) ? rgget("basePrice", $field) : esc_attr($value[$field["id"] . ".2"]);
                $quantity = is_array($value) ? esc_attr($value[$field["id"] . ".3"]) : "";

                if(empty($price))
                    $price = 0;

                $form = RGFormsModel::get_form_meta($form_id);
                $has_quantity = sizeof(GFCommon::get_product_fields_by_type($form, array("quantity"), $field["id"])) > 0;
                if($has_quantity)
                    $field["disableQuantity"] = true;

                $quantity_field = "";
                if(IS_ADMIN){
                    $style = rgget("disableQuantity", $field) ? "style='display:none;'" : "";
                    $quantity_field  = " <span class='ginput_quantity_label' {$style}>" . apply_filters("gform_product_quantity_{$form_id}",apply_filters("gform_product_quantity",__("Quantity:", "gravityforms"), $form_id), $form_id) . "</span> <input type='text' name='input_{$id}.3' value='{$quantity}' id='ginput_quantity_{$form_id}_{$field["id"]}' class='ginput_quantity' size='10' />";
                }
                else if(!rgget("disableQuantity", $field)){
                    $tabindex = self::get_tabindex();
                    $quantity_field .= " <span class='ginput_quantity_label'>" . apply_filters("gform_product_quantity_{$form_id}",apply_filters("gform_product_quantity",__("Quantity:", "gravityforms"), $form_id), $form_id) . "</span> <input type='text' name='input_{$id}.3' value='{$quantity}' id='ginput_quantity_{$form_id}_{$field["id"]}' class='ginput_quantity' size='10' {$tabindex}/>";
                }
                else{
                    if(!is_numeric($quantity))
                        $quantity = 1;

                    if(!$has_quantity){
                        $quantity_field .= "<input type='hidden' name='input_{$id}.3' value='{$quantity}' class='ginput_quantity_{$form_id}_{$field["id"]} gform_hidden' />";
                    }
                }

                return "<div class='ginput_container'><input type='hidden' name='input_{$id}.1' value='{$product_name}' class='gform_hidden' /><span class='ginput_product_price_label'>" . apply_filters("gform_product_price_{$form_id}", apply_filters("gform_product_price",__("Price", "gravityforms"), $form_id), $form_id) . ":</span> <span class='ginput_product_price' id='{$field_id}'>" . esc_html(GFCommon::to_money($price, $currency)) ."</span><input type='hidden' name='input_{$id}.2' id='ginput_base_price_{$form_id}_{$field["id"]}' class='gform_hidden' value='" . esc_attr($price) . "'/>{$quantity_field}</div>";

            break;

            case "hiddenproduct" :

                $form = RGFormsModel::get_form_meta($form_id);
                $has_quantity_field = sizeof(GFCommon::get_product_fields_by_type($form, array("quantity"), $field["id"])) > 0;

                $product_name = !is_array($value) || empty($value[$field["id"] . ".1"]) ? esc_attr($field["label"]) : esc_attr($value[$field["id"] . ".1"]);
                $quantity = is_array($value) ? esc_attr($value[$field["id"] . ".3"]) : "1";
                $price = !is_array($value) || empty($value[$field["id"] . ".2"]) ? rgget("basePrice", $field) : esc_attr($value[$field["id"] . ".2"]);
                if(empty($price))
                    $price = 0;

                $quantity_field = $has_quantity_field ? "" : "<input type='hidden' name='input_{$id}.3' value='" . esc_attr($quantity) . "' id='ginput_quantity_{$form_id}_{$field["id"]}' class='gform_hidden' />";
                $product_name_field = "<input type='hidden' name='input_{$id}.1' value='{$product_name}' class='gform_hidden' />";

                $field_type = IS_ADMIN ? "text" : "hidden";

                return $quantity_field . $product_name_field . sprintf("<input name='input_%d.2' id='ginput_base_price_{$form_id}_{$field["id"]}' type='{$field_type}' value='%s' class='gform_hidden ginput_amount' %s/>", $id, esc_attr($price), $disabled_text);

            break;

            case "singleshipping" :

                $price = !empty($value) ? $value : rgget("basePrice", $field);
                if(empty($price))
                    $price = 0;

                return "<div class='ginput_container'><input type='hidden' name='input_{$id}' value='{$price}' class='gform_hidden'/><span class='ginput_shipping_price' id='{$field_id}'>" . GFCommon::to_money($price, $currency) ."</span></div>";

            break;

            case "website":
                $is_html5 = RGFormsModel::is_html5_enabled();
                $value = empty($value) && !$is_html5 ? "http://" : $value;
                $html_input_type = $is_html5 ? "url" : "text";
                $html5_attributes = $is_html5 ? "placeholder='http://'" : "";
            case "text":
                if(empty($html_input_type))
                    $html_input_type = "text";

                if(rgget("enablePasswordInput", $field) && RG_CURRENT_VIEW != "entry")
                    $html_input_type = "password";

                if(is_numeric(rgget("maxLength", $field)))
                    $max_length = "maxlength='{$field["maxLength"]}'";

                if(!empty($post_link))
                    return $post_link;

                $logic_event = self::get_logic_event($field, "keyup");

                $tabindex = self::get_tabindex();
                return sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='%s' value='%s' class='%s' $max_length $tabindex $logic_event $html5_attributes %s/></div>", $id, $field_id, $html_input_type, esc_attr($value), esc_attr($class), $disabled_text);
            break;

            case "email":

                if(!empty($post_link))
                    return $post_link;

                $html_input_type = RGFormsModel::is_html5_enabled() ? "email" : "text";

                if(IS_ADMIN && RG_CURRENT_VIEW != "entry"){
                    $single_style = rgget("emailConfirmEnabled", $field) ? "style='display:none;'" : "";
                    $confirm_style = rgget("emailConfirmEnabled", $field) ? "" : "style='display:none;'";
                    return "<div class='ginput_container ginput_single_email' {$single_style}><input name='input_{$id}' type='{$html_input_type}' class='" . esc_attr($class) . "' disabled='disabled' /></div><div class='ginput_complex ginput_container ginput_confirm_email' {$confirm_style} id='{$field_id}_container'><span id='{$field_id}_1_container' class='ginput_left'><input type='text' name='input_{$id}' id='{$field_id}' disabled='disabled' /><label for='{$field_id}'>" . apply_filters("gform_email_{$form_id}", apply_filters("gform_email",__("Enter Email", "gravityforms"), $form_id), $form_id) . "</label></span><span id='{$field_id}_2_container' class='ginput_right'><input type='text' name='input_{$id}_2' id='{$field_id}_2' disabled='disabled' /><label for='{$field_id}_2'>" . apply_filters("gform_email_confirm_{$form_id}", apply_filters("gform_email_confirm",__("Confirm Email", "gravityforms"), $form_id), $form_id) . "</label></span></div>";
                }
                else{
                    $logic_event = self::get_logic_event($field, "keyup");

                    if(rgget("emailConfirmEnabled", $field) && RG_CURRENT_VIEW != "entry"){
                        $first_tabindex = self::get_tabindex();
                        $last_tabindex = self::get_tabindex();
                        return "<div class='ginput_complex ginput_container' id='{$field_id}_container'><span id='{$field_id}_1_container' class='ginput_left'><input type='{$html_input_type}' name='input_{$id}' id='{$field_id}' value='" . esc_attr($value) . "' {$first_tabindex} {$logic_event} {$disabled_text}/><label for='{$field_id}'>" . apply_filters("gform_email_{$form_id}", apply_filters("gform_email",__("Enter Email", "gravityforms"), $form_id), $form_id) . "</label></span><span id='{$field_id}_2_container' class='ginput_right'><input type='{$html_input_type}' name='input_{$id}_2' id='{$field_id}_2' value='" . esc_attr(rgpost("input_" . $id ."_2")) . "' {$last_tabindex} {$disabled_text}/><label for='{$field_id}_2'>" . apply_filters("gform_email_confirm_{$form_id}", apply_filters("gform_email_confirm",__("Confirm Email", "gravityforms"), $form_id), $form_id) . "</label></span></div>";
                    }
                    else{
                        $tabindex = self::get_tabindex();
                        return sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='%s' value='%s' class='%s' {$max_length} {$tabindex} {$html5_attributes} {$logic_event} %s/></div>", $id, $field_id, $html_input_type, esc_attr($value), esc_attr($class), $disabled_text);
                    }
                }

            break;
            case "honeypot":
                $autocomplete = RGFormsModel::is_html5_enabled() ? "autocomplete='off'" : "";
                return "<div class='ginput_container'><input name='input_{$id}' id='{$field_id}' type='text' value='' {$autocomplete}/></div>";
            break;

            case "hidden" :
                if(!empty($post_link))
                    return $post_link;

                $field_type = IS_ADMIN ? "text" : "hidden";
                $class_attribute = IS_ADMIN ? "" : "class='gform_hidden'";

                return sprintf("<input name='input_%d' id='%s' type='$field_type' $class_attribute value='%s' %s/>", $id, $field_id, esc_attr($value), $disabled_text);
            break;

            case "html" :
                $content = IS_ADMIN ? "<img class='gfield_html_block' src='" . self::get_base_url() . "/images/gf_html_admin_placeholder.jpg' alt='HTML Block'/>" : $field["content"];
                $content = GFCommon::replace_variables_prepopulate($content); //adding support for merge tags
                $content = do_shortcode($content); //adding support for shortcodes
                return $content;
            break;

            case "adminonly_hidden" :
                if(!is_array($field["inputs"]))
                    return sprintf("<input name='input_%d' id='%s' class='gform_hidden' type='hidden' value='%s'/>", $id, $field_id, esc_attr($value));

                $fields = "";
                foreach($field["inputs"] as $input){
                    $fields .= sprintf("<input name='input_%s' class='gform_hidden' type='hidden' value='%s'/>", $input["id"], esc_attr(rgar($value, $input["id"])));
                }
                return $fields;
            break;

            case "number" :
                if(!empty($post_link))
                    return $post_link;

                $instruction = "";
                $read_only = "";

                if(!IS_ADMIN){

                    if(GFCommon::has_field_calculation($field)) {

                        // calculation-enabled fields should be read only
                        $read_only = 'readonly="readonly"';

                    } else {

                        $message = self::get_range_message($field);
                        $validation_class = $field["failed_validation"] ? "validation_message" : "";

                        if(!$field["failed_validation"] && !empty($message) && empty($field["errorMessage"]))
                            $instruction = "<div class='instruction $validation_class'>" . $message . "</div>";

                    }

                }
                $is_html5 = RGFormsModel::is_html5_enabled();
                $html_input_type = $is_html5 && !GFCommon::has_field_calculation($field) ? "number" : "text"; // chrome does not allow number fields to have commas, calculations display numbers with commas
                $step_attr = $is_html5 ? "step='any'" : "";

                $logic_event = self::get_logic_event($field, "keyup");

                $tabindex = self::get_tabindex();
                return sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='{$html_input_type}' {$step_attr} value='%s' class='%s' {$tabindex} {$logic_event} {$read_only} %s/>%s</div>", $id, $field_id, esc_attr($value), esc_attr($class),  $disabled_text, $instruction);

            case "donation" :
                $tabindex = self::get_tabindex();
                return sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='text' value='%s' class='%s ginput_donation_amount' $tabindex %s/></div>", $id, $field_id, esc_attr($value), esc_attr($class),  $disabled_text);

            case "price" :
                $logic_event = self::get_logic_event($field, "keyup");

                $tabindex = self::get_tabindex();
                return sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='text' value='%s' class='%s ginput_amount' {$tabindex} {$logic_event} %s/></div>", $id, $field_id, esc_attr($value), esc_attr($class),  $disabled_text);

            case "phone" :
                if(!empty($post_link))
                    return $post_link;

                $instruction = $field["phoneFormat"] == "standard" ? __("Phone format:", "gravityforms") . " (###)###-####" : "";
                $instruction_div = rgget("failed_validation", $field) ? "<div class='instruction validation_message'>$instruction</div>" : "";
                $html_input_type = RGFormsModel::is_html5_enabled() ? "tel" : "text";
                $logic_event = self::get_logic_event($field, "keyup");

                $tabindex = self::get_tabindex();
                return sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='{$html_input_type}' value='%s' class='%s' {$tabindex} {$logic_event} %s/>{$instruction_div}</div>", $id, $field_id, esc_attr($value), esc_attr($class), $disabled_text);

            case "textarea":
                $max_chars = "";
                $logic_event = self::get_logic_event($field, "keyup");

                $tabindex = self::get_tabindex();
                return sprintf("<div class='ginput_container'><textarea name='input_%d' id='%s' class='textarea %s' {$tabindex} {$logic_event} %s rows='10' cols='50'>%s</textarea></div>{$max_chars}", $id, $field_id, esc_attr($class), $disabled_text, esc_html($value));

            case "post_title":
            case "post_tags":
            case "post_custom_field":
                $tabindex = self::get_tabindex();
                $logic_event = self::get_logic_event($field, "keyup");

                return !empty($post_link) ? $post_link : sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='text' value='%s' class='%s' {$tabindex} {$logic_event} %s/></div>", $id, $field_id, esc_attr($value), esc_attr($class), $disabled_text);
            break;

            case "post_content":
            case "post_excerpt":
                $max_chars = "";
                $logic_event = self::get_logic_event($field, "keyup");

                $tabindex = self::get_tabindex();
                return !empty($post_link) ? $post_link : sprintf("<div class='ginput_container'><textarea name='input_%d' id='%s' class='textarea %s' {$tabindex} {$logic_event} %s rows='10' cols='50'>%s</textarea></div>{$max_chars}", $id, $field_id, esc_attr($class), $disabled_text, esc_html($value));
            break;

            case "post_category" :
                if(!empty($post_link))
                    return $post_link;

                if(rgget("displayAllCategories", $field) && !IS_ADMIN){
                    $default_category = rgget("categoryInitialItemEnabled", $field) ? "-1" : get_option('default_category');
                    $selected = empty($value) ? $default_category : $value;
                    $args = array('echo' => 0, 'selected' => $selected, "class" => esc_attr($class) . " gfield_select",  'hide_empty' => 0, 'name' => "input_$id", 'orderby' => 'name', 'hierarchical' => true );
                    if(self::$tab_index > 0)
                        $args["tab_index"] = self::$tab_index++;

                    if(rgget("categoryInitialItemEnabled", $field)){
                        $args["show_option_none"] = empty($field["categoryInitialItem"]) ? " " : $field["categoryInitialItem"];
                    }

                    return "<div class='ginput_container'>" . wp_dropdown_categories($args) . "</div>";
                }
                else{
                    $tabindex = self::get_tabindex();
                    if(is_array(rgar($field, "choices")))
                        usort($field["choices"], create_function('$a,$b', 'return strcmp($a["text"], $b["text"]);'));

                    $choices = self::get_select_choices($field, $value);

                    //Adding first option
                    if(rgget("categoryInitialItemEnabled", $field)){
                        $selected = empty($value) ? "selected='selected'" : "";
                        $choices = "<option value='-1' {$selected}>{$field["categoryInitialItem"]}</option>" . $choices;
                    }

                    return sprintf("<div class='ginput_container'><select name='input_%d' id='%s' class='%s gfield_select' {$tabindex} %s>%s</select></div>", $id, $field_id, esc_attr($class), $disabled_text, $choices);
                }
            break;

            case "post_image" :
                if(!empty($post_link))
                    return $post_link;

                $title = esc_attr(rgget($field["id"] . ".1", $value));
                $caption = esc_attr(rgget($field["id"] . ".4", $value));
                $description = esc_attr(rgget($field["id"] . ".7", $value));

                //hidding meta fields for admin
                $hidden_style = "style='display:none;'";
                $title_style = !rgget("displayTitle", $field) && IS_ADMIN ? $hidden_style : "";
                $caption_style = !rgget("displayCaption", $field) && IS_ADMIN ? $hidden_style : "";
                $description_style = !rgget("displayDescription", $field) && IS_ADMIN ? $hidden_style : "";
                $file_label_style = IS_ADMIN && !(rgget("displayTitle", $field) || rgget("displayCaption", $field) || rgget("displayDescription", $field)) ? $hidden_style : "";

                $hidden_class = $preview = "";
                $file_info = RGFormsModel::get_temp_filename($form_id, "input_{$id}");
                if($file_info){
                    $hidden_class = " gform_hidden";
                    $file_label_style = $hidden_style;
                    $preview = "<span class='ginput_preview'><strong>" . esc_html($file_info["uploaded_filename"]) . "</strong> | <a href='javascript:;' onclick='gformDeleteUploadedFile({$form_id}, {$id});'>" . __("delete", "gravityforms") . "</a></span>";
                }

                //in admin, render all meta fields to allow for immediate feedback, but hide the ones not selected
                $file_label = (IS_ADMIN || rgget("displayTitle", $field) || rgget("displayCaption", $field) || rgget("displayDescription", $field)) ? "<label for='$field_id' class='ginput_post_image_file' $file_label_style>" . apply_filters("gform_postimage_file_{$form_id}",apply_filters("gform_postimage_file",__("File", "gravityforms"), $form_id), $form_id) . "</label>" : "";

                $tabindex = self::get_tabindex();
                $upload = sprintf("<span class='ginput_full$class_suffix'>{$preview}<input name='input_%d' id='%s' type='file' value='%s' class='%s' $tabindex %s/>$file_label</span>", $id, $field_id, esc_attr($value), esc_attr($class . $hidden_class), $disabled_text);

                $tabindex = self::get_tabindex();
                $title_field = rgget("displayTitle", $field) || IS_ADMIN ? sprintf("<span class='ginput_full$class_suffix ginput_post_image_title' $title_style><input type='text' name='input_%d.1' id='%s_1' value='%s' $tabindex %s/><label for='%s_1'>" . apply_filters("gform_postimage_title_{$form_id}",apply_filters("gform_postimage_title",__("Title", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $title, $disabled_text, $field_id) : "";

                $tabindex = self::get_tabindex();
                $caption_field = rgget("displayCaption", $field) || IS_ADMIN ? sprintf("<span class='ginput_full$class_suffix ginput_post_image_caption' $caption_style><input type='text' name='input_%d.4' id='%s_4' value='%s' $tabindex %s/><label for='%s_4'>" . apply_filters("gform_postimage_caption_{$form_id}",apply_filters("gform_postimage_caption",__("Caption", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $caption, $disabled_text, $field_id) : "";

                $tabindex = self::get_tabindex();
                $description_field = rgget("displayDescription", $field) || IS_ADMIN? sprintf("<span class='ginput_full$class_suffix ginput_post_image_description' $description_style><input type='text' name='input_%d.7' id='%s_7' value='%s' $tabindex %s/><label for='%s_7'>" . apply_filters("gform_postimage_description_{$form_id}",apply_filters("gform_postimage_description",__("Description", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $description, $disabled_text, $field_id) : "";

                return "<div class='ginput_complex$class_suffix ginput_container'>" . $upload . $title_field . $caption_field . $description_field . "</div>";

            break;

            case "multiselect" :
                if(!empty($post_link))
                    return $post_link;

                $placeholder = rgar($field, "enableEnhancedUI") ? "data-placeholder='" . esc_attr(apply_filters("gform_multiselect_placeholder_{$form_id}", apply_filters("gform_multiselect_placeholder", __("Click to select...", "gravityforms"), $form_id), $form_id)) . "'" : "";
                $logic_event = self::get_logic_event($field, "keyup");
                $css_class = trim(esc_attr($class) . " gfield_select");
                $size = rgar($field, "multiSelectSize");
                if(empty($size))
                    $size = 7;

                $tabindex = self::get_tabindex();
                return sprintf("<div class='ginput_container'><select multiple='multiple' {$placeholder} size='{$size}' name='input_%d[]' id='%s' {$logic_event} class='%s' $tabindex %s>%s</select></div>", $id, $field_id, $css_class, $disabled_text, self::get_select_choices($field, $value));

            break;

            case "select" :
                if(!empty($post_link))
                    return $post_link;

                $logic_event = self::get_logic_event($field, "change");
                $css_class = trim(esc_attr($class) . " gfield_select");
                $tabindex = self::get_tabindex();
                return sprintf("<div class='ginput_container'><select name='input_%d' id='%s' $logic_event class='%s' $tabindex %s>%s</select></div>", $id, $field_id, $css_class, $disabled_text, self::get_select_choices($field, $value));

            case "checkbox" :
                if(!empty($post_link))
                    return $post_link;

                return sprintf("<div class='ginput_container'><ul class='gfield_checkbox' id='%s'>%s</ul></div>", $field_id, self::get_checkbox_choices($field, $value, $disabled_text));

            case "radio" :
                if(!empty($post_link))
                    return $post_link;

                return sprintf("<div class='ginput_container'><ul class='gfield_radio' id='%s'>%s</ul></div>", $field_id, self::get_radio_choices($field, $value, $disabled_text));

            case "password" :

                $first_tabindex = self::get_tabindex();
                $last_tabindex = self::get_tabindex();

                $strength_style = !rgar($field,"passwordStrengthEnabled") ? "style='display:none;'" : "";
                $strength = rgar($field,"passwordStrengthEnabled") || IS_ADMIN ? "<div id='{$field_id}_strength_indicator' class='gfield_password_strength' {$strength_style}>" . __("Strength indicator", "gravityforms") . "</div><input type='hidden' class='gform_hidden' id='{$field_id}_strength' name='input_{$id}_strength' />" : "";

                $action = !IS_ADMIN ? "gformShowPasswordStrength(\"$field_id\");" : "";
                $onchange= rgar($field,"passwordStrengthEnabled") ? "onchange='{$action}'" : "";
                $onkeyup = rgar($field,"passwordStrengthEnabled") ? "onkeyup='{$action}'" : "";

                $pass = RGForms::post("input_" . $id ."_2");
                return sprintf("<div class='ginput_complex$class_suffix ginput_container' id='{$field_id}_container'><span id='" . $field_id . "_1_container' class='ginput_left'><input type='password' name='input_%d' id='%s' {$onkeyup} {$onchange} value='%s' $first_tabindex %s/><label for='%s'>" . apply_filters("gform_password_{$form_id}", apply_filters("gform_password",__("Enter Password", "gravityforms"), $form_id), $form_id) . "</label></span><span id='" . $field_id . "_2_container' class='ginput_right'><input type='password' name='input_%d_2' id='%s_2' {$onkeyup} {$onchange} value='%s' $last_tabindex %s/><label for='%s_2'>" . apply_filters("gform_password_confirm_{$form_id}", apply_filters("gform_password_confirm",__("Confirm Password", "gravityforms"), $form_id), $form_id) . "</label></span></div>{$strength}", $id, $field_id, esc_attr($value), $disabled_text, $field_id, $id, $field_id, esc_attr($pass), $disabled_text, $field_id);

            case "name" :
                $prefix = "";
                $first = "";
                $last = "";
                $suffix = "";
                if(is_array($value)){
                    $prefix = esc_attr(RGForms::get($field["id"] . ".2", $value));
                    $first = esc_attr(RGForms::get($field["id"] . ".3", $value));
                    $last = esc_attr(RGForms::get($field["id"] . ".6", $value));
                    $suffix = esc_attr(RGForms::get($field["id"] . ".8", $value));
                }
                switch(rgget("nameFormat", $field)){

                    case "extended" :
                        $prefix_tabindex = self::get_tabindex();
                        $first_tabindex = self::get_tabindex();
                        $last_tabindex = self::get_tabindex();
                        $suffix_tabindex = self::get_tabindex();
                        return sprintf("<div class='ginput_complex$class_suffix ginput_container' id='$field_id'><span id='" . $field_id . "_2_container' class='name_prefix'><input type='text' name='input_%d.2' id='%s_2' value='%s' $prefix_tabindex %s/><label for='%s_2'>" . apply_filters("gform_name_prefix_{$form_id}",apply_filters("gform_name_prefix",__("Prefix", "gravityforms"), $form_id), $form_id) . "</label></span><span id='" . $field_id . "_3_container' class='name_first'><input type='text' name='input_%d.3' id='%s_3' value='%s' $first_tabindex %s/><label for='%s_3'>" . apply_filters("gform_name_first_{$form_id}",apply_filters("gform_name_first",__("First", "gravityforms"), $form_id), $form_id) . "</label></span><span id='" . $field_id . "_6_container' class='name_last'><input type='text' name='input_%d.6' id='%s_6' value='%s' $last_tabindex %s/><label for='%s_6'>" . apply_filters("gform_name_last_{$form_id}", apply_filters("gform_name_last", __("Last", "gravityforms"), $form_id), $form_id) . "</label></span><span id='" . $field_id . "_8_container' class='name_suffix'><input type='text' name='input_%d.8' id='%s_8' value='%s' $suffix_tabindex %s/><label for='%s_8'>" . apply_filters("gform_name_suffix_{$form_id}", apply_filters("gform_name_suffix", __("Suffix", "gravityforms"), $form_id), $form_id) . "</label></span></div>", $id, $field_id, $prefix, $disabled_text, $field_id, $id, $field_id, $first, $disabled_text, $field_id, $id, $field_id, $last, $disabled_text, $field_id, $id, $field_id, $suffix, $disabled_text, $field_id);

                    case "simple" :
                        $tabindex = self::get_tabindex();
                        return sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='text' value='%s' class='%s' $tabindex %s/></div>", $id, $field_id, esc_attr($value), esc_attr($class), $disabled_text);

                    default :
                        $first_tabindex = self::get_tabindex();
                        $last_tabindex = self::get_tabindex();
                        return sprintf("<div class='ginput_complex$class_suffix ginput_container' id='$field_id'><span id='" . $field_id . "_3_container' class='ginput_left'><input type='text' name='input_%d.3' id='%s_3' value='%s' $first_tabindex %s/><label for='%s_3'>" . apply_filters("gform_name_first_{$form_id}", apply_filters("gform_name_first",__("First", "gravityforms"), $form_id), $form_id) . "</label></span><span id='" . $field_id . "_6_container' class='ginput_right'><input type='text' name='input_%d.6' id='%s_6' value='%s' $last_tabindex %s/><label for='%s_6'>" . apply_filters("gform_name_last_{$form_id}", apply_filters("gform_name_last",__("Last", "gravityforms"), $form_id), $form_id) . "</label></span></div>", $id, $field_id, $first, $disabled_text, $field_id, $id, $field_id, $last, $disabled_text, $field_id);
                }

            case "address" :
                $street_value ="";
                $street2_value ="";
                $city_value ="";
                $state_value ="";
                $zip_value ="";
                $country_value ="";

                if(is_array($value)){
                    $street_value = esc_attr(rgget($field["id"] . ".1",$value));
                    $street2_value = esc_attr(rgget($field["id"] . ".2",$value));
                    $city_value = esc_attr(rgget($field["id"] . ".3",$value));
                    $state_value = esc_attr(rgget($field["id"] . ".4",$value));
                    $zip_value = esc_attr(rgget($field["id"] . ".5",$value));
                    $country_value = esc_attr(rgget($field["id"] . ".6",$value));
                }

                $address_types = self::get_address_types($form_id);
                $addr_type = empty($field["addressType"]) ? "international" : $field["addressType"];
                $address_type = $address_types[$addr_type];

                $state_label = empty($address_type["state_label"]) ? __("State", "gravityforms") : $address_type["state_label"];
                $zip_label = empty($address_type["zip_label"]) ? __("Zip Code", "gravityforms") : $address_type["zip_label"];
                $hide_country = !empty($address_type["country"]) || rgget("hideCountry", $field);

                if(empty($country_value))
                    $country_value = rgget("defaultCountry", $field);

                if(empty($state_value))
                    $state_value = rgget("defaultState", $field);

                $country_list = self::get_country_dropdown($country_value);

                //changing css classes based on field format to ensure proper display
                $address_display_format = apply_filters("gform_address_display_format", "default");
                $city_location = $address_display_format == "zip_before_city" ? "right" : "left";
                $zip_location = $address_display_format != "zip_before_city" && rgar($field,"hideState") ? "right" : "left";
                $state_location = $address_display_format == "zip_before_city" ? "left" : "right";
                $country_location = rgar($field,"hideState") ? "left" : "right";

                //address field
                $tabindex = self::get_tabindex();
                $street_address = sprintf("<span class='ginput_full$class_suffix' id='" . $field_id . "_1_container'><input type='text' name='input_%d.1' id='%s_1' value='%s' $tabindex %s/><label for='%s_1' id='" . $field_id . "_1_label'>" . apply_filters("gform_address_street_{$form_id}", apply_filters("gform_address_street",__("Street Address", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $street_value, $disabled_text, $field_id);

                //address line 2 field
                $street_address2 = "";
                $style = (IS_ADMIN && rgget("hideAddress2", $field)) ? "style='display:none;'" : "";
                if(IS_ADMIN || !rgget("hideAddress2", $field)){
                    $tabindex = self::get_tabindex();
                    $street_address2 = sprintf("<span class='ginput_full$class_suffix' id='" . $field_id . "_2_container' $style><input type='text' name='input_%d.2' id='%s_2' value='%s' $tabindex %s/><label for='%s_2' id='" . $field_id . "_2_label'>" . apply_filters("gform_address_street2_{$form_id}",apply_filters("gform_address_street2",__("Address Line 2", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $street2_value, $disabled_text, $field_id);
                }

                if($address_display_format == "zip_before_city"){
                    //zip field
                    $tabindex = self::get_tabindex();
                    $zip = sprintf("<span class='ginput_{$zip_location}$class_suffix' id='" . $field_id . "_5_container'><input type='text' name='input_%d.5' id='%s_5' value='%s' $tabindex %s/><label for='%s_5' id='" . $field_id . "_5_label'>" . apply_filters("gform_address_zip_{$form_id}", apply_filters("gform_address_zip", $zip_label, $form_id), $form_id) . "</label></span>", $id, $field_id, $zip_value, $disabled_text, $field_id);

                    //city field
                    $tabindex = self::get_tabindex();
                    $city = sprintf("<span class='ginput_{$city_location}$class_suffix' id='" . $field_id . "_3_container'><input type='text' name='input_%d.3' id='%s_3' value='%s' $tabindex %s/><label for='%s_3' id='$field_id.3_label'>" . apply_filters("gform_address_city_{$form_id}", apply_filters("gform_address_city",__("City", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $city_value, $disabled_text, $field_id);

                    //state field
                    $style = (IS_ADMIN && rgget("hideState", $field)) ? "style='display:none;'" : "";
                    if(IS_ADMIN || !rgget("hideState", $field)){
                        $state_field = self::get_state_field($field, $id, $field_id, $state_value, $disabled_text, $form_id);
                        $state = sprintf("<span class='ginput_{$state_location}$class_suffix' id='" . $field_id . "_4_container' $style>$state_field<label for='%s_4' id='" . $field_id . "_4_label'>" . apply_filters("gform_address_state_{$form_id}", apply_filters("gform_address_state", $state_label, $form_id), $form_id) . "</label></span>", $field_id);
                    }
                    else{
                        $state = sprintf("<input type='hidden' class='gform_hidden' name='input_%d.4' id='%s_4' value='%s'/>", $id, $field_id, $state_value);
                    }
                }
                else{

                    //city field
                    $tabindex = self::get_tabindex();
                    $city = sprintf("<span class='ginput_{$city_location}$class_suffix' id='" . $field_id . "_3_container'><input type='text' name='input_%d.3' id='%s_3' value='%s' $tabindex %s/><label for='%s_3' id='$field_id.3_label'>" . apply_filters("gform_address_city_{$form_id}", apply_filters("gform_address_city",__("City", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $city_value, $disabled_text, $field_id);

                    //state field
                    $style = (IS_ADMIN && rgget("hideState", $field)) ? "style='display:none;'" : "";
                    if(IS_ADMIN || !rgget("hideState", $field)){
                        $state_field = self::get_state_field($field, $id, $field_id, $state_value, $disabled_text, $form_id);
                        $state = sprintf("<span class='ginput_{$state_location}$class_suffix' id='" . $field_id . "_4_container' $style>$state_field<label for='%s_4' id='" . $field_id . "_4_label'>" . apply_filters("gform_address_state_{$form_id}", apply_filters("gform_address_state", $state_label, $form_id), $form_id) . "</label></span>", $field_id);
                    }
                    else{
                        $state = sprintf("<input type='hidden' class='gform_hidden' name='input_%d.4' id='%s_4' value='%s'/>", $id, $field_id, $state_value);
                    }

                    //zip field
                    $tabindex = self::get_tabindex();
                    $zip = sprintf("<span class='ginput_{$zip_location}$class_suffix' id='" . $field_id . "_5_container'><input type='text' name='input_%d.5' id='%s_5' value='%s' $tabindex %s/><label for='%s_5' id='" . $field_id . "_5_label'>" . apply_filters("gform_address_zip_{$form_id}", apply_filters("gform_address_zip", $zip_label, $form_id), $form_id) . "</label></span>", $id, $field_id, $zip_value, $disabled_text, $field_id);

                }

                if(IS_ADMIN || !$hide_country){
                    $style = $hide_country ? "style='display:none;'" : "";
                    $tabindex = self::get_tabindex();
                    $country = sprintf("<span class='ginput_{$country_location}$class_suffix' id='" . $field_id . "_6_container' $style><select name='input_%d.6' id='%s_6' $tabindex %s>%s</select><label for='%s_6' id='" . $field_id . "_6_label'>" . apply_filters("gform_address_country_{$form_id}", apply_filters("gform_address_country",__("Country", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $disabled_text, $country_list, $field_id);
                }
                else{
                    $country = sprintf("<input type='hidden' class='gform_hidden' name='input_%d.6' id='%s_6' value='%s'/>", $id, $field_id, $country_value);
                }

                $inputs = $address_display_format == "zip_before_city" ? $street_address . $street_address2 . $zip . $city . $state . $country : $street_address . $street_address2 . $city . $state . $zip . $country;

                return "<div class='ginput_complex$class_suffix ginput_container' id='$field_id'>" . $inputs . "</div>";

            case "date" :
                if(!empty($post_link))
                    return $post_link;

                $format = empty($field["dateFormat"]) ? "mdy" : esc_attr($field["dateFormat"]);
                $field_position = substr($format, 0, 3);
                if(IS_ADMIN && RG_CURRENT_VIEW != "entry"){
                    $datepicker_display = in_array(rgget("dateType", $field), array("datefield", "datedropdown")) ? "none" : "inline";
                    $datefield_display = rgget("dateType", $field) == "datefield" ? "inline" : "none";
                    $dropdown_display = rgget("dateType", $field) == "datedropdown" ? "inline" : "none";
                    $icon_display = rgget("calendarIconType", $field) == "calendar" ? "inline" : "none";

                    $month_field = "<div class='gfield_date_month ginput_date' id='gfield_input_date_month' style='display:$datefield_display'><input name='ginput_month' type='text' disabled='disabled'/><label>" . __("MM", "gravityforms") . "</label></div>";
                    $day_field = "<div class='gfield_date_day ginput_date' id='gfield_input_date_day' style='display:$datefield_display'><input name='ginput_day' type='text' disabled='disabled'/><label>" . __("DD", "gravityforms") . "</label></div>";
                    $year_field = "<div class='gfield_date_year ginput_date' id='gfield_input_date_year' style='display:$datefield_display'><input type='text' name='ginput_year' disabled='disabled'/><label>" . __("YYYY", "gravityforms") . "</label></div>";

                    $month_dropdown = "<div class='gfield_date_dropdown_month ginput_date_dropdown' id='gfield_dropdown_date_month' style='display:$dropdown_display'>" . self::get_month_dropdown("","","","","disabled='disabled'") . "</div>";
                    $day_dropdown = "<div class='gfield_date_dropdown_day ginput_date_dropdown' id='gfield_dropdown_date_day' style='display:$dropdown_display'>" . self::get_day_dropdown("","","","","disabled='disabled'") . "</div>";
                    $year_dropdown = "<div class='gfield_date_dropdown_year ginput_date_dropdown' id='gfield_dropdown_date_year' style='display:$dropdown_display'>" . self::get_year_dropdown("","","","","disabled='disabled'") . "</div>";

                    $field_string ="<div class='ginput_container' id='gfield_input_datepicker' style='display:$datepicker_display'><input name='ginput_datepicker' type='text' /><img src='" . GFCommon::get_base_url() . "/images/calendar.png' id='gfield_input_datepicker_icon' style='display:$icon_display'/></div>";

                    switch($field_position){
                        case "dmy" :
                            $field_string .= $day_field . $month_field . $year_field . $day_dropdown . $month_dropdown . $year_dropdown;
                        break;

                        case "ymd" :
                            $field_string .= $year_field . $month_field . $day_field . $year_dropdown . $month_dropdown . $day_dropdown;
                        break;

                        default :
                            $field_string .= $month_field . $day_field . $year_field . $month_dropdown . $day_dropdown . $year_dropdown;
                        break;
                    }

                    return $field_string;
                }
                else{
                    $date_info = self::parse_date($value, $format);
                    $date_type = rgget("dateType", $field);
                    if(in_array($date_type, array("datefield", "datedropdown")))
                    {
                        switch($field_position){

                            case "dmy" :
                                $tabindex = self::get_tabindex();
                                $field_str = $date_type == "datedropdown"
                                            ? "<div class='clear-multi'><div class='gfield_date_dropdown_day ginput_container' id='{$field_id}'>" . self::get_day_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"day"), $tabindex, $disabled_text) . "</div>"
                                            : sprintf("<div class='clear-multi'><div class='gfield_date_day ginput_container' id='%s'><input type='text' maxlength='2' name='input_%d[]' id='%s_2' value='%s' $tabindex %s/><label for='%s_2'>" . __("DD", "gravityforms") . "</label></div>", $field_id, $id, $field_id, rgget("day", $date_info), $disabled_text, $field_id);

                                $tabindex = self::get_tabindex();
                                $field_str .= $date_type == "datedropdown"
                                            ? "<div class='gfield_date_dropdown_month ginput_container' id='{$field_id}'>" . self::get_month_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"month"), $tabindex, $disabled_text) . "</div>"
                                            : sprintf("<div class='gfield_date_month ginput_container' id='%s'><input type='text' maxlength='2' name='input_%d[]' id='%s_1' value='%s' $tabindex %s/><label for='%s_1'>" . __("MM", "gravityforms") . "</label></div>", $field_id, $id, $field_id, rgget("month", $date_info), $disabled_text, $field_id);

                                $tabindex = self::get_tabindex();
                                $field_str .= $date_type == "datedropdown"
                                        ? "<div class='gfield_date_dropdown_year ginput_container' id='{$field_id}'>" . self::get_year_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"year"), $tabindex, $disabled_text) . "</div></div>"
                                        : sprintf("<div class='gfield_date_year ginput_container' id='%s'><input type='text' maxlength='4' name='input_%d[]' id='%s_3' value='%s' $tabindex %s/><label for='%s_3'>" . __("YYYY", "gravityforms") . "</label></div></div>", $field_id, $id, $field_id, rgget("year", $date_info), $disabled_text, $field_id);

                            break;

                            case "ymd" :
                                $tabindex = self::get_tabindex();
                                $field_str = $date_type == "datedropdown"
                                        ? "<div class='clear-multi'><div class='gfield_date_dropdown_year ginput_container' id='{$field_id}'>" . self::get_year_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"year"), $tabindex, $disabled_text) . "</div>"
                                        : sprintf("<div class='clear-multi'><div class='gfield_date_year ginput_container' id='%s'><input type='text' maxlength='4' name='input_%d[]' id='%s_3' value='%s' $tabindex %s/><label for='%s_3'>" . __("YYYY", "gravityforms") . "</label></div>", $field_id, $id, $field_id, rgget("year", $date_info), $disabled_text, $field_id);

                                $field_str .= $date_type == "datedropdown"
                                            ? "<div class='gfield_date_dropdown_month ginput_container' id='{$field_id}'>" . self::get_month_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"month"), $tabindex, $disabled_text) . "</div>"
                                            : sprintf("<div class='gfield_date_month ginput_container' id='%s'><input type='text' maxlength='2' name='input_%d[]' id='%s_1' value='%s' $tabindex %s/><label for='%s_1'>" . __("MM", "gravityforms") . "</label></div>", $field_id, $id, $field_id, rgar($date_info,"month"), $disabled_text, $field_id);

                                $tabindex = self::get_tabindex();
                                $field_str .= $date_type == "datedropdown"
                                            ? "<div class='gfield_date_dropdown_day ginput_container' id='{$field_id}'>" . self::get_day_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"day"), $tabindex, $disabled_text) . "</div></div>"
                                            : sprintf("<div class='gfield_date_day ginput_container' id='%s'><input type='text' maxlength='2' name='input_%d[]' id='%s_2' value='%s' $tabindex %s/><label for='%s_2'>" . __("DD", "gravityforms") . "</label></div></div>", $field_id, $id, $field_id, rgar($date_info,"day"), $disabled_text, $field_id);

                            break;

                            default :
                                $tabindex = self::get_tabindex();

                                $field_str = $date_type == "datedropdown"
                                            ? "<div class='clear-multi'><div class='gfield_date_dropdown_month ginput_container' id='{$field_id}'>" . self::get_month_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"month"), $tabindex, $disabled_text) . "</div>"
                                            : sprintf("<div class='clear-multi'><div class='gfield_date_month ginput_container' id='%s'><input type='text' maxlength='2' name='input_%d[]' id='%s_1' value='%s' $tabindex %s/><label for='%s_1'>" . __("MM", "gravityforms") . "</label></div>", $field_id, $id, $field_id, rgar($date_info,"month"), $disabled_text, $field_id);

                                $tabindex = self::get_tabindex();
                                $field_str .= $date_type == "datedropdown"
                                            ? "<div class='gfield_date_dropdown_day ginput_container' id='{$field_id}'>" . self::get_day_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"day"), $tabindex, $disabled_text) . "</div>"
                                            : sprintf("<div class='gfield_date_day ginput_container' id='%s'><input type='text' maxlength='2' name='input_%d[]' id='%s_2' value='%s' $tabindex %s/><label for='%s_2'>" . __("DD", "gravityforms") . "</label></div>", $field_id, $id, $field_id, rgar($date_info,"day"), $disabled_text, $field_id);

                                $tabindex = self::get_tabindex();
                                $field_str .= $date_type == "datedropdown"
                                        ? "<div class='gfield_date_dropdown_year ginput_container' id='{$field_id}'>" . self::get_year_dropdown("input_{$id}[]", "{$field_id}_1", rgar($date_info,"year"), $tabindex, $disabled_text) . "</div></div>"
                                        : sprintf("<div class='gfield_date_year ginput_container' id='%s'><input type='text' maxlength='4' name='input_%d[]' id='%s_3' value='%s' $tabindex %s/><label for='%s_3'>" . __("YYYY", "gravityforms") . "</label></div></div>", $field_id, $id, $field_id, rgget("year", $date_info), $disabled_text, $field_id);

                            break;
                        }

                        return $field_str;
                    }
                    else
                    {
                        $value = GFCommon::date_display($value, $format);
                        $icon_class = $field["calendarIconType"] == "none" ? "datepicker_no_icon" : "datepicker_with_icon";
                        $icon_url = empty($field["calendarIconUrl"]) ? GFCommon::get_base_url() . "/images/calendar.png" : $field["calendarIconUrl"];
                        $tabindex = self::get_tabindex();
                        return sprintf("<div class='ginput_container'><input name='input_%d' id='%s' type='text' value='%s' class='datepicker %s %s %s' $tabindex %s/> </div><input type='hidden' id='gforms_calendar_icon_$field_id' class='gform_hidden' value='$icon_url'/>", $id, $field_id, esc_attr($value), esc_attr($class), $format, $icon_class, $disabled_text);
                    }
                }

            case "time" :
                if(!empty($post_link))
                    return $post_link;

                $hour = $minute = $am_selected = $pm_selected = "";

                if(!is_array($value) && !empty($value)){
                    preg_match('/^(\d*):(\d*) ?(.*)$/', $value, $matches);
                    $hour = esc_attr($matches[1]);
                    $minute = esc_attr($matches[2]);
                    $am_selected = rgar($matches,3) == "am" ? "selected='selected'" : "";
                    $pm_selected = rgar($matches,3) == "pm" ? "selected='selected'" : "";
                }
                else if(is_array($value)){
                    $hour = esc_attr($value[0]);
                    $minute = esc_attr($value[1]);
                    $am_selected = rgar($value,2) == "am" ? "selected='selected'" : "";
                    $pm_selected = rgar($value,2) == "pm" ? "selected='selected'" : "";
                }
                $hour_tabindex = self::get_tabindex();
                $minute_tabindex = self::get_tabindex();
                $ampm_tabindex = self::get_tabindex();

                $ampm_field_style = is_admin() && rgar($field, "timeFormat") == "24" ? "style='display:none;'" : "";
                $ampm_field = is_admin() || rgar($field, "timeFormat") != "24" ? "<div class='gfield_time_ampm ginput_container' {$ampm_field_style}><select name='input_{$id}[]' id='{$field_id}_3' $ampm_tabindex {$disabled_text}><option value='am' {$am_selected}>" . __("AM", "gravityforms") . "</option><option value='pm' {$pm_selected}>" . __("PM", "gravityforms") . "</option></select></div>" : "";

                return sprintf("<div class='clear-multi'><div class='gfield_time_hour ginput_container' id='%s'><input type='text' maxlength='2' name='input_%d[]' id='%s_1' value='%s' $hour_tabindex %s/> : <label for='%s_1'>" . __("HH", "gravityforms") . "</label></div><div class='gfield_time_minute ginput_container'><input type='text' maxlength='2' name='input_%d[]' id='%s_2' value='%s' $minute_tabindex %s/><label for='%s_2'>" . __("MM", "gravityforms") . "</label></div>{$ampm_field}</div>", $field_id, $id, $field_id, $hour, $disabled_text, $field_id, $id, $field_id, $minute, $disabled_text, $field_id);

            case "fileupload" :
                $tabindex = self::get_tabindex();
                $upload = sprintf("<input name='input_%d' id='%s' type='file' value='%s' size='20' class='%s' $tabindex %s/>", $id, $field_id, esc_attr($value), esc_attr($class), $disabled_text);

                if(IS_ADMIN && !empty($value)){
                    $value = esc_attr($value);
                    $preview = sprintf("<div id='preview_%d'><a href='%s' target='_blank' alt='%s' title='%s'>%s</a><a href='%s' target='_blank' alt='" . __("Download file", "gravityforms") . "' title='" . __("Download file", "gravityforms") . "'><img src='%s' style='margin-left:10px;'/></a><a href='javascript:void(0);' alt='" . __("Delete file", "gravityforms") . "' title='" . __("Delete file", "gravityforms") . "' onclick='DeleteFile(%d,%d);' ><img src='%s' style='margin-left:10px;'/></a></div>", $id, $value, $value, $value, GFCommon::truncate_url($value), $value, GFCommon::get_base_url() . "/images/download.png", $lead_id, $id, GFCommon::get_base_url() . "/images/delete.png");
                    return $preview . "<div id='upload_$id' style='display:none;'>$upload</div>";
                }
                else{
                    $file_info = RGFormsModel::get_temp_filename($form_id, "input_{$id}");
                    if($file_info && !$field["failed_validation"]){
                        $preview = "<span class='ginput_preview'><strong>" . esc_html($file_info["uploaded_filename"]) . "</strong> | <a href='javascript:;' onclick='gformDeleteUploadedFile({$form_id}, {$id});'>" . __("delete", "gravityforms") . "</a></span>";
                        return "<div class='ginput_container'>" . str_replace(" class='", " class='gform_hidden ", $upload) . " {$preview}</div>";
                    }
                    else{
                        return "<div class='ginput_container'>$upload</div>";
                    }
                }


            case "captcha" :

                switch(rgget("captchaType", $field)){
                    case "simple_captcha" :
                        $size = rgempty("simpleCaptchaSize", $field) ? "medium" : $field["simpleCaptchaSize"];
                        $captcha = self::get_captcha($field);

                        $tabindex = self::get_tabindex();

                        $dimensions = IS_ADMIN ? "" : "width='" . rgar($captcha,"width") . "' height='" . rgar($captcha,"height") . "'";
                        return "<div class='gfield_captcha_container'><img class='gfield_captcha' src='" . rgar($captcha,"url") . "' alt='' {$dimensions} /><div class='gfield_captcha_input_container simple_captcha_{$size}'><input type='text' name='input_{$id}' id='{$field_id}' {$tabindex}/><input type='hidden' name='input_captcha_prefix_{$id}' value='" . rgar($captcha,"prefix") . "' /></div></div>";
                    break;

                    case "math" :
                        $size = empty($field["simpleCaptchaSize"]) ? "medium" : $field["simpleCaptchaSize"];
                        $captcha_1 = self::get_math_captcha($field, 1);
                        $captcha_2 = self::get_math_captcha($field, 2);
                        $captcha_3 = self::get_math_captcha($field, 3);

                        $tabindex = self::get_tabindex();

                        $dimensions = IS_ADMIN ? "" : "width='{$captcha_1["width"]}' height='{$captcha_1["height"]}'";
                        return "<div class='gfield_captcha_container'><img class='gfield_captcha' src='{$captcha_1["url"]}' alt='' {$dimensions} /><img class='gfield_captcha' src='{$captcha_2["url"]}' alt='' {$dimensions} /><img class='gfield_captcha' src='{$captcha_3["url"]}' alt='' {$dimensions} /><div class='gfield_captcha_input_container math_{$size}'><input type='text' name='input_{$id}' id='input_{$field_id}' {$tabindex}/><input type='hidden' name='input_captcha_prefix_{$id}' value='{$captcha_1["prefix"]},{$captcha_2["prefix"]},{$captcha_3["prefix"]}' /></div></div>";
                    break;

                    default:

                        if(!function_exists("recaptcha_get_html")){
                            require_once(GFCommon::get_base_path() . '/recaptchalib.php');
                        }

                        $theme = empty($field["captchaTheme"]) ? "red" : esc_attr($field["captchaTheme"]);
                        $publickey = get_option("rg_gforms_captcha_public_key");
                        $privatekey = get_option("rg_gforms_captcha_private_key");
                        if(IS_ADMIN){
                            if(empty($publickey) || empty($privatekey)){
                                return "<div class='captcha_message'>" . __("To use the reCaptcha field you must first do the following:", "gravityforms") . "</div><div class='captcha_message'>1 - <a href='http://www.google.com/recaptcha/whyrecaptcha' target='_blank'>" . sprintf(__("Sign up%s for a free reCAPTCHA account", "gravityforms"), "</a>") . "</div><div class='captcha_message'>2 - " . sprintf(__("Enter your reCAPTCHA keys in the %ssettings page%s", "gravityforms"), "<a href='?page=gf_settings'>", "</a>") . "</div>";
                            }
                            else{
                                return "<div class='ginput_container'><img class='gfield_captcha' src='" . GFCommon::get_base_url() . "/images/captcha_$theme.jpg' alt='reCAPTCHA' title='reCAPTCHA'/></div>";
                            }
                        }
                        else{
                            $language = empty($field["captchaLanguage"]) ? "en" : esc_attr($field["captchaLanguage"]);

                            $options = "<script type='text/javascript'>" . apply_filters("gform_cdata_open", "") . " var RecaptchaOptions = {theme : '$theme'}; if(parseInt('" . self::$tab_index . "') > 0) {RecaptchaOptions.tabindex = " . self::$tab_index++ . ";}" .
                            apply_filters("gform_recaptcha_init_script", "", $form_id, $field) . apply_filters("gform_cdata_close", "") . "</script>";

                            $is_ssl = !empty($_SERVER['HTTPS']);
                            return $options . "<div class='ginput_container' id='$field_id'>" . recaptcha_get_html($publickey, null, $is_ssl, $language) . "</div>";
                        }
                }
            break;

            case "creditcard" :
                $card_number = "";
                $card_name = "";
                $expiration_date = "";
                $expiration_month = "";
                $expiration_year = "";
                $security_code = "";

                if(is_array($value)){
                    $card_number = esc_attr(rgget($field["id"] . ".1",$value));
                    $card_name = esc_attr(rgget($field["id"] . ".5",$value));
                    $expiration_date = rgget($field["id"] . ".2",$value);
                    if(!is_array($expiration_date) && !empty($expiration_date))
                        $expiration_date = explode("/", $expiration_date);

                    if(is_array($expiration_date) && count($expiration_date) == 2){
                        $expiration_month = $expiration_date[0];
                        $expiration_year = $expiration_date[1];
                    }

                    $security_code = esc_attr(rgget($field["id"] . ".3",$value));
                }

                $action = !IS_ADMIN ? "gformMatchCard(\"{$field_id}_1\");" : "";

                $onchange= "onchange='{$action}'";
                $onkeyup = "onkeyup='{$action}'";

                $card_icons = '';
                $cards = GFCommon::get_card_types();
                $card_style = rgar($field, 'creditCardStyle') ? rgar($field, 'creditCardStyle') : 'style1';

                foreach($cards as $card) {

                    $style = "";
                    if(self::is_card_supported($field, $card["slug"])){
                        $print_card = true;
                    }
                    else if (IS_ADMIN){
                        $print_card = true;
                        $style = "style='display:none;'";
                    }
                    else{
                        $print_card = false;
                    }

                    if($print_card){
                        $card_icons .= "<div class='gform_card_icon gform_card_icon_{$card['slug']}' {$style}>{$card['name']}</div>";
                    }
                }

                $card_icons = "<div class='gform_card_icon_container gform_card_icon_{$card_style}'>{$card_icons}</div>";

                //card number fields
                $tabindex = self::get_tabindex();
                $card_field =   sprintf("<span class='ginput_full{$class_suffix}' id='{$field_id}_1_container'>{$card_icons}<input type='text' name='input_%d.1' id='%s_1' value='%s' {$tabindex} %s {$onchange} {$onkeyup} /><label for='%s_1' id='{$field_id}_1_label'>" . apply_filters("gform_card_number_{$form_id}", apply_filters("gform_card_number",__("Card Number", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $card_number, $disabled_text, $field_id);

                //expiration date field
                $expiration_field =  "<span class='ginput_full{$class_suffix} ginput_cardextras' id='{$field_id}_2_container'>".

                				    "<span class='ginput_cardinfo_left{$class_suffix}' id='{$field_id}_2_container'>".

                                     "<span class='ginput_card_expiration_container'>".

                                     // month selector

                                     "<select name='input_{$id}.2[]' id='{$field_id}_2_month' " . self::get_tabindex() . " {$disabled_text} class='ginput_card_expiration ginput_card_expiration_month'>" . self::get_expiration_months($expiration_month) . "</select>".

                                     // year selector

                                     "<select name='input_{$id}.2[]' id='{$field_id}_2_year' " . self::get_tabindex() . " {$disabled_text} class='ginput_card_expiration ginput_card_expiration_year'>" . self::get_expiration_years($expiration_year) . "</select>".

                                     // label for the expiration fields

                                     "<label for='{$field_id}_2_month' >" . apply_filters("gform_card_expiration_{$form_id}", apply_filters("gform_card_expiration",__("Expiration Date", "gravityforms"), $form_id), $form_id) . "</label>".

                                     "</span>".
                                     "</span>";

               //security code field
                $tabindex = self::get_tabindex();
                $security_field = "<span class='ginput_cardinfo_right{$class_suffix}' id='{$field_id}_2_container'>".
                				"<input type='text' name='input_{$id}.3' id='{$field_id}_3' {$tabindex} {$disabled_text} class='ginput_card_security_code' value='{$security_code}' />".
                				"<span class='ginput_card_security_code_icon'>&nbsp;</span>".
                                    "<label for='{$field_id}_3' >" . apply_filters("gform_card_security_code_{$form_id}", apply_filters("gform_card_security_code",__("Security Code", "gravityforms"), $form_id), $form_id) . "</label>".

                                    "</span>".
                                    "</span>";

                $tabindex = self::get_tabindex();
                $card_name_field = sprintf("<span class='ginput_full{$class_suffix}' id='{$field_id}_5_container'><input type='text' name='input_%d.5' id='%s_5' value='%s' {$tabindex} %s /><label for='%s_5' id='{$field_id}_5_label'>" . apply_filters("gform_card_name_{$form_id}", apply_filters("gform_card_name",__("Cardholder Name", "gravityforms"), $form_id), $form_id) . "</label></span>", $id, $field_id, $card_name, $disabled_text, $field_id);

                return "<div class='ginput_complex{$class_suffix} ginput_container' id='{$field_id}'>" . $card_field . $expiration_field . $security_field . $card_name_field . " </div>";

            break;

            case "list" :

                if(!empty($value))
                    $value = maybe_unserialize($value);

                if(!is_array($value))
                    $value = array(array());

                $has_columns = is_array(rgar($field, "choices"));
                $columns = $has_columns ? rgar($field, "choices") : array(array());

                $list = "<div class='ginput_container ginput_list'>" .
                        "<table class='gfield_list'>";

                $class_attr = "";
                if($has_columns){

                    $list .= "<colgroup>";
                    $colnum = 1;
                    foreach($columns as $column){
                        $odd_even = ($colnum % 2) == 0 ? "even" : "odd";
                        $list .= "<col id='gfield_list_{$field["id"]}_col{$colnum}' class='gfield_list_col_{$odd_even}'></col>";
                        $colnum++;
                    }
                    $list .= "</colgroup>";

                    $list .= "<thead><tr>";
                    foreach($columns as $column){
                        $list .= "<th>" . esc_html($column["text"]) . "</th>";
                    }
                    $list .= "<th>&nbsp;</th></tr></thead>";
                }
                else{
                    $list .= "<colgroup><col id='gfield_list_{$field["id"]}_col1' class='gfield_list_col_odd'></col></colgroup>";
                }

                $delete_display = count($value) == 1 ? "visibility:hidden;" : "";
                $maxRow = intval(rgar($field, "maxRows"));
                $disabled_icon_class = !empty($maxRow) && count($value) >= $maxRow ? "gfield_icon_disabled" : "";

                $list .= "<tbody>";
                $rownum = 1;
                foreach($value as $item){

                    $odd_even = ($rownum % 2) == 0 ? "even" : "odd";

                    $list .= "<tr class='gfield_list_row_{$odd_even}'>";
                    $colnum = 1;
                    foreach($columns as $column){

                        //getting value. taking into account columns being added/removed from form meta
                        if(is_array($item)){
                            if($has_columns){
                                $val = rgar($item, $column["text"]);
                            }
                            else{
                                 $vals = array_values($item);
                                 $val = rgar($vals, 0);
                            }
                        }
                        else{
                            $val = $colnum == 1 ? $item : "";
                        }

                        $list .= "<td class='gfield_list_cell gfield_list_{$field["id"]}_cell{$colnum}'>" . self::get_list_input($field, $has_columns, $column, $val, $form_id) . "</td>";
                        $colnum++;
                    }

                    $add_icon = !rgempty("addIconUrl", $field) ? $field["addIconUrl"] : GFCommon::get_base_url() . "/images/add.png";
                    $delete_icon = !rgempty("deleteIconUrl", $field) ? $field["deleteIconUrl"] : GFCommon::get_base_url() . "/images/remove.png";

                    $on_click = IS_ADMIN && RG_CURRENT_VIEW != "entry" ? "" : "onclick='gformAddListItem(this, {$maxRow})'";

                    if(rgar($field, "maxRows") != 1){

                        $list .="<td class='gfield_list_icons'>";
                        $list .="   <img src='{$add_icon}' class='add_list_item {$disabled_icon_class}' {$disabled_text} title='" . __("Add a row", "gravityforms") . "' alt='" . __("Add a row", "gravityforms") . "' {$on_click} style='cursor:pointer; margin:0 3px;' />" .
                                "   <img src='{$delete_icon}' {$disabled_text} title='" . __("Remove this row", "gravityforms") . "' alt='" . __("Remove this row", "gravityforms") . "' class='delete_list_item' style='cursor:pointer; {$delete_display}' onclick='gformDeleteListItem(this, {$maxRow})' />";
                        $list .="</td>";
                    }

                    $list .= "</tr>";

                    if(!empty($maxRow) && $rownum >= $maxRow)
                        break;

                    $rownum++;
                }

                $list .="</tbody></table></div>";

                return $list;
            break;
        }
    }

    public static function is_ssl(){
        global $wordpress_https;
        $is_ssl = false;

        $has_https_plugin = class_exists('WordPressHTTPS') && isset($wordpress_https);
        $has_is_ssl_method = $has_https_plugin && method_exists('WordPressHTTPS', 'is_ssl');
        $has_isSsl_method = $has_https_plugin && method_exists('WordPressHTTPS', 'isSsl');

        //Use the WordPress HTTPs plugin if installed
        if ($has_https_plugin && $has_is_ssl_method){
            $is_ssl = $wordpress_https->is_ssl();
        }
        else if ($has_https_plugin && $has_isSsl_method){
            $is_ssl = $wordpress_https->isSsl();
        }
        else{
            $is_ssl = is_ssl();
        }


        if(!$is_ssl && isset($_SERVER["HTTP_CF_VISITOR"]) && strpos($_SERVER["HTTP_CF_VISITOR"], "https")){
            $is_ssl=true;
        }

        return apply_filters("gform_is_ssl", $is_ssl);
    }

    public static function is_card_supported($field, $card_slug){
        $supported_cards = rgar($field, 'creditCards');
        $default_cards = array('amex', 'discover', 'mastercard', 'visa');

        if(!empty($supported_cards) && in_array($card_slug, $supported_cards)) {
            return true;
        }
        else if(empty($supported_cards) && in_array($card_slug, $default_cards)) {
            return true;
        }

        return false;

    }

    public static function is_preview(){
        $url_info = parse_url(RGFormsModel::get_current_page_url());
        $file_name = basename($url_info["path"]);
        return $file_name == "preview.php" || rgget("gf_page", $_GET) == "preview";
    }

    private static function get_expiration_months($selected_month){
        $str = "<option value=''>" . __("Month", "gravityforms") . "</option>";
        for($i=1; $i<13; $i++){
            $selected = intval($selected_month) == $i ? "selected='selected'" : "";
            $month = str_pad($i, 2, "0", STR_PAD_LEFT);
            $str .= "<option value='{$i}' {$selected}>{$month}</option>";
        }
        return $str;
    }

    private static function get_expiration_years($selected_year){
        $str = "<option value=''>" . __("Year", "gravityforms") . "</option>";
        $year = intval(date("Y"));
        for($i=$year; $i < ($year + 20); $i++){
            $selected = intval($selected_year) == $i ? "selected='selected'" : "";
            $str .= "<option value='{$i}' {$selected}>{$i}</option>";
        }
        return $str;
    }

    private static function get_list_input($field, $has_columns, $column, $value, $form_id){

        $tabindex = GFCommon::get_tabindex();

        $column_index = 1;
        if($has_columns && is_array(rgar($field, "choices"))){
            foreach($field["choices"] as $choice){
                if($choice["text"] == $column["text"])
                    break;

                $column_index++;
            }
        }
        $input_info = array("type" => "text");

        $input_info = apply_filters("gform_column_input_{$form_id}_{$field["id"]}_{$column_index}", apply_filters("gform_column_input", $input_info, $field, rgar($column, "text"), $value, $form_id), $field, rgar($column, "text"), $value, $form_id);

        switch($input_info["type"]){

            case "select" :
                $input = "<select name='input_{$field["id"]}[]' {$tabindex} >";
                if(!is_array($input_info["choices"]))
                    $input_info["choices"] = explode(",", $input_info["choices"]);

                foreach($input_info["choices"] as $choice){
                    if(is_array($choice)){
                        $choice_value = $choice["value"];
                        $choice_text = $choice["text"];
                        $choice_selected = $choice["isSelected"];
                    }
                    else{
                        $choice_value = $choice;
                        $choice_text = $choice;
                        $choice_selected = false;
                    }
                    $is_selected = empty($value) ? $choice_selected : $choice_value == $value;
                    $selected = $is_selected ? "selected='selected'" : "";
                    $input .= "<option value='" . esc_attr($choice_value) . "' {$selected}>" . esc_html($choice_text) . "</option>";
                }
                $input .= "</select>";

            break;

            default :
                $input = "<input type='text' name='input_{$field["id"]}[]' value='" . esc_attr($value) . "' {$tabindex}/>";
            break;
        }


        return apply_filters("gform_column_input_content_{$form_id}_{$field["id"]}_{$column_index}",
            apply_filters("gform_column_input_content", $input, $input_info, $field, rgar($column, "text"), $value, $form_id),
                                                                $input_info, $field, rgar($column, "text"), $value, $form_id);
    }

    public static function to_money($number, $currency_code=""){
        if(!class_exists("RGCurrency"))
            require_once("currency.php");

        if(empty($currency_code))
            $currency_code = self::get_currency();

        $currency = new RGCurrency($currency_code);
        return $currency->to_money($number);
    }

    public static function to_number($text, $currency_code=""){
        if(!class_exists("RGCurrency"))
            require_once("currency.php");

         if(empty($currency_code))
            $currency_code = self::get_currency();

        $currency = new RGCurrency($currency_code);

        return $currency->to_number($text);
    }

    public static function get_currency(){
        $currency = get_option("rg_gforms_currency");
        $currency = empty($currency) ? "USD" : $currency;
        return apply_filters("gform_currency", $currency);
    }


    public static function get_simple_captcha(){
        $captcha = new ReallySimpleCaptcha();
        $captcha->tmp_dir = RGFormsModel::get_upload_path("captcha") . "/";
        return $captcha;
    }

    public static function get_captcha($field){
        if(!class_exists("ReallySimpleCaptcha"))
            return array();

        $captcha = self::get_simple_captcha();

        //If captcha folder does not exist and can't be created, return an empty captcha
        if(!wp_mkdir_p($captcha->tmp_dir))
            return array();

        $captcha->char_length = 5;
        switch(rgar($field,"simpleCaptchaSize")){
            case "small" :
                $captcha->img_size = array( 100, 28 );
                $captcha->font_size = 18;
                $captcha->base = array( 8, 20 );
                $captcha->font_char_width = 17;

            break;

            case "large" :
                $captcha->img_size = array( 200, 56 );
                $captcha->font_size = 32;
                $captcha->base = array( 18, 42 );
                $captcha->font_char_width = 35;
            break;

            default :
                $captcha->img_size = array( 150, 42 );
                $captcha->font_size = 26;
                $captcha->base = array( 15, 32 );
                $captcha->font_char_width = 25;
            break;
        }

        if(!empty($field["simpleCaptchaFontColor"])){
            $captcha->fg = self::hex2rgb($field["simpleCaptchaFontColor"]);
        }
        if(!empty($field["simpleCaptchaBackgroundColor"])){
            $captcha->bg = self::hex2rgb($field["simpleCaptchaBackgroundColor"]);
        }

        $word = $captcha->generate_random_word();
        $prefix = mt_rand();
        $filename = $captcha->generate_image($prefix, $word);
        $url = RGFormsModel::get_upload_url("captcha") . "/" . $filename;
        $path = $captcha->tmp_dir . $filename;

        return array("path"=>$path, "url"=> $url, "height" => $captcha->img_size[1], "width" => $captcha->img_size[0], "prefix" => $prefix);
    }

    public static function get_math_captcha($field, $pos){
        if(!class_exists("ReallySimpleCaptcha"))
            return array();

        $captcha = self::get_simple_captcha();

        //If captcha folder does not exist and can't be created, return an empty captcha
        if(!wp_mkdir_p($captcha->tmp_dir))
            return array();

        $captcha->char_length = 1;
        if($pos == 1 || $pos == 3)
            $captcha->chars = '0123456789';
        else
            $captcha->chars = '+';

        switch(rgar($field,"simpleCaptchaSize")){
            case "small" :
                $captcha->img_size = array( 23, 28 );
                $captcha->font_size = 18;
                $captcha->base = array( 6, 20 );
                $captcha->font_char_width = 17;

            break;

            case "large" :
                $captcha->img_size = array( 36, 56 );
                $captcha->font_size = 32;
                $captcha->base = array( 10, 42 );
                $captcha->font_char_width = 35;
            break;

            default :
                $captcha->img_size = array( 30, 42 );
                $captcha->font_size = 26;
                $captcha->base = array( 9, 32 );
                $captcha->font_char_width = 25;
            break;
        }

        if(!empty($field["simpleCaptchaFontColor"])){
            $captcha->fg = self::hex2rgb($field["simpleCaptchaFontColor"]);
        }
        if(!empty($field["simpleCaptchaBackgroundColor"])){
            $captcha->bg = self::hex2rgb($field["simpleCaptchaBackgroundColor"]);
        }

        $word = $captcha->generate_random_word();
        $prefix = mt_rand();
        $filename = $captcha->generate_image($prefix, $word);
        $url = RGFormsModel::get_upload_url("captcha") . "/" . $filename;
        $path = $captcha->tmp_dir . $filename;

        return array("path"=>$path, "url"=> $url, "height" => $captcha->img_size[1], "width" => $captcha->img_size[0], "prefix" => $prefix);
    }

    private static function hex2rgb($color){
        if ($color[0] == '#')
            $color = substr($color, 1);

        if (strlen($color) == 6)
            list($r, $g, $b) = array($color[0].$color[1],
                                     $color[2].$color[3],
                                     $color[4].$color[5]);
        elseif (strlen($color) == 3)
            list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
        else
            return false;

        $r = hexdec($r); $g = hexdec($g); $b = hexdec($b);

        return array($r, $g, $b);
    }

    public static function get_address_types($form_id){

        $addressTypes = array(
            "international" =>  array("label" => __("International", "gravityforms"),"zip_label" => apply_filters("gform_address_zip_{$form_id}",apply_filters("gform_address_zip", __("Zip / Postal Code", "gravityforms"), $form_id), $form_id),"state_label" => apply_filters("gform_address_state_{$form_id}",apply_filters("gform_address_state",__("State / Province / Region", "gravityforms"), $form_id), $form_id)),
            "us" =>             array("label" => __("United States", "gravityforms"),"zip_label" => apply_filters("gform_address_zip_{$form_id}",apply_filters("gform_address_zip", __("Zip Code", "gravityforms"), $form_id), $form_id),         "state_label" => apply_filters("gform_address_state_{$form_id}",apply_filters("gform_address_state",__("State", "gravityforms"), $form_id), $form_id),   "country" => "United States", "states" => array_merge(array(''), GFCommon::get_us_states())),
            "canadian" =>       array("label" => __("Canadian", "gravityforms"),     "zip_label" => apply_filters("gform_address_zip_{$form_id}",apply_filters("gform_address_zip", __("Postal Code", "gravityforms"), $form_id), $form_id),      "state_label" => apply_filters("gform_address_state_{$form_id}",apply_filters("gform_address_state",__("Province", "gravityforms"), $form_id), $form_id),"country" => "Canada",        "states" => array_merge(array(''), GFCommon::get_canadian_provinces()))
            );

        return apply_filters("gform_address_types_{$form_id}", apply_filters("gform_address_types", $addressTypes, $form_id), $form_id);
    }

    private static function get_state_field($field, $id, $field_id, $state_value, $disabled_text, $form_id){
        $state_dropdown_class = $state_text_class = $state_style = $text_style = $state_field_id = "";

        if(empty($state_value)){
            $state_value = rgget("defaultState", $field);

            //for backwards compatibility (canadian address type used to store the default state into the defaultProvince property)
            if (rgget("addressType", $field) == "canadian" && !rgempty("defaultProvince", $field))
                $state_value = $field["defaultProvince"];
        }

        $address_type = rgempty("addressType", $field) ? "international" : $field["addressType"];
        $address_types = self::get_address_types($form_id);
        $has_state_drop_down = isset($address_types[$address_type]["states"]) && is_array($address_types[$address_type]["states"]);

        if(IS_ADMIN && RG_CURRENT_VIEW != "entry"){
            $state_dropdown_class = "class='state_dropdown'";
            $state_text_class = "class='state_text'";
            $state_style = !$has_state_drop_down ? "style='display:none;'" : "";
            $text_style = $has_state_drop_down  ? "style='display:none;'" : "";
            $state_field_id = "";
        }
        else{
            //id only displayed on front end
            $state_field_id = "id='" . $field_id . "_4'";
        }

        $tabindex = self::get_tabindex();
        $states = empty($address_types[$address_type]["states"]) ? array() : $address_types[$address_type]["states"];
        $state_dropdown = sprintf("<select name='input_%d.4' %s $tabindex %s $state_dropdown_class $state_style>%s</select>", $id, $state_field_id, $disabled_text, GFCommon::get_state_dropdown($states, $state_value));

        $tabindex = self::get_tabindex();
        $state_text = sprintf("<input type='text' name='input_%d.4' %s value='%s' $tabindex %s $state_text_class $text_style/>", $id, $state_field_id, $state_value, $disabled_text);

        if(IS_ADMIN && RG_CURRENT_VIEW != "entry")
            return $state_dropdown . $state_text;
        else if($has_state_drop_down)
            return $state_dropdown;
        else
            return $state_text;
    }

    public static function get_lead_field_display($field, $value, $currency="", $use_text=false, $format="html", $media="screen"){

        if($field['type'] == 'post_category')
            $value = self::prepare_post_category_value($value, $field);

        switch(RGFormsModel::get_input_type($field)){
            case "name" :
                if(is_array($value)){
                    $prefix = trim(rgget($field["id"] . ".2", $value));
                    $first = trim(rgget($field["id"] . ".3", $value));
                    $last = trim(rgget($field["id"] . ".6", $value));
                    $suffix = trim(rgget($field["id"] . ".8", $value));

                    $name = $prefix;
                    $name .= !empty($name) && !empty($first) ? " $first" : $first;
                    $name .= !empty($name) && !empty($last) ? " $last" : $last;
                    $name .= !empty($name) && !empty($suffix) ? " $suffix" : $suffix;

                    return $name;
                }
                else{
                    return $value;
                }

            break;
            case "creditcard" :
                if(is_array($value)){
                    $card_number = trim(rgget($field["id"] . ".1", $value));
                    $card_type = trim(rgget($field["id"] . ".4", $value));
                    $separator = $format == "html" ? "<br/>" : "\n";
                    return empty($card_number) ? "" : $card_type . $separator . $card_number;
                }
                else{
                    return "";
                }
            break;

            case "address" :
                if(is_array($value)){
                    $street_value = trim(rgget($field["id"] . ".1", $value));
                    $street2_value = trim(rgget($field["id"] . ".2", $value));
                    $city_value = trim(rgget($field["id"] . ".3", $value));
                    $state_value = trim(rgget($field["id"] . ".4", $value));
                    $zip_value = trim(rgget($field["id"] . ".5", $value));
                    $country_value = trim(rgget($field["id"] . ".6", $value));

                    $line_break = $format == "html" ? "<br />" : "\n";

                    $address_display_format = apply_filters("gform_address_display_format", "default");
                    if($address_display_format == "zip_before_city"){
                        /*
                        Sample:
                        3333 Some Street
                        suite 16
                        2344 City, State
                        Country
                        */

                        $addr_ary = array();
                        $addr_ary[] = $street_value;

                        if(!empty($street2_value))
                            $addr_ary[] = $street2_value;

                        $zip_line = trim($zip_value . " " . $city_value);
                        $zip_line .= !empty($zip_line) && !empty($state_value) ? ", {$state_value}" : $state_value;
                        $zip_line = trim($zip_line);
                        if(!empty($zip_line))
                            $addr_ary[] = $zip_line;

                        if(!empty($country_value))
                            $addr_ary[] = $country_value;

                        $address = implode("<br />", $addr_ary);

                    }
                    else{
                        $address = $street_value;
                        $address .= !empty($address) && !empty($street2_value) ? $line_break . $street2_value : $street2_value;
                        $address .= !empty($address) && (!empty($city_value) || !empty($state_value)) ? $line_break. $city_value : $city_value;
                        $address .= !empty($address) && !empty($city_value) && !empty($state_value) ? ", $state_value" : $state_value;
                        $address .= !empty($address) && !empty($zip_value) ? " $zip_value" : $zip_value;
                        $address .= !empty($address) && !empty($country_value) ? $line_break . $country_value : $country_value;
                    }

                    //adding map link
                    if(!empty($address) && $format == "html"){
                        $address_qs = str_replace($line_break, " ", $address); //replacing <br/> and \n with spaces
                        $address_qs = urlencode($address_qs);
                        $address .= "<br/><a href='http://maps.google.com/maps?q={$address_qs}' target='_blank' class='map-it-link'>Map It</a>";
                    }

                    return $address;
                }
                else{
                    return "";
                }
            break;

            case "email" :
                return GFCommon::is_valid_email($value) && $format == "html" ? "<a href='mailto:$value'>$value</a>" : $value;
            break;

            case "website" :
                return GFCommon::is_valid_url($value) && $format == "html" ? "<a href='$value' target='_blank'>$value</a>" : $value;
            break;

            case "checkbox" :
                if(is_array($value)){

                    $items = '';

                    foreach($value as $key => $item){
                        if(!empty($item)){
                            switch($format){
                                case "text" :
                                    $items .= GFCommon::selection_display($item, $field, $currency, $use_text) . ", ";
                                break;

                                default:
                                    $items .= "<li>" . GFCommon::selection_display($item, $field, $currency, $use_text) . "</li>";
                                break;
                            }
                        }
                    }
                    if(empty($items)){
                        return "";
                    }
                    else if($format == "text"){
                        return substr($items, 0, strlen($items)-2); //removing last comma
                    }
                    else{
                        return "<ul class='bulleted'>$items</ul>";
                    }
                }
                else{
                    return $value;
                }
            break;

            case "post_image" :
                $ary = explode("|:|", $value);
                $url = count($ary) > 0 ? $ary[0] : "";
                $title = count($ary) > 1 ? $ary[1] : "";
                $caption = count($ary) > 2 ? $ary[2] : "";
                $description = count($ary) > 3 ? $ary[3] : "";

                if(!empty($url)){
                    $url = str_replace(" ", "%20", $url);

                    switch($format){
                        case "text" :
                            $value = $url;
                            $value .= !empty($title) ? "\n\n" . $field["label"] . " (" . __("Title", "gravityforms") . "): " . $title : "";
                            $value .= !empty($caption) ? "\n\n" . $field["label"] . " (" . __("Caption", "gravityforms") . "): " . $caption : "";
                            $value .= !empty($description) ? "\n\n" . $field["label"] . " (" . __("Description", "gravityforms") . "): " . $description : "";
                        break;

                        default :
                            $value = "<a href='$url' target='_blank' title='" . __("Click to view", "gravityforms") . "'><img src='$url' width='100' /></a>";
                            $value .= !empty($title) ? "<div>Title: $title</div>" : "";
                            $value .= !empty($caption) ? "<div>Caption: $caption</div>" : "";
                            $value .= !empty($description) ? "<div>Description: $description</div>": "";

                        break;
                    }
                }
                return $value;

            case "fileupload" :
                $file_path = $value;
                if(!empty($file_path)){
                    $info = pathinfo($file_path);
                    $file_path = esc_attr(str_replace(" ", "%20", $file_path));
                    $value = $format == "text" ? $file_path : "<a href='$file_path' target='_blank' title='" . __("Click to view", "gravityforms") . "'>" . $info["basename"] . "</a>";
                }
                return $value;
            break;

            case "date" :
                return GFCommon::date_display($value, rgar($field, "dateFormat"));
            break;

            case "radio" :
            case "select" :
                return GFCommon::selection_display($value, $field, $currency, $use_text);
            break;

            case "multiselect" :
                if(empty($value) || $format == "text")
                    return $value;

                $value = explode(",", $value);

                $items = '';
                foreach($value as $item){
                    $items .= "<li>" . GFCommon::selection_display($item, $field, $currency, $use_text) . "</li>";
                }

                return "<ul class='bulleted'>{$items}</ul>";

            break;

            case "calculation" :
            case "singleproduct" :
                if(is_array($value)){
                    $product_name = trim($value[$field["id"] . ".1"]);
                    $price = trim($value[$field["id"] . ".2"]);
                    $quantity = trim($value[$field["id"] . ".3"]);

                    $product = $product_name . ", " . __("Qty: ", "gravityforms") . $quantity . ", " . __("Price: ", "gravityforms") . $price;
                    return $product;
                }
                else{
                    return "";
                }
            break;

            case "number" :
                return GFCommon::format_number($value, rgar($field, "numberFormat"));
            break;

            case "singleshipping" :
            case "donation" :
            case "total" :
            case "price" :
                return GFCommon::to_money($value, $currency);

            case "list" :
                if(empty($value))
                    return "";
                $value = unserialize($value);

                $has_columns = is_array($value[0]);

                if(!$has_columns){
                    $items = '';
                    foreach($value as $key => $item){
                        if(!empty($item)){
                            switch($format){
                                case "text" :
                                    $items .= $item . ", ";
                                break;
                                case "url" :
                                    $items .= $item . ",";
                                break;
                                default :
                                    if($media == "email"){
                                        $items .= "<li>{$item}</li>";
                                    }
                                    else{
                                        $items .= "<li>{$item}</li>";
                                    }
                                break;
                            }
                        }
                    }

                    if(empty($items)){
                        return "";
                    }
                    else if($format == "text"){
                        return substr($items, 0, strlen($items)-2); //removing last comma
                    }
                    else if($format == "url"){
                        return substr($items, 0, strlen($items)-1); //removing last comma
                    }
                    else if($media == "email"){
                        return "<ul class='bulleted'>{$items}</ul>";
                    }
                    else{
                        return "<ul class='bulleted'>{$items}</ul>";
                    }
                }
                else if(is_array($value)){
                    $columns = array_keys($value[0]);

                    $list = "";

                    switch($format){
                        case "text" :
                            $is_first_row = true;
                            foreach($value as $item){
                                if(!$is_first_row)
                                    $list .= "\n\n" . $field["label"] . ": ";
                                $list .= implode(",", array_values($item));

                                $is_first_row = false;
                            }
                        break;

                        case "url" :
                            foreach($value as $item){
                                $list .= implode("|", array_values($item)) . ",";
                            }
                            if(!empty($list))
                                $list = substr($list, 0, strlen($list)-1);
                        break;

                        default :
                            if($media == "email"){
                                $list = "<table class='gfield_list' style='border-top: 1px solid #DFDFDF; border-left: 1px solid #DFDFDF; border-spacing: 0; padding: 0; margin: 2px 0 6px; width: 100%'><thead><tr>";

                                //reading columns from entry data
                                foreach($columns as $column){
                                    $list .= "<th style='background-image: none; border-right: 1px solid #DFDFDF; border-bottom: 1px solid #DFDFDF; padding: 6px 10px; font-family: sans-serif; font-size: 12px; font-weight: bold; background-color: #F1F1F1; color:#333; text-align:left'>" . esc_html($column) . "</th>";
                                }
                                $list .= "</tr></thead>";

                                $list .= "<tbody style='background-color: #F9F9F9'>";
                                foreach($value as $item){
                                    $list .= "<tr>";
                                    foreach($columns as $column){
                                        $val = rgar($item, $column);
                                        $list .= "<td style='padding: 6px 10px; border-right: 1px solid #DFDFDF; border-bottom: 1px solid #DFDFDF; border-top: 1px solid #FFF; font-family: sans-serif; font-size:12px;'>{$val}</td>";
                                    }

                                    $list .="</tr>";
                                }

                                $list .="<tbody></table>";
                            }
                            else{
                                $list = "<table class='gfield_list'><thead><tr>";

                                //reading columns from entry data
                                foreach($columns as $column){
                                    $list .= "<th>" . esc_html($column) . "</th>";
                                }
                                $list .= "</tr></thead>";

                                $list .= "<tbody>";
                                foreach($value as $item){
                                    $list .= "<tr>";
                                    foreach($columns as $column){
                                        $val = rgar($item, $column);
                                        $list .= "<td>{$val}</td>";
                                    }

                                    $list .="</tr>";
                                }

                                $list .="<tbody></table>";
                            }
                        break;
                    }

                    return $list;
                }
                return "";
            break;

            default :
            	if (!is_array($value))
            	{
                	return nl2br($value);
				}
            break;
        }
    }

    public static function get_product_fields($form, $lead, $use_choice_text=false, $use_admin_label=false){
        $products = array();

        $product_info = null;
        // retrieve static copy of product info (only for "real" entries)
        if(!rgempty("id", $lead))
            $product_info = gform_get_meta(rgar($lead,'id'), "gform_product_info_{$use_choice_text}_{$use_admin_label}");

        // if no static copy, generate from form/lead info
        if(!$product_info) {

            foreach($form["fields"] as $field){
                $id = $field["id"];
                $lead_value = RGFormsModel::get_lead_field_value($lead, $field);

                $quantity_field = self::get_product_fields_by_type($form, array("quantity"), $id);
                $quantity = sizeof($quantity_field) > 0 && !RGFormsModel::is_field_hidden($form, $quantity_field[0], array(), $lead) ? RGFormsModel::get_lead_field_value($lead, $quantity_field[0]) : 1;

                switch($field["type"]){

                    case "product" :

                        //ignore products that have been hidden by conditional logic
                        $is_hidden = RGFormsModel::is_field_hidden($form, $field, array(), $lead);
                        if($is_hidden)
                            continue;

                        //if single product, get values from the multiple inputs
                        if(is_array($lead_value)){
                            $product_quantity = sizeof($quantity_field) == 0 && !rgar($field,"disableQuantity") ? rgget($id . ".3", $lead_value) : $quantity;
                            if(empty($product_quantity))
                                continue;

                            if(!rgget($id, $products))
                                $products[$id] = array();

                            $products[$id]["name"] = $use_admin_label && !rgempty("adminLabel", $field) ? $field["adminLabel"] : $lead_value[$id . ".1"];
                            $products[$id]["price"] = $lead_value[$id . ".2"];
                            $products[$id]["quantity"] = $product_quantity;
                        }
                        else if(!empty($lead_value)){

                            if(empty($quantity))
                                continue;

                            if(!rgar($products,$id))
                                $products[$id] = array();

                            if($field["inputType"] == "price"){
                                $name = $field["label"];
                                $price = $lead_value;
                            }
                            else{
                                list($name, $price) = explode("|", $lead_value);
                            }

                            $products[$id]["name"] = !$use_choice_text ? $name : RGFormsModel::get_choice_text($field, $name);
                            $products[$id]["price"] = $price;
                            $products[$id]["quantity"] = $quantity;
                            $products[$id]["options"] = array();
                        }

                        if(isset($products[$id])){
                            $options = self::get_product_fields_by_type($form, array("option"), $id);
                            foreach($options as $option){
                                $option_value = RGFormsModel::get_lead_field_value($lead, $option);
                                $option_label = empty($option["adminLabel"]) ? $option["label"] : $option["adminLabel"];
                                if(is_array($option_value)){
                                    foreach($option_value as $value){
                                        $option_info = self::get_option_info($value, $option, $use_choice_text);
                                        if(!empty($option_info))
                                            $products[$id]["options"][] = array("field_label" => rgar($option, "label"), "option_name"=> rgar($option_info, "name"), "option_label" => $option_label . ": " . rgar($option_info, "name"), "price" => rgar($option_info,"price"));
                                    }
                                }
                                else if(!empty($option_value)){
                                    $option_info = self::get_option_info($option_value, $option, $use_choice_text);
                                    $products[$id]["options"][] = array("field_label" => rgar($option, "label"), "option_name"=> rgar($option_info, "name"), "option_label" => $option_label . ": " . rgar($option_info, "name"), "price" => rgar($option_info,"price"));
                                }

                            }
                        }
                    break;
                }
            }

            $shipping_field = self::get_fields_by_type($form, array("shipping"));
            $shipping_price = $shipping_name = "";

            if(!empty($shipping_field) && !RGFormsModel::is_field_hidden($form, $shipping_field[0], array(), $lead)){
                $shipping_price = RGFormsModel::get_lead_field_value($lead, $shipping_field[0]);
                $shipping_name = $shipping_field[0]["label"];
                if($shipping_field[0]["inputType"] != "singleshipping"){
                    list($shipping_method, $shipping_price) = explode("|", $shipping_price);
                    $shipping_name = $shipping_field[0]["label"] . " ($shipping_method)";
                }
            }
            $shipping_price = self::to_number($shipping_price);

            $product_info = array("products" => $products, "shipping" => array("name" => $shipping_name, "price" => $shipping_price));

            $product_info = apply_filters("gform_product_info_{$form["id"]}", apply_filters("gform_product_info", $product_info, $form, $lead), $form, $lead);

            // save static copy of product info (only for "real" entries)
            if(!rgempty("id", $lead) && !empty($product_info["products"]))
                gform_update_meta($lead['id'], "gform_product_info_{$use_choice_text}_{$use_admin_label}", $product_info);
        }

        return $product_info;
    }

    public static function get_order_total($form, $lead) {

        $products = self::get_product_fields($form, $lead, false);
        return self::get_total($products);
    }

    public static function get_total($products) {

        $total = 0;
        foreach($products["products"] as $product){

            $price = self::to_number($product["price"]);
            if(is_array(rgar($product,"options"))){
                foreach($product["options"] as $option){
                    $price += self::to_number($option["price"]);
                }
            }
            $subtotal = floatval($product["quantity"]) * $price;
            $total += $subtotal;

        }

        $total += floatval($products["shipping"]["price"]);

        return $total;
    }

    public static function get_option_info($value, $option, $use_choice_text){
        if(empty($value))
            return array();

        list($name, $price) = explode("|", $value);
        if($use_choice_text)
            $name = RGFormsModel::get_choice_text($option, $name);

        return array("name" => $name, "price" => $price);
    }

    public static function gform_do_shortcode($content){

        $is_ajax = false;
        $forms = GFFormDisplay::get_embedded_forms($content, $is_ajax);

        foreach($forms as $form){
            GFFormDisplay::print_form_scripts($form, $is_ajax);
        }

        return do_shortcode($content);
    }

    public static function has_akismet(){
        return function_exists('akismet_http_post');
    }

    public static function akismet_enabled($form_id) {

        if(!self::has_akismet())
            return false;

        // if no option is set, leave akismet enabled; otherwise, use option value true/false
        $enabled_by_setting = get_option('rg_gforms_enable_akismet') === false ? true : get_option('rg_gforms_enable_akismet') == true;
        $enabled_by_filter = apply_filters("gform_akismet_enabled_$form_id", apply_filters("gform_akismet_enabled", $enabled_by_setting));

        return $enabled_by_filter;

    }

    public static function is_akismet_spam($form, $lead){

        global $akismet_api_host, $akismet_api_port;

        $fields = self::get_akismet_fields($form, $lead);

        //Submitting info do Akismet
        $response = akismet_http_post($fields, $akismet_api_host, '/1.1/comment-check', $akismet_api_port );
        $is_spam = trim(rgar($response, 1)) == "true";

        return $is_spam;
    }

    public static function mark_akismet_spam($form, $lead, $is_spam){

        global $akismet_api_host, $akismet_api_port;

        $fields = self::get_akismet_fields($form, $lead);
        $as = $is_spam ? "spam" : "ham";

        //Submitting info do Akismet
        akismet_http_post($fields, $akismet_api_host,  '/1.1/submit-'.$as, $akismet_api_port );
    }

    private static function get_akismet_fields($form, $lead){
        //Gathering Akismet information
        $akismet_info = array();
        $akismet_info['comment_type'] = 'gravity_form';
        $akismet_info['comment_author'] = self::get_akismet_field("name", $form, $lead);
        $akismet_info['comment_author_email'] = self::get_akismet_field("email", $form, $lead);
        $akismet_info['comment_author_url'] = self::get_akismet_field("website", $form, $lead);
        $akismet_info['comment_content'] = self::get_akismet_field("textarea", $form, $lead);
        $akismet_info['contact_form_subject'] = $form["title"];
        $akismet_info['comment_author_IP'] = $lead["ip"];
        $akismet_info['permalink'] = $lead["source_url"];
        $akismet_info['user_ip']      = preg_replace( '/[^0-9., ]/', '', $lead["ip"] );
        $akismet_info['user_agent']   = $lead["user_agent"];
        $akismet_info['referrer']     = is_admin() ? "" : $_SERVER['HTTP_REFERER'];
        $akismet_info['blog']         = get_option('home');

        $akismet_info = apply_filters("gform_akismet_fields_{$form["id"]}", apply_filters("gform_akismet_fields", $akismet_info, $form, $lead), $form, $lead);

        return http_build_query($akismet_info);
    }

    private static function get_akismet_field($field_type, $form, $lead){
        $fields = GFCommon::get_fields_by_type($form, array($field_type));
        if(empty($fields))
            return "";

        $value = RGFormsModel::get_lead_field_value($lead, $fields[0]);
        switch($field_type){
            case "name" :
                $value = GFCommon::get_lead_field_display($fields[0], $value);
            break;
        }

        return $value;
    }

    public static function get_other_choice_value(){
        $value = apply_filters('gform_other_choice_value', __("Other", "gravityforms"));
        return $value;
    }

    public static function get_browser_class() {
        global $is_lynx, $is_gecko, $is_IE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone, $post;

        $classes = array();

        //adding browser related class
        if($is_lynx) $classes[] = 'gf_browser_lynx';
        else if($is_gecko) $classes[] = 'gf_browser_gecko';
        else if($is_opera) $classes[] = 'gf_browser_opera';
        else if($is_NS4) $classes[] = 'gf_browser_ns4';
        else if($is_safari) $classes[] = 'gf_browser_safari';
        else if($is_chrome) $classes[] = 'gf_browser_chrome';
        else if($is_IE) $classes[] = 'gf_browser_ie';
        else $classes[] = 'gf_browser_unknown';


        //adding IE version
        if($is_IE){
            if(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 6') !== false)
                $classes[] = 'gf_browser_ie6';
            else if(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 7') !== false)
                $classes[] = 'gf_browser_ie7';
            if(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8') !== false)
                $classes[] = 'gf_browser_ie8';
            if(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 9') !== false)
                $classes[] = 'gf_browser_ie9';
        }

        if($is_iphone) $classes[] = 'gf_browser_iphone';

        return implode(" " , $classes);
    }

    public static function create_post($form, &$lead) {
        $disable_post = apply_filters("gform_disable_post_creation_{$form["id"]}", apply_filters("gform_disable_post_creation", false, $form, $lead), $form, $lead);

        //creates post if the form has any post fields
        $post_id = !$disable_post ? RGFormsModel::create_post($form, $lead) : 0;

        return $post_id;
    }


    public static function get_card_types(){
        $cards =   array (

                   array (  'name' => 'American Express',
                            'slug' => 'amex',
                            'lengths' => '15',
                            'prefixes' => '34,37',
                            'checksum' => true
                         ),
                   array (  'name' => 'Discover',
                            'slug' => 'discover',
                            'lengths' => '16',
                            'prefixes' => '6011,622,64,65',
                            'checksum' => true
                         ),
                         array (  'name' => 'MasterCard',
                            'slug' => 'mastercard',
                            'lengths' => '16',
                            'prefixes' => '51,52,53,54,55',
                            'checksum' => true
                         ),
                   array (  'name' => 'Visa',
                            'slug' => 'visa',
                            'lengths' => '13,16',
                            'prefixes' => '4,417500,4917,4913,4508,4844',
                            'checksum' => true
                         ),
                   array (  'name' => 'JCB',
                            'slug' => 'jcb',
                            'lengths' => '16',
                            'prefixes' => '35',
                            'checksum' => true
                         ),
                   array (  'name' => 'Maestro',
                            'slug' => 'maestro',
                            'lengths' => '12,13,14,15,16,18,19',
                            'prefixes' => '5018,5020,5038,6304,6759,6761',
                            'checksum' => true
                         )

                );

        $cards = apply_filters("gform_creditcard_types", $cards);

        return $cards;
    }

    public static function get_card_type($number){

        //removing spaces from number
        $number = str_replace (' ', '', $number);

        if(empty($number))
            return false;

        $cards = self::get_card_types();

        $matched_card = false;
        foreach($cards as $card){
            if(self::matches_card_type($number, $card)){
                $matched_card = $card;
                break;
            }
        }

        if($matched_card && $matched_card["checksum"] && !self::is_valid_card_checksum($number))
            $matched_card = false;

        return $matched_card ? $matched_card : false;

    }

    private static function matches_card_type($number, $card){

        //checking prefix
        $prefixes = explode(',',$card['prefixes']);
        $matches_prefix = false;
        foreach($prefixes as $prefix){
            if(preg_match("|^{$prefix}|", $number)){
                $matches_prefix = true;
                break;
            }
        }

        //checking length
        $lengths = explode(',',$card['lengths']);
        $matches_length = false;
        foreach($lengths as $length){
            if(strlen($number) == absint($length)){
                $matches_length = true;
                break;
            }
        }

        return $matches_prefix && $matches_length;

    }

    private static function is_valid_card_checksum($number){
        $checksum = 0;
        $num = 0;
        $multiplier = 1;

        // Process each character starting at the right
        for ($i = strlen($number) - 1; $i >= 0; $i--) {

            //Multiply current digit by multiplier (1 or 2)
            $num = $number{$i} * $multiplier;

            // If the result is in greater than 9, add 1 to the checksum total
            if ($num >= 10) {
                $checksum++;
                $num -= 10;
            }

            //Update checksum
            $checksum += $num;

            //Update multiplier
            $multiplier = $multiplier == 1 ? 2 : 1;
        }

        return $checksum % 10 == 0;

    }

    public static function is_wp_version($min_version){
        return !version_compare(get_bloginfo("version"), "{$min_version}.dev1", '<');
    }

    public static function add_categories_as_choices($field, $value) {

        $choices = $inputs = array();
        $is_post = isset($_POST["gform_submit"]);
        $has_placeholder = rgar($field, 'categoryInitialItemEnabled') && RGFormsModel::get_input_type($field) == 'select';

        if($has_placeholder)
            $choices[] = array('text' => rgar($field, 'categoryInitialItem'), 'value' => '', 'isSelected' => true);

        if(rgar($field, "displayAllCategories")) {

            $categories = get_terms('category', array('hide_empty' => false));

            foreach($categories as $category) {
                $selected = $value == $category->term_id ||
                            (
                                empty($value) &&
                                get_option('default_category') == $category->term_id &&
                                RGFormsModel::get_input_type($field) == 'select' && // only preselect default category on select fields
                                !$is_post &&
                                !$has_placeholder
                            );
                $choices[] = array('text' => $category->name, 'value' => $category->term_id, 'isSelected' => $selected);
            }

        } else {

            $choices = array_merge($choices, $field['choices']);
        }

        if(empty($choices))
            $choices[] = array('text' => 'You must select at least one category.', 'value' => '');

        $choice_number = 1;
        foreach($choices as $choice) {

            if($choice_number % 10 == 0) //hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
                $choice_number++;

            $input_id = $field["id"] . '.' . $choice_number;
            $inputs[] = array('id' => $input_id, 'label' => $choice['text'], 'name' => '');
            $choice_number++;
        }

        $field['choices'] = $choices;

        if(RGFormsModel::get_input_type($field) == 'checkbox')
            $field['inputs'] = $inputs;

        return $field;
    }

    public static function prepare_post_category_value($value, $field, $mode = 'entry_detail') {

        if(!is_array($value))
            $value = explode(',', $value);

        $cat_names = array();
        $cat_ids = array();
        foreach($value as $cat_string) {
            $ary = explode(":", $cat_string);
            $cat_name = count($ary) > 0 ? $ary[0] : "";
            $cat_id = count($ary) > 1 ? $ary[1] : $ary[0];

            if(!empty($cat_name))
                $cat_names[] = $cat_name;

            if(!empty($cat_id))
                $cat_ids[] = $cat_id;
        }

        sort($cat_names);

        switch($mode) {
            case 'entry_list':
                $value = self::implode_non_blank(', ', $cat_names);
                break;
            case 'entry_detail':
                $value = RGFormsModel::get_input_type($field) == 'checkbox' ? $cat_names : self::implode_non_blank(', ', $cat_names);
                break;
            case 'conditional_logic':
                $value = array_values($cat_ids);
                break;
        }

        return $value;
    }

    public static function calculate($field, $form, $lead) {

        $formula = rgar($field, 'calculationFormula');

        preg_match_all('/{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/mi', $formula, $matches, PREG_SET_ORDER);

        if(is_array($matches)) {
            foreach($matches as $match) {

                list($text, $input_id) = $match;

                $value = self::get_calculation_value($match[1], $form, $lead);
                $formula = str_replace($match[0], $value, $formula);

            }
        }

        return preg_match("/^[0-9 -\/*\(\)]+$/", $formula) ? eval("return {$formula};") : false;
    }

    public static function round_number($number, $rounding){
        if(is_numeric($rounding) && $rounding >= 0){
            $number = round($number, $rounding);
        }
        return $number;
    }

    public static function get_calculation_value($field_id, $form, $lead) {

        $filters = array('price', 'value', '');

        do {
            $filter = isset($filter) ? next($filters) : reset($filters);
            $value =  GFCommon::to_number(GFCommon::replace_variables("{:{$field_id}:$filter}", $form, $lead));
        } while(!is_numeric($value) && $filter !== false);

        if(!$value || !is_numeric($value))
            $value = 0;

        return $value;
    }

    public static function conditional_shortcode($attributes, $content = null) {

        extract(shortcode_atts(array(
             'merge_tag' => '',
             'condition' => '',
             'value' => ''
          ), $attributes));

        $result = RGFormsModel::matches_operation($merge_tag, $value, $condition);

        return RGFormsModel::matches_operation($merge_tag, $value, $condition) ? do_shortcode($content) : '';

    }

    public static function is_valid_for_calcuation($field) {

        $supported_input_types = array('text', 'select', 'number', 'checkbox', 'radio', 'hidden', 'singleproduct', 'price', 'hiddenproduct', 'calculation', 'singleshipping');
        $unsupported_field_types = array('category');
        $input_type = RGFormsModel::get_input_type($field);

        return in_array($input_type, $supported_input_types) && !in_array($input_type, $unsupported_field_types);
    }

    public static function log_error($message){
        if(class_exists("GFLogging"))
        {
            GFLogging::include_logger();
            GFLogging::log_message("gravityforms", $message, KLogger::ERROR);
        }
    }

    public static function log_debug($message){
        if(class_exists("GFLogging"))
        {
            GFLogging::include_logger();
            GFLogging::log_message("gravityforms", $message, KLogger::DEBUG);
        }
    }

    public static function is_bp_active() {
        return defined('BP_VERSION') ? true : false;
    }
}
?>