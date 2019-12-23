<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Password extends GF_Field {

	public $type = 'password';

	private static $passwords = array();

	public function get_form_editor_field_title() {
		return esc_attr__( 'Password', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'size_setting',
			'rules_setting',
			'input_placeholders_setting',
			'sub_label_placement_setting',
			'description_setting',
			'css_class_setting',
			'password_strength_setting',
			'password_visibility_setting',
			'password_setting',
		);
	}

	public function get_form_editor_button() {
		return array(); // this button is conditionally added in the form detail page
	}

	public function get_entry_inputs() {
		return null;
	}

	public function validate( $value, $form ) {
		$password = rgpost( 'input_' . $this->id );
		$confirm  = rgpost( 'input_' . $this->id . '_2' );
		if ( $this->is_confirm_input_enabled() && $password != $confirm ) {
			$this->failed_validation  = true;
			$this->validation_message = esc_html__( 'Your passwords do not match.', 'gravityforms' );
		} elseif ( $this->passwordStrengthEnabled && ! empty( $this->minPasswordStrength ) && ! empty( $password ) ) {
			
			$strength = rgpost('input_' . $this->id . '_strength' );
			
			if ( empty( $strength ) ) {
				$strength = $this->get_password_strength( $password );
			}

			$levels = array( 'short' => 1, 'bad' => 2, 'good' => 3, 'strong' => 4 );
			if ( $levels[ $strength ] < $levels[ $this->minPasswordStrength ] ) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $this->errorMessage ) ? sprintf( esc_html__( 'Your password does not meet the required strength. %sHint: To make it stronger, use upper and lower case letters, numbers and symbols like ! " ? $ %% ^ & ).', 'gravityforms' ), '<br />' ) : $this->errorMessage;
			}
		}
	}
	
	/**
	 * Calculate the password score using PHP when not passed by JS.
	 *
	 * @since 2.4.11
	 *
	 * @see gravityforms.js gformPasswordStrength() JS code
	 *
	 * @param string $password The password that should be checked.
	 *
	 * @return string blank|short|bad|good|strong
	 */
	protected function get_password_strength( $password = '' ) {

		$symbol_size = 0;
		$strlen      = GFCommon::safe_strlen( $password );

		if ( 0 >= $strlen ) {
			return 'blank';
		}

		if ( $strlen < 4 ) {
			return 'short';
		}

		if ( preg_match( '/[ 0 - 9 ] /', $password ) ) {
			$symbol_size += 10;
		}

		if ( preg_match( '/[ a - z ] /', $password ) ) {
			$symbol_size += 26;
		}

		if ( preg_match( '/[ A - Z ] /', $password ) ) {
			$symbol_size += 26;
		}

		if ( preg_match( '/[^a - zA - Z0 - 9]/', $password ) ) {
			$symbol_size += 31;
		}

		$natLog = log( pow( $symbol_size, $strlen ) );
		$score  = $natLog / log( 2 );

		if ( 40 > $score ) {
			return 'bad';
		}

		if ( 56 > $score ) {
			return 'good';
		}

		return 'strong';
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		if ( is_array( $value ) ) {
			$value = array_values( $value );
		}

		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin        = $is_entry_detail || $is_form_editor;

		$id       = (int) $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$size         = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class        = $this->is_confirm_input_enabled() ? '' : $size . $class_suffix; // Size only applies when confirmation is disabled.

		$form_sub_label_placement  = rgar( $form, 'subLabelPlacement' );
		$field_sub_label_placement = $this->subLabelPlacement;
		$is_sub_label_above        = $field_sub_label_placement == 'above' || ( empty( $field_sub_label_placement ) && $form_sub_label_placement == 'above' );
		$sub_label_class_attribute = $field_sub_label_placement == 'hidden_label' ? "class='hidden_sub_label screen-reader-text'" : '';

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$first_tabindex = $this->get_tabindex();
		$last_tabindex  = $this->get_tabindex();

		$strength_style           = ! $this->passwordStrengthEnabled ? "style='display:none;'" : '';
		$strength_indicator_label = esc_html__( 'Strength indicator', 'gravityforms' );
		$strength                 = $this->passwordStrengthEnabled || $is_admin ? "<div id='{$field_id}_strength_indicator' class='gfield_password_strength' {$strength_style}>
																			{$strength_indicator_label}
																		</div>
																		<input type='hidden' class='gform_hidden' id='{$field_id}_strength' name='input_{$id}_strength' />" : '';

		$action   = ! $is_admin ? "gformShowPasswordStrength(\"$field_id\");" : '';
		$onchange = $this->passwordStrengthEnabled ? "onchange='{$action}'" : '';
		$onkeyup  = $this->passwordStrengthEnabled ? "onkeyup='{$action}'" : '';

		$confirmation_value = rgpost( 'input_' . $id . '_2' );

		$password_value     = is_array( $value ) ? $value[0] : $value;
		$password_value     = esc_attr( $password_value );
		$confirmation_value = esc_attr( $confirmation_value );

		$enter_password_field_input   = GFFormsModel::get_input( $this, $this->id . '' );
		$confirm_password_field_input = GFFormsModel::get_input( $this, $this->id . '.2' );

		$enter_password_label = rgar( $enter_password_field_input, 'customLabel' ) != '' ? $enter_password_field_input['customLabel'] : esc_html__( 'Enter Password', 'gravityforms' );
		$enter_password_label = gf_apply_filters( array( 'gform_password', $form_id ), $enter_password_label, $form_id );

		$confirm_password_label = rgar( $confirm_password_field_input, 'customLabel' ) != '' ? $confirm_password_field_input['customLabel'] : esc_html__( 'Confirm Password', 'gravityforms' );
		$confirm_password_label = gf_apply_filters( array( 'gform_password_confirm', $form_id ), $confirm_password_label, $form_id );
	
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';

		$enter_password_placeholder_attribute   = GFCommon::get_input_placeholder_attribute( $enter_password_field_input );
		$confirm_password_placeholder_attribute = GFCommon::get_input_placeholder_attribute( $confirm_password_field_input );

		$visibility_toggle_style = ! $this->passwordVisibilityEnabled ? " style='display:none;'" : '';
		$enter_password_toggle   = $this->passwordVisibilityEnabled || $is_admin ? "<button type='button' onclick='javascript:gformToggleShowPassword(\"{$field_id}\");' label='" . esc_attr__( 'Show Password', 'gravityforms' ) . "' data-label-show='" . esc_attr__( 'Show Password', 'gravityforms' ) . "' data-label-hide='" . esc_attr__( 'Hide Password', 'gravityforms' ) . "'{$visibility_toggle_style}><span class='dashicons dashicons-hidden' aria-hidden='true'></span></button>" : "";
		$confirm_password_toggle = $this->passwordVisibilityEnabled || $is_admin ? "<button type='button' onclick='javascript:gformToggleShowPassword(\"{$field_id}_2\");' label='" . esc_attr__( 'Show Password', 'gravityforms' ) . "' data-label-show='" . esc_attr__( 'Show Password', 'gravityforms' ) . "' data-label-hide='" . esc_attr__( 'Hide Password', 'gravityforms' ) . "'{$visibility_toggle_style}><span class='dashicons dashicons-hidden' aria-hidden='true'></span></button>" : "";

		if ( $is_form_editor ) {
			$confirm_style = $this->is_confirm_input_enabled() ? '' : "style='display:none;'";

			if ( $is_sub_label_above ) {
				return "<div class='ginput_complex$class_suffix ginput_container ginput_container_password' id='{$field_id}_container'>
						<span id='{$field_id}_1_container' class='ginput_left'>
							<label for='{$field_id}' {$sub_label_class_attribute} {$confirm_style}>{$enter_password_label}</label>
							<input type='password' name='input_{$id}' id='{$field_id}' {$onkeyup} {$onchange} value='{$password_value}' {$first_tabindex} {$enter_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$enter_password_toggle}
						</span>
						<span id='{$field_id}_2_container' class='ginput_right' {$confirm_style}>
							<label for='{$field_id}_2' {$sub_label_class_attribute}>{$confirm_password_label}</label>
							<input type='password' name='input_{$id}_2' id='{$field_id}_2' {$onkeyup} {$onchange} value='{$confirmation_value}' {$last_tabindex} {$confirm_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$confirm_password_toggle}
						</span>
						<div class='gf_clear gf_clear_complex'></div>
					</div>{$strength}";
			} else {
				return "<div class='ginput_complex$class_suffix ginput_container ginput_container_password' id='{$field_id}_container'>
						<span id='{$field_id}_1_container' class='ginput_left'>
							<input type='password' name='input_{$id}' id='{$field_id}' {$onkeyup} {$onchange} value='{$password_value}' {$first_tabindex} {$enter_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$enter_password_toggle}
							<label for='{$field_id}' {$sub_label_class_attribute} {$confirm_style}>{$enter_password_label}</label>
						</span>
						<span id='{$field_id}_2_container' class='ginput_right' {$confirm_style}>
							<input type='password' name='input_{$id}_2' id='{$field_id}_2' {$onkeyup} {$onchange} value='{$confirmation_value}' {$last_tabindex} {$confirm_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$confirm_password_toggle}
							<label for='{$field_id}_2' {$sub_label_class_attribute}>{$confirm_password_label}</label>
						</span>
						<div class='gf_clear gf_clear_complex'></div>
					</div>{$strength}";
			}
		}

		if ( $this->is_confirm_input_enabled() ) {

			if ( $is_sub_label_above ) {
				return "<div class='ginput_complex$class_suffix ginput_container ginput_container_password' id='{$field_id}_container'>
						<span id='{$field_id}_1_container' class='ginput_left'>
							<label for='{$field_id}' {$sub_label_class_attribute}>{$enter_password_label}</label>
							<input type='password' name='input_{$id}' id='{$field_id}' {$onkeyup} {$onchange} value='{$password_value}' {$first_tabindex} {$enter_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$enter_password_toggle}
						</span>
						<span id='{$field_id}_2_container' class='ginput_right'>
							<label for='{$field_id}_2' {$sub_label_class_attribute}>{$confirm_password_label}</label>
							<input type='password' name='input_{$id}_2' id='{$field_id}_2' {$onkeyup} {$onchange} value='{$confirmation_value}' {$last_tabindex} {$confirm_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$confirm_password_toggle}
						</span>
						<div class='gf_clear gf_clear_complex'></div>
					</div>{$strength}";
			} else {
				return "<div class='ginput_complex$class_suffix ginput_container ginput_container_password' id='{$field_id}_container'>
						<span id='{$field_id}_1_container' class='ginput_left'>
							<input type='password' name='input_{$id}' id='{$field_id}' {$onkeyup} {$onchange} value='{$password_value}' {$first_tabindex} {$enter_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$enter_password_toggle}
							<label for='{$field_id}' {$sub_label_class_attribute}>{$enter_password_label}</label>
						</span>
						<span id='{$field_id}_2_container' class='ginput_right'>
							<input type='password' name='input_{$id}_2' id='{$field_id}_2' {$onkeyup} {$onchange} value='{$confirmation_value}' {$last_tabindex} {$confirm_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$confirm_password_toggle}
							<label for='{$field_id}_2' {$sub_label_class_attribute}>{$confirm_password_label}</label>
						</span>
						<div class='gf_clear gf_clear_complex'></div>
					</div>{$strength}";
			}

		} else {
			$class    = esc_attr( $class );

			return "<div class='ginput_container ginput_container_password'>
						<span id='{$field_id}_1_container' class='{$size}'>
							<input type='password' name='input_{$id}' id='{$field_id}' {$onkeyup} {$onchange} value='{$password_value}' {$first_tabindex} {$enter_password_placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
							{$enter_password_toggle}
						</span>
						<div class='gf_clear gf_clear_complex'></div>
					</div>{$strength}";

		}

	}

	public function get_field_label_class(){
		return 'gfield_label gfield_label_before_complex';
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		/**
		 * A filter to allow the password to be encrypted (default set to false)
		 *
		 * @param bool Whether to encrypt the Password field with true or false
		 * @param array $form The Current Form Object
		 */
		$encrypt_password = apply_filters( 'gform_encrypt_password', false, $this, $form );
		if ( $encrypt_password ) {
			$value = GFCommon::openssl_encrypt( $value );
			GFFormsModel::set_openssl_encrypted_fields( $lead_id, $this->id );
		}

		return $value;
	}

	/**
	 * @deprecated 2.4.16
	 *
	 * @param $entry
	 * @param $form
	 */
	public static function delete_passwords( $entry, $form ) {
		$password_fields = GFAPI::get_fields_by_type( $form, array( 'password' ) );

		$field_ids = array();

		$encrypted_fields = GFFormsModel::get_openssl_encrypted_fields( $entry['id'] );

		foreach ( $password_fields as $password_field ) {
			$field_ids[] = $password_field->id;
			GFAPI::update_entry_field( $entry['id'], $password_field->id, '' );

			$key = array_search( $password_field->id, $encrypted_fields );
			if ( $key !== false ) {
				unset( $encrypted_fields[ $key ] );
			}
		}

		if ( empty( $encrypted_fields ) ) {
			gform_delete_meta( $entry['id'], '_openssl_encrypted_fields' );
		} else {
			gform_update_meta( $entry['id'], '_openssl_encrypted_fields', $encrypted_fields );
		}

	}

	/**
	 * Removes the "for" attribute in the field label.
	 * Inputs are only allowed one label (a11y) and the inputs already have labels.
	 *
	 * @since  2.4
	 * @access public
	 *
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_first_input_id( $form ) {
		return '';
	}

	/**
	 * Determines if the Confirm Password input is enabled.
	 *
	 * @since 2.4.15
	 *
	 * @return bool
	 */
	private function is_confirm_input_enabled() {

		// Get Confirm Password input.
		$confirm_input = GFFormsModel::get_input( $this, $this->id . '.2' );

		return isset( $confirm_input['isHidden'] ) ? ! $confirm_input['isHidden'] : true;

	}

	/**
	 * Passwords are not saved to the database and won't be available in the runtime $entry object unless we stash and
	 * rehydrate them into the $entry object after it has been retrieved from the database.
	 *
	 * @since 2.4.16
	 *
	 * @param $form
	 */
	public static function stash_passwords( $form ) {
		foreach( $form['fields'] as $field ) {
			/* @var GF_Field $field */
			if ( $field->get_input_type() == 'password' ) {
				self::$passwords[ $field->id ] = $field->get_value_submission( rgpost( 'gform_field_values' ) );
			}
		}
	}

	/**
	 * Hydrate the stashed passwords back into the runtime $entry object that has just been saved and retrieved from the
	 * database.
	 *
	 * @since 2.4.16
	 *
	 * @param $entry
	 *
	 * @return array $entry
	 */
	public static function hydrate_passwords( $entry ) {
		foreach( self::$passwords as $field_id => $password ) {
			$entry[ $field_id ] = $password;
		}
		// Reset passwords so they are not available for the next submission in multi-submission requests (only possible via API).
		self::$passwords = array();
		return $entry;
	}

}

GF_Fields::register( new GF_Field_Password() );
