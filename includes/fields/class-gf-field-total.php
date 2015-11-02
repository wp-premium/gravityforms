<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Total extends GF_Field {

	public $type = 'total';

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'label_setting',
			'admin_label_setting',
			'label_placement_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function get_form_editor_field_title() {
		return esc_attr__( 'Total', 'gravityforms' );
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id          = (int) $this->id;
		$field_id    = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		if ( $is_entry_detail ) {
			return "<div class='ginput_container ginput_container_total'>
						<input type='text' name='input_{$id}' value='{$value}' />
					</div>";
		} else {
			return "<div class='ginput_container ginput_container_total'>
						<span class='ginput_total ginput_total_{$form_id}'>" . GFCommon::to_money( '0' ) . "</span>
						<input type='hidden' name='input_{$id}' id='{$field_id}' class='gform_hidden'/>
					</div>";
		}

	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		return GFCommon::to_money( $value, $currency );
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		$lead  = empty( $lead ) ? RGFormsModel::get_lead( $lead_id ) : $lead;
		$value = GFCommon::get_order_total( $form, $lead );

		return $value;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		return GFCommon::to_money( $value, $entry['currency'] );
	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$format_numeric = $modifier == 'price';

		$value = $format_numeric ? GFCommon::to_number( $value ) : GFCommon::to_money( $value );

		return GFCommon::format_variable_value( $value, $url_encode, $esc_html, $format );
	}


}

GF_Fields::register( new GF_Field_Total() );