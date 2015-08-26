<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_MultiSelect extends GF_Field {

	public $type = 'multiselect';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Multi Select', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'enable_enhanced_ui_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'size_setting',
			'choices_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$logic_event   = $this->get_conditional_logic_event( 'keyup' );
		$size          = $this->size;
		$class_suffix  = $is_entry_detail ? '_admin' : '';
		$class         = $size . $class_suffix;
		$css_class     = trim( esc_attr( $class ) . ' gfield_select' );
		$tabindex      = $this->get_tabindex();
		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$placeholder = $this->enableEnhancedUI ? "data-placeholder='" . esc_attr( gf_apply_filters( 'gform_multiselect_placeholder', $form_id, esc_attr__( 'Click to select...', 'gravityforms' ), $form_id ) ) . "'" : '';

		$size = $this->multiSelectSize;
		if ( empty( $size ) ) {
			$size = 7;
		}

		return sprintf( "<div class='ginput_container'><select multiple='multiple' {$placeholder} size='{$size}' name='input_%d[]' id='%s' {$logic_event} class='%s' $tabindex %s>%s</select></div>", $id, esc_attr( $field_id ), $css_class, $disabled_text, $this->get_choices( $value ) );
	}

	public function get_choices( $value ) {
		return GFCommon::get_select_choices( $this, $value );
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		// add space after comma-delimited values
		return implode( ', ', explode( ',', $value ) );
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( empty( $value ) || $format == 'text' ) {
			return $value;
		}

		$value = explode( ',', $value );

		$items = '';
		foreach ( $value as $item ) {
			$items .= '<li>' . GFCommon::selection_display( $item, $this, $currency, $use_text ) . '</li>';
		}

		return "<ul class='bulleted'>{$items}</ul>";
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		return empty( $value ) ? '' : is_array( $value ) ? implode( ',', $value ) : $value;
	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		if ( $this->type == 'post_category' ) {
			$use_id = $modifier == 'id';
			$items  = explode( ',', $value );

			if ( is_array( $items ) ) {
				$cats = array();
				foreach ( $items as $item ) {
					$cat    = GFCommon::format_post_category( $item, $use_id );
					$cats[] = GFCommon::format_variable_value( $cat, $url_encode, $esc_html, $format );
				}
				$value = GFCommon::implode_non_blank( ', ', $cats );
			}
		}
		return $value;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->enableEnhancedUI = (bool) $this->enableEnhancedUI;

		if ( $this->type === 'post_category' ) {
			$this->displayAllCategories = (bool) $this->displayAllCategories;
		}
	}

	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		$value  = rgar( $entry, $input_id );

		if ( ! empty( $value ) && ! $is_csv ) {
			$items = explode( ',', $value );

			foreach ( $items as &$item ) {
				$item = GFCommon::selection_display( $item, $this, rgar( $entry, 'currency' ), $use_text );
			}

			$value = GFCommon::implode_non_blank( ', ', $items );
		}

		return $value;
	}

}

GF_Fields::register( new GF_Field_MultiSelect() );