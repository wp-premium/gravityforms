function Form(){
    this.id = 0;
    this.title = gf_vars.formTitle;
    this.description = gf_vars.formDescription;
    this.labelPlacement = "top_label";
    this.maxEntriesMessage = "";
    this.confirmation = new Confirmation();
    this.button = new Button();
    this.fields = new Array();
}

function Confirmation(){
    this.type = "message";
    this.message = gf_vars.formConfirmationMessage;
    this.url = "";
    this.pageId = "";
    this.queryString="";
}

function Button(){
    this.type = "text";
    this.text = gf_vars.buttonText;
    this.imageUrl = "";
}

function Field(id, type){
    this.id = id;
    this.label = "";
    this.adminLabel = "";
    this.type = type;
    this.isRequired = false;
    this.size = "medium";
    this.errorMessage = "";
    //NOTE: other properties will be added dynamically using associative array syntax
}

function Choice(text, value, price){
    this.text=text;
    this.value = value ? value : text;
    this.isSelected = false;
    this.price = price ? price : "";
}

function Input(id, label){
    this.id = id;
    this.label = label;
    this.name = "";
}

function ConditionalLogic(){
    this.actionType = "show"; //show or hide
    this.logicType = "all"; //any or all
    this.rules = [new ConditionalRule()];
}

function ConditionalRule(){
    this.fieldId = 0;
    this.operator = "is"; //is or isnot
    this.value = "";
}

