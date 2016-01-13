<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

add_action( 'gform_after_submission', array( 'GF_Field_Password', 'delete_passwords' ), 100, 2 );

class GF_Field_Password extends GF_Field {

	public $type = 'password';

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
			'rules_setting',
			'input_placeholders_setting',
			'sub_labels_setting',
			'sub_label_placement_setting',
			'description_setting',
			'css_class_setting',
			'password_strength_setting',
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
		if ( $password != $confirm ) {
			$this->failed_validation  = true;
			$this->validation_message = esc_html__( 'Your passwords do not match.', 'gravityforms' );
		} elseif ( $this->passwordStrengthEnabled && ! empty( $this->minPasswordStrength ) && ! empty( $password ) ) {
			$strength = $_POST[ 'input_' . $this->id . '_strength' ];

			$levels = array( 'short' => 1, 'bad' => 2, 'good' => 3, 'strong' => 4 );
			if ( $levels[ $strength ] < $levels[ $this->minPasswordStrength ] ) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $this->errorMessage ) ? sprintf( esc_html__( 'Your password does not meet the required strength. %sHint: To make it stronger, use upper and lower case letters, numbers and symbols like ! " ? $ %% ^ & ).', 'gravityforms' ), '<br />' ) : $this->errorMessage;
			}
		}
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

		$class_suffix = $is_entry_detail ? '_admin' : '';

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


		$enter_password_placeholder_attribute   = GFCommon::get_input_placeholder_attribute( $enter_password_field_input );
		$confirm_password_placeholder_attribute = GFCommon::get_input_placeholder_attribute( $confirm_password_field_input );

		if ( $is_sub_label_above ) {
			return "<div class='ginput_complex$class_suffix ginput_container ginput_container_password' id='{$field_id}_container'>
					<span id='{$field_id}_1_container' class='ginput_left'>
						<label for='{$field_id}' {$sub_label_class_attribute}>{$enter_password_label}</label>
						<input type='password' name='input_{$id}' id='{$field_id}' {$onkeyup} {$onchange} value='{$password_value}' {$first_tabindex} {$enter_password_placeholder_attribute} {$disabled_text}/>
					</span>
					<span id='{$field_id}_2_container' class='ginput_right'>
						<label for='{$field_id}_2' {$sub_label_class_attribute}>{$confirm_password_label}</label>
						<input type='password' name='input_{$id}_2' id='{$field_id}_2' {$onkeyup} {$onchange} value='{$confirmation_value}' {$last_tabindex} {$confirm_password_placeholder_attribute} {$disabled_text}/>
					</span>
					<div class='gf_clear gf_clear_complex'></div>
				</div>{$strength}";
		} else {
			return "<div class='ginput_complex$class_suffix ginput_container ginput_container_password' id='{$field_id}_container'>
					<span id='{$field_id}_1_container' class='ginput_left'>
						<input type='password' name='input_{$id}' id='{$field_id}' {$onkeyup} {$onchange} value='{$password_value}' {$first_tabindex} {$enter_password_placeholder_attribute} {$disabled_text}/>
						<label for='{$field_id}' {$sub_label_class_attribute}>{$enter_password_label}</label>
					</span>
					<span id='{$field_id}_2_container' class='ginput_right'>
						<input type='password' name='input_{$id}_2' id='{$field_id}_2' {$onkeyup} {$onchange} value='{$confirmation_value}' {$last_tabindex} {$confirm_password_placeholder_attribute} {$disabled_text}/>
						<label for='{$field_id}_2' {$sub_label_class_attribute}>{$confirm_password_label}</label>
					</span>
					<div class='gf_clear gf_clear_complex'></div>
				</div>{$strength}";
		}

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
			$value = GFCommon::encrypt( $value );
			GFFormsModel::set_encrypted_fields( $lead_id, $this->id );
		}

		return $value;
	}


	public static function delete_passwords( $entry, $form ) {

		$password_fields = GFAPI::get_fields_by_type( $form, array( 'password' ) );

		foreach ( $password_fields as $password_field ) {
			GFAPI::update_entry_field( $entry['id'], $password_field['id'], '' );
		}
	}
}

GF_Fields::register( new GF_Field_Password() );