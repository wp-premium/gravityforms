<?php

/**
 * The Gravity Forms Query Column class.
 */
class GF_Query_Column {

	/**
	 * @const Identifier for searching across any meta field.
	 */
	const META = '{{ANY META FIELD}}';

	/**
	 * @var string The field ID.
	 */
	private $_field_id;

	/**
	 * @var int|array The source.
	 */
	private $_source;

	/**
	 * @var string|false The alias override (for meta rows)
	 */
	private $_alias = false;

	/**
	 * Represents a column.
	 *
	 * @param string $field_id The field ID (meta key or column name).
	 * @param int|array $source The source this field is referencing.
	 * @param string|bool $alias An alias override. Default: false.
	 */
	public function __construct( $field_id, $source = 0, $alias = false ) {
		if ( ! ( is_int( $source ) || is_array( $source ) ) ) {
			return;
		}

		$this->_field_id = $field_id;
		$this->_source = $source;
		$this->_alias = $alias;
	}

	/**
	 * Get some SQL for this column.
	 *
	 * @param $query GF_Query The query.
	 *
	 * @return string The SQL.
	 */
	public function sql( $query ) {
		if ( ! $query instanceof GF_Query ) {
			return '';
		}

		if ( ! $this->field_id ) {
			return '';
		}

		if ( $this->is_entry_column() ) {
			return sprintf( '`%s`.`%s`', $this->alias ? $this->alias : $query->_alias( null, $this->source ), $this->field_id );
		} else if ( $this->is_meta_column() ) {
			return sprintf( '`%s`.`%s`', $this->alias ? $this->alias : $query->_alias( $this->field_id, $this->source, 'm' ), $this->field_id );
		}

		return '';
	}

	/**
	 * Whether this field is an entry column.
	 *
	 * @return boolean An entry column or not.
	 */
	public function is_entry_column() {
		if ( ! $this->field_id ) {
			return false;
		}

		static $entry_columns  = array();
		if ( empty( $entry_columns ) ) {
			global $wpdb;
			$entry_columns = wp_list_pluck( $wpdb->get_results( 'SHOW COLUMNS FROM ' . GFFormsModel::get_entry_table_name(), ARRAY_A ), 'Field' );
		}
		return in_array( $this->field_id, $entry_columns );
	}

	/**
	 * Whether this field is a meta column.
	 *
	 * @return boolean A meta column or not.
	 */
	public function is_meta_column() {
		if ( ! $this->field_id ) {
			return false;
		}

		return in_array( $this->field_id, array( 'meta_key', 'meta_value' ) );
	}

	/**
	 * Proxy read-only values.
	 */
	public function __get( $key ) {
		switch ( $key ):
			case 'field_id':
				return $this->_field_id;
			case 'source':
				return $this->_source;
			case 'alias':
				return $this->_alias;
		endswitch;
	}
}
