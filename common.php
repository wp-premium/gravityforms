<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GFCommon
 *
 * Includes common methods accessed throughout Gravity Forms and add-ons.
 */
class GFCommon {

	// deprecated; set to GFForms::$version in GFForms::init() for backwards compat
	public static $version = null;

	public static $tab_index = 1;
	public static $errors = array();
	public static $messages = array();
	public static $email_boundary = '394c21ef2c7143749256c37c3b5b7ee0';
	/**
	 * An array of dismissible messages to display on the page.
	 *
	 * @var array $dismissible_messages
	 */
	public static $dismissible_messages = array();

	public static function get_selection_fields( $form, $selected_field_id ) {

		$str = '';
		foreach ( $form['fields'] as $field ) {
			$input_type  = RGFormsModel::get_input_type( $field );
			$field_label = RGFormsModel::get_label( $field );
			if ( $input_type == 'checkbox' || $input_type == 'radio' || $input_type == 'select' ) {
				$selected = $field->id == $selected_field_id ? "selected='selected'" : '';
				$str .= "<option value='" . $field->id . "' " . $selected . '>' . $field_label . '</option>';
			}
		}

		return $str;
	}

	public static function is_numeric( $value, $number_format = '' ) {

		if ( $number_format == 'currency' ) {

			$number_format = self::is_currency_decimal_dot() ? 'decimal_dot' : 'decimal_comma';
			$value         = self::remove_currency_symbol( $value );
		}

		switch ( $number_format ) {
			case 'decimal_dot' :
				return preg_match( "/^(-?[0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]+)?)$/", $value );
				break;

			case 'decimal_comma' :
				return preg_match( "/^(-?[0-9]{1,3}(?:\.?[0-9]{3})*(?:,[0-9]+)?)$/", $value );
				break;

			default :
				return preg_match( "/^(-?[0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]{2})?)$/", $value ) || preg_match( "/^(-?[0-9]{1,3}(?:\.?[0-9]{3})*(?:,[0-9]{2})?)$/", $value );

		}
	}

	public static function remove_currency_symbol( $value, $currency = null ) {
		if ( $currency == null ) {
			$code = GFCommon::get_currency();
			if ( empty( $code ) ) {
				$code = 'USD';
			}

			$currency = RGCurrency::get_currency( $code );
		}

		$value = str_replace( $currency['symbol_left'], '', $value );
		$value = str_replace( $currency['symbol_right'], '', $value );

		//some symbols can't be easily matched up, so this will catch any of them
		$value = preg_replace( '/[^,.\d]/', '', $value );

		return $value;
	}

	public static function is_currency_decimal_dot( $currency = null ) {

		if ( $currency == null ) {
			$code = GFCommon::get_currency();
			if ( empty( $code ) ) {
				$code = 'USD';
			}

			$currency = RGCurrency::get_currency( $code );
		}

		return rgar( $currency, 'decimal_separator' ) == '.';
	}

	public static function trim_all( $text ) {
		$text = trim( $text );
		do {
			$prev_text = $text;
			$text      = str_replace( '  ', ' ', $text );
		} while ( $text != $prev_text );

		return $text;
	}

	public static function format_number( $number, $number_format, $currency = '', $include_thousands_sep = false ) {
		if ( ! is_numeric( $number ) ) {
			return $number;
		}

		//replacing commas with dots and dots with commas
		if ( $number_format == 'currency' ) {
			if ( empty( $currency ) ) {
				$currency = GFCommon::get_currency();
			}

			$currency = new RGCurrency( $currency );
			$number   = $currency->to_money( $number );
		} else {
			if ( $number_format == 'decimal_comma' ) {
				$dec_point     = ',';
				$thousands_sep = $include_thousands_sep ? '.' : '';
			} else {
				$dec_point     = '.';
				$thousands_sep = $include_thousands_sep ? ',' : '';
			}

			$is_negative = $number < 0;

			$number    = explode( '.', $number );
			$number[0] = number_format( absint( $number[0] ), 0, '', $thousands_sep );
			$number    = implode( $dec_point, $number );

			if ( $is_negative ) {
				$number = '-' . $number;
			}
		}

		return $number;
	}

	public static function recursive_add_index_file( $dir ) {
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}

		if ( ! ( $dp = opendir( $dir ) ) ) {
			return;
		}

		// ignores all errors
		set_error_handler( '__return_false', E_ALL );

		//creates an empty index.html file
		if ( $f = fopen( $dir . '/index.html', 'w' ) ) {
			fclose( $f );
		}

		// restores error handler
		restore_error_handler();

		while ( ( false !== $file = readdir( $dp ) ) ) {
			if ( is_dir( "$dir/$file" ) && $file != '.' && $file != '..' ) {
				self::recursive_add_index_file( "$dir/$file" );
			}
		}

		closedir( $dp );
	}

	public static function add_htaccess_file() {

		$upload_root = GFFormsModel::get_upload_root();

		if ( ! is_dir( $upload_root ) ) {
			return;
		}

		if ( ! wp_is_writable( $upload_root ) ) {
			return;
		}

		$htaccess_file = $upload_root . '.htaccess';
		if ( file_exists( $htaccess_file ) ) {
			@unlink( $htaccess_file );
		}
		$txt   = '# Disable parsing of PHP for some server configurations. This file may be removed or modified on certain server configurations by using by the gform_upload_root_htaccess_rules filter. Please consult your system administrator before removing this file.
<Files *>
  SetHandler none
  SetHandler default-handler
  Options -ExecCGI
  RemoveHandler .cgi .php .php3 .php4 .php5 .phtml .pl .py .pyc .pyo
</Files>
<IfModule mod_php5.c>
  php_flag engine off
</IfModule>
<IfModule headers_module>
  Header set X-Robots-Tag "noindex"
</IfModule>';
		$rules = explode( "\n", $txt );

		/**
		 * A filter to allow the modification/disabling of parsing certain PHP within Gravity Forms
		 *
		 * @since 1.9.2
		 *
		 * @param mixed $rules The Rules of what to parse or not to parse
		 */
		$rules = apply_filters( 'gform_upload_root_htaccess_rules', $rules );
		if ( ! empty( $rules ) ) {
			if ( ! function_exists( 'insert_with_markers' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/misc.php' );
			}
			insert_with_markers( $htaccess_file, 'Gravity Forms', $rules );
		}
	}

	public static function clean_number( $number, $number_format = '' ) {
		if ( rgblank( $number ) ) {
			return $number;
		}

		$decimal_char = '';
		if ( $number_format == 'decimal_dot' ) {
			$decimal_char = '.';
		} else if ( $number_format == 'decimal_comma' ) {
			$decimal_char = ',';
		} else if ( $number_format == 'currency' ) {
			$currency     = RGCurrency::get_currency( GFCommon::get_currency() );
			$decimal_char = $currency['decimal_separator'];
		}


		$float_number = '';
		$clean_number = '';
		$is_negative  = false;

		//Removing all non-numeric characters
		$array = str_split( $number );
		foreach ( $array as $char ) {
			if ( ( $char >= '0' && $char <= '9' ) || $char == ',' || $char == '.' ) {
				$clean_number .= $char;
			} else if ( $char == '-' ) {
				$is_negative = true;
			}
		}

		//Removing thousand separators but keeping decimal point
		$array = str_split( $clean_number );
		for ( $i = 0, $count = sizeof( $array ); $i < $count; $i ++ ) {
			$char = $array[ $i ];
			if ( $char >= '0' && $char <= '9' ) {
				$float_number .= $char;
			} else if ( empty( $decimal_char ) && ( $char == '.' || $char == ',' ) && strlen( $clean_number ) - $i <= 3 ) {
				$float_number .= '.';
			} else if ( $decimal_char == $char ) {
				$float_number .= '.';
			}
		}

		if ( $is_negative ) {
			$float_number = '-' . $float_number;
		}

		return $float_number;

	}

	public static function json_encode( $value ) {
		return json_encode( $value );
	}

	public static function json_decode( $str, $is_assoc = true ) {
		return json_decode( $str, $is_assoc );
	}

	//Returns the url of the plugin's root folder
	public static function get_base_url() {
		return plugins_url( '', __FILE__ );
	}

	//Returns the physical path of the plugin's root folder
	public static function get_base_path() {
		return dirname( __FILE__ );
	}

	/**
	 * Returns an array of files/directories which match the supplied pattern.
	 *
	 * @since 2.4.15
	 *
	 * @param string $pattern   The pattern to be appended to the base path when performing the search.
	 * @param string $base_path The base path. Defaults to the plugin's root folder.
	 *
	 * @return array|false
	 */
	public static function glob( $pattern, $base_path = '' ) {
		if ( empty( $base_path ) ) {
			$base_path = self::get_base_path();
		}

		// Escape any brackets in the base path.
		$base_path = str_replace( array( '[', ']' ), array( '\[', '\]' ), $base_path );
		$base_path = str_replace( array( '\[', '\]' ), array( '[[]', '[]]' ), $base_path );

		return glob( $base_path . $pattern );
	}

	/**
	 * Requires and returns an array of files which match the supplied pattern.
	 *
	 * @since 2.4.15
	 *
	 * @param string $pattern   The pattern to be appended to the base path when performing the search.
	 * @param string $base_path The base path. Defaults to the plugin's root folder.
	 *
	 * @return array|false
	 */
	public static function glob_require_once( $pattern, $base_path = '' ) {
		$files = self::glob( $pattern, $base_path );

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				require_once $file;
			}
		}

		return $files;
	}

	public static function get_email_fields( $form ) {
		$fields = array();
		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'email' || $field->inputType == 'email' ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public static function truncate_middle( $text, $max_length ) {
		if ( strlen( $text ) <= $max_length ) {
			return $text;
		}

		$middle = intval( $max_length / 2 );

		return self::safe_substr( $text, 0, $middle ) . '...' . self::safe_substr( $text, strlen( $text ) - $middle, $middle );
	}

	public static function is_invalid_or_empty_email( $email ) {
		return empty( $email ) || ! self::is_valid_email( $email );
	}

	/**
	 * Validates URLs.
	 *
	 * @since   2.0.7.12 Filters added to allow for using custom validation.
	 * @access  public
	 *
	 * @used-by GFFormSettings::handle_confirmation_edit_submission()
	 * @used-by GF_Field_Post_Image::get_value_save_entry()
	 * @used-by GF_Field_Website::get_value_entry_detail()
	 * @used-by GF_Field_Website::validate()
	 *
	 * @param string $url The URL to validate.
	 *
	 * @return bool True if valid. False otherwise.
	 */
	public static function is_valid_url( $url ) {
		$url = trim( $url );

		/***
		 * Enables and disables RFC URL validation. Defaults to true.
		 *
		 * When RFC is enabled, URLs will be validated against the RFC standard.
		 * When disabled, a simple and generic URL validation will be performed.
		 *
		 * @since 2.0.7.12
		 * @see   https://docs.gravityforms.com/gform_rfc_url_validation/
		 *
		 * @param bool true If RFC validation should be enabled. Defaults to true. Set to false to disable RFC validation.
		 */
		$use_rfc = apply_filters( 'gform_rfc_url_validation', true );

		$is_valid = preg_match( "/^(https?:\/\/)/i", $url );

		if ( $use_rfc ) {
			$is_valid = $is_valid && filter_var( $url, FILTER_VALIDATE_URL ) !== false;
		}

		/***
		 * Filters the result of URL validations, allowing for custom validation to be performed.
		 *
		 * @since 2.0.7.12
		 * @see   https://docs.gravityforms.com/gform_is_valid_url/
		 *
		 * @param bool   $is_valid True if valid. False otherwise.
		 * @param string $url      The URL being validated.
		 */
		$is_valid = apply_filters( 'gform_is_valid_url', $is_valid, $url );

		return $is_valid;
	}

	public static function is_valid_email( $email ) {
		return is_email( $email );
	}

	public static function is_valid_email_list( $email_list ) {
		$emails = explode( ',', $email_list );
		if ( ! is_array( $emails ) ) {
			return false;
		}

		// Trim values.
		$emails = array_map( 'trim', $emails );

		foreach ( $emails as $email ) {
			if ( ! self::is_valid_email( $email ) ) {
				return false;
			}
		}

		return true;
	}

	public static function get_label( $field, $input_id = 0, $input_only = false, $allow_admin_label = true ) {
		return RGFormsModel::get_label( $field, $input_id, $input_only, $allow_admin_label );
	}

	public static function get_input( $field, $id ) {
		return RGFormsModel::get_input( $field, $id );
	}

	public static function insert_variables( $fields, $element_id, $hide_all_fields = false, $callback = '', $onchange = '', $max_label_size = 40, $exclude = null, $args = '', $class_name = '' ) {

		if ( $fields == null ) {
			$fields = array();
		}

		if ( $exclude == null ) {
			$exclude = array();
		}

		$exclude    = apply_filters( 'gform_merge_tag_list_exclude', $exclude, $element_id, $fields );
		$merge_tags = self::get_merge_tags( $fields, $element_id, $hide_all_fields, $exclude, $args );

		$onchange = empty( $onchange ) ? "InsertVariable('{$element_id}', '{$callback}');" : $onchange;
		$class    = trim( $class_name . ' gform_merge_tags' );

		?>

		<select id="<?php echo esc_attr( $element_id ); ?>_variable_select" onchange="<?php echo $onchange ?>" class="<?php echo esc_attr( $class ) ?>">
			<option value=''><?php esc_html_e( 'Insert Merge Tag', 'gravityforms' ); ?></option>

			<?php foreach ( $merge_tags as $group => $group_tags ) {

				$group_label = rgar( $group_tags, 'label' );
				$tags        = rgar( $group_tags, 'tags' );

				if ( empty( $group_tags['tags'] ) ) {
					continue;
				}

				if ( $group_label ) {
					?>
					<optgroup label="<?php echo $group_label; ?>">
				<?php } ?>

				<?php foreach ( $tags as $tag ) { ?>
					<option value="<?php echo $tag['tag']; ?>"><?php echo $tag['label']; ?></option>
					<?php
				}
				if ( $group_label ) {
					?>
					</optgroup>
					<?php
				}
			} ?>

		</select>

		<?php
	}

	/**
	 * This function is used by the gfMergeTags JS object to get the localized label for non-field merge tags as well as
	 * for backwards compatibility with the gform_custom_merge_tags hook. Lastly, this plugin is used by the soon-to-be
	 * deprecated insert_variables() function as the new gfMergeTags object has not yet been applied to the Post Content
	 * Template setting.
	 *
	 * @param GF_Field[] $fields
	 * @param            $element_id
	 * @param bool       $hide_all_fields
	 * @param array      $exclude_field_types
	 * @param string     $option
	 *
	 * @return array
	 */
	public static function get_merge_tags( $fields, $element_id, $hide_all_fields = false, $exclude_field_types = array(), $option = '' ) {

		if ( $fields == null ) {
			$fields = array();
		}

		if ( $exclude_field_types == null ) {
			$exclude_field_types = array();
		}

		$required_fields = $optional_fields = $pricing_fields = array();
		$ungrouped       = $required_group = $optional_group = $pricing_group = $other_group = array();

		if ( ! $hide_all_fields ) {
			$ungrouped[] = array(
				'tag'   => '{all_fields}',
				'label' => esc_html__( 'All Submitted Fields', 'gravityforms' )
			);
		}

		// group fields by required, optional, and pricing
		foreach ( $fields as $field ) {

			if ( $field->displayOnly ) {
				continue;
			}

			$input_type = RGFormsModel::get_input_type( $field );

			// skip field types that should be excluded
			if ( is_array( $exclude_field_types ) && in_array( $input_type, $exclude_field_types ) ) {
				continue;
			}

			if ( $field->isRequired ) {

				switch ( $input_type ) {

					case 'name' :

						if ( $field->nameFormat == 'extended' ) {

							$prefix                   = GFCommon::get_input( $field, $field->id . '.2' );
							$suffix                   = GFCommon::get_input( $field, $field->id . '.8' );
							$optional_field           = $field;
							$optional_field['inputs'] = array( $prefix, $suffix );

							//Add optional name fields to the optional list
							$optional_fields[] = $optional_field;

							//Remove optional name field from required list
							unset( $field->inputs[0] );
							unset( $field->inputs[3] );

						}

						$required_fields[] = $field;

						break;

					default:
						$required_fields[] = $field;
				}
			} else {
				$optional_fields[] = $field;
			}

			if ( self::is_pricing_field( $field->type ) ) {
				$pricing_fields[] = $field;
			}
		}

		if ( ! empty( $required_fields ) ) {
			foreach ( $required_fields as $field ) {
				$required_group = array_merge( $required_group, self::get_field_merge_tags( $field, $option ) );
			}
		}

		if ( ! empty( $optional_fields ) ) {
			foreach ( $optional_fields as $field ) {
				$optional_group = array_merge( $optional_group, self::get_field_merge_tags( $field, $option ) );
			}
		}

		if ( ! empty( $pricing_fields ) ) {

			if ( ! $hide_all_fields ) {
				$pricing_group[] = array(
					'tag'   => '{pricing_fields}',
					'label' => esc_html__( 'All Pricing Fields', 'gravityforms' )
				);
			}

			foreach ( $pricing_fields as $field ) {
				$pricing_group = array_merge( $pricing_group, self::get_field_merge_tags( $field, $option ) );
			}
		}

		$other_group[] = array( 'tag' => '{ip}', 'label' => esc_html__( 'User IP Address', 'gravityforms' ) );
		$other_group[] = array(
			'tag'   => '{date_mdy}',
			'label' => esc_html__( 'Date', 'gravityforms' ) . ' (mm/dd/yyyy)'
		);
		$other_group[] = array(
			'tag'   => '{date_dmy}',
			'label' => esc_html__( 'Date', 'gravityforms' ) . ' (dd/mm/yyyy)'
		);
		$other_group[] = array(
			'tag'   => '{embed_post:ID}',
			'label' => esc_html__( 'Embed Post/Page Id', 'gravityforms' )
		);
		$other_group[] = array(
			'tag'   => '{embed_post:post_title}',
			'label' => esc_html__( 'Embed Post/Page Title', 'gravityforms' )
		);
		$other_group[] = array( 'tag' => '{embed_url}', 'label' => esc_html__( 'Embed URL', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{entry_id}', 'label' => esc_html__( 'Entry Id', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{entry_url}', 'label' => esc_html__( 'Entry URL', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{form_id}', 'label' => esc_html__( 'Form Id', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{form_title}', 'label' => esc_html__( 'Form Title', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{user_agent}', 'label' => esc_html__( 'HTTP User Agent', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{referer}', 'label' => esc_html__( 'HTTP Referer URL', 'gravityforms' ) );

		if ( self::has_post_field( $fields ) ) {
			$other_group[] = array( 'tag' => '{post_id}', 'label' => esc_html__( 'Post Id', 'gravityforms' ) );
			$other_group[] = array(
				'tag'   => '{post_edit_url}',
				'label' => esc_html__( 'Post Edit URL', 'gravityforms' )
			);
		}

		$other_group[] = array(
			'tag'   => '{user:display_name}',
			'label' => esc_html__( 'User Display Name', 'gravityforms' )
		);
		$other_group[] = array( 'tag' => '{user:user_email}', 'label' => esc_html__( 'User Email', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{user:user_login}', 'label' => esc_html__( 'User Login', 'gravityforms' ) );

		$form_id = isset( $fields[0] ) ? $fields[0]->formId : rgget( 'id' );
		$form_id = absint( $form_id );

		$custom_group = apply_filters( 'gform_custom_merge_tags', array(), $form_id, $fields, $element_id );

		$merge_tags = array(
			'ungrouped' => array(
				'label' => false,
				'tags'  => $ungrouped,
			),
			'required'  => array(
				'label' => esc_html__( 'Required form fields', 'gravityforms' ),
				'tags'  => $required_group,
			),
			'optional'  => array(
				'label' => esc_html__( 'Optional form fields', 'gravityforms' ),
				'tags'  => $optional_group,
			),
			'pricing'   => array(
				'label' => esc_html__( 'Pricing form fields', 'gravityforms' ),
				'tags'  => $pricing_group,
			),
			'other'     => array(
				'label' => esc_html__( 'Other', 'gravityforms' ),
				'tags'  => $other_group,
			),
			'custom'    => array(
				'label' => esc_html__( 'Custom', 'gravityforms' ),
				'tags'  => $custom_group,
			)
		);

		return $merge_tags;
	}

	/**
	 * @param GF_Field $field
	 * @param string   $option
	 *
	 * @return string
	 */
	public static function get_field_merge_tags( $field, $option = '' ) {

		$merge_tags = array();
		$tag_args   = RGFormsModel::get_input_type( $field ) == 'list' ? ":{$option}" : ''; //args currently only supported by list field

		$inputs = $field->get_entry_inputs();

		if ( is_array( $inputs ) ) {

			if ( RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
				$value        = '{' . esc_html( GFCommon::get_label( $field, $field->id ) ) . ':' . $field->id . "{$tag_args}}";
				$merge_tags[] = array(
					'tag'   => $value,
					'label' => esc_html( GFCommon::get_label( $field, $field->id ) )
				);
			}

			foreach ( $field->inputs as $input ) {
				if ( RGFormsModel::get_input_type( $field ) == 'creditcard' ) {
					//only include the credit card type (field_id.4) and number (field_id.1)
					if ( $input['id'] == $field->id . '.1' || $input['id'] == $field->id . '.4' ) {
						$value        = '{' . esc_html( GFCommon::get_label( $field, $input['id'] ) ) . ':' . $input['id'] . "{$tag_args}}";
						$merge_tags[] = array(
							'tag'   => $value,
							'label' => esc_html( GFCommon::get_label( $field, $input['id'] ) )
						);
					}
				} else {
					$value        = '{' . esc_html( GFCommon::get_label( $field, $input['id'] ) ) . ':' . $input['id'] . "{$tag_args}}";
					$merge_tags[] = array(
						'tag'   => $value,
						'label' => esc_html( GFCommon::get_label( $field, $input['id'] ) )
					);
				}
			}
		} else {
			$value        = '{' . esc_html( GFCommon::get_label( $field ) ) . ':' . $field->id . "{$tag_args}}";
			$merge_tags[] = array(
				'tag'   => $value,
				'label' => esc_html( GFCommon::get_label( $field ) )
			);
		}

		return $merge_tags;
	}

	public static function insert_field_variable( $field, $max_label_size = 40, $args = '' ) {

		$tag_args = RGFormsModel::get_input_type( $field ) == 'list' ? ":{$args}" : ''; //args currently only supported by list field

		if ( is_array( $field->inputs ) ) {
			if ( RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
				?>
				<option value='<?php echo '{' . esc_html( GFCommon::get_label( $field, $field->id ) ) . ':' . $field->id . "{$tag_args}}" ?>'><?php echo esc_html( GFCommon::get_label( $field, $field->id ) ) ?></option>
				<?php
			}

			foreach ( $field->inputs as $input ) {
				?>
				<option value='<?php echo '{' . esc_html( GFCommon::get_label( $field, $input['id'] ) ) . ':' . $input['id'] . "{$tag_args}}" ?>'><?php echo esc_html( GFCommon::get_label( $field, $input['id'] ) ) ?></option>
				<?php
			}
		} else {
			?>
			<option value='<?php echo '{' . esc_html( GFCommon::get_label( $field ) ) . ':' . $field->id . "{$tag_args}}" ?>'><?php echo esc_html( GFCommon::get_label( $field ) ) ?></option>
			<?php
		}
	}

	public static function insert_post_content_variables( $fields, $element_id, $callback, $max_label_size = 25 ) {
		// TODO: replace with class-powered merge tags
		$insert_variables_onchange = sprintf( "InsertPostContentVariable('%s', '%s');", esc_js( $element_id ), esc_js( $callback ) );
		self::insert_variables( $fields, $element_id, true, '', $insert_variables_onchange, $max_label_size, null, '', 'gform_content_template_merge_tags' );
		?>
		&nbsp;&nbsp;
		<select id="<?php echo $element_id ?>_image_size_select" onchange="InsertPostImageVariable('<?php echo esc_js( $element_id ); ?>', '<?php echo esc_js( $element_id ); ?>'); SetCustomFieldTemplate();" style="display:none;">
			<option value=""><?php esc_html_e( 'Select image size', 'gravityforms' ) ?></option>
			<option value="thumbnail"><?php esc_html_e( 'Thumbnail', 'gravityforms' ) ?></option>
			<option value="thumbnail:left"><?php esc_html_e( 'Thumbnail - Left Aligned', 'gravityforms' ) ?></option>
			<option value="thumbnail:center"><?php esc_html_e( 'Thumbnail - Centered', 'gravityforms' ) ?></option>
			<option value="thumbnail:right"><?php esc_html_e( 'Thumbnail - Right Aligned', 'gravityforms' ) ?></option>

			<option value="medium"><?php esc_html_e( 'Medium', 'gravityforms' ) ?></option>
			<option value="medium:left"><?php esc_html_e( 'Medium - Left Aligned', 'gravityforms' ) ?></option>
			<option value="medium:center"><?php esc_html_e( 'Medium - Centered', 'gravityforms' ) ?></option>
			<option value="medium:right"><?php esc_html_e( 'Medium - Right Aligned', 'gravityforms' ) ?></option>

			<option value="large"><?php esc_html_e( 'Large', 'gravityforms' ) ?></option>
			<option value="large:left"><?php esc_html_e( 'Large - Left Aligned', 'gravityforms' ) ?></option>
			<option value="large:center"><?php esc_html_e( 'Large - Centered', 'gravityforms' ) ?></option>
			<option value="large:right"><?php esc_html_e( 'Large - Right Aligned', 'gravityforms' ) ?></option>

			<option value="full"><?php esc_html_e( 'Full Size', 'gravityforms' ) ?></option>
			<option value="full:left"><?php esc_html_e( 'Full Size - Left Aligned', 'gravityforms' ) ?></option>
			<option value="full:center"><?php esc_html_e( 'Full Size - Centered', 'gravityforms' ) ?></option>
			<option value="full:right"><?php esc_html_e( 'Full Size - Right Aligned', 'gravityforms' ) ?></option>
		</select>
		<?php
	}

	public static function insert_calculation_variables( $fields, $element_id, $onchange = '', $callback = '', $max_label_size = 40 ) {

		if ( $fields == null ) {
			$fields = array();
		}

		$onchange = empty( $onchange ) ? sprintf( "InsertVariable('%s', '%s');", esc_js( $element_id ), esc_js( $callback ) ) : $onchange;
		$class    = 'gform_merge_tags';
		?>

		<select id="<?php echo esc_attr( $element_id ); ?>_variable_select" onchange="<?php echo $onchange ?>" class="<?php echo esc_attr( $class ) ?>">
			<option value=''><?php esc_html_e( 'Insert Merge Tag', 'gravityforms' ); ?></option>
			<optgroup label="<?php esc_attr_e( 'Allowable form fields', 'gravityforms' ); ?>">

				<?php
				foreach ( $fields as $field ) {

					if ( ! self::is_valid_for_calcuation( $field ) ) {
						continue;
					}

					if ( RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
						foreach ( $field->inputs as $input ) {
							?>
							<option value='<?php echo esc_attr( '{' . esc_html( GFCommon::get_label( $field, $input['id'] ) ) . ':' . $input['id'] . '}' ); ?>'><?php echo esc_html( GFCommon::get_label( $field, $input['id'] ) ) ?></option>
							<?php
						}
					} else {
						self::insert_field_variable( $field, $max_label_size );
					}
				}
				?>

			</optgroup>

			<?php
			$form_id = isset( $fields[0] ) ? $fields[0]->formId : rgget( 'id' );
			$form_id = absint( $form_id );

			$custom_merge_tags = apply_filters( 'gform_custom_merge_tags', array(), $form_id, $fields, $element_id );

			if ( is_array( $custom_merge_tags ) && ! empty( $custom_merge_tags ) ) {
				?>

				<optgroup label="<?php esc_attr_e( 'Custom', 'gravityforms' ); ?>">

					<?php foreach ( $custom_merge_tags as $custom_merge_tag ) { ?>

						<option value='<?php echo esc_attr( rgar( $custom_merge_tag, 'tag' ) ); ?>'><?php echo esc_html( rgar( $custom_merge_tag, 'label' ) ); ?></option>

					<?php } ?>

				</optgroup>

			<?php } ?>

		</select>

		<?php
	}

	private static function get_post_image_variable( $media_id, $arg1, $arg2, $is_url = false ) {

		if ( $is_url ) {
			$image = wp_get_attachment_image_src( $media_id, $arg1 );
			if ( $image ) {
				list( $src, $width, $height ) = $image;
			}

			return $src;
		}

		switch ( $arg1 ) {
			case 'title' :
				$media = get_post( $media_id );

				return $media->post_title;
			case 'caption' :
				$media = get_post( $media_id );

				return $media->post_excerpt;
			case 'description' :
				$media = get_post( $media_id );

				return $media->post_content;

			default :

				$img = wp_get_attachment_image( $media_id, $arg1, false, array( 'class' => "size-{$arg1} align{$arg2} wp-image-{$media_id}" ) );

				return $img;
		}
	}

	public static function replace_variables_post_image( $text, $post_images, $lead ) {

		preg_match_all( '/{[^{]*?:(\d+)(:([^:]*?))?(:([^:]*?))?(:url)?}/mi', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$input_id = $match[1];

				// Ignore fields that are not post images.
				if ( ! isset( $post_images[ $input_id ] ) ) {
					continue;
				}

				// Reading alignment and 'url' parameters.
				// Format could be {image:5:medium:left:url} or {image:5:medium:url}.
				$size_meta = empty( $match[3] ) ? 'full' : $match[3];
				$align     = empty( $match[5] ) ? 'none' : $match[5];
				if ( $align == 'url' ) {
					$align  = 'none';
					$is_url = true;
				} else {
					$is_url = rgar( $match, 6 ) == ':url';
				}

				$media_id = $post_images[ $input_id ];
				$value    = is_wp_error( $media_id ) ? '' : self::get_post_image_variable( $media_id, $size_meta, $align, $is_url );

				$text = str_replace( $match[0], $value, $text );
			}
		}

		return $text;
	}

	public static function implode_non_blank( $separator, $array ) {

		if ( ! is_array( $array ) ) {
			return '';
		}

		$ary = array();
		foreach ( $array as $item ) {
			if ( ! rgblank( $item ) ) {
				$ary[] = $item;
			}
		}

		return implode( $separator, $ary );
	}

	public static function format_variable_value( $value, $url_encode, $esc_html, $format, $nl2br = true ) {
		if ( $esc_html ) {
			$value = esc_html( $value );
		}

		if ( $format == 'html' && $nl2br ) {
			$value = nl2br( $value );
		}

		if ( $url_encode ) {
			$value = urlencode( $value );
		}

		return $value;
	}

	public static function replace_variables( $text, $form, $lead, $url_encode = false, $esc_html = true, $nl2br = true, $format = 'html', $aux_data = array() ) {

		$data = array_merge( array( 'entry' => $lead ), $aux_data );

		/**
		 * Filter data that will be used to replace merge tags.
		 *
		 * @since 2.1.1.11 Added the Entry Object as the 4th parameter.
		 *
		 * @param $data  array  Array of key/value pairs, where key is used as merge tag and value is an array of data available to the merge tag.
		 * @param $text  string String of text which will be searched for merge tags.
		 * @param $form  array  Current form object.
		 * @param $lead  array  The current Entry Object.
		 *
		 * @see   https://docs.gravityforms.com/gform_merge_tag_data/
		 */
		$data = apply_filters( 'gform_merge_tag_data', $data, $text, $form, $lead );

		$lead = $data['entry'];

		$text = $format == 'html' && $nl2br ? nl2br( $text ) : $text;
		$text = apply_filters( 'gform_pre_replace_merge_tags', $text, $form, $lead, $url_encode, $esc_html, $nl2br, $format );

		if ( strpos( $text, '{' ) === false ) {
			return $text;
		}

		// Replacing conditional merge tag variables: [gravityforms action="conditional" merge_tag="{Other Services:4}" ....
		preg_match_all( '/merge_tag\s*=\s*["|\']({[^{]*?:(\d+(\.\d+)?)(:(.*?))?})["|\']/mi', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$input_id = $match[2];

				$text = self::replace_field_variable( $text, $form, $lead, $url_encode, $esc_html, $nl2br, $format, $input_id, $match, true );
			}
		}

		// Process dynamic merge tags based on auxiliary data.
		$aux_tags = array_keys( $data );
		$pattern  = sprintf( '/{(%s):(.+?)}/', implode( '|', $aux_tags ) );

		preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {

			list( $search, $tag, $prop ) = $match;

			if ( is_callable( $data[ $tag ] ) ) {
				$data[ $tag ] = call_user_func( $data[ $tag ], $lead, $form );
			}

			$object  = $data[ $tag ];
			$replace = rgars( $object, $prop );

			$text = str_replace( $search, $replace, $text );

		}

		// Replacing field variables: {FIELD_LABEL:FIELD_ID} {My Field:2}.
		preg_match_all( '/{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/mi', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$input_id = $match[1];

				$text = self::replace_field_variable( $text, $form, $lead, $url_encode, $esc_html, $nl2br, $format, $input_id, $match );
			}
		}

		$matches = array();
		preg_match_all( "/{all_fields(:(.*?))?}/", $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$options         = explode( ',', rgar( $match, 2 ) );
			$use_value       = in_array( 'value', $options );
			$display_empty   = in_array( 'empty', $options );
			$use_admin_label = in_array( 'admin', $options );

			//all submitted fields using text
			if ( strpos( $text, $match[0] ) !== false ) {
				$text = str_replace( $match[0], self::get_submitted_fields( $form, $lead, $display_empty, ! $use_value, $format, $use_admin_label, 'all_fields', rgar( $match, 2 ) ), $text );
			}
		}

		// All submitted fields including empty fields.
		if ( strpos( $text, '{all_fields_display_empty}' ) !== false ) {
			$text = str_replace( '{all_fields_display_empty}', self::get_submitted_fields( $form, $lead, true, true, $format, false, 'all_fields_display_empty' ), $text );
		}

		// Pricing fields.
		$pricing_matches = array();
		preg_match_all( "/{pricing_fields(:(.*?))?}/", $text, $pricing_matches, PREG_SET_ORDER );
		foreach ( $pricing_matches as $match ) {
			$options         = explode( ',', rgar( $match, 2 ) );
			$use_value       = in_array( 'value', $options );
			$use_admin_label = in_array( 'admin', $options );

			// All submitted pricing fields using text.
			if ( strpos( $text, $match[0] ) !== false ) {
				$pricing_fields = self::get_submitted_pricing_fields( $form, $lead, $format, ! $use_value, $use_admin_label );

				if ( $format == 'html' ) {
					$text = str_replace(
						$match[0],
						'<table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#EAEAEA">
							<tr><td>
								<table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">' . $pricing_fields . '</table>
							</td></tr>
						</table>',
						$text
					);
				} else {
					$text = str_replace( $match[0], $pricing_fields, $text );
				}
			}
		}

		// Replacing global variables.
		// Form title.
		$text = str_replace( '{form_title}', $url_encode ? urlencode( rgar( $form, 'title' ) ) : rgar( $form, 'title' ), $text );

		// Form ID.
		$text = str_replace( '{form_id}', $url_encode ? urlencode( rgar( $form, 'id' ) ) : rgar( $form, 'id' ), $text );

		// Entry ID.
		$text = str_replace( '{entry_id}', $url_encode ? urlencode( rgar( $lead, 'id' ) ) : rgar( $lead, 'id' ), $text );

		// Entry URL.
		$entry_url = get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=gf_entries&view=entry&id=' . rgar( $form, 'id' ) . '&lid=' . rgar( $lead, 'id' );

		/**
		 * Filter the entry URL
		 *
		 * Allows for the filtering of the entry_url placeholder to handle situation in which the wpurl might not agree with the admin_url.
		 *
		 * @since 2.2.3.14
		 *
		 * @param string $entry_url The Entry URL to filter.
		 * @param array  $form      The current Form object.
		 * @param array  $lead      The current Entry object.
		 */
		$entry_url      = esc_url( apply_filters( 'gform_entry_detail_url', $entry_url, $form, $lead ) );
		$text           = str_replace( '{entry_url}', $url_encode ? urlencode( $entry_url ) : $entry_url, $text );

		// Post ID.
		$text = str_replace( '{post_id}', $url_encode ? urlencode( rgar( $lead, 'post_id' ) ) : rgar( $lead, 'post_id' ), $text );

		// Admin email.
		$wp_email = get_bloginfo( 'admin_email' );
		$text     = str_replace( '{admin_email}', $url_encode ? urlencode( $wp_email ) : $wp_email, $text );

		// Admin URL.
		$text = str_replace( '{admin_url}', $url_encode ? urlencode( admin_url() ) : admin_url(), $text );

		// Logout URL.
		$text = str_replace( '{logout_url}', $url_encode ? urlencode( wp_logout_url() ) : wp_logout_url(), $text );

		// Post edit URL.
		$post_url = get_bloginfo( 'wpurl' ) . '/wp-admin/post.php?action=edit&post=' . rgar( $lead, 'post_id' );
		$text     = str_replace( '{post_edit_url}', $url_encode ? urlencode( $post_url ) : $post_url, $text );

		$text = self::replace_variables_prepopulate( $text, $url_encode, $lead, $esc_html, $form, $nl2br, $format );

		// TODO: Deprecate the 'gform_replace_merge_tags' and replace it with a call to the 'gform_merge_tag_filter'
		//$text = apply_filters('gform_merge_tag_filter', $text, false, false, false );

		$text = self::decode_merge_tag( $text );

		return $text;
	}

	public static function encode_merge_tag( $text ) {
		return str_replace( '{', '&#x7b;', $text );
	}

	public static function decode_merge_tag( $text ) {
		return str_replace( '&#x7b;', '{', $text );
	}

	public static function format_post_category( $value, $use_id ) {

		list( $item_value, $item_id ) = rgexplode( ':', $value, 2 );

		if ( $use_id && ! empty( $item_id ) ) {
			$item_value = $item_id;
		}

		return $item_value;
	}

	public static function get_embed_post() {
		global $embed_post, $post, $wp_query;

		if ( $embed_post ) {
			return $embed_post;
		}

		if ( ! rgempty( 'gform_embed_post' ) ) {
			$post_id    = absint( rgpost( 'gform_embed_post' ) );
			$embed_post = get_post( $post_id );
		} else if ( $wp_query->is_in_loop ) {
			$embed_post = $post;
		} else {
			$embed_post = array();
		}
	}

	public static function get_ul_classes( $form ) {

		$description_class = rgar( $form, 'descriptionPlacement' ) == 'above' ? 'description_above' : 'description_below';
		$sublabel_class    = rgar( $form, 'subLabelPlacement' ) == 'above' ? 'form_sublabel_above' : 'form_sublabel_below';
		$label_class       = rgempty( 'labelPlacement', $form ) ? 'top_label' : rgar( $form, 'labelPlacement' );

		$css_class = preg_replace( '/\s+/', ' ', "gform_fields {$label_class} {$sublabel_class} {$description_class}" ); //removing extra spaces

		return $css_class;
	}

	public static function replace_variables_prepopulate( $text, $url_encode = false, $entry = false, $esc_html = false, $form = false, $nl2br = false, $format = 'html' ) {

		if ( strpos( $text, '{' ) !== false ) {

			//embed url
			$current_page_url = empty( $entry ) ? RGFormsModel::get_current_page_url() : rgar( $entry, 'source_url' );
			if ( $esc_html ) {
				$current_page_url = esc_html( $current_page_url );
			}
			if ( $url_encode ) {
				$current_page_url = urlencode( $current_page_url );
			}
			$text = str_replace( '{embed_url}', $current_page_url, $text );

			$local_timestamp = self::get_local_timestamp( time() );

			//date (mm/dd/yyyy)
			$local_date_mdy = date_i18n( 'm/d/Y', $local_timestamp, true );
			$text           = str_replace( '{date_mdy}', $url_encode ? urlencode( $local_date_mdy ) : $local_date_mdy, $text );

			//date (dd/mm/yyyy)
			$local_date_dmy = date_i18n( 'd/m/Y', $local_timestamp, true );
			$text           = str_replace( '{date_dmy}', $url_encode ? urlencode( $local_date_dmy ) : $local_date_dmy, $text );

			// ip
			$request_ip = rgars( $form, 'personalData/preventIP' ) ? '' : GFFormsModel::get_ip();
			$ip = isset( $entry['ip'] ) ? $entry['ip'] : $request_ip;
			if ( $esc_html ) {
				$ip = esc_html( $ip );
			}
			$text = str_replace( '{ip}', $url_encode ? urlencode( $ip ) : $ip, $text );

			//user agent
			$user_agent = isset( $entry['user_agent'] ) ? $entry['user_agent'] : sanitize_text_field( rgar( $_SERVER, 'HTTP_USER_AGENT' ) );
			$text       = str_replace( '{user_agent}', self::format_variable_value( $user_agent, $url_encode, $esc_html, $format, $nl2br ), $text );

			//referrer
			$referer = RGForms::get( 'HTTP_REFERER', $_SERVER );
			if ( $esc_html ) {
				$referer = esc_html( $referer );
			}
			if ( $url_encode ) {
				$referer = urlencode( $referer );
			}
			$text = str_replace( '{referer}', $referer, $text );

			//embed post and custom fields
			preg_match_all( "/\{embed_post:(.*?)\}/", $text, $ep_matches, PREG_SET_ORDER );
			preg_match_all( "/\{custom_field:(.*?)\}/", $text, $cf_matches, PREG_SET_ORDER );

			if ( ! empty( $ep_matches ) || ! empty( $cf_matches ) ) {
				global $post;
				$is_singular = is_singular();
				$post_array  = self::object_to_array( $post );

				//embed_post
				foreach ( $ep_matches as $match ) {
					$full_tag = $match[0];
					$property = $match[1];
					$value    = $is_singular ? $post_array[ $property ] : '';
					$text     = str_replace( $full_tag, $url_encode ? urlencode( $value ) : $value, $text );
				}

				//custom_field
				foreach ( $cf_matches as $match ) {
					$full_tag           = $match[0];
					$custom_field_name  = $match[1];
					$custom_field_value = $is_singular && ! empty( $post_array['ID'] ) ? get_post_meta( $post_array['ID'], $custom_field_name, true ) : '';
					$text               = str_replace( $full_tag, $url_encode ? urlencode( $custom_field_value ) : $custom_field_value, $text );
				}
			}

			//logged in user info
			global $current_user;

			preg_match_all( "/\{user:(.*?)\}/", $text, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				$full_tag = $match[0];
				$property = $match[1];

				// Prevent leaking hashed passwords.
				$value = $property == 'user_pass' ? '' : $current_user->get( $property );
				$value = $url_encode ? urlencode( $value ) : $value;

				$text = str_replace( $full_tag, $value, $text );
			}

		}

		/**
		 * Allow the text to be filtered so custom merge tags can be replaced.
		 *
		 * @param string      $text       The text in which merge tags are being processed.
		 * @param false|array $form       The Form object if available or false.
		 * @param false|array $entry      The Entry object if available or false.
		 * @param bool        $url_encode Indicates if the urlencode function should be applied.
		 * @param bool        $esc_html   Indicates if the esc_html function should be applied.
		 * @param bool        $nl2br      Indicates if the nl2br function should be applied.
		 * @param string      $format     The format requested for the location the merge is being used. Possible values: html, text or url.
		 */
		$text = apply_filters( 'gform_replace_merge_tags', $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format );

		return $text;
	}

	public static function object_to_array( $object ) {
		$array = array();
		if ( ! empty( $object ) ) {
			foreach ( $object as $member => $data ) {
				$array[ $member ] = $data;
			}
		}

		return $array;
	}

	public static function is_empty_array( $val ) {
		if ( ! is_array( $val ) ) {
			$val = array( $val );
		}

		$ary = array_values( $val );
		foreach ( $ary as $item ) {
			if ( ! rgblank( $item ) ) {
				return false;
			}
		}

		return true;
	}

	public static function get_submitted_fields( $form, $lead, $display_empty = false, $use_text = false, $format = 'html', $use_admin_label = false, $merge_tag = '', $options = '' ) {

		$field_data = '';
		if ( $format == 'html' ) {
			$field_data = '<table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#EAEAEA"><tr><td>
                            <table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">
                            ';
		}

		$options_array           = explode( ',', $options );
		$no_admin                = in_array( 'noadmin', $options_array );
		$no_hidden               = in_array( 'nohidden', $options_array );
		$display_product_summary = false;

		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */

			$field_value = '';

			$field->set_context_property( 'use_admin_label', $use_admin_label );
			$field_label = $format == 'text' ? sanitize_text_field( self::get_label( $field ) ) : esc_html( self::get_label( $field ) );

			switch ( $field->type ) {
				case 'captcha' :
				case 'password' :
					break;

				case 'section' :

					if ( GFFormsModel::is_field_hidden( $form, $field, array(), $lead ) ) {
						break;
					}

					if ( ( ! GFCommon::is_section_empty( $field, $form, $lead ) || $display_empty ) && ! $field->is_administrative() ) {

						switch ( $format ) {
							case 'text' :
								$field_value = "--------------------------------\n{$field_label}\n\n";
								break;

							default:
								$field_value = sprintf(
									'<tr>
                                        	<td colspan="2" style="font-size:14px; font-weight:bold; background-color:#EEE; border-bottom:1px solid #DFDFDF; padding:7px 7px">%s</td>
	                                   </tr>
	                                   ', $field_label
								);
								break;
						}
					}

					$field_value = apply_filters( 'gform_merge_tag_filter', $field_value, $merge_tag, $options, $field, $field_label, $format );

					$field_data .= $field_value;

					break;

				default :

					if ( self::is_product_field( $field->type ) ) {

						// ignore product fields as they will be grouped together at the end of the grid
						$display_product_summary = apply_filters( 'gform_display_product_summary', true, $field, $form, $lead );
						if ( $display_product_summary ) {
							break;
						}
					} else if ( GFFormsModel::is_field_hidden( $form, $field, array(), $lead ) ) {
						// ignore fields hidden by conditional logic
						break;
					}

					$field->set_modifiers( $options_array );
					$raw_field_value = RGFormsModel::get_lead_field_value( $lead, $field );
					$field_value     = GFCommon::get_lead_field_display( $field, $raw_field_value, rgar( $lead, 'currency' ), $use_text, $format, 'email' );

					$display_field = true;
					//depending on parameters, don't display adminOnly or hidden fields
					if ( $no_admin && $field->is_administrative() ) {
						$display_field = false;
					} else if ( $no_hidden && RGFormsModel::get_input_type( $field ) == 'hidden' ) {
						$display_field = false;
					}

					//if field is not supposed to be displayed, pass false to filter. otherwise, pass field's value
					if ( ! $display_field ) {
						$field_value = false;
					}

					$field_value = self::encode_shortcodes( $field_value );

					$field_value = apply_filters( 'gform_merge_tag_filter', $field_value, $merge_tag, $options, $field, $raw_field_value, $format );

					// Clear merge tag modifiers from the field object.
					$field->set_modifiers( array() );

					if ( $field_value === false ) {
						break;
					}

					if ( ! rgblank( $field_value ) || strlen( $field_value ) > 0 || $display_empty ) {
						switch ( $format ) {
							case 'text' :
								$field_data .= "{$field_label}: {$field_value}\n\n";
								break;

							default:

								$field_data .= sprintf(
									'<tr bgcolor="%3$s">
		                                    <td colspan="2">
		                                        <font style="font-family: sans-serif; font-size:12px;"><strong>%1$s</strong></font>
		                                    </td>
		                               </tr>
		                               <tr bgcolor="%4$s">
		                                    <td width="20">&nbsp;</td>
		                                    <td>
		                                        <font style="font-family: sans-serif; font-size:12px;">%2$s</font>
		                                    </td>
		                               </tr>
		                               ', $field_label, empty( $field_value ) && strlen( $field_value ) == 0 ? '&nbsp;' : $field_value, esc_attr( apply_filters( 'gform_email_background_color_label', '#EAF2FA', $field, $lead ) ), esc_attr( apply_filters( 'gform_email_background_color_data', '#FFFFFF', $field, $lead ) )
								);
								break;
						}
					}
			}
		}

		if ( $display_product_summary ) {
			$field_data .= self::get_submitted_pricing_fields( $form, $lead, $format, $use_text, $use_admin_label );
		}

		if ( $format == 'html' ) {
			$field_data .= '</table>
                        </td>
                   </tr>
               </table>';
		}

		return $field_data;
	}

	public static function get_submitted_pricing_fields( $form, $lead, $format, $use_text = true, $use_admin_label = false ) {
		$form_id     = $form['id'];
		$order_label = gf_apply_filters( array(
			'gform_order_label',
			$form_id
		), esc_html__( 'Order', 'gravityforms' ), $form['id'] );
		$products    = GFCommon::get_product_fields( $form, $lead, $use_text, $use_admin_label );
		$total       = 0;
		$field_data  = '';

		switch ( $format ) {
			case 'text' :
				if ( ! empty( $products['products'] ) ) {
					$field_data = "--------------------------------\n" . $order_label . "\n\n";
					foreach ( $products['products'] as $product ) {
						$product_name = $product['quantity'] . ' ' . $product['name'];
						$price        = self::to_number( $product['price'], $lead['currency'] );
						if ( ! empty( $product['options'] ) ) {
							$product_name .= ' (';
							$options = array();
							foreach ( $product['options'] as $option ) {
								$price += self::to_number( $option['price'], $lead['currency'] );
								$options[] = $option['option_name'];
							}
							$product_name .= implode( ', ', $options ) . ')';
						}
						$quantity = self::to_number( $product['quantity'], $lead['currency'] );
						$subtotal = $quantity * $price;
						$total += $subtotal;

						$field_data .= "{$product_name}: " . self::to_money( $subtotal, $lead['currency'] ) . "\n\n";
					}
					$total += floatval( $products['shipping']['price'] );

					if ( ! empty( $products['shipping']['name'] ) ) {
						$field_data .= $products['shipping']['name'] . ': ' . self::to_money( $products['shipping']['price'], $lead['currency'] ) . "\n\n";
					}

					$field_data .= esc_html__( 'Total', 'gravityforms' ) . ': ' . self::to_money( $total, $lead['currency'] ) . "\n\n";
				}
				break;


			default :
				if ( ! empty( $products['products'] ) ) {

					/**
					 * Filters the default product label.
					 *
					 * @var string Product  The product label string.  Defaults to 'Product'.
					 * @var int $form_id The ID of the form being processed.
					 */
					$gform_product = gf_apply_filters( array(
						'gform_product',
						$form_id
					), esc_html__( 'Product', 'gravityforms' ), $form_id );
					/**
					 * Filters the default quantity label.
					 *
					 * @var string Qty      The quantity label string.  Defaults to 'Qty'.
					 * @var int $form_id The ID of the form being processed.
					 */
					$gform_product_qty = gf_apply_filters( array(
						'gform_product_qty',
						$form_id
					), esc_html__( 'Qty', 'gravityforms' ), $form_id );
					/**
					 * Filters the default unit price label.
					 *
					 * @var string Unit Price The unit price label string.  Defaults to 'Unit Price'.
					 * @var int $form_id The ID of the form being processed.
					 */
					$gform_product_unitprice = gf_apply_filters( array(
						'gform_product_unitprice',
						$form_id
					), esc_html__( 'Unit Price', 'gravityforms' ), $form_id );
					/**
					 * Filters the default product price label.
					 *
					 * @var string Unit Price The product price label string.  Defaults to 'Price'.
					 * @var int $form_id The ID of the form being processed.
					 */
					$gform_product_price = gf_apply_filters( array(
						'gform_product_price',
						$form_id
					), esc_html__( 'Price', 'gravityforms' ), $form_id );

					$field_data = '<tr bgcolor="#EAF2FA">
                            <td colspan="2">
                                <font style="font-family: sans-serif; font-size:12px;"><strong>' . $order_label . '</strong></font>
                            </td>
                       </tr>
                       <tr bgcolor="#FFFFFF">
                            <td width="20">&nbsp;</td>
                            <td>
                                <table cellspacing="0" width="97%" style="border-left:1px solid #DFDFDF; border-top:1px solid #DFDFDF">
                                <thead>
                                    <tr>
	                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-family: sans-serif; font-size:12px; text-align:left">' . $gform_product . '</th>
	                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:50px; font-family: sans-serif; font-size:12px; text-align:center">' . $gform_product_qty . '</th>
	                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:12px; text-align:left">' . $gform_product_unitprice . '</th>
	                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:12px; text-align:left">' . $gform_product_price . '</th>
                                    </tr>
                                </thead>
                                <tbody>';


					foreach ( $products['products'] as $product ) {

						$field_data .= '<tr>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-family: sans-serif; font-size:11px;" >
                                                            <strong style="color:#BF461E; font-size:12px; margin-bottom:5px">' . $product['name'] . '</strong>
                                                            <ul style="margin:0">';

						$price = self::to_number( $product['price'], $lead['currency'] );
						if ( is_array( rgar( $product, 'options' ) ) ) {
							foreach ( $product['options'] as $option ) {
								$price += self::to_number( $option['price'], $lead['currency'] );
								$field_data .= '<li style="padding:4px 0 4px 0">' . $option['option_label'] . '</li>';
							}
						}
						$quantity = self::to_number( $product['quantity'], $lead['currency'] );
						$subtotal = $quantity * $price;
						$total += $subtotal;

						$field_data .= '</ul>
                                                        </td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:center; width:50px; font-family: sans-serif; font-size:11px;" >' . $product['quantity'] . '</td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:11px;" >' . self::to_money( $price, $lead['currency'] ) . '</td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:11px;" >' . self::to_money( $subtotal, $lead['currency'] ) . '</td>
                                                    </tr>';
					}
					$total += floatval( $products['shipping']['price'] );
					$field_data .= '</tbody>
                                <tfoot>';

					if ( ! empty( $products['shipping']['name'] ) ) {
						$field_data .= '
                                    <tr>
                                        <td colspan="2" rowspan="2" style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-size:11px;">&nbsp;</td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:right; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . $products['shipping']['name'] . '</strong></td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . self::to_money( $products['shipping']['price'], $lead['currency'] ) . '</strong></td>
                                    </tr>
                                    ';
					}

					$field_data .= '
                                    <tr>';

					if ( empty( $products['shipping']['name'] ) ) {
						$field_data .= '
                                        <td colspan="2" style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-size:11px;">&nbsp;</td>';
					}

					$field_data .= '
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:right; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . esc_html__( 'Total:', 'gravityforms' ) . '</strong></td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . self::to_money( $total, $lead['currency'] ) . '</strong></td>
                                    </tr>
                                </tfoot>
                               </table>
                            </td>
                        </tr>';
				}
				break;
		}

		/**
		 * Filter the markup of the order summary which appears on the Entry Detail, the {all_fields} merge tag and the {pricing_fields} merge tag.
         *
         * @since 2.1.2.5
         * @see   https://docs.gravityforms.com/gform_order_summary/
         *
         * @var string $field_data      The order summary markup.
         * @var array  $form            Current form object.
         * @var array  $lead            Current entry object.
         * @var array  $products        Current order summary object.
         * @var string $format          Format that should be used to display the summary ('html' or 'text').
		 */
		$field_data = gf_apply_filters( array( 'gform_order_summary', $form['id'] ), $field_data, $form, $lead, $products, $format );

		return $field_data;
	}

	public static function send_user_notification( $form, $lead, $override_options = false ) {
		_deprecated_function( 'send_user_notification', '1.7', 'send_notification' );

		$notification = self::prepare_user_notification( $form, $lead, $override_options );
		self::send_email( $notification['from'], $notification['to'], $notification['bcc'], $notification['reply_to'], $notification['subject'], $notification['message'], $notification['from_name'], $notification['message_format'], $notification['attachments'], $lead );
	}

	public static function send_admin_notification( $form, $lead, $override_options = false ) {
		_deprecated_function( 'send_admin_notification', '1.7', 'send_notification' );

		$notification = self::prepare_admin_notification( $form, $lead, $override_options );
		self::send_email( $notification['from'], $notification['to'], $notification['bcc'], $notification['replyTo'], $notification['subject'], $notification['message'], $notification['from_name'], $notification['message_format'], $notification['attachments'], $lead );
	}

	private static function prepare_user_notification( $form, $lead, $override_options = false ) {
		$form_id = $form['id'];

		if ( ! isset( $form['autoResponder'] ) ) {
			return;
		}

		//handling autoresponder email
		$to_field = isset( $form['autoResponder']['toField'] ) ? rgget( $form['autoResponder']['toField'], $lead ) : '';
		$to       = gf_apply_filters( array( 'gform_autoresponder_email', $form_id ), $to_field, $form );
		$subject  = GFCommon::replace_variables( rgget( 'subject', $form['autoResponder'] ), $form, $lead, false, false );

		$message_format = gf_apply_filters( array(
			'gform_notification_format',
			$form_id
		), 'html', 'user', $form, $lead );
		$message        = GFCommon::replace_variables( rgget( 'message', $form['autoResponder'] ), $form, $lead, false, false, ! rgget( 'disableAutoformat', $form['autoResponder'] ), $message_format );

		/**
		 * Allows the disabling of the notification message defined in the shortcode.
		 *
		 * @since 1.9.2
		 *
		 * @param       bool  true  If the notification message shortcode should be used.
		 * @param array $form The Form Object.
		 * @param array $lead The Entry Object.
		 */
		if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
			$message = do_shortcode( $message );
		}

		//Running trough variable replacement
		$to        = GFCommon::replace_variables( $to, $form, $lead, false, false );
		$from      = GFCommon::replace_variables( rgget( 'from', $form['autoResponder'] ), $form, $lead, false, false );
		$bcc       = GFCommon::replace_variables( rgget( 'bcc', $form['autoResponder'] ), $form, $lead, false, false );
		$reply_to  = GFCommon::replace_variables( rgget( 'replyTo', $form['autoResponder'] ), $form, $lead, false, false );
		$from_name = GFCommon::replace_variables( rgget( 'fromName', $form['autoResponder'] ), $form, $lead, false, false );

		// override default values if override options provided
		if ( $override_options && is_array( $override_options ) ) {
			foreach ( $override_options as $override_key => $override_value ) {
				${$override_key} = $override_value;
			}
		}

		$attachments = gf_apply_filters( array(
			'gform_user_notification_attachments',
			$form_id
		), array(), $lead, $form );

		//Disabling autoformat to prevent double autoformatting of messages
		$disableAutoformat = '1';

		return compact( 'to', 'from', 'bcc', 'reply_to', 'subject', 'message', 'from_name', 'message_format', 'attachments', 'disableAutoformat' );
	}

	private static function prepare_admin_notification( $form, $lead, $override_options = false ) {
		$form_id = $form['id'];

		//handling admin notification email
		$subject = GFCommon::replace_variables( rgget( 'subject', $form['notification'] ), $form, $lead, false, false );

		$message_format = gf_apply_filters( array(
			'gform_notification_format',
			$form_id
		), 'html', 'admin', $form, $lead );
		$message        = GFCommon::replace_variables( rgget( 'message', $form['notification'] ), $form, $lead, false, false, ! rgget( 'disableAutoformat', $form['notification'] ), $message_format );

		if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
			$message = do_shortcode( $message );
		}

		$version_info = self::get_version_info();
		$is_expired   = ! rgempty( 'expiration_time', $version_info ) && $version_info['expiration_time'] < time();
		if ( ! rgar( $version_info, 'is_valid_key' ) && $is_expired ) {
			$message .= "<br/><br/>Your Gravity Forms License Key has expired. In order to continue receiving support and software updates you must renew your license key. You can do so by following the renewal instructions on the Gravity Forms Settings page in your WordPress Dashboard or by <a href='http://www.gravityhelp.com/renew-license/?key=" . self::get_key() . "'>clicking here</a>.";
		}

		$from = rgempty( 'fromField', $form['notification'] ) ? rgget( 'from', $form['notification'] ) : rgget( $form['notification']['fromField'], $lead );

		if ( rgempty( 'fromNameField', $form['notification'] ) ) {
			$from_name = rgget( 'fromName', $form['notification'] );
		} else {
			$field     = RGFormsModel::get_field( $form, rgget( 'fromNameField', $form['notification'] ) );
			$value     = RGFormsModel::get_lead_field_value( $lead, $field );
			$from_name = GFCommon::get_lead_field_display( $field, $value );
		}

		$replyTo = rgempty( 'replyToField', $form['notification'] ) ? rgget( 'replyTo', $form['notification'] ) : rgget( $form['notification']['replyToField'], $lead );

		if ( rgempty( 'routing', $form['notification'] ) ) {
			$email_to = rgempty( 'toField', $form['notification'] ) ? rgget( 'to', $form['notification'] ) : rgget( 'toField', $form['notification'] );
		} else {
			$email_to = array();
			foreach ( $form['notification']['routing'] as $routing ) {

				$source_field   = RGFormsModel::get_field( $form, $routing['fieldId'] );
				$field_value    = RGFormsModel::get_lead_field_value( $lead, $source_field );
				$is_value_match = RGFormsModel::is_value_match( $field_value, $routing['value'], $routing['operator'], $source_field, $routing, $form ) && ! RGFormsModel::is_field_hidden( $form, $source_field, array(), $lead );

				if ( $is_value_match ) {
					$email_to[] = $routing['email'];
				}
			}

			$email_to = join( ',', $email_to );
		}

		//Running through variable replacement
		$email_to  = GFCommon::replace_variables( $email_to, $form, $lead, false, false );
		$from      = GFCommon::replace_variables( $from, $form, $lead, false, false );
		$bcc       = GFCommon::replace_variables( rgget( 'bcc', $form['notification'] ), $form, $lead, false, false );
		$reply_to  = GFCommon::replace_variables( $replyTo, $form, $lead, false, false );
		$from_name = GFCommon::replace_variables( $from_name, $form, $lead, false, false );

		//Filters the admin notification email to address. Allows users to change email address before notification is sent
		$to = gf_apply_filters( array( 'gform_notification_email', $form_id ), $email_to, $lead );

		// override default values if override options provided
		if ( $override_options && is_array( $override_options ) ) {
			foreach ( $override_options as $override_key => $override_value ) {
				${$override_key} = $override_value;
			}
		}

		$attachments = gf_apply_filters( array(
			'gform_admin_notification_attachments',
			$form_id
		), array(), $lead, $form );

		//Disabling autoformat to prevent double autoformatting of messages
		$disableAutoformat = '1';

		return compact( 'to', 'from', 'bcc', 'replyTo', 'subject', 'message', 'from_name', 'message_format', 'attachments', 'disableAutoformat' );

	}

	public static function send_notification( $notification, $form, $lead, $data = array() ) {

		GFCommon::log_debug( "GFCommon::send_notification(): Starting to process notification (#{$notification['id']} - {$notification['name']})." );

		$notification = gf_apply_filters( array( 'gform_notification', $form['id'] ), $notification, $form, $lead );

		$to_field = '';
		if ( rgar( $notification, 'toType' ) == 'field' ) {
			$to_field = rgar( $notification, 'toField' );
			if ( rgempty( 'toField', $notification ) ) {
				$to_field = rgar( $notification, 'to' );
			}
		}

		$email_to = rgar( $notification, 'to' );
		//do routing logic if "to" field doesn't have a value (to support legacy notifications that will run routing prior to this method)
		if ( empty( $email_to ) && rgar( $notification, 'toType' ) == 'routing' && ! empty( $notification['routing'] ) ) {
			$email_to = array();
			foreach ( $notification['routing'] as $routing ) {
				if ( rgempty( 'email', $routing ) ) {
					continue;
				}

				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - rule => ' . print_r( $routing, 1 ) );

				$source_field   = RGFormsModel::get_field( $form, rgar( $routing, 'fieldId' ) );
				$field_value    = RGFormsModel::get_lead_field_value( $lead, $source_field );
				$is_value_match = RGFormsModel::is_value_match( $field_value, rgar( $routing, 'value', '' ), rgar( $routing, 'operator', 'is' ), $source_field, $routing, $form ) && ! RGFormsModel::is_field_hidden( $form, $source_field, array(), $lead );

				if ( $is_value_match ) {
					$email_to[] = $routing['email'];
				}

				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - field value => ' . print_r( $field_value, 1 ) );
				$is_value_match = $is_value_match ? 'Yes' : 'No';
				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - is value match? ' . $is_value_match );
			}

			$email_to = join( ',', $email_to );
		} elseif ( ! empty( $to_field ) ) {
			$source_field = RGFormsModel::get_field( $form, $to_field );
			$email_to     = RGFormsModel::get_lead_field_value( $lead, $source_field );
		}

		// Running through variable replacement
		$to        = GFCommon::replace_variables( $email_to, $form, $lead, false, false, false, 'text', $data );
		$subject   = GFCommon::replace_variables( rgar( $notification, 'subject' ), $form, $lead, false, false, false, 'text', $data );
		$from      = GFCommon::replace_variables( rgar( $notification, 'from' ), $form, $lead, false, false, false, 'text', $data );
		$from_name = GFCommon::replace_variables( rgar( $notification, 'fromName' ), $form, $lead, false, false, false, 'text', $data );
		$bcc       = GFCommon::replace_variables( rgar( $notification, 'bcc' ), $form, $lead, false, false, false, 'text', $data );
		$replyTo   = GFCommon::replace_variables( rgar( $notification, 'replyTo' ), $form, $lead, false, false, false, 'text', $data );

		/**
		 * Enable the CC header for the notification.
		 *
		 * @since 2.3
		 *
		 * @param bool  $enable_cc    Should the CC header be enabled?
		 * @param array $notification The current notification object.
		 * @param array $from         The current form object.
		 */
		$enable_cc = gf_apply_filters( array( 'gform_notification_enable_cc', $form['id'], $notification['id'] ), false, $notification, $form );

		// Set CC if enabled.
		$cc = $enable_cc ? GFCommon::replace_variables( rgar( $notification, 'cc' ), $form, $lead, false, false, false, 'text', $data ) : null;

		$message_format = rgempty( 'message_format', $notification ) ? 'html' : rgar( $notification, 'message_format' );

		$merge_tag_format = $message_format === 'multipart' ? 'html' : $message_format;

		$message = GFCommon::replace_variables( rgar( $notification, 'message' ), $form, $lead, false, false, ! rgar( $notification, 'disableAutoformat' ), $merge_tag_format, $data );

		if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
			$message = do_shortcode( $message );
		}

		// Allow attachments to be passed as a single path (string) or an array of paths, if string provided, add to array.
		$attachments = rgar( $notification, 'attachments' );
		if ( ! empty( $attachments ) ) {
			$attachments = is_array( $attachments ) ? $attachments : array( $attachments );
		} else {
			$attachments = array();
		}

		// Add attachment fields.
		if ( rgar( $notification, 'enableAttachments', false ) ) {

			// Get file upload fields and upload root.
			$upload_fields = GFCommon::get_fields_by_type( $form, array( 'fileupload' ) );
			$upload_root   = GFFormsModel::get_upload_root();

			foreach ( $upload_fields as $upload_field ) {

				// Get field value.
				$attachment_urls = rgar( $lead, $upload_field->id );

				// If field value is empty, skip.
				if ( empty( $attachment_urls ) ) {
					self::log_debug( __METHOD__ . '(): No file(s) to attach for field #' . $upload_field->id );
					continue;
				}

				// Convert to array.
				$attachment_urls = $upload_field->multipleFiles ? json_decode( $attachment_urls, true ) : array( $attachment_urls );

				self::log_debug( __METHOD__ . '(): Attaching file(s) for field #' . $upload_field->id . '. ' . print_r( $attachment_urls, true ) );

				// Loop through attachment URLs; replace URL with path and add to attachments.
				foreach ( $attachment_urls as $attachment_url ) {
					$attachment_url = preg_replace( '|^(.*?)/gravity_forms/|', $upload_root, $attachment_url );
					$attachments[]  = $attachment_url;
				}

			}

		}

		$attachments = array_unique( $attachments );

		if ( $message_format === 'multipart' ) {

			// Creating alternate text message.
			$text_message = GFCommon::replace_variables( rgar( $notification, 'message' ), $form, $lead, false, false, ! rgar( $notification, 'disableAutoformat' ), 'text', $data );

			if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
				$text_message = do_shortcode( $text_message );
			}

			// Formatting text message. Removes all tags.
			$text_message = self::format_text_message( $text_message );

			// Sends text and html messages to send_email()
			$message = array(
				'html' => $message,
				'text' => $text_message,
			);
		}

		self::send_email( $from, $to, $bcc, $replyTo, $subject, $message, $from_name, $message_format, $attachments, $lead, $notification, $cc );

		return compact( 'to', 'from', 'bcc', 'replyTo', 'subject', 'message', 'from_name', 'message_format', 'attachments', 'cc' );
	}

	public static function send_notifications( $notification_ids, $form, $lead, $do_conditional_logic = true, $event = 'form_submission', $data = array() ) {
		$entry_id = rgar( $lead, 'id' );
		if ( ! is_array( $notification_ids ) || empty( $notification_ids ) ) {
			GFCommon::log_debug( __METHOD__ . "(): Aborting. No notifications to process for {$event} event for entry #{$entry_id}." );

			return;
		}

		GFCommon::log_debug( __METHOD__ . "(): Processing notifications for {$event} event for entry #{$entry_id}: " . print_r( $notification_ids, true ) . "\n(only active/applicable notifications are sent)" );

		foreach ( $notification_ids as $notification_id ) {
			if ( ! isset( $form['notifications'][ $notification_id ] ) ) {
				continue;
			}
			if ( isset( $form['notifications'][ $notification_id ]['isActive'] ) && ! $form['notifications'][ $notification_id ]['isActive'] ) {
				GFCommon::log_debug( __METHOD__ . "(): Notification is inactive, not processing notification (#{$notification_id} - {$form['notifications'][$notification_id]['name']}) for entry #{$entry_id}." );
				continue;
			}

			$notification = $form['notifications'][ $notification_id ];

			//check conditional logic when appropriate
			if ( $do_conditional_logic && ! GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $lead ) ) {
				GFCommon::log_debug( __METHOD__ . "(): Notification conditional logic not met, not processing notification (#{$notification_id} - {$notification['name']}) for entry #{$entry_id}." );
				continue;
			}

			if ( rgar( $notification, 'type' ) == 'user' ) {

				//Getting user notification from legacy structure (for backwards compatibility)
				$legacy_notification = GFCommon::prepare_user_notification( $form, $lead );
				$notification        = self::merge_legacy_notification( $notification, $legacy_notification );
			} elseif ( rgar( $notification, 'type' ) == 'admin' ) {

				//Getting admin notification from legacy structure (for backwards compatibility)
				$legacy_notification = GFCommon::prepare_admin_notification( $form, $lead );
				$notification        = self::merge_legacy_notification( $notification, $legacy_notification );
			}

			//sending notification
			self::send_notification( $notification, $form, $lead, $data );
		}

	}

	public static function send_form_submission_notifications( $form, $lead ) {
		GFAPI::send_notifications( $form, $lead );
	}

	private static function merge_legacy_notification( $notification, $notification_data ) {

		$keys = array(
			'to',
			'from',
			'bcc',
			'replyTo',
			'subject',
			'message',
			'from_name',
			'message_format',
			'attachments',
			'disableAutoformat'
		);
		foreach ( $keys as $key ) {
			$notification[ $key ] = rgar( $notification_data, $key );
		}

		return $notification;
	}

	public static function get_notifications_to_send( $event, $form, $lead ) {
		$notifications         = self::get_notifications( $event, $form );
		$notifications_to_send = array();
		foreach ( $notifications as $notification ) {
			if ( GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $lead ) ) {
				$notifications_to_send[] = $notification;
			}
		}

		return $notifications_to_send;
	}

	public static function get_notifications( $event, $form ) {
		if ( rgempty( 'notifications', $form ) ) {
			return array();
		}

		$notifications = array();
		foreach ( $form['notifications'] as $notification ) {
			$notification_event = rgar( $notification, 'event' );
			$omit_from_resend   = array( 'form_saved', 'form_save_email_requested' );
			if ( $notification_event == $event || ( $event == 'resend_notifications' && ! in_array( $notification_event, $omit_from_resend ) ) ) {
				$notifications[] = $notification;
			}
		}

		return $notifications;
	}

	public static function has_admin_notification( $form ) {

		return ( ! empty( $form['notification']['to'] ) || ! empty( $form['notification']['routing'] ) ) && ( ! empty( $form['notification']['subject'] ) || ! empty( $form['notification']['message'] ) );

	}

	public static function has_user_notification( $form ) {

		return ! empty( $form['autoResponder']['toField'] ) && ( ! empty( $form['autoResponder']['subject'] ) || ! empty( $form['autoResponder']['message'] ) );

	}

	public static function send_email( $from, $to, $bcc, $reply_to, $subject, $message, $from_name = '', $message_format = 'html', $attachments = '', $entry = false, $notification = false, $cc = null ) {

		global $phpmailer;
		$entry_id = rgar( $entry, 'id' );

		$to    = str_replace( ' ', '', $to );
		$bcc   = str_replace( ' ', '', $bcc );
		$cc    = str_replace( ' ', '', $cc );

		if ( ! GFCommon::is_valid_email( $from ) ) {
			$from = get_bloginfo( 'admin_email' );
		}

		// Array containing email details.
		$email = compact( 'from', 'to', 'bcc', 'reply_to', 'subject', 'message', 'from_name', 'message_format', 'attachments', 'cc' );

		$error = false;
		if ( ! GFCommon::is_valid_email_list( $to ) ) {

			$error_info = esc_html__( 'Cannot send email because the TO address is invalid.', 'gravityforms' );
			GFFormsModel::add_notification_note( $entry_id, false, $notification, $error_info, $email );

			$error = new WP_Error( 'invalid_to', 'Cannot send email because the TO address is invalid.' );

		} elseif ( empty( $subject ) && empty( $message ) ) {

			$error_info = esc_html__( 'Cannot send email because there is no SUBJECT and no MESSAGE.', 'gravityforms' );
			GFFormsModel::add_notification_note( $entry_id, false, $notification, $error_info, $email );

			$error = new WP_Error( 'missing_subject_and_message', 'Cannot send email because there is no SUBJECT and no MESSAGE.' );

		} elseif ( ! GFCommon::is_valid_email( $from ) ) {

			$error_info = esc_html__( 'Cannot send email because the FROM address is invalid.', 'gravityforms' );
			GFFormsModel::add_notification_note( $entry_id, false, $notification, $error_info, $email );

			$error = new WP_Error( 'invalid_from', 'Cannot send email because the FROM address is invalid.' );
		}

		switch ( strtolower( $message_format ) ) {
			case 'html' :
				$content_type = 'text/html';
				break;

			case 'text' :
				$content_type = 'text/plain';
				break;

			case 'multipart' :
				$boundary     = self::$email_boundary;
				$content_type = "multipart/alternative; boundary={$boundary}";

				break;

			default :
				//When content type is unknown, default to HTML
				$content_type = 'text/html';

				break;
		}

		if ( is_wp_error( $error ) ) {
			GFCommon::log_error( __METHOD__ . '(): ' . $error->get_error_message() );
			GFCommon::log_error( print_r( compact( 'to', 'subject', 'message' ), true ) );

			/**
			 * Fires when an email from Gravity Forms has failed to send
			 *
			 * @since 1.8.10
			 *
			 * @param string $error   The Error message returned after the email fails to send
			 * @param array  $details The details of the message that failed
			 * @param array  $entry   The Entry object
			 *
			 */
			do_action( 'gform_send_email_failed', $error, $email, $entry );

			return;
		}

		/**
		 * Allows for formatting of the TO email address to improve spam score.
		 *
		 * @param bool enabled Value being filtered. Return true to format email TO, or false to leave email TO as is. Defaults to false.
		 *
		 * @since 2.2.0.3
		 */
		if ( apply_filters( 'gform_format_email_to', false ) ) {
			// Formats email TO field to improve Spam Assassin score
			$to = self::format_email_to( $to );
		}

		$message = self::format_email_message( $message, $message_format, $subject );

		$name = empty( $from_name ) ? $from : $from_name;

		$headers         = array();
		$headers['From'] = 'From: "' . wp_strip_all_tags( $name, true ) . '" <' . $from . '>';

		if ( GFCommon::is_valid_email_list( $reply_to ) ) {
			$headers['Reply-To'] = "Reply-To: {$reply_to}";
		}

		if ( GFCommon::is_valid_email_list( $bcc ) ) {
			$headers['Bcc'] = "Bcc: $bcc";
		}

		if ( GFCommon::is_valid_email_list( $cc ) ) {
			$headers['Cc'] = "Cc: $cc";
		}

		$headers['Content-type'] = "Content-type: {$content_type}; charset=" . get_option( 'blog_charset' );

		$abort_email = false;

		/**
		 * Modify the email before a notification has been sent.
		 * You may also use this to prevent an email from being sent.
		 *
		 * @since 2.2.3.8  Added $entry parameter.
		 * @since 1.9.15.6 Added $notification parameter.
		 * @since Unknown
		 *
		 * @param array  $email          An array containing the email to address, subject, message, headers, attachments and abort email flag.
		 * @param string $message_format The message format: html or text.
		 * @param array  $notification   The current Notification object.
		 * @param array  $entry          The current Entry object.
		 */
		extract( apply_filters( 'gform_pre_send_email', compact( 'to', 'subject', 'message', 'headers', 'attachments', 'abort_email' ), $message_format, $notification, $entry ) );

		$is_success = false;

		// Determine when to add entry id information to the logging message.
		$entry_info = $entry_id ? ' for entry #' . $entry_id : '';

		if ( ! $abort_email ) {

			GFCommon::log_debug( __METHOD__ . '(): Sending email via wp_mail().' );
			GFCommon::log_debug( print_r( compact( 'to', 'subject', 'message', 'headers', 'attachments', 'abort_email' ), true ) );

			// Content type filter is needed to get around a bug in WordPress that ignores the boundary attribute and character set.
			add_filter( 'wp_mail_content_type', array( 'GFCommon', 'set_content_type_boundary' ) );
			add_filter( 'wp_mail_charset', array( 'GFCommon', 'set_mail_charset' ) );

			// Sending email.
			$is_success = wp_mail( $to, $subject, $message, $headers, $attachments );

			// Removing filter. It is only needed when sending GF notifications.
			remove_filter( 'wp_mail_content_type', array( 'GFCommon', 'set_content_type_boundary' ) );
			remove_filter( 'wp_mail_charset', array( 'GFCommon', 'set_mail_charset' ) );

			$result = is_wp_error( $is_success ) ? $is_success->get_error_message() : $is_success;

			// Get $phpmailer->ErrorInfo value if available.
			$error_info = is_object( $phpmailer ) ? $phpmailer->ErrorInfo : '';

			// Add note with sending result ?
			GFFormsModel::add_notification_note( $entry_id, $result, $notification, $error_info, $email );

			GFCommon::log_debug( __METHOD__ . "(): Result from wp_mail(): {$result}" );

			if ( ! is_wp_error( $is_success ) && $is_success ) {
				GFCommon::log_debug( sprintf( '%s(): WordPress successfully passed the notification email (#%s - %s)%s to the sending server.', __METHOD__, $notification['id'], $notification['name'], $entry_info ) );
			} else {
				GFCommon::log_error( sprintf( '%s(): WordPress was unable to send the notification email (#%s - %s)%s to the sending server.', __METHOD__, $notification['id'], $notification['name'], $entry_info ) );
			}

			if ( has_filter( 'phpmailer_init' ) ) {
				GFCommon::log_debug( __METHOD__ . '(): The WordPress phpmailer_init hook has been detected, usually used by SMTP plugins. It can alter the email setup/content or sending server, and impact the notification deliverability.' );
			}

			if ( ! empty( $error_info ) ) {
				GFCommon::log_debug( __METHOD__ . '(): PHPMailer class returned an error message: ' . $error_info );
			}
		} else {
			GFCommon::log_debug( sprintf( '%s(): Aborting notification (#%s - %s)%s. The gform_pre_send_email hook was used to set the abort_email parameter to true.', __METHOD__, $notification['id'], $notification['name'], $entry_info ) );
		}

		self::add_emails_sent();

		/**
		 * Fires after an email is sent
		 *
		 * @param bool   $is_success     True is successfully sent.  False if failed
		 * @param string $to             Recipient address
		 * @param string $subject        Subject line
		 * @param string $message        Message body
		 * @param string $headers        Email headers
		 * @param string $attachments    Email attachments
		 * @param string $message_format Format of the email.  Ex: text, html
		 * @param string $from           Address of the sender
		 * @param string $from_name      Displayed name of the sender
		 * @param string $bcc            BCC recipients
		 * @param string $reply_to       Reply-to address
		 * @param array  $entry          Entry object associated with the sent email
		 * @param string $cc             CC recipients
		 *
		 */
		do_action( 'gform_after_email', $is_success, $to, $subject, $message, $headers, $attachments, $message_format, $from, $from_name, $bcc, $reply_to, $entry, $cc );
	}

	/**
	 * Sets the boundary attribute of the Content-type email header.
	 * This is a target of the wp_mail_content_type filter and is needed to get around a WordPress bug
	 * That ignores the boundary attribute if added to the $headers parameter of wp_mail().
	 *
	 * @since 2.2
	 *
	 * @param $content_type Content type to be filtered
	 *
	 * @return string
	 */
	public static function set_content_type_boundary( $content_type ) {

		if ( $content_type === 'multipart/alternative' ) {
			$boundary     = GFCommon::$email_boundary;
			$content_type = "{$content_type}; boundary={$boundary}";
		}

		return $content_type;
	}

	/**
	 * Sets the character set email header.
	 *
	 * This is a target of the wp_mail_charset filter and is needed to get around a WordPress bug
	 * that ignores the charset attribute if added to the $headers parameter of wp_mail().
	 *
	 * @since 2.2
	 *
	 * @param string $charset Character set to be filtered.
	 *
	 * @return string
	 */
	public static function set_mail_charset( $charset ) {

		if ( empty( $charset ) ) {
			$charset = get_option( 'blog_charset' );
		}

		return $charset;
	}

	/**
	 * Formats emails to improve Spam Assassin score.
	 *
	 * @since 2.2
	 *
	 * @param string $to Email or comma separated list of emails to be formatted
	 *
	 * @return string
	 */
	private static function format_email_to( $to ) {

		$emails     = explode( ',', $to );
		$email_list = array();
		foreach ( $emails as $email ) {

			if ( empty( $email ) ) {
				continue;
			}

			// Formatting To to improve Spam Assassin score
			if ( strpos( $email, '<' ) === false ) {
				$email_list[] = "\"{$email}\" <$email>";
			}
		}

		return implode( ',', $email_list );
	}

	/**
	 * Formats the email message to improve Spam Assassin score.
	 *
	 * @since 2.2
	 *
	 * @param string $message Email message to be formatted.
	 * @param string $message_format Format of the message to be sent. 'text' or 'html'.
	 * @param string $subject Email subject.
	 *
	 * @return string
	 */
	private static function format_email_message( $message, $message_format, $subject ) {

		switch ( strtolower( $message_format ) ) {

			case 'html' :

				// Formatting HTML message
				$message = self::format_html_message( $message, $subject );
				return $message;
				break;

			case 'text' :

				// No format needed for text messages
				return $message;
				break;

			case 'multipart' :

				$html_message = self::format_html_message( $message['html'], $subject );
				$text_message = $message['text'];
				$boundary     = self::$email_boundary;

				// Formatting multipart message
				$message = "--{$boundary}
Content-Type: text/plain;

{$text_message}
--{$boundary}
Content-Type: text/html;

{$html_message}
--{$boundary}--";

				return $message;
				break;

			default :

				return $message;
		}
	}

	public static function add_emails_sent() {

		$count = self::get_emails_sent();

		update_option( 'gform_email_count', ++ $count );

	}

	public static function get_emails_sent() {
		$count = get_option( 'gform_email_count' );

		if ( ! $count ) {
			$count = 0;
		}

		return $count;
	}

	public static function get_api_calls() {
		$count = get_option( 'gform_api_count' );

		if ( ! $count ) {
			$count = 0;
		}

		return $count;
	}

	public static function add_api_call() {

		$count = self::get_api_calls();

		update_option( 'gform_api_count', ++ $count );

	}

	public static function has_post_field( $fields ) {
		foreach ( $fields as $field ) {
			if ( in_array( $field->type, array(
				'post_title',
				'post_content',
				'post_excerpt',
				'post_category',
				'post_image',
				'post_tags',
				'post_custom_field'
			) ) ) {
				return true;
			}
		}

		return false;
	}

	public static function has_list_field( $form ) {
		return self::has_field_by_type( $form, 'list' );
	}

	/**
	 * Whether the form contains a repeater field.
	 *
	 * @since 2.4
	 *
	 * @param $form
	 *
	 * @return bool
	 */
	public static function has_repeater_field( $form ) {
		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				if ( $field instanceof GF_Field_Repeater ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function has_credit_card_field( $form ) {
		return self::has_field_by_type( $form, 'creditcard' );
	}

	/**
	 * Whether the form has a consent field.
	 *
	 * @since 2.4
	 *
	 * @param $form
	 *
	 * @return bool
	 */
	public static function has_consent_field( $form ) {
		return self::has_field_by_type( $form, 'consent' );
	}

	private static function has_field_by_type( $form, $type ) {
		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {

				if ( RGFormsModel::get_input_type( $field ) == $type ) {
					return true;
				}
			}
		}

		return false;
	}

	/***
	 * Determines if the current user has the proper cabalities to uninstall the plugin specified in $plugin_path.
	 * Plugins that have been network activated can only be uninstalled by a network admin.
	 *
	 * @since 2.3.1.12
	 * @access public
	 *
	 * @param string $caps Capabilities that current user must have to be able to uninstall the plugin.
	 * @param string $plugin_path Path of the plugin to be checked, relative to the plugins folder. i.e. "gravityforms/gravityforms.php"
	 *
	 * @return bool True if current user can uninstall the plugin. False otherwise
	 */
	public static function current_user_can_uninstall( $caps = 'gravityforms_uninstall', $plugin_path = 'gravityforms/gravityforms.php' ) {

		$is_multisite = function_exists( 'is_multisite' ) && is_multisite();
		$is_network_activated = is_plugin_active_for_network( $plugin_path );


		//If an addon is network activated, it can only be uninstalled by a super admin.
		if ( $is_multisite && $is_network_activated ) {
			return is_super_admin();
		} else {
			return self::current_user_can_any( $caps );
		}

	}

	public static function current_user_can_any( $caps ) {

		if ( ! is_array( $caps ) ) {
			$has_cap = current_user_can( $caps ) || current_user_can( 'gform_full_access' );

			return $has_cap;
		}

		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}

		$has_full_access = current_user_can( 'gform_full_access' );

		return $has_full_access;
	}

	public static function current_user_can_which( $caps ) {

		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				return $cap;
			}
		}

		return '';
	}

	/**
	 * Checks if the given type is a pricing field.
	 *
	 * @since 2.4.10 Added creditcard field.
	 * @since unknown
	 *
	 * @param string $field_type The value of the field type or inputType property.
	 *
	 * @return bool
	 */
	public static function is_pricing_field( $field_type ) {
		$types = array( 'creditcard', 'donation' );

		return in_array( $field_type, $types, true ) || self::is_product_field( $field_type );
	}

	/**
	 * Checks if a field is a product field.
	 *
	 * @access public
	 * @since  2.1.1.12 Added support for hiddenproduct, singleproduct, and singleshipping input types.
	 *
	 * @param  string $field_type The field type.

	 *
	 * @return bool Returns true if it is a product field. Otherwise, false.
	 */
	public static function is_product_field( $field_type ) {
		/**
		 * Filters the input types to use when checking if a field is a product field.
		 *
		 * @since 2.1.1.12 Added support for hiddenproduct, singleproduct, and singleshipping input types.
		 * @since 1.9.14
		 *
		 * @param $product_fields The product field types.
		 */
		$product_fields = apply_filters( 'gform_product_field_types', array(
			'option',
			'quantity',
			'product',
			'total',
			'shipping',
			'calculation',
			'price',
			'hiddenproduct',
			'singleproduct',
			'singleshipping'
		) );

		return in_array( $field_type, $product_fields );
	}

	/**
	 * Returns all the plugin capabilities.
	 *
	 * @since 2.2.1.12 Added gravityforms_system_status.
	 * @since unknown
	 *
	 * @return array
	 */
	public static function all_caps() {
		return array(
			'gravityforms_edit_forms',
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
			'gravityforms_view_addons',
			'gravityforms_preview_forms',
			'gravityforms_system_status',
		);
	}

	public static function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}

		if ( $handle = opendir( $dir ) ) {
			$array = array();
			while ( false !== ( $file = readdir( $handle ) ) ) {
				if ( $file != '.' && $file != '..' ) {
					if ( is_dir( $dir . $file ) ) {
						if ( ! @rmdir( $dir . $file ) ) {
							// Empty directory? Remove it
							self::delete_directory( $dir . $file . '/' );
						} // Not empty? Delete the files inside it
					} else {
						@unlink( $dir . $file );
					}
				}
			}
			closedir( $handle );
			@rmdir( $dir );
		}
	}

	public static function get_remote_message() {
		return stripslashes( get_option( 'rg_gforms_message' ) );
	}

	public static function get_key() {
		return get_option( 'rg_gforms_key' );
	}

	public static function has_update( $use_cache = true ) {
		$version_info = GFCommon::get_version_info( $use_cache );
		$version      = rgar( $version_info, 'version' );

		return empty( $version ) ? false : version_compare( GFCommon::$version, $version, '<' );
	}

	public static function get_key_info( $key ) {

		$options            = array( 'method' => 'POST', 'timeout' => 3 );
		$options['headers'] = array(
			'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ),
			'User-Agent'   => 'WordPress/' . get_bloginfo( 'version' ),
			'Referer'      => get_bloginfo( 'url' )
		);

		$raw_response = self::post_to_manager( 'api.php', "op=get_key&key={$key}", $options );

		if ( is_wp_error( $raw_response ) || $raw_response['response']['code'] != 200 ) {
			return array();
		}

		$key_info = unserialize( trim( $raw_response['body'] ) );

		return $key_info ? $key_info : array();
	}

	public static function get_version_info( $cache = true ) {

		$version_info = get_option( 'gform_version_info' );
		if ( ! $cache ) {
			$version_info = null;
		} else {

			// Checking cache expiration
			$cache_duration = DAY_IN_SECONDS; // 24 hours.
			$cache_timestamp = $version_info && isset( $version_info['timestamp'] ) ? $version_info['timestamp'] : 0;

			// Is cache expired ?
			if ( $cache_timestamp + $cache_duration < time() ) {
				$version_info = null;
			}
		}

		if ( is_wp_error( $version_info ) || isset( $version_info['headers'] ) ) {
			// Legacy ( < 2.1.1.14 ) version info contained the whole raw response.
			$version_info = null;
		}

		if ( ! $version_info ) {
			//Getting version number
			$options            = array( 'method' => 'POST', 'timeout' => 20 );
			$options['headers'] = array(
				'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ),
				'User-Agent'   => 'WordPress/' . get_bloginfo( 'version' ),
				'Referer'      => get_bloginfo( 'url' ),
			);
			$options['body']    = self::get_remote_post_params();
			$options['timeout'] = 15;

			$nocache = $cache ? '' : 'nocache=1'; //disabling server side caching

			$raw_response = self::post_to_manager( 'version.php', $nocache, $options );

			if ( is_wp_error( $raw_response ) || rgars( $raw_response, 'response/code' ) != 200 ) {

				$version_info = array( 'is_valid_key' => '1', 'version' => '', 'url' => '', 'is_error' => '1' );
			} else {
				$version_info = json_decode( $raw_response['body'], true );
				if ( empty( $version_info ) ) {
					$version_info = array( 'is_valid_key' => '1', 'version' => '', 'url' => '', 'is_error' => '1' );
				}
			}

			$version_info['timestamp'] = time();

			// Caching response.
			update_option( 'gform_version_info', $version_info, false ); //caching version info
		}

		return $version_info;
	}

	public static function get_remote_request_params() {
		global $wpdb;

		return sprintf( 'of=GravityForms&key=%s&v=%s&wp=%s&php=%s&mysql=%s&version=2', urlencode( self::get_key() ), urlencode( self::$version ), urlencode( get_bloginfo( 'version' ) ), urlencode( phpversion() ), urlencode( $wpdb->db_version() ) );
	}

	public static function get_remote_post_params() {
		global $wpdb;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_list = get_plugins();
		$site_url    = get_bloginfo( 'url' );
		$plugins     = array();

		$active_plugins = get_option( 'active_plugins' );

		foreach ( $plugin_list as $key => $plugin ) {
			$is_active = in_array( $key, $active_plugins );

			$slug = substr( $key, 0, strpos( $key, '/' ) );
			if ( empty( $slug ) ) {
				$slug = str_replace( '.php', '', $key );
			}

			$plugins[] = array(
				'name' => str_replace( 'phpinfo()', 'PHP Info', $plugin['Name'] ),
				'slug' => $slug,
				'version' => $plugin['Version'],
				'is_active' => $is_active,
			);
		}
		$plugins = json_encode( $plugins );

		//get theme info
		$theme            = wp_get_theme();
		$theme_name       = $theme->get( 'Name' );
		$theme_uri        = $theme->get( 'ThemeURI' );
		$theme_version    = $theme->get( 'Version' );
		$theme_author     = $theme->get( 'Author' );
		$theme_author_uri = $theme->get( 'AuthorURI' );

		$form_counts    = GFFormsModel::get_form_count();
		$active_count   = $form_counts['active'];
		$inactive_count = $form_counts['inactive'];
		$fc             = abs( $active_count ) + abs( $inactive_count );
		$entry_count    = GFFormsModel::get_entry_count_all_forms( 'active' );
		$meta_counts    = GFFormsModel::get_entry_meta_counts();
		$im             = is_multisite();
		$lang           = get_locale();

		$post = array(
			'of'      => 'gravityforms',
			'key'     => self::get_key(),
			'v'       => self::$version,
			'wp'      => get_bloginfo( 'version' ),
			'php'     => phpversion(),
			'mysql'   => $wpdb->db_version(),
			'version' => '2',
			'plugins' => $plugins,
			'tn'      => $theme_name,
			'tu'      => $theme_uri,
			'tv'      => $theme_version,
			'ta'      => $theme_author,
			'tau'     => $theme_author_uri,
			'im'      => $im,
			'fc'      => $fc,
			'ec'      => $entry_count,
			'emc'     => self::get_emails_sent(),
			'api'     => self::get_api_calls(),
			'emeta'   => $meta_counts['meta'],
			'ed'      => $meta_counts['details'],
			'en'      => $meta_counts['notes'],
			'lang'    => $lang
		);

		return $post;
	}

	public static function ensure_wp_version() {
		if ( ! GF_SUPPORTED_WP_VERSION ) {
			echo "<div class='error' style='padding:10px;'>" . sprintf( esc_html__( 'Gravity Forms requires WordPress %s or greater. You must upgrade WordPress in order to use Gravity Forms', 'gravityforms' ), GF_MIN_WP_VERSION ) . '</div>';

			return false;
		}

		return true;
	}

	public static function check_update( $option, $cache = true ) {

		if ( ! is_object( $option ) ) {
			return $option;
		}

		$version_info = self::get_version_info( $cache );

		if ( ! $version_info ) {
			return $option;
		}

		$plugin_path = 'gravityforms/gravityforms.php';
		if ( empty( $option->response[ $plugin_path ] ) ) {
			$option->response[ $plugin_path ] = new stdClass();
		}

		$version = rgar( $version_info, 'version' );
		//Empty response means that the key is invalid. Do not queue for upgrade
		if ( ! rgar( $version_info, 'is_valid_key' ) || version_compare( GFCommon::$version, $version, '>=' ) ) {
			unset( $option->response[ $plugin_path ] );
		} else {
			$url                                           = rgar( $version_info, 'url' );
			$option->response[ $plugin_path ]->url         = 'http://www.gravityforms.com';
			$option->response[ $plugin_path ]->slug        = 'gravityforms';
			$option->response[ $plugin_path ]->plugin      = $plugin_path;
			$option->response[ $plugin_path ]->package     = str_replace( '{KEY}', GFCommon::get_key(), $url );
			$option->response[ $plugin_path ]->new_version = $version;
			$option->response[ $plugin_path ]->id          = '0';
		}

		return $option;

	}

	public static function cache_remote_message() {
		//Getting version number
		$key                = GFCommon::get_key();
		$body               = "key=$key";
		$options            = array( 'method' => 'POST', 'timeout' => 3, 'body' => $body );
		$options['headers'] = array(
			'Content-Type'   => 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ),
			'Content-Length' => strlen( $body ),
			'User-Agent'     => 'WordPress/' . get_bloginfo( 'version' ),
			'Referer'        => get_bloginfo( 'url' )
		);

		$raw_response = self::post_to_manager( 'message.php', GFCommon::get_remote_request_params(), $options );

		if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code'] ) {
			$message = '';
		} else {
			$message = $raw_response['body'];
		}

		//validating that message is a valid Gravity Form message. If message is invalid, don't display anything
		if ( substr( $message, 0, 10 ) != '<!--GFM-->' ) {
			$message = '';
		}

		update_option( 'rg_gforms_message', $message );
	}

	public static function post_to_manager( $file, $query, $options ) {

		$request_url = GRAVITY_MANAGER_URL . '/' . $file . '?' . $query;
		self::log_debug( __METHOD__ . '(): endpoint: ' . $request_url );
		$raw_response = wp_remote_post( $request_url, $options );
		self::log_remote_response( $raw_response );

		if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code'] ) {
			self::log_error( __METHOD__ . '(): Error from manager. Sending to proxy...' );
			$request_url  = GRAVITY_MANAGER_PROXY_URL . '/proxy.php?f=' . $file . '&' . $query;
			$raw_response = wp_remote_post( $request_url, $options );
			self::log_remote_response( $raw_response );
		}

		return $raw_response;
	}

	/**
	 * Converts the given timestamp to a pseudo timestamp which has been adjusted for the timezone in the WordPress settings.
	 *
	 *
	 * @param int $timestamp
	 *
	 * @return int
	 */
	public static function get_local_timestamp( $timestamp = null ) {
		if ( $timestamp == null ) {
			$timestamp = time();
		}

		$gmt_datetime = gmdate( 'Y-m-d H:i:s', $timestamp );

		return strtotime( get_date_from_gmt( $gmt_datetime ) );
	}

	public static function get_gmt_timestamp( $local_timestamp ) {
		return $local_timestamp - ( get_option( 'gmt_offset' ) * 3600 );
	}

	public static function format_date( $gmt_datetime, $is_human = true, $date_format = '', $include_time = true ) {
		if ( empty( $gmt_datetime ) ) {
			return '';
		}

		//adjusting date to local configured Time Zone
		$lead_gmt_time   = mysql2date( 'G', $gmt_datetime );
		$lead_local_time = self::get_local_timestamp( $lead_gmt_time );

		if ( empty( $date_format ) ) {
			$date_format = get_option( 'date_format' );
		}

		if ( $is_human ) {
			$time_diff = time() - $lead_gmt_time;

			if ( $time_diff > 0 && $time_diff < 24 * 60 * 60 ) {
				$date_display = sprintf( esc_html__( '%s ago', 'gravityforms' ), human_time_diff( $lead_gmt_time ) );
			} else {
				$date_display = $include_time ? sprintf( esc_html__( '%1$s at %2$s', 'gravityforms' ), date_i18n( $date_format, $lead_local_time, true ), date_i18n( get_option( 'time_format' ), $lead_local_time, true ) ) : date_i18n( $date_format, $lead_local_time, true );
			}
		} else {
			$date_display = $include_time ? sprintf( esc_html__( '%1$s at %2$s', 'gravityforms' ), date_i18n( $date_format, $lead_local_time, true ), date_i18n( get_option( 'time_format' ), $lead_local_time, true ) ) : date_i18n( $date_format, $lead_local_time, true );
		}

		return $date_display;
	}

	public static function get_selection_value( $value ) {
		$ary = explode( '|', $value );
		$val = $ary[0];

		return $val;
	}

	public static function selection_display( $value, $field, $currency = '', $use_text = false ) {
		if ( is_array( $value ) ) {
			return '';
		}

		if ( $field !== null && $field->enablePrice ) {
			$ary   = explode( '|', $value );
			$val   = $ary[0];
			$price = count( $ary ) > 1 ? $ary[1] : '';
		} else {
			$val   = $value;
			$price = '';
		}

		if ( $use_text ) {
			$val = RGFormsModel::get_choice_text( $field, $val );
		}

		if ( ! empty( $price ) ) {
			return "$val (" . self::to_money( $price, $currency ) . ')';
		} else {
			return $val;
		}
	}

	public static function date_display( $value, $input_format = 'mdy', $output_format = false ) {

		if ( ! $output_format ) {
			$output_format = $input_format;
		}

		$date = self::parse_date( $value, $input_format );
		if ( empty( $date ) ) {
			return $value;
		}

		list( $position, $separator ) = rgexplode( '_', $output_format, 2 );
		switch ( $separator ) {
			case 'dash' :
				$separator = '-';
				break;
			case 'dot' :
				$separator = '.';
				break;
			default :
				$separator = '/';
				break;
		}

		switch ( $position ) {
			case 'year' :
			case 'month' :
			case 'day' :
				return $date[ $position ];

			case 'ymd' :
				return $date['year'] . $separator . $date['month'] . $separator . $date['day'];
				break;

			case 'dmy' :
				return $date['day'] . $separator . $date['month'] . $separator . $date['year'];
				break;

			default :
				return $date['month'] . $separator . $date['day'] . $separator . $date['year'];
				break;

		}
	}

	public static function parse_date( $date, $format = 'mdy' ) {
		$date_info = array();

		$position = substr( $format, 0, 3 );

		if ( is_array( $date ) ) {

			switch ( $position ) {
				case 'mdy' :
					$date_info['month'] = rgar( $date, 0 );
					$date_info['day']   = rgar( $date, 1 );
					$date_info['year']  = rgar( $date, 2 );
					break;

				case 'dmy' :
					$date_info['day']   = rgar( $date, 0 );
					$date_info['month'] = rgar( $date, 1 );
					$date_info['year']  = rgar( $date, 2 );
					break;

				case 'ymd' :
					$date_info['year']  = rgar( $date, 0 );
					$date_info['month'] = rgar( $date, 1 );
					$date_info['day']   = rgar( $date, 2 );
					break;
			}

			return $date_info;
		}

		$date = preg_replace( "|[/\.]|", '-', $date );
		if ( preg_match( '/^(\d{1,4})-(\d{1,2})-(\d{1,4})$/', $date, $matches ) ) {

			if ( strlen( $matches[1] ) == 4 ) {
				//format yyyy-mm-dd
				$date_info['year']  = $matches[1];
				$date_info['month'] = $matches[2];
				$date_info['day']   = $matches[3];
			} else if ( $position == 'mdy' ) {
				//format mm-dd-yyyy
				$date_info['month'] = $matches[1];
				$date_info['day']   = $matches[2];
				$date_info['year']  = $matches[3];
			} else {
				//format dd-mm-yyyy
				$date_info['day']   = $matches[1];
				$date_info['month'] = $matches[2];
				$date_info['year']  = $matches[3];
			}
		}

		return $date_info;
	}


	public static function truncate_url( $url ) {
		// parse URL to break it out into pieces
		$parsed_url = parse_url( $url );

		if ( isset( $parsed_url['path'] ) ) {
			if ( $parsed_url['path'] == '/' ) {
				// In instances where the path is just /, set truncated URL to be the host.
				$truncated_url = $parsed_url['host'];
			} else {
				// Get the basename from the URL Path
				$truncated_url = basename( $parsed_url['path'] );
			}

			// Append a query string if necessary.
			if ( isset( $parsed_url['query'] ) ) {
				$truncated_url .= '/?...';
			}

		} else {
			// Anything outside of the above will fall back to the old truncation logic.
			$truncated_url = basename( $url );

			if ( empty( $truncated_url ) ) {
				$truncated_url = dirname( $url );
			}
		}

		return $truncated_url;
	}

	public static function get_field_placeholder_attribute( $field ) {

		$placeholder_value = GFCommon::replace_variables_prepopulate( $field->placeholder );

		return ! empty( $placeholder_value ) ? sprintf( "placeholder='%s'", esc_attr( $placeholder_value ) ) : '';
	}

	public static function get_input_placeholder_attribute( $input ) {

		$placeholder_value = self::get_input_placeholder_value( $input );

		return ! empty( $placeholder_value ) ? sprintf( "placeholder='%s'", esc_attr( $placeholder_value ) ) : '';
	}

	public static function get_input_placeholder_value( $input ) {

		$placeholder = rgar( $input, 'placeholder' );

		return empty( $placeholder ) ? '' : GFCommon::replace_variables_prepopulate( $placeholder );
	}

	public static function get_tabindex() {
		return GFCommon::$tab_index > 0 ? "tabindex='" . GFCommon::$tab_index ++ . "'" : '';
	}

	/**
	 * @deprecated
	 *
	 * @param GF_Field_Checkbox $field
	 * @param                   $value
	 * @param                   $disabled_text
	 *
	 * @return mixed
	 */
	public static function get_checkbox_choices( $field, $value, $disabled_text ) {
		_deprecated_function( 'get_checkbox_choices', '1.9', 'GF_Field_Checkbox::get_checkbox_choices' );

		return $field->get_checkbox_choices( $value, $disabled_text );
	}

	/**
	 * @deprecated Deprecated since 1.9. Use GF_Field_Checkbox::get_radio_choices() instead.
	 *
	 * @param GF_Field_Radio $field
	 * @param string         $value
	 * @param                $disabled_text
	 *
	 * @return mixed
	 */
	public static function get_radio_choices( $field, $value = '', $disabled_text ) {
		_deprecated_function( 'get_radio_choices', '1.9', 'GF_Field_Checkbox::get_radio_choices' );

		return $field->get_radio_choices( $value, $disabled_text );
	}

	public static function get_field_type_title( $type ) {
		$gf_field = GF_Fields::get( $type );
		if ( ! empty( $gf_field ) ) {
			return $gf_field->get_form_editor_field_title();
		}

		return apply_filters( 'gform_field_type_title', $type, $type );
	}

	public static function get_select_choices( $field, $value = '', $support_placeholders = true ) {
		$choices     = '';
		$placeholder = '';

		if ( $support_placeholders && ! rgblank( $field->placeholder ) ) {
			$placeholder = self::replace_variables_prepopulate( $field->placeholder );
		}

		if ( rgget( 'view' ) == 'entry' && empty( $value ) && rgblank( $placeholder ) ) {
			$choices .= "<option value=''></option>";
		}

		if ( is_array( $field->choices ) ) {

			if ( ! rgblank( $placeholder ) ) {
				$selected = empty( $value ) ? "selected='selected'" : '';
				$choices .= sprintf( "<option value='' %s class='gf_placeholder'>%s</option>", $selected, esc_html( $placeholder) );
			}

			foreach ( $field->choices as $choice ) {

				//needed for users upgrading from 1.0
				$field_value = ! empty( $choice['value'] ) || $field->enableChoiceValue || $field->type == 'post_category' ? $choice['value'] : $choice['text'];
				if ( $field->enablePrice ) {
					$price = rgempty( 'price', $choice ) ? 0 : GFCommon::to_number( rgar( $choice, 'price' ) );
					$field_value .= '|' . $price;
				}

				if ( ! isset( $_GET['gf_token'] ) && empty( $_POST ) && self::is_empty_array( $value ) && rgget('view') != 'entry' ) {
					$selected = rgar( $choice, 'isSelected' ) ? "selected='selected'" : '';
				} else {
					if ( is_array( $value ) ) {
						$is_match = false;
						foreach ( $value as $item ) {
							if ( RGFormsModel::choice_value_match( $field, $choice, $item ) ) {
								$is_match = true;
								break;
							}
						}
						$selected = $is_match ? "selected='selected'" : '';
					} else {
						$selected = RGFormsModel::choice_value_match( $field, $choice, $value ) ? "selected='selected'" : '';
					}
				}

				$choice_markup = sprintf( "<option value='%s' %s>%s</option>", esc_attr( $field_value ), $selected, esc_html( $choice['text'] ) );

				$choices .= gf_apply_filters( array(
					'gform_field_choice_markup_pre_render',
					$field->formId,
					$field->id
				), $choice_markup, $choice, $field, $value );

			}
		}

		return $choices;
	}

	public static function is_section_empty( $section_field, $form, $entry ) {

		$cache_key = "GFCommon::is_section_empty_{$form['id']}_{$section_field->id}";
		$value     = GFCache::get( $cache_key, $is_hit, false );

		if ( $value !== false ) {
			return $value == true;
		}

		$fields = self::get_section_fields( $form, $section_field->id );
		if ( ! is_array( $fields ) ) {
			GFCache::set( $cache_key, 1 );

			return true;
		}

		foreach ( $fields as $field ) {

			$value = GFFormsModel::get_lead_field_value( $entry, $field );
			$value = GFCommon::get_lead_field_display( $field, $value, rgar( $entry, 'currency' ) );

			if ( rgblank( $value ) ) {
				continue;
			}

			// most fields are displayed in the section by default, exceptions are handled below
			$is_field_displayed_in_section = true;

			// by default, product fields are not displayed in their containing section (displayed in a product summary table)
			// if the filter is used to disable this, product fields are displayed in the section like other fields
			if ( self::is_product_field( $field->type ) ) {

				/**
				 * By default, product fields are not displayed in their containing section (displayed in a product summary table). If the filter is used to disable this, product fields are displayed in the section like other fields
				 *
				 * @param array $field The Form Fields Object
				 * @param array $form  The Form Object
				 * @param array $entry The Entry object
				 *
				 */
				$display_product_summary = apply_filters( 'gform_display_product_summary', true, $field, $form, $entry );

				$is_field_displayed_in_section = ! $display_product_summary;
			}

			if ( $is_field_displayed_in_section ) {
				GFCache::set( $cache_key, 0 );

				return false;
			}
		}

		GFCache::set( $cache_key, 1 );

		return true;
	}

	public static function get_section_fields( $form, $section_field_id ) {
		$fields     = array();
		$in_section = false;
		foreach ( $form['fields'] as $field ) {
			if ( in_array( $field->type, array( 'section', 'page' ) ) && $in_section ) {
				return $fields;
			}

			if ( $field->id == $section_field_id ) {
				$in_section = true;
			}

			if ( $in_section ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public static function get_us_state_code( $state_name ) {
		return GF_Fields::get( 'address' )->get_us_state_code( $state_name );
	}

	public static function get_country_code( $country_name ) {
		return GF_Fields::get( 'address' )->get_country_code( $country_name );
	}

	public static function get_us_states() {
		return GF_Fields::get( 'address' )->get_us_states();
	}

	public static function get_canadian_provinces() {
		return GF_Fields::get( 'address' )->get_canadian_provinces();
	}

	public static function is_post_field( $field ) {
		return in_array( $field->type, array(
			'post_title',
			'post_tags',
			'post_category',
			'post_custom_field',
			'post_content',
			'post_excerpt',
			'post_image'
		) );
	}

	public static function get_fields_by_type( $form, $types ) {
		return GFAPI::get_fields_by_type( $form, $types );
	}

	public static function has_pages( $form ) {
		return sizeof( GFAPI::get_fields_by_type( $form, array( 'page' ) ) ) > 0;
	}

	public static function get_product_fields_by_type( $form, $types, $product_id ) {
		global $_product_fields;
		$key = json_encode( $types ) . '_' . $product_id . '_' . $form['id'];
		if ( ! isset( $_product_fields[ $key ] ) ) {
			$fields = array();
			for ( $i = 0, $count = sizeof( $form['fields'] ); $i < $count; $i ++ ) {
				$field = $form['fields'][ $i ];
				if ( in_array( $field->type, $types ) && $field->productField == $product_id ) {
					$fields[] = $field;
				}
			}
			$_product_fields[ $key ] = $fields;
		}

		return $_product_fields[ $key ];
	}

	public static function form_page_title( $form ) {
		$editable_class = GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ? ' gform_settings_page_title_editable' : '';

		?>
		<h1>
			<span id='gform_settings_page_title' class='gform_settings_page_title<?php echo $editable_class ?>' onclick='GF_ShowEditTitle()'><?php echo esc_html( rgar( $form, 'title' ) ); ?></span>
			<?php GFForms::form_switcher(); ?>
			<span class="gf_admin_page_formid">ID: <?php echo absint( $form['id'] ); ?></span>
		</h1>
		<?php GFForms::edit_form_title( $form ); ?>
		<?php
	}


	/**
	 * @deprecated
	 *
	 * @param GF_Field $field
	 *
	 * @return mixed
	 */
	public static function has_field_calculation( $field ) {
		_deprecated_function( 'has_field_calculation', '1.7', 'GF_Field::has_calculation' );

		return $field->has_calculation();
	}

	/**
	 * @param GF_Field $field
	 * @param string   $value
	 * @param int      $lead_id
	 * @param int      $form_id
	 * @param null     $form
	 *
	 * @return mixed|string|void
	 */
	public static function get_field_input( $field, $value = '', $lead_id = 0, $form_id = 0, $form = null ) {

		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		$is_form_editor  = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$id       = intval( $field->id );
		$field_id = $is_admin || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$form_id  = $is_admin && empty( $form_id ) ? rgget( 'id' ) : $form_id;

		if ( rgget('view') == 'entry' && ! empty( $lead_id ) ) {
			$lead      = RGFormsModel::get_lead( $lead_id );
			$post_id   = rgar( $lead, 'post_id' );
			$post_link = '';
			if ( is_numeric( $post_id ) && self::is_post_field( $field ) ) {
				$post_link = "<div>You can <a href='post.php?action=edit&post=$post_id'>edit this post</a> from the post page.</div>";
			}
		}

		/**
		 * Filters the field input markup.
		 *
		 * @since 2.1.2.14 Added form and field ID modifiers.
		 *
		 * @param string empty    The markup. Defaults to an empty string.
		 * @param array  $field   The Field Object.
		 * @param int    $lead_id The entry ID.
		 * @param string $value   The field value.
		 * @param int    $form_id The form ID.
		 */
		$field_input = gf_apply_filters( array( 'gform_field_input', $form_id, $field->id ), '', $field, $value, $lead_id, $form_id );
		if ( $field_input ) {
			return $field_input;
		}

		// Pricing fields are not editable.
		if ( rgget('view') == 'entry' && self::is_pricing_field( $field->type ) ) {

			return "<div class='ginput_container'>" . esc_html__( 'Pricing fields are not editable' , 'gravityforms' ) . '</div>';

		}

		// Add categories as choices for Post Category field
		if ( $field->type == 'post_category' ) {
			$field = self::add_categories_as_choices( $field, $value );
		}

		$type = RGFormsModel::get_input_type( $field );
		switch ( $type ) {

			case 'honeypot':
				$autocomplete = RGFormsModel::is_html5_enabled() ? "autocomplete='off'" : '';

				return "<div class='ginput_container'><input name='input_{$id}' id='{$field_id}' type='text' value='' {$autocomplete}/></div>";
				break;

			case 'adminonly_hidden' :
				$inputs = $field->get_entry_inputs();

				if ( ! is_array( $inputs ) ) {
					if ( is_array( $value ) ) {
						$value = json_encode( $value );
					}

					return sprintf( "<input name='input_%d' id='%s' class='gform_hidden' type='hidden' value='%s'/>", $id, esc_attr( $field_id ), esc_attr( $value ) );
				}


				$fields = '';
				foreach ( $inputs as $input ) {
					$fields .= sprintf( "<input name='input_%s' class='gform_hidden' type='hidden' value='%s'/>", $input['id'], esc_attr( rgar( $value, strval( $input['id'] ) ) ) );
				}

				return $fields;
				break;

			default :

				if ( ! empty( $post_link ) ) {
					return $post_link;
				}

				if ( $form === null ) {
					$form = array( 'id' => 0 );
				}

				if ( ! isset( $lead ) ) {
					$lead = null;
				}

				return $field->get_field_input( $form, $value, $lead );

				break;

		}
	}

	public static function is_ssl() {
		global $wordpress_https;
		$is_ssl = false;

		$has_https_plugin  = class_exists( 'WordPressHTTPS' ) && isset( $wordpress_https );
		$has_is_ssl_method = $has_https_plugin && method_exists( 'WordPressHTTPS', 'is_ssl' );
		$has_isSsl_method  = $has_https_plugin && method_exists( 'WordPressHTTPS', 'isSsl' );

		//Use the WordPress HTTPs plugin if installed
		if ( $has_https_plugin && $has_is_ssl_method ) {
			$is_ssl = $wordpress_https->is_ssl();
		} else if ( $has_https_plugin && $has_isSsl_method ) {
			$is_ssl = $wordpress_https->isSsl();
		} else {
			$is_ssl = is_ssl();
		}


		if ( ! $is_ssl && isset( $_SERVER['HTTP_CF_VISITOR'] ) && strpos( $_SERVER['HTTP_CF_VISITOR'], 'https' ) ) {
			$is_ssl = true;
		}

		return apply_filters( 'gform_is_ssl', $is_ssl );
	}

	public static function is_preview() {
		$url_info  = parse_url( RGFormsModel::get_current_page_url() );
		$file_name = basename( $url_info['path'] );

		return $file_name == 'preview.php' || rgget( 'gf_page', $_GET ) == 'preview';
	}

	public static function clean_extensions( $extensions ) {
		$count = sizeof( $extensions );
		for ( $i = 0; $i < $count; $i ++ ) {
			$extensions[ $i ] = str_replace( '.', '', str_replace( ' ', '', $extensions[ $i ] ) );
		}

		return $extensions;
	}

	public static function get_disallowed_file_extensions() {

		$extensions = array(
			'php',
			'asp',
			'aspx',
			'cmd',
			'csh',
			'bat',
			'html',
			'htm',
			'hta',
			'jar',
			'exe',
			'com',
			'js',
			'lnk',
			'htaccess',
			'phtml',
			'ps1',
			'ps2',
			'php3',
			'php4',
			'php5',
			'php6',
			'py',
			'rb',
			'tmp'
		);

		// Intended for internal use - not to be included in the documentation.
		$extensions = apply_filters( 'gform_disallowed_file_extensions', $extensions );

		return $extensions;
	}

	public static function match_file_extension( $file_name, $extensions ) {
		if ( empty ( $extensions ) || ! is_array( $extensions ) ) {
			return false;
		}

		$ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, $extensions ) ) {
			return true;
		}

		return false;
	}

	public static function file_name_has_disallowed_extension( $file_name ) {

		return self::match_file_extension( $file_name, self::get_disallowed_file_extensions() ) || strpos( strtolower( $file_name ), '.php.' ) !== false;
	}

	public static function check_type_and_ext( $file, $file_name = '' ) {
		if ( empty( $file_name ) ) {
			$file_name = $file['name'];
		}
		$tmp_name = $file['tmp_name'];
		// Whitelist the mime type and extension
		$wp_filetype     = wp_check_filetype_and_ext( $tmp_name, $file_name );
		$ext             = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
		$type            = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
		$proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];

		if ( $proper_filename ) {
			return new WP_Error( 'invalid_file', esc_html__( 'There was an problem while verifying your file.' ) );
		}
		if ( ! $ext ) {
			return new WP_Error( 'illegal_extension', esc_html__( 'Sorry, this file extension is not permitted for security reasons.' ) );
		}
		if ( ! $type ) {
			return new WP_Error( 'illegal_type', esc_html__( 'Sorry, this file type is not permitted for security reasons.' ) );
		}

		return true;
	}

	public static function to_money( $number, $currency_code = '' ) {
		if ( empty( $currency_code ) ) {
			$currency_code = self::get_currency();
		}

		$currency = new RGCurrency( $currency_code );

		return $currency->to_money( $number );
	}

	public static function to_number( $text, $currency_code = '' ) {
		if ( empty( $currency_code ) ) {
			$currency_code = self::get_currency();
		}

		$currency = new RGCurrency( $currency_code );

		return $currency->to_number( $text );
	}

	public static function get_currency() {
		$currency = get_option( 'rg_gforms_currency' );
		$currency = empty( $currency ) ? 'USD' : $currency;

		return apply_filters( 'gform_currency', $currency );
	}

	public static function get_simple_captcha() {
		_deprecated_function( 'GFCommon::get_simple_captcha', '1.9', 'GFField_CAPTCHA::get_simple_captcha' );
		$captcha          = new ReallySimpleCaptcha();
		$captcha->tmp_dir = RGFormsModel::get_upload_path( 'captcha' ) . '/';

		return $captcha;
	}

	/**
	 * @deprecated
	 *
	 * @param GF_Field_CAPTCH $field
	 *
	 * @return mixed
	 */
	public static function get_captcha( $field ) {
		_deprecated_function( 'GFCommon::get_captcha', '1.9', 'GFField_CAPTCHA::get_captcha' );

		return $field->get_captcha();
	}

	/**
	 * @deprecated
	 *
	 * @param $field
	 * @param $pos
	 *
	 * @return mixed
	 */
	public static function get_math_captcha( $field, $pos ) {
		_deprecated_function( 'GFCommon::get_math_captcha', '1.9', 'GFField_CAPTCHA::get_math_captcha' );

		return $field->get_math_captcha( $pos );
	}

	/**
	 * @param GF_Field $field
	 * @param          $value
	 * @param string   $currency
	 * @param bool     $use_text
	 * @param string   $format
	 * @param string   $media
	 *
	 * @return array|mixed|string
	 */
	public static function get_lead_field_display( $field, $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		if ( $field->type == 'post_category' ) {
			$value = self::prepare_post_category_value( $value, $field );
		}

		return $field->get_value_entry_detail( $value, $currency, $use_text, $format, $media );
	}

	public static function get_product_fields( $form, $lead, $use_choice_text = false, $use_admin_label = false ) {
		$products = array();

		$product_info = null;
		// retrieve static copy of product info (only for 'real' entries)
		if ( ! rgempty( 'id', $lead ) ) {
			$product_info = gform_get_meta( rgar( $lead, 'id' ), "gform_product_info_{$use_choice_text}_{$use_admin_label}" );
		}

		// if no static copy, generate from form/lead info
		if ( ! $product_info ) {

			foreach ( $form['fields'] as $field ) {
				$id         = $field->id;
				$lead_value = RGFormsModel::get_lead_field_value( $lead, $field );

				$quantity_field = self::get_product_fields_by_type( $form, array( 'quantity' ), $id );
				$quantity       = sizeof( $quantity_field ) > 0 && ! RGFormsModel::is_field_hidden( $form, $quantity_field[0], array(), $lead ) ? RGFormsModel::get_lead_field_value( $lead, $quantity_field[0] ) : 1;

				switch ( $field->type ) {

					case 'product' :

						//ignore products that have been hidden by conditional logic
						$is_hidden = RGFormsModel::is_field_hidden( $form, $field, array(), $lead );
						if ( $is_hidden ) {
							break;
						}

						//if single product, get values from the multiple inputs
						if ( is_array( $lead_value ) ) {
							$product_quantity = sizeof( $quantity_field ) == 0 && ! $field->disableQuantity ? rgget( $id . '.3', $lead_value ) : $quantity;
							if ( empty( $product_quantity ) ) {
								break;
							}

							if ( ! rgar( $products, $id ) ) {
								$products[ $id ] = array();
							}

							$products[ $id ]['name']     = $use_admin_label && ! empty( $field->adminLabel ) ? $field->adminLabel : rgar( $lead_value, $id . '.1' );
							$products[ $id ]['price']    = rgar( $lead_value, $id . '.2' );
							$products[ $id ]['quantity'] = $product_quantity;
						} elseif ( ! empty( $lead_value ) ) {

							if ( empty( $quantity ) ) {
								break;
							}

							if ( ! rgar( $products, $id ) ) {
								$products[ $id ] = array();
							}

							$field_label = $use_admin_label && ! empty( $field->adminLabel ) ? $field->adminLabel : $field->label;

							if ( $field->inputType == 'price' ) {
								$name  = $field_label;
								$price = $lead_value;
							} else {
								list( $name, $price ) = explode( '|', $lead_value );

								if ( $use_choice_text ) {
									$name = RGFormsModel::get_choice_text( $field, $name );
								}

								/**
								 * Enables inclusion of the field label or admin label in the product name for choice based Product fields.
								 *
								 * @since 1.9.1
								 *
								 * @param bool $include_field_label Indicates if the label should be included in the product name. Default is false.
								 */
								$include_field_label = apply_filters( 'gform_product_info_name_include_field_label', false );
								if ( $include_field_label ) {
									$name = $field_label . " ({$name})";
								}
							}

							$products[ $id ]['name']     = $name;
							$products[ $id ]['price']    = $price;
							$products[ $id ]['quantity'] = $quantity;
							$products[ $id ]['options']  = array();
						}

						if ( isset( $products[ $id ] ) ) {
							$option_fields = self::get_product_fields_by_type( $form, array( 'option' ), $id );
							foreach ( $option_fields as $option_field ) {
								$option_value = RGFormsModel::get_lead_field_value( $lead, $option_field );
								$option_label = $use_admin_label && ! empty( $option_field->adminLabel ) ? $option_field->adminLabel : $option_field->label;
								if ( is_array( $option_value ) ) {
									foreach ( $option_value as $value ) {
										$option_info = self::get_option_info( $value, $option_field, $use_choice_text );
										if ( ! empty( $option_info ) ) {
											$products[ $id ]['options'][] = array(
												'id'           => $option_field->id,
												'field_label'  => rgobj( $option_field, 'label' ),
												'option_name'  => rgar( $option_info, 'name' ),
												'option_label' => $option_label . ': ' . rgar( $option_info, 'name' ),
												'price'        => rgar( $option_info, 'price' )
											);
										}
									}
								} elseif ( ! empty( $option_value ) ) {
									$option_info                  = self::get_option_info( $option_value, $option_field, $use_choice_text );
									$products[ $id ]['options'][] = array(
										'id'           => $option_field->id,
										'field_label'  => rgobj( $option_field, 'label' ),
										'option_name'  => rgar( $option_info, 'name' ),
										'option_label' => $option_label . ': ' . rgar( $option_info, 'name' ),
										'price'        => rgar( $option_info, 'price' )
									);
								}
							}

							if ( empty( $products[ $id ]['options'] ) && empty( $products[ $id ]['name'] ) && rgblank( $products[ $id ]['price'] ) ) {
								self::log_debug( __METHOD__ . "(): Product field #{$id} has no options, name, or price; removing." );
								unset( $products[ $id ] );
							}
						}
						break;
				}
			}

			$shipping_fields = GFAPI::get_fields_by_type( $form, array( 'shipping' ) );
			$shipping_price  = $shipping_name = $shipping_field_id = '';

			if ( ! empty( $shipping_fields ) && ! RGFormsModel::is_field_hidden( $form, $shipping_fields[0], array(), $lead ) ) {
				$shipping_price    = RGFormsModel::get_lead_field_value( $lead, $shipping_fields[0] );
				$shipping_name     = $use_admin_label && ! empty( $shipping_fields[0]->adminLabel ) ? $shipping_fields[0]->adminLabel : $shipping_fields[0]->label;
				$shipping_field_id = $shipping_fields[0]->id;
				if ( $shipping_fields[0]->inputType != 'singleshipping' && ! empty( $shipping_price ) ) {
					list( $shipping_method, $shipping_price ) = explode( '|', $shipping_price );
					if ( $use_choice_text ) {
						$shipping_method = RGFormsModel::get_choice_text( $shipping_fields[0], $shipping_method );
					}
					$shipping_name .= " ($shipping_method)";
				}
			}

			$shipping_price = self::to_number( $shipping_price, $lead['currency'] );

			$product_info = array(
				'products' => $products,
				'shipping' => array(
					'id'    => $shipping_field_id,
					'name'  => $shipping_name,
					'price' => $shipping_price
				)
			);

			/**
			 * Allows the product info used by add-ons and when generating the entry order summary table to be overridden.
			 *
			 * @since 1.5.2.8
			 *
			 * @param array $product_info The selected products, options, and shipping details for the current entry.
			 * @param array $form         The form object used to generate the current entry.
			 * @param array $lead         The current entry object.
			 */
			$product_info = gf_apply_filters( array( 'gform_product_info', $form['id'] ), $product_info, $form, $lead );

			// save static copy of product info (only for 'real' entries)
			if ( ! rgempty( 'id', $lead ) && ! empty( $product_info['products'] ) ) {
				gform_update_meta( $lead['id'], "gform_product_info_{$use_choice_text}_{$use_admin_label}", $product_info, $form['id'] );
			}
		}

		return $product_info;
	}

	public static function get_order_total( $form, $lead ) {

		$products = self::get_product_fields( $form, $lead, false );

		return self::get_total( $products );
	}

	public static function get_total( $products ) {

		$total = 0;
		foreach ( $products['products'] as $product ) {

			$price = self::to_number( $product['price'] );
			if ( is_array( rgar( $product, 'options' ) ) ) {
				foreach ( $product['options'] as $option ) {
					$price += self::to_number( $option['price'] );
				}
			}
			$quantity = self::to_number( $product['quantity'], GFCommon::get_currency() );
			$subtotal = $quantity * $price;
			$total += $subtotal;

		}

		$total += floatval( $products['shipping']['price'] );

		return $total;
	}

	public static function get_option_info( $value, $option, $use_choice_text ) {
		if ( empty( $value ) ) {
			return array();
		}

		list( $name, $price ) = explode( '|', $value );
		if ( $use_choice_text ) {
			$name = RGFormsModel::get_choice_text( $option, $name );
		}

		return array( 'name' => $name, 'price' => $price );
	}

	public static function gform_do_shortcode( $content ) {

		$is_ajax = false;
		$forms   = GFFormDisplay::get_embedded_forms( $content, $is_ajax );

		foreach ( $forms as $form ) {

			/**
			 * Determine if scripts and stylesheets should be printed or enqueued when processing form shortcodes after headers have been sent.
			 *
			 * @since 2.0
			 *
			 * @param bool  $disable_print_form_script Defaults to false.
			 * @param array $form                      The form object for the shortcode being processed.
			 * @param bool  $is_ajax                   Indicates if ajax was enabled on the shortcode.
			 */
			$disable_print_form_script = apply_filters( 'gform_disable_print_form_scripts', false, $form, $is_ajax );

			if ( headers_sent() && ! $disable_print_form_script ) {
				GFFormDisplay::print_form_scripts( $form, $is_ajax );
			} else {
				GFFormDisplay::enqueue_form_scripts( $form, $is_ajax );
			}
		}

		return do_shortcode( $content );
	}

	/**
	 * Determines if the supplied entry is spam.
	 *
	 * @since 2.4.17
	 *
	 * @param array $entry The entry currently being processed.
	 * @param array $form  The form currently being processed.
	 *
	 * @return bool
	 */
	public static function is_spam_entry( $entry, $form ) {
		$form_id   = absint( $form['id'] );
		$use_cache = class_exists( 'GFFormDisplay' );

		if ( $use_cache ) {
			$is_spam = rgars( GFFormDisplay::$submission, $form_id . '/is_spam' );

			if ( is_bool( $is_spam ) ) {
				return $is_spam;
			}
		}

		$is_spam = false;

		if ( self::akismet_enabled( $form_id ) ) {
			$is_spam = self::is_akismet_spam( $form, $entry );
			self::log_debug( __METHOD__ . '(): Result from Akismet: ' . json_encode( $is_spam ) );
		}

		if ( has_filter( 'gform_entry_is_spam' ) || has_filter( "gform_entry_is_spam_{$form_id}" ) ) {

			/**
			 * Allows submissions to be flagged as spam by custom methods.
			 *
			 * @since 1.8.17
			 * @since 2.4.17 Moved from GFFormDisplay::handle_submission().
			 *
			 * @param bool  $is_spam Indicates if the submission has been flagged as spam.
			 * @param array $form    The form currently being processed.
			 * @param array $entry   The entry currently being processed.
			 */
			$is_spam = gf_apply_filters( array( 'gform_entry_is_spam', $form_id ), $is_spam, $form, $entry );
			self::log_debug( __METHOD__ . '(): Result from gform_entry_is_spam filter: ' . json_encode( $is_spam ) );

		}

		$log_is_spam = $is_spam ? 'Yes' : 'No';
		self::log_debug( __METHOD__ . "(): Is submission considered spam? {$log_is_spam}." );

		if ( $use_cache ) {
			GFFormDisplay::$submission[ $form_id ]['is_spam'] = $is_spam;
		}

		return $is_spam;
	}

	public static function spam_enabled( $form_id ) {
		$spam_enabled = self::akismet_enabled( $form_id ) || has_filter( 'gform_entry_is_spam' ) || has_filter( "gform_entry_is_spam_{$form_id}" );

		return $spam_enabled;
	}

	public static function has_akismet() {
		$akismet_exists = function_exists( 'akismet_http_post' ) || method_exists( 'Akismet', 'http_post' );

		return $akismet_exists;
	}

	public static function akismet_enabled( $form_id ) {

		if ( ! self::has_akismet() ) {
			return false;
		}

		// if no option is set, leave akismet enabled; otherwise, use option value true/false
		$enabled_by_setting = get_option( 'rg_gforms_enable_akismet' ) === false ? true : get_option( 'rg_gforms_enable_akismet' ) == true;
		$enabled_by_filter  = gf_apply_filters( array( 'gform_akismet_enabled', $form_id ), $enabled_by_setting );

		return $enabled_by_filter;

	}

	public static function is_akismet_spam( $form, $lead ) {

		global $akismet_api_host, $akismet_api_port;

		$fields = self::get_akismet_fields( $form, $lead );

		// Submitting info to Akismet
		if ( defined( 'AKISMET_VERSION' ) && AKISMET_VERSION < 3.0 ) {
			//Akismet versions before 3.0
			$response = akismet_http_post( $fields, $akismet_api_host, '/1.1/comment-check', $akismet_api_port );
		} else {
			$response = Akismet::http_post( $fields, 'comment-check' );
		}
		$is_spam = trim( rgar( $response, 1 ) ) == 'true';

		return $is_spam;
	}

	public static function mark_akismet_spam( $form, $lead, $is_spam ) {

		global $akismet_api_host, $akismet_api_port;

		$fields = self::get_akismet_fields( $form, $lead );
		$as     = $is_spam ? 'spam' : 'ham';

		// Submitting info to Akismet
		if ( defined( 'AKISMET_VERSION' ) && AKISMET_VERSION < 3.0 ) {
			//Akismet versions before 3.0
			akismet_http_post( $fields, $akismet_api_host, '/1.1/submit-' . $as, $akismet_api_port );
		} else {
			Akismet::http_post( $fields, 'submit-' . $as );
		}
	}

	private static function get_akismet_fields( $form, $lead ) {

		$is_form_editor  = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		// Gathering Akismet information
		$akismet_info                         = array();
		$akismet_info['comment_type']         = 'gravity_form';
		$akismet_info['comment_author']       = self::get_akismet_field( 'name', $form, $lead );
		$akismet_info['comment_author_email'] = self::get_akismet_field( 'email', $form, $lead );
		$akismet_info['comment_author_url']   = self::get_akismet_field( 'website', $form, $lead );
		$akismet_info['comment_content']      = self::get_akismet_field( 'textarea', $form, $lead );
		$akismet_info['contact_form_subject'] = $form['title'];
		$akismet_info['comment_author_IP']    = $lead['ip'];
		$akismet_info['permalink']            = $lead['source_url'];
		$akismet_info['user_ip']              = preg_replace( '/[^0-9., ]/', '', $lead['ip'] );
		$akismet_info['user_agent']           = $lead['user_agent'];
		$akismet_info['referrer']             = $is_admin ? '' : $_SERVER['HTTP_REFERER'];
		$akismet_info['blog']                 = get_option( 'home' );

		$akismet_info = gf_apply_filters( array( 'gform_akismet_fields', $form['id'] ), $akismet_info, $form, $lead );

		return http_build_query( $akismet_info );
	}

	private static function get_akismet_field( $field_type, $form, $lead ) {
		$fields = GFAPI::get_fields_by_type( $form, array( $field_type ) );
		if ( empty( $fields ) ) {
			return '';
		}

		$value = RGFormsModel::get_lead_field_value( $lead, $fields[0] );
		switch ( $field_type ) {
			case 'name' :
				$value = GFCommon::get_lead_field_display( $fields[0], $value );
				break;
		}

		return $value;
	}

	/**
	 * Get the placeholder to use for the radio button field other choice.
	 *
	 * @param null|GF_Field_Radio $field Null or the Field currently being prepared for display or being validated.
	 *
	 * @return string
	 */
	public static function get_other_choice_value( $field = null ) {
		$placeholder = esc_html__( 'Other', 'gravityforms' );

		/**
		 * Filter the default placeholder for the radio button field other choice.
		 *
		 * @since 2.1.1.6 Added the $field parameter.
		 * @since Unknown
		 *
		 * @param string              $placeholder The placeholder to be filtered. Defaults to "Other".
		 * @param null|GF_Field_Radio $field       Null or the Field currently being prepared for display or being validated.
		 */
		$placeholder = apply_filters( 'gform_other_choice_value', $placeholder, $field );

		return $placeholder;
	}

	public static function get_browser_class() {
		global $is_lynx, $is_gecko, $is_IE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone, $post;

		$classes = array();

		//adding browser related class
		if ( $is_lynx ) {
			$classes[] = 'gf_browser_lynx';
		} else if ( $is_gecko ) {
			$classes[] = 'gf_browser_gecko';
		} else if ( $is_opera ) {
			$classes[] = 'gf_browser_opera';
		} else if ( $is_NS4 ) {
			$classes[] = 'gf_browser_ns4';
		} else if ( $is_safari ) {
			$classes[] = 'gf_browser_safari';
		} else if ( $is_chrome ) {
			$classes[] = 'gf_browser_chrome';
		} else if ( $is_IE ) {
			$classes[] = 'gf_browser_ie';
		} else {
			$classes[] = 'gf_browser_unknown';
		}


		//adding IE version
		if ( $is_IE ) {
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 6' ) !== false ) {
				$classes[] = 'gf_browser_ie6';
			} else if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 7' ) !== false ) {
				$classes[] = 'gf_browser_ie7';
			}
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 8' ) !== false ) {
				$classes[] = 'gf_browser_ie8';
			}
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 9' ) !== false ) {
				$classes[] = 'gf_browser_ie9';
			}
		}

		if ( $is_iphone ) {
			$classes[] = 'gf_browser_iphone';
		}

		return implode( ' ', $classes );
	}

	public static function create_post( $form, &$lead ) {
		$disable_post = gf_apply_filters( array( 'gform_disable_post_creation', $form['id'] ), false, $form, $lead );
		$post_id      = 0;
		if ( ! $disable_post ) {
			//creates post if the form has any post fields
			$post_id = RGFormsModel::create_post( $form, $lead );
		}

		return $post_id;
	}

	public static function evaluate_conditional_logic( $logic, $form, $lead ) {

		if ( ! $logic || ! is_array( rgar( $logic, 'rules' ) ) ) {
			return true;
		}

		$entry_meta_keys = array_keys( GFFormsModel::get_entry_meta( $form['id'] ) );
		$match_count     = 0;
		if ( is_array( $logic['rules'] ) ) {
			foreach ( $logic['rules'] as $rule ) {

				if ( in_array( $rule['fieldId'], $entry_meta_keys ) ) {
					$is_value_match = GFFormsModel::is_value_match( rgar( $lead, $rule['fieldId'] ), $rule['value'], $rule['operator'], null, $rule, $form );
				} else {
					$source_field   = GFFormsModel::get_field( $form, $rule['fieldId'] );
					$field_value    = empty( $lead ) ? GFFormsModel::get_field_value( $source_field, array() ) : GFFormsModel::get_lead_field_value( $lead, $source_field );
					$is_value_match = GFFormsModel::is_value_match( $field_value, $rule['value'], $rule['operator'], $source_field, $rule, $form );
				}

				if ( $is_value_match ) {
					$match_count ++;
				}
			}
		}

		$do_action = ( $logic['logicType'] == 'all' && $match_count == sizeof( $logic['rules'] ) ) || ( $logic['logicType'] == 'any' && $match_count > 0 );

		return $do_action;
	}

	public static function get_card_types() {
		$cards = array(

			array(
				'name'     => 'American Express',
				'slug'     => 'amex',
				'lengths'  => '15',
				'prefixes' => '34,37',
				'checksum' => true,
			),
			array(
				'name'     => 'Discover',
				'slug'     => 'discover',
				'lengths'  => '16',
				'prefixes' => '6011,622,64,65',
				'checksum' => true,
			),
			array(
				'name'     => 'MasterCard',
				'slug'     => 'mastercard',
				'lengths'  => '16',
				'prefixes' => '51,52,53,54,55,22,23,24,25,26,270,271,272',
				'checksum' => true,
			),
			array(
				'name'     => 'Visa',
				'slug'     => 'visa',
				'lengths'  => '13,16',
				'prefixes' => '4,417500,4917,4913,4508,4844',
				'checksum' => true,
			),
			array(
				'name'     => 'JCB',
				'slug'     => 'jcb',
				'lengths'  => '16',
				'prefixes' => '35',
				'checksum' => true,
			),
			array(
				'name'     => 'Maestro',
				'slug'     => 'maestro',
				'lengths'  => '12,13,14,15,16,18,19',
				'prefixes' => '5018,5020,5038,6304,6759,6761',
				'checksum' => true,
			),
		);

		$cards = apply_filters( 'gform_creditcard_types', $cards );

		return $cards;
	}

	public static function get_card_type( $number ) {

		//removing spaces from number
		$number = str_replace( ' ', '', $number );

		if ( empty( $number ) ) {
			return false;
		}

		$cards = self::get_card_types();

		$matched_card = false;
		foreach ( $cards as $card ) {
			if ( self::matches_card_type( $number, $card ) ) {
				$matched_card = $card;
				break;
			}
		}

		if ( $matched_card && $matched_card['checksum'] && ! self::is_valid_card_checksum( $number ) ) {
			$matched_card = false;
		}

		return $matched_card ? $matched_card : false;

	}

	private static function matches_card_type( $number, $card ) {

		//checking prefix
		$prefixes       = explode( ',', $card['prefixes'] );
		$matches_prefix = false;
		foreach ( $prefixes as $prefix ) {
			if ( preg_match( "|^{$prefix}|", $number ) ) {
				$matches_prefix = true;
				break;
			}
		}

		//checking length
		$lengths        = explode( ',', $card['lengths'] );
		$matches_length = false;
		foreach ( $lengths as $length ) {
			if ( strlen( $number ) == absint( $length ) ) {
				$matches_length = true;
				break;
			}
		}

		return $matches_prefix && $matches_length;

	}

	private static function is_valid_card_checksum( $number ) {
		$checksum   = 0;
		$num        = 0;
		$multiplier = 1;

		// Process each character starting at the right
		for ( $i = strlen( $number ) - 1; $i >= 0; $i -- ) {

			//Multiply current digit by multiplier (1 or 2)
			$num = $number[ $i ] * $multiplier;

			// If the result is in greater than 9, add 1 to the checksum total
			if ( $num >= 10 ) {
				$checksum ++;
				$num -= 10;
			}

			//Update checksum
			$checksum += $num;

			//Update multiplier
			$multiplier = $multiplier == 1 ? 2 : 1;
		}

		return $checksum % 10 == 0;

	}

	public static function is_wp_version( $min_version ) {
		return ! version_compare( get_bloginfo( 'version' ), "{$min_version}.dev1", '<' );
	}

	/**
	 * Checks if the logging plugin is active.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @used-by GFSettings::gravityforms_settings_page()
	 *
	 * @return bool If the logging plugin is active.
	 */
	public static function is_logging_plugin_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// In some scenarios, is_plugin_active() will return true when plugin file has been manually deleted.
		return is_plugin_active( 'gravityformslogging/logging.php' ) && file_exists( trailingslashit( WP_PLUGIN_DIR ) . 'gravityformslogging/logging.php' );

	}

	public static function add_categories_as_choices( $field, $value ) {

		$choices         = $inputs = array();
		$is_post         = isset( $_POST['gform_submit'] );
		$has_placeholder = $field->categoryInitialItemEnabled && RGFormsModel::get_input_type( $field ) == 'select';

		if ( $has_placeholder ) {
			$choices[] = array( 'text' => $field->categoryInitialItem, 'value' => '', 'isSelected' => true );
		}

		$display_all = $field->displayAllCategories;

		$args = array( 'hide_empty' => false, 'orderby' => 'name', 'taxonomy' => 'category' );

		if ( ! $display_all ) {
			foreach ( $field->choices as $field_choice_to_include ) {
				$args['include'][] = $field_choice_to_include['value'];
			}
		}

		$args  = gf_apply_filters( array( 'gform_post_category_args', $field->id ), $args, $field );
		$terms = get_terms( $args['taxonomy'], $args );

		$terms_copy = unserialize( serialize( $terms ) ); // deep copy the terms to avoid repeating GFCategoryWalker on previously cached terms.
		$walker     = new GFCategoryWalker();
		$categories = $walker->walk( $terms_copy, 0, array( 0 ) ); // 3rd parameter prevents notices triggered by $walker::display_element() function which checks $args[0]

		foreach ( $categories as $category ) {
			if ( $display_all ) {
				$selected  = $value == $category->term_id ||
				             (
					             empty( $value ) &&
					             get_option( 'default_category' ) == $category->term_id &&
					             RGFormsModel::get_input_type( $field ) == 'select' && // only preselect default category on select fields
					             ! $is_post &&
					             ! $has_placeholder
				             );
				$choices[] = array(
					'text'       => $category->name,
					'value'      => $category->term_id,
					'isSelected' => $selected
				);
			} else {
				foreach ( $field->choices as $field_choice ) {
					if ( $field_choice['value'] == $category->term_id ) {
						$choices[] = array( 'text' => $category->name, 'value' => $category->term_id );
						break;
					}
				}
			}
		}

		if ( empty( $choices ) ) {
			$choices[] = array( 'text' => 'You must select at least one category.', 'value' => '' );
		}

		$field->choices = $choices;

		$is_form_editor  = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$form_id = $is_admin ? rgget( 'id' ) : $field->formId;

		/**
		 * Allows you to filter (modify) the post category choices when using post fields.
		 *
		 * @param GF_Field $field   The category choices field.
		 * @param int      $form_id The current form ID.
		 */
		$field->choices = gf_apply_filters( array(
			'gform_post_category_choices',
			$form_id,
			$field->id
		), $field->choices, $field, $form_id );

		if ( $field->get_input_type() == 'checkbox' ) {
			$choice_number = 1;
			foreach ( $field->choices as $choice ) {

				if ( $choice_number % 10 == 0 ) {
					//hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
					$choice_number ++;
				}

				$input_id = $field->id . '.' . $choice_number;
				$inputs[] = array( 'id' => $input_id, 'label' => $choice['text'], 'name' => '' );
				$choice_number ++;
			}

			$field->inputs = $inputs;
		}

		return $field;
	}

	public static function prepare_post_category_value( $value, $field, $mode = 'entry_detail' ) {

		if ( ! is_array( $value ) ) {
			$value = explode( ',', $value );
		}

		$cat_names = array();
		$cat_ids   = array();
		foreach ( $value as $cat_string ) {
			$ary      = explode( ':', $cat_string );
			$cat_name = count( $ary ) > 0 ? $ary[0] : '';
			$cat_id   = count( $ary ) > 1 ? $ary[1] : $ary[0];

			if ( ! empty( $cat_name ) ) {
				$cat_names[] = $cat_name;
			}

			if ( ! empty( $cat_id ) ) {
				$cat_ids[] = $cat_id;
			}
		}

		sort( $cat_names );

		switch ( $mode ) {
			case 'entry_list':
				$value = self::implode_non_blank( ', ', $cat_names );
				break;
			case 'entry_detail':
				$value = RGFormsModel::get_input_type( $field ) == 'checkbox' ? $cat_names : self::implode_non_blank( ', ', $cat_names );
				break;
			case 'conditional_logic':
				$value = array_values( $cat_ids );
				break;
		}

		return $value;
	}

	public static function calculate( $field, $form, $lead ) {

		$number_format = $field->numberFormat;

		if ( empty( $number_format ) ) {
			$currency      = RGCurrency::get_currency( rgar( $lead, 'currency' ) );
			$number_format = self::is_currency_decimal_dot( $currency ) ? 'decimal_dot' : 'decimal_comma';
		}

		$formula = (string) apply_filters( 'gform_calculation_formula', $field->calculationFormula, $field, $form, $lead );

		// replace multiple spaces and new lines with single space
		// @props: http://stackoverflow.com/questions/3760816/remove-new-lines-from-string
		$formula = trim( preg_replace( '/\s+/', ' ', $formula ) );

		preg_match_all( '/{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/mi', $formula, $matches, PREG_SET_ORDER );

		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {

				list( $text, $input_id ) = $match;
				$value   = self::get_calculation_value( $input_id, $form, $lead, $number_format );
				$value   = apply_filters( 'gform_merge_tag_value_pre_calculation', $value, $input_id, rgar( $match, 4 ), $field, $form, $lead );
				$formula = str_replace( $text, $value, $formula );

			}
		}

		$result = false;

		if ( preg_match( '/^[0-9 -\/*\(\)]+$/', $formula ) ) {
			$prev_reporting_level = error_reporting( 0 );
			try {
				$result = eval( "return {$formula};" );
        		} catch ( ParseError $e ) {
				GFCommon::log_debug( __METHOD__ . sprintf( '(): Formula could not be parsed: "%s".', $e->getMessage() ) );
				$result = 0;
			}
			error_reporting( $prev_reporting_level );
		}

		$result = apply_filters( 'gform_calculation_result', $result, $formula, $field, $form, $lead );

		if ( ! $result || ! is_numeric( $result ) || ! is_finite( $result ) ) {
			GFCommon::log_debug( __METHOD__ . '(): No result or non-numeric result. Returning zero instead.' );
			$result = 0;
		}

		return $result;
	}

	public static function round_number( $number, $rounding ) {
		if ( is_numeric( $rounding ) && $rounding >= 0 ) {
			$number = round( $number, $rounding );
		}

		return $number;
	}

	public static function get_calculation_value( $field_id, $form, $lead, $number_format = '' ) {

		$filters = array( 'price', 'value', '' );
		$value   = false;

		$field            = RGFormsModel::get_field( $form, $field_id );
		if ( empty( $field ) ) {
			//return 0 if fields does not belong to form
			return 0;
		}

		$is_pricing_field = $field ? self::has_currency_value( $field ) : false;

		if ( $field && $field->numberFormat ) {
			$number_format = $field->numberFormat;
		} elseif ( empty( $number_format ) ) {
			$number_format = 'decimal_dot';
		}

		foreach ( $filters as $filter ) {
			if ( is_numeric( $value ) ) {
				//value found, exit loop
				break;
			}

			$replaced_value = GFCommon::replace_variables( "{:{$field_id}:$filter}", $form, $lead );

			if ( $is_pricing_field ) {
				$value = self::to_number( $replaced_value );
			} else {
				$value = self::clean_number( $replaced_value, $number_format );
			}

		}

		if ( ! $value || ! is_numeric( $value ) ) {
			GFCommon::log_debug( "GFCommon::get_calculation_value(): No value or non-numeric value available for field #{$field_id}. Returning zero instead." );
			$value = 0;
		}

		return $value;
	}

	public static function has_currency_value( $field ) {
		$has_currency = self::is_pricing_field( $field->type ) || rgobj( $field, 'numberFormat' ) == 'currency';

		return $has_currency;
	}

	public static function conditional_shortcode( $attributes, $content = null ) {

		extract(
			shortcode_atts(
				array(
					'merge_tag' => '',
					'condition' => '',
					'value'     => '',
				), $attributes
			)
		);

		return RGFormsModel::matches_operation( $merge_tag, $value, $condition ) ? do_shortcode( $content ) : '';

	}

	public static function is_valid_for_calcuation( $field ) {

		$supported_input_types   = array(
			'text',
			'select',
			'number',
			'checkbox',
			'radio',
			'hidden',
			'singleproduct',
			'price',
			'hiddenproduct',
			'calculation',
			'singleshipping'
		);
		$unsupported_field_types = array( 'category' );
		$input_type              = RGFormsModel::get_input_type( $field );

		return in_array( $input_type, $supported_input_types ) && ! in_array( $input_type, $unsupported_field_types );
	}

	public static function log_error( $message ) {
		if ( class_exists( 'GFLogging' ) ) {
			GFLogging::include_logger();
			GFLogging::log_message( 'gravityforms', $message, KLogger::ERROR );
		}
	}

	public static function log_debug( $message ) {
		if ( class_exists( 'GFLogging' ) ) {
			GFLogging::include_logger();
			GFLogging::log_message( 'gravityforms', $message, KLogger::DEBUG );
		}
	}

	/**
	 * Log the remote request response.
	 *
	 * @since 2.2.2.1
	 *
	 * @param WP_Error|array $response The remote request response or WP_Error on failure.
	 */
	public static function log_remote_response( $response ) {
		if ( is_wp_error( $response ) || isset( $_GET['gform_debug'] ) ) {
			self::log_error( __METHOD__ . '(): ' . print_r( $response, 1 ) );
		} else {
			self::log_debug( sprintf( '%s(): code: %s; body: %s', __METHOD__, wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) ) );
		}
	}

	public static function echo_if( $condition, $text ) {
		_deprecated_function( 'GFCommon::echo_if() is deprecated', '1.9.9', 'Use checked() or selected() instead.' );

		switch ( $text ) {
			case 'checked':
				$text = 'checked="checked"';
				break;
			case 'selected':
				$text = 'selected="selected"';
		}

		echo $condition ? $text : '';
	}

	/**
	 * Outputs the gf_global and returns either the gf_global var declaration or the array containing the gf_global values.
	 *
	 *
	 * @since 2.4.7		Added the $return_array parameter
	 * @since unknown
	 *
	 * @param bool $echo         If true, outputs the inline gf_global var declaration.
	 * @param bool $return_array If true, returns the array containing the gf_global values.
	 *
	 * @return array|string
	 */
	public static function gf_global( $echo = true, $return_array = false ) {
		$gf_global                       = array();
		$gf_global['gf_currency_config'] = RGCurrency::get_currency( GFCommon::get_currency() );
		$gf_global['base_url']           = GFCommon::get_base_url();
		$gf_global['number_formats']     = array();
		$gf_global['spinnerUrl']         = GFCommon::get_base_url() . '/images/spinner.gif';

		$gf_global_json = 'var gf_global = ' . json_encode( $gf_global ) . ';';

		if ( ! $echo ) {
			return $return_array ? $gf_global : $gf_global_json;
		}

		echo $gf_global_json;
	}

	public static function gf_vars( $echo = true ) {
		$gf_vars                            = array();
		$gf_vars['active']                  = esc_attr__( 'Active', 'gravityforms' );
		$gf_vars['inactive']                = esc_attr__( 'Inactive', 'gravityforms' );
		$gf_vars['save']                    = esc_html__( 'Save', 'gravityforms' );
		$gf_vars['update']                  = esc_html__( 'Update', 'gravityforms' );
		$gf_vars['previousLabel']           = esc_html__( 'Previous', 'gravityforms' );
		$gf_vars['selectFormat']            = esc_html__( 'Select a format', 'gravityforms' );
		$gf_vars['editToViewAll']           = esc_html__( '5 of %d items shown. Edit field to view all', 'gravityforms' );
		$gf_vars['enterValue']              = esc_html__( 'Enter a value', 'gravityforms' );
		$gf_vars['formTitle']               = esc_html__( 'Untitled Form', 'gravityforms' );
		$gf_vars['formDescription']         = esc_html__( 'We would love to hear from you! Please fill out this form and we will get in touch with you shortly.', 'gravityforms' );
		$gf_vars['formConfirmationMessage'] = esc_html__( 'Thanks for contacting us! We will get in touch with you shortly.', 'gravityforms' );
		$gf_vars['buttonText']              = esc_html__( 'Submit', 'gravityforms' );
		$gf_vars['loading']                 = esc_html__( 'Loading...', 'gravityforms' );
		$gf_vars['thisFieldIf']             = esc_html__( 'this field if', 'gravityforms' );
		$gf_vars['thisSectionIf']           = esc_html__( 'this section if', 'gravityforms' );
		$gf_vars['thisPage']                = esc_html__( 'this page', 'gravityforms' );
		$gf_vars['thisFormButton']          = esc_html__( 'this form button if', 'gravityforms' );
		$gf_vars['show']                    = esc_html__( 'Show', 'gravityforms' );
		$gf_vars['hide']                    = esc_html__( 'Hide', 'gravityforms' );
		$gf_vars['all']                     = esc_html( _x( 'All', 'Conditional Logic', 'gravityforms' ) );
		$gf_vars['any']                     = esc_html( _x( 'Any', 'Conditional Logic', 'gravityforms' ) );
		$gf_vars['ofTheFollowingMatch']     = esc_html__( 'of the following match:', 'gravityforms' );
		$gf_vars['is']                      = esc_html__( 'is', 'gravityforms' );
		$gf_vars['isNot']                   = esc_html__( 'is not', 'gravityforms' );
		$gf_vars['greaterThan']             = esc_html__( 'greater than', 'gravityforms' );
		$gf_vars['lessThan']                = esc_html__( 'less than', 'gravityforms' );
		$gf_vars['contains']                = esc_html__( 'contains', 'gravityforms' );
		$gf_vars['startsWith']              = esc_html__( 'starts with', 'gravityforms' );
		$gf_vars['endsWith']                = esc_html__( 'ends with', 'gravityforms' );
		$gf_vars['emptyChoice']             = wp_strip_all_tags( __( 'Empty (no choices selected)', 'gravityforms' ) );

		$gf_vars['thisConfirmation']                 = esc_html__( 'Use this confirmation if', 'gravityforms' );
		$gf_vars['thisNotification']                 = esc_html__( 'Send this notification if', 'gravityforms' );
		$gf_vars['confirmationSave']                 = esc_html__( 'Save', 'gravityforms' );
		$gf_vars['confirmationSaving']               = esc_html__( 'Saving...', 'gravityforms' );
		$gf_vars['confirmationAreYouSure']           = __( 'Are you sure you wish to cancel these changes?', 'gravityforms' );
		$gf_vars['confirmationIssueSaving']          = __( 'There was an issue saving this confirmation.', 'gravityforms' );
		$gf_vars['confirmationConfirmDelete']        = __( 'Are you sure you wish to delete this confirmation?', 'gravityforms' );
		$gf_vars['confirmationIssueDeleting']        = __( 'There was an issue deleting this confirmation.', 'gravityforms' );
		$gf_vars['confirmationConfirmDiscard']       = __( 'There are unsaved changes to the current confirmation. Would you like to discard these changes?', 'gravityforms' );
		$gf_vars['confirmationDefaultName']          = __( 'Untitled Confirmation', 'gravityforms' );
		$gf_vars['confirmationDefaultMessage']       = __( 'Thanks for contacting us! We will get in touch with you shortly.', 'gravityforms' );
		$gf_vars['confirmationInvalidPageSelection'] = __( 'Please select a page.', 'gravityforms' );
		$gf_vars['confirmationInvalidRedirect']      = __( 'Please enter a URL.', 'gravityforms' );
		$gf_vars['confirmationInvalidName']          = __( 'Please enter a confirmation name.', 'gravityforms' );
		$gf_vars['confirmationDeleteField']          = __( "Warning! Deleting this field will also delete all entry data associated with it. 'Cancel' to stop. 'OK' to delete.", 'gravityforms' );

		$gf_vars['conditionalLogicDependency']           = __( "Warning! This form contains conditional logic dependent upon this field. Deleting this field will deactivate those conditional logic rules and also delete all entry data associated with the field. 'OK' to delete, 'Cancel' to abort.", 'gravityforms' );
		$gf_vars['conditionalLogicDependencyChoice']     = __( "This form contains conditional logic dependent upon this choice. Are you sure you want to delete this choice? 'OK' to delete, 'Cancel' to abort.", 'gravityforms' );
		$gf_vars['conditionalLogicDependencyChoiceEdit'] = __( "This form contains conditional logic dependent upon this choice. Are you sure you want to modify this choice? 'OK' to delete, 'Cancel' to abort.", 'gravityforms' );
		$gf_vars['conditionalLogicDependencyAdminOnly']  = __( "This form contains conditional logic dependent upon this field. Are you sure you want to mark this field as Admin Only? 'OK' to confirm, 'Cancel' to abort.", 'gravityforms' );

		$gf_vars['mergeTagsTooltip'] = '<h6>' . esc_html__( 'Merge Tags', 'gravityforms' ) . '</h6>' . esc_html__( 'Merge tags allow you to dynamically populate submitted field values in your form content wherever this merge tag icon is present.', 'gravityforms' );

		$gf_vars['baseUrl']              = GFCommon::get_base_url();
		$gf_vars['gf_currency_config']   = RGCurrency::get_currency( GFCommon::get_currency() );
		$gf_vars['otherChoiceValue']     = GFCommon::get_other_choice_value();
		$gf_vars['isFormTrash']          = false;
		$gf_vars['currentlyAddingField'] = false;
		$gf_vars['visibilityOptions']    = GFCommon::get_visibility_options();

		$gf_vars['addFieldFilter']    = esc_html__( 'Add a condition', 'gravityforms' );
		$gf_vars['removeFieldFilter'] = esc_html__( 'Remove a condition', 'gravityforms' );
		$gf_vars['filterAndAny']      = esc_html__( 'Include results if {0} match:', 'gravityforms' );

		$gf_vars['customChoices']     = esc_html__( 'Custom Choices', 'gravityforms' );
		$gf_vars['predefinedChoices'] = esc_html__( 'Predefined Choices', 'gravityforms' );

		if ( is_admin() && rgget( 'id' ) ) {

			$form                 = RGFormsModel::get_form_meta( rgget( 'id' ) );
			$gf_vars['mergeTags'] = GFCommon::get_merge_tags( $form['fields'], '', false );

			$address_field                 = new GF_Field_Address();
			$gf_vars['addressTypes']       = $address_field->get_address_types( $form['id'] );
			$gf_vars['defaultAddressType'] = $address_field->get_default_address_type( $form['id'] );

		}

		$gf_vars_json = 'var gf_vars = ' . json_encode( $gf_vars ) . ';';

		if ( ! $echo ) {
			return $gf_vars_json;
		} else {
			echo $gf_vars_json;
		}
	}

	public static function is_bp_active() {
		return defined( 'BP_VERSION' ) ? true : false;
	}

	public static function add_message( $message, $is_error = false ) {
		if ( $is_error ) {
			self::$errors[] = $message;
		} else {
			self::$messages[] = $message;
		}
	}

	public static function add_error_message( $message ) {
		self::add_message( $message, true );
	}

	/**
	 * Add a dismissible message to the array of dismissible messages.
	 *
	 * @param string            $text
	 * @param string            $key
	 * @param string            $type
	 * @param string|array|bool $capabilities A string containing a capability. Or an array or capabilities. Or FALSE for no capability check.
	 * @param bool              $sticky       Whether to keep displaying the message until it's dismissed.
	 * @param string|null       $page         The page on which to display the sticky message. NULL will display on all pages available.
	 *
	 * @since 2.0
	 */
	public static function add_dismissible_message( $text, $key, $type = 'warning', $capabilities = false, $sticky = false, $page = null ) {
		$message['type']         = $type;
		$message['text']         = $text;
		$message['key']          = sanitize_key( $key );
		$message['capabilities'] = $capabilities;
		$message['page']         = $page;

		if ( $sticky ) {
			$sticky_messages         = get_option( 'gform_sticky_admin_messages', array() );
			$sticky_messages[ $key ] = $message;
			update_option( 'gform_sticky_admin_messages', $sticky_messages );
		} else {
			self::$dismissible_messages[] = $message;
		}
	}

	/**
	 * Remove a dismissible message from the array of sticky dismissible messages.
	 *
	 * @param string $key
	 *
	 * @since 2.0.2.3
	 */
	public static function remove_dismissible_message( $key ) {
		$key = sanitize_key( $key );
		$sticky_messages = get_option( 'gform_sticky_admin_messages', array() );
		foreach ( $sticky_messages as $sticky_key => $sticky_message ) {
			if ( $key == sanitize_key( $sticky_message['key'] ) ) {
				unset( $sticky_messages[ $sticky_key ] );
				update_option( 'gform_sticky_admin_messages', $sticky_messages );
				break;
			}
		}
	}

	public static function display_admin_message( $errors = false, $messages = false ) {

		if ( ! $errors ) {
			$errors = self::$errors;
		}

		if ( ! $messages ) {
			$messages = self::$messages;
		}

		$errors   = apply_filters( 'gform_admin_error_messages', $errors );
		$messages = apply_filters( 'gform_admin_messages', $messages );

		if ( ! empty( $errors ) ) {
			?>
			<div class="error below-h2">
				<?php if ( count( $errors ) > 1 ) { ?>
					<ul style="margin: 0.5em 0 0; padding: 2px;">
						<li><?php echo implode( '</li><li>', $errors ); ?></li>
					</ul>
				<?php } else { ?>
					<p><?php echo $errors[0]; ?></p>
				<?php } ?>
			</div>
			<?php
		} else if ( ! empty( $messages ) ) {
			?>
			<div id="message" class="updated below-h2">
				<?php if ( count( $messages ) > 1 ) { ?>
					<ul style="margin: 0.5em 0 0; padding: 2px;">
						<li><?php echo implode( '</li><li>', $messages ); ?></li>
					</ul>
				<?php } else { ?>
					<p><strong><?php echo $messages[0]; ?></strong></p>
				<?php } ?>
			</div>
			<?php
		}

	}

	/**
	 * Outputs dismissible messages on the page.
	 *
	 * @param bool        $messages
	 * @param string|null $page Defaults to current Gravity Forms page from GFForms::get_page().
	 *
	 * @since 2.0
	 */
	public static function display_dismissible_message( $messages = false, $page = null ) {

		if ( ! $messages ) {
			$messages        = self::$dismissible_messages;
			$sticky_messages = get_option( 'gform_sticky_admin_messages', array() );
			$messages        = array_merge( $messages, $sticky_messages );
			$messages        = array_values( $messages );
		}

		if ( empty( $page ) ) {
			$page = GFForms::get_page();
		}

		if ( ! empty( $messages ) ) {
			foreach ( $messages as $message ) {
				if ( isset( $sticky_messages[ $message['key'] ] ) && isset( $message['page'] ) && $message['page'] && $page !== $message['page'] ) {
					continue;
				}

				if ( empty( $message['page'] ) && $page == 'site-wide' ) {
					// Prevent double display on GF pages
					continue;
				}

				if ( empty( $message['key'] ) || self::is_message_dismissed( $message['key'] ) ) {
					continue;
				}

				if ( isset( $message['capabilities'] ) && $message['capabilities'] && ! GFCommon::current_user_can_any( $message['capabilities'] ) ) {
					continue;
				}

				$class = in_array( $message['type'], array(
					'warning',
					'error',
					'updated',
					'success',
				) ) ? $message['type'] : 'error';
				?>
				<div class="notice below-h1 notice-<?php echo $class; ?> is-dismissible"
				     data-gf_dismissible_key="<?php echo $message['key'] ?>"
				     data-gf_dismissible_nonce="<?php echo wp_create_nonce( 'gf_dismissible_nonce' ) ?>">
					<p>
						<?php echo $message['text']; ?>
					</p>
				</div>
				<?php
			}
			?>
			<script>
				jQuery(document).ready(function ($) {
					$(document).on("click", ".notice-dismiss", function () {
						var $div = $(this).closest('div.notice');
						if ($div.length > 0) {
							var messageKey = $div.data('gf_dismissible_key');
							var nonce = $div.data('gf_dismissible_nonce');
							if (messageKey) {
								jQuery.ajax({
									url: ajaxurl,
									data: {
										action: 'gf_dismiss_message',
										message_key: messageKey,
										nonce: nonce
									}
								})
							}
						}
					});
				});
			</script>
			<?php
		}
	}

	/**
	 * Adds a dismissible message to the user meta of the current user so it's not displayed again.
	 *
	 * @param $key
	 */
	public static function dismiss_message( $key ) {
		$db_key = self::get_dismissed_message_db_key( $key );
		update_user_meta( get_current_user_id(), $db_key, true, true );
	}

	/**
	 * Has the dismissible message been dismissed by the current user?
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	public static function is_message_dismissed( $key ) {
		$db_key = self::get_dismissed_message_db_key( $key );

		return (bool) get_user_meta( get_current_user_id(), $db_key, true );
	}

	/**
	 * Returns the database key for the message.
	 *
	 * @param $key
	 *
	 * @return string
	 */
	public static function get_dismissed_message_db_key( $key ) {
		$key = sanitize_key( $key );
		return 'gf_dimissed_' . substr( md5( $key ), 0, 40 );
	}

	private static function requires_gf_vars() {
		$dependent_scripts = array(
			'gform_form_admin',
			'gform_gravityforms',
			'gform_form_editor',
			'gform_field_filter'
		);
		foreach ( $dependent_scripts as $script ) {
			if ( wp_script_is( $script ) ) {
				return true;
			}
		}

		return false;
	}

	public static function maybe_output_gf_vars() {
		if ( self::requires_gf_vars() ) {
			echo '<script type="text/javascript">' . self::gf_vars( false ) . '</script>';
		}
	}

	/**
	 * Adds a leading zero if the first character is a comma or period.
	 *
	 * @param string $value The field value.
	 *
	 * @return string
	 */
	public static function maybe_add_leading_zero( $value ) {
		$value      = trim( $value );
		$first_char = GFCommon::safe_substr( $value, 0, 1 );
		if ( in_array( $first_char, array( '.', ',' ) ) ) {
			$value = '0' . $value;
		}

		return $value;
	}

	// used by the gfFieldFilterUI() jQuery plugin
	public static function get_field_filter_settings( $form ) {

		$exclude_types = array( 'rank', 'page', 'html' );

		// Initialize filters.
		$field_filters = array(
			array(
				'key'             => '0',
				'text'            => esc_html__( 'Any form field', 'gravityforms' ),
				'operators'       => array( 'contains', 'is' ),
				'preventMultiple' => false,
			),
		);

		/** @var GF_Field $field */
		foreach ( $form['fields'] as $field ) {
			$input_type = $field->get_input_type();

			if ( in_array( $input_type, $exclude_types ) || $field->displayOnly ) {
				continue;
			}

			if ( $field->type == 'post_category' ) {
				$field = self::add_categories_as_choices( $field, '' );
			}

			$filter_settings = $field->get_filter_settings();
			if ( empty( $filter_settings ) ) {
				continue;
			}

			// Start backwards compatibility. Adds missing settings. Required until the field add-ons implement the GF_Field helpers.

			if ( $input_type == 'likert' ) {
				$operators = array( 'is', 'isnot' );

				if ( ! $field->gsurveyLikertEnableMultipleRows ) {
					$filter_settings['operators'] = $operators;
				}

				if ( $field->gsurveyLikertEnableMultipleRows && ! isset( $filter_settings['group'] ) ) {
					$sub_filters = array();
					$rows        = $field->gsurveyLikertRows;

					foreach ( $rows as $row ) {
						$sub_filter                    = array();
						$sub_filter['key']             = $filter_settings['key'] . '|' . rgar( $row, 'value' );
						$sub_filter['text']            = rgar( $row, 'text' );
						$sub_filter['type']            = 'field';
						$sub_filter['preventMultiple'] = false;
						$sub_filter['operators']       = $operators;
						$sub_filter['values']          = $field->choices;
						$sub_filters[]                 = $sub_filter;
					}

					$filter_settings['filters'] = $sub_filters;
					$filter_settings['group']   = true;
					unset( $filter_settings['values'], $filter_settings['preventMultiple'], $filter_settings['operators'] );
				}
			}

			// End of backwards compatibility.

			$field_filters[] = $filter_settings;
		}

		$form_id            = $form['id'];
		$entry_meta_filters = self::get_entry_meta_filter_settings( $form_id );
		$field_filters      = array_merge( $field_filters, $entry_meta_filters );
		$field_filters      = array_values( $field_filters ); // reset the numeric keys in case some filters have been unset
		$info_filters       = self::get_entry_info_filter_settings();
		$field_filters      = array_merge( $field_filters, $info_filters );
		$field_filters      = array_values( $field_filters );

		/**
		 * Enables the filter settings for the form fields, entry properties, and entry meta to be overridden.
		 *
		 * @since 2.3.1.16
		 *
		 * @param array $field_filters The form field, entry properties, and entry meta filter settings.
		 * @param array $form          The form object the filter settings have been prepared for.
		 */
		$field_filters = apply_filters( 'gform_field_filters', $field_filters, $form );

		return $field_filters;
	}

	public static function get_entry_info_filter_settings() {
		$settings     = array();
		$info_columns = self::get_entry_info_filter_columns();
		foreach ( $info_columns as $key => $info_column ) {
			$info_column['key']             = $key;
			$info_column['preventMultiple'] = false;
			$settings[]                     = $info_column;
		}

		return $settings;
	}

	public static function get_entry_info_filter_columns( $get_users = true ) {
		$account_choices = array();
		if ( $get_users ) {
			$args            = apply_filters( 'gform_filters_get_users', array(
				'number' => 200,
				'fields' => array( 'ID', 'user_login' )
			) );
			$accounts        = get_users( $args );
			$account_choices = array();
			foreach ( $accounts as $account ) {
				$account_choices[] = array( 'text' => $account->user_login, 'value' => $account->ID );
			}
		}

		return array(
			'entry_id'       => array(
				'text'      => esc_html__( 'Entry ID', 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<' )
			),
			'date_created'   => array(
				'text'        => esc_html__( 'Entry Date', 'gravityforms' ),
				'operators'   => array( 'is', '>', '<' ),
				'placeholder' => __( 'yyyy-mm-dd', 'gravityforms' ),
				'cssClass'    => 'datepicker ymd_dash',
			),
			'is_starred'     => array(
				'text'      => esc_html__( 'Starred', 'gravityforms' ),
				'operators' => array( 'is', 'isnot' ),
				'values'    => array(
					array(
						'text'  => 'Yes',
						'value' => '1',
					),
					array(
						'text'  => 'No',
						'value' => '0',
					),
				)
			),
			'ip'             => array(
				'text'      => esc_html__( 'IP Address', 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
			),
			'source_url'     => array(
				'text'      => esc_html__( 'Source URL', 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
			),
			'payment_status' => array(
				'text'      => esc_html__( 'Payment Status', 'gravityforms' ),
				'operators' => array( 'is', 'isnot' ),
				'values'    => self::get_entry_payment_statuses_as_choices(),
			),
			'payment_date'    => array(
				'text'        => esc_html__( 'Payment Date', 'gravityforms' ),
				'operators'   => array( 'is', 'isnot', '>', '<' ),
				'placeholder' => __( 'yyyy-mm-dd', 'gravityforms' ),
				'cssClass'    => 'datepicker ymd_dash',
			),
			'payment_amount' => array(
				'text'      => esc_html__( 'Payment Amount', 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
			),
			'transaction_id' => array(
				'text'      => esc_html__( 'Transaction ID', 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<', 'contains' ),
			),
			'created_by'     => array(
				'text'      => esc_html__( 'User', 'gravityforms' ),
				'operators' => array( 'is', 'isnot' ),
				'values'    => $account_choices,
			),
		);
	}

	/**
	 * Returns an array of supported entry payment statuses.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public static function get_entry_payment_statuses() {
		$payment_statuses = array(
			'Authorized' => esc_html__( 'Authorized', 'gravityforms' ),
			'Paid'       => esc_html__( 'Paid', 'gravityforms' ),
			'Processing' => esc_html__( 'Processing', 'gravityforms' ),
			'Failed'     => esc_html__( 'Failed', 'gravityforms' ),
			'Active'     => esc_html__( 'Active', 'gravityforms' ),
			'Cancelled'  => esc_html__( 'Cancelled', 'gravityforms' ),
			'Pending'    => esc_html__( 'Pending', 'gravityforms' ),
			'Refunded'   => esc_html__( 'Refunded', 'gravityforms' ),
			'Voided'     => esc_html__( 'Voided', 'gravityforms' ),
		);

		/**
		 * Allow custom payment statuses to be defined.
		 *
		 * @since 2.4
		 *
		 * @param array $payment_statuses An array of entry payment statuses with the entry value as the key (15 char max) to the text for display.
		 */
		$payment_statuses = apply_filters( 'gform_payment_statuses', $payment_statuses );

		return $payment_statuses;
	}

	/**
	 * Returns an array of supported entry payment statuses formatted for use as drop down choices.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public static function get_entry_payment_statuses_as_choices() {
		$choices          = array();
		$payment_statuses = self::get_entry_payment_statuses();

		foreach ( $payment_statuses as $value => $text ) {
			$choices[] = array(
				'text'  => $text,
				'value' => $value,
			);
		}

		return $choices;
	}

	/**
	 * Returns the display text for the specified entry payment status value.
	 *
	 * @since 2.4
	 *
	 * @param string $payment_status_value The entry payment status value.
	 *
	 * @return string
	 */
	public static function get_entry_payment_status_text( $payment_status_value ) {
		$payment_statuses = self::get_entry_payment_statuses();

		return rgar( $payment_statuses, $payment_status_value, $payment_status_value );
	}

	public static function get_entry_meta_filter_settings( $form_id ) {
		$filters    = array();
		$entry_meta = GFFormsModel::get_entry_meta( $form_id );
		if ( empty( $entry_meta ) ) {
			return $filters;
		}

		foreach ( $entry_meta as $key => $meta ) {
			if ( isset( $meta['filter'] ) ) {
				$filter                    = array();
				$filter['key']             = $key;
				$filter['preventMultiple'] = isset( $meta['filter']['preventMultiple'] ) ? $meta['filter']['preventMultiple'] : false;
				$filter['text']            = rgar( $meta, 'label' );
				$filter['operators']       = isset( $meta['filter']['operators'] ) ? $meta['filter']['operators'] : array(
					'is',
					'isnot'
				);
				if ( isset( $meta['filter']['choices'] ) ) {
					$filter['values'] = $meta['filter']['choices'];
				}
				$filters[] = $filter;
			}
		}

		return $filters;
	}


	public static function get_field_filters_from_post( $form ) {
		$field_filters = array();
		$filter_fields = rgpost( 'f' );
		if ( is_array( $filter_fields ) ) {
			$filter_operators = rgpost( 'o' );
			$filter_values    = rgpost( 'v' );
			for ( $i = 0; $i < count( $filter_fields ); $i ++ ) {
				$field_filter = array();
				$key          = $filter_fields[ $i ];
				if ( 'entry_id' == $key ) {
					$key = 'id';
				}
				$operator       = $filter_operators[ $i ];
				$val            = $filter_values[ $i ];
				$strpos_row_key = strpos( $key, '|' );
				if ( $strpos_row_key !== false ) { //multi-row likert
					$key_array = explode( '|', $key );
					$key       = $key_array[0];
					$val       = $key_array[1] . ':' . $val;
				}
				$field_filter['key'] = $key;

				$field = GFFormsModel::get_field( $form, $key );
				if ( $field ) {
					$input_type = GFFormsModel::get_input_type( $field );
					if ( $field->type == 'product' && in_array( $input_type, array( 'radio', 'select' ) ) ) {
						$operator = 'contains';
					}
				}

				$field_filter['operator'] = $operator;
				$field_filter['value']    = $val;
				$field_filters[]          = $field_filter;
			}
		}
		$field_filters['mode'] = rgpost( 'mode' );

		return $field_filters;
	}

	public static function has_multifile_fileupload_field( $form ) {
		$fileupload_fields = GFAPI::get_fields_by_type( $form, array( 'fileupload', 'post_custom_field' ) );
		if ( is_array( $fileupload_fields ) ) {
			foreach ( $fileupload_fields as $field ) {
				if ( $field->multipleFiles ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function localize_gform_gravityforms_multifile() {
		wp_localize_script(
			'gform_gravityforms', 'gform_gravityforms', array(
				'strings' => array(
					'invalid_file_extension' => wp_strip_all_tags( __( 'This type of file is not allowed. Must be one of the following: ', 'gravityforms' ) ),
					'delete_file'            => wp_strip_all_tags( __( 'Delete this file', 'gravityforms' ) ),
					'in_progress'            => wp_strip_all_tags( __( 'in progress', 'gravityforms' ) ),
					'file_exceeds_limit'     => wp_strip_all_tags( __( 'File exceeds size limit', 'gravityforms' ) ),
					'illegal_extension'      => wp_strip_all_tags( __( 'This type of file is not allowed.', 'gravityforms' ) ),
					'max_reached'            => wp_strip_all_tags( __( 'Maximum number of files reached', 'gravityforms' ) ),
					'unknown_error'          => wp_strip_all_tags( __( 'There was a problem while saving the file on the server', 'gravityforms' ) ),
					'currently_uploading'    => wp_strip_all_tags( __( 'Please wait for the uploading to complete', 'gravityforms' ) ),
					'cancel'                 => wp_strip_all_tags( __( 'Cancel', 'gravityforms' ) ),
					'cancel_upload'          => wp_strip_all_tags( __( 'Cancel this upload', 'gravityforms' ) ),
					'cancelled'              => wp_strip_all_tags( __( 'Cancelled', 'gravityforms' ) )
				),
				'vars'    => array(
					'images_url' => GFCommon::get_base_url() . '/images'
				)
			)
		);
	}

	public static function send_resume_link( $message, $subject, $email, $embed_url, $resume_token ) {

		$from      = get_bloginfo( 'admin_email' );
		$from_name = get_bloginfo( 'name' );

		$message_format = 'multipart';

		$resume_url  = add_query_arg( array( 'gf_token' => $resume_token ), $embed_url );
		$resume_url  = esc_url( $resume_url );
		$resume_link = "<a href='{$resume_url}'>{$resume_url}</a>";
		$message .= $resume_link;

		$text_message = self::format_text_message( $message );
		$message = array(
			'html' => $message,
			'text' => $text_message,
		);

		self::send_email( $from, $email, '', $from, $subject, $message, $from_name, $message_format );
	}

	public static function safe_strlen( $string ) {

		if ( is_array( $string ) ) {
			return false;
		}

		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $string );
		} else {
			return strlen( $string );
		}

	}

	public static function safe_substr( $string, $start, $length = null ) {

		if ( is_array( $string ) ) {
			return false;
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $string, $start, $length );
		} else {
			return substr( $string, $start, $length );
		}
	}


	/**
	 * @param string $string
	 *
	 * @return string
	 */
	public static function safe_strtoupper( $string ) {

		if ( function_exists( 'mb_strtoupper' ) ) {
			return mb_strtoupper( $string );
		} else {
			return strtoupper( $string );
		}

	}

	/**
	 * Trims a string or an array recursively.
	 *
	 * @param array|string $array
	 *
	 * @return array|string
	 */
	public static function trim_deep( $array ) {
		if ( ! is_array( $array ) ) {
			return trim( $array );
		}

		return array_map( array( 'GFCommon', 'trim_deep' ), $array );
	}

	/**
	 * Reliably compare floats.
	 *
	 * @param  float  $float1
	 * @param  float  $float2
	 * @param  string $operator Supports: '<', '<=', '>', '>=', '==', '=', '!='
	 *
	 * @return bool
	 */
	public static function compare_floats( $float1, $float2, $operator ) {

		$epsilon    = 0.00001;
		$is_equal   = abs( floatval( $float1 ) - floatval( $float2 ) ) < $epsilon;
		$is_greater = floatval( $float1 ) > floatval( $float2 );
		$is_less    = floatval( $float1 ) < floatval( $float2 );

		switch ( $operator ) {
			case '<':
				return $is_less;
			case '<=':
				return $is_less || $is_equal;
			case '>' :
				return $is_greater;
			case '>=':
				return $is_greater || $is_equal;
			case '==':
			case '=':
				return $is_equal;
			case '!=':
				return ! $is_equal;
		}

	}

	/**
	 * Encrypts a string using mcrypt_encrypt if available.
	 *
	 * mcrypt_encrypt is deprecated in PHP 7.1, use GFCommon::openssl_encrypt() instead.
	 *
	 * @deprecated 2.3
	 *
	 * @param      $text
	 * @param null $key
	 * @param bool $mcrypt_cipher_name
	 *
	 * @return string
	 */
	public static function encrypt( $text, $key = null, $mcrypt_cipher_name = false ) {

		_deprecated_function( 'GFCommon::encrypt()', '2.3', 'GFCommon::openssl_encrypt()' );

		$use_mcrypt = apply_filters( 'gform_use_mcrypt', function_exists( 'mcrypt_encrypt' ) );

		if ( $use_mcrypt ) {
			$mcrypt_cipher_name = $mcrypt_cipher_name === false ? MCRYPT_RIJNDAEL_256 : $mcrypt_cipher_name;
			$iv_size            = mcrypt_get_iv_size( $mcrypt_cipher_name, MCRYPT_MODE_ECB );
			$key                = ! is_null( $key ) ? $key : substr( md5( wp_salt( 'nonce' ) ), 0, $iv_size );

			$encrypted_value = trim( base64_encode( mcrypt_encrypt( $mcrypt_cipher_name, $key, $text, MCRYPT_MODE_ECB, mcrypt_create_iv( $iv_size, MCRYPT_RAND ) ) ) );
		} else {
			$encrypted_value = EncryptDB::encrypt( $text, wp_salt( 'nonce' ) );
		}

		return $encrypted_value;
	}

	/**
	 * Decrypts a string using mcrypt_decrypt if available.
	 *
	 * mcrypt_decrypt is deprecated in PHP 7.1, use GFCommon::openssl_decrypt() instead.
	 *
	 * @deprecated 2.3
	 *
	 * @param      $text
	 * @param null $key
	 * @param bool $mcrypt_cipher_name
	 *
	 * @return null|string
	 */
	public static function decrypt( $text, $key = null, $mcrypt_cipher_name = false ) {

		_deprecated_function( 'GFCommon::decrypt()', '2.3', 'GFCommon::openssl_decrypt()' );

		$use_mcrypt = apply_filters( 'gform_use_mcrypt', function_exists( 'mcrypt_decrypt' ) );

		if ( $use_mcrypt ) {
			$mcrypt_cipher_name = $mcrypt_cipher_name === false ? MCRYPT_RIJNDAEL_256 : $mcrypt_cipher_name;
			$iv_size            = mcrypt_get_iv_size( $mcrypt_cipher_name, MCRYPT_MODE_ECB );
			$key                = ! is_null( $key ) ? $key : substr( md5( wp_salt( 'nonce' ) ), 0, $iv_size );

			$decrypted_value = trim( mcrypt_decrypt( $mcrypt_cipher_name, $key, base64_decode( $text ), MCRYPT_MODE_ECB, mcrypt_create_iv( $iv_size, MCRYPT_RAND ) ) );
		} else {
			$decrypted_value = EncryptDB::decrypt( $text, wp_salt( 'nonce' ) );
		}

		return $decrypted_value;
	}

	/**
	 * Encrypt with AES-256-CTR plus HMAC-SHA-512 hash.
	 *
	 *
	 * @since 2.3
	 *
	 * @param string $text           The text to encrypt.
	 * @param string $encryption_key Key for encryption
	 * @param string $cipher_name    The cypher name. Default 'aes-256-ctr'.
	 * @param string $mac_key        The key to be used to generate the hash.
	 *
	 * @return string|false the encrypted string on success or false on failure
	 */
	public static function openssl_encrypt( $text, $encryption_key = null, $cipher_name = 'aes-256-ctr', $mac_key = null ) {

		if ( function_exists( 'openssl_encrypt' ) ) {
			$nonce = openssl_random_pseudo_bytes( 16 );

			if ( empty( $encryption_key ) ) {
				$encryption_key = 'gravityforms_encryption_key' . wp_salt( 'nonce' );
			}

			// OPENSSL_RAW_DATA is not available on PHP 5.3
			$options = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : 1;

			$ciphertext = openssl_encrypt( $text, $cipher_name, $encryption_key, $options, $nonce );

			if ( empty( $ciphertext ) ) {
				return false;
			}

			if ( empty( $mac_key ) ) {
				$mac_key = 'gravityforms_encryption_mac' . wp_salt( 'nonce' );
			}

			$mac = hash_hmac( 'sha512', $nonce . $ciphertext, $mac_key, true );

			$encrypted_value = base64_encode( $mac . $nonce . $ciphertext );
		} else {
			$encrypted_value = EncryptDB::encrypt( $text, wp_salt( 'nonce' ) );
		}

		return $encrypted_value;
	}

	/**
	 * Decrypt AES-256-CTR with HMAC-SHA-512 hash.
	 *
	 * @since 2.3
	 *
	 * @param string $text           Your message
	 * @param string $encryption_key Key for encryption
	 * @param string $cipher_name    The cypher name. Default 'aes-256-ctr'.
	 * @param string $mac_key        The key to be used for the hash.
	 *
	 * @return string|false the decrypted string on success or false on failure
	 */
	public static function openssl_decrypt( $text, $encryption_key = null, $cipher_name = 'aes-256-ctr', $mac_key = null ) {

		if ( function_exists( 'openssl_encrypt' ) ) {

			$text_decoded = base64_decode( $text );

			$mac = substr( $text_decoded, 0, 64 );

			$nonce = substr( $text_decoded, 64, 16 );

			$ciphertext = substr( $text_decoded, 80 );

			if ( empty( $mac_key ) ) {
				$mac_key = 'gravityforms_encryption_mac' . wp_salt( 'nonce' );
			}

			$mac_check = hash_hmac( 'sha512', $nonce . $ciphertext, $mac_key, true );

			if ( ! hash_equals( $mac_check, $mac ) ) {
				return false;
			}

			if ( empty( $encryption_key ) ) {
				$encryption_key = 'gravityforms_encryption_key' . wp_salt( 'nonce' );
			}

			// OPENSSL_RAW_DATA is not available on PHP 5.3
			$options = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : 1;

			$decrypted_value = openssl_decrypt( $ciphertext, $cipher_name, $encryption_key, $options, $nonce );

		} else {
			$decrypted_value = EncryptDB::decrypt( $text, wp_salt( 'nonce' ) );
		}

		return $decrypted_value;
	}

	public static function esc_like( $value ) {
		global $wpdb;

		if ( is_callable( array( $wpdb, 'esc_like' ) ) ) {
			$value = $wpdb->esc_like( $value );
		} else {
			$value = like_escape( $value );
		}

		return $value;
	}

	public static function is_form_editor() {
		$is_form_editor = GFForms::get_page() == 'form_editor' || ( defined( 'DOING_AJAX' ) && DOING_AJAX && in_array( rgpost( 'action' ), array(
					'rg_add_field',
					'rg_refresh_field_preview',
					'rg_duplicate_field',
					'rg_delete_field',
					'rg_change_input_type'
				) ) );

		return apply_filters( 'gform_is_form_editor', $is_form_editor );
	}

	public static function is_entry_detail() {
		$is_entry_detail = GFForms::get_page() == 'entry_detail_edit' || GFForms::get_page() == 'entry_detail';

		return apply_filters( 'gform_is_entry_detail', $is_entry_detail );
	}

	public static function is_entry_detail_view() {
		$is_entry_detail_view = GFForms::get_page() == 'entry_detail';

		return apply_filters( 'gform_is_entry_detail_view', $is_entry_detail_view );
	}

	public static function is_entry_detail_edit() {
		$is_entry_detail_edit = GFForms::get_page() == 'entry_detail_edit';

		return apply_filters( 'gform_is_entry_detail_edit', $is_entry_detail_edit );
	}

	public static function has_merge_tag( $string ) {
		return preg_match( '/{.+}/', $string );
	}

	public static function get_upload_page_slug() {
		$slug = get_option( 'gform_upload_page_slug' );
		if ( empty( $slug ) ) {
			$slug = substr( str_shuffle( wp_hash( microtime() ) ), 0, 15 );
			update_option( 'gform_upload_page_slug', $slug );
		}

		return $slug;
	}

	/**
	 * Whitelists a value. Returns the value or the first value in the array.
	 *
	 * @param $value
	 * @param $whitelist
	 *
	 * @return mixed
	 */
	public static function whitelist( $value, $whitelist ) {

		if ( ! in_array( $value, $whitelist ) ) {
			$value = $whitelist[0];
		}

		return $value;
	}

	/**
	 * Forces an integer into a range of integers. Returns the value or the minimum if it's outside the range.
	 *
	 * @param $value
	 * @param $min
	 * @param $max
	 *
	 * @return int
	 */
	public static function int_range( $value, $min, $max ) {
		$value = (int) $value;
		$min   = (int) $min;
		$max   = (int) $max;

		return filter_var( $value, FILTER_VALIDATE_INT, array(
			'min_range' => $min,
			'max_range' => $max
		) ) ? $value : $min;
	}


	/**
	 * Checks for the existence of a MySQL table.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @param string $table_name Table to check for.
	 *
	 * @uses wpdb::get_var()
	 *
	 * @return bool
	 */
	public static function table_exists( $table_name ) {

		global $wpdb;

		$count = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );

		return ! empty( $count );

	}

	/**
	 * Initializing translations.
	 *
	 * Translation files in the WP_LANG_DIR folder have a higher priority.
	 *
	 * @param string $domain   The plugin text domain. Default is gravityforms.
	 * @param string $basename The plugin basename. plugin_basename() will be used to get the Gravity Forms basename when not provided.
	 */
	public static function load_gf_text_domain( $domain = 'gravityforms', $basename = '' ) {
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		if ( $locale != 'en_US' && ! is_textdomain_loaded( $domain ) ) {
			if ( empty( $basename ) ) {
				$basename = plugin_basename( self::get_base_path() );
			}

			load_textdomain( $domain, sprintf( '%s/gravityforms/%s-%s.mo', WP_LANG_DIR, $domain, $locale ) );
			load_plugin_textdomain( $domain, false, $basename . '/languages' );
		}
	}

	public static function replace_field_variable( $text, $form, $lead, $url_encode, $esc_html, $nl2br, $format, $input_id, $match, $esc_attr = false ) {
		$field = RGFormsModel::get_field( $form, $input_id );

		//If field is not in the form, don't replace the merge tag.
		if ( ! $field ) {
			return $text;
		}

		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		$value     = RGFormsModel::get_lead_field_value( $lead, $field );
		$raw_value = $value;

		if ( is_array( $value ) ) {
			$value = rgar( $value, $input_id );
		}

		$value = self::format_variable_value( $value, $url_encode, $esc_html, $format, $nl2br );

		// Modifier will be at index 4 unless used in a conditional shortcode in which case it would be at index 5.
		$i         = $match[0][0] == '{' ? 4 : 5;
		$modifier  = strtolower( rgar( $match, $i ) );
		$modifiers = array_map( 'trim', explode( ',', $modifier ) );
		$field->set_modifiers( $modifiers );

		if ( in_array( 'urlencode', $modifiers ) ) {
			$url_encode = true;
		}

		$value = $field->get_value_merge_tag( $value, $input_id, $lead, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br );

		if ( ! in_array( $field->type, array( 'html', 'section', 'signature' ) ) ) {
			$value = self::encode_shortcodes( $value );
		}

		if ( $esc_attr ) {
			$value = esc_attr( $value );
		}

		if ( $modifier == 'label' ) {
			$value = empty( $value ) ? '' : $field->label;
		} else if ( $modifier == 'numeric' ) {
			$number_format = $field->numberFormat ? $field->numberFormat : 'decimal_dot';
			$value         = self::clean_number( $value, $number_format );
		} else if ( $modifier == 'qty' && $field->type == 'product' ) {
			// Getting quantity associated with product field.
			$products = self::get_product_fields( $form, $lead, false, false );
			$value    = 0;
			foreach ( $products['products'] as $product_id => $product ) {
				if ( $product_id == $field->id ) {
					$value = $product['quantity'];
				}
			}
		}

		// Encoding left curly bracket so that merge tags entered in the front end are displayed as is and not parsed.
		$value = self::encode_merge_tag( $value );

		// Filter can change merge tag value.
		$value = apply_filters( 'gform_merge_tag_filter', $value, $input_id, $modifier, $field, $raw_value, $format );
		if ( $value === false ) {
			$value = '';
		}

		// Clear merge tag modifiers from the field object.
		$field->set_modifiers( array() );

		if ( $match[0][0] != '{' ) {
			// Replace the merge tag in the conditional shortcode merge_tag attr.
			$value = str_replace( $match[1], $value, $match[0] );
		}

		$text = str_replace( $match[0], $value, $text );

		return $text;
	}

	public static function encode_shortcodes( $string ) {
		$find    = array( '[', ']' );
		$replace = array( '&#91;', '&#93;' );
		$string  = str_replace( $find, $replace, $string );

		return $string;
	}

	/**
	 * Sanitizes html content. Checks the unfiltered_html capability.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param $html
	 * @param $allowed_html
	 * @param $allowed_protocols
	 *
	 * @return string
	 */
	public static function maybe_wp_kses( $html, $allowed_html = 'post', $allowed_protocols = array() ) {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$html = wp_kses( $html, $allowed_html, $allowed_protocols );
		}

		return $html;
	}

	/**
	 * Sanitizes a confirmation message.
	 *
	 * @since 2.0.0
	 *
	 * @param $confirmation_message
	 *
	 * @return string
	 */
	public static function maybe_sanitize_confirmation_message( $confirmation_message ) {
		// Default during deprecation period = false
		$sanitize_confirmation_nessage = false;

		/**
		 * Allows sanitization to be turned on or off for the confirmation message. Only turn off if you're sure you know what you're doing.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $sanitize_confirmation_nessage Whether to sanitize the confirmation message. default: true
		 */
		$sanitize_confirmation_nessage = apply_filters( 'gform_sanitize_confirmation_message', $sanitize_confirmation_nessage );
		if ( $sanitize_confirmation_nessage ) {
			$confirmation_message = wp_kses_post( $confirmation_message );
		}

		return $confirmation_message;
	}

	/**
	 * Generates a hash for a Gravity Forms download.
	 *
	 * May return false if the algorithm is not available.
	 *
	 * @param int    $form_id  The Form ID.
	 * @param int    $field_id The ID of the field used to upload the file.
	 * @param string $file     The file url relative to the form's upload folder. E.g. 2016/04/my-file.pdf
	 *
	 * @return string|bool
	 */
	public static function generate_download_hash( $form_id, $field_id, $file ) {

		$key = absint( $form_id ) . ':' . absint( $field_id ) . ':' . urlencode( $file );

		$algo = 'sha256';

		/**
		 * Allows the hash algorithm to be changed when generating the file download hash.
		 *
		 * @param string $algo The algorithm. E.g. "md5", "sha256", "haval160,4", etc
		 */
		$algo = apply_filters( 'gform_download_hash_algorithm', $algo );

		$hash = hash_hmac( $algo, $key, 'gform_download' . wp_salt() );
		/**
		 * Allows the hash to be modified.
		 *
		 * @param string $hash    The hash.
		 * @param int    $form_id The Form ID
		 * @param string $file    The File path relative to the upload root for the form.
		 */
		$hash = apply_filters( 'gform_download_hash', $hash, $form_id, $file );

		return $hash;
	}

	public static function get_visibility_options() {

		$options = array(
			array(
				'label'       => __( 'Visible', 'gravityforms' ),
				'value'       => 'visible',
				'description' => __( 'Default option. The field is visible when viewing the form.', 'gravityforms' )
			),
			array(
				'label'       => __( 'Hidden', 'gravityforms' ),
				'value'       => 'hidden',
				'description' => __( 'The field is hidden when viewing the form. Useful when you require the functionality of this field but do not want the user to be able to see this field.', 'gravityforms' )
			),
			array(
				'label'       => __( 'Administrative', 'gravityforms' ),
				'value'       => 'administrative',
				'description' => __( 'The field is only visible when administering submitted entries. The field is not visible or functional when viewing the form.', 'gravityforms' )
			),
		);

		/**
		 * Allows default visibility options to be modified or removed and custom visibility options to be added.
		 *
		 * @since 2.1
		 *
		 * @param array $options     {
		 *                           An array of visibility options.
		 *
		 * @type string $label       The label of the visibility option; displayed in the field's Visibility setting.
		 * @type string $value       The value of the visibility option; will be saved to the form meta.
		 * @type string $description The description of the visibility option; used in the Visibility setting tooltip.
		 * }
		 */
		return (array) apply_filters( 'gform_visibility_options', $options );
	}

	public static function get_visibility_tooltip() {

		$options = self::get_visibility_options();
		$markup  = array();

		foreach ( $options as $option ) {
			$markup[] = sprintf( '<b>%s</b><br>%s', $option['label'], $option['description'] );
		}

		$markup = sprintf( '<ul><li>%s</li></ul>', implode( '</li><li>', $markup ) );

		return sprintf( '<h6>%s</h6> %s<br><br>%s', __( 'Visibility', 'gravityforms' ), __( 'Select the visibility for this field.', 'gravityforms' ), $markup );
	}

	/**
	 * @param $message
	 *
	 * @return mixed|string
	 */
	private static function format_text_message( $message ) {

		// Replacing <h> tags with asterisk.
		$text_message = preg_replace( '|<h(\d)|', '* <h$1', $message );

		// Replacing <br> tags with new line character.
		$text_message = preg_replace( '|<br\s*?/?>|', "\n<br />", $text_message );

		// Removing all HTML tags.
		$text_message = wp_strip_all_tags( $text_message );

		// Removing &nbsp; characters
		$text_message = str_replace( '&nbsp;', ' ', $text_message );

		// Removing multiple white spaces
		$text_message = preg_replace( '|[ \t]+|', ' ', $text_message );

		// Removing multiple line feeds
		$text_message = preg_replace( "|[\r\n]+\s*|", "\n", $text_message );

		return $text_message;
	}

	/**
	 * Maybe wrap the notification message in html tags.
	 *
	 * @since 2.2.0
	 *
	 * @param string $message The notification message. Merge tags have already been processed.
	 * @param string $subject The notification subject line. Merge tags have already been processed.
	 *
	 * @return string
	 */
	private static function format_html_message( $message, $subject ) {
		if ( ! preg_match( '/<html/i', $message ) ) {
			$template =
				"<html>
	<head>
		<title>{subject}</title>
	</head>
	<body>
		{message}
	</body>
</html>";

			/**
			 * Allow the template for the html formatted message to be overridden.
			 *
			 * @since 2.2.1.5
			 *
			 * @param string $template The template for the html formatted message. Use {message} and {subject} as placeholders.
			 */
			$template = apply_filters( 'gform_html_message_template_pre_send_email', $template );

			$message = str_replace( '{message}', $message, $template );
			$message = str_replace( '{subject}', $subject, $message );
		}

		return $message;
	}


	/***
	 * Registers a site to the specified key, or if $new_key is blank, unlinks a key from an existing site.
	 * Requires that the $new_key is saved in options before calling this function
	 *
	 * @since 2.3
	 *
	 * @param $new_key string Unhashed Gravity Forms license key
	 * @param $is_md5 boolean Specifies if the $new_key parameter is an md5 key or an unhashed key. Defaults to false.
	 *
	 * @return bool|WP_Error Returns true if site was updated or created successfully, otherwise returns an instance of WP_Error.
	 */
	public static function update_site_registration( $new_key, $is_md5 = false ) {

		GFForms::include_gravity_api();

		$result = null;

		if ( empty( $new_key ) ) {

			//Unlinking key to site
			$result = gapi()->update_current_site( '' );

		} else {

			//License Key has changed, update site record appropriately.

			//Get new license key information
			$version_info = GFCommon::get_version_info( false );

			//Has site been already registered?
			$is_site_registered = gapi()->is_site_registered();
			$is_valid_new 			= $version_info['is_valid_key'] && ! $is_site_registered;
			$is_valid_registered 	= $version_info['is_valid_key'] && $is_site_registered;

			if ( $is_valid_new ) {
				//Site is new (not registered) and license key is valid
				//Register new site
				$result = gapi()->register_current_site( $new_key, $is_md5 );
			} elseif ( $is_valid_registered ) {

				//Site is already registered and new license key is valid
				//Update site with new license key
				$result = gapi()->update_current_site( $new_key );
			} else {

				//Invalid key, do not change site registration.
				$result = new WP_Error( 'invalid_license', 'Invalid license. Site cannot be registered' );
				GFCommon::log_error( 'Invalid license. Site cannot be registered' );
			}
		}

		if ( is_wp_error( $result ) ) {
			GFCommon::log_error( 'Failed to update site registration with Gravity Manager. ' . print_r( $result, true ) );
		}

		return $result;
	}

	/**
	 * Checks if notification from email is using the site domain.
	 *
	 * @since  2.4.12
	 *
	 * @param string $email_address Email address to check.
	 * @param string $domain        Domain to check.
	 *
	 * @return bool
	 */
	public static function email_domain_matches( $email_address, $domain = '' ) {

		GFCommon::log_debug( __METHOD__ . '(): Email address: ' . $email_address );

		if ( ! is_email( $email_address ) ) {
			GFCommon::log_debug( __METHOD__ . '(): Email address failed is_email() validation.' );
			return false;
		}

		if ( empty( $domain ) ) {
			$domain = parse_url( get_bloginfo( 'url' ), PHP_URL_HOST );
		}

		GFCommon::log_debug( __METHOD__ . '(): Domain or URL: ' . $domain );

		$email_domain = explode( '@', $email_address );

		$domain_matches = ( strpos( $domain, array_pop( $email_domain ) ) !== false ) ? true : false;
		GFCommon::log_debug( __METHOD__ . '(): Domain matches? '. var_export( $domain_matches, true ) );

		return $domain_matches;
  }

}

class GFCategoryWalker extends Walker {
	/**
	 * @see   Walker::$tree_type
	 * @since 2.1.0
	 * @var string
	 */
	var $tree_type = 'category';

	/**
	 * @see   Walker::$db_fields
	 * @since 2.1.0
	 * @todo  Decouple this
	 * @var array
	 */
	var $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

	/**
	 * @see   Walker::start_el()
	 * @since 2.1.0
	 *
	 * @param string $output            Passed by reference. Used to append additional content.
	 * @param object $object            Category data object.
	 * @param int    $depth             Depth of category. Used for padding. Defaults to 0.
	 * @param array  $args              Uses 'selected' and 'show_count' keys, if they exist. Defaults to empty array.
	 * @param int    $current_object_id The current object ID. Defaults to 0.
	 */
	function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		//$pad = str_repeat('&nbsp;', $depth * 3);
		$pad = str_repeat( '&#9472;', $depth );
		if ( ! empty( $pad ) ) {
			$pad .= '&nbsp;';
		}
		$object->name = "{$pad}{$object->name}";

		if ( empty( $output ) ) {
			$output = array();
		}

		$output[] = $object;
	}
}

/**
 *
 * Notes:
 * 1. The WordPress Transients API does not support boolean
 * values so boolean values should be converted to integers
 * or arrays before setting the values as persistent.
 *
 * 2. The transients API only deletes the transient from the database
 * when the transient is accessed after it has expired. WordPress doesn't
 * do any garbage collection of transients.
 *
 */
class GFCache {
	private static $_transient_prefix = 'GFCache_';
	private static $_cache = array();

	public static function get( $key, &$found = null, $is_persistent = true ) {
		global $blog_id;
		if ( is_multisite() ) {
			$key = $blog_id . ':' . $key;
		}

		if ( isset( self::$_cache[ $key ] ) ) {
			$found = true;
			$data  = rgar( self::$_cache[ $key ], 'data' );

			return $data;
		}

		//If set to not persistent, do not check transient for performance reasons
		if ( ! $is_persistent ) {
			$found = false;

			return false;
		}

		$data = self::get_transient( $key );

		if ( false === ( $data ) ) {
			$found = false;

			return false;
		} else {
			self::$_cache[ $key ] = array( 'data' => $data, 'is_persistent' => true );
			$found                = true;

			return $data;
		}

	}

	public static function set( $key, $data, $is_persistent = false, $expiration_seconds = 0 ) {
		global $blog_id;
		$success = true;

		if ( is_multisite() ) {
			$key = $blog_id . ':' . $key;
		}

		if ( $is_persistent ) {
			$success = self::set_transient( $key, $data, $expiration_seconds );
		}

		self::$_cache[ $key ] = array( 'data' => $data, 'is_persistent' => $is_persistent );

		return $success;
	}

	public static function delete( $key ) {
		global $blog_id;
		$success = true;

		if ( is_multisite() ) {
			$key = $blog_id . ':' . $key;
		}

		if ( isset( self::$_cache[ $key ] ) ) {
			if ( self::$_cache[ $key ]['is_persistent'] ) {
				$success = self::delete_transient( $key );
			}

			unset( self::$_cache[ $key ] );
		} else {
			$success = self::delete_transient( $key );

		}

		return $success;
	}

	public static function flush( $flush_persistent = false ) {
		global $wpdb;

		self::$_cache = array();

		if ( false === $flush_persistent ) {
			return true;
		}

		if ( is_multisite() ) {
			$sql = "
                 DELETE FROM $wpdb->sitemeta
                 WHERE meta_key LIKE '\_site\_transient\_timeout\_GFCache\_%' OR
                 meta_key LIKE '_site_transient_GFCache_%'
                ";
		} else {
			$sql = "
                 DELETE FROM $wpdb->options
                 WHERE option_name LIKE '\_transient\_timeout\_GFCache\_%' OR
                 option_name LIKE '\_transient\_GFCache\_%'
                ";

		}
		$rows_deleted = $wpdb->query( $sql );

		$success = $rows_deleted !== false ? true : false;

		return $success;
	}

	private static function delete_transient( $key ) {
		if ( ! function_exists( 'wp_hash' ) ) {
			return false;
		}
		$key = self::$_transient_prefix . wp_hash( $key );
		if ( is_multisite() ) {
			$success = delete_site_transient( $key );
		} else {
			$success = delete_transient( $key );
		}

		return $success;
	}

	private static function set_transient( $key, $data, $expiration ) {
		if ( ! function_exists( 'wp_hash' ) ) {
			return false;
		}
		$key = self::$_transient_prefix . wp_hash( $key );
		if ( is_multisite() ) {
			$success = set_site_transient( $key, $data, $expiration );
		} else {
			$success = set_transient( $key, $data, $expiration );
		}

		return $success;
	}

	private static function get_transient( $key ) {
		if ( ! function_exists( 'wp_hash' ) ) {
			return false;
		}
		$key = self::$_transient_prefix . wp_hash( $key );
		if ( is_multisite() ) {
			$data = get_site_transient( $key );
		} else {
			$data = get_transient( $key );
		}

		return $data;
	}

}

class EncryptDB extends wpdb {
	private static $_instance = null;

	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new EncryptDB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		}

		return self::$_instance;
	}

	public static function encrypt( $text, $key ) {
		$db = self::get_instance();

		$encrypted = base64_encode( $db->get_var( $db->prepare( 'SELECT AES_ENCRYPT(%s, %s) AS data', $text, $key ) ) );

		return $encrypted;
	}

	public static function decrypt( $text, $key ) {

		$db = self::get_instance();

		$decrypted = $db->get_var( $db->prepare( 'SELECT AES_DECRYPT(%s, %s) AS data', base64_decode( $text ), wp_salt( 'nonce' ) ) );

		return $decrypted;
	}

	public function get_var( $query = null, $x = 0, $y = 0 ) {

		$this->check_current_query = false;

		return parent::get_var( $query );
	}
}

/**
 * Late static binding for dynamic function calls.
 *
 * Provides compatibility with PHP 7.2 (create_function deprecated) and 5.2.
 * So whenever the need for `create_function` arises, use this instead.
 */
class GF_Late_Static_Binding {
	private $args = array();

	public function __construct( $args ) {
		$this->args = wp_parse_args( $args, array(
			'form_id' => 0,
		) );
	}

	/**
	 * Binding for GFFormDisplay::footer_init_scripts
	 */
	public function GFFormDisplay_footer_init_scripts() {
		return GFFormDisplay::footer_init_scripts( $this->args['form_id'] );
	}
}
