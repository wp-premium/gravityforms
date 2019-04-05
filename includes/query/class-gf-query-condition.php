<?php

/**
 * The Gravity Forms Query Condition class.
 */
class GF_Query_Condition {
	/**
	 * @var string The chosen operator.
	 */
	private $_operator = null;

	/**
	 * @var array Boolean combined expressions.
	 */
	private $_expressions = array();

	/**
	 * @var mixed The left-hand expression.
	 */
	private $_left = null;

	/**
	 * @var mixed The right-hand expression.
	 */
	private $_right = null;

	/**
	 * @const string The AND operator.
	 */
	const _AND = 'AND';

	/**
	 * @const string The AND operator.
	 */
	const _OR = 'OR';

	/**
	 * @const string The less than operator.
	 */
	const LT = '<';

	/**
	 * @const string The less than or equal to operator.
	 */
	const LTE = '<=';

	/**
	 * @const string The greater than operator.
	 */
	const GT = '>';

	/**
	 * @const string The greater than or equal to operator.
	 */
	const GTE = '>=';

	/**
	 * @const string The equal to operator.
	 */
	const EQ = '=';

	/**
	 * @const string The not equal to operator.
	 */
	const NEQ = '!=';

	/**
	 * @const string The IN operator.
	 */
	const IN = 'IN';

	/**
	 * @const string The NOT IN operator.
	 */
	const NIN = 'NOT IN';

	/**
	 * @const string The LIKE operator.
	 */
	const LIKE = 'LIKE';

	/**
	 * @const string The NOT LIKE operator.
	 */
	const NLIKE = 'NOT LIKE';

	/**
	 * @const string The BETWEEN operator.
	 */
	const BETWEEN = 'BETWEEN';

	/**
	 * @const string The NOT BETWEEN operator.
	 */
	const NBETWEEN = 'NOT BETWEEN';

	/**
	 * @const string The inverse IN operator.
	 */
	const CONTAINS = 'CONTAINS';

	/**
	 * @const string The inverse NIN operator.
	 */
	const NCONTAINS = 'NCONTAINS';

	/**
	 * @const string The IS operator.
	 */
	const IS = 'IS';

	/**
	 * @const string The IS NOT operator.
	 */
	const ISNOT = 'IS NOT';

	/**
	 * @const string NULL.
	 */
	const NULL = 'NULL';

	/**
	 * A condition.
	 *
	 * @param GF_Query_Column|GF_Query_Call|GF_Query_Literal|null $left The left-hand expression.
	 * @param string|null $operator The operator.
	 * @param GF_Query_Column|GF_Query_Call|GF_Query_Literal|GF_Query_Series|null $right The right-hand expression.
	 *
	 * @return GF_Query_Condition $this This condition.
	 */
	public function __construct( $left = null, $operator = null, $right = null ) {

		$allowed_ops = array( self::LT, self::LTE, self::GT, self::GTE, self::EQ, self::NEQ, self::IN, self::NIN, self::LIKE, self::NLIKE, self::BETWEEN, self::NBETWEEN, self::CONTAINS, self::NCONTAINS, self::IS, self::ISNOT );

		/**
		 * Left-hand expression, non Series.
		 */
		if ( self::is_valid_expression_type( $left ) && ! $left instanceof GF_Query_Series ) {
			$this->_left = $left;
		}

		/**
		 * The operator.
		 */
		if ( in_array( $operator, $allowed_ops ) ) {
			$this->_operator = $operator;
		}

		/**
		 * Right-hand expression, non Series.
		 */
		if ( self::is_valid_expression_type( $right ) ) {
			$this->_right = $right;
		}
	}

	/**
	 * Tie several conditions together with an AND relationship.
	 *
	 * Accepts any number of GF_Query_Condition objects.
	 *
	 * @return GF_Query_Condition The condition.
	 */
	public static function _and() {
		$conditions = array();

		foreach ( func_get_args() as $arg ) {
			if ( $arg instanceof GF_Query_Condition ) {
				$conditions[] = $arg;
			}
		}

		$_this = new self();
		$_this->_operator = self::_AND;
		$_this->_expressions = $conditions;
		return $_this;
	}

	/**
	 * Tie several conditions together with an OR relationship.
	 *
	 * Accepts any number of GF_Query_Condition objects.
	 *
	 * @return GF_Query_Condition The condition.
	 */
	public static function _or() {

		$conditions = array();

		foreach ( func_get_args() as $arg ) {
			if ( $arg instanceof GF_Query_Condition ) {
				$conditions[] = $arg;
			}
		}

		$_this = new self();
		$_this->_operator = self::_OR;
		$_this->_expressions = $conditions;
		return $_this;
	}

	/**
	 * Compile the expressions into a SQL string.
	 *
	 * @param GF_Query $query The query.
	 *
	 * @return string The SQL string.
	 */
	public function sql( $query ) {
		global $wpdb;

		/**
		 * Both expressions are given.
		 */
		if ( $this->operator && $this->left && $this->right ) {

			/**
			 * Meta field.
			 */
			if ( $this->left instanceof GF_Query_Column && ( ! $this->left->is_entry_column() && ! $this->left->is_meta_column() )  && $this->left->field_id ) {
				/**
				 * A meta field needs some extra conditions: "meta_key", "meta_value", and sometimes EXISTS.
				 */
				$alias = $query->_alias( $this->left->field_id, $this->left->source, 'm' );

				if ( $this->left->field_id == GF_Query_Column::META ) {

					// Global meta search doesn't require a meta_key clause.
					$compare_condition = new self(
						new GF_Query_Column( 'meta_value', $this->left->source, $alias ),
						$this->operator,
						$this->right
					);

					return $query->_where_unwrap( $compare_condition );
				}

				/**
				 * Multi-input fields are processed in a more complex way.
				 *
				 * If a non-specific input is requested (radio, checkboxes, usually)
				 *  we have to include all the input ids in the query.
				 */
				if ( is_numeric( $this->left->field_id ) && intval( $this->left->field_id ) == $this->left->field_id ) {
					if ( ( $field = GFFormsModel::get_field( GFAPI::get_form( $this->left->source ), $this->left->field_id ) ) && $field->get_entry_inputs() ) {

						/**
						 * EQ and NEQ require an unordered comparison of all the values for the entry.
						 */
						if ( in_array( $this->operator, array( self::EQ, self::NEQ ) ) && $this->right instanceof GF_Query_Series ) {
							$compare_conditions = array();
							foreach ( $this->right->values as $literal ) {
								$subquery = $wpdb->prepare( sprintf( "SELECT 1 FROM `%s` WHERE `meta_key` LIKE %%s AND `meta_value` = %s AND `entry_id` = `%s`.`id`",
									GFFormsModel::get_entry_meta_table_name(), $literal->sql( $query ), $query->_alias( null, $this->left->source ) ),
									sprintf( '%d.%%', $this->left->field_id ) );
								$compare_condition = new self( new GF_Query_Call( sprintf( '%sEXISTS', $this->operator == self::NEQ ? 'NOT ' : '' ), array( $subquery ) ) );
								$compare_conditions []= $compare_condition->sql( $query );
							}

							$subquery = $wpdb->prepare( sprintf( "SELECT COUNT(1) FROM `%s` WHERE `meta_key` LIKE %%s AND `entry_id` = `%s`.`id`",
								GFFormsModel::get_entry_meta_table_name(), $query->_alias( null, $this->left->source ) ),
								sprintf( '%d.%%', $this->left->field_id ) );

							/**
							 * Add length comparison to make sure all the needed values are found
							 *  and no extra ones exist.
							 */
							$compare_conditions []= sprintf( "(%s) %s %d", $subquery, $this->operator == self::NEQ ? '!=' : '=', count( $compare_conditions ) );
							return sprintf( "(%s)", implode( $this->operator == self::NEQ ? ' OR ' : ' AND ', $compare_conditions ) );

							/**
							 * Inverse contains.
							 */
						} elseif ( in_array( $this->operator, array( self::CONTAINS, self::NCONTAINS ) ) ) {
							$subquery = $wpdb->prepare( sprintf( "SELECT 1 FROM `%s` WHERE `meta_key` LIKE %%s AND `meta_value` = %s AND `entry_id` = `%s`.`id`",
								GFFormsModel::get_entry_meta_table_name(), $this->right->sql( $query ), $query->_alias( null, $this->left->source ) ),
								sprintf( '%d.%%', $this->left->field_id ) );
							$compare_condition = new self( new GF_Query_Call( sprintf( '%sEXISTS', $this->operator == self::NCONTAINS ? 'NOT ' : '' ), array( $subquery ) ) );
							return $compare_condition->sql( $query );

							/**
							 * One of.
							 */
						} elseif ( in_array( $this->operator, array( self::IN, self::NIN ) ) && $this->right instanceof GF_Query_Series ) {
							$subquery = $wpdb->prepare( sprintf( "SELECT 1 FROM `%s` WHERE `meta_key` LIKE %%s AND `meta_value` IN (%s) AND `entry_id` = `%s`.`id`",
								GFFormsModel::get_entry_meta_table_name(), str_replace( '%', '%%', $this->right->sql( $query, ', ' ) ), $query->_alias( null, $this->left->source ) ),
								sprintf( '%d.%%', $this->left->field_id ) );
							$compare_condition = new self( new GF_Query_Call( sprintf( '%sEXISTS', $this->operator == self::NIN ? 'NOT ' : '' ), array( $subquery ) ) );
							return $compare_condition->sql( $query );

							/**
							 * Everything else.
							 */
						} else {
							$operator = $this->operator;
							$is_negative = in_array( $operator, array( self::NLIKE, self::NBETWEEN, self::NEQ ) );
							if ( $is_negative ) {
								/**
								 * Convert operator to positive, since we're doing it the NOT EXISTS way.
								 */
								switch ( $operator ) {
									case self::NLIKE:
										$operator = self::LIKE;
										break;
									case self::NBETWEEN:
										$operator = self::BETWEEN;
										break;
									case self::NEQ:
										$operator = self::EQ;
										break;
								}
							}

							$subquery = $wpdb->prepare( sprintf( "SELECT 1 FROM `%s` WHERE `meta_key` LIKE %%s AND `meta_value` %s %s AND `entry_id` = `%s`.`id`",
								GFFormsModel::get_entry_meta_table_name(), $operator, str_replace( '%', '%%', $this->right->sql( $query ) ), $query->_alias( null, $this->left->source ) ),
								sprintf( '%d.%%', $this->left->field_id ) );
							$compare_condition = new self( new GF_Query_Call( sprintf( '%sEXISTS', $is_negative ? 'NOT ' : '' ), array( $subquery ) ) );
							return $compare_condition->sql( $query );
						}
					}
				}

				$compare_condition = self::_and(
					new self(
						new GF_Query_Column( 'meta_key', $this->left->source, $alias ),
						self::EQ,
						new GF_Query_Literal( $this->left->field_id )
					),
					new self(
						new GF_Query_Column( 'meta_value', $this->left->source, $alias ),
						$this->operator,
						$this->right
					)
				);

				if ( ( in_array( $this->operator, array( self::NIN, self::NBETWEEN ) ) && ! in_array( new GF_Query_Literal(''), $this->right->values ) )
				     || ( $this->operator == self::NEQ && ! $this->right->value == '')
				     || ( $this->operator == self::EQ && $this->right->value == '' )
				) {
					/**
					 * Empty string comparisons and negative comparisons need a NOT EXISTS clause to grab entries that
					 *  don't have the value set in the first place.
					 */
					$subquery = $wpdb->prepare( sprintf( "SELECT 1 FROM `%s` WHERE `meta_key` = %%s AND `entry_id` = `%s`.`id`",
						GFFormsModel::get_entry_meta_table_name(), $query->_alias( null, $this->left->source ) ), $this->left->field_id );
					$not_exists = new self( new GF_Query_Call( 'NOT EXISTS', array( $subquery ) ) );
					return $query->_where_unwrap( self::_or( $not_exists, $compare_condition ) );
				}

				return $query->_where_unwrap( $compare_condition );
			}

			if ( ( $left = $this->left_sql( $query ) ) && ( $right = $this->right_sql( $query ) ) ) {
				if ( in_array( $this->operator, array( self::NBETWEEN, self::BETWEEN ) ) ) {
					return "($left {$this->operator} $right)";
				}

				if ( $this->left instanceof GF_Query_Column && $this->left->is_nullable_entry_column() ) {
					if ( ( $this->operator == self::EQ && empty ( $this->right->value ) ) || ( $this->operator == self::NEQ && ! empty ( $this->right->value ) ) ) {
						$right .= ' OR ' . $left . ' IS NULL)';
						$left = "($left";
					}
				}

				if ( $this->left instanceof GF_Query_Column && $this->left->is_entry_column() && $this->left->source ) {
					if ( $query->is_multisource() && $this->left->field_id != 'form_id' ) {
						$alias = $query->_alias( null, $this->left->source );
						$left = "(`$alias`.`form_id` = {$this->left->source} AND $left";
						$right .= ')';
					}
				}

				return "$left {$this->operator} $right";
			}
		}

		if ( $this->left && ( $this->left instanceof GF_Query_Call || $this->left instanceof GF_Query_Column ) ) {
			return $this->left_sql( $query );
		}

		return '';
	}

	/**
	 * Checks whether the expression is of a valid type.
	 *
	 * @param mixed $expression The expression to check.
	 *
	 * @return boolean Valid or not.
	 */
	public static function is_valid_expression_type( $expression ) {
		return (
			( $expression instanceof GF_Query_Literal ) ||
			( $expression instanceof GF_Query_Column ) ||
			( $expression instanceof GF_Query_Series ) ||
			( $expression instanceof GF_Query_Call ) ||
			( $expression === self::NULL )
		);
	}

	/**
	 * The right expression.
	 *
	 * @return string The SQL string or null for the right expression.
	 */
	private function right_sql( $query ) {
		/**
		 * (NOT) IN
		 *
		 * Only works with literal arrays, which can be made
		 * up of a Literal, Column or Call.
		 */
		if ( in_array( $this->operator, array( self::IN, self::NIN ) ) ) {
			if ( ! $this->right instanceof GF_Query_Series ) {
				return '';
			}

			return sprintf( '(%s)', $this->right->sql( $query, ', ' ) );

			/**
			 * BETWEEN
			 */
		} elseif ( in_array( $this->operator, array( self::BETWEEN, self::NBETWEEN ) ) ) {
			if ( ! $this->right instanceof GF_Query_Series ) {
				return '';
			}

			return $this->right->sql( $query, ' AND ' );
		} elseif ( in_array( $this->operator, array( self::IS, self::ISNOT ) ) ) {
			if ( $this->right !== self::NULL ) {
				return '';
			}

			return self::NULL;
		}

		return $this->right->sql( $query );
	}

	/**
	 * The left expression.
	 *
	 * @return string The SQL string or null for the left expression.
	 */
	private function left_sql( $query ) {
		if ( $this->left instanceof GF_Query_Call ) {

			$columns = array();

			foreach ( $this->left->parameters as $c ) {
				if ( $c instanceof GF_Query_Column ) {
					$columns[] = $c;
				}
			}

			/**
			 * Add a meta_key condition to a calls.
			 */
			if ( $columns ) {
				$meta_key_conditions = array();
				foreach ( $columns as $column ) {
					$alias = $column->alias ? $column->alias : $query->_alias( $column->field_id, $column->source, 'm' );
					$condition = new GF_Query_Condition(
						new GF_Query_Column( 'meta_key', $column->source, $alias ),
						GF_Query_Condition::EQ,
						new GF_Query_Literal( $column->field_id )
					);

					$meta_key_conditions []= $condition->sql( $query );
				}

				return implode( ' AND ',
					array_merge(
						$meta_key_conditions,
						array( $this->left->sql( $query ) )
					)
				);
			}
		}
		return $this->left->sql( $query );
	}

	/**
	 * Retrieve all the columns present in the left, right clauses.
	 *
	 * @return array The columns.
	 */
	public function get_columns() {
		$columns = array();

		if ( $this->left instanceof GF_Query_Column ) {
			$columns[] = $this->left;
		}

		if ( $this->right instanceof GF_Query_Column ) {
			$columns[] = $this->right;
		}

		/**
		 * Support Calls
		 */
		if ( $this->left instanceof GF_Query_Call) {

			$left_columns = array();

			foreach ( $this->left->parameters as $c ) {
				if ( $c instanceof GF_Query_Column ) {
					$left_columns[] = $c;
				}
			}

			$columns = array_merge( $columns, $left_columns );
		}

		/**
		 * Support series of columns.
		 */
		if ( $this->right instanceof GF_Query_Series ) {

			$right_columns = array();

			foreach ( $this->right->values as $c ) {
				if ( $c instanceof GF_Query_Column ) {
					$right_columns[] = $c;
				}
			}

			$columns = array_merge( $columns, $right_columns );
		}

		return $columns;
	}

	/**
	 * Proxy read-only values.
	 */
	public function __get( $key ) {
		switch ( $key ) :
			case 'operator':
				return $this->_operator;
			case 'expressions':
				return $this->_expressions;
			case 'left':
				return $this->_left;
			case 'right':
				return $this->_right;
			case 'columns':
				return $this->get_columns();
		endswitch;
	}
}
