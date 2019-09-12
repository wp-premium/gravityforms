<?php

if ( ! class_exists( 'GF_Query_Call' ) ) {
	require_once( 'class-gf-query-call.php' );
}

if ( ! class_exists( 'GF_Query_Column' ) ) {
	require_once( 'class-gf-query-column.php' );
}

if ( ! class_exists( 'GF_Query_Condition' ) ) {
	require_once( 'class-gf-query-condition.php' );
}

if ( ! class_exists( 'GF_Query_Literal' ) ) {
	require_once( 'class-gf-query-literal.php' );
}

if ( ! class_exists( 'GF_Query_JSON_Literal' ) ) {
	require_once( 'class-gf-query-json-literal.php' );
}

if ( ! class_exists( 'GF_Query_Series' ) ) {
	require_once( 'class-gf-query-series.php' );
}

/**
 * The Gravity Forms Query Builder class.
 *
 * @internal
 *
 * @since 2.3
 */
class GF_Query {


	/**
	 * @var null|int Holds the total number of entries found after calling get()
	 */
	public $total_found = null;

	/**
	 * @var array An array of Form IDs
	 */
	private $from;

	/**
	 * @var array Join clauses.
	 */
	private $joins = array();

	/**
	 * @var array All the where clauses.
	 */
	private $where = array();

	/**
	 * @var array All the order clauses.
	 */
	private $order = array();

	/**
	 * @var int The limit of entries returned.
	 */
	private $limit = 0;

	/**
	 * @var int The offset of entries returned.
	 */
	private $offset = 0;

	/**
	 * @var array The table aliases.
	 */
	private $aliases = array();

	/**
	 * @var string The queries executed against the database and their times.
	 */
	private $queries = array();

	/**
	 * @var float An internal timer. Used for instrumentation purposes.
	 */
	private $timer = 0.0;

	/**
	 * @var array An array of inferred joins used by _join_infer().
	 */
	private $_inferred_joins = array();

	/**
	 * @const null Nothing.
	 */
	const NOOP = null;

	/**
	 * @const string (ORDER BY) ASC operator.
	 */
	const ASC = 'ASC';

	/**
	 * @const string (ORDER BY) DESC operator.
	 */
	const DESC = 'DESC';

	/**
	 * @const string SIGNED type.
	 */
	const TYPE_SIGNED = 'SIGNED';

	/**
	 * @const string UNSIGNED type.
	 */
	const TYPE_UNSIGNED = 'UNSIGNED';

	/**
	 * @const string DECIMAL type.
	 */
	const TYPE_DECIMAL = 'DECIMAL';

	/**
	 * GF_Query constructor.
	 *
	 * @param null|int|array $form_ids
	 * @param null|array     $search_criteria
	 * @param null|array     $sorting
	 * @param null|array     $paging
	 */
	public function __construct( $form_ids = null, $search_criteria = null, $sorting = null, $paging = null ) {
		if ( ! is_null( $search_criteria ) || ! is_null( $form_ids ) || ! empty( $sorting ) || ! empty( $paging ) ) {
			$this->parse( $form_ids, $search_criteria, $sorting, $paging );
		}
	}

	/**
	 * Parses the Search Criteria args.
	 *
	 * @param int|array $form_id
	 * @param array     $search_criteria
	 * @param null      $sorting
	 * @param null      $paging
	 */
	public function parse( $form_id, $search_criteria = array(), $sorting = null, $paging = null ) {

		$page_size = isset( $paging['page_size'] ) ? $paging['page_size'] : 20;
		$offset = isset( $paging['offset'] ) ? $paging['offset'] : 0;

		$sort_field = ! empty( $sorting['key'] ) ? $sorting['key'] : 'id';
		$sort_dir = isset( $sorting['direction'] ) ? strtoupper( $sorting['direction'] ) : 'DESC';

		switch ( $sort_dir ) {
			case 'DESC':
				$sort_dir_query = self::DESC;
				break;
			case 'RAND':
				$sort_dir_query = 'RAND';
				break;
			case 'ASC':
			default:
				$sort_dir_query = self::ASC;
				break;
		}

		$from = ! is_array( $form_id ) ? array( $form_id ) : $form_id;

		$from = array_map( 'absint', $from );

		$form_id = is_array( $form_id ) ? reset( $form_id ) : $form_id;

		$form_id = absint( $form_id );

		$order = new GF_Query_Column( $sort_field, $form_id );

		$force_order_numeric = false;
		if ( ! $order->is_entry_column() ) {
			if ( isset( $sorting['is_numeric'] ) ) {
				$force_order_numeric = $sorting['is_numeric'];
			} else {
				$field = GFAPI::get_field( $form_id, $sort_field );

				if ( $field instanceof GF_Field ) {
					$force_order_numeric = $field->get_input_type() == 'number';
				} else {
					$entry_meta          = GFFormsModel::get_entry_meta( $form_id );
					$force_order_numeric = rgars( $entry_meta, $sort_field . '/is_numeric' );
				}
			}
		}

		if ( $force_order_numeric ) {
			$order = GF_Query_Call::CAST( $order, GF_Query::TYPE_DECIMAL );
		}

		$this->from( $from );

		if ( $sort_dir_query == 'RAND' ) {
			$this->order( GF_Query_Call::RAND() );
		} else {
			$this->order( $order, $sort_dir_query );
		}

		$this->limit( $page_size )
		     ->offset( $offset );

		$properties_condition = null;
		$filters_condition = null;
		$filters = array();

		if ( isset( $search_criteria['status'] ) ) {
			$property_conditions[] = new GF_Query_Condition(
				new GF_Query_Column( 'status' ),
				GF_Query_Condition::EQ,
				new GF_Query_Literal( $search_criteria['status'] )
			);
		}

		$start_date = rgar( $search_criteria, 'start_date' );
		$end_date = rgar( $search_criteria, 'end_date' );

		$column = count( $from ) > 1 ? new GF_Query_Column( 'date_created' ) : new GF_Query_Column( 'date_created', $form_id );

		if ( ! empty( $start_date ) ) {

			try {
				$start_date         = new DateTime( $search_criteria['start_date'] );
				$start_datetime_str = $start_date->format( 'Y-m-d H:i:s' );
				$start_date_str     = $start_date->format( 'Y-m-d' );
				if ( $start_datetime_str == $start_date_str . ' 00:00:00' ) {
					$start_date_str = $start_date_str . ' 00:00:00';
				} else {
					$start_date_str = $start_date->format( 'Y-m-d H:i:s' );
				}

				$start_date_str_utc = get_gmt_from_date( $start_date_str );

				$property_conditions[] = new GF_Query_Condition(
					$column,
					GF_Query_Condition::GTE,
					new GF_Query_Literal( $start_date_str_utc )
				);
			} catch ( Exception $e ) {
				GFAPI::log_error( __METHOD__ . '(): Invalid start_date; ' . $e->getMessage() );
			}

		}

		if ( ! empty( $end_date ) ) {

			try {
				$end_date         = new DateTime( $search_criteria['end_date'] );
				$end_datetime_str = $end_date->format( 'Y-m-d H:i:s' );
				$end_date_str     = $end_date->format( 'Y-m-d' );

				// extend end date till the end of the day unless a time was specified. 00:00:00 is ignored.
				if ( $end_datetime_str == $end_date_str . ' 00:00:00' ) {
					$end_date_str = $end_date->format( 'Y-m-d' ) . ' 23:59:59';
				} else {
					$end_date_str = $end_date->format( 'Y-m-d H:i:s' );
				}

				$end_date_str_utc = get_gmt_from_date( $end_date_str );

				if ( ! empty( $end_date ) ) {
					$property_conditions[] = new GF_Query_Condition(
						$column,
						GF_Query_Condition::LTE,
						new GF_Query_Literal( $end_date_str_utc )
					);
				}
			} catch ( Exception $e ) {
				GFAPI::log_error( __METHOD__ . '(): Invalid end_date; ' . $e->getMessage() );
			}

		}

		if ( ! empty( $property_conditions ) ) {
			if ( count( $property_conditions ) > 1 ) {
				$properties_condition = call_user_func_array( array( 'GF_Query_Condition', '_and' ), $property_conditions );
			} else {
				$properties_condition = $property_conditions[0];
			}
		}

		if ( ! empty( $search_criteria['field_filters'] ) ) {
			$field_filters = $search_criteria['field_filters'];
			$search_mode = isset( $field_filters['mode'] ) ? strtolower( $field_filters['mode'] ) : 'all';
			unset( $field_filters['mode'] );

			foreach ( $field_filters as $filter ) {
				$key = rgar( $filter, 'key' );

				if ( empty( $key ) ) {
					$global_condition = $this->get_global_condition( $filter, $form_id );
					$filters[] = $global_condition;
					continue;
				}

				$value = rgar( $filter, 'value' );

				$operator = isset( $filter['operator'] ) ? $filter['operator'] : GF_Query_Condition::EQ;
				$operator = strtoupper( $operator );

				switch ( $operator ) {
					case 'CONTAINS':
						$operator = GF_Query_Condition::LIKE;
						$value    = '%' . $value . '%';
						break;
					case 'IS NOT':
					case 'ISNOT':
					case '<>':
						$operator = GF_Query_Condition::NEQ;
						break;
					case 'IS':
					case '=':
						$operator = GF_Query_Condition::EQ;
						break;
					case 'LIKE':
						$operator = GF_Query_Condition::LIKE;
						break;
					case 'NOT IN':
						$operator = GF_Query_Condition::NIN;
						break;
					case 'IN':
						$operator = GF_Query_Condition::IN;
				}

				$form = GFFormsModel::get_form_meta( $form_id );
				$field = GFFormsModel::get_field( $form, $key );
				if ( $field && $operator != GF_Query_Condition::LIKE && ( $field->get_input_type() == 'number' || rgar( $filter, 'is_numeric' ) ) ) {
					if ( ! is_numeric( $value ) ) {
						$value = floatval( $value );
					}
					$filters[] = new GF_Query_Condition(
						GF_Query_Call::CAST( new GF_Query_Column( $key, $form_id ), self::TYPE_DECIMAL ),
						$operator,
						new GF_Query_Literal( $value )
					);
					continue;
				}

				if ( is_array( $value ) ) {
					foreach ( $value as &$v ) {
						$v = $field && $field->storageType == 'json' ? new GF_Query_JSON_Literal( (string) $v ) : new GF_Query_Literal( $v );
					}
					$value = new GF_Query_Series( $value );

					$filters[] = new GF_Query_Condition(
						new GF_Query_Column( $key, $form_id ),
						$operator,
						$value
					);

					continue;
				}

				if ( $key == 'date_created' && $operator == GF_Query_Condition::EQ ) {
					$search_date           = new DateTime( $value );
					$search_date_str       = $search_date->format( 'Y-m-d' );
					$date_created_start    = $search_date_str . ' 00:00:00';
					$date_create_start_utc = get_gmt_from_date( $date_created_start );
					$date_created_end      = $search_date_str . ' 23:59:59';
					$date_created_end_utc  = get_gmt_from_date( $date_created_end );

					$column = count( $from ) > 1 ? new GF_Query_Column( $key ) : new GF_Query_Column( $key, $form_id );

					$filters[] = new GF_Query_Condition(
						$column,
						GF_Query_Condition::BETWEEN,
						new GF_Query_Series( array( new GF_Query_Literal( $date_create_start_utc ), new GF_Query_Literal( $date_created_end_utc ) ) )
					);

					continue;
				}

				$literal = $field && $field->storageType == 'json' ? new GF_Query_JSON_Literal( (string) $value ) : new GF_Query_Literal( (string) $value );

				$column = count( $from ) > 1 ? new GF_Query_Column( $key ) : new GF_Query_Column( $key, $form_id );
				$filters[] = new GF_Query_Condition(
					$column,
					$operator,
					$literal
				);

			}

			$condition_mode = strtolower( $search_mode ) == 'any' ? '_or' : '_and';
			if ( count( $filters ) > 1 ) {
				$filters_condition = call_user_func_array( array( 'GF_Query_Condition', $condition_mode ), $filters );
			} elseif ( $filters ) {
				$filters_condition = $filters[0];
			}
		}

		if ( ! empty( $properties_condition ) && ! empty( $filters_condition ) ) {
			$where = call_user_func_array( array( 'GF_Query_Condition', '_and' ), array( $properties_condition, $filters_condition ) );
		} else {
			$where = ! empty( $properties_condition ) ? $properties_condition : $filters_condition;
		}

		if ( ! empty( $where ) ) {
			$this->where( $where );
		}

	}

	/**
	 * @param array     $filter
	 * @param int|array $form_ids
	 *
	 * @return GF_Query_Condition|mixed
	 */
	private function get_global_condition( $filter, $form_ids ) {

		// include choice text
		$forms = array();
		if ( $form_ids == 0 ) {
			$forms = GFAPI::get_forms();
		} elseif ( is_array( $form_ids ) ) {
			foreach ( $form_ids as $id ) {
				$forms[] = GFAPI::get_form( $id );
			}
		} else {
			$forms[] = GFAPI::get_form( $form_ids );
		}

		$original_operator = strtoupper( rgar( $filter, 'operator' ) );

		switch ( $original_operator ) {
			case 'CONTAINS':
				$operator = GF_Query_Condition::LIKE;
				break;
			case 'IS NOT':
			case 'ISNOT':
			case '<>':
				$operator = GF_Query_Condition::NEQ;
				break;
			case 'IS':
			case '=':
				$operator = GF_Query_Condition::EQ;
				break;
			case 'LIKE':
				$operator = GF_Query_Condition::LIKE;
				break;
			case 'NOT IN':
				$operator = GF_Query_Condition::NIN;
				break;
			case 'IN':
				$operator = GF_Query_Condition::IN;
				break;
			default:
				$operator = empty( $original_operator ) ? GF_Query_Condition::EQ : $original_operator;
		}

		$val = $filter['value'];

		$choice_filters = array();

		foreach ( $forms as $form ) {
			if ( isset( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					/* @var GF_Field $field */
					if ( is_array( $field->choices ) ) {
						foreach ( $field->choices as $choice ) {
							if ( ( $operator == '=' && strtolower( $choice['text'] ) == strtolower( $val ) ) || ( $operator == 'LIKE' && ! empty( $val ) && strpos( strtolower( $choice['text'] ), strtolower( $val ) ) !== false ) ) {
								if ( $field->gsurveyLikertEnableMultipleRows ) {
									$choice_value           = $choice['value'];
									$choice_search_operator = 'like';
									$choice_value = '%' . $choice_value;
								} else {
									$choice_value           = $choice['value'];
									$choice_search_operator = '=';
								}
								$choice_filters[] = new GF_Query_Condition(
									new GF_Query_Column( $field->id, $form['id'] ),
									$choice_search_operator,
									new GF_Query_Literal( $choice_value )
								);
							}
						}
					}
				}
			}
		}

		if ( is_array( $val ) ) {
			foreach ( $val as &$v ) {
				$v = new GF_Query_Literal( $v );
			}
			$val = new GF_Query_Series( $val );

			$choice_filters[] = new GF_Query_Condition(
				new GF_Query_Column( GF_Query_Column::META, 0 ),
				$operator,
				$val
			);
		} else {
			if ( $original_operator == 'CONTAINS' ) {
				$val = '%' . $val . '%';
			}
			$choice_filters[] = new GF_Query_Condition(
				new GF_Query_Column( GF_Query_Column::META, $form_ids ),
				$operator,
				new GF_Query_Literal( $val )
			);
		}

		if ( count( $choice_filters ) > 1 ) {

			$combine_operator = in_array( $operator, array( GF_Query_Condition::NEQ, GF_Query_Condition::NIN ) ) ? '_and' : '_or';

			$condition = call_user_func_array( array( 'GF_Query_Condition', $combine_operator ), $choice_filters );
		} else {
			$condition = $choice_filters[0];
		}

		return $condition;
	}

	/**
	 * Query a source.
	 *
	 * Sets the FROM clause.
	 *
	 * @param int|array The Form ID or array of IDs.
	 *
	 * @return GF_Query Chainable $this.
	 */
	public function from( $source ) {

		if ( ! is_array( $source ) ) {
			$source = array( $source );
		}

		$this->from = $source;

		/**
		 * Reserve the alias.
		 */
		$this->_alias( null, reset( $source ) );

		return $this;
	}

	/**
	 * Join on column tables. The serious stuff.
	 *
	 * LEFT JOIN $on_column.table alias ON alias.$on_column = $to_column
	 *
	 * @param GF_Query_Column $on_column The column to join on.
	 * @param GF_Query_Column $to_column The column to join the above to.
	 *
	 * @return GF_Query Chainable $this.
	 */
	public function join( $on_column, $to_column) {
		if ( $on_column instanceof GF_Query_Column && $to_column instanceof GF_Query_Column ) {
			$this->joins[] = array( $on_column, $to_column );
		}
		return $this;
	}

	/**
	 * Where something is something :)
	 *
	 * @param GF_Query_Condition $condition A condition.
	 *
	 * @return GF_Query Chainable $this.
	 */
	public function where( $condition ) {
		if ( $condition instanceof GF_Query_Condition ) {
			$this->where = $condition;
		}
		return $this;
	}

	/**
	 * Sets the order.
	 *
	 * @param GF_Query_Column|GF_Query_Call $column The field, function to order by.
	 * @param string $order The order (one of self::ASC, self::DESC or empty). Default for GF_Query_Column: self::ASC Default for GF_Query_Call: empty
	 *
	 * @return GF_Query Chainable $this.
	 */
	public function order( $column, $order = null ) {

		/**
		 * This is a function.
		 */
		if ( $column instanceof GF_Query_Call ) {

			if ( ! in_array( $order, array( self::ASC, self::DESC ) ) ) {
				$order = '';
			}

			$this->order[ spl_object_hash( $column ) . '()' ] = array( $column, $order );
		} elseif ( $column instanceof GF_Query_Column ) {

			if ( ! in_array( $order, array( self::ASC, self::DESC ) ) ) {
				$order = self::ASC;
			}

			$source_id = $column->source;
			$field_id = $column->field_id ? $column->field_id : '-';
			$this->order[ "{$source_id}_{$field_id}" ] = array( $column, $order );
		}

		return $this;
	}

	/**
	 * Sets the limit.
	 *
	 * @param int $limit The limit to set.
	 *
	 * @return GF_Query Chainable $this.
	 */
	public function limit( $limit ) {
		if ( is_numeric( $limit ) ) {
			$this->limit = $limit;
		}
		return $this;
	}

	/**
	 * Sets the offset.
	 *
	 * Use self::page() as a more convenient wrapper.
	 *
	 * @return GF_Query Chainable $this.
	 */
	public function offset( $offset ) {
		if ( is_numeric( $offset ) ) {
			$this->offset = $offset;
		}
		return $this;
	}

	/**
	 * Sets the offset from page.
	 *
	 * Calculated from self::$limit. If not set, offset will be 0.
	 *
	 * @return GF_Query Chainable $this.
	 */
	public function page( $page ) {
		if ( is_numeric( $page ) ) {
			$this->offset = max( 0, $page - 1 ) * $this->limit;
		}
		return $this;
	}

	/**
	 * Retrieve the results.
	 *
	 * @return array The resulting entries.
	 */
	public function get() {
		global $wpdb;

		$entries = array();

		$results = $this->query();

		$this->total_found = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		return $this->get_entries( $results );
	}

	/**
	 * Retrieve the IDs for the results.
	 *
	 * @return array The resulting entry IDs.
	 */
	public function get_ids() {
		global $wpdb;

		$results = $this->query();

		$this->total_found = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		$ids = array();

		foreach( $results as $result ) {
			$ids[] = $result[0];
		}

		return $ids;
	}

	/**
	 * Build the query and return the raw rows.
	 *
	 * @return array The rows.
	 */
	private function query() {
		if ( count( $this->queries ) ) {
			GFCommon::log_debug( 'Reusing GF_Query is undefined behavior. Create a new instance instead.' );
		}

		if ( ! is_array( $this->from ) || ! count( $this->from ) ) {
			return array();
		}

		global $wpdb;

		/**
		 * FROM.
		 *
		 * We always select from the entry table first.
		 *  It is our main source.
		 */
		$from = sprintf( 'FROM `%s` AS `%s`', GFFormsModel::get_entry_table_name(), $this->_alias( null, reset( $this->from ) ) );

		/**
		 * ORDER.
		 */
		$order = '';
		if ( ! empty( $this->order ) && $orders = $this->_order_generate( $this->order ) ) {
			$order = 'ORDER BY ' . implode( ', ', $orders );
		}

		/**
		 * JOIN.
		 */
		$joins = array_merge( $this->_join_infer( $this->where ), $this->_join_infer_orders( $this->where, $this->order ), $this->_join_generate( $this->joins ) );
		$join = count( $joins ) ? 'LEFT JOIN ' . implode( ' LEFT JOIN ', $this->_prime_joins( $joins ) ) : '';

		/**
		 * SELECT.
		 */
		$select = sprintf( 'SELECT SQL_CALC_FOUND_ROWS DISTINCT %s', implode( ', ', $this->_select_infer( $this->joins ) ) );

		$form_ids = array();
		foreach ( $this->from as $f ) {
			$f = new GF_Query_Literal( $f );
			if ( $f->value ) {
				$form_ids[] = $f;
			}
		}

		if ( $form_ids ) {
			$this->where( GF_Query_Condition::_and(
			/**
			 * Prepend the selected form IDs.
			 */
				new GF_Query_Condition(
					new GF_Query_Column( 'form_id' ),
					GF_Query_Condition::IN,
					new GF_Query_Series( $form_ids )
				),

				$this->where
			) );
		}

		/**
		 * WHERE.
		 */
		if ( $where = $this->_where_unwrap( $this->where ) ) {
			$where = "WHERE $where";
		}

		/**
		 * LIMIT and OFFSET.
		 */
		$limit = $offset = '';
		if ( $this->limit ) {
			$limit = sprintf( 'LIMIT %d', $this->limit );
			if ( $this->offset ) {
				$offset = sprintf( 'OFFSET %d', $this->offset );
			}
		}

		$paginate = implode( ' ', array_filter( array( $limit, $offset ), 'strlen' ) );

		/**
		 * Filter the SQL query fragments to allow low-level advanced analysis and modification before the query is run.
		 *
		 * @since 2.4.3
		 *
		 * @param array $sql An array with all the SQL fragments: select, from, join, where, order, paginate.
		 */
		$sql = apply_filters( 'gform_gf_query_sql', compact( 'select', 'from', 'join', 'where', 'order', 'paginate' ) );
		$sql = implode( ' ', array_filter( $sql, 'strlen' ) );

		GFCommon::log_debug( __METHOD__ . '(): sql => ' . $sql );

		$this->timer_start();
		$results = $wpdb->get_results( $sql, ARRAY_N );
		$this->queries []= array( $this->timer_stop(), $sql );

		if ( is_null( $results ) ) {
			return array();
		}

		return $results;
	}

	/**
	 * Generate the ORDER BY clause fragments.
	 *
	 * @param array $orders Usually self::$order, the orders.
	 *
	 * @internal
	 *
	 * @return array The ORDER BY clause fragments.
	 */
	public function _order_generate( $orders ) {
		$order_clauses = array();

		foreach ( $orders as $o ) {
			list( $column, $order ) = $o;

			if ( ! in_array( $order, array( self::ASC, self::DESC, '' ) ) ) {
				continue;
			}

			if ( $column instanceof GF_Query_Call ) {
				$order_clauses []= sprintf( '%s %s', $column->sql( $this ), $order );

			} else if ( $column instanceof GF_Query_Column ) {
				if ( $column->is_entry_column() ) {
					$order_clauses []= sprintf( '`%s`.`%s` %s', $this->_alias( null, $column->source ), $column->field_id, $order );

					/**
					 * An entry meta field.
					 */
				} else {
					$alias = $this->_alias( $column->field_id, $column->source, 'o' );
					$order_clauses []= sprintf( '`%s`.`meta_value` %s', $alias, $order );
				}
			}
		}

		$order_clauses = array_map( 'trim', $order_clauses );

		return $order_clauses;
	}

	/**
	 * Recursive function to find joins.
	 *
	 * @param $condition
	 * @param $already_joined
	 */
	private function find_joins( $condition, &$already_joined ) {
		if ( ! $condition instanceof GF_Query_Condition ) {
			return;
		}

		/**
		 * This is an OR or AND clause. Needs unwrapping.
		 */
		if ( in_array( $condition->operator, array( GF_Query_Condition::_AND, GF_Query_Condition::_OR ) ) ) {
			foreach ( $condition->expressions as $expression ) {
				$this->find_joins( $expression, $already_joined );
			}
			return;
		}

		foreach ( $condition->columns as $column ) {
			/** @var GF_Query_Column $column */
			if ( $column->is_entry_column() || ! $column->field_id || ! $column->source )
				continue;

			$source = $column->source;

			$source_id = is_array( $source ) ? reset( $source ) : $source;
			$field_id = $column->field_id;

			/** Reserve the alias. */
			$this->_alias( $column->field_id, $column->source, 'm' );

			$already_joined []= "{$source_id}_{$field_id}";
		}
	}

	/**
	 * Generate the needed SELECT columns from join arrays.
	 *
	 * @param array $joins The joins (from self::$joins)
	 *
	 * @internal
	 *
	 * @return string[] The individual SELECT columns.
	 */
	public function _select_infer( $joins ) {

		$select[] = sprintf( '`%s`.`id`', $this->_alias( null, reset( $this->from ) ) );

		foreach ( $joins as $join ) {
			list( $on, $column ) = $join;
			if ( ! $on instanceof GF_Query_Column || ! $column instanceof GF_Query_Column ) {
				continue;
			}

			if ( ! $on->field_id || ! $on->source || ! $column->field_id || ! $column->source ) {
				continue;
			}

			$alias = $this->_alias( $on->is_entry_column() ? null : $on->field_id, $on->source );
			$select[] = sprintf( '`%s`.`%s` AS `%s_id`', $alias, $on->is_entry_column() ? 'id' : 'entry_id', $alias );
		}

		return $select;
	}

	/**
	 * Generate the needed JOIN statements from join arrays.
	 *
	 * @param array $joins The joins (from self::$joins)
	 *
	 * @internal
	 *
	 * @return string[] The individual JOIN statements.
	 */
	public function _join_generate( $joins ) {
		$_joins = array();

		foreach ( $joins as $join ) {
			list( $on, $column ) = $join;
			if ( ! $on instanceof GF_Query_Column || ! $column instanceof GF_Query_Column ) {
				continue;
			}

			if ( ! $on->field_id || ! $on->source || ! $column->field_id || ! $column->source ) {
				continue;
			}

			/**
			 * Join on the entry table when dealing with an entry column.
			 */
			if ( $on->is_entry_column() ) {
				$table_on = GFFormsModel::get_entry_table_name();
				$alias_on = $this->_alias( null, $on->source, 't' );
				$column_on = $on->field_id;

				/**
				 * Join on the meta table when dealing with a meta field.
				 */
			} else {
				$table_on = GFFormsModel::get_entry_meta_table_name();
				$alias_on = $this->_alias( $on->field_id, $on->source, 'm' );
				$column_on = 'meta_value';

				/**
				 * Make sure a WHERE clause exists on meta fields.
				 */
				$meta_condition = new GF_Query_Condition(
					new GF_Query_Column( 'meta_key', $on->source, $alias_on ),
					GF_Query_Condition::EQ,
					new GF_Query_Literal( $on->field_id )
				);

				$this->where(
					GF_Query_Condition::_and( $this->where, $meta_condition )
				);
			}

			$this->where( GF_Query_Condition::_and(
				$this->where,
				new GF_Query_Condition(
					new GF_Query_Column( 'form_id', $on->source, $alias_on ),
					GF_Query_Condition::EQ,
					new GF_Query_Literal( $on->source )
				)
			) );

			/**
			 * Join to on an entry table.
			 */
			if ( $column->is_entry_column() ) {
				$equals_table = $this->_alias( null, $column->source );
				$equals_column = $column->field_id;


				/**
				 * Join to a meta table.
				 */
			} else {
				$equals_table = $this->_alias( $column->field_id, $column->source, 'm' );
				$equals_column = 'meta_value';

				/**
				 * Make sure a WHERE clause exists on meta fields.
				 */
				$meta_condition = new GF_Query_Condition(
					new GF_Query_Column( 'meta_key', $column->source, $equals_table ),
					GF_Query_Condition::EQ,
					new GF_Query_Literal( $column->field_id )
				);

				$this->where(
					GF_Query_Condition::_and( $this->where, $meta_condition )
				);

				/**
				 * Make sure the initial join exists.
				 */
				$_joins = array_merge( $this->_join_infer( $meta_condition ), $_joins );
			}

			$_joins[] = sprintf( '`%s` AS `%s` ON `%s`.`%s` = `%s`.`%s`',
				$table_on, $alias_on, $alias_on, $column_on, $equals_table, $equals_column );
		}

		return $_joins;
	}

	/**
	 * Generate the needed JOIN statements for a condition.
	 *
	 * @param array $condition A condition (from self::$where)
	 *
	 * @internal
	 *
	 * @return string[] The individual JOIN statements.
	 */
	public function _join_infer( $condition ) {
		$this->_inferred_joins = array();

		if ( ! $condition instanceof GF_Query_Condition ) {
			return $this->_inferred_joins;
		}

		/**
		 * This is an OR or AND clause. Needs unwrapping.
		 */
		if ( in_array( $condition->operator, array( GF_Query_Condition::_AND, GF_Query_Condition::_OR ) ) ) {

			/** Recurse and unwrap. */
			$_joins = array_map( array( $this, __FUNCTION__ ), $condition->expressions );
			$this->_inferred_joins = array();
			array_walk_recursive( $_joins, array( $this, 'merge_joins' ) );
			return array_unique( $this->_inferred_joins );
		}

		/**
		 * Regular WHERE clause JOIN inference.
		 */
		foreach ( $condition->columns as $column ) {
			if ( $column->is_entry_column() || ! $column->field_id )
				continue;

			$alias = $column->alias ? $column->alias : $this->_alias( $column->field_id, $column->source, 'm' );

			if ( ! $column->is_meta_column() && $column->field_id != GF_Query_Column::META ) {
				if ( $column->field_id == intval( $column->field_id ) && ( $field = GFFormsModel::get_field( GFAPI::get_form( $column->source ? $column->source : reset( $this->from ) ), $column->field_id ) ) && $field->get_entry_inputs() ) {
					/**
					 * Multi-input across all inputs.
					 */
					$literal = new GF_Query_Literal( sprintf( '%d.%%', $column->field_id ) );
					$this->_inferred_joins []= sprintf( '`%s` AS `%s` ON (`%s`.`entry_id` = `%s`.`id` AND `%s`.`meta_key` LIKE %s)',
						GFFormsModel::get_entry_meta_table_name(), $alias, $alias, $this->_alias( null, $column->source ), $alias, $literal->sql( $this )
					);
				} else {
					$literal = new GF_Query_Literal( $column->field_id );
					$this->_inferred_joins []= sprintf( '`%s` AS `%s` ON (`%s`.`entry_id` = `%s`.`id` AND `%s`.`meta_key` = %s)',
						GFFormsModel::get_entry_meta_table_name(), $alias, $alias, $this->_alias( null, $column->source ), $alias, $literal->sql( $this )
					);
				}
			} else {
				$this->_inferred_joins []= sprintf( '`%s` AS `%s` ON `%s`.`entry_id` = `%s`.`id`',
					GFFormsModel::get_entry_meta_table_name(), $alias, $alias, $this->_alias( null, $column->source )
				);
			}
		}

		return array_unique( $this->_inferred_joins );
	}

	/**
	 * Generate the needed JOIN statements for stanalone ORDER BY clauses.
	 *
	 * @param array $condition A condition (from self::$where)
	 * @param array $order The orders (from self::$order )
	 *
	 * @internal
	 *
	 * @return string[] The individual JOIN statements.
	 */
	public function _join_infer_orders( $condition, $order ) {
		$already_joined = array();
		$this->find_joins( $condition, $already_joined );

		$joins = array();

		foreach ( $order as $o ) {
			list( $column, $_ ) = $o;

			if ( $column instanceof GF_Query_Call ) {
				$columns = $column->columns;
			} else if ( $column instanceof GF_Query_Column ) {
				$columns = array( $column );
			} else {
				continue;
			}

			foreach ( $columns as $column ) {
				if ( $column->is_entry_column() || $column->is_meta_column() ) {
					continue;
				}
				
				$source_id = $column->source;
				$field_id = $column->field_id ? $column->field_id : '-';
				$alias = $this->_alias( $column->field_id, $column->source, 'm' );

				if ( ! in_array( "{$source_id}_{$field_id}", $already_joined ) ) {
					$already_joined []= "{$source_id}_{$field_id}";

					$literal = new GF_Query_Literal( $field_id );
					$joins []= sprintf( '`%s` AS `%s` ON (`%s`.`entry_id` = `%s`.`id` AND `%s`.`meta_key` = %s)',
						GFFormsModel::get_entry_meta_table_name(), $alias, $alias, $this->_alias( null, $column->source ),
						$alias, $literal->sql( $this ) );
				}
			}
		}

		return $joins;
	}

	/**
	 * @param $join
	 */
	private function merge_joins( $join ) {
		$this->_inferred_joins = array_merge( $this->_inferred_joins, array( $join ) );
	}

	/**
	 * Remove simplified join statements on the same column.
	 *
	 * Used to avoid duplicate/non-unique aliases in joins. We always
	 *  select the more specific join clause.
	 *
	 * @param array $joins
	 *
	 * @return array
	 */
	public function _prime_joins( $joins ) {
		$joins = array_unique( $joins );

		$primed_joins = array();
		foreach ( $joins as $join ) {
			if ( preg_match( '#` AS `([motc]\d+)` ON #', $join, $matches ) ) {
				$alias = $matches[1];
				if ( ! empty( $primed_joins[ $alias ] ) ) {
					if ( strlen( $primed_joins[ $alias ] ) > strlen( $join ) ) {
						continue;
					}
				}
				$primed_joins[ $alias ] = $join;
			}
		}

		return array_values( $primed_joins );
	}

	/**
	 * Return a nice table alias for this source/field.
	 *
	 * Every source is marked as unique by ID. Different instances
	 *  of the same source will have the same alias.
	 *
	 * @internal
	 *
	 * @param string $field_id The field.
	 * @param int|array $source The source. Default: self::$from
	 * @param string $prefix The table prefix. Default: "t"
	 *
	 * @return string|null The alias.
	 */
	public function _alias( $field_id, $source = null, $prefix = 't' ) {
		if ( 't' == $prefix && ( ! $source || empty ( $this->from ) || in_array( $source, $this->from ) ) ) {
			if ( ! isset( $this->aliases['-'] ) ) {
				$this->aliases['-'] = true;
			}
			return 't1';
		}

		if ( $source && is_array( $source ) ) {
			$source = reset( $source );
		}
		$source_id = $source ? $source : ( ( ! empty( $this->from[0] ) ) ? $this->from[0] : '-' );
		$field_id = $field_id ? $field_id : '-';

		$source_id = "{$source_id}_{$field_id}";

		if ( ! isset( $this->aliases[ $source_id ] ) ) {
			$this->aliases[ $source_id ] = sprintf( '%s%d', $prefix, count( $this->aliases ) + 1 );
		};

		return $this->aliases[ $source_id ];
	}

	/**
	 * Unwraps a nested array of conditionals into one long string.
	 *
	 * @param GF_Query_Condition $condition Either a condition or an array of conditions. Usually from self::$where.
	 *
	 * @internal
	 *
	 * @return string.
	 */
	public function _where_unwrap( $condition ) {
		if ( ! $condition instanceof GF_Query_Condition ) {
			return '';
		}

		/**
		 * This is an OR or AND clause. Needs unwrapping.
		 */
		if ( in_array( $condition->operator, array( GF_Query_Condition::_AND, GF_Query_Condition::_OR ) ) ) {

			/** Recurse. */
			$conditions = array_filter( array_map( array( $this, __FUNCTION__ ), $condition->expressions ), 'strlen' );

			if ( count( $conditions ) ) {
				return count( $conditions ) == 1 ? current( $conditions ) : '(' . implode( " {$condition->operator} ", $conditions ) . ')';
			}

			return '';
		}

		return $condition->sql( $this );
	}

	/**
	 * The CAST function call.
	 *
	 * @param string $field_id The field to cast.
	 * @param string $type The type, one of self::TYPE_*
	 *
	 * @unused This seems to be a development artifact. Candidate for removal.
	 *
	 * @return array|$field The function name, args in order. Or the field if error.
	 */
	public function cast( $field_id, $type ) {
		if ( ! in_array( $type, array( self::TYPE_SIGNED, self::TYPE_UNSIGNED, self::TYPE_DECIMAL ) ) ) {
			return $field_id;
		}

		if ( self::TYPE_DECIMAL === $type ) {
			$type = 'DECIMAL(65, 6)';
		}

		return array( 'CAST', $field_id, 'AS', $type );
	}

	/**
	 * Whether there are several forms that are being selected from or not.
	 *
	 * Does not return true for joins and unions.
	 *
	 * @return bool
	 */
	public function is_multisource() {
		return is_array( $this->from ) && count( $this->from ) > 1;
	}

	/**
	 * Shows the private internal state of this query.
	 *
	 * For debugging and testing purposes only.
	 *
	 * @internal
	 *
	 * @return array Containing introspection data.
	 */
	public function _introspect() {
		return array(
			'from' => $this->from,
			'joins' => $this->joins,
			'where' => $this->where,
			'offset' => $this->offset,
			'limit' => $this->limit,
			'order' => $this->order,

			'aliases' => $this->aliases,
			'queries' => $this->queries,
		);
	}

	/**
	 * Start the stopwatch.
	 *
	 * @return void
	 */
	private function timer_start() {
		$this->timer = microtime( true );
	}

	/**
	 * Stop the stopwatch.
	 *
	 * @return float The time it took in seconds.
	 */
	private function timer_stop() {
		return microtime( true ) - $this->timer;
	}

	/**
	 * Returns an array with the field values for a given entry ID.
	 *
	 * @param $entry_id
	 *
	 * @return array|bool
	 */
	public function get_entry( $entry_id ) {

		if ( empty( $entry_id ) ) {
			return false;
		}

		$entries = $this->get_entries( array( $entry_id ) );
		if ( empty( $entries ) ) {
			return false;
		}
		return array_pop( $entries );
	}

	/**
	 * Returns an array or array entries with the field values for the given entry IDs.
	 *
	 * @param int[]|int[][] $entry_ids A (nested) array of entry IDs to fetch. Invalid IDs are discarded.
	 *
	 * @return array[] An array of entry objects.
	 */
	public function get_entries( $entry_ids ) {
		global $wpdb;

		foreach ( $entry_ids as $i => $id ) {
			if ( ! is_array( $id ) ) {
				$entry_ids[ $i ] = array( $id );
			}
		}

		$ids = array();
		foreach ( $entry_ids as $entry_id ) {
			$ids = array_merge( $ids, $entry_id );
		}
		$ids = array_unique( $ids );

		if ( empty( $ids ) ) {
			return array();
		}

		$entry_table = GFFormsModel::get_entry_table_name();
		$sql = sprintf( "SELECT * from $entry_table WHERE id IN(%s)", $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) ) );
		$entryset = $wpdb->get_results( $wpdb->prepare( $sql, $ids ), ARRAY_A );

		$entry_meta_table = GFFormsModel::get_entry_meta_table_name();

		$entries = array();

		foreach ( $entryset as $entry ) {
			$entries[ $entry['id'] ] = $entry;
		}

		$cache = array(
			'form_meta' => array(),
			'form_entry_meta' => array(),
		);

		$meta_clauses = array();

		foreach ( $entryset as $entry ) {
			$form_id = absint( $entry['form_id'] );
			if ( isset( $cache['form_entry_meta'][ $form_id ] ) ) {
				$entry_meta = $cache['form_entry_meta'][ $form_id ];
			} else {
				$entry_meta = $cache['form_entry_meta'][ $form_id ] = RGFormsModel::get_entry_meta( $form_id );
			}
			if ( ! empty( $entry_meta ) ) {
				$entry_meta_placeholders = implode( ',', array_fill( 0, count( $entry_meta ), '%s' ) );
				$sql = sprintf( '( form_id = %d AND meta_key IN (%s) )', $form_id, $entry_meta_placeholders );
				if ( ! isset( $meta_clauses[ $form_id ] ) ) {
					$meta_clauses[ $form_id ] = $wpdb->prepare( $sql, array_keys( $entry_meta ) );
				}
			}
		}

		$meta_clauses_str =  empty ( $meta_clauses ) ?  '' : sprintf( 'OR (%s)', join( ' OR ', $meta_clauses ) );

		$sql = sprintf( "
SELECT entry_id, meta_key, meta_value, item_index 
FROM $entry_meta_table 
WHERE entry_id IN(%s) 
AND ( meta_key REGEXP '^[0-9|.]+$'
%s )
", $placeholders, $meta_clauses_str );
		$metaset = $wpdb->get_results( $wpdb->prepare( $sql, $ids ), ARRAY_A );


		foreach ( $metaset as $meta ) {
			$entries[ $meta['entry_id'] ][ $meta['meta_key'] . $meta['item_index'] ] = $meta['meta_value'];
		}

		foreach ( $entryset as $entry ) {
			if ( isset( $cache['form_meta'][ $entry['form_id'] ] ) ) {
				$form = $cache['form_meta'][ $entry['form_id'] ];
			} else {
				$form = $cache['form_meta'][ $entry['form_id'] ] = RGFormsModel::get_form_meta( $entry['form_id'] );
			}

			$openssl_encrypted_fields = GFFormsModel::get_openssl_encrypted_fields( $entry['id'] );

			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */

				$inputs = $field->get_entry_inputs();
				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {
						$entries[ $entry['id'] ][ (string) $input['id'] ] = gf_apply_filters( array(
							'gform_get_input_value',
							$form['id'],
							$field->id,
							$input['id']
						), rgar( $entries[ $entry['id'] ], (string) $input['id'] ), $entry, $field, $input['id'] );
					}
				} else {
					$value = rgar( $entries[ $entry['id'] ], (string) $field->id );
					if ( in_array( (string) $field->id, $openssl_encrypted_fields ) ) {
						$value = GFCommon::openssl_decrypt( $value );
					}
					$entries[ $entry['id'] ][ $field->id ] = gf_apply_filters( array(
						'gform_get_input_value',
						$form['id'],
						$field->id
					), $value, $entry, $field, '' );
				}
			}

			if ( isset( $cache['form_entry_meta'][ $entry['form_id'] ] ) ) {
				$entry_meta = $cache['form_entry_meta'][ $entry['form_id'] ];
			}

			foreach ( array_keys( $entry_meta ) as $meta_key ) {
				if ( isset( $entries[ $entry['id'] ][ $meta_key ] ) ) {
					$entries[ $entry['id'] ][ $meta_key ] = maybe_unserialize( $entries[ $entry['id'] ][ $meta_key ] );
				} else {
					$entries[ $entry['id'] ][ $meta_key ] = false;
				}
			}

			GFFormsModel::hydrate_repeaters( $entries[ $entry['id'] ], $form );
		}

		$results = array();

		foreach ( $entry_ids as $entry_id ) {
			if ( count( $entry_id ) > 1 ) {
				$joined_entries = array();
				foreach ( $entry_id as $id ) {
					if ( ! isset( $entries[ $id ] ) ) {
						continue;
					}
					$joined_entries[ $entries[ $id ][ 'form_id' ] ] = &$entries[ $id ];
				}
				$results[] = $joined_entries;
			} elseif ( count( $entry_id ) == 1 ) {
				if ( ! isset( $entries[ $entry_id[0] ] ) ) {
					continue;
				}
				$results[] = &$entries[ $entry_id[0] ];
			}
		}

		return $results;
	}

	private function set_sub_field_values( $field, $db_values, $sub_field_values, $form, &$entry ) {
		if ( isset( $field->fields ) && is_array( $field->fields ) ) {
			foreach ( $field->fields as $sub_field ) {
				$this->set_sub_field_values( $sub_field, $db_values, $sub_field_values, $form, $entry );
			}
			return;
		}
		foreach( $db_values as $key => $db_value ) {
			if ( $key == $field->id || preg_match( "/$field->id(\.|_)/", $key ) ) {
				$entry[ $key ] = $db_value;
			}
		}
		return;
	}
}
