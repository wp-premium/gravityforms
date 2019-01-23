<?php

/**
 * The Gravity Forms Query Call class.
 */
class GF_Query_Call {
	/**
	 * @var string The function name.
	 */
	private $_function_name = null;

	/**
	 * @var array The parameters.
	 */
	private $_parameters = array();

	/**
	 * A function call.
	 *
	 * @param string $function_name The function to call.
	 * @param array $parameters The function parameters. Default: []
	 */
	public function __construct( $function_name, $parameters = array() ) {
		$this->_function_name = $function_name;
		$this->_parameters = $parameters;
	}

	/**
	 * Generate the SQL.
	 *
	 * The default behavior is to just plop function_name( implode( ', ', $parameters ) ).
	 * For other cases, like CAST, check the derived classes.
	 *
	 * @param GF_Query $query The query.
	 *
	 * @return string The generated SQL.
	 */
	public function sql( $query ) {
		if ( method_exists( $this, strtolower( $this->function_name ) . '_sql' ) ) {
			return call_user_func( array( $this, strtolower( $this->function_name ) . '_sql' ), $query );
		}
		return sprintf( "{$this->function_name}(%s)", implode( ', ', $this->parameters ) );
	}

	/**
	 * A cast call.
	 *
	 * @param GF_Query_Column $column The column to cast.
	 * @param string $type The type to cast to.
	 *
	 * @return GF_Query_Call|null instance or null.
	 */
	public static function CAST( $column, $type ) {
		if ( ! $column instanceof GF_Query_Column ) {
			return null;
		}

		return new self( 'CAST', array( $column, $type ) );
	}

	/**
	 * A RAND call.
	 *
	 * @return GF_Query_Call|null instance or null.
	 */
	public static function RAND( ) {
		return new self( 'RAND' );
	}

	/**
	 * Generate the RAND call SQL.
	 *
	 * @return string The generated SQL.
	 */
	private function rand_sql() {
		return 'RAND()';
	}

	/**
	 * Generate the CAST call SQL.
	 *
	 * @param GF_Query $query The query.
	 *
	 * @return string The generated SQL.
	 */
	private function cast_sql( $query ) {
		if ( ! $this->parameters || count( $this->parameters ) != 2 ) {
			return '';
		}

		list( $column, $type ) = $this->parameters;

		if ( ! in_array( $type, array( GF_Query::TYPE_SIGNED, GF_Query::TYPE_UNSIGNED, GF_Query::TYPE_DECIMAL ) ) ) {
			return '';
		}

		if ( ! $column->field_id || ! $column->source ) {
			return '';
		}

		if ( GF_Query::TYPE_DECIMAL === $type ) {
			$type = 'DECIMAL(65, 6)'; // @todo make the decimal point configurable one day
		}

		$id = $column->is_entry_column() ? $column->field_id : 'meta_value';
		return sprintf( 'CAST(`%s`.`%s` AS %s)', $query->_alias( $column->field_id, $column->source, 'c' ), $id, $type );
	}

	/**
	 * Retrieve all the columns from the parameters of this call.
	 *
	 * @return array The columns.
	 */
	private function get_columns() {
		$columns = array();
		foreach ( $this->parameters as $p ) {
			if ( $p instanceof GF_Query_Column ) {
				$columns[] = $p;
			}
		}
		return $columns;
	}

	/**
	 * Proxy read-only values.
	 */
	public function __get( $key ) {
		switch ( $key ):
			case 'parameters':
				return $this->_parameters;
			case 'function_name':
				return $this->_function_name;
			case 'columns':
				return $this->get_columns();
		endswitch;
	}
}
