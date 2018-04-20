<?php

/**
 * The Gravity Forms Query Literal class.
 */
class GF_Query_Literal {
	/**
	 * @var int|string|float The value.
	 */
	private $_value;

	/**
	 * A literal value.
	 *
	 * @param int|string|float $value
	 */
	public function __construct( $value ) {
		if ( is_int( $value ) || is_string( $value ) || is_float( $value ) ) {
			$this->value = $value;
		}
	}

	/**
	 * Get SQL for this.
	 *
	 * @param GF_Query $query The query.
	 * @param string $delimiter The delimiter for arrays.
	 *
	 * @return string The SQL.
	 */
	public function sql( $query, $delimiter = '' ) {
		global $wpdb;

		if ( is_int( $this->value ) ) {
			return $wpdb->prepare( '%d', $this->value );
		} elseif ( is_double( $this->value ) ) {
			return $this->value;
		} elseif ( is_string( $this->value ) || is_float( $this->value ) ) {
			return $wpdb->prepare( '%s', $this->value );
		}

		/**
		 * @todo Add support for Column, Call
		 */

		return '';
	}

	/**
	 * Proxy read-only values.
	 */
	public function __get( $key ) {
		switch ( $key ) :
			case 'value':
				return $this->_value;
		endswitch;
	}
}
