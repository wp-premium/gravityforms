<?php

/**
 * The Gravity Forms Query Series class.
 *
 * A list of arguments. Would have named "List" but it's a reserved keyword.
 */
class GF_Query_Series {
	/**
	 * @var array A series of values.
	 */
	private $_values = array();

	/**
	 * A series of expressions.
	 *
	 * @param mixed[] $values With a valid expression type (GF_Query_Literal, GF_Query_Column, GF_Query_Call)
	 */
	public function __construct( $values ) {
		if ( is_array( $values ) ) {
			$this->_values = array_filter( $values, array( 'GF_Query_Condition', 'is_valid_expression_type' ) );
		}
	}

	/**
	 * Get SQL for this.
	 *
	 * @param GF_Query $query     The query.
	 * @param string           $delimiter The delimiter to stick the series values with.
	 *
	 * @return string The SQL.
	 */
	public function sql( $query, $delimiter = '' ) {
		$values = array();

		foreach( $this->_values as $value ) {
			$values[] = $value->sql( $query );
		}

		$chunks = array_filter( $values, 'strlen' );

		return implode( $delimiter, $chunks);
	}

	/**
	 * Proxy read-only values.
	 */
	public function __get( $key ) {
		switch ( $key ):
			case 'values':
				return $this->_values;
		endswitch;
	}
}
