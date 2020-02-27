<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Enqueue the styles and scripts required for the tooltips.
 */
function enqueue_tooltip_scripts() {

	wp_enqueue_style( 'gform_tooltip' );
	wp_enqueue_style( 'gform_font_awesome' );

	wp_enqueue_script( 'gform_tooltip_init' );

}
add_action( 'admin_enqueue_scripts', 'enqueue_tooltip_scripts' );

global $__gf_tooltips;
$__gf_tooltips = array(
	'notification_send_to_email'                  => '<h6>' . __( 'Send To Email Address', 'gravityforms' ) . '</h6>' . __( 'Enter the email address you would like the notification email sent to.', 'gravityforms' ),
	'notification_autoformat'                     => '<h6>' . __( 'Disable Auto-Formatting', 'gravityforms' ) . '</h6>' . __( 'When enabled, auto-formatting will insert paragraph breaks automatically. Disable auto-formatting when using HTML to create email notification content.', 'gravityforms' ),
	'notification_send_to_routing'                => '<h6>' . __( 'Routing', 'gravityforms' ) . '</h6>' . __( 'Allows notification to be sent to different email addresses depending on values selected in the form.', 'gravityforms' ),
	'notification_from_email'                     => '<h6>' . __( 'From Email Address', 'gravityforms' ) . '</h6>' . __( 'Enter an authorized email address you would like the notification email sent from. To avoid deliverability issues, always use your site domain in the from email.', 'gravityforms' ),
	'notification_from_name'                      => '<h6>' . __( 'From Name', 'gravityforms' ) . '</h6>' . __( 'Enter the name you would like the notification email sent from, or select the name from available name fields.', 'gravityforms' ),
	'notification_reply_to'                       => '<h6>' . __( 'Reply To', 'gravityforms' ) . '</h6>' . __( 'Enter the email address you would like to be used as the reply to address for the notification email.', 'gravityforms' ),
	'notification_cc'                             => '<h6>' . __( 'Carbon Copy Addresses', 'gravityforms' ) . '</h6>' . __( 'Enter a comma separated list of email addresses you would like to receive a CC of the notification email.', 'gravityforms' ),
	'notification_bcc'                            => '<h6>' . __( 'Blind Carbon Copy Addresses', 'gravityforms' ) . '</h6>' . __( 'Enter a comma separated list of email addresses you would like to receive a BCC of the notification email.', 'gravityforms' ),
	'notification_attachments'                    => '<h6>' . __( 'Attachments', 'gravityforms' ) . '</h6>' . __( 'When enabled, any files uploaded to File Upload fields will be attached to the notification email.', 'gravityforms' ),
	'form_activity'                               => '<h6>' . __( 'Limit Form Activity', 'gravityforms' ) . '</h6>' . __( 'Limit the number of entries a form can generate and/or schedule a time period the form is active.', 'gravityforms' ),
	'form_limit_entries'                          => '<h6>' . __( 'Limit Number of Entries', 'gravityforms' ) . '</h6>' . __( 'Enter a number in the input box below to limit the number of entries allowed for this form. The form will become inactive when that number is reached.', 'gravityforms' ),
	'form_schedule_form'                          => '<h6>' . __( 'Schedule Form', 'gravityforms' ) . '</h6>' . __( 'Schedule a time period the form is active.', 'gravityforms' ),
	'form_honeypot'                               => '<h6>' . __( 'Enable Anti-spam honeypot', 'gravityforms' ) . '</h6>' . __( 'Enables the honeypot spam protection technique, which is an alternative to the reCAPTCHA field.', 'gravityforms' ),
	'form_animation'                              => '<h6>' . __( 'Enable Animation', 'gravityforms' ) . '</h6>' . __( 'Check this option to enable a sliding animation when displaying/hiding conditional logic fields.', 'gravityforms' ),
	'form_title'                                  => '<h6>' . __( 'Form Title', 'gravityforms' ) . '</h6>' . __( 'Enter the title of your form.', 'gravityforms' ),
	'form_description'                            => '<h6>' . __( 'Form Description', 'gravityforms' ) . '</h6>' . __( 'Enter a description for your form. This may be used for user instructions.', 'gravityforms' ),
	'form_label_placement'                        => '<h6>' . __( 'Form Label Placement', 'gravityforms' ) . '</h6>' . __( 'Select the default label placement.  Labels can be top aligned above a field, left aligned to the left of a field, or right aligned to the right of a field. This is a global label placement setting', 'gravityforms' ),
	'form_description_placement'                  => '<h6>' . __( 'Description Placement', 'gravityforms' ) . '</h6>' . __( 'Select the default description placement.  Descriptions can be placed above the field inputs or below the field inputs. This setting can be overridden in the appearance settings for each field.', 'gravityforms' ),
	'form_sub_label_placement'                    => '<h6>' . __( 'Sub-Label Placement', 'gravityforms' ) . '</h6>' . __( 'Select the default sub-label placement.  Sub-labels can be placed above the field inputs or below the field inputs. This setting can be overridden in the appearance settings for each field.', 'gravityforms' ),
	'form_button_text'                            => '<h6>' . __( 'Form Button Text', 'gravityforms' ) . '</h6>' . __( 'Enter the text you would like to appear on the form submit button.', 'gravityforms' ),
	'form_button_image'                           => '<h6>' . __( 'Form Button Image', 'gravityforms' ) . '</h6>' . __( 'Enter the path to an image you would like to use as the form submit button.', 'gravityforms' ),
	'form_css_class'                              => '<h6>' . __( 'Form CSS Class Name', 'gravityforms' ) . '</h6>' . __( 'Enter the CSS class name you would like to use in order to override the default styles for this form.', 'gravityforms' ),
	'form_field_add_icon_url'                     => '<h6>' . __( 'Add Icon URL', 'gravityforms' ) . '</h6>' . __( "Enter the URL of a custom image to replace the default 'add item' icon. A maximum size of 16px by 16px is recommended", 'gravityforms' ),
	'form_field_delete_icon_url'                  => '<h6>' . __( 'Delete Icon URL', 'gravityforms' ) . '</h6>' . __( "Enter the URL of a custom image to replace the default 'delete item' icon. A maximum size of 16px by 16px is recommended", 'gravityforms' ),
	'form_confirmation_message'                   => '<h6>' . __( 'Confirmation Message Text', 'gravityforms' ) . '</h6>' . __( 'Enter the text you would like the user to see on the confirmation page of this form.', 'gravityforms' ),
	'form_confirmation_autoformat'                => '<h6>' . __( 'Disable Auto-Formatting', 'gravityforms' ) . '</h6>' . __( 'When enabled, auto-formatting will insert paragraph breaks automatically. Disable auto-formatting when using HTML to create the confirmation content.', 'gravityforms' ),
	'form_redirect_to_webpage'                    => '<h6>' . __( 'Redirect Form to Page', 'gravityforms' ) . '</h6>' . __( 'Select the page you would like the user to be redirected to after they have submitted the form.', 'gravityforms' ),
	'form_redirect_to_url'                        => '<h6>' . __( 'Redirect Form to URL', 'gravityforms' ) . '</h6>' . __( 'Enter the URL of the webpage you would like the user to be redirected to after they have submitted the form.', 'gravityforms' ),
	                                                 /* Translators: %s: Link to article about query strings. */
	'form_redirect_querystring'                   => '<h6>' . __( 'Pass Data Via Query String', 'gravityforms' ) . '</h6>' . sprintf( __( "To pass field data to the confirmation page, build a Query String using the 'Insert Merge Tag' drop down. %s..more info on querystrings &raquo;%s", 'gravityforms' ), "<a href='https://en.wikipedia.org/wiki/Query_string' target='_blank'>", '</a>' ),
	'form_field_label'                            => '<h6>' . __( 'Field Label', 'gravityforms' ) . '</h6>' . __( 'Enter the label of the form field.  This is the field title the user will see when filling out the form.', 'gravityforms' ),
	'form_field_label_html'                       => '<h6>' . __( 'Field Label', 'gravityforms' ) . '</h6>' . __( 'Enter the label for this HTML block. It will help you identify your HTML blocks in the form editor, but it will not be displayed on the form.', 'gravityforms' ),
	'form_field_disable_margins'                  => '<h6>' . __( 'Disable Default Margins', 'gravityforms' ) . '</h6>' . __( 'When enabled, margins are added to properly align the HTML content with other form fields.', 'gravityforms' ),
	'form_field_recaptcha_theme'                  => '<h6>' . __( 'reCAPTCHA Theme', 'gravityforms' ) . '</h6>' . __( 'Select the visual theme for the reCAPTCHA field from the available options to better match your site design.', 'gravityforms' ),
	'form_field_captcha_type'                     => '<h6>' . __( 'CAPTCHA Type', 'gravityforms' ) . '</h6>' . __( 'Select the type of CAPTCHA you would like to use.', 'gravityforms' ),
	'form_field_recaptcha_badge'                  => '<h6>' . __( 'CAPTCHA Badge Position', 'gravityforms' ) . '</h6>' . __( "Select the position of the badge containing the links to Google's privacy policy and terms.", 'gravityforms' ),
	'form_field_custom_field_name'                => '<h6>' . __( 'Custom Field Name', 'gravityforms' ) . '</h6>' . __( 'Select the custom field name from available existing custom fields, or enter a new custom field name.', 'gravityforms' ),
	'form_field_type'                             => '<h6>' . __( 'Field type', 'gravityforms' ) . '</h6>' . __( 'Select the type of field from the available form fields.', 'gravityforms' ),
	'form_field_maxlength'                        => '<h6>' . __( 'Maximum Characters', 'gravityforms' ) . '</h6>' . __( 'Enter the maximum number of characters that this field is allowed to have.', 'gravityforms' ),
	'form_field_maxrows'                          => '<h6>' . __( 'Maximum Rows', 'gravityforms' ) . '</h6>' . __( 'Enter the maximum number of rows that users are allowed to add.', 'gravityforms' ),
	'form_field_date_input_type'                  => '<h6>' . __( 'Date Input Type', 'gravityforms' ) . '</h6>' . __( 'Select the type of inputs you would like to use for the date field. Date Picker will let users select a date from a calendar. Date Field will let users free type the date.', 'gravityforms' ),
	'form_field_address_type'                     => '<h6>' . __( 'Address Type', 'gravityforms' ) . '</h6>' . __( 'Select the type of address you would like to use.', 'gravityforms' ),
	'form_field_address_default_state_us'         => '<h6>' . __( 'Default State', 'gravityforms' ) . '</h6>' . __( 'Select the state you would like to be selected by default when the form gets displayed.', 'gravityforms' ),
	'form_field_address_default_state_canadian'   => '<h6>' . __( 'Default Province', 'gravityforms' ) . '</h6>' . __( 'Select the province you would like to be selected by default when the form gets displayed.', 'gravityforms' ),
	'form_field_address_default_country'          => '<h6>' . __( 'Default Country', 'gravityforms' ) . '</h6>' . __( 'Select the country you would like to be selected by default when the form gets displayed.', 'gravityforms' ),
	'form_field_address_hide_country'             => '<h6>' . __( 'Hide Country', 'gravityforms' ) . '</h6>' . __( 'For addresses that only apply to one country, you can choose to not display the country drop down. Entries will still be recorded with the selected country.', 'gravityforms' ),
	'form_field_address_hide_address2'            => '<h6>' . __( 'Hide Address Line 2', 'gravityforms' ) . '</h6>' . __( 'Check this box to prevent the extra address input (Address Line 2) from being displayed in the form.', 'gravityforms' ),
	'form_field_address_hide_state_us'            => '<h6>' . __( 'Hide State Field', 'gravityforms' ) . '</h6>' . __( 'Check this box to prevent the State field from being displayed in the form.', 'gravityforms' ),
	'form_field_address_hide_state_canadian'      => '<h6>' . __( 'Hide Province Field', 'gravityforms' ) . '</h6>' . __( 'Check this box to prevent Province field from being displayed in the form.', 'gravityforms' ),
	'form_field_address_hide_state_international' => '<h6>' . __( 'Hide State/Province/Region', 'gravityforms' ) . '</h6>' . __( 'Check this box to prevent the State/Province/Region from being displayed in the form.', 'gravityforms' ),
	'form_field_name_format'                      => '<h6>' . __( 'Field Name Format', 'gravityforms' ) . '</h6>' . __( 'Select the format you would like to use for the Name field.  There are 3 options, Normal which includes First and Last Name, Extended which adds Prefix and Suffix, or Simple which is a single input field.', 'gravityforms' ),
	'form_field_number_format'                    => '<h6>' . __( 'Number Format', 'gravityforms' ) . '</h6>' . __( 'Select the format of numbers that are allowed in this field. You have the option to use a comma or a dot as the decimal separator.', 'gravityforms' ),
	'form_field_force_ssl'                        => '<h6>' . __( 'Force SSL', 'gravityforms' ) . '</h6>' . __( 'Check this box to prevent this field from being displayed in a non-secure page (i.e. not https://). It will redirect the page to the same URL, but starting with https:// instead. This option requires a properly configured SSL certificate.', 'gravityforms' ),
	'form_field_card_style'                       => '<h6>' . __( 'Credit Card Icon Style', 'gravityforms' ) . '</h6>' . __( 'Select the style you would like to use for the credit card icons.', 'gravityforms' ),
	'form_field_date_format'                      => '<h6>' . __( 'Field Date Format', 'gravityforms' ) . '</h6>' . __( 'Select the format you would like to use for the date input.', 'gravityforms' ),
	'form_field_time_format'                      => '<h6>' . __( 'Time Format', 'gravityforms' ) . '</h6>' . __( 'Select the format you would like to use for the time field.  Available options are 12 hour (i.e. 8:30 pm) and 24 hour (i.e. 20:30).', 'gravityforms' ),
	'form_field_fileupload_allowed_extensions'    => '<h6>' . __( 'Allowed File Extensions', 'gravityforms' ) . '</h6>' . __( 'Enter the allowed file extensions for file uploads.  This will limit the type of files a user may upload.', 'gravityforms' ),
	'form_field_multiple_files'                   => '<h6>' . __( 'Enable Multi-File Upload', 'gravityforms' ) . '</h6>' . __( 'Select this option to enable multiple files to be uploaded for this field.', 'gravityforms' ),
	'form_field_max_files'                        => '<h6>' . __( 'Maximum Number of Files', 'gravityforms' ) . '</h6>' . __( "Specify the maximum number of files that can be uploaded using this field. Leave blank for unlimited. Note that the actual number of files permitted may be limited by this server's specifications and configuration.", 'gravityforms' ),
	'form_field_max_file_size'                    => '<h6>' . __( 'Maximum File Size', 'gravityforms' ) . '</h6>' . __( 'Specify the maximum file size in megabytes allowed for each of the files.', 'gravityforms' ),
	'form_field_phone_format'                     => '<h6>' . __( 'Phone Number Format', 'gravityforms' ) . '</h6>' . __( 'Select the format you would like to use for the phone input.  Available options are domestic US/CANADA style phone number and international long format phone number.', 'gravityforms' ),
	'form_field_description'                      => '<h6>' . __( 'Field Description', 'gravityforms' ) . '</h6>' . __( 'Enter the description for the form field.  This will be displayed to the user and provide some direction on how the field should be filled out or selected.', 'gravityforms' ),
	'form_field_required'                         => '<h6>' . __( 'Required Field', 'gravityforms' ) . '</h6>' . __( 'Select this option to make the form field required.  A required field will prevent the form from being submitted if it is not filled out or selected.', 'gravityforms' ),
	'form_field_no_duplicate'                     => '<h6>' . __( 'No Duplicates', 'gravityforms' ) . '</h6>' . __( 'Select this option to limit user input to unique values only.  This will require that a value entered in a field does not currently exist in the entry database for that field.', 'gravityforms' ),
	'form_field_hide_label'                       => '<h6>' . __( 'Hide Field Label', 'gravityforms' ) . '</h6>' . __( 'Select this option to hide the field label in the form.', 'gravityforms' ),
	'form_field_number_range'                     => '<h6>' . __( 'Number Range', 'gravityforms' ) . '</h6>' . __( 'Enter the minimum and maximum values for this form field.  This will require that the value entered by the user must fall within this range.', 'gravityforms' ),
	'form_field_enable_calculation'               => '<h6>' . __( 'Enable Calculation', 'gravityforms' ) . '</h6>' . __( 'Enabling calculations will allow the value of this field to be dynamically calculated based on a mathematical formula.', 'gravityforms' ),
	'form_field_calculation_formula'              => '<h6>' . __( 'Formula', 'gravityforms' ) . '</h6>' . __( 'Specify a mathematical formula. The result of this formula will be dynamically populated as the value for this field.', 'gravityforms' ),
	'form_field_calculation_rounding'             => '<h6>' . __( 'Rounding', 'gravityforms' ) . '</h6>' . __( 'Specify how many decimal places the number should be rounded to.', 'gravityforms' ),
	'form_field_admin_label'                      => '<h6>' . __( 'Admin Label', 'gravityforms' ) . '</h6>' . __( 'Enter the admin label of the form field.  Entering a value in this field will override the Field Label when displayed in the Gravity Forms administration tool.', 'gravityforms' ),
	'form_field_sub_labels'                       => '<h6>' . __( 'Sub-Labels', 'gravityforms' ) . '</h6>' . __( 'Enter values in this setting to override the Sub-Label for each field.', 'gravityforms' ),
	'form_field_label_placement'                  => '<h6>' . __( 'Label Visibility', 'gravityforms' ) . '</h6>' . __( 'Select the label visibility for this field.  Labels can either inherit the form setting or be hidden.', 'gravityforms' ),
	'form_field_description_placement'            => '<h6>' . __( 'Description Placement', 'gravityforms' ) . '</h6>' . __( 'Select the description placement.  Descriptions can be placed above the field inputs or below the field inputs.', 'gravityforms' ),
	'form_field_sub_label_placement'              => '<h6>' . __( 'Sub-Label Placement', 'gravityforms' ) . '</h6>' . __( 'Select the sub-label placement.  Sub-labels can be placed above the field inputs or below the field inputs.', 'gravityforms' ),
	'form_field_size'                             => '<h6>' . __( 'Field Size', 'gravityforms' ) . '</h6>' . __( 'Select a form field size from the available options. This will set the width of the field. Please note: if using a paragraph field, the size applies only to the height of the field.', 'gravityforms' ),
	'form_field_name_fields'                      => '<h6>' . __( 'Name Fields', 'gravityforms' ) . '</h6>' . __( "Select the fields you'd like to use in this Name field and customize the Sub-Labels by entering new ones.", 'gravityforms' ),
	'form_field_name_prefix_choices'              => '<h6>' . __( 'Name Prefix Choices', 'gravityforms' ) . '</h6>' . __( 'Add Choices to this field. You can mark a choice as selected by default by using the radio buttons on the left.', 'gravityforms' ),
	'form_field_address_fields'                   => '<h6>' . __( 'Address Fields', 'gravityforms' ) . '</h6>' . __( "Select the fields you'd like to use in this Address Field and customize the Sub-Labels by entering new ones.", 'gravityforms' ),
	'form_field_default_value'                    => '<h6>' . __( 'Default Value', 'gravityforms' ) . '</h6>' . __( 'If you would like to pre-populate the value of a field, enter it here.', 'gravityforms' ),
	'form_field_default_input_values'             => '<h6>' . __( 'Default Values', 'gravityforms' ) . '</h6>' . __( 'If you would like to pre-populate the value of a field, enter it here.', 'gravityforms' ),
	'form_field_placeholder'                      => '<h6>' . __( 'Placeholder', 'gravityforms' ) . '</h6>' . __( 'The Placeholder will not be submitted along with the form. Use the Placeholder to give a hint at the expected value or format.', 'gravityforms' ),
	'form_field_input_placeholders'               => '<h6>' . __( 'Placeholders', 'gravityforms' ) . '</h6>' . __( 'Placeholders will not be submitted along with the form. Use Placeholders to give a hint at the expected value or format.', 'gravityforms' ),
	'form_field_enable_copy_values_option'        => '<h6>' . __( 'Use Values Submitted in a Different Field', 'gravityforms' ) . '</h6>' . __( 'Activate this option to allow users to skip this field and submit the values entered in the associated field. For example, this is useful for shipping and billing address fields.', 'gravityforms' ),
	'form_field_copy_values_option_label'         => '<h6>' . __( 'Option Label', 'gravityforms' ) . '</h6>' . __( 'Enter the label to be displayed next to the check box. For example, &quot;same as shipping address&quot;.', 'gravityforms' ),
	'form_field_copy_values_option_field'         => '<h6>' . __( 'Source Field', 'gravityforms' ) . '</h6>' . __( 'Select the field to be used as the source for the values for this field.', 'gravityforms' ),
	'form_field_copy_values_option_default'       => '<h6>' . __( 'Activated by Default', 'gravityforms' ) . '</h6>' . __( 'Select this setting to display the option as activated by default when the form first loads.', 'gravityforms' ),
	'form_field_validation_message'               => '<h6>' . __( 'Validation Message', 'gravityforms' ) . '</h6>' . __( 'If you would like to override the default error validation for a field, enter it here.  This message will be displayed if there is an error with this field when the user submits the form.', 'gravityforms' ),
	'form_field_recaptcha_language'               => '<h6>' . __( 'reCAPTCHA Language', 'gravityforms' ) . '</h6>' . __( 'Select the language you would like to use for the reCAPTCHA display from the available options.', 'gravityforms' ),
	'form_field_css_class'                        => '<h6>' . __( 'CSS Class Name', 'gravityforms' ) . '</h6>' . __( 'Enter the CSS class name you would like to use in order to override the default styles for this field.', 'gravityforms' ),
	'form_field_visibility'                       => GFCommon::get_visibility_tooltip(),
	'form_field_choices'                          => '<h6>' . __( 'Field Choices', 'gravityforms' ) . '</h6>' . __( 'Define the choices for this field. If the field type supports it you will also be able to select the default choice(s) using a radio or checkbox located to the left of the choice.', 'gravityforms' ),
	'form_field_choice_values'                    => '<h6>' . __( 'Enable Choice Values', 'gravityforms' ) . '</h6>' . __( 'Check this option to specify a value for each choice. Choice values are not displayed to the user viewing the form, but are accessible to administrators when viewing the entry.', 'gravityforms' ),
	'form_field_conditional_logic'                => '<h6>' . __( 'Conditional Logic', 'gravityforms' ) . '</h6>' . __( 'Create rules to dynamically display or hide this field based on values from another field.', 'gravityforms' ),
	                                                 /* Translators: %s: Link to Chosen jQuery framework. */
	'form_field_enable_enhanced_ui'               => '<h6>' . __( 'Enable Enhanced UI', 'gravityforms' ) . '</h6>' . sprintf( __( "By selecting this option, the %s jQuery script will be applied to this field, enabling search capabilities to Drop Down fields and a more user-friendly interface for Multi Select fields.", 'gravityforms' ), "<a href='https://harvesthq.github.com/chosen/' target='_blank'>Chosen</a>" ),
	'form_field_checkbox_label'                    => '<h6>' . __( 'Checkbox Text', 'gravityforms' ) . '</h6>' . __( 'Text of the consent checkbox.', 'gravityforms' ),
	'form_field_select_all_choices'               => '<h6>' . __( '"Select All" Choice', 'gravityforms' ) . '</h6>' . __( 'Check this option to add a "Select All" checkbox before the checkbox choices to allow users to check all the checkboxes with one click.', 'gravityforms' ),
	'form_field_other_choice'                     => '<h6>' . __( '"Other" Choice', 'gravityforms' ) . '</h6>' . __( 'Check this option to add a text input as the final choice of your radio button field. This allows the user to specify a value that is not a predefined choice.', 'gravityforms' ),
	'form_require_login'                          => '<h6>' . __( 'Require user to be logged in', 'gravityforms' ) . '</h6>' . __( 'Check this option to require a user to be logged in to view this form.', 'gravityforms' ),
	'form_require_login_message'                  => '<h6>' . __( 'Require Login Message', 'gravityforms' ) . '</h6>' . __( 'Enter a message to be displayed to users who are not logged in (shortcodes and HTML are supported).', 'gravityforms' ),
	'form_page_conditional_logic'                 => '<h6>' . __( 'Page Conditional Logic', 'gravityforms' ) . '</h6>' . __( 'Create rules to dynamically display or hide this page based on values from another field.', 'gravityforms' ),
	'form_progress_indicator'                     => '<h6>' . __( 'Progress Indicator', 'gravityforms' ) . '</h6>' . __( 'Select which type of visual progress indicator you would like to display.  Progress Bar, Steps or None.', 'gravityforms' ),
	'form_percentage_style'                       => '<h6>' . __( 'Progress Bar Style', 'gravityforms' ) . '</h6>' . __( 'Select which progress bar style you would like to use.  Select custom to choose your own text and background color.', 'gravityforms' ),
	'form_page_names'                             => '<h6>' . __( 'Page Names', 'gravityforms' ) . '</h6>' . __( 'Name each of the pages on your form.  Page names are displayed with the selected progress indicator.', 'gravityforms' ),
	'next_button_text'                            => '<h6>' . __( 'Next Button Text', 'gravityforms' ) . '</h6>' . __( 'Enter the text you would like to appear on the page next button.', 'gravityforms' ),
	'next_button_image'                           => '<h6>' . __( 'Next Button Image', 'gravityforms' ) . '</h6>' . __( 'Enter the path to an image you would like to use as the page next button.', 'gravityforms' ),
	'previous_button_text'                        => '<h6>' . __( 'Previous Button Text', 'gravityforms' ) . '</h6>' . __( 'Enter the text you would like to appear on the page previous button.', 'gravityforms' ),
	'previous_button_image'                       => '<h6>' . __( 'Previous Button Image', 'gravityforms' ) . '</h6>' . __( 'Enter the path to an image you would like to use as the page previous button.', 'gravityforms' ),
	'form_nextbutton_conditional_logic'           => '<h6>' . __( 'Next Button Conditional Logic', 'gravityforms' ) . '</h6>' . __( "Create rules to dynamically display or hide the page's Next Button based on values from another field.", 'gravityforms' ),
	'form_button_conditional_logic'               => '<h6>' . __( 'Conditional Logic', 'gravityforms' ) . '</h6>' . __( 'Create rules to dynamically display or hide the submit button based on values from another field.', 'gravityforms' ),
	'form_field_post_category_selection'          => '<h6>' . __( 'Post Category', 'gravityforms' ) . '</h6>' . __( 'Select which categories are displayed. You can choose to display all of them or select individual ones.', 'gravityforms' ),
	'form_field_post_status'                      => '<h6>' . __( 'Post Status', 'gravityforms' ) . '</h6>' . __( 'Select the post status that will be used for the post that is created by the form entry.', 'gravityforms' ),
	'form_field_post_author'                      => '<h6>' . __( 'Post Author', 'gravityforms' ) . '</h6>' . __( 'Select the author that will be used for the post that is created by the form entry.', 'gravityforms' ),
	'form_field_post_format'                      => '<h6>' . __( 'Post Format', 'gravityforms' ) . '</h6>' . __( 'Select the post format that will be used for the post that is created by the form entry.', 'gravityforms' ),
	'form_field_post_content_template_enable'     => '<h6>' . __( 'Post Content Template', 'gravityforms' ) . '</h6>' . __( 'Check this option to format and insert merge tags into the Post Content.', 'gravityforms' ),
	'form_field_post_title_template_enable'       => '<h6>' . __( 'Post Title Template', 'gravityforms' ) . '</h6>' . __( 'Check this option to format and insert merge tags into the Post Title.', 'gravityforms' ),
	'form_field_post_category'                    => '<h6>' . __( 'Post Category', 'gravityforms' ) . '</h6>' . __( 'Select the category that will be used for the post that is created by the form entry.', 'gravityforms' ),
	'form_field_current_user_as_author'           => '<h6>' . __( 'Use Current User as Author', 'gravityforms' ) . '</h6>' . __( 'Selecting this option will set the post author to the WordPress user that submitted the form.', 'gravityforms' ),
	'form_field_image_meta'                       => '<h6>' . __( 'Image Meta', 'gravityforms' ) . '</h6>' . __( 'Select one or more image metadata field to be displayed along with the image upload field. They enable users to enter additional information about the uploaded image.', 'gravityforms' ),
	'form_field_featured_image'                   => '<h6>' . __( 'Set as Featured Image', 'gravityforms' ) . '</h6>' . __( "Check this option to set this image as the post's Featured Image.", 'gravityforms' ),
	'form_field_prepopulate'                      => '<h6>' . __( 'Incoming Field Data', 'gravityforms' ) . '</h6>' . __( 'Check this option to enable data to be passed to the form and pre-populate this field dynamically. Data can be passed via Query Strings, Shortcode and/or Hooks.', 'gravityforms' ),
	'form_field_content'                          => '<h6>' . __( 'Content', 'gravityforms' ) . '</h6>' . __( 'Enter the content (Text or HTML) to be displayed on the form.', 'gravityforms' ),
	'form_field_base_price'                       => '<h6>' . __( 'Base Price', 'gravityforms' ) . '</h6>' . __( 'Enter the base price for this product.', 'gravityforms' ),
	'form_field_disable_quantity'                 => '<h6>' . __( 'Disable Quantity', 'gravityforms' ) . '</h6>' . __( 'Disables the quantity field.  A quantity of 1 will be assumed or you can add a Quantity field to your form from the Pricing Fields.', 'gravityforms' ),
	'form_field_product'                          => '<h6>' . __( 'Product Field', 'gravityforms' ) . '</h6>' . __( 'Select which Product this field is tied to.', 'gravityforms' ),
	'form_field_mask'                             => '<h6>' . __( 'Input Mask', 'gravityforms' ) . '</h6>' . __( 'Input masks provide a visual guide allowing users to more easily enter data in a specific format such as dates and phone numbers.', 'gravityforms' ),
	'form_standard_fields'                        => '<h6>' . __( 'Standard Fields', 'gravityforms' ) . '</h6>' . __( 'Standard Fields provide basic form functionality.', 'gravityforms' ),
	'form_advanced_fields'                        => '<h6>' . __( 'Advanced Fields', 'gravityforms' ) . '</h6>' . __( 'Advanced Fields are for specific uses.  They enable advanced formatting of regularly used fields such as Name, Email, Address, etc.', 'gravityforms' ),
	'form_post_fields'                            => '<h6>' . __( 'Post Fields', 'gravityforms' ) . '</h6>' . __( 'Post Fields allow you to add fields to your form that create Post Drafts in WordPress from the submitted data.', 'gravityforms' ),
	'form_pricing_fields'                         => '<h6>' . __( 'Pricing Fields', 'gravityforms' ) . '</h6>' . __( 'Pricing fields allow you to add fields to your form that calculate pricing for selling goods and services.', 'gravityforms' ),
	'export_select_form'                          => '<h6>' . __( 'Export Selected Form', 'gravityforms' ) . '</h6>' . __( 'Select the form you would like to export entry data from. You may only export data from one form at a time.', 'gravityforms' ),
	'export_select_forms'                         => '<h6>' . __( 'Export Selected Forms', 'gravityforms' ) . '</h6>' . __( 'Select the forms you would like to export.', 'gravityforms' ),
	'export_conditional_logic'                    => '<h6>' . __( 'Conditional Logic', 'gravityforms' ) . '</h6>' . __( 'Filter the entries by adding conditions.', 'gravityforms' ),
	'export_select_fields'                        => '<h6>' . __( 'Export Selected Fields', 'gravityforms' ) . '</h6>' . __( 'Select the fields you would like to include in the export.', 'gravityforms' ),
	'export_date_range'                           => '<h6>' . __( 'Export Date Range', 'gravityforms' ) . '</h6>' . __( 'Select a date range. Setting a range will limit the export to entries submitted during that date range. If no range is set, all entries will be exported.', 'gravityforms' ),
	'import_select_file'                          => '<h6>' . __( 'Select Files', 'gravityforms' ) . '</h6>' . __( 'Click the file selection button to upload a Gravity Forms export file from your computer.', 'gravityforms' ),
	'settings_license_key'                        => '<h6>' . __( 'Settings License Key', 'gravityforms' ) . '</h6>' . __( 'Your Gravity Forms support license key is used to verify your support package, enable automatic updates and receive support.', 'gravityforms' ),
	'settings_output_css'                         => '<h6>' . __( 'Output CSS', 'gravityforms' ) . '</h6>' . __( 'Select yes or no to enable or disable CSS output.  Setting this to no will disable the standard Gravity Forms CSS from being included in your theme.', 'gravityforms' ),
	'settings_html5'                              => '<h6>' . __( 'Output HTML5', 'gravityforms' ) . '</h6>' . __( 'Select yes or no to enable or disable HTML5 output. Setting this to no will disable the standard Gravity Forms HTML5 form field output.', 'gravityforms' ),
	'settings_noconflict'                         => '<h6>' . __( 'No-Conflict Mode', 'gravityforms' ) . '</h6>' . __( 'Select On or Off to enable or disable no-conflict mode. Setting this to On will prevent extraneous scripts and styles from being printed on Gravity Forms admin pages, reducing conflicts with other plugins and themes.', 'gravityforms' ),
	'settings_recaptcha_public'                   => '<h6>' . __( 'reCAPTCHA Site Key', 'gravityforms' ) . '</h6>' . __( 'Enter your reCAPTCHA Site Key, if you do not have a key you can register for one at the provided link.  reCAPTCHA is a free service.', 'gravityforms' ),
	'settings_recaptcha_private'                  => '<h6>' . __( 'reCAPTCHA Secret Key', 'gravityforms' ) . '</h6>' . __( 'Enter your reCAPTCHA Secret Key, if you do not have a key you can register for one at the provided link.  reCAPTCHA is a free service.', 'gravityforms' ),
	'settings_recaptcha_type'                     => '<h6>' . __( 'reCAPTCHA Type', 'gravityforms' ) . '</h6>' . __( 'Select the type of reCAPTCHA you would like to use.', 'gravityforms' ),
	'settings_currency'                           => '<h6>' . __( 'Currency', 'gravityforms' ) . '</h6>' . __( 'Please select the currency for your location.  Currency is used for pricing fields and price calculations.', 'gravityforms' ),
	'settings_akismet'                            => '<h6>' . __( 'Akismet Integration', 'gravityforms' ) . '</h6>' . __( 'Protect your form entries from spam using Akismet.', 'gravityforms' ),
	'entries_conversion'                          => '<h6>' . __( 'Entries Conversion', 'gravityforms' ) . '</h6>' . __( 'Conversion is the percentage of form views that generated an entry. If a form was viewed twice, and one entry was generated, the conversion will be 50%.', 'gravityforms' ),
	'widget_tabindex'                             => '<h6>' . __( 'Tab Index Start Value', 'gravityforms' ) . '</h6>' . __( 'If you have other forms on the page (i.e. Comments Form), specify a higher tabindex start value so that your Gravity Form does not end up with the same tabindices as your other forms. To disable the tabindex, enter 0 (zero).', 'gravityforms' ),
	'notification_override_email'                 => '<h6>' . __( 'Override Notifications', 'gravityforms' ) . '</h6>' . __( 'Enter a comma separated list of email addresses you would like to receive the selected notification emails.', 'gravityforms' ),
	'form_percentage_confirmation_display'        => '<h6>' . __( 'Progress Bar Confirmation Display', 'gravityforms' ) . '</h6>' . __( 'Check this box if you would like the progress bar to display with the confirmation text.', 'gravityforms' ),
	'percentage_confirmation_page_name'           => '<h6>' . __( 'Progress Bar Completion Text', 'gravityforms' ) . '</h6>' . __( 'Enter text to display at the top of the progress bar.', 'gravityforms' ),
	'form_field_rich_text_editor'                 => '<h6>' . __( 'Use Rich Text Editor', 'gravityforms' ) . '</h6>' . __( 'Check this box if you would like to use the rich text editor for this field.', 'gravityforms' ),
	'personal_data_enable'                        => '<h6>' . __( 'Enable Personal Data Tools', 'gravityforms' ) . '</h6>' . __( 'Check this box if you would like to include data from this form when exporting or erasing personal data on this site.', 'gravityforms' ),
	'personal_data_identification'                => '<h6>' . __( 'Identification', 'gravityforms' ) . '</h6>' . __( 'Select the field which will be used to identify the owner of the personal data.', 'gravityforms' ),
	'personal_data_field_settings'                => '<h6>' . __( 'Field Settings', 'gravityforms' ) . '</h6>' . __( 'Select the fields which will be included when exporting or erasing personal data.', 'gravityforms' ),
	'personal_data_prevent_ip'                    => '<h6>' . __( 'IP Address', 'gravityforms' ) . '</h6>' . __( 'Check this box if you would like to prevent the IP address from being stored during form submission.', 'gravityforms' ),
	'personal_data_retention_policy'              => '<h6>' . __( 'Retention Policy', 'gravityforms' ) . '</h6>' . __( 'Use these settings to keep entries only as long as they are needed. Trash or delete entries automatically older than the specified number of days. The minimum number of days allowed is one. This is to ensure that all entry processing is complete before deleting/trashing. The number of days setting is a minimum, not an exact period of time. The trashing/deleting occurs during the daily cron task so some entries may appear to remain up to a day longer than expected.', 'gravityforms' ),
    'form_field_password_visibility_enable'       => '<h6>' . __( 'Password Visibility Toggle', 'gravityforms' ) . '</h6>' . __( 'Check this box to add a toggle allowing the user to see the password they are entering in.', 'gravityforms' ),
);

/**
 * Displays the tooltip
 *
 * @global $__gf_tooltips
 *
 * @param string $name      The name of the tooltip to be displayed
 * @param string $css_class Optional. The CSS class to apply toi the element. Defaults to empty string.
 * @param bool   $return    Optional. If the tooltip should be returned instead of output. Defaults to false (output)
 *
 * @return string
 */
function gform_tooltip( $name, $css_class = '', $return = false ) {
	global $__gf_tooltips; //declared as global to improve WPML performance

	$css_class     = empty( $css_class ) ? 'tooltip' : $css_class;
	/**
	 * Filters the tooltips available
	 *
	 * @param array $__gf_tooltips Array containing the available tooltips
	 */
	$__gf_tooltips = apply_filters( 'gform_tooltips', $__gf_tooltips );

	//AC: the $name parameter is a key when it has only one word. Maybe try to improve this later.
	$parameter_is_key = count( explode( ' ', $name ) ) == 1;

	$tooltip_text  = $parameter_is_key ? rgar( $__gf_tooltips, $name ) : $name;
	$tooltip_class = isset( $__gf_tooltips[ $name ] ) ? "tooltip_{$name}" : '';
	$tooltip_class = esc_attr( $tooltip_class );

	if ( empty( $tooltip_text ) ) {
		return '';
	}

	$tooltip = "<a href='#' onclick='return false;' onkeypress='return false;' class='gf_tooltip " . esc_attr( $css_class ) . " {$tooltip_class}' title='" . esc_attr( $tooltip_text ) . "'><i class='fa fa-question-circle'></i></a>";

	if ( $return ) {
		return $tooltip;
	} else {
		echo $tooltip;
	}
}
