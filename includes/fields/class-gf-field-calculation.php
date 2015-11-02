<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Calculation extends GF_Field {

	public $type = 'calculation';

	function get_form_editor_field_settings() {
		return array(
			'disable_quantity_setting',
			'rules_setting',
			'duplicate_setting',
			'calculation_setting',
			'conditional_logic_field_setting',
		);
	}

	public function get_form_editor_button() {
		return array();
	}

	public function validate( $value, $form ) {
		$quantity_id = $this->id . '.3';
		$quantity    = rgget( $quantity_id, $value );

		if ( $this->isRequired && rgblank( $quantity ) && ! $this->disableQuantity ) {
			$this->failed_validation  = true;
			$this->validation_message = empty($this->errorMessage) ? esc_html__( 'This field is required.', 'gravityforms' ) : $this->errorMessage;
		} elseif ( ! empty( $quantity ) && ( ! is_numeric( $quantity ) || intval( $quantity ) != floatval( $quantity ) || intval( $quantity ) < 0 ) ) {
			$this->failed_validation  = true;
			$this->validation_message = esc_html__( 'Please enter a valid quantity', 'gravityforms' );
		}
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id          = (int) $this->id;
		$field_id    = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$product_name = ! is_array( $value ) || empty( $value[ $this->id . '.1' ] ) ? esc_attr( $this->label ) : esc_attr( $value[ $this->id . '.1' ] );
		$price        = ! is_array( $value ) || empty( $value[ $this->id . '.2' ] ) ? $this->basePrice : esc_attr( $value[ $this->id . '.2' ] );
		$quantity     = is_array( $value ) ? esc_attr( $value[ $this->id . '.3' ] ) : '';

		if ( empty( $price ) ) {
			$price = 0;
		}

		$has_quantity = sizeof( GFCommon::get_product_fields_by_type( $form, array( 'quantity' ), $this->id ) ) > 0;
		if ( $has_quantity ) {
			$this->disableQuantity = true;
		}

		$currency = $is_entry_detail && ! empty( $entry ) ? $entry['currency'] : '';

		$quantity_field = '';

		$qty_input_type = GFFormsModel::is_html5_enabled() ? 'number' : 'text';

		$product_quantity_sub_label = gf_apply_filters( 'gform_product_quantity', $form_id, esc_html__( 'Quantity:', 'gravityforms' ), $form_id );

		if ( $is_entry_detail || $is_form_editor  ) {
			$style          = $this->disableQuantity ? "style='display:none;'" : '';
			$quantity_field = " <span class='ginput_quantity_label' {$style}>{$product_quantity_sub_label}</span> <input type='{$qty_input_type}' name='input_{$id}.3' value='{$quantity}' id='ginput_quantity_{$form_id}_{$this->id}' class='ginput_quantity' size='10' />";
		} elseif ( ! $this->disableQuantity ) {
			$tabindex  = $this->get_tabindex();
			$quantity_field .= " <span class='ginput_quantity_label'>" . $product_quantity_sub_label . "</span> <input type='{$qty_input_type}' name='input_{$id}.3' value='{$quantity}' id='ginput_quantity_{$form_id}_{$this->id}' class='ginput_quantity' size='10' {$tabindex}/>";
		} else {
			if ( ! is_numeric( $quantity ) ) {
				$quantity = 1;
			}

			if ( ! $has_quantity ) {
				$quantity_field .= "<input type='hidden' name='input_{$id}.3' value='{$quantity}' class='ginput_quantity_{$form_id}_{$this->id} gform_hidden' />";
			}
		}

		return "<div class='ginput_container ginput_container_product_calculation'>
					<input type='hidden' name='input_{$id}.1' value='{$product_name}' class='gform_hidden' />
					<span class='ginput_product_price_label'>" . gf_apply_filters( 'gform_product_price', $form_id, esc_html__( 'Price', 'gravityforms' ), $form_id ) . ":</span> <span class='ginput_product_price' id='{$field_id}'>" . esc_html( GFCommon::to_money( $price, $currency ) ) . "</span>
					<input type='hidden' name='input_{$id}.2' id='ginput_base_price_{$form_id}_{$this->id}' class='gform_hidden' value='" . esc_attr( $price ) . "'/>
					{$quantity_field}
				</div>";
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( is_array( $value ) && ! empty( $value ) ) {
			$product_name = trim( $value[ $this->id . '.1' ] );
			$price        = trim( $value[ $this->id . '.2' ] );
			$quantity     = trim( $value[ $this->id . '.3' ] );

			$product = $product_name . ', ' . esc_html__( 'Qty: ', 'gravityforms' ) . $quantity . ', ' . esc_html__( 'Price: ', 'gravityforms' ) . $price;

			return $product;
		} else {
			return '';
		}
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		// ignore submitted value and recalculate price in backend
		list( $prefix, $field_id, $input_id ) = rgexplode( '_', $input_name, 3 );
		if ( $input_id == 2 ) {
			require_once( GFCommon::get_base_path() . '/currency.php' );
			$currency = new RGCurrency( GFCommon::get_currency() );
			$lead     = empty( $lead ) ? RGFormsModel::get_lead( $lead_id ) : $lead;
			$value    = $currency->to_money( GFCommon::calculate( $this, $form, $lead ) );
		}
		return $value;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->enableCalculation = (bool) $this->enableCalculation;

	}


}

GF_Fields::register( new GF_Field_Calculation() );