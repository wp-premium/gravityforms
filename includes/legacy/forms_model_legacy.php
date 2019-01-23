<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

require_once( ABSPATH . WPINC . '/post.php' );

/**
 * Class GF_Forms_Model_Legacy
 *
 * Legacy methods from GFFormsModel
 */
class GF_Forms_Model_Legacy {

	/**
	 * Gets the form table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The form table name.
	 */
	public static function get_form_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rg_form';
	}

	/**
	 * Gets the form meta table, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The form meta table.
	 */
	public static function get_meta_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rg_form_meta';
	}

	/**
	 * Gets the form view table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The form view table name.
	 */
	public static function get_form_view_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rg_form_view';
	}


	/**
	 * Gets the lead (entries) table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The lead (entry) table name
	 */
	public static function get_lead_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'rg_lead';
	}

	/**
	 * Gets the lead (entry) meta table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The lead (entry) meta table name
	 */
	public static function get_lead_meta_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'rg_lead_meta';
	}

	/**
	 * Gets the lead (entry) notes table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The lead (entry) notes table name
	 */
	public static function get_lead_notes_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'rg_lead_notes';
	}

	/**
	 * Gets the lead (entry) details table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The lead (entry) details table name
	 */
	public static function get_lead_details_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'rg_lead_detail';
	}

	/**
	 * Gets the lead (entry) details long table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The lead (entry) details long table name
	 */
	public static function get_lead_details_long_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'rg_lead_detail_long';
	}

	/**
	 * Gets the lead (entry) view table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The lead (entry) view table name
	 */
	public static function get_lead_view_name() {
		global $wpdb;

		return $wpdb->prefix . 'rg_lead_view';
	}

	/**
	 * Gets the incomplete submissions table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The incomplete submissions table name
	 */
	public static function get_incomplete_submissions_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'rg_incomplete_submissions';
	}

	public static function get_legacy_tables() {
		return array(
			self::get_form_table_name(),
			self::get_meta_table_name(),
			self::get_form_view_table_name(),
			self::get_lead_details_long_table_name(),
			self::get_lead_notes_table_name(),
			self::get_lead_details_table_name(),
			self::get_lead_table_name(),

			self::get_lead_meta_table_name(),
			self::get_incomplete_submissions_table_name(),
		);
	}

	/**
	 * Gets the number of entries per form.
	 *
	 * First attempts to read from cache. If unavailable, gets the entry count, caches it, and returns it.
	 *
	 * @since 2.3 lead_count changed to entry_count
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 * @see GFFormsModel::get_lead_table_name
	 * @see GFCache::get
	 * @see GFCache::set
	 *
	 * @return array $entry_count Array of forms, containing the form ID and the entry count
	 */
	public static function get_entry_count_per_form() {
		global $wpdb;
		$lead_table_name = self::get_lead_table_name();

		$entry_count = GFCache::get( 'get_entry_count_per_form' );
		if ( empty( $entry_count ) ) {
			//Getting entry count per form
			$sql         = "SELECT form_id, count(id) as entry_count FROM $lead_table_name l WHERE status='active' GROUP BY form_id";
			$entry_count = $wpdb->get_results( $sql );

			GFCache::set( 'get_entry_count_per_form', $entry_count, true, 30 );
		}

		return $entry_count;
	}

	/**
	 * Gets the total, unread, starred, spam, and trashed entry counts.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_lead_table_name()
	 * @uses GFFormsModel::get_lead_details_table_name()
	 *
	 * @param int $form_id The ID of the form to check.
	 *
	 * @return array $results[0] The form counts.
	 */
	public static function get_form_counts( $form_id ) {
		global $wpdb;
		$lead_table_name = self::get_lead_table_name();
		$lead_detail_table_name = self::get_lead_details_table_name();

		$sql             = $wpdb->prepare(
			"SELECT
                    (SELECT count(DISTINCT(l.id)) FROM $lead_table_name l INNER JOIN $lead_detail_table_name ld ON l.id=ld.lead_id WHERE l.form_id=%d AND l.status='active') as total,
                    (SELECT count(DISTINCT(l.id)) FROM $lead_table_name l INNER JOIN $lead_detail_table_name ld ON l.id=ld.lead_id WHERE l.is_read=0 AND l.status='active' AND l.form_id=%d) as unread,
                    (SELECT count(DISTINCT(l.id)) FROM $lead_table_name l INNER JOIN $lead_detail_table_name ld ON l.id=ld.lead_id WHERE l.is_starred=1 AND l.status='active' AND l.form_id=%d) as starred,
                    (SELECT count(DISTINCT(l.id)) FROM $lead_table_name l INNER JOIN $lead_detail_table_name ld ON l.id=ld.lead_id WHERE l.status='spam' AND l.form_id=%d) as spam,
                    (SELECT count(DISTINCT(l.id)) FROM $lead_table_name l INNER JOIN $lead_detail_table_name ld ON l.id=ld.lead_id WHERE l.status='trash' AND l.form_id=%d) as trash",
			$form_id, $form_id, $form_id, $form_id, $form_id
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return $results[0];

	}

	public static function update_leads_property( $leads, $property_name, $property_value ) {
		foreach ( $leads as $lead ) {
			self::update_lead_property( $lead, $property_name, $property_value );
		}
	}

	public static function update_lead_property( $lead_id, $property_name, $property_value, $update_akismet = true, $disable_hook = false ) {
		global $wpdb;
		$lead_table = GFFormsModel::get_lead_table_name();

		$lead = GFFormsModel::get_lead( $lead_id );

		//marking entry as 'spam' or 'not spam' with Akismet if the plugin is installed
		if ( $update_akismet && GFCommon::akismet_enabled( $lead['form_id'] ) && $property_name == 'status' && in_array( $property_value, array( 'active', 'spam' ) ) ) {

			$current_status = $lead['status'];
			if ( $current_status == 'spam' && $property_value == 'active' ) {
				$form = GFFormsModel::get_form_meta( $lead['form_id'] );
				GFCommon::mark_akismet_spam( $form, $lead, false );
			} else if ( $current_status == 'active' && $property_value == 'spam' ) {
				$form = GFFormsModel::get_form_meta( $lead['form_id'] );
				GFCommon::mark_akismet_spam( $form, $lead, true );
			}
		}

		//updating lead
		$result = $wpdb->update( $lead_table, array( $property_name => $property_value ), array( 'id' => $lead_id ) );

		if ( ! $disable_hook ) {

			$previous_value = rgar( $lead, $property_name );

			if ( $previous_value != $property_value ) {

				// if property is status, prev value is spam and new value is active
				if ( $property_name == 'status' && $previous_value == 'spam' && $property_value == 'active' && ! rgar( $lead, 'post_id' ) ) {
					$lead[ $property_name ] = $property_value;
					$lead['post_id']      = GFCommon::create_post( isset( $form ) ? $form : GFAPI::get_form( $lead['form_id'] ), $lead );
				}

				/**
				 * Fired after an entry property is updated
				 *
				 * @param string $property_name Used within the action string.  Defines the property that fires the action.
				 *
				 * @param int    $lead_id        The Entry ID
				 * @param string $property_value The new value of the property that was updated
				 * @param string $previous_value The previous property value before the update
				 */
				do_action( "gform_update_{$property_name}", $lead_id, $property_value, $previous_value );
			}
		}

		return $result;
	}

	public static function delete_leads( $leads ) {
		foreach ( $leads as $lead_id ) {
			self::delete_lead( $lead_id );
		}
	}

	public static function delete_lead( $lead_id ) {

		global $wpdb;

		GFCommon::log_debug( __METHOD__ . "(): Deleting entry #{$lead_id}." );

		/**
		 * Fires before a lead is deleted
		 * @param $lead_id
		 * @deprecated
		 * @see gform_delete_entry
		 */
		do_action( 'gform_delete_lead', $lead_id );

		$lead_table             = self::get_lead_table_name();
		$lead_notes_table       = self::get_lead_notes_table_name();
		$lead_detail_table_name = self::get_lead_details_table_name();

		//deleting uploaded files
		GFFormsModel::delete_files( $lead_id );

		//Delete from lead details
		$sql = $wpdb->prepare( "DELETE FROM $lead_detail_table_name WHERE lead_id=%d", $lead_id );
		$wpdb->query( $sql );

		//Delete from lead notes
		$sql = $wpdb->prepare( "DELETE FROM $lead_notes_table WHERE lead_id=%d", $lead_id );
		$wpdb->query( $sql );

		//Delete from lead meta
		gform_delete_meta( $lead_id );

		//Delete from lead
		$sql = $wpdb->prepare( "DELETE FROM $lead_table WHERE id=%d", $lead_id );
		$wpdb->query( $sql );

	}

	public static function delete_leads_by_form( $form_id, $status = '' ) {
		global $wpdb;

		$lead_table             = GFFormsModel::get_lead_table_name();
		$lead_notes_table       = GFFormsModel::get_lead_notes_table_name();
		$lead_detail_table      = GFFormsModel::get_lead_details_table_name();
		$lead_meta_table 		= GFFormsModel::get_lead_meta_table_name();

		GFCommon::log_debug( __METHOD__ . "(): Deleting entries for form #{$form_id}." );

		/**
		 * Fires when you delete entries for a specific form
		 *
		 * @param int    $form_id The form ID to specify from which form to delete entries
		 * @param string $status  Allows you to set the form entries to a deleted status
		 */
		do_action( 'gform_delete_entries', $form_id, $status );

		//deleting uploaded files
		self::delete_files_by_form( $form_id, $status );

		$status_filter = empty( $status ) ? '' : $wpdb->prepare( 'AND status=%s', $status );

		//Delete from lead details
		$sql = $wpdb->prepare(
			" DELETE FROM $lead_detail_table
                                WHERE lead_id IN (
                                    SELECT id FROM $lead_table WHERE form_id=%d {$status_filter}
                                )", $form_id
		);
		$wpdb->query( $sql );

		//Delete from lead notes
		$sql = $wpdb->prepare(
			" DELETE FROM $lead_notes_table
                                WHERE lead_id IN (
                                    SELECT id FROM $lead_table WHERE form_id=%d {$status_filter}
                                )", $form_id
		);
		$wpdb->query( $sql );

		//Delete from lead meta
		$sql = $wpdb->prepare(
			" DELETE FROM $lead_meta_table
        						WHERE lead_id IN (
        							SELECT id FROM $lead_table WHERE form_id=%d {$status_filter}
                                )", $form_id
		);
		$wpdb->query( $sql );

		//Delete from lead
		$sql = $wpdb->prepare( "DELETE FROM $lead_table WHERE form_id=%d {$status_filter}", $form_id );
		$wpdb->query( $sql );
	}

	public static function delete_files_by_form( $form_id, $status = '' ) {
		global $wpdb;
		$form   = GFFormsModel::get_form_meta( $form_id );

		// Default field types to delete
		$field_types = array( 'fileupload', 'post_image' );

		/**
		 * Allows more files to be deleted
		 *
		 * @since 1.9.10
		 *
		 * @param array $field_types Field types which contain file uploads
		 * @param array $form        The Form Object
		 */
		$field_types = gf_apply_filters( array( 'gform_field_types_delete_files', $form_id ), $field_types, $form );


		$fields = GFFormsModel::get_fields_by_type( $form, $field_types );
		if ( empty( $fields ) ) {
			return;
		}

		$status_filter = empty( $status ) ? '' : $wpdb->prepare( 'AND status=%s', $status );
		$results       = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rg_lead WHERE form_id=%d {$status_filter}", $form_id ), ARRAY_A );

		foreach ( $results as $result ) {
			GFFormsModel::delete_files( $result['id'], $form );
		}
	}

	public static function delete_file( $entry_id, $field_id, $file_index = 0 ) {
		global $wpdb;

		if ( $entry_id == 0 || $field_id == 0 ) {
			return;
		}

		$entry          = self::get_lead( $entry_id );
		$form_id        = $entry['form_id'];
		$form           = GFFormsModel::get_form_meta( $form_id );
		$field          = GFFormsModel::get_field( $form, $field_id );
		$multiple_files = $field->multipleFiles;
		if ( $multiple_files ) {
			$file_urls = json_decode( $entry[ $field_id ], true );
			$file_url  = $file_urls[ $file_index ];
			unset( $file_urls[ $file_index ] );
			$file_urls   = array_values( $file_urls );
			$field_value = empty( $file_urls ) ? '' : json_encode( $file_urls );
		} else {
			$file_url    = $entry[ $field_id ];
			$field_value = '';
		}

		self::delete_physical_file( $file_url );

		// update lead field value - simulate form submission

		$lead_detail_table = self::get_lead_details_table_name();
		$sql               = $wpdb->prepare( "SELECT id FROM $lead_detail_table WHERE lead_id=%d AND meta_key = %s", $entry_id, $field_id );
		$entry_detail_id   = $wpdb->get_var( $sql );

		GFFormsModel::update_lead_field_value( $form, $entry, $field, $entry_detail_id, $field_id, $field_value );

	}

	private static function delete_physical_file( $file_url ) {
		$ary = explode( '|:|', $file_url );
		$url = rgar( $ary, 0 );
		if ( empty( $url ) ) {
			return;
		}

		$file_path = GFFormsModel::get_physical_file_path( $url );

		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}

	public static function get_lead( $lead_id ) {
		return GFAPI::get_entry( $lead_id );
	}

	public static function delete_field_values( $form_id, $field_id ) {
		global $wpdb;

		$lead_table             = self::get_lead_table_name();
		$lead_detail_table      = self::get_lead_details_table_name();

		// Delete from lead details
		$sql = $wpdb->prepare( "DELETE FROM $lead_detail_table WHERE form_id=%d AND field_number >= %d AND field_number < %d", $form_id, $field_id, $field_id + 1 );
		$wpdb->query( $sql );

		//Delete leads with no details
		$sql = $wpdb->prepare(
			" DELETE FROM $lead_table
                                WHERE form_id=%d
                                AND id NOT IN(
                                    SELECT DISTINCT(lead_id) FROM $lead_detail_table WHERE form_id=%d
                                )", $form_id, $form_id
		);
		$wpdb->query( $sql );
	}

	public static function save_lead( $form, &$lead ) {
		global $wpdb;

		GFCommon::log_debug( __METHOD__ . '(): Saving entry.' );

		$is_form_editor  = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		if ( $is_admin && ! GFCommon::current_user_can_any( 'gravityforms_edit_entries' ) ) {
			die( esc_html__( "You don't have adequate permission to edit entries.", 'gravityforms' ) );
		}

		$lead_detail_table = self::get_lead_details_table_name();
		$is_new_lead       = $lead == null;

		//Inserting lead if null
		if ( $is_new_lead ) {

			global $current_user;
			$user_id = $current_user && $current_user->ID ? $current_user->ID : 'NULL';

			$lead_table = RGFormsModel::get_lead_table_name();
			$user_agent = self::truncate( rgar( $_SERVER, 'HTTP_USER_AGENT' ), 250 );
			$user_agent = sanitize_text_field( $user_agent );
			$source_url = self::truncate( GFFormsModel::get_current_page_url(), 200 );

			/**
			 * Allow the currency code to be overridden.
			 *
			 * @param string $currency The three character ISO currency code to be stored in the entry. Default is value returned by GFCommon::get_currency()
			 * @param array $form The form currently being processed.
			 *
			 */
			$currency = gf_apply_filters( array( 'gform_currency_pre_save_entry', $form['id'] ), GFCommon::get_currency(), $form );

			$wpdb->query( $wpdb->prepare( "INSERT INTO $lead_table(form_id, ip, source_url, date_created, user_agent, currency, created_by) VALUES(%d, %s, %s, utc_timestamp(), %s, %s, {$user_id})", $form['id'], GFFormsModel::get_ip(), $source_url, $user_agent, $currency ) );


			//reading newly created lead id
			$lead_id = $wpdb->insert_id;

			if ( $lead_id == 0 ) {
				GFCommon::log_error( __METHOD__ . '(): Unable to save entry. ' . $wpdb->last_error );

				die( esc_html__( 'An error prevented the entry for this form submission being saved. Please contact support.', 'gravityforms' ) );
			}

			$lead = array( 'id' => $lead_id );

			GFCommon::log_debug( __METHOD__ . "(): Entry record created in the database. ID: {$lead_id}." );
		}

		$current_fields   = $wpdb->get_results( $wpdb->prepare( "SELECT id, field_number FROM $lead_detail_table WHERE lead_id=%d", $lead['id'] ) );

		$total_fields = array();
		/* @var $calculation_fields GF_Field[] */
		$calculation_fields = array();
		$recalculate_total  = false;

		GFCommon::log_debug( __METHOD__ . '(): Saving entry fields.' );

		foreach ( $form['fields'] as $field ) {
			/* @var $field GF_Field */

			// ignore the honeypot field
			if ( $field->type == 'honeypot' ) {
				continue;
			}

			//Ignore fields that are marked as display only
			if ( $field->displayOnly && $field->type != 'password' ) {
				continue;
			}

			// Ignore pricing fields in the entry detail
			if ( $is_entry_detail && GFCommon::is_pricing_field( $field->type ) ) {
				continue;
			}


			// Process total field after all fields have been saved
			if ( $field->type == 'total' ) {
				$total_fields[] = $field;
				continue;
			}

			$read_value_from_post = $is_new_lead || ! isset( $lead[ 'date_created' ] );

			// Only save fields that are not hidden (except when updating an entry)
			if ( $is_entry_detail || ! GFFormsModel::is_field_hidden( $form, $field, array(), $read_value_from_post ? null : $lead ) ) {

				// process calculation fields after all fields have been saved (moved after the is hidden check)
				if ( $field->has_calculation() ) {
					$calculation_fields[] = $field;
					continue;
				}

				if ( $field->type == 'post_category' ) {
					$field = GFCommon::add_categories_as_choices( $field, '' );
				}

				$inputs = $field->get_entry_inputs();
				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {
						self::save_input( $form, $field, $lead, $current_fields, $input['id'] );
					}
				} else {
					self::save_input( $form, $field, $lead, $current_fields, $field->id );
				}
			}
		}

		if ( ! empty( $calculation_fields ) ) {
			foreach ( $calculation_fields as $calculation_field ) {
				$inputs = $calculation_field->get_entry_inputs();
				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {
						self::save_input( $form, $calculation_field, $lead, $current_fields, $input['id'] );
						GFFormsModel::refresh_lead_field_value( $lead['id'], $input['id'] );
					}
				} else {
					self::save_input( $form, $calculation_field, $lead, $current_fields, $calculation_field->id );
					GFFormsModel::refresh_lead_field_value( $lead['id'], $calculation_field->id );
				}
			}
			GFFormsModel::refresh_product_cache( $form, $lead = RGFormsModel::get_lead( $lead['id'] ) );
		}

		//saving total field as the last field of the form.
		if ( ! empty( $total_fields ) ) {
			foreach ( $total_fields as $total_field ) {
				self::save_input( $form, $total_field, $lead, $current_fields, $total_field->id );
				GFFormsModel::refresh_lead_field_value( $lead['id'], $total_field->id );
			}
		}
		GFCommon::log_debug( __METHOD__ . '(): Finished saving entry fields.' );
	}

	public static function save_input( $form, $field, &$lead, $current_fields, $input_id ) {

		$input_name = 'input_' . str_replace( '.', '_', $input_id );

		if ( $field->enableCopyValuesOption && rgpost( 'input_' . $field->id . '_copy_values_activated' ) ) {
			$source_field_id   = $field->copyValuesOptionField;
			$source_input_name = str_replace( 'input_' . $field->id, 'input_' . $source_field_id, $input_name );
			$value             = rgpost( $source_input_name );
		} else {
			$value = rgpost( $input_name );
		}

		$value = GFFormsModel::maybe_trim_input( $value, $form['id'], $field );

		//ignore file upload when nothing was sent in the admin
		//ignore post fields in the admin
		$type           = GFFormsModel::get_input_type( $field );
		$multiple_files = $field->multipleFiles;
		$uploaded_files = GFFormsModel::$uploaded_files;
		$form_id        = $form['id'];
		if ( RG_CURRENT_VIEW == 'entry' && $type == 'fileupload' && ( ( ! $multiple_files && empty( $_FILES[ $input_name ]['name'] ) ) || ( $multiple_files && ! isset( $uploaded_files[ $form_id ][ $input_name ] ) ) ) ) {
			return;
		} else if ( RG_CURRENT_VIEW == 'entry' && in_array( $field->type, array( 'post_category', 'post_title', 'post_content', 'post_excerpt', 'post_tags', 'post_custom_field', 'post_image' ) ) ) {
			return;
		}

		$is_form_editor = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin = $is_form_editor || $is_entry_detail;

		if ( empty( $value ) && $field->is_administrative() && ! $is_admin ) {
			$value = GFFormsModel::get_default_value( $field, $input_id );
		}

		//processing values so that they are in the correct format for each input type
		$value = GFFormsModel::prepare_value( $form, $field, $value, $input_name, rgar( $lead, 'id' ) );

		//ignore fields that have not changed
		if ( $lead != null && isset( $lead[ $input_id ] ) && $value === rgget( (string) $input_id, $lead ) ) {
			return;
		}

		$lead_detail_id = GFFormsModel::get_lead_detail_id( $current_fields, $input_id );
		$result         = GFFormsModel::update_lead_field_value( $form, $lead, $field, $lead_detail_id, $input_id, $value );
		GFCommon::log_debug( __METHOD__ . "(): Saving: {$field->label}(#{$input_id} - {$field->type}). Result: " . var_export( $result, 1 ) );

	}

	private static function truncate( $str, $length ) {
		if ( strlen( $str ) > $length ) {
			$str = substr( $str, 0, $length );
		}

		return $str;
	}

	public static function is_duplicate( $form_id, $field, $value ) {
		global $wpdb;

		$lead_detail_table_name = self::get_lead_details_table_name();
		$lead_table_name        = self::get_lead_table_name();


		switch ( GFFormsModel::get_input_type( $field ) ) {
			case 'time':
				$value = sprintf( "%02d:%02d %s", $value[0], $value[1], $value[2] );
				break;
			case 'date':
				$value = self::prepare_date( $field->dateFormat, $value );
				break;
			case 'number':
				$value = GFCommon::clean_number( $value, $field->numberFormat );
				break;
			case 'phone':
				$value          = str_replace( array( ')', '(', '-', ' ' ), '', $value );
				$sql_comparison = 'replace( replace( replace( replace( ld.value, ")", "" ), "(", "" ), "-", "" ), " ", "" ) = %s';
				break;
			case 'email':
				$value = is_array( $value ) ? rgar( $value, 0 ) : $value;
				break;
		}

		$inner_sql_template = "SELECT %s as input, ld.lead_id
                                FROM {$lead_detail_table_name} ld
                                INNER JOIN {$lead_table_name} l ON l.id = ld.lead_id\n";


		$inner_sql_template .= "WHERE l.form_id=%d AND ld.form_id=%d
                                AND ld.meta_key = %s
                                AND status='active' AND ld.value = %s";

		$sql = "SELECT count(distinct input) as match_count FROM ( ";

		$input_count = 1;
		if ( is_array( $field->get_entry_inputs() ) ) {
			$input_count = sizeof( $field->inputs );
			$inner_sql   = '';
			foreach ( $field->inputs as $input ) {
				$union = empty( $inner_sql ) ? '' : ' UNION ALL ';
				$inner_sql .= $union . $wpdb->prepare( $inner_sql_template, $input['id'], $form_id, $form_id, $input['id'] - 0.0001, $input['id'] + 0.0001, $value[ $input['id'] ] );
			}
		} else {
			$inner_sql = $wpdb->prepare( $inner_sql_template, $field->id, $form_id, $form_id, doubleval( $field->id ) - 0.0001, doubleval( $field->id ) + 0.0001, $value );
		}

		$sql .= $inner_sql . "
                ) as count
                GROUP BY lead_id
                ORDER BY match_count DESC";

		$count = gf_apply_filters( array( 'gform_is_duplicate', $form_id ), $wpdb->get_var( $sql ), $form_id, $field, $value );

		return $count != null && $count >= $input_count;
	}

	public static function get_lead_notes( $lead_id ) {
		global $wpdb;
		$notes_table = self::get_lead_notes_table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"  SELECT n.id, n.user_id, n.date_created, n.value, n.note_type, ifnull(u.display_name,n.user_name) as user_name, u.user_email
                                                    FROM $notes_table n
                                                    LEFT OUTER JOIN $wpdb->users u ON n.user_id = u.id
                                                    WHERE lead_id=%d ORDER BY id", $lead_id
			)
		);
	}

	public static function get_leads_by_meta( $meta_key, $meta_value ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"   SELECT l.*, d.field_number, d.value
					FROM {$wpdb->prefix}rg_lead l
					INNER JOIN {$wpdb->prefix}rg_lead_detail d ON l.id = d.lead_id
					INNER JOIN {$wpdb->prefix}rg_lead_meta m ON l.id = m.lead_id
					WHERE m.meta_key=%s AND m.meta_value=%s", $meta_key, $meta_value
		);

		//getting results
		$results = $wpdb->get_results( $sql );
		$leads   = self::build_lead_array( $results );

		return $leads;
	}

	public static function get_leads( $form_id, $sort_field_number = 0, $sort_direction = 'DESC', $search = '', $offset = 0, $page_size = 30, $star = null, $read = null, $is_numeric_sort = false, $start_date = null, $end_date = null, $status = 'active', $payment_status = false ) {
		global $wpdb;

		if ( empty( $sort_field_number ) ) {
			$sort_field_number = 'date_created';
		}

		if ( is_numeric( $sort_field_number ) ) {
			$sql = self::sort_by_custom_field_query( $form_id, $sort_field_number, $sort_direction, $search, $offset, $page_size, $star, $read, $is_numeric_sort, $status, $payment_status );
		} else {
			$sql = self::sort_by_default_field_query( $form_id, $sort_field_number, $sort_direction, $search, $offset, $page_size, $star, $read, $is_numeric_sort, $start_date, $end_date, $status, $payment_status );
		}

		//initializing rownum
		$wpdb->query( 'select @rownum:=0' );

		//getting results
		$results = $wpdb->get_results( $sql );

		$leads = self::build_lead_array( $results );

		return $leads;
	}

	private static function sort_by_custom_field_query( $form_id, $sort_field_number = 0, $sort_direction = 'DESC', $search = '', $offset = 0, $page_size = 30, $star = null, $read = null, $is_numeric_sort = false, $status = 'active', $payment_status = false ) {
		if ( ! is_numeric( $form_id ) || ! is_numeric( $sort_field_number ) || ! is_numeric( $offset ) || ! is_numeric( $page_size ) ) {
			return '';
		}

		$lead_detail_table_name = self::get_lead_details_table_name();
		$lead_table_name        = self::get_lead_table_name();

		$sort_direction = in_array( strtolower( $sort_direction ), array( 'desc', 'asc', 'rand' ) ) ? strtoupper( $sort_direction ) : 'ASC';

		$orderby    = $is_numeric_sort ? "ORDER BY query, (value+0) $sort_direction" : "ORDER BY query, value $sort_direction";
		$is_default = false;

		$search_sql = self::get_leads_where_sql( compact( 'form_id', 'search', 'status', 'star', 'read', 'start_date', 'end_date', 'payment_status', 'is_default' ) );

		$sql = "
            SELECT filtered.sort, l.*, d.field_number, d.value
            FROM $lead_table_name l
            INNER JOIN $lead_detail_table_name d ON d.lead_id = l.id
            INNER JOIN (
                SELECT distinct sorted.sort, l.id
                FROM $lead_table_name l
                INNER JOIN $lead_detail_table_name d ON d.lead_id = l.id
                INNER JOIN (
                    SELECT @rownum:=@rownum+1 as sort, id FROM (
                        SELECT 0 as query, lead_id as id, value
                        FROM $lead_detail_table_name
                        WHERE form_id=$form_id
                        AND meta_key = $sort_field_number

                        UNION ALL

                        SELECT 1 as query, l.id, d.value
                        FROM $lead_table_name l
                        LEFT OUTER JOIN $lead_detail_table_name d ON d.lead_id = l.id AND meta_key = $sort_field_number
                        WHERE l.form_id=$form_id
                        AND d.lead_id IS NULL

                    ) sorted1
                   $orderby
                ) sorted ON d.lead_id = sorted.id
                $search_sql
                LIMIT $offset,$page_size
            ) filtered ON filtered.id = l.id
            ORDER BY filtered.sort";

		return $sql;
	}

	private static function sort_by_default_field_query( $form_id, $sort_field, $sort_direction = 'DESC', $search = '', $offset = 0, $page_size = 30, $star = null, $read = null, $is_numeric_sort = false, $start_date = null, $end_date = null, $status = 'active', $payment_status = false ) {
		global $wpdb;

		if ( ! is_numeric( $form_id ) || ! is_numeric( $offset ) || ! is_numeric( $page_size ) ) {
			return '';
		}

		$lead_table_name        = self::get_lead_table_name();
		$lead_detail_table_name   = self::get_lead_details_table_name();

		$where = self::get_leads_where_sql( compact( 'form_id', 'search', 'status', 'star', 'read', 'start_date', 'end_date', 'payment_status' ) );

		$entry_meta          = self::get_entry_meta( $form_id );
		$entry_meta_sql_join = '';
		if ( false === empty( $entry_meta ) && array_key_exists( $sort_field, $entry_meta ) ) {
			$entry_meta_sql_join = $wpdb->prepare(
				"INNER JOIN
				(
				SELECT
					 lead_id, value as $sort_field
					 from $lead_detail_table_name
					 WHERE meta_key = %s
				) lead_meta_data ON lead_meta_data.lead_id = l.id
				", $sort_field
			);
			$is_numeric_sort     = $entry_meta[ $sort_field ]['is_numeric'];
		}
		$grid_columns = RGFormsModel::get_grid_columns( $form_id );
		if ( $sort_field != 'date_created' && false === array_key_exists( $sort_field, $grid_columns ) ) {
			$sort_field = 'date_created';
		}
		$orderby = $is_numeric_sort ? "ORDER BY ($sort_field+0) $sort_direction" : "ORDER BY $sort_field $sort_direction";

		$sql = "
            SELECT filtered.sort, l.*, d.field_number, d.value
            FROM $lead_table_name l
            INNER JOIN $lead_detail_table_name d ON d.lead_id = l.id
            INNER JOIN
            (
                SELECT @rownum:=@rownum + 1 as sort, id
                FROM
                (
                    SELECT distinct l.id
                    FROM $lead_table_name l
                    INNER JOIN $lead_detail_table_name d ON d.lead_id = l.id
					$entry_meta_sql_join
                    $where
                    $orderby
                    LIMIT $offset,$page_size
                ) page
            ) filtered ON filtered.id = l.id
            ORDER BY filtered.sort";

		return $sql;
	}

	public static function get_leads_where_sql( $args ) {
		global $wpdb;

		extract(
			wp_parse_args(
				$args, array(
					'form_id'        => false,
					'search'         => '',
					'status'         => 'active',
					'star'           => null,
					'read'           => null,
					'start_date'     => null,
					'end_date'       => null,
					'payment_status' => null,
					'is_default'     => true,
				)
			)
		);

		$where = array();

		if ( $is_default ) {
			$where[] = "l.form_id = $form_id";
		}

		if ( $search && $is_default ) {
			$where[] = $wpdb->prepare( 'value LIKE %s', "%$search%" );
		} else if ( $search ) {
			$where[] = $wpdb->prepare( 'd.value LIKE %s', "%$search%" );
		}

		if ( $star !== null && $status == 'active' ) {
			$where[] = $wpdb->prepare( "is_starred = %d AND status = 'active'", $star );
		}

		if ( $read !== null && $status == 'active' ) {
			$where[] = $wpdb->prepare( "is_read = %d AND status = 'active'", $read );
		}

		if ( $payment_status ) {
			$where[] = $wpdb->prepare( "payment_status = '%s'", $payment_status );
		}

		if ( $status !== null ) {
			$where[] = $wpdb->prepare( 'status = %s', $status );
		}

		if ( ! empty( $start_date ) ) {
			$where[] = "timestampdiff(SECOND, '$start_date', date_created) >= 0";
		}

		if ( ! empty( $end_date ) ) {
			$where[] = "timestampdiff(SECOND, '$end_date', date_created) <= 0";
		}

		return 'WHERE ' . implode( ' AND ', $where );
	}

	public static function get_lead_count( $form_id, $search, $star = null, $read = null, $start_date = null, $end_date = null, $status = null, $payment_status = null ) {
		global $wpdb;

		if ( ! is_numeric( $form_id ) ) {
			return '';
		}

		$lead_detail_table_name = self::get_lead_details_table_name();
		$lead_table_name   = self::get_lead_table_name();

		$where = self::get_leads_where_sql( compact( 'form_id', 'search', 'status', 'star', 'read', 'start_date', 'end_date', 'payment_status', 'is_default' ) );

		$sql = "SELECT count(distinct l.id)
                FROM $lead_table_name l
                INNER JOIN $lead_detail_table_name ld ON l.id = ld.lead_id
                $where";

		return $wpdb->get_var( $sql );
	}

	public static function get_lead_ids( $form_id, $search, $star = null, $read = null, $start_date = null, $end_date = null, $status = null, $payment_status = null ) {
		global $wpdb;

		if ( ! is_numeric( $form_id ) ) {
			return '';
		}

		$detail_table_name = self::get_lead_details_table_name();
		$lead_table_name   = self::get_lead_table_name();

		$where = self::get_leads_where_sql( compact( 'form_id', 'search', 'status', 'star', 'read', 'start_date', 'end_date', 'payment_status', 'is_default' ) );

		$sql = "SELECT distinct l.id
                FROM $lead_table_name l
                INNER JOIN $detail_table_name ld ON l.id = ld.lead_id
                $where";

		$rows = $wpdb->get_results( $sql );

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$lead_ids[] = $row->id;
		}

		return $lead_ids;

	}

	public static function get_submitted_fields( $form_id ) {
		global $wpdb;
		$lead_detail_table_name = self::get_lead_details_table_name();
		$field_list             = '';
		$fields                 = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT field_number FROM $lead_detail_table_name WHERE form_id=%d", $form_id ) );
		foreach ( $fields as $field ) {
			$field_list .= intval( $field->field_number ) . ',';
		}

		if ( ! empty( $field_list ) ) {
			$field_list = substr( $field_list, 0, strlen( $field_list ) - 1 );
		}

		return $field_list;
	}

	public static function search_leads( $form_id, $search_criteria = array(), $sorting = null, $paging = null ) {

		global $wpdb;
		$sort_field = isset( $sorting['key'] ) ? $sorting['key'] : 'date_created'; // column, field or entry meta

		if ( is_numeric( $sort_field ) ) {
			$sql = self::sort_by_field_query( $form_id, $search_criteria, $sorting, $paging );
		} else {
			$sql = self::sort_by_column_query( $form_id, $search_criteria, $sorting, $paging );
		}

		//initializing rownum
		$wpdb->query( 'SELECT @rownum:=0' );

		GFCommon::log_debug( $sql );

		//getting results
		$results = $wpdb->get_results( $sql );

		$leads = self::build_lead_array( $results );

		return $leads;
	}

	public static function search_lead_ids( $form_id, $search_criteria = array() ) {
		global $wpdb;

		$detail_table_name = GFFormsModel::get_lead_details_table_name();
		$lead_table_name   = GFFormsModel::get_lead_table_name();

		$where = self::get_search_where( $form_id, $search_criteria );

		$sql = "SELECT distinct l.id
                FROM $lead_table_name l
                INNER JOIN $detail_table_name ld ON l.id = ld.lead_id
                $where
                ";

		$rows = $wpdb->get_results( $sql );

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$lead_ids[] = $row->id;
		}

		return $lead_ids;
	}

	public static function build_lead_array( $results ) {

		$leads   = array();
		$lead    = array();
		$form_id = 0;
		if ( is_array( $results ) && sizeof( $results ) > 0 ) {
			$form_id = $results[0]->form_id;
			$lead    = array( 'id' => $results[0]->id, 'form_id' => $results[0]->form_id, 'date_created' => $results[0]->date_created, 'is_starred' => intval( $results[0]->is_starred ), 'is_read' => intval( $results[0]->is_read ), 'ip' => $results[0]->ip, 'source_url' => $results[0]->source_url, 'post_id' => $results[0]->post_id, 'currency' => $results[0]->currency, 'payment_status' => $results[0]->payment_status, 'payment_date' => $results[0]->payment_date, 'transaction_id' => $results[0]->transaction_id, 'payment_amount' => $results[0]->payment_amount, 'payment_method' => $results[0]->payment_method, 'is_fulfilled' => $results[0]->is_fulfilled, 'created_by' => $results[0]->created_by, 'transaction_type' => $results[0]->transaction_type, 'user_agent' => $results[0]->user_agent, 'status' => $results[0]->status );

			$form         = RGFormsModel::get_form_meta( $form_id );
			$prev_lead_id = 0;
			foreach ( $results as $result ) {
				if ( $prev_lead_id <> $result->id && $prev_lead_id > 0 ) {
					array_push( $leads, $lead );
					$lead = array( 'id' => $result->id, 'form_id' => $result->form_id, 'date_created' => $result->date_created, 'is_starred' => intval( $result->is_starred ), 'is_read' => intval( $result->is_read ), 'ip' => $result->ip, 'source_url' => $result->source_url, 'post_id' => $result->post_id, 'currency' => $result->currency, 'payment_status' => $result->payment_status, 'payment_date' => $result->payment_date, 'transaction_id' => $result->transaction_id, 'payment_amount' => $result->payment_amount, 'payment_method' => $result->payment_method, 'is_fulfilled' => $result->is_fulfilled, 'created_by' => $result->created_by, 'transaction_type' => $result->transaction_type, 'user_agent' => $result->user_agent, 'status' => $result->status );
				}

				$field_value           = $result->value;
				$field_number          = (string) $result->field_number;
				$lead[ $field_number ] = $field_value;
				$prev_lead_id          = $result->id;
			}
		}


		//adding last lead.
		if ( sizeof( $lead ) > 0 ) {
			array_push( $leads, $lead );
		}

		//running entry through gform_get_field_value filter
		foreach ( $leads as &$lead ) {
			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				$inputs = $field->get_entry_inputs();
				// skip types html, page and section?
				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {
						$lead[ (string) $input['id'] ] = gf_apply_filters( array( 'gform_get_input_value', $form['id'], $field->id, $input['id'] ), rgar( $lead, (string) $input['id'] ), $lead, $field, $input['id'] );
					}
				} else {

					$value = rgar( $lead, (string) $field->id );

					if ( GFFormsModel::is_openssl_encrypted_field( $lead['id'], $field->id ) ) {
						$value = GFCommon::openssl_decrypt( $value );
					}

					$lead[ $field->id ] = gf_apply_filters( array( 'gform_get_input_value', $form['id'], $field->id ), $value, $lead, $field, '' );

				}
			}
		}

		//add custom entry properties
		$entry_ids = array();
		foreach ( $leads as $l ) {
			$entry_ids[] = $l['id'];
		}
		$entry_meta           = GFFormsModel::get_entry_meta( $form_id );
		$meta_keys            = array_keys( $entry_meta );
		$entry_meta_data_rows = gform_get_meta_values_for_entries( $entry_ids, $meta_keys );
		foreach ( $leads as &$lead ) {
			foreach ( $entry_meta_data_rows as $entry_meta_data_row ) {
				if ( $entry_meta_data_row->lead_id == $lead['id'] ) {
					foreach ( $meta_keys as $meta_key ) {
						$lead[ $meta_key ] = $entry_meta_data_row->$meta_key;
					}
				}
			}
		}

		return $leads;
	}

	private static function get_form_id_where( $form_id ) {
		global $wpdb;

		if ( is_array( $form_id ) ) {
			$in_str_arr    = array_fill( 0, count( $form_id ), '%d' );
			$in_str        = join( ',', $in_str_arr );
			$form_id_where = $wpdb->prepare( "l.form_id IN ($in_str)", $form_id );
		} else {
			$form_id_where = $form_id > 0 ? $wpdb->prepare( 'l.form_id=%d', $form_id ) : '';
		}

		return $form_id_where;
	}

	private static function sort_by_field_query( $form_id, $search_criteria, $sorting, $paging ) {
		global $wpdb;
		$sort_field_number = rgar( $sorting, 'key' );
		$sort_direction    = isset( $sorting['direction'] ) ? $sorting['direction'] : 'DESC';

		$is_numeric_sort = isset( $sorting['is_numeric'] ) ? $sorting['is_numeric'] : false;
		$offset          = isset( $paging['offset'] ) ? $paging['offset'] : 0;
		$page_size       = isset( $paging['page_size'] ) ? $paging['page_size'] : 20;

		if ( ! is_numeric( $sort_field_number ) || ! is_numeric( $offset ) || ! is_numeric( $page_size ) ) {
			return '';
		}

		$lead_detail_table_name = GFFormsModel::get_lead_details_table_name();
		$lead_table_name        = GFFormsModel::get_lead_table_name();

		$sort_direction = in_array( strtolower( $sort_direction ), array( 'desc', 'asc', 'rand' ) ) ? strtoupper( $sort_direction ) : 'ASC';

		$orderby = $is_numeric_sort ? "ORDER BY query, (value+0) $sort_direction" : "ORDER BY query, value $sort_direction";

		$form_id_where = self::get_form_id_where( $form_id );

		if ( ! empty( $form_id_where ) ) {
			$form_id_where = ' AND ' . $form_id_where;
		}

		$where = self::get_search_where( $form_id, $search_criteria );

		$field_number_min = $sort_field_number - 0.0001;
		$field_number_max = $sort_field_number + 0.0001;

		$sql = "
            SELECT filtered.sort, l.*, d.field_number, d.value
            FROM $lead_table_name l
            INNER JOIN $lead_detail_table_name d ON d.lead_id = l.id
            INNER JOIN (
                SELECT distinct sorted.sort, l.id
                FROM $lead_table_name l
                INNER JOIN $lead_detail_table_name d ON d.lead_id = l.id
                INNER JOIN (
                    SELECT @rownum:=@rownum+1 as sort, id FROM (
                        SELECT 0 as query, lead_id as id, value
                        FROM $lead_detail_table_name l
                        WHERE field_number between $field_number_min AND $field_number_max
                        $form_id_where

                        UNION ALL

                        SELECT 1 as query, l.id, d.value
                        FROM $lead_table_name l
                        LEFT OUTER JOIN $lead_detail_table_name d ON d.lead_id = l.id AND field_number between $field_number_min AND $field_number_max
                        WHERE d.lead_id IS NULL
                        $form_id_where

                    ) sorted1
                   $orderby
                ) sorted ON d.lead_id = sorted.id
                $where
                ORDER BY sorted.sort
                LIMIT $offset,$page_size
            ) filtered ON filtered.id = l.id

            ORDER BY filtered.sort";

		return $sql;
	}

	private static function sort_by_column_query( $form_id, $search_criteria, $sorting, $paging ) {
		global $wpdb;
		$sort_field      = isset( $sorting['key'] ) ? $sorting['key'] : 'date_created';
		$sort_direction  = isset( $sorting['direction'] ) ? $sorting['direction'] : 'DESC';
		$is_numeric_sort = isset( $sorting['is_numeric'] ) ? $sorting['is_numeric'] : false;
		$offset          = isset( $paging['offset'] ) ? $paging['offset'] : 0;
		$page_size       = isset( $paging['page_size'] ) ? $paging['page_size'] : 20;

		if ( ! is_numeric( $offset ) || ! is_numeric( $page_size ) ) {
			return '';
		}

		$lead_detail_table_name = GFFormsModel::get_lead_details_table_name();
		$lead_table_name        = GFFormsModel::get_lead_table_name();
		$lead_meta_table_name   = GFFormsModel::get_lead_meta_table_name();

		$entry_meta               = self::get_entry_meta( is_array( $form_id ) ? 0 : $form_id );
		$entry_meta_sql_join      = '';
		$sort_field_is_entry_meta = false;
		if ( false === empty( $entry_meta ) && array_key_exists( $sort_field, $entry_meta ) ) {
			$entry_meta_sql_join      = $wpdb->prepare(
				"
                LEFT JOIN
                (
                SELECT
                     lead_id, meta_value as $sort_field
                     from $lead_meta_table_name
                     WHERE meta_key=%s
                ) lead_meta_data ON lead_meta_data.lead_id = l.id
                ", $sort_field
			);
			$is_numeric_sort          = $entry_meta[ $sort_field ]['is_numeric'];
			$sort_field_is_entry_meta = true;
		} else {
			$db_columns = self::get_lead_db_columns();
			if ( $sort_field != 'date_created' && false === in_array( $sort_field, $db_columns ) ) {
				$sort_field = 'date_created';
			}
		}

		if ( $sort_field_is_entry_meta ) {
			$orderby = $is_numeric_sort ? "ORDER BY ($sort_field+0) $sort_direction" : "ORDER BY $sort_field $sort_direction";
		} else {
			$orderby = $is_numeric_sort ? "ORDER BY (l.$sort_field+0) $sort_direction" : "ORDER BY l.$sort_field $sort_direction";
		}

		$where = self::get_search_where( $form_id, $search_criteria );

		$sql = "
            SELECT filtered.sort, l.*, d.field_number, d.value
            FROM $lead_table_name l
            INNER JOIN $lead_detail_table_name d ON d.lead_id = l.id
            INNER JOIN
            (
                SELECT @rownum:=@rownum + 1 as sort, id
                FROM
                (
                    SELECT distinct l.id
                    FROM $lead_table_name l
                    INNER JOIN $lead_detail_table_name d ON d.lead_id = l.id
                    $entry_meta_sql_join
                    $where
                    $orderby
                    LIMIT $offset,$page_size
                ) page
            ) filtered ON filtered.id = l.id

            ORDER BY filtered.sort";

		return $sql;
	}

	private static function get_search_where( $form_id, $search_criteria ) {

		global $wpdb;

		$where_arr = array();

		$field_filters_where = self::get_field_filters_where( $form_id, $search_criteria );
		if ( ! empty( $field_filters_where ) ) {
			$where_arr[] = $field_filters_where;
		}

		$info_search_where = self::get_info_search_where( $search_criteria );

		if ( ! empty( $info_search_where ) ) {
			$where_arr[] = $info_search_where;
		}

		$search_operator = self::get_search_operator( $search_criteria );
		$where           = empty( $where_arr ) ? '' : '(' . join( " $search_operator ", $where_arr ) . ')';

		$date_range_where = self::get_date_range_where( $search_criteria );

		$where_and_clause_arr = array();
		if ( ! empty( $date_range_where ) ) {
			$where_and_clause_arr[] = $date_range_where;
		}

		$form_id_where = self::get_form_id_where( $form_id );

		if ( ! empty( $form_id_where ) ) {
			$where_and_clause_arr[] = $form_id_where;
		}

		$status_where = isset( $search_criteria['status'] ) ? $wpdb->prepare( 'l.status = %s', $search_criteria['status'] ) : '';
		if ( ! empty( $status_where ) ) {
			$where_and_clause_arr[] = $status_where;
		}

		$where_and_clause = join( ' AND ', $where_and_clause_arr );

		if ( ! empty( $where_and_clause ) ) {
			$where_and_clause = '(' . $where_and_clause . ')';
		}

		$where_parts = array();
		if ( ! empty( $where ) ) {
			$where_parts[] = $where;
		}
		if ( ! empty( $where_and_clause ) ) {
			$where_parts[] = $where_and_clause;
		}

		$where = join( ' AND ', $where_parts );

		if ( ! empty( $where ) ) {
			$where = 'WHERE ' . $where;
		}

		return $where;
	}

	public static function get_lead_db_columns() {
		return array( 'id', 'form_id', 'post_id', 'date_created', 'is_starred', 'is_read', 'ip', 'source_url', 'user_agent', 'currency', 'payment_status', 'payment_date', 'payment_amount', 'transaction_id', 'is_fulfilled', 'created_by', 'transaction_type', 'status', 'payment_method' );
	}

	private static function get_field_filters_where( $form_id, $search_criteria ) {
		global $wpdb;

		$field_filters = rgar( $search_criteria, 'field_filters' );

		$search_operator = self::get_search_operator( $search_criteria );

		if ( empty( $field_filters ) ) {
			return false;
		}

		unset( $field_filters['mode'] );

		$sql_array               = array();
		$lead_details_table_name = GFFormsModel::get_lead_details_table_name();
		$lead_meta_table_name    = GFFormsModel::get_lead_meta_table_name();
		if ( is_array( $form_id ) ) {
			$in_str_arr    = array_fill( 0, count( $form_id ), '%d' );
			$in_str        = join( ',', $in_str_arr );
			$form_id_where = $wpdb->prepare( "AND form_id IN ($in_str)", $form_id );
		} else {
			$form_id_where = $form_id > 0 ? $wpdb->prepare( 'AND form_id=%d', $form_id ) : '';
		}
		$info_column_keys = self::get_lead_db_columns();
		$entry_meta       = self::get_entry_meta( is_array( $form_id ) ? 0 : $form_id );
		array_push( $info_column_keys, 'id' );
		foreach ( $field_filters as $search ) {

			$key = rgar( $search, 'key' );
			if ( 'entry_id' === $key ) {
				$key = 'id';
			}

			if ( in_array( $key, $info_column_keys ) ) {
				continue;
			}

			$val = rgar( $search, 'value' );

			$operator = self::is_valid_operator( rgar( $search, 'operator' ) ) ? strtolower( $search['operator'] ) : '=';

			if ( 'is' == $operator ) {
				$operator = '=';
			}
			if ( 'isnot' == $operator ) {
				$operator = '<>';
			}
			if ( 'contains' == $operator ) {
				$operator = 'like';
			}

			$search_term = 'like' == $operator ? "%$val%" : $val;

			$search_type = rgar( $search, 'type' );
			if ( empty( $search_type ) ) {
				if ( empty( $key ) ) {
					$search_type = 'global';
				} elseif ( is_numeric( $key ) ) {
					$search_type = 'field';
				} else {
					$search_type = 'meta';
				}
			}

			switch ( $search_type ) {
				case 'field':
					$is_number_field = false;
					if ( $operator != 'like' && ! is_array( $form_id ) && $form_id > 0 ) {
						$form               = GFAPI::get_form( $form_id );
						$field              = GFFormsModel::get_field( $form, $key );
						if (  GFFormsModel::get_input_type( $field ) == 'number' ){
							$is_number_field = true;
						}
					}



					$upper_field_number_limit = (string) (int) $key === (string) $key ? (float) $key + 0.9999 : (float) $key + 0.0001;

					if ( is_array( $search_term ) ) {
						if ( in_array( $operator, array( '=', 'in' ) ) ) {
							$operator = 'IN'; // Override operator
						} elseif ( in_array( $operator, array( '!=', '<>', 'not in' ) ) ) {
							$operator = 'NOT IN'; // Override operator
						}
						// Format in SQL and sanitize the strings in the list
						$search_terms = array_fill( 0, count( $search_term ), '%s' );
						$search_terms_in = $wpdb->prepare( '( ' . implode( ', ', $search_terms ) . ' )', $search_term );

						/* doesn't support "<>" for checkboxes */
						$field_query = $wpdb->prepare(
							"
                        l.id IN
                        (
                        SELECT
                        lead_id
                        from {$lead_details_table_name}
                        WHERE (field_number BETWEEN %s AND %s AND value {$operator} {$search_terms_in})
                        {$form_id_where}
                        )", (float) $key - 0.0001, $upper_field_number_limit );
					} else {
						$search_term_placeholder = rgar( $search, 'is_numeric' ) || $is_number_field ? '%f' : '%s';
						/* doesn't support "<>" for checkboxes */
						$field_query = $wpdb->prepare(
							"
                        l.id IN
                        (
                        SELECT
                        lead_id
                        from {$lead_details_table_name}
                        WHERE (field_number BETWEEN %s AND %s AND value {$operator} {$search_term_placeholder})
                        {$form_id_where}
                        )", (float) $key - 0.0001, $upper_field_number_limit, $search_term
						);
					}

					if ( ( empty( $val ) && $operator != '<>' ) || $val === '%%' || ( $operator === '<>' && ! empty( $val ) ) ) {
						$skipped_field_query = $wpdb->prepare(
							"
                            l.id NOT IN
                            (
                            SELECT
                            lead_id
                            from {$lead_details_table_name}
                            WHERE (field_number BETWEEN %s AND %s)
                            {$form_id_where}
                            )", (float) $key - 0.0001, $upper_field_number_limit
						);
						$field_query = '(' . $field_query . ' OR ' . $skipped_field_query . ')';
					}

					$sql_array[] = $field_query;

					/*
                    //supports '<>' for checkboxes but it doesn't scale
                    $sql_array[] = $wpdb->prepare("l.id IN
                                    (SELECT lead_id
                                    FROM
                                        (
                                            SELECT lead_id, value
                                            FROM $lead_details_table_name
                                            WHERE form_id = %d
                                            AND (field_number BETWEEN %s AND %s)
                                            GROUP BY lead_id
                                            HAVING value $operator %s
                                        ) ld
                                    )
                                    ", $form_id, (float)$key - 0.0001, $upper_field_number_limit, $val );
                    */
					break;
				case 'global':

					// include choice text
					$forms = array();
					if ( $form_id == 0 ) {
						$forms = GFAPI::get_forms();
					} elseif ( is_array( $form_id ) ) {
						foreach ( $form_id as $id ){
							$forms[] = GFAPI::get_form( $id );
						}
					} else {
						$forms[] = GFAPI::get_form( $form_id );
					}

					$choice_texts_clauses = array();
					foreach ( $forms as $form ) {
						if ( isset( $form['fields'] ) ) {
							$choice_texts_clauses_for_form = array();
							foreach ( $form['fields'] as $field ) {
								/* @var GF_Field $field */
								$choice_texts_clauses_for_field = array();
								if ( is_array( $field->choices ) ) {
									foreach ( $field->choices as $choice ) {
										if ( ( $operator == '=' && strtolower( $choice['text'] ) == strtolower( $val ) ) || ( $operator == 'like' && ! empty( $val ) && strpos( strtolower( $choice['text'] ), strtolower( $val ) ) !== false ) ) {
											if ( $field->gsurveyLikertEnableMultipleRows ){
												$choice_value = '%' . $choice['value'] . '%' ;
												$choice_search_operator = 'like';
											} else {
												$choice_value = $choice['value'];
												$choice_search_operator = '=';
											}
											$choice_texts_clauses_for_field[] = $wpdb->prepare( "(field_number BETWEEN %s AND %s AND value {$choice_search_operator} %s)", (float) $field->id - 0.0001, (float) $field->id + 0.9999, $choice_value );
										}
									}
								}
								if ( ! empty( $choice_texts_clauses_for_field ) ) {
									$choice_texts_clauses_for_form[] = join( ' OR ', $choice_texts_clauses_for_field );
								}
							}
						}
						if ( ! empty( $choice_texts_clauses_for_form ) ) {
							$choice_texts_clauses[] = '(l.form_id = ' . $form['id'] . ' AND (' . join( ' OR ', $choice_texts_clauses_for_form ) . ' ))';
						}
					}
					$choice_texts_clause = '';
					if ( ! empty( $choice_texts_clauses) ){
						$choice_texts_clause = join( ' OR ', $choice_texts_clauses );
						$choice_texts_clause = "
						l.id IN (
                        SELECT
                        lead_id
                        FROM {$lead_details_table_name}
                        WHERE {$choice_texts_clause} ) OR ";
					}
					$choice_value_clause = $wpdb->prepare( "value {$operator} %s", $search_term );
					$sql_array[] = '(' . $choice_texts_clause . $choice_value_clause . ')';
					break;
				case 'meta':
					/* doesn't support '<>' for multiple values of the same key */

					if ( is_array( $search_term ) ) {
						if ( in_array( $operator, array( '=', 'in' ) ) ) {
							$operator = 'IN';
						} elseif ( in_array( $operator, array( '!=', '<>', 'not in' ) ) ) {
							$operator = 'NOT IN';
						}
						$search_terms = array_fill( 0, count( $search_term ), '%s' );
						$search_terms_in = $wpdb->prepare( '( ' . implode( ', ', $search_terms ) . ' )', $search_term );

						$sql_array[] = $wpdb->prepare(
							"
                        l.id IN
                        (
                        SELECT
                        lead_id
                        FROM $lead_meta_table_name
                        WHERE meta_key=%s AND meta_value $operator $search_terms_in
                        $form_id_where
                        )", $search['key'] );
					} else {
						$meta = rgar( $entry_meta, $key );
						$placeholder = rgar( $meta, 'is_numeric' ) ? '%s' : '%s';
						$search_term = 'like' == $operator ? "%$val%" : $val;
						$sql_array[] = $wpdb->prepare(
							"
                        l.id IN
                        (
                        SELECT
                        lead_id
                        FROM $lead_meta_table_name
                        WHERE meta_key=%s AND meta_value $operator $placeholder
                        $form_id_where
                        )", $search['key'], $search_term
						);
					}

					break;
			}
		}

		$sql = empty( $sql_array ) ? '' : join( ' ' . $search_operator . ' ', $sql_array );

		return $sql;
	}

	/**
	 * Checks whether the conditional logic operator passed in is valid.
	 *
	 * @since  2.0.7.20 Refactored and added filter gform_is_valid_conditional_logic_operator.
	 * @access public
	 *
	 * @param string $operator Conditional logic operator.
	 *
	 * @return bool true if a valid operator, false if not.
	 */
	public static function is_valid_operator( $operator ) {
		$operators = array( 'is', 'isnot', '<>', 'not in', 'in', '>', '<', 'contains', 'starts_with', 'ends_with', 'like', '>=', '<=' );
		$is_valid = in_array( strtolower( $operator ), $operators );
		/**
		 * Filter which checks whether the operator is valid.
		 *
		 * Allows custom operators to be validated.
		 *
		 * @since 2.0.7.20
		 *
		 * @param bool   $is_valid Whether the operator is valid or not.
		 * @param string $operator The conditional logic operator.
		 */
		return apply_filters( 'gform_is_valid_conditional_logic_operator', $is_valid, $operator );
	}

	public static function get_entry_meta( $form_ids ) {
		global $_entry_meta;

		if ( $form_ids == 0 ) {
			$form_ids = GFFormsModel::get_form_ids();
		}

		if ( ! is_array( $form_ids ) ) {
			$form_ids = array( $form_ids );
		}
		$meta = array();
		foreach ( $form_ids as $form_id ) {
			if ( ! isset( $_entry_meta[ $form_id ] ) ) {
				$_entry_meta           = array();
				$_entry_meta[ $form_id ] = apply_filters( 'gform_entry_meta', array(), $form_id );
			}
			$meta = array_merge( $meta, $_entry_meta[ $form_id ] );
		}

		return $meta;
	}

	private static function get_date_range_where( $search_criteria ) {
		global $wpdb;

		if ( isset( $search_criteria['start_date'] ) ) {
			$start_date           = new DateTime( $search_criteria['start_date'] );
			$start_datetime_str = $start_date->format( 'Y-m-d H:i:s' );
			$start_date_str       = $start_date->format( 'Y-m-d' );
			if ( $start_datetime_str == $start_date_str  . ' 00:00:00' ) {
				$start_date_str = $start_date_str . ' 00:00:00';
			} else {
				$start_date_str = $start_date->format( 'Y-m-d H:i:s' );
			}

			$start_date_str_utc = get_gmt_from_date( $start_date_str );
			$where_array[] = $wpdb->prepare( 'date_created >= %s', $start_date_str_utc );
		}

		if ( isset( $search_criteria['end_date'] ) ) {

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

			$where_array[] = $wpdb->prepare( 'date_created <= %s', $end_date_str_utc );
		}


		$sql = empty( $where_array ) ? '' : '(' . join( ' AND ', $where_array ) . ')';

		return $sql;
	}

	private static function get_search_operator( $search_criteria ) {
		if ( ! isset( $search_criteria['field_filters'] ) ) {
			return '';
		}
		$field_filters = $search_criteria['field_filters'];

		$search_mode = isset( $field_filters['mode'] ) ? strtolower( $field_filters['mode'] ) : 'all';

		return strtolower( $search_mode ) == 'any' ? 'OR' : 'AND';
	}

	private static function get_info_search_where( $search_criteria ) {
		global $wpdb;

		$field_filters   = rgar( $search_criteria, 'field_filters' );
		$search_operator = self::get_search_operator( $search_criteria );

		if ( empty( $field_filters ) ) {
			return;
		}

		unset( $field_filters['mode'] );

		$info_column_keys = GFFormsModel::get_lead_db_columns();
		array_push( $info_column_keys, 'id' );
		$int_columns = array( 'id', 'post_id', 'is_starred', 'is_read', 'is_fulfilled', 'entry_id' );
		$where_array = array();
		foreach ( $field_filters as $filter ) {
			$key = strtolower( rgar( $filter, 'key' ) );

			if ( 'entry_id' === $key ) {
				$key = 'id';
			}

			if ( ! in_array( $key, $info_column_keys ) ) {
				continue;
			}

			$operator = GFFormsModel::is_valid_operator( rgar( $filter, 'operator' ) ) ? strtolower( $filter['operator'] ) : '=';

			$value = rgar( $filter, 'value' );

			if ( 'is' == $operator ) {
				$operator = '=';
			}
			if ( 'isnot' == $operator ) {
				$operator = '<>';
			}
			if ( 'contains' == $operator ) {
				$operator = 'like';
			}
			$search_term = 'like' == $operator ? "%$value%" : $value;
			if ( 'date_created' == $key && '=' === $operator ) {
				$search_date           = new DateTime( $search_term );
				$search_date_str       = $search_date->format( 'Y-m-d' );
				$date_created_start    = $search_date_str . ' 00:00:00';
				$date_create_start_utc = get_gmt_from_date( $date_created_start );
				$date_created_end      = $search_date_str . ' 23:59:59';
				$date_created_end_utc  = get_gmt_from_date( $date_created_end );
				$where_array[] = $wpdb->prepare( '(date_created >= %s AND date_created <= %s)', $date_create_start_utc, $date_created_end_utc );
			} else if ( in_array( $key, $int_columns ) ) {
				$where_array[] = $wpdb->prepare( "l.{$key} $operator %d", $search_term );
			} else {
				$where_array[] = $wpdb->prepare( "l.{$key} $operator %s", $search_term );
			}
		}


		$sql = empty( $where_array ) ? '' : join( " $search_operator ", $where_array );

		return $sql;
	}

	public static function count_search_leads( $form_id, $search_criteria = array() ) {
		global $wpdb;

		$lead_detail_table_name = self::get_lead_details_table_name();
		$lead_table_name   = GFFormsModel::get_lead_table_name();

		$where = self::get_search_where( $form_id, $search_criteria );

		$sql = "SELECT count(distinct l.id)
                FROM $lead_table_name l
                INNER JOIN $lead_detail_table_name ld ON l.id = ld.lead_id
                $where
                ";

		return (int) $wpdb->get_var( $sql );
	}

	public static function get_entry_meta_counts() {
		global $wpdb;

		$detail_table_name = self::get_lead_details_table_name();
		$meta_table_name = self::get_lead_meta_table_name();
		$notes_table_name = self::get_lead_notes_table_name();

		$results = $wpdb->get_results(
			"
            SELECT
            (SELECT count(0) FROM $detail_table_name) as details,
            (SELECT count(0) FROM $meta_table_name) as meta,
            (SELECT count(0) FROM $notes_table_name) as notes
            "
		);

		return array(
			'details' => intval( $results[0]->details ),
			'meta'    => intval( $results[0]->meta ),
			'notes'   => intval( $results[0]->notes ),
		);

	}

	//functions to handle lead meta

	public static function gform_get_meta( $entry_id, $meta_key ) {
		global $wpdb, $_gform_lead_meta;

		//get from cache if available
		$cache_key = get_current_blog_id() . '_' . $entry_id . '_' . $meta_key;
		if ( array_key_exists( $cache_key, $_gform_lead_meta ) ) {
			return maybe_unserialize( $_gform_lead_meta[ $cache_key ] );
		}

		$table_name                   = RGFormsModel::get_lead_meta_table_name();
		$results                      = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$table_name} WHERE lead_id=%d AND meta_key=%s", $entry_id, $meta_key ) );
		$value                        = isset( $results[0] ) ? $results[0]->meta_value : null;
		$meta_value                   = $value === null ? false : maybe_unserialize( $value );
		$_gform_lead_meta[ $cache_key ] = $meta_value;

		return $meta_value;
	}

	public static function gform_get_meta_values_for_entries( $entry_ids, $meta_keys ) {
		global $wpdb;

		if ( empty( $meta_keys ) || empty( $entry_ids ) ) {
			return array();
		}

		$table_name            = RGFormsModel::get_lead_meta_table_name();
		$meta_key_select_array = array();

		foreach ( $meta_keys as $meta_key ) {
			$meta_key_select_array[] = "max(case when meta_key = '$meta_key' then meta_value end) as $meta_key";
		}

		$entry_ids_str = join( ',', $entry_ids );

		$meta_key_select = join( ',', $meta_key_select_array );

		$sql_query = "  SELECT
                    lead_id, $meta_key_select
                    FROM $table_name
                    WHERE lead_id IN ($entry_ids_str)
                    GROUP BY lead_id";

		$results = $wpdb->get_results( $sql_query );

		foreach ( $results as $result ) {
			foreach ( $meta_keys as $meta_key ) {
				$result->$meta_key = $result->$meta_key === null ? false : maybe_unserialize( $result->$meta_key );
			}
		}

		$meta_value_array = $results;

		return $meta_value_array;
	}


	/**
	 * Add or update metadata associated with an entry
	 *
	 * Data will be serialized. Don't forget to sanitize user input.
	 *
	 * @param int $entry_id The ID of the entry to be updated
	 * @param string $meta_key The key for the meta data to be stored
	 * @param mixed $meta_value The data to be stored for the entry
	 * @param int|null $form_id The form ID of the entry (optional, saves extra query if passed when creating the metadata)
	 */
	public static function gform_update_meta( $entry_id, $meta_key, $meta_value, $form_id = null ) {
		global $wpdb, $_gform_lead_meta;
		if ( intval( $entry_id ) <= 0 ) {
			return;
		}
		$table_name = RGFormsModel::get_lead_meta_table_name();
		if ( false === $meta_value ) {
			$meta_value = '0';
		}
		$serialized_meta_value  = maybe_serialize( $meta_value );
		$meta_exists = gform_get_meta( $entry_id, $meta_key ) !== false;
		if ( $meta_exists ) {
			$wpdb->update( $table_name, array( 'meta_value' => $serialized_meta_value ), array( 'lead_id' => $entry_id, 'meta_key' => $meta_key ), array( '%s' ), array( '%d', '%s' ) );
		} else {

			if ( empty( $form_id ) ) {
				$lead_table_name = RGFormsModel::get_lead_table_name();
				$form_id         = $wpdb->get_var( $wpdb->prepare( "SELECT form_id from $lead_table_name WHERE id=%d", $entry_id ) );
			} else {
				$form_id = intval( $form_id );
			}

			$wpdb->insert( $table_name, array( 'form_id' => $form_id, 'lead_id' => $entry_id, 'meta_key' => $meta_key, 'meta_value' => $serialized_meta_value ), array( '%d', '%d', '%s', '%s' ) );
		}

		//updates cache
		$cache_key = get_current_blog_id() . '_' . $entry_id . '_' . $meta_key;
		if ( array_key_exists( $cache_key, $_gform_lead_meta ) ) {
			$_gform_lead_meta[ $cache_key ] = $meta_value;
		}
	}

	/**
	 * Add metadata associated with an entry
	 *
	 * Data will be serialized; Don't forget to sanitize user input.
	 *
	 * @param int $entry_id The ID of the entry where metadata is to be added
	 * @param string $meta_key The key for the meta data to be stored
	 * @param mixed $meta_value The data to be stored for the entry
	 * @param int|null $form_id The form ID of the entry (optional, saves extra query if passed when creating the metadata)
	 */
	public static function gform_add_meta( $entry_id, $meta_key, $meta_value, $form_id = null ) {
		global $wpdb, $_gform_lead_meta;
		$table_name = RGFormsModel::get_lead_meta_table_name();
		if ( false === $meta_value ) {
			$meta_value = '0';
		}
		$serialized_meta_value  = maybe_serialize( $meta_value );

		if ( empty( $form_id ) ) {
			$lead_table_name = RGFormsModel::get_lead_table_name();
			$form_id         = $wpdb->get_var( $wpdb->prepare( "SELECT form_id from $lead_table_name WHERE id=%d", $entry_id ) );
		} else {
			$form_id = intval( $form_id );
		}

		$wpdb->insert( $table_name, array( 'form_id' => $form_id, 'lead_id' => $entry_id, 'meta_key' => $meta_key, 'meta_value' => $serialized_meta_value ), array( '%d', '%d', '%s', '%s' ) );

		$cache_key                      = get_current_blog_id() . '_' . $entry_id . '_' . $meta_key;
		$_gform_lead_meta[ $cache_key ] = $meta_value;
	}

	public static function gform_delete_meta( $entry_id, $meta_key = '' ) {
		global $wpdb, $_gform_lead_meta;
		$table_name  = RGFormsModel::get_lead_meta_table_name();
		$meta_filter = empty( $meta_key ) ? '' : $wpdb->prepare( 'AND meta_key=%s', $meta_key );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE lead_id=%d {$meta_filter}", $entry_id ) );

		//clears cache.
		$_gform_lead_meta = array();
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

		$current_entry = $original_entry = GFFormsModel::get_entry( $entry_id );

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
		$entry_meta = self::get_entry_meta( $form_id );
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

		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		$form = GFAPI::get_form( $entry['form_id'] );
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

	public static function get_lead_detail_id( $current_fields, $field_number ) {
		foreach ( $current_fields as $field ) {
			if ( $field->field_number == $field_number ) {
				return $field->id;
			}
		}

		return 0;
	}

	/**
	 * Updates an existing field value in the database.
	 *
	 * @param array $form
	 * @param array $lead
	 * @param GF_Field $field
	 * @param int $lead_detail_id
	 * @param string $input_id
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function update_lead_field_value( $form, $lead, $field, $lead_detail_id, $input_id, $value ) {
		global $wpdb;

		/**
		 * Filter the value before it's saved to the database.
		 *
		 * @since 1.5.0
		 * @since 1.8.6 Added the $input_id parameter.
		 * @since 1.9.14 Added form and field specific versions.
		 *
		 * @param string|array $value The fields input value.
		 * @param array $lead The current entry object.
		 * @param GF_Field $field The current field object.
		 * @param array $form The current form object.
		 * @param string $input_id The ID of the input being saved or the field ID for single input field types.
		 */
		$value = apply_filters( 'gform_save_field_value', $value, $lead, $field, $form, $input_id );
		$value = apply_filters( "gform_save_field_value_{$form['id']}", $value, $lead, $field, $form, $input_id );

		if ( is_object( $field ) ) {
			$value = apply_filters( "gform_save_field_value_{$form['id']}_{$field->id}", $value, $lead, $field, $form, $input_id );
		}

		if ( is_array( $value ) ) {
			GFCommon::log_debug( __METHOD__ . '(): bailing. value is an array.' );
			return false;
		}

		$lead_id                = $lead['id'];
		$form_id                = $form['id'];
		$lead_detail_table      = self::get_lead_details_table_name();

		// Add emoji support.
		if ( version_compare( get_bloginfo( 'version' ), '4.2', '>=' ) ) {

			// Get charset for lead detail value column .
			$charset = $wpdb->get_col_charset( $lead_detail_table, 'value' );

			// If lead detail value column is UTF-8, encode emoji.
			if ( 'utf8' === $charset ) {
				$value = wp_encode_emoji( $value );
			}
		}

		if ( ! rgblank( $value ) ) {


			if ( $lead_detail_id > 0 ) {

				$result = $wpdb->update( $lead_detail_table, array( 'value' => $value ), array( 'id' => $lead_detail_id ), array( '%s' ), array( '%d' ) );
				if ( false === $result ) {
					return false;
				}

			} else {
				$result = $wpdb->insert( $lead_detail_table, array( 'lead_id' => $lead_id, 'form_id' => $form_id, 'field_number' => $input_id, 'value' => $value ), array( '%d', '%d', '%F', '%s' ) );
				if ( false === $result ) {
					return false;
				}

			}
		} else {
			//Deleting details for this field
			$sql    = $wpdb->prepare( "DELETE FROM $lead_detail_table WHERE lead_id=%d AND field_number BETWEEN %s AND %s ", $lead_id, doubleval( $input_id ) - 0.0001, doubleval( $input_id ) + 0.0001 );
			$result = $wpdb->query( $sql );
			if ( false === $result ) {
				return false;
			}
		}

		return true;
	}

	public static function add_note( $lead_id, $user_id, $user_name, $note, $note_type = 'note' ) {
		global $wpdb;

		$table_name = self::get_lead_notes_table_name();
		$sql        = $wpdb->prepare( "INSERT INTO $table_name(lead_id, user_id, user_name, value, note_type, date_created) values(%d, %d, %s, %s, %s, utc_timestamp())", $lead_id, $user_id, $user_name, $note, $note_type );

		$wpdb->query( $sql );

		/**
		 * Fires after a note has been added to an entry
		 *
		 * @param int    $wpdb->insert_id The row ID of this note in the database
		 * @param int    $lead_id         The ID of the entry that the note was added to
		 * @param int    $user_id         The ID of the current user adding the note
		 * @param string $user_name       The user name of the current user
		 * @param string $note            The content of the note being added
		 * @param string $note_type       The type of note being added.  Defaults to 'note'
		 */
		do_action( 'gform_post_note_added', $wpdb->insert_id, $lead_id, $user_id, $user_name, $note, $note_type );
	}

	public static function delete_note( $note_id ) {
		global $wpdb;

		$table_name = self::get_lead_notes_table_name();

		$lead_id = $wpdb->get_var( $wpdb->prepare( "SELECT lead_id FROM $table_name WHERE id = %d", $note_id ) );

		/**
		 * Fires before a note is deleted
		 *
		 * @param int $note_id The current note ID
		 * @param int $lead_id The current lead ID
		 */
		do_action( 'gform_pre_note_deleted', $note_id, $lead_id );

		$sql        = $wpdb->prepare( "DELETE FROM $table_name WHERE id=%d", $note_id );
		$wpdb->query( $sql );
	}

	public static function get_lead_count_all_forms( $status = 'active' ) {
		global $wpdb;

		$lead_table_name   = self::get_lead_table_name();

		$sql = $wpdb->prepare( "SELECT count(id)
								FROM $lead_table_name
								WHERE status=%s", $status );

		return $wpdb->get_var( $sql );
	}
}
