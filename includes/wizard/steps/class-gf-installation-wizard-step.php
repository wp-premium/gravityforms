<?php

abstract class GF_Installation_Wizard_Step extends stdClass {

	protected $_name = '';

	protected $_field_validation_results = array();
	protected $_validation_summary = '';

	public $defaults = array();
	private $_step_values;

	function __construct( $values = array() ){
		if ( empty ( $this->_name ) ) {
			throw new Exception( 'Name not set' );
		}
		$this->_step_values = empty ( $values ) ? $this->defaults : $values;
	}

	function get_name(){
		return $this->_name;
	}

	function is( $key ) {
		return $key == $this->get_name();
	}

	function get_title(){
		return '';
	}

	public function __set( $key, $value ) {
		$this->_step_values[ $key ] = $value;
	}

	public function __isset( $key ) {
		return isset( $this->_step_values[ $key ] );
	}

	public function __unset( $key ) {
		unset( $this->_step_values[ $key ] );
	}

	function &__get( $key ){
		if ( ! isset( $this->_step_values[ $key ] ) ) {
			$this->_step_values[ $key ] = '';
		}
		return $this->_step_values[ $key ];
	}

	function get_values(){
		$set_values = $this->_step_values ? $this->_step_values : array();
		$values = array_merge( $this->defaults, $set_values);
		return $values;
	}

	function display(){
	}

	function validate(){
		// Assign $this->_validation_result;
		return true;
	}

	function get_field_validation_result( $key ){
		if ( ! isset( $this->_field_validation_results[ $key ] ) ) {
			$this->_field_validation_results[ $key ] = '';
		}
		return $this->_field_validation_results[ $key ];
	}

	function set_field_validation_result( $key, $text ){
		$this->_field_validation_results[ $key ] = $text;
	}

	function set_validation_summary( $text ) {
		$this->_validation_summary = $text;
	}

	function get_validation_summary(){
		return $this->_validation_summary;
	}

	function validation_message( $key, $echo = true ){
		$message = '';
		$validation_result = $this->get_field_validation_result( $key );
		if ( ! empty ( $validation_result ) ) {

			$message = sprintf( '<div class="validation_message">%s</div>', $validation_result );
		}

		if ( $echo ) {
			echo $message;
		}
		return $message;
	}

	function is_complete(){
	}

	function get_next_button_text(){
		return __( 'Next', 'gravityforms' );
	}

	function get_previous_button_text(){
		return __( 'Back', 'gravityforms' );
	}

	function update( $posted_values = array() ){
		$step_values = $this->get_values();
		if ( empty ( $step_values ) ) {
			$step_values = array();
		}
		$new_values = array_merge( $step_values, $posted_values );
		update_option( 'gform_installation_wizard_' . $this->get_name(), $new_values );
		$this->_step_values = $new_values;
	}

	function summary( $echo = true ){
		return '';
	}

	function install(){
		// do something
	}

	function flush_values(){
		delete_option( 'gform_installation_wizard_' . $this->get_name() );
	}

	function get_posted_values() {

		$posted_values = stripslashes_deep( $_POST );
		$values        = array();
		foreach ( $posted_values as $key => $value ) {
			if ( strpos( $key, '_', 0 ) !== 0 ) {
				$values[ $key ] = $value;
			}
		}
		$values = array_merge( $this->defaults, $values);
		return $values;
	}
}