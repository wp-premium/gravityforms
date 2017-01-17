<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * API for standard Gravity Forms functionality.
 *
 * Supports:
 * - Forms
 * - Entries
 *
 * @package    Gravity Forms
 * @subpackage GFAPI
 * @since      1.8
 * @access     public
 */
class GFAPI {

	// FORMS ----------------------------------------------------

	/**
	 * Returns the form object for a given Form ID.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFFormsModel::get_form_meta()
	 * @uses GFFormsModel::get_form()
	 *
	 * @param int $form_id The ID of the Form.
	 *
	 * @return mixed The form meta array or false.
	 */
	public static function get_form( $form_id ) {

		$form_id = absint( $form_id );

		$form = GFFormsModel::get_form_meta( $form_id );
		if ( ! $form ) {
			return false;
		}

		// Loading form columns into meta.
		$form_info            = GFFormsModel::get_form( $form_id, true );
		$form['is_active']    = $form_info->is_active;
		$form['date_created'] = $form_info->date_created;
		$form['is_trash']     = $form_info->is_trash;

		return $form;

	}

	/**
	 * Returns all the form objects.
	 *
	 * @since  1.8.11.5
	 * @access public
	 *
	 * @uses GFFormsModel::get_form_ids()
	 * @uses GFAPI::get_form()
	 *
	 * @param bool $active True if active forms are returned. False to get inactive forms. Defaults to true.
	 * @param bool $trash  True if trashed forms are returned. False to exclude trash. Defaults to false.
	 *
	 * @return array The array of Form Objects.
	 */
	public static function get_forms( $active = true, $trash = false ) {

		$form_ids = GFFormsModel::get_form_ids( $active, $trash );
		if ( empty( $form_ids ) ) {
			return array();
		}

		$forms = array();
		foreach ( $form_ids as $form_id ) {
			$forms[] = GFAPI::get_form( $form_id );
		}

		return $forms;
	}

	/**
	 * Deletes the forms with the given Form IDs.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFFormsModel::delete_forms()
	 *
	 * @param array $form_ids An array of form IDs to delete.
	 *
	 * @return void
	 */
	public static function delete_forms( $form_ids ) {

		GFFormsModel::delete_forms( $form_ids );
	}

	/**
	 * Deletes the form with the given Form ID.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFAPI::get_form()
	 * @uses GFAPI::delete_forms()
	 *
	 * @param int $form_id The ID of the Form to delete.
	 *
	 * @return mixed True for success, or a WP_Error instance.
	 */
	public static function delete_form( $form_id ) {
		$form = self::get_form( $form_id );
		if ( empty( $form ) ) {
			return new WP_Error( 'not_found', sprintf( __( 'Form with id: %s not found', 'gravityforms' ), $form_id ), $form_id );
		}
		self::delete_forms( array( $form_id ) );

		return true;
	}

	/**
	 * Updates the forms with an array of form objects.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFAPI::update_form()
	 *
	 * @param array $forms Array of form objects.
	 *
	 * @return mixed True for success, or a WP_Error instance.
	 */
	public static function update_forms( $forms ) {

		foreach ( $forms as $form ) {
			$result = self::update_form( $form );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Updates the form with a given form object.
	 *
	 * @since  1.8
	 * @access public
	 * @global $wpdb
	 *
	 * @uses \GFFormsModel::get_meta_table_name()
	 * @uses \GFFormsModel::update_form_meta()
	 *
	 * @param array $form The Form object
	 * @param int   $form_id  Optional. If specified, then the ID in the Form Object will be ignored.
	 *
	 * @return bool|WP_Error True for success, or a WP_Error instance.
	 */
	public static function update_form( $form, $form_id = null ) {
		global $wpdb;

		if ( ! $form ) {
			return new WP_Error( 'invalid', __( 'Invalid form object', 'gravityforms' ) );
		}

		$form_table_name = $wpdb->prefix . 'rg_form';
		if ( empty( $form_id ) ) {
			$form_id = $form['id'];
		} else {
			// Make sure the form object has the right form id.
			$form['id'] = $form_id;
			if ( isset( $form['fields'] ) ) {
				foreach ( $form['fields'] as &$field ) {
					if ( $field instanceof GF_Field ) {
						$field->formId = $form_id;
					} else {
						$field['formId'] = $form_id;
					}
				}
			}
		}

		if ( empty( $form_id ) ) {
			return new WP_Error( 'missing_form_id', __( 'Missing form id', 'gravityforms' ) );
		}

		$meta_table_name = GFFormsModel::get_meta_table_name();

		if ( intval( $wpdb->get_var( $wpdb->prepare( "SELECT count(0) FROM {$meta_table_name} WHERE form_id=%d", $form_id ) ) ) == 0 ) {
			return new WP_Error( 'not_found', __( 'Form not found', 'gravityforms' ) );
		}

		// Strip confirmations and notifications.
		$form_display_meta = $form;
		unset( $form_display_meta['confirmations'] );
		unset( $form_display_meta['notifications'] );

		$result = GFFormsModel::update_form_meta( $form_id, $form_display_meta );
		if ( false === $result ) {
			return new WP_Error( 'error_updating_form', __( 'Error updating form', 'gravityforms' ), $wpdb->last_error );
		}

		if ( isset( $form['confirmations'] ) && is_array( $form['confirmations'] ) ) {
			$result = GFFormsModel::update_form_meta( $form_id, $form['confirmations'], 'confirmations' );
			if ( false === $result ) {
				return new WP_Error( 'error_updating_confirmations', __( 'Error updating form confirmations', 'gravityforms' ), $wpdb->last_error );
			}
		}

		if ( isset( $form['notifications'] ) && is_array( $form['notifications'] ) ) {
			$result = GFFormsModel::update_form_meta( $form_id, $form['notifications'], 'notifications' );
			if ( false === $result ) {
				return new WP_Error( 'error_updating_notifications', __( 'Error updating form notifications', 'gravityforms' ), $wpdb->last_error );
			}
		}

		// Updating form title and is_active flag.
		$is_active = rgar( $form, 'is_active' ) ? '1' : '0';
		$result    = $wpdb->query( $wpdb->prepare( "UPDATE {$form_table_name} SET title=%s, is_active=%s WHERE id=%d", $form['title'], $is_active, $form['id'] ) );
		if ( false === $result ) {
			return new WP_Error( 'error_updating_title', __( 'Error updating title', 'gravityforms' ), $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Updates a form property - a column in the main forms table. e.g. is_trash, is_active, title
	 *
	 * @since  1.8.3.15
	 * @access public
	 *
	 * @uses GFFormsModel::get_form_table_name()
	 * @uses GFFormsModel::get_form_db_columns()
	 *
	 * @param array $form_ids     The IDs of the forms to update.
	 * @param array $property_key The name of the column in the database e.g. is_trash, is_active, title.
	 * @param array $value        The new value.
	 *
	 * @return mixed Either a WP_Error instance or the result of the query
	 */
	public static function update_forms_property( $form_ids, $property_key, $value ) {
		global $wpdb;
		$table        = GFFormsModel::get_form_table_name();

		$db_columns = GFFormsModel::get_form_db_columns();

		if ( ! in_array( strtolower( $property_key ), $db_columns ) ) {
			return new WP_Error( 'property_key_incorrect', __( 'Property key incorrect', 'gravityforms' ) );
		}

		$value = esc_sql( $value );
		if ( ! is_numeric( $value ) ) {
			$value = sprintf( "'%s'", $value );
		}
		$in_str_arr = array_fill( 0, count( $form_ids ), '%d' );
		$in_str     = join( $in_str_arr, ',' );
		$result     = $wpdb->query(
			$wpdb->prepare(
				"
                UPDATE $table
                SET {$property_key} = {$value}
                WHERE id IN ($in_str)
                ", $form_ids
			)
		);

		return $result;
	}

	/**
	 * Updates the property of one form - columns in the main forms table. e.g. is_trash, is_active, title.
	 *
	 * @since  1.8.3.15
	 * @access public
	 *
	 * @uses GFAPI::update_forms_property()
	 *
	 * @param array|int $form_id      The ID of the forms to update.
	 * @param string    $property_key The name of the column in the database e.g. is_trash, is_active, title.
	 * @param string    $value        The new value.
	 *
	 * @return mixed Either a WP_Error instance or the result of the query
	 */
	public static function update_form_property( $form_id, $property_key, $value ) {
		return self::update_forms_property( array( $form_id ), $property_key, $value );
	}


	/**
	 * Adds multiple form objects.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFAPI::add_form()
	 *
	 * @param array $forms The Form Objects.
	 *
	 * @return array|WP_Error Either an array of new form IDs or a WP_Error instance.
	 */
	public static function add_forms( $forms ) {

		if ( ! $forms || ! is_array( $forms ) ) {
			return new WP_Error( 'invalid', __( 'Invalid form objects', 'gravityforms' ) );
		}
		$form_ids = array();
		foreach ( $forms as $form ) {
			$result = self::add_form( $form );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$form_ids[] = $result;
		}

		return $form_ids;
	}

	/**
	 * Adds a new form using the given Form object. Warning, little checking is done to make sure it's a valid Form object.
	 *
	 * @since  1.8
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::is_unique_title()
	 * @uses GFFormsModel::insert_form()
	 * @uses GFAPI::set_property_as_key()
	 * @uses GFFormsModel::update_form_meta()
	 *
	 * @param array $form_meta The Form object.
	 *
	 * @return int|WP_Error Either the new Form ID or a WP_Error instance.
	 */
	public static function add_form( $form_meta ) {
		global $wpdb;

		if ( ! $form_meta || ! is_array( $form_meta ) ) {
			return new WP_Error( 'invalid', __( 'Invalid form object', 'gravityforms' ) );
		}

		if ( rgar( $form_meta, 'title' ) == '' ) {
			return new WP_Error( 'missing_title', __( 'The form title is missing', 'gravityforms' ) );
		}
		// Making sure title is not duplicate.
		$title = $form_meta['title'];
		$count = 2;
		while ( ! RGFormsModel::is_unique_title( $title ) ) {
			$title = $form_meta['title'] . "($count)";
			$count ++;
		}

		// Inserting form.
		$form_id = RGFormsModel::insert_form( $title );

		// Updating form meta.
		$form_meta['title'] = $title;

		// Updating object's id property.
		$form_meta['id'] = $form_id;

		if ( isset( $form_meta['confirmations'] ) ) {
			$form_meta['confirmations'] = self::set_property_as_key( $form_meta['confirmations'], 'id' );
			GFFormsModel::update_form_meta( $form_id, $form_meta['confirmations'], 'confirmations' );
			unset( $form_meta['confirmations'] );
		}

		if ( isset( $form_meta['notifications'] ) ) {
			$form_meta['notifications'] = self::set_property_as_key( $form_meta['notifications'], 'id' );
			GFFormsModel::update_form_meta( $form_id, $form_meta['notifications'], 'notifications' );
			unset( $form_meta['notifications'] );
		}

		// Updating form meta.
		$result = GFFormsModel::update_form_meta( $form_id, $form_meta );

		if ( false === $result ) {
			return new WP_Error( 'insert_form_error', __( 'There was a problem while inserting the form', 'gravityforms' ), $wpdb->last_error );
		}

		return $form_id;
	}

	/**
	 * Private.
	 *
	 * @since  1.8
	 * @access private
	 * @ignore
	 */
	private static function set_property_as_key( $array, $property ) {
		$new_array = array();
		foreach ( $array as $item ) {
			$new_array[ $item[ $property ] ] = $item;
		}

		return $new_array;
	}

	// ENTRIES ----------------------------------------------------

	/**
	 * Returns an array of Entry objects for the given search criteria. The search criteria array is constructed as follows:
	 *
	 *  Filter by status
	 *     $search_criteria['status'] = 'active';
	 *
	 *  Filter by date range
	 *     $search_criteria['start_date'] = $start_date; // Using the time zone in the general settings.
	 *     $search_criteria['end_date'] =  $end_date;    // Using the time zone in the general settings.
	 *
	 *  Filter by any column in the main table
	 *     $search_criteria['field_filters'][] = array("key" => 'currency', value => 'USD');
	 *     $search_criteria['field_filters'][] = array("key" => 'is_read', value => true);
	 *
	 *  Filter by Field Values
	 *     $search_criteria['field_filters'][] = array('key' => '1', 'value' => 'gquiz159982170');
	 *
	 *  Filter Operators
	 *     Supported operators for scalar values: is/=, isnot/<>, contains
	 *     $search_criteria['field_filters'][] = array('key' => '1', 'operator' => 'contains', value' => 'Steve');
	 *     Supported operators for array values: in/=, not in/<>/!=
	 *     $search_criteria['field_filters'][] = array('key' => '1', 'operator' => 'not in', value' => array( 'Alex', 'David', 'Dana' );
	 *
	 *  Filter by a checkbox value - input ID search keys
	 *     $search_criteria['field_filters'][] = array('key' => '2.2', 'value' => 'gquiz246fec995');
	 *     NOTES:
	 *          - Using input IDs as search keys will work for checkboxes but it won't work if the checkboxes have been re-ordered since the first submission.
	 *          - the 'not in' operator is not currently supported for checkbox values.
	 *
	 *  Filter by a checkbox value - field ID keys
	 *     Using the field ID as the search key is recommended for checkboxes.
	 *     $search_criteria['field_filters'][] = array('key' => '2', 'value' => 'gquiz246fec995');
	 *     $search_criteria['field_filters'][] = array('key' => '2', 'operator' => 'in', 'value' => array( 'First Choice', 'Third Choice' );
	 *     NOTE: Neither 'not in' nor '<>' operators are not currently supported for checkboxes using field IDs as search keys.
	 *
	 *  Filter by a global search of values of any form field
	 *     $search_criteria['field_filters'][] = array('value' => $search_value);
	 *  OR
	 *     $search_criteria['field_filters'][] = array('key' => 0, 'value' => $search_value);
	 *
	 *  Filter entries by Entry meta (added using the gform_entry_meta hook)
	 *     $search_criteria['field_filters'][] = array('key' => 'gquiz_score', 'value' => '1');
	 *     $search_criteria['field_filters'][] = array('key' => 'gquiz_is_pass', 'value' => '1');
	 *
	 *  Filter by ALL / ANY of the field filters
	 *     $search_criteria['field_filters']['mode'] = 'all'; // default
	 *     $search_criteria['field_filters']['mode'] = 'any';
	 *
	 *  Sorting: column, field or entry meta
	 *     $sorting = array('key' => $sort_field, 'direction' => 'ASC' );
	 *
	 *  Paging
	 *     $paging = array('offset' => 0, 'page_size' => 20 );
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFFormsModel::search_leads()
	 * @uses GFAPI::count_entries()
	 *
	 * @param int|array $form_ids        The ID of the form or an array IDs of the Forms. Zero for all forms.
	 * @param array     $search_criteria Optional. An array containing the search criteria. Defaults to empty array.
	 * @param array     $sorting         Optional. An array containing the sorting criteria. Defaults to null.
	 * @param array     $paging          Optional. An array containing the paging criteria. Defaults to null.
	 * @param int       $total_count     Optional. An output parameter containing the total number of entries. Pass a non-null value to get the total count. Defaults to null.
	 *
	 * @return array|WP_Error Either an array of the Entry objects or a WP_Error instance.
	 */
	public static function get_entries( $form_ids, $search_criteria = array(), $sorting = null, $paging = null, &$total_count = null ) {

		if ( empty( $sorting ) ) {
			$sorting = array( 'key' => 'id', 'direction' => 'DESC', 'is_numeric' => true );
		}


		$entries = GFFormsModel::search_leads( $form_ids, $search_criteria, $sorting, $paging );

		if ( ! is_null( $total_count ) ) {
			$total_count = self::count_entries( $form_ids, $search_criteria );
		}


		return $entries;
	}

	/**
	 * Returns the total number of entries for the given search criteria. See get_entries() for examples of the search criteria.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFFormsModel::count_search_leads()
	 *
	 * @param int|array $form_ids        The ID of the Form or an array of Form IDs.
	 * @param array     $search_criteria Optional. An array containing the search criteria. Defaults to empty array.
	 *
	 * @return int The total count.
	 */
	public static function count_entries( $form_ids, $search_criteria = array() ) {
		return GFFormsModel::count_search_leads( $form_ids, $search_criteria );
	}

	/**
	 * Returns the Entry object for a given Entry ID.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFAPI::get_entries()
	 *
	 * @param int $entry_id The ID of the Entry.
	 *
	 * @return array|WP_Error The Entry object or a WP_Error instance.
	 */
	public static function get_entry( $entry_id ) {

		$search_criteria['field_filters'][] = array( 'key' => 'id', 'value' => $entry_id );

		$paging  = array( 'offset' => 0, 'page_size' => 1 );
		$entries = self::get_entries( 0, $search_criteria, null, $paging );

		if ( empty( $entries ) ) {
			return new WP_Error( 'not_found', sprintf( __( 'Entry with id %s not found', 'gravityforms' ), $entry_id ), $entry_id );
		}

		return $entries[0];
	}

	/**
	 * Adds multiple Entry objects.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFAPI::add_entry()
	 *
	 * @param array $entries The Entry objects
	 * @param int   $form_id Optional. If specified, the form_id in the Entry objects will be ignored. Defaults to null.
	 *
	 * @return array|WP_Error Either an array of new Entry IDs or a WP_Error instance
	 */
	public static function add_entries( $entries, $form_id = null ) {

		$entry_ids = array();
		foreach ( $entries as $entry ) {
			if ( $form_id ) {
				$entry['form_id'] = $form_id;
			}
			$result = self::add_entry( $entry );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$entry_ids[] = $result;
		}

		return $entry_ids;
	}

	/**
	 * Updates multiple Entry objects.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFCommon::log_debug()
	 * @uses GFAPI::update_entry()
	 *
	 * @param array $entries The Entry objects
	 *
	 * @return bool|WP_Error Either true for success, or a WP_Error instance
	 */
	public static function update_entries( $entries ) {

		foreach ( $entries as $entry ) {
			$entry_id = rgar( $entry, 'id' );
			GFCommon::log_debug( 'Updating entry ' . $entry_id );
			$result = self::update_entry( $entry, $entry_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Updates an entire single Entry object.
	 *
	 * If the date_created value is not set then the current time UTC will be used.
	 * The date_created value, if set, is expected to be in 'Y-m-d H:i:s' format (UTC).
	 *
	 * @since  1.8
	 * @access public
	 * @global $wpdb
	 * @global $current_user
	 *
	 * @uses \GFAPI::get_entry
	 * @uses \GFAPI::form_id_exists
	 * @uses \GFFormsModel::get_ip
	 * @uses \GFFormsModel::get_current_page_url
	 * @uses \GFCommon::get_currency
	 * @uses \GFFormsModel::get_lead_table_name
	 * @uses \GFFormsModel::get_lead_details_table_name
	 * @uses \GFFormsModel::get_form_meta
	 * @uses \GFFormsModel::get_input_type
	 * @uses \GF_Field::get_entry_inputs
	 * @uses \GFFormsModel::get_lead_detail_id
	 * @uses \GFFormsModel::update_lead_field_value
	 * @uses \GFFormsModel::get_entry_meta
	 * @uses \GFFormsModel::get_field
	 *
	 * @param array $entry    The Entry Object.
	 * @param int   $entry_id Optional. If specified, the ID in the Entry Object will be ignored. Defaults to null.
	 *
	 * @return true|WP_Error Either True or a WP_Error instance
	 */
	public static function update_entry( $entry, $entry_id = null ) {
		global $wpdb;

		if ( empty( $entry_id ) ) {
			if ( rgar( $entry, 'id' ) ) {
				$entry_id = absint( $entry['id'] );
			}
		} else {
			$entry['id'] = absint( $entry_id );
		}

		if ( empty( $entry_id ) ) {
			return new WP_Error( 'missing_entry_id', __( 'Missing entry id', 'gravityforms' ) );
		}

		$current_entry = $original_entry = self::get_entry( $entry_id );

		if ( ! $current_entry ) {
			return new WP_Error( 'not_found', __( 'Entry not found', 'gravityforms' ), $entry_id );
		}

		if ( is_wp_error( $current_entry ) ) {
			return $current_entry;
		}

		// Make sure the form id exists
		$form_id = rgar( $entry, 'form_id' );
		if ( empty( $form_id ) ) {
			$form_id = rgar( $current_entry, 'form_id' );
		}

		if ( false === self::form_id_exists( $form_id ) ) {
			return new WP_Error( 'invalid_form_id', __( 'The form for this entry does not exist', 'gravityforms' ) );
		}

		/**
		 * Filters the entry before it is updated.
		 *
		 * @since Unknown
		 *
		 * @param array $entry          The Entry Object.
		 * @param array $original_entry Te original Entry Object, before changes.
		 */
		$entry = apply_filters( 'gform_entry_pre_update', $entry, $original_entry );

		// Use values in the entry object if present
		$post_id        = isset( $entry['post_id'] ) ? intval( $entry['post_id'] ) : 'NULL';
		$date_created   = isset( $entry['date_created'] ) ? sprintf( "'%s'", esc_sql( $entry['date_created'] ) ) : 'utc_timestamp()';
		$is_starred     = isset( $entry['is_starred'] ) ? $entry['is_starred'] : 0;
		$is_read        = isset( $entry['is_read'] ) ? $entry['is_read'] : 0;
		$ip             = isset( $entry['ip'] ) ? $entry['ip'] : GFFormsModel::get_ip();
		$source_url     = isset( $entry['source_url'] ) ? $entry['source_url'] : GFFormsModel::get_current_page_url();
		$user_agent     = isset( $entry['user_agent'] ) ? $entry['user_agent'] : 'API';
		$currency       = isset( $entry['currency'] ) ? $entry['currency'] : GFCommon::get_currency();
		$payment_status = isset( $entry['payment_status'] ) ? sprintf( "'%s'", esc_sql( $entry['payment_status'] ) ) : 'NULL';
		$payment_date   = strtotime( rgar( $entry, 'payment_date' ) ) ? "'" . gmdate( 'Y-m-d H:i:s', strtotime( "{$entry['payment_date']}" ) ) . "'" : 'NULL';
		$payment_amount = isset( $entry['payment_amount'] ) ? (float) $entry['payment_amount'] : 'NULL';
		$payment_method = isset( $entry['payment_method'] ) ? $entry['payment_method'] : '';
		$transaction_id = isset( $entry['transaction_id'] ) ? sprintf( "'%s'", esc_sql( $entry['transaction_id'] ) ) : 'NULL';
		$is_fulfilled   = isset( $entry['is_fulfilled'] ) ? intval( $entry['is_fulfilled'] ) : 'NULL';
		$status         = isset( $entry['status'] ) ? $entry['status'] : 'active';

		global $current_user;
		$user_id = isset( $entry['created_by'] ) ?  absint( $entry['created_by'] ) : '';
		if ( empty( $user_id ) ) {
			$user_id = $current_user && $current_user->ID ? absint( $current_user->ID ) : 'NULL';
		}

		$transaction_type = isset( $entry['transaction_type'] ) ? intval( $entry['transaction_type'] ) : 'NULL';

		$lead_table = GFFormsModel::get_lead_table_name();
		$sql = $wpdb->prepare(
				"
                UPDATE $lead_table
                SET
                form_id = %d,
                post_id = {$post_id},
                date_created = {$date_created},
                is_starred = %d,
                is_read = %d,
                ip = %s,
                source_url = %s,
                user_agent = %s,
                currency = %s,
                payment_status = {$payment_status},
                payment_date = {$payment_date},
                payment_amount = {$payment_amount},
                transaction_id = {$transaction_id},
                is_fulfilled = {$is_fulfilled},
                created_by = {$user_id},
                transaction_type = {$transaction_type},
                status = %s,
                payment_method = %s
                WHERE
                id = %d
                ", $form_id, $is_starred, $is_read, $ip, $source_url, $user_agent, $currency, $status, $payment_method, $entry_id
		);
		$result     = $wpdb->query( $sql );
		if ( false === $result ) {
			return new WP_Error( 'update_entry_properties_failed', __( 'There was a problem while updating the entry properties', 'gravityforms' ), $wpdb->last_error );
		}

		// Only save field values for fields that currently exist in the form. The rest in $entry will be ignored. The rest in $current_entry will get deleted.

		$lead_detail_table = GFFormsModel::get_lead_details_table_name();
		$current_fields    = $wpdb->get_results( $wpdb->prepare( "SELECT id, field_number FROM $lead_detail_table WHERE lead_id=%d", $entry_id ) );

		$form = GFFormsModel::get_form_meta( $form_id );

		$form = gf_apply_filters( array( 'gform_form_pre_update_entry', $form_id ), $form, $entry, $entry_id );

		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */
			$type = GFFormsModel::get_input_type( $field );
			if ( in_array( $type, array( 'html', 'page', 'section' ) ) ) {
				continue;
			}
			$inputs = $field->get_entry_inputs();
			if ( is_array( $inputs ) ) {
				foreach ( $field->inputs as $input ) {
					$input_id = (string) $input['id'];
					if ( isset( $entry[ $input_id ] ) ) {
						if ( $entry[ $input_id ] != $current_entry[ $input_id ] ) {
							$lead_detail_id = GFFormsModel::get_lead_detail_id( $current_fields, $input_id );
							$result         = GFFormsModel::update_lead_field_value( $form, $entry, $field, $lead_detail_id, $input_id, $entry[ $input_id ] );
							if ( false === $result ) {
								return new WP_Error( 'update_input_value_failed', __( 'There was a problem while updating one of the input values for the entry', 'gravityforms' ), $wpdb->last_error );
							}
						}
						unset( $current_entry[ $input_id ] );
					}
				}
			} else {
				$field_id    = $field->id;
				$field_value = isset( $entry[ (string) $field_id ] ) ? $entry[ (string) $field_id ] : '';
				if ( $field_value != $current_entry[ $field_id ] ) {
					$lead_detail_id = GFFormsModel::get_lead_detail_id( $current_fields, $field_id );
					$result         = GFFormsModel::update_lead_field_value( $form, $entry, $field, $lead_detail_id, $field_id, $field_value );
					if ( false === $result ) {
						return new WP_Error( 'update_field_values_failed', __( 'There was a problem while updating the field values', 'gravityforms' ), $wpdb->last_error );
					}
				}
				unset( $current_entry[ $field_id ] );
			}
		}

		// Save the entry meta values - only for the entry meta currently available for the form, ignore the rest.
		$entry_meta = GFFormsModel::get_entry_meta( $form_id );
		if ( is_array( $entry_meta ) ) {
			foreach ( array_keys( $entry_meta ) as $key ) {
				if ( isset( $entry[ $key ] ) ) {
					if ( $entry[ $key ] != $current_entry[ $key ] ) {
						gform_update_meta( $entry_id, $key, $entry[ $key ] );
					}
					unset( $current_entry[ $key ] );
				}
			}
		}

		// Now delete remaining values from the old entry.

		if ( is_array( $entry_meta ) ) {
			foreach ( array_keys( $entry_meta ) as $meta_key ) {
				if ( isset( $current_entry[ $meta_key ] ) ) {
					gform_delete_meta( $entry_id, $meta_key );
					unset( $current_entry[ $meta_key ] );
				}
			}
		}

		foreach ( $current_entry as $k => $v ) {
			$lead_detail_id = GFFormsModel::get_lead_detail_id( $current_fields, $k );
			$field          = GFFormsModel::get_field( $form, $k );
			$result         = GFFormsModel::update_lead_field_value( $form, $entry, $field, $lead_detail_id, $k, '' );
			if ( false === $result ) {
				return new WP_Error( 'update_field_values_failed', __( 'There was a problem while updating the field values', 'gravityforms' ), $wpdb->last_error );
			}
		}

		/**
		 * Fires after the Entry is updated.
		 *
		 * @since Unknown.
		 *
		 * @param array $lead           The entry object after being updated.
		 * @param array $original_entry The entry object before being updated.
		 */
		gf_do_action( array( 'gform_post_update_entry', $form_id ), $entry, $original_entry );

		return true;
	}

	/**
	 * Adds a single Entry object.
	 *
	 * Intended to be used for importing an entry object. The usual hooks that are triggered while saving entries are not fired here.
	 * Checks that the form id, field ids and entry meta exist and ignores legacy values (i.e. values for fields that no longer exist).
	 *
	 * @since  1.8
	 * @access public
	 * @global $wpdb
	 * @global $current_user
	 *
	 * @uses GFAPI::form_id_exists()
	 * @uses GFFormsModel::get_ip()
	 * @uses GFFormsModel::get_current_page_url()
	 * @uses GFCommon::get_currency()
	 * @uses GFFormsModel::get_lead_table_name()
	 * @uses GF_Field::get_entry_inputs()
	 * @uses GFFormsModel::update_lead_field_value()
	 * @uses GFFormsModel::get_entry_meta()
	 * @uses GFAPI::get_entry()
	 *
	 * @param array $entry The Entry Object.
	 *
	 * @return int|WP_Error Either the new Entry ID or a WP_Error instance.
	 */
	public static function add_entry( $entry ) {
		global $wpdb;

		if ( ! is_array( $entry ) ) {
			return new WP_Error( 'invalid_entry_object', __( 'The entry object must be an array', 'gravityforms' ) );
		}

		// Make sure the form id exists.
		$form_id = rgar( $entry, 'form_id' );
		if ( empty( $form_id ) ) {
			return new WP_Error( 'empty_form_id', __( 'The form id must be specified', 'gravityforms' ) );
		}

		if ( false === self::form_id_exists( $form_id ) ) {
			return new WP_Error( 'invalid_form_id', __( 'The form for this entry does not exist', 'gravityforms' ) );
		}

		// Use values in the entry object if present
		$post_id        = isset( $entry['post_id'] ) ? intval( $entry['post_id'] ) : 'NULL';
		$date_created   = isset( $entry['date_created'] ) && $entry['date_created'] != '' ? sprintf( "'%s'", esc_sql( $entry['date_created'] ) ) : 'utc_timestamp()';
		$is_starred     = isset( $entry['is_starred'] ) ? $entry['is_starred'] : 0;
		$is_read        = isset( $entry['is_read'] ) ? $entry['is_read'] : 0;
		$ip             = isset( $entry['ip'] ) ? $entry['ip'] : GFFormsModel::get_ip();
		$source_url     = isset( $entry['source_url'] ) ? $entry['source_url'] : esc_url_raw( GFFormsModel::get_current_page_url() );
		$user_agent     = isset( $entry['user_agent'] ) ? $entry['user_agent'] : 'API';
		$currency       = isset( $entry['currency'] ) ? $entry['currency'] : GFCommon::get_currency();
		$payment_status = isset( $entry['payment_status'] ) ? sprintf( "'%s'", esc_sql( $entry['payment_status'] ) ) : 'NULL';
		$payment_date   = strtotime( rgar( $entry, 'payment_date' ) ) ? sprintf( "'%s'", gmdate( 'Y-m-d H:i:s', strtotime( "{$entry['payment_date']}" ) ) ) : 'NULL';
		$payment_amount = isset( $entry['payment_amount'] ) ? (float) $entry['payment_amount'] : 'NULL';
		$payment_method = isset( $entry['payment_method'] ) ? $entry['payment_method'] : '';
		$transaction_id = isset( $entry['transaction_id'] ) ? sprintf( "'%s'", esc_sql( $entry['transaction_id'] ) ) : 'NULL';
		$is_fulfilled   = isset( $entry['is_fulfilled'] ) ? intval( $entry['is_fulfilled'] ) : 'NULL';
		$status         = isset( $entry['status'] ) ? $entry['status'] : 'active';

		global $current_user;
		$user_id = isset( $entry['created_by'] ) ? absint( $entry['created_by'] ) : '';
		if ( empty( $user_id ) ) {
			$user_id = $current_user && $current_user->ID ? absint( $current_user->ID )  : 'NULL';
		}

		$transaction_type = isset( $entry['transaction_type'] ) ? intval( $entry['transaction_type'] ) : 'NULL';

		$lead_table = GFFormsModel::get_lead_table_name();
		$result     = $wpdb->query(
			$wpdb->prepare(
				"
                INSERT INTO $lead_table
                (form_id, post_id, date_created, is_starred, is_read, ip, source_url, user_agent, currency, payment_status, payment_date, payment_amount, transaction_id, is_fulfilled, created_by, transaction_type, status, payment_method)
                VALUES
                (%d, {$post_id}, {$date_created}, %d,  %d, %s, %s, %s, %s, {$payment_status}, {$payment_date}, {$payment_amount}, {$transaction_id}, {$is_fulfilled}, {$user_id}, {$transaction_type}, %s, %s)
                ", $form_id, $is_starred, $is_read, $ip, $source_url, $user_agent, $currency, $status, $payment_method
			)
		);
		if ( false === $result ) {
			return new WP_Error( 'insert_entry_properties_failed', __( 'There was a problem while inserting the entry properties', 'gravityforms' ), $wpdb->last_error );
		}
		// Reading newly created lead id.
		$entry_id    = $wpdb->insert_id;
		$entry['id'] = $entry_id;

		// Only save field values for fields that currently exist in the form.
		$form = GFFormsModel::get_form_meta( $form_id );
		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */
			if ( in_array( $field->type, array( 'html', 'page', 'section' ) ) ) {
				continue;
			}
			$inputs = $field->get_entry_inputs();
			if ( is_array( $inputs ) ) {
				foreach ( $inputs as $input ) {
					$input_id = (string) $input['id'];
					if ( isset( $entry[ $input_id ] ) ) {
						$result = GFFormsModel::update_lead_field_value( $form, $entry, $field, 0, $input_id, $entry[ $input_id ] );
						if ( false === $result ) {
							return new WP_Error( 'insert_input_value_failed', __( 'There was a problem while inserting one of the input values for the entry', 'gravityforms' ), $wpdb->last_error );
						}
					}
				}
			} else {
				$field_id    = $field->id;
				$field_value = isset( $entry[ (string) $field_id ] ) ? $entry[ (string) $field_id ] : '';
				$result      = GFFormsModel::update_lead_field_value( $form, $entry, $field, 0, $field_id, $field_value );
				if ( false === $result ) {
					return new WP_Error( 'insert_field_values_failed', __( 'There was a problem while inserting the field values', 'gravityforms' ), $wpdb->last_error );
				}
			}
		}

		// Add save the entry meta values - only for the entry meta currently available for the form, ignore the rest.
		$entry_meta = GFFormsModel::get_entry_meta( $form_id );
		if ( is_array( $entry_meta ) ) {
			foreach ( array_keys( $entry_meta ) as $key ) {
				if ( isset( $entry[ $key ] ) ) {
					gform_update_meta( $entry_id, $key, $entry[ $key ], $form['id'] );
				}
			}
		}

        // Refresh the entry
        $entry = GFAPI::get_entry( $entry['id'] );

        /**
		 * Fires after the Entry is added using the API.
         *
         * @since  1.9.14.26
		 *
		 * @param array $entry The Entry Object added.
         * @param array $form  The Form Object added.
		 */
		do_action( 'gform_post_add_entry', $entry, $form );

		return $entry_id;
	}

	/**
	 * Deletes a single Entry.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @uses GFFormsModel::get_lead()
	 * @uses GFFormsModel::delete_lead()
	 *
	 * @param int $entry_id The ID of the Entry object.
	 *
	 * @return bool|WP_Error Either true for success or a WP_Error instance.
	 */
	public static function delete_entry( $entry_id ) {

		$entry = GFFormsModel::get_lead( $entry_id );
		if ( empty( $entry ) ) {
			return new WP_Error( 'invalid_entry_id', sprintf( __( 'Invalid entry id: %s', 'gravityforms' ), $entry_id ), $entry_id );
		}
		GFFormsModel::delete_lead( $entry_id );

		return true;
	}

	/**
	 * Updates a single property of an entry.
	 *
	 * @since  1.8.3.1
	 * @access public
	 *
	 * @uses GFFormsModel::update_lead_property()
	 *
	 * @param int    $entry_id The ID of the Entry object.
	 * @param string $property The property of the Entry object to be updated.
	 * @param mixed  $value    The value to which the property should be set.
	 *
	 * @return bool Whether the entry property was updated successfully.
	 */
	public static function update_entry_property( $entry_id, $property, $value ) {
		return GFFormsModel::update_lead_property( $entry_id, $property, $value );
	}

	/**
	 * Updates a single field of an entry.
	 *
	 * @since  1.9
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFAPI::get_entry()
	 * @uses GFAPI::get_form()
	 * @uses GFFormsModel::get_field()
	 * @uses GFFormsModel::get_lead_details_table_name()
	 * @uses GFFormsModel::update_lead_field_value()
	 *
	 * @param int    $entry_id The ID of the Entry object.
	 * @param string $input_id The id of the input to be updated. For single input fields such as text, paragraph, website, drop down etc... this will be the same as the field ID.
	 *                         For multi input fields such as name, address, checkboxes, etc... the input id will be in the format {FIELD_ID}.{INPUT NUMBER}. ( i.e. "1.3" ).
	 *                         The $input_id can be obtained by inspecting the key for the specified field in the $entry object.
	 * @param mixed  $value    The value to which the field should be set.
	 *
	 * @return bool|array Whether the entry property was updated successfully. If there's an error getting the entry, the entry object.
	 */
	public static function update_entry_field( $entry_id, $input_id, $value ) {
		global $wpdb;

		$entry = self::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		$form = self::get_form( $entry['form_id'] );
		if ( ! $form ) {
			return false;
		}

		$field = GFFormsModel::get_field( $form, $input_id );

		$input_id_min = (float) $input_id - 0.0001;
		$input_id_max = (float) $input_id + 0.0001;

		$lead_details_table_name = GFFormsModel::get_lead_details_table_name();

		$lead_detail_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$lead_details_table_name} WHERE lead_id=%d AND field_number BETWEEN %s AND %s", $entry_id, $input_id_min, $input_id_max ) );

		$result = true;
		if ( ! isset( $entry[ $input_id ] ) || $entry[ $input_id ] != $value ){
			$result = GFFormsModel::update_lead_field_value( $form, $entry, $field, $lead_detail_id, $input_id, $value );
		}

		return $result;
	}

	// FORM SUBMISSIONS -------------------------------------------

	/**
	 * Submits a form. Use this function to send input values through the complete form submission process.
	 * Supports field validation, notifications, confirmations, multiple-pages and save & continue.
	 *
	 * Example usage:
	 * $input_values['input_1']   = 'Single line text';
	 * $input_values['input_2_3'] = 'First name';
	 * $input_values['input_2_6'] = 'Last name';
	 * $input_values['input_5']   = 'A paragraph of text.';
	 * //$input_values['gform_save'] = true; // support for save and continue
	 *
	 * $result = GFAPI::submit_form( 52, $input_values );
	 *
	 * Example output for a successful submission:
	 * 'is_valid' => boolean true
	 * 'page_number' => int 0
	 * 'source_page_number' => int 1
	 * 'confirmation_message' => string 'confirmation message [snip]'
	 *
	 * Example output for failed validation:
	 * 'is_valid' => boolean false
	 * 'validation_messages' =>
	 *      array (size=1)
	 *          2 => string 'This field is required. Please enter the first and last name.'
	 *	'page_number' => int 1
	 *  'source_page_number' => int 1
	 *	'confirmation_message' => string ''
	 *
	 *
	 * Example output for save and continue:
	 * 'is_valid' => boolean true
	 * 'page_number' => int 1
	 * 'source_page_number' => int 1
	 * 'confirmation_message' => string 'Please use the following link to return to your form from any computer. [snip]'
	 * 'resume_token' => string '045f941cc4c04d479556bab1db6d3495'
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFAPI::get_form()
	 * @uses GFCommon::get_base_path()
	 * @uses GFFormDisplay::process_form()
	 * @uses GFFormDisplay::replace_save_variables()
	 *
	 * @param int $form_id The Form ID
	 * @param array $input_values An array of values. Not $_POST, that will be automatically merged with the $input_values.
	 * @param array $field_values Optional.
	 * @param int $target_page Optional.
	 * @param int $source_page Optional.
	 *
	 * @return array|WP_Error An array containing the result of the submission.
	 */
	public static function submit_form( $form_id, $input_values, $field_values = array(), $target_page = 0, $source_page = 1 ) {
		$form_id = absint( $form_id );
		$form    = GFAPI::get_form( $form_id );

		if ( empty( $form ) || ! $form['is_active'] || $form['is_trash'] ) {
			return new WP_Error( 'form_not_found', __( 'Your form could not be found', 'gravityforms' ) );
		}

		$input_values[ 'is_submit_' . $form_id ]                = true;
		$input_values['gform_submit']                           = $form_id;
		$input_values[ 'gform_target_page_number_' . $form_id ] = absint( $target_page );
		$input_values[ 'gform_source_page_number_' . $form_id ] = absint( $source_page );
		$input_values['gform_field_values']                     = $field_values;

		require_once( GFCommon::get_base_path() . '/form_display.php' );

		if ( ! isset( $_POST ) ) {
			$_POST = array();
		}

		$_POST = array_merge_recursive( $_POST, $input_values );

		try {
			GFFormDisplay::process_form( $form_id );
		} catch ( Exception $ex ) {
			return new WP_Error( 'error_processing_form', __( 'There was an error while processing the form:', 'gravityforms' ) . ' ' . $ex->getCode() . ' ' . $ex->getMessage() );
		}

		if ( empty( GFFormDisplay::$submission ) ) {
			return new WP_Error( 'error_processing_form', __( 'There was an error while processing the form:', 'gravityforms' ) );
		}

		$submissions_array = GFFormDisplay::$submission;

		$submission_details = $submissions_array[ $form_id ];

		$result = array();

		$result['is_valid'] = $submission_details['is_valid'];

		if ( $result['is_valid'] == false ) {
			$validation_messages = array();
			foreach ( $submission_details['form']['fields'] as $field ) {
				if ( $field->failed_validation ) {
					$validation_messages[ $field->id ] = $field->validation_message;
				}
			}
			$result['validation_messages'] = $validation_messages;
		}

		$result['page_number']          = $submission_details['page_number'];
		$result['source_page_number']   = $submission_details['source_page_number'];
		$result['confirmation_message'] = $submission_details['confirmation_message'];

		if ( isset( $submission_details['resume_token'] ) ) {
			$result['resume_token'] = $submission_details['resume_token'];

			$form = self::get_form( $form_id );

			$result['confirmation_message'] = GFFormDisplay::replace_save_variables( $result['confirmation_message'], $form, $result['resume_token'] );
		}

		return $result;
	}

	// FEEDS ------------------------------------------------------

	/**
	 * Returns all the feeds for the given criteria.
	 *
	 * @since  1.8
	 * @access public
	 * @global $wpdb
	 *
	 * @param mixed  $feed_ids   The ID of the Feed or an array of Feed IDs.
	 * @param int    $form_id    The ID of the Form to which the Feeds belong.
	 * @param string $addon_slug The slug of the add-on to which the Feeds belong.
	 * @param bool   $is_active  If the feed is active.
	 *
	 * @return array|WP_Error Either an array of Feed objects or a WP_Error instance.
	 */
	public static function get_feeds( $feed_ids = null, $form_id = null, $addon_slug = null, $is_active = true ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'gf_addon_feed';
		$where_arr   = array();
		$where_arr[] = $wpdb->prepare( 'is_active=%d', $is_active );
		if ( false === empty( $form_id ) ) {
			$where_arr[] = $wpdb->prepare( 'form_id=%d', $form_id );
		}
		if ( false === empty( $addon_slug ) ) {
			$where_arr[] = $wpdb->prepare( 'addon_slug=%s', $addon_slug );
		}
		if ( false === empty( $feed_ids ) ) {
			if ( ! is_array( $feed_ids ) ) {
				$feed_ids = array( $feed_ids );
			}
			$in_str_arr  = array_fill( 0, count( $feed_ids ), '%d' );
			$in_str      = join( $in_str_arr, ',' );
			$where_arr[] = $wpdb->prepare( "id IN ($in_str)", $feed_ids );
		}


		$where = join( ' AND ', $where_arr );

		$sql = "SELECT id, form_id, addon_slug, meta FROM {$table} WHERE $where";

		$results = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $results ) ) {
			return new WP_Error( 'not_found', __( 'Feed not found', 'gravityforms' ) );
		}

		foreach ( $results as &$result ) {
			$result['meta'] = json_decode( $result['meta'], true );
		}

		return $results;
	}

	/**
	 * Deletes a single Feed.
	 *
	 * @since  1.8
	 * @access public
	 * @global $wpdb
	 *
	 * @param int $feed_id The ID of the Feed to delete.
	 *
	 * @return bool|WP_Error True if successful, or a WP_Error instance.
	 */
	public static function delete_feed( $feed_id ) {

		global $wpdb;

		$table = $wpdb->prefix . 'gf_addon_feed';

		$sql = $wpdb->prepare( "DELETE FROM {$table} WHERE id=%d", $feed_id );

		$results = $wpdb->query( $sql );
		if ( false === $results ) {
			return new WP_Error( 'error_deleting', sprintf( __( 'There was an error while deleting feed id %s', 'gravityforms' ), $feed_id ), $wpdb->last_error );
		}

		if ( 0 === $results ) {
			return new WP_Error( 'not_found', sprintf( __( 'Feed id %s not found', 'gravityforms' ), $feed_id ) );
		}

		return true;
	}

	/**
	 * Updates a feed.
	 *
	 * @param int   $feed_id   The ID of the feed being updated.
	 * @param array $feed_meta The feed meta to replace the existing feed meta.
	 * @param null  $form_id   The ID of the form that the feed is associated with
	 *
	 * @return false|int|WP_Error
	 */
	public static function update_feed( $feed_id, $feed_meta, $form_id = null ) {
		global $wpdb;

		$feed_meta_json = json_encode( $feed_meta );
		$table          = $wpdb->prefix . 'gf_addon_feed';
		if ( empty( $form_id ) ) {
			$sql = $wpdb->prepare( "UPDATE {$table} SET meta= %s WHERE id=%d", $feed_meta_json, $feed_id );
		} else {
			$sql = $wpdb->prepare( "UPDATE {$table} SET form_id = %d, meta= %s WHERE id=%d", $form_id, $feed_meta_json, $feed_id );
		}

		$results = $wpdb->query( $sql );

		if ( false === $results ) {
			return new WP_Error( 'error_updating', sprintf( __( 'There was an error while updating feed id %s', 'gravityforms' ), $feed_id ), $wpdb->last_error );
		}

		if ( 0 === $results ) {
			return new WP_Error( 'not_found', sprintf( __( 'Feed id %s not found', 'gravityforms' ), $feed_id ) );
		}

		return $results;
	}

	/**
	 * Adds a feed with the given Feed object.
	 *
	 * @since  1.8
	 * @access public
	 * @global $wpdb
	 *
	 * @param int    $form_id    The ID of the form to which the feed belongs.
	 * @param array  $feed_meta  The Feed Object.
	 * @param string $addon_slug The slug of the add-on to which the feeds belong.
	 *
	 * @return int|WP_Error Either the ID of the newly created feed or a WP_Error instance.
	 */
	public static function add_feed( $form_id, $feed_meta, $addon_slug ) {
		global $wpdb;

		$table          = $wpdb->prefix . 'gf_addon_feed';
		$feed_meta_json = json_encode( $feed_meta );
		$sql            = $wpdb->prepare( "INSERT INTO {$table} (form_id, meta, addon_slug) VALUES (%d, %s, %s)", $form_id, $feed_meta_json, $addon_slug );

		$results = $wpdb->query( $sql );

		if ( false === $results ) {
			return new WP_Error( 'error_inserting', __( 'There was an error while inserting a feed', 'gravityforms' ), $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}

	// NOTIFICATIONS ----------------------------------------------

	/**
	 * Sends all active notifications for a form given an entry object and an event.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::log_debug()
	 * @uses GFCommon::send_notifications()
	 *
	 * @param array  $form  The Form Object associated with the notification.
	 * @param array  $entry The Entry Object associated with the triggered event.
	 * @param string $event Optional. The event that's firing the notification. Defaults to 'form_submission'.
	 * @param array  $data  Optional. Array of data which can be used in the notifications via the generic {object:property} merge tag. Defaults to empty array.
	 *
	 * @return array
	 */
	public static function send_notifications( $form, $entry, $event = 'form_submission', $data = array() ) {

		if ( rgempty( 'notifications', $form ) || ! is_array( $form['notifications'] ) ) {
			return array();
		}

		$entry_id = rgar( $entry, 'id' );
		GFCommon::log_debug( "GFAPI::send_notifications(): Gathering notifications for {$event} event for entry #{$entry_id}." );

		$notifications_to_send = array();

		// Running through filters that disable form submission notifications.
		foreach ( $form['notifications'] as $notification ) {
			if ( rgar( $notification, 'event' ) != $event ) {
				continue;
			}

			if ( $event == 'form_submission' ) {
				/**
				 * Disables user notifications.
				 *
				 * @since Unknown
				 *
				 * @param bool  false  Determines if the notification will be disabled. Set to true to disable the notification.
				 * @param array $form  The Form Object that triggered the notification event.
				 * @param array $entry The Entry Object that triggered the notification event.
				 */
				if ( rgar( $notification, 'type' ) == 'user' && gf_apply_filters( array( 'gform_disable_user_notification', $form['id'] ), false, $form, $entry ) ) {
					GFCommon::log_debug( "GFAPI::send_notifications(): Notification is disabled by gform_disable_user_notification hook, not including notification (#{$notification['id']} - {$notification['name']})." );
					// Skip user notification if it has been disabled by a hook.
					continue;
				/**
				 * Disables admin notifications.
				 *
				 * @since Unknown
				 *
				 * @param bool  false  Determines if the notification will be disabled. Set to true to disable the notification.
				 * @param array $form  The Form Object that triggered the notification event.
				 * @param array $entry The Entry Object that triggered the notification event.
				 */
				} elseif ( rgar( $notification, 'type' ) == 'admin' && gf_apply_filters( array( 'gform_disable_admin_notification', $form['id'] ), false, $form, $entry ) ) {
					GFCommon::log_debug( "GFAPI::send_notifications(): Notification is disabled by gform_disable_admin_notification hook, not including notification (#{$notification['id']} - {$notification['name']})." );
					// Skip admin notification if it has been disabled by a hook.
					continue;
				}
			}

			/**
			 * Disables notifications.
			 *
			 * @since Unknown
			 *
			 * @param bool  false  Determines if the notification will be disabled. Set to true to disable the notification.
			 * @param array $form  The Form Object that triggered the notification event.
			 * @param array $entry The Entry Object that triggered the notification event.
			 */
			if ( gf_apply_filters( array( 'gform_disable_notification', $form['id'] ), false, $notification, $form, $entry ) ) {
				GFCommon::log_debug( "GFAPI::send_notifications(): Notification is disabled by gform_disable_notification hook, not including notification (#{$notification['id']} - {$notification['name']})." );
				// Skip notifications if it has been disabled by a hook
				continue;
			}

			$notifications_to_send[] = $notification['id'];
		}

		GFCommon::send_notifications( $notifications_to_send, $form, $entry, true, $event, $data );
	}


	// PERMISSIONS ------------------------------------------------
	/**
	 * Checks the permissions for the current user. Returns true if the current user has any of the specified capabilities.
	 *
	 * IMPORTANT: Call this before calling any of the other API Functions as permission checks are not performed at lower levels.
	 *
	 * @since  1.8.5.10
	 * @access public
	 *
	 * @uses GFCommon::current_user_can_any()
	 *
	 * @param array|string $capabilities An array of capabilities, or a single capability
	 *
	 * @return bool Returns true if the current user has any of the specified capabilities
	 */
	public static function current_user_can_any( $capabilities ) {
		return GFCommon::current_user_can_any( $capabilities );
	}

	// FIELDS -----------------------------------------------------

	/**
	 * Returns an array containing the form fields of the specified type or types.
	 *
	 * @since  1.9.9.8
	 * @access public
	 *
	 * @param array        $form           The Form Object.
	 * @param array|string $types          The field types to get. Multiple field types as an array or a single type in a string.
	 * @param bool         $use_input_type Optional. Defaults to false.
	 *
	 * @uses GFFormsModel::get_fields_by_type()
	 *
	 * @return object GF_Field
	 */
	public static function get_fields_by_type( $form, $types, $use_input_type = false ) {
		return GFFormsModel::get_fields_by_type( $form, $types, $use_input_type );
	}

	// HELPERS ----------------------------------------------------

	/**
	 * Private.
	 *
	 * @since  1.8
	 * @access private
	 * @ignore
	 */
	public static function form_id_exists( $form_id ) {
		global $wpdb;
		$form_table_name = GFFormsModel::get_form_table_name();
		$form_id         = intval( $form_id );
		$result          = $wpdb->get_var(
			$wpdb->prepare(
				" SELECT count(id) FROM {$form_table_name}
                  WHERE id=%d", $form_id
			)
		);

		$result = intval( $result );

		return $result > 0;
	}

}