var fieldSettings = {
    "html" :            ".label_setting, .content_setting, .conditional_logic_field_setting, .disable_margins_setting, .css_class_setting",
    "hidden" :          ".prepopulate_field_setting, .label_setting, .default_value_setting",
    "section" :         ".conditional_logic_field_setting, .label_setting, .description_setting, .visibility_setting, .css_class_setting",
    "page" :            ".next_button_setting, .previous_button_setting, .css_class_setting, .conditional_logic_page_setting, .conditional_logic_nextbutton_setting",
    "text" :            ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .size_setting, .input_mask_setting, .maxlen_setting, .password_field_setting, .rules_setting, .visibility_setting, .duplicate_setting, .default_value_setting, .description_setting, .css_class_setting",
    "creditcard" :      ".conditional_logic_field_setting, .force_ssl_field_setting, .credit_card_style_setting, .error_message_setting, .label_setting, .admin_label_setting, .rules_setting, .description_setting, .css_class_setting, .credit_card_setting",
    "website" :         ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .size_setting, .rules_setting, .visibility_setting, .duplicate_setting, .default_value_setting, .description_setting, .css_class_setting",
    "phone" :           ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .size_setting, .rules_setting, .duplicate_setting, .visibility_setting, .default_value_setting, .description_setting, .phone_format_setting, .css_class_setting",
    "number" :          ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .size_setting, .number_format_setting, .range_setting, .rules_setting, .visibility_setting, .duplicate_setting, .default_value_setting, .description_setting, .css_class_setting, .calculation_setting",
    "date" :            ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .rules_setting, .date_input_type_setting, .duplicate_setting, .visibility_setting, .date_format_setting, .default_value_setting, .description_setting, .css_class_setting",
    "time" :            ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .time_format_setting, .rules_setting, .duplicate_setting, .visibility_setting, .description_setting, .css_class_setting",
    "textarea" :        ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .maxlen_setting, .size_setting, .rules_setting, .visibility_setting, .duplicate_setting, .default_value_textarea_setting, .description_setting, .css_class_setting",
    "select" :          ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .enable_enhanced_ui_setting, .label_setting, .admin_label_setting, .size_setting, .choices_setting, .rules_setting,  .duplicate_setting, .visibility_setting, .description_setting, .css_class_setting",
    "multiselect" :     ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .enable_enhanced_ui_setting, .label_setting, .admin_label_setting, .size_setting, .choices_setting, .rules_setting, .visibility_setting, .description_setting, .css_class_setting",
    "checkbox" :        ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .choices_setting, .rules_setting, .visibility_setting, .description_setting, .css_class_setting",
    "radio" :           ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .choices_setting, .rules_setting, .visibility_setting, .duplicate_setting, .description_setting, .css_class_setting, .other_choice_setting",
    "name" :            ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .name_format_setting, .rules_setting, .visibility_setting, .description_setting, .css_class_setting",
    "address" :         ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .address_setting, .rules_setting, .description_setting, .visibility_setting, .css_class_setting",
    "fileupload" :      ".conditional_logic_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .rules_setting, .file_extensions_setting, .file_size_setting, .multiple_files_setting, .visibility_setting, .description_setting, .css_class_setting",
    "email" :           ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .email_confirm_setting, .admin_label_setting, .size_setting, .rules_setting, .visibility_setting, .duplicate_setting, .default_value_setting, .description_setting, .css_class_setting",
    "post_title" :      ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .admin_label_setting, .post_title_template_setting, .post_status_setting, .post_category_setting, .post_author_setting, .label_setting, .size_setting, .rules_setting, .visibility_setting, .default_value_setting, .description_setting, .css_class_setting, .post_format_setting",
    "post_content" :    ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .admin_label_setting, .maxlen_setting, .post_content_template_setting, .post_status_setting, .post_category_setting, .post_author_setting, .label_setting, .size_setting, .rules_setting, .visibility_setting, .default_value_textarea_setting, .description_setting, .css_class_setting, .post_format_setting",
    "post_excerpt" :    ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .admin_label_setting, .maxlen_setting, .post_status_setting, .post_category_setting, .post_author_setting, .label_setting, .size_setting, .rules_setting, .visibility_setting, .default_value_textarea_setting, .description_setting, .css_class_setting",
    "post_tags" :       ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .admin_label_setting, .label_setting, .post_tag_type_setting, .size_setting, .rules_setting, .visibility_setting, .default_value_setting, .description_setting, .css_class_setting",
    "post_category" :   ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .admin_label_setting, .post_category_checkbox_setting, .post_category_initial_item_setting, .label_setting, .size_setting, .rules_setting, .visibility_setting, .duplicate_setting, .description_setting, .css_class_setting, .post_category_field_type_setting",
    "post_image" :      ".conditional_logic_field_setting, .error_message_setting, .admin_label_setting, .post_image_setting, .label_setting, .rules_setting, .description_setting, .css_class_setting, .post_image_featured_image",
    "captcha"   :       ".captcha_type_setting, .captcha_size_setting, .captcha_fg_setting, .captcha_bg_setting, .conditional_logic_field_setting, .captcha_language_setting, .captcha_theme_setting, .error_message_setting, .label_setting, .description_setting, .css_class_setting",
    "product"   :       ".product_field_type_setting, .prepopulate_field_setting, .label_setting, .admin_label_setting, .description_setting, .css_class_setting",
    "singleproduct" :   ".base_price_setting, .disable_quantity_setting, .rules_setting, .duplicate_setting, .error_message_setting, .conditional_logic_field_setting",
    "calculation" :     ".disable_quantity_setting, .rules_setting, .duplicate_setting, .calculation_setting, .conditional_logic_field_setting",
    "price" :           ".rules_setting, .duplicate_setting, .error_message_setting, .conditional_logic_field_setting",
    "hiddenproduct" :   ".base_price_setting",
    "list" :            ".columns_setting, .maxrows_setting, .add_icon_url_setting, .delete_icon_url_setting, .conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .rules_setting, .visibility_setting, .description_setting, .css_class_setting",
    "shipping"   :      ".shipping_field_type_setting, .conditional_logic_field_setting, .prepopulate_field_setting, .label_setting, .admin_label_setting, .description_setting, .css_class_setting",
    "singleshipping":   ".base_price_setting",
    "option"    :       ".product_field_setting, .option_field_type_setting, .conditional_logic_field_setting, .prepopulate_field_setting, .label_setting, .admin_label_setting, .default_value_setting, .description_setting, .css_class_setting",
    "quantity"  :       ".product_field_setting, .quantity_field_type_setting, .conditional_logic_field_setting, .prepopulate_field_setting, .label_setting, .admin_label_setting, .default_value_setting, .description_setting, .css_class_setting",
    "donation"  :       ".conditional_logic_field_setting, .donation_field_type_setting, .prepopulate_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .rules_setting, .default_value_setting, .description_setting, .css_class_setting",
    "total"     :       ".conditional_logic_field_setting, .label_setting, .admin_label_setting, .description_setting, .css_class_setting",
    "post_custom_field" : ".conditional_logic_field_setting, .prepopulate_field_setting, .error_message_setting, .post_custom_field_setting, .post_custom_field_type_setting, .label_setting, .size_setting, .rules_setting, .visibility_setting, .duplicate_setting, .default_value_setting, .description_setting, .css_class_setting",
    "password" :        ".conditional_logic_field_setting, .error_message_setting, .label_setting, .admin_label_setting, .rules_setting, .duplicate_setting, .description_setting, .css_class_setting, .password_strength_setting"

}