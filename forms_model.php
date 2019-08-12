<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

require_once( ABSPATH . WPINC . '/post.php' );
require_once( 'includes/legacy/forms_model_legacy.php' );

/**
 * Class GFFormsModel
 *
 * Handles database calls and formatting of stored data regarding forms
 */
class GFFormsModel {

	/**
	 * Stores the values containing and uploaded files for later access
	 *
	 * @since  Unknwon
	 * @access public
	 *
	 * @var array Defaults to an empty array.
	 */
	public static $uploaded_files = array();
	/**
	 * Stores unique form IDs found.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var array Defaults to an empty array.
	 */
	public static $unique_ids = array();

	/**
	 * Stores confirmations found.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @var array Defaults to an empty array.
	 */
	private static $_confirmations = array();

	/**
	 * An in-memory cache for the form meta for the current blog.
	 *
	 *  Use "{Blog ID}_{Form ID}" as the key.
	 *
	 * @since   Unknown
	 * @access  private
	 * @example $_current_forms['1_2']
	 *
	 * @var array $_current_forms
	 */
	private static $_current_forms = array();

	/**
	 * The entry data for the current site.
	 *.
	 * @access private
	 *
	 * @var null Defaults to null.
	 */
	private static $_current_lead = null;

	private static $_batch_field_updates = array();
	private static $_batch_field_inserts = array();
	private static $_batch_field_deletes = array();

	/**
	 * Returns the current database version.
	 *
	 * @since 2.2
	 *
	 * @return string
	 */
	public static function get_database_version() {
		static $db_version = array();
		$blog_id = get_current_blog_id();
		if ( empty( $db_version[ $blog_id ] ) ) {
			$db_version[ $blog_id ] = get_option( 'gf_db_version' );
		}

		return $db_version[ $blog_id ];
	}

	/**
	 * Flushes the data stored within GFFormsModel::$_current_forms.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::$_current_forms
	 *
	 * @return void
	 */
	public static function flush_current_forms() {
		self::$_current_forms = null;
		self::flush_confirmations();
	}

	/**
	 * Flushes the data stored within GFFormsModel::$_current_lead.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::$_current_lead
	 *
	 * @return void
	 */
	public static function flush_current_lead() {
		self::$_current_lead = null;
	}

	/**
	 * Flushes the data stored within GFFormsModel::$_confirmations
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::$_confirmations
	 *
	 * @return void
	 */
	public static function flush_confirmations() {
		self::$_confirmations = null;
	}

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

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return $wpdb->prefix . 'rg_form';
		}

		return $wpdb->prefix . 'gf_form';
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

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return $wpdb->prefix . 'rg_form_meta';
		}

		return $wpdb->prefix . 'gf_form_meta';
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

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return $wpdb->prefix . 'rg_form_view';
		}

		return $wpdb->prefix . 'gf_form_view';
	}

	/**
	 * Gets the form revisions table name, including the site's database prefix.
	 *
	 * @since  2.4-dev
	 *
	 * @return string The form revisions table name.
	 */
	public static function get_form_revisions_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_form_revisions';
	}

	/**
	 * Gets the lead (entries) table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The lead (entry) table name.
	 */
	public static function get_lead_table_name() {
		return GF_Forms_Model_Legacy::get_lead_table_name();
	}

	/**
	 * Gets the lead (entry) meta table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The lead (entry) meta table name.
	 */
	public static function get_lead_meta_table_name() {
		return GF_Forms_Model_Legacy::get_lead_meta_table_name();
	}

	/**
	 * Gets the lead (entry) notes table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The lead (entry) notes table name.
	 */
	public static function get_lead_notes_table_name() {
		return GF_Forms_Model_Legacy::get_lead_notes_table_name();
	}

	/**
	 * Gets the lead (entry) details table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The lead (entry) details table name.
	 */
	public static function get_lead_details_table_name() {
		return GF_Forms_Model_Legacy::get_lead_details_table_name();
	}

	/**
	 * Gets the lead (entry) details long table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The lead (entry) details long table name.
	 */
	public static function get_lead_details_long_table_name() {
		return GF_Forms_Model_Legacy::get_lead_details_long_table_name();
	}

	/**
	 * Gets the lead (entry) view table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string The lead (entry) view table name.
	 */
	public static function get_lead_view_name() {
		return GF_Forms_Model_Legacy::get_lead_view_name();
	}

	/**
	 * Gets the incomplete submissions table name, including the site's database prefix.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @return string he incomplete submissions table name.
	 */
	public static function get_incomplete_submissions_table_name() {
		return GF_Forms_Model_Legacy::get_incomplete_submissions_table_name();
	}

	/**
	 * Gets the entry table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The entry table name
	 */
	public static function get_entry_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_entry';
	}

	/**
	 * Gets the entry meta table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The entry meta table name
	 */
	public static function get_entry_meta_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_entry_meta';
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
	public static function get_entry_notes_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_entry_notes';
	}


	/**
	 * Gets the draft submissions table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The draft submissions table name
	 */
	public static function get_draft_submissions_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_draft_submissions';
	}

	/**
	 * Gets the REST API Key table name, including the site's database prefix
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 *
	 * @return string The REST API Keys submissions table name
	 */
	public static function get_rest_api_keys_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_rest_api_keys';
	}


	/**
	 * Gets all forms.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_form_table_name()
	 * @uses GFFormsModel::get_form_db_columns()
	 * @uses GFFormsModel::get_entry_count_per_form()
	 * @uses GFFormsModel::get_view_count_per_form()
	 *
	 * @param bool   $is_active   Optional. Defines if inactive forms should be displayed. Defaults to null.
	 * @param string $sort_column Optional. The column to be used for sorting the forms. Defaults to 'title'.
	 * @param string $sort_dir    Optional. Defines the direction that sorting should occur. Defaults to 'ASC' (ascending). Use 'DESC' for descending.
	 * @param bool   $is_trash    Optional. Defines if forms within the trash should be displayed. Defaults to false.
	 *
	 * @return array $forms All forms found.
	 */
	public static function get_forms( $is_active = null, $sort_column = 'title', $sort_dir = 'ASC', $is_trash = false ) {
		global $wpdb;
		$form_table_name = self::get_form_table_name();

		$where_arr   = array();
		$where_arr[] = $wpdb->prepare( 'is_trash=%d', $is_trash );
		if ( $is_active !== null ) {
			$where_arr[] = $wpdb->prepare( 'is_active=%d', $is_active );
		}

		$where_clause = 'WHERE ' . join( ' AND ', $where_arr );
		$sort_keyword = $sort_dir == 'ASC' ? 'ASC' : 'DESC';

		$db_columns = self::get_form_db_columns();

		if ( ! in_array( strtolower( $sort_column ), $db_columns ) ) {
			$sort_column = 'title';
		}

		$order_by     = ! empty( $sort_column ) ? "ORDER BY $sort_column $sort_keyword" : '';

		$sql = "SELECT f.id, f.title, f.date_created, f.is_active, 0 as entry_count, 0 view_count
                FROM $form_table_name f
                $where_clause
                $order_by";

		//Getting all forms
		$forms = $wpdb->get_results( $sql );

		//Getting entry count per form
		$entry_count = self::get_entry_count_per_form();

		//Getting view count per form
		$view_count = self::get_view_count_per_form();

		//Adding entry counts and to form array
		foreach ( $forms as &$form ) {
			foreach ( $view_count as $count ) {
				if ( $count->form_id == $form->id ) {
					$form->view_count = $count->view_count;
					break;
				}
			}

			foreach ( $entry_count as $count ) {
				if ( $count->form_id == $form->id ) {
					$form->entry_count = $count->entry_count;
					break;
				}
			}
		}

		return $forms;
	}

	/**
	 * Searches form titles based on query.
	 *
	 * @access public
	 * @static
	 * @global $wpdb
	 * @see GFFormsModel::get_form_table_name
	 * @see GFFormsModel::get_form_db_columns
	 * @see GFFormsModel::get_entry_count_per_form
	 * @see GFFormsModel::get_view_count_per_form
	 *
	 * @param string $query       Optional. The query to search.
	 * @param bool   $is_active   Optional. Defines if inactive forms should be displayed. Defaults to null.
	 * @param string $sort_column Optional. The column to be used for sorting the forms. Defaults to 'title'.
	 * @param string $sort_dir    Optional. Defines the direction that sorting should occur. Defaults to 'ASC' (ascending). Use 'DESC' for descending.
	 * @param bool   $is_trash    Optional. Defines if forms within the trash should be displayed. Defaults to false.
	 *
	 * @return array $forms All forms found.
	 */
	public static function search_forms( $query = '', $is_active = null, $sort_column = 'title', $sort_dir = 'ASC', $is_trash = false ) {
		global $wpdb;
		$form_table_name = self::get_form_table_name();

		$where_arr   = array();
		$where_arr[] = $wpdb->prepare( 'is_trash=%d', $is_trash );
		if ( $is_active !== null ) {
			$where_arr[] = $wpdb->prepare( 'is_active=%d', $is_active );
		}

		if ( ! rgblank( $query ) ) {
			$where_arr[] = $wpdb->prepare( 'title LIKE %s', '%' . $query . '%' );
		}

		$where_clause = 'WHERE ' . join( ' AND ', $where_arr );
		$sort_keyword = $sort_dir == 'ASC' ? 'ASC' : 'DESC';

		$db_columns = self::get_form_db_columns();

		if ( ! in_array( strtolower( $sort_column ), $db_columns ) ) {
			$sort_column = 'title';
		}

		$order_by     = ! empty( $sort_column ) ? "ORDER BY $sort_column $sort_keyword" : '';

		$sql = "SELECT f.id, f.title, f.date_created, f.is_active, 0 as entry_count, 0 view_count
                FROM $form_table_name f
                $where_clause
                $order_by";

		//Getting all forms
		$forms = $wpdb->get_results( $sql );

		//Getting entry count per form
		$entry_count = self::get_entry_count_per_form();

		//Getting view count per form
		$view_count = self::get_view_count_per_form();

		//Adding entry counts and to form array
		foreach ( $forms as &$form ) {
			foreach ( $view_count as $count ) {
				if ( $count->form_id == $form->id ) {
					$form->view_count = $count->view_count;
					break;
				}
			}

			foreach ( $entry_count as $count ) {
				if ( $count->form_id == $form->id ) {
					$form->entry_count = $count->entry_count;
					break;
				}
			}
		}

		return $forms;
	}

	/**
	 * Gets the number of entries per form.
	 *
	 * First attempts to read from cache. If unavailable, gets the entry count, caches it, and returns it.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_lead_table_name()
	 * @uses GFCache::get()
	 * @uses GFCache::set()
	 *
	 * @return array $entry_count Array of forms, containing the form ID and the entry count
	 */
	public static function get_entry_count_per_form() {

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_entry_count_per_form();
		}

		global $wpdb;
		$entry_table_name = self::get_entry_table_name();

		$entry_count = GFCache::get( 'get_entry_count_per_form' );
		if ( empty( $entry_count ) ) {
			//Getting entry count per form
			$sql         = "SELECT form_id, count(id) as entry_count FROM $entry_table_name l WHERE status='active' GROUP BY form_id";
			$entry_count = $wpdb->get_results( $sql );

			GFCache::set( 'get_entry_count_per_form', $entry_count, true, 30 );
		}

		return $entry_count;
	}

	/**
	 * Gets the number of views per form
	 *
	 * Checks the cache first.  If not there, gets the count from the database, stores it in the cache, and returns it.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_form_view_table_name()
	 * @uses GFCache::get()
	 * @uses GFCache::set()
	 *
	 * @return array $view_count Array of forms, containing the form ID and the view count
	 */
	public static function get_view_count_per_form() {
		global $wpdb;
		$view_table_name = self::get_form_view_table_name();

		$view_count = GFCache::get( 'get_view_count_per_form' );
		if ( empty( $view_count ) ){
			$sql        = "SELECT form_id, sum(count) as view_count FROM $view_table_name GROUP BY form_id";
			$view_count = $wpdb->get_results( $sql );

			GFCache::set( 'get_view_count_per_form', $view_count, true, 30 );
		}

		return $view_count;
	}

	/**
	 * Returns the form database columns.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return array The column IDs
	 */
	public static function get_form_db_columns() {
		return array( 'id', 'title', 'date_created', 'is_active', 'is_trash' );
	}

	/**
	 * Gets the payment totals for a particular form ID.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_lead_table_name()
	 *
	 * @param int $form_id The form ID to get payment totals for.
	 *
	 * @return array $totals The payment totals found.
	 */
	public static function get_form_payment_totals( $form_id ) {
		global $wpdb;
		$entry_table_name = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_lead_table_name() : self::get_entry_table_name();

		$sql = $wpdb->prepare(
			" SELECT sum(payment_amount) revenue, count(l.id) orders
             FROM $entry_table_name l
             WHERE form_id=%d AND payment_amount IS NOT null", $form_id
		);

		$totals = $wpdb->get_row( $sql, ARRAY_A );

		$active = $wpdb->get_var(
			$wpdb->prepare(
				" SELECT count(id) as active
                 FROM $entry_table_name
                 WHERE form_id=%d AND payment_status='Active'", $form_id
			)
		);

		if ( empty( $active ) ) {
			$active = 0;
		}

		$totals['active'] = $active;

		return $totals;
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

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_form_counts( $form_id );
		}

		global $wpdb;

		$cache_key = 'form_counts_' . $form_id;

		$results = GFCache::get( $cache_key );

		if ( ! empty( $results ) ) {
			return $results;
		}

		$entry_table_name = self::get_entry_table_name();
		$entry_detail_table_name = self::get_entry_meta_table_name();

		$sql             = $wpdb->prepare(
			"SELECT
                    (SELECT count(DISTINCT(l.id)) FROM $entry_table_name l INNER JOIN $entry_detail_table_name ld ON l.id=ld.entry_id WHERE l.form_id=%d AND l.status='active') as total,
                    (SELECT count(DISTINCT(l.id)) FROM $entry_table_name l INNER JOIN $entry_detail_table_name ld ON l.id=ld.entry_id WHERE l.is_read=0 AND l.status='active' AND l.form_id=%d) as unread,
                    (SELECT count(DISTINCT(l.id)) FROM $entry_table_name l INNER JOIN $entry_detail_table_name ld ON l.id=ld.entry_id WHERE l.is_starred=1 AND l.status='active' AND l.form_id=%d) as starred,
                    (SELECT count(DISTINCT(l.id)) FROM $entry_table_name l INNER JOIN $entry_detail_table_name ld ON l.id=ld.entry_id WHERE l.status='spam' AND l.form_id=%d) as spam,
                    (SELECT count(DISTINCT(l.id)) FROM $entry_table_name l INNER JOIN $entry_detail_table_name ld ON l.id=ld.entry_id WHERE l.status='trash' AND l.form_id=%d) as trash",
			$form_id, $form_id, $form_id, $form_id, $form_id
		);

		$wpdb->timer_start();
		$results = $wpdb->get_results( $sql, ARRAY_A );
		$time_total = $wpdb->timer_stop();
		if ( $time_total > 1 ) {
			GFCache::set( $cache_key, $results[0], true, 10 * MINUTE_IN_SECONDS );
		}

		return $results[0];

	}

	/**
	 * Gets the form summary for all forms.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_form_table_name()
	 * @uses GFFormsModel::get_lead_table_name()
	 *
	 * @return array $forms Contains the form summary for all forms.
	 */
	public static function get_form_summary() {
		global $wpdb;
		$form_table_name = self::get_form_table_name();
		$entry_table_name = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_lead_table_name() : self::get_entry_table_name();

		$sql = "SELECT l.form_id, count(l.id) as unread_count
                FROM $entry_table_name l
                WHERE is_read=0 AND status='active'
                GROUP BY form_id";

		// Getting number of unread and total leads for all forms
		$unread_results = $wpdb->get_results( $sql, ARRAY_A );

		$sql = "SELECT l.form_id, max(l.date_created) as last_entry_date, count(l.id) as total_entries
                FROM $entry_table_name l
                WHERE status='active'
                GROUP BY form_id";

		$lead_date_results = $wpdb->get_results( $sql, ARRAY_A );

		$sql = "SELECT id, title, is_trash, '' as last_entry_date, 0 as unread_count

                FROM $form_table_name
                WHERE is_active=1
                ORDER BY title";

		$forms = $wpdb->get_results( $sql, ARRAY_A );


		for ( $i = 0; $count = sizeof( $forms ), $i < $count; $i ++ ) {
			if ( is_array( $unread_results ) ) {
				foreach ( $unread_results as $unread_result ) {
					if ( $unread_result['form_id'] == $forms[ $i ]['id'] ) {
						$forms[ $i ]['unread_count'] = $unread_result['unread_count'];
						break;
					}
				}
			}

			if ( is_array( $lead_date_results ) ) {
				foreach ( $lead_date_results as $entry_date_result ) {
					if ( $entry_date_result['form_id'] == $forms[ $i ]['id'] ) {
						$forms[ $i ]['last_entry_date'] = $entry_date_result['last_entry_date'];
						$forms[ $i ]['total_entries']    = $entry_date_result['total_entries'];
						break;
					}
				}
			}
		}

		return $forms;
	}

	/**
	 * Gets the total, active, inactive, and trashed form counts.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_form_table_name()
	 *
	 * @return array The form counts.
	 */
	public static function get_form_count() {
		global $wpdb;
		$form_table_name = self::get_form_table_name();

		if ( ! GFCommon::table_exists( $form_table_name ) ) {
			return array(
				'total'    => 0,
				'active'   => 0,
				'inactive' => 0,
				'trash'    => 0,
			);
		}

		$results = $wpdb->get_results(
			"
            SELECT
            (SELECT count(0) FROM $form_table_name WHERE is_trash = 0) as total,
            (SELECT count(0) FROM $form_table_name WHERE is_active=1 AND is_trash = 0 ) as active,
            (SELECT count(0) FROM $form_table_name WHERE is_active=0 AND is_trash = 0 ) as inactive,
            (SELECT count(0) FROM $form_table_name WHERE is_trash=1) as trash
            "
		);

		return array(
			'total'    => intval( $results[0]->total ),
			'active'   => intval( $results[0]->active ),
			'inactive' => intval( $results[0]->inactive ),
			'trash'    => intval( $results[0]->trash ),
		);
	}

	/**
	 * Gets the form ID based on the form title.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::get_forms()
	 *
	 * @param string $form_title The form title to search for.
	 *
	 * @return int The form ID. Returns 0 if not found.
	 */
	public static function get_form_id( $form_title ) {
		$forms = self::get_forms();
		foreach ( $forms as $form ) {
			$sanitized_name = str_replace( '[', '', str_replace( ']', '', $form->title ) );
			if ( $form->title == $form_title || $sanitized_name == $form_title ) {
				return $form->id;
			}
		}

		return 0;
	}

	/**
	 * Gets a form based on the form ID.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_form_table_name()
	 *
	 * @param int  $form_id     The ID of the form to get.
	 * @param bool $allow_trash Optional. Set to true to allow trashed results. Defaults to false.
	 *
	 * @return bool
	 */
	public static function get_form( $form_id, $allow_trash = false ) {
		global $wpdb;
		$table_name   = self::get_form_table_name();
		$trash_clause = $allow_trash ? '' : 'AND is_trash = 0';
		$results      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE id=%d {$trash_clause}", $form_id ) );

		return isset( $results[0] ) ? $results[0] : false;
	}

	/**
	 * Converts a serialized string or JSON for access in PHP.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $string The string to convert.
	 *
	 * @return object|array The object that the string was converted to.
	 */
	public static function unserialize( $string ) {

		if ( is_serialized( $string ) ) {
			$obj = @unserialize( $string );
		} else {
			$obj = json_decode( $string, true );
		}

		return $obj;
	}

	/**
	 * Gets the form meta based on the form ID.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_meta_table_name()
	 * @uses GFFormsModel::unserialize()
	 * @uses GFFormsModel::convert_field_objects()
	 * @uses GFFormsModel::load_notifications_from_legacy()
	 * @uses GFFormsModel::$_current_forms
	 *
	 * @param int $form_id The form ID.
	 *
	 * @return array|null $form Form object if found. Null if not found.
	 */
	public static function get_form_meta( $form_id ) {
		global $wpdb;

		$form_id = absint( $form_id );

		$key = get_current_blog_id() . '_' . $form_id;
		// Return cached version if form meta has been previously retrieved for this form
		if ( isset( self::$_current_forms[ $key ] ) ) {
			return self::$_current_forms[ $key ];
		}

		$table_name = self::get_meta_table_name();
		$form_row   = $wpdb->get_row( $wpdb->prepare( "SELECT display_meta, notifications FROM {$table_name} WHERE form_id=%d", $form_id ), ARRAY_A );


		// Loading main form object (supports serialized strings as well as JSON strings)
		$form = self::unserialize( $form_row['display_meta'] );

		if ( ! $form ) {
			return null;
		}

		// Ensure the fields property is in the correct format, an associative array will cause warnings and js errors in the form editor.
		if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
			$form['fields'] = array_values( $form['fields'] );
		}

		// Loading notifications
		$form['notifications'] = self::unserialize( $form_row['notifications'] );

		// Creating field objects and copying some form variables down to fields for easier access
		$form = self::convert_field_objects( $form );

		// Loading confirmations from legacy structure into new structure
		$form = self::load_confirmations( $form );

		//only migrate legacy notification if there isn't any notification configured in new structure
		if ( ! isset( $form['notifications'] ) ) {
			$form = self::load_notifications_from_legacy( $form ); // Moving notification data from legacy structure into new 'notifications' array
		}

		// Load notifications to legacy structure to maintain backward compatibility with legacy hooks and functions
		$form = self::load_notifications_to_legacy( $form );

		// Ensure the next field ID is set correctly.
		$form['nextFieldId'] = self::get_next_field_id( $form['fields'] );

		/**
		 * Filters the Form object after the form meta is obtained
		 *
		 * @param array $form The Form object
		 */
		$form = gf_apply_filters( array( 'gform_form_post_get_meta', $form_id ), $form );

		// Cached form meta for cheaper retrieval on subsequent requests
		self::$_current_forms[ $key ] = $form;

		return $form;
	}

	/**
	 * Recursively checks the highest ID for all the fields in the form and then returns the highest ID + 1.
	 *
	 * @since 2.4.6.12
	 *
	 * @param GF_Field[] $fields
	 * @param int        $next_field_id
	 *
	 * @return int
	 */
	public static function get_next_field_id( $fields, $next_field_id = 1 ) {

		foreach ( $fields as $field ) {

			if ( is_array( $field->fields ) ) {
				$next_field_id = self::get_next_field_id( $field->fields, $next_field_id );
			}

			if ( $field->id >= $next_field_id ) {
				$next_field_id = $field->id + 1;
			}

		}

		return (int) $next_field_id;
	}

	/**
	 * Converts all field objects in a form, based on field type.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GF_Field_CreditCard::maybe_upgrade_inputs()
	 *
	 * @param array $form The Form object.
	 *
	 * @return array $form The Form object after the field objects are converted.
	 */
	public static function convert_field_objects( $form ) {
		$page_number = 1;
		if ( is_array( rgar( $form, 'fields' ) ) ) {
			foreach ( $form['fields'] as &$field ) {

				// convert adminOnly property to visibility
				if ( ! isset( $field['visibility'] ) ) {
					$field['visibility'] = isset( $field['adminOnly'] ) && $field['adminOnly'] ? 'administrative' : 'visible';
					unset( $field['adminOnly'] );
				}

				$field = GF_Fields::create( $field );
				if ( isset( $form['id'] ) ) {
					$field->formId = $form['id'];
				}

				$field->pageNumber = $page_number;

				if ( is_array( $field->fields ) ) {
					self::convert_sub_field_objects( $field, $form['id'], $page_number );
				}

				if ( $field->type == 'page' ) {
					$page_number ++;
					$field->pageNumber = $page_number;
				}

				$field->post_convert_field();
			}
		}

		return $form;
	}

	/**
	 * Converts the sub fields to field objects.
	 *
	 * @since 2.4
	 *
	 * @param GF_Field $field         The repeater field to be converted to objects.
	 * @param int      $form_id       The current form ID.
	 * @param int      $page_number   The page number the parent field is located on.
	 * @param int      $nesting_level The level at which a repeater field is nested.
	 */
	private static function convert_sub_field_objects( &$field, $form_id, $page_number, $nesting_level = 0 ) {
		$field->nestingLevel = $nesting_level;
		foreach ( $field->fields as &$field ) {
			$field             = GF_Fields::create( $field );
			$field->formId     = $form_id;
			$field->pageNumber = $page_number;
			$field->post_convert_field();
			if ( is_array( $field['fields'] ) ) {
				$new_nesting_level = $nesting_level + 1;
				self::convert_sub_field_objects( $field, $form_id, $page_number, $new_nesting_level );
			}
		}
	}

	/**
	 * Gets the form meta for multiple forms based on an array for form IDs.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_form_table_name()
	 * @uses GFFormsModel::get_meta_table_name()
	 * @uses GFFormsModel::unserialize()
	 * @uses GFFormsModel::convert_field_objects()
	 *
	 * @param array $ids Array of form IDs.
	 *
	 * @return array $results
	 */
	public static function get_form_meta_by_id( $ids ) {
		global $wpdb;
		$form_table_name = self::get_form_table_name();
		$meta_table_name = self::get_meta_table_name();

		if ( is_array( $ids ) ) {
			$ids = implode( ',', array_map( 'intval', $ids ) );
		} else {
			$ids = intval( $ids );
		}

		$results = $wpdb->get_results(
			" SELECT display_meta, confirmations, notifications FROM {$form_table_name} f
                                        INNER JOIN {$meta_table_name} m ON f.id = m.form_id
                                        WHERE id in({$ids})", ARRAY_A
		);

		foreach ( $results as &$result ) {
			$form                  = self::unserialize( $result['display_meta'] );
			$form['confirmations'] = self::unserialize( $result['confirmations'] );
			$form['notifications'] = self::unserialize( $result['notifications'] );
			// Creating field objects and copying some form variables down to fields for easier access
			$form   = self::convert_field_objects( $form );
			$result = $form;
		}

		return $results;

	}

	/**
	 * Converts current notification structure to legacy.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @param array $form The Form object.
	 *
	 * @return array $form The Form object.
	 */
	private static function load_notifications_to_legacy( $form ) {
		if ( ! is_array( rgar( $form, 'notifications' ) ) ) {
			return $form;
		}

		foreach ( $form['notifications'] as $notification ) {
			if ( ! in_array( rgar( $notification, 'type' ), array( 'user', 'admin' ) ) ) {
				continue;
			}

			$legacy_notification = $notification;

			if ( $notification['toType'] == 'field' ) {
				$legacy_notification['toField'] = $notification['to'];
				unset( $legacy_notification['to'] );
			}

			// Unsetting new properties
			unset( $legacy_notification['toType'] );
			unset( $legacy_notification['id'] );
			unset( $legacy_notification['event'] );
			unset( $legacy_notification['name'] );
			if ( isset( $legacy_notification['type'] ) ) {
				unset( $legacy_notification['type'] );
			}

			//saving into form object
			$property        = $notification['type'] == 'user' ? 'autoResponder' : 'notification';
			$form[ $property ] = $legacy_notification;
		}

		return $form;
	}

	/**
	 * Loads notifications using from the legacy format.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFCommon::has_admin_notification()
	 * @uses GFFormsModel::convert_property_to_merge_tag()
	 * @uses GFCommon::has_user_notification()
	 * @uses GFFormsModel::save_form_notifications()
	 *
	 * @param array $form The Form object.
	 *
	 * @return array $form The Form object.
	 */
	private static function load_notifications_from_legacy( $form ) {

		$form['notifications'] = array();
		if ( GFCommon::has_admin_notification( $form ) ) {
			$admin_notification = $form['notification'];

			//if there is a fromField configured, move it to 'from' as a merge tag
			$admin_notification = self::convert_property_to_merge_tag( $form, $admin_notification, 'from', 'fromField' );

			//if there is a fromNameField configured, move it to 'fromName' as a merge tag
			$admin_notification = self::convert_property_to_merge_tag( $form, $admin_notification, 'fromName', 'fromNameField' );

			//if there is a replyToField configured, move it to 'replyTo' as a merge tag
			$admin_notification = self::convert_property_to_merge_tag( $form, $admin_notification, 'replyTo', 'replyToField' );

			//if routing is configured, set toType to routing, otherwise, set it to email
			$admin_notification['toType'] = ! rgempty( 'routing', $admin_notification ) ? 'routing' : 'email';

			$notification_id = uniqid();

			//assigning this notification to the form_submission action
			$admin_notification['event'] = 'form_submission';
			$admin_notification['name']  = esc_html__( 'Admin Notification', 'gravityforms' );
			$admin_notification['type']  = 'admin';
			$admin_notification['id']    = $notification_id;

			//copying admin notification as an item in the new notifications array
			$form['notifications'][ $notification_id ] = $admin_notification;
		}

		if ( GFCommon::has_user_notification( $form ) ) {

			$user_notification = $form['autoResponder'];

			//if there is a toField configured, set toType to field, if not, set it toemail
			$to_field = rgar( $user_notification, 'toField' );
			if ( ! empty( $to_field ) ) {
				$user_notification['toType'] = 'field';
				$user_notification['to']     = $to_field;
			} else {
				$user_notification['toType'] = 'email';
			}

			$notification_id = uniqid();
			//assigning this notification to the form_submission action
			$user_notification['event'] = 'form_submission';
			$user_notification['name']  = esc_html__( 'User Notification', 'gravityforms' );
			$user_notification['type']  = 'user';
			$user_notification['id']    = $notification_id;

			//copying user notification as an item in the new notifications array
			$form['notifications'][ $notification_id ] = $user_notification;
		}

		self::save_form_notifications( $form['id'], $form['notifications'] );

		return $form;
	}

	/**
	 * Converts a form property to the merge tag format.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFFormsModel::get_field_merge_tag()
	 *
	 * @param array  $form            The Form object.
	 * @param array  $array           Array of properties to search through.
	 * @param string $target_property The property to move the value to.
	 * @param string $source_property The property to search for.
	 *
	 * @return array $array The array that was searched through.
	 */
	private static function convert_property_to_merge_tag( $form, $array, $target_property, $source_property ) {
		$merge_tag = self::get_field_merge_tag( $form, rgar( $array, $source_property ) );
		if ( $merge_tag ) {
			$array[ $target_property ] = $merge_tag;
			unset( $array[ $source_property ] );
		}

		return $array;
	}

	/**
	 * Gets a formatted merge tag for a field.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFFormsModel::get_field()
	 * @uses GFCommon::get_label()
	 *
	 * @param array $form     The Form object.
	 * @param int   $field_id The field ID.
	 *
	 * @return string|false The merge tag if found. False if not found.
	 */
	private static function get_field_merge_tag( $form, $field_id ) {
		$field = self::get_field( $form, $field_id );
		if ( ! $field ) {
			return false;
		}

		return '{' . GFCommon::get_label( $field, $field_id ) . ':' . $field_id . '}';
	}

	/**
	 * Adds default form properties
	 *
	 * @deprecated 1.9
	 */
	public static function add_default_properties( $form ) {
		_deprecated_function( 'GFFormsModel::add_default_properties', '1.9' );

		if ( is_array( rgar( $form, 'fields' ) ) ) {
			$all_fields = array(
				'adminLabel'        => '', 'allowsPrepopulate' => '', 'defaultValue' => '', 'description' => '', 'content' => '', 'cssClass' => '',
				'errorMessage'      => '', 'id' => '', 'inputName' => '', 'isRequired' => '', 'label' => '', 'noDuplicates' => '',
				'size'              => '', 'type' => '', 'postCustomFieldName' => '', 'displayAllCategories' => '', 'displayCaption' => '', 'displayDescription' => '',
				'displayTitle'      => '', 'inputType' => '', 'rangeMin' => '', 'rangeMax' => '', 'calendarIconType' => '',
				'calendarIconUrl'   => '', 'dateType' => '', 'dateFormat' => '', 'phoneFormat' => '', 'addressType' => '', 'defaultCountry' => '', 'defaultProvince' => '',
				'defaultState'      => '', 'hideAddress2' => '', 'hideCountry' => '', 'hideState' => '', 'inputs' => '', 'nameFormat' => '', 'allowedExtensions' => '',
				'captchaType'       => '', 'pageNumber' => '', 'captchaTheme' => '', 'simpleCaptchaSize' => '', 'simpleCaptchaFontColor' => '', 'simpleCaptchaBackgroundColor' => '',
				'failed_validation' => '', 'productField' => '', 'enablePasswordInput' => '', 'maxLength' => '', 'enablePrice' => '', 'basePrice' => '',
				'visibility'        => 'visible'
			);

			foreach ( $form['fields'] as &$field ) {
				if ( is_array( $field ) ) {
					$field = wp_parse_args( $field, $all_fields );
				}
			}
		}

		return $form;
	}

	/**
	 * Gets the column info for the entry listing page.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @uses GFFormsModel::get_meta_table_name()
	 *
	 * @param int $form_id The ID of the form that entries are coming from.
	 *
	 * @return mixed
	 */
	public static function get_grid_column_meta( $form_id ) {
		global $wpdb;

		$table_name = self::get_meta_table_name();

		return maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT entries_grid_meta FROM $table_name WHERE form_id=%d", $form_id ) ) );
	}

	public static function update_grid_column_meta( $form_id, $columns ) {
		global $wpdb;

		$table_name = self::get_meta_table_name();
		$meta       = maybe_serialize( stripslashes_deep( $columns ) );
		$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET entries_grid_meta=%s WHERE form_id=%d", $meta, $form_id ) );
	}

	public static function get_lead_detail_id( $current_fields, $field_number, $item_index = '' ) {
		if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_lead_detail_id( $current_fields, $field_number );
		}

		foreach ( $current_fields as $field ) {
			if ( $field->meta_key == $field_number && $field->item_index == $item_index) {
				return $field->id;
			}
		}

		return 0;
	}

	public static function update_form_active( $form_id, $is_active ) {
		global $wpdb;
		$form_table = self::get_form_table_name();
		$sql        = $wpdb->prepare( "UPDATE $form_table SET is_active=%d WHERE id=%d", $is_active, $form_id );
		$wpdb->query( $sql );

		if ( $is_active ) {

			/**
			 * Fires after an inactive form gets marked as active
			 *
			 * @since 1.9
			 *
			 * @param int $form_id The Form ID used to specify which form to activate
			 */
			do_action( 'gform_post_form_activated', $form_id );
		} else {

			/**
			 * Fires after an active form gets marked as inactive
			 *
			 * @since 1.9
			 *
			 * @param int $form_id The Form ID used to specify which form to activate
			 */
			do_action( 'gform_post_form_deactivated', $form_id );
		}
	}

	public static function update_notification_active( $form_id, $notification_id, $is_active ) {
		$form = GFFormsModel::get_form_meta( $form_id );

		if ( ! isset( $form['notifications'][ $notification_id ] ) ) {
			return new WP_Error( 'not_found', __( 'Notification not found', 'gravityforms' ) );
		}

		$form['notifications'][ $notification_id ]['isActive'] = (bool) $is_active;

		if ( (bool) $is_active ) {
            /**
             * Fires before a notification is activated
             *
             * @param int   $form['notifications'][ $notification_id ] The ID of the notification that was activated
             * @param array $form                                      The Form object
             */
			do_action( 'gform_pre_notification_activated', $form['notifications'][ $notification_id ], $form );
		} else {
            /**
             * Fires before a notification is deactivated
             *
             * @param int   $form['notifications'][ $notification_id ] The ID of the notification that was deactivated
             * @param array $form                                      The Form object
             */
			do_action( 'gform_pre_notification_deactivated', $form['notifications'][ $notification_id ], $form );
		}

		$result = GFFormsModel::update_form_meta( $form_id, $form['notifications'], 'notifications' );

		return $result;
	}

	public static function update_confirmation_active( $form_id, $confirmation_id, $is_active ) {
		$form = GFFormsModel::get_form_meta( $form_id );

		if ( ! isset( $form['confirmations'][ $confirmation_id ] ) ) {
			return new WP_Error( 'not_found', __( 'Notification not found', 'gravityforms' ) );
		}

		$form['confirmations'][ $confirmation_id ]['isActive'] = (bool) $is_active;



		$result = GFFormsModel::update_form_meta( $form_id, $form['confirmations'], 'confirmations' );

		return $result;
	}

	public static function update_forms_active( $forms, $is_active ) {
		foreach ( $forms as $form_id ) {
			self::update_form_active( $form_id, $is_active );
		}
	}

	public static function update_leads_property( $leads, $property_name, $property_value ) {
		self::update_entries_property( $leads, $property_name, $property_value );
	}

	public static function update_lead_property( $lead_id, $property_name, $property_value, $update_akismet = true, $disable_hook = false ) {
		return self::update_entry_property( $lead_id, $property_name, $property_value, $update_akismet, $disable_hook );
	}

	public static function update_entries_property( $leads, $property_name, $property_value ) {
		foreach ( $leads as $lead ) {
			self::update_entry_property( $lead, $property_name, $property_value );
		}
	}

	public static function update_entry_property( $lead_id, $property_name, $property_value, $update_akismet = true, $disable_hook = false ) {
		global $wpdb, $current_user;

		if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::update_lead_property( $lead_id, $property_name, $property_value, $update_akismet, $disable_hook );
		}

		$entry_table = self::get_entry_table_name();

		$lead = self::get_entry( $lead_id );

		//marking entry as 'spam' or 'not spam' with Akismet if the plugin is installed
		if ( $update_akismet && GFCommon::akismet_enabled( $lead['form_id'] ) && $property_name == 'status' && in_array( $property_value, array( 'active', 'spam' ) ) ) {

			$current_status = $lead['status'];
			if ( $current_status == 'spam' && $property_value == 'active' ) {
				$form = self::get_form_meta( $lead['form_id'] );
				GFCommon::mark_akismet_spam( $form, $lead, false );
			} else if ( $current_status == 'active' && $property_value == 'spam' ) {
				$form = self::get_form_meta( $lead['form_id'] );
				GFCommon::mark_akismet_spam( $form, $lead, true );
			}
		}

		// If property is trash, log user login
		if ( $property_name == 'status' && $property_value == 'trash' && ! empty( $current_user->user_login ) ) {
			GFCommon::log_debug( __METHOD__ . "(): User ID {$current_user->ID} requested moving of entry #{$lead_id} to trash." );
		}

		//updating lead
		$result = $wpdb->update( $entry_table, array( $property_name => $property_value ), array( 'id' => $lead_id ) );

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

				/**
				 * Fired after an entry property is updated.
				 *
				 * @param int    $lead_id        The Entry ID.
				 * @param string $property_name  The property that was updated.
				 * @param string $property_value The new value of the property that was updated.
				 * @param string $previous_value The previous property value before the update.
				 *
				 * @since 2.3.3.9
				 */
				do_action( "gform_post_update_entry_property", $lead_id, $property_name, $property_value, $previous_value );
			}
		}

		return $result;
	}

	private static function truncate( $str, $length ) {
		if ( strlen( $str ) > $length ) {
			$str = substr( $str, 0, $length );
		}

		return $str;
	}

	/**
	 *
	 * @param $leads
	 */
	public static function delete_leads( $leads ) {
		self::delete_entries( $leads );
	}

	public static function delete_entries( $entries ) {
		foreach ( $entries as $entry_id ) {
			self::delete_entry( $entry_id );
		}
	}

	public static function delete_forms( $forms ) {
		foreach ( $forms as $form_id ) {
			self::delete_form( $form_id );
		}
	}

	public static function trash_forms( $form_ids ) {
		foreach ( $form_ids as $form_id ) {
			self::trash_form( $form_id );
		}
	}

	public static function restore_forms( $form_ids ) {
		foreach ( $form_ids as $form_id ) {
			self::restore_form( $form_id );
		}
	}

	public static function delete_leads_by_form( $form_id, $status = '' ) {
		self::delete_entries_by_form( $form_id, $status );
	}

	public static function delete_entries_by_form( $form_id, $status = '' ) {
		global $wpdb, $current_user;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			GF_Forms_Model_Legacy::delete_leads_by_form( $form_id, $status );
			return;
		}

		$entry_table       = self::get_entry_table_name();
		$entry_notes_table = self::get_entry_notes_table_name();
		$entry_meta_table  = self::get_entry_meta_table_name();

		GFCommon::log_debug( __METHOD__ . "(): Deleting entries for form #{$form_id}." );

		/**
		 * Fires when you delete entries for a specific form
		 *
		 * @param int    $form_id The form ID to specify from which form to delete entries
		 * @param string $status  Allows you to set the form entries to a deleted status
		 */
		do_action( 'gform_delete_entries', $form_id, $status );

		// Get status filter.
		$status_filter = empty( $status ) ? '' : $wpdb->prepare( 'AND status=%s', $status );

		// Get entry IDs.
		$entry_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $entry_table WHERE form_id=%d {$status_filter}", $form_id ) );

		// If entries were found, loop through them and run action.
		if ( ! empty( $entry_ids ) ) {

		// Log user login for user requesting the deletion of entries
		if ( ! empty( $current_user->user_login ) ) {
			GFCommon::log_debug( __METHOD__ . "(): User ID {$current_user->ID} requested deletion of entries: " . json_encode( $entry_ids ) );
		}

			foreach ( $entry_ids as $entry_id ) {

				/**
				 * Fires before an entry is deleted.
				 *
				 * @param int $entry_id Entry ID to be deleted.
				 */
				do_action( 'gform_delete_entry', $entry_id );


				/**
				 * Fires before a lead is deleted
				 * @param $lead_id
				 * @deprecated Use gform_delete_entry instead
				 * @see gform_delete_entry
				 */
				do_action( 'gform_delete_lead', $entry_id );

			}

		}

		// Deleting uploaded files
		self::delete_files_by_form( $form_id, $status );
		// Delete from entry notes
		$sql = $wpdb->prepare(
			" DELETE FROM $entry_notes_table
                                WHERE entry_id IN (
                                    SELECT id FROM $entry_table WHERE form_id=%d {$status_filter}
                                )", $form_id
		);
		$wpdb->query( $sql );

		// Delete from entry meta
		$sql = $wpdb->prepare(
			" DELETE FROM $entry_meta_table
        						WHERE entry_id IN (
        							SELECT id FROM $entry_table WHERE form_id=%d {$status_filter}
                                )", $form_id
		);
		$wpdb->query( $sql );

		// Delete from entry
		$sql = $wpdb->prepare( "DELETE FROM $entry_table WHERE form_id=%d {$status_filter}", $form_id );
		$wpdb->query( $sql );
	}

	/**
	 * Delete the views for the specified form.
	 *
	 * @param int $form_id The form ID.
	 */
	public static function delete_views( $form_id ) {

		if ( gf_upgrade()->get_submissions_block() ) {
			return new WP_Error( 'submissions_blocked', __( 'Submissions are currently blocked due to an upgrade in progress', 'gravityforms' ) );
		}

		global $wpdb, $current_user;

		// Log user login for user requesting deletion of views
		if ( ! empty( $current_user->user_login ) ) {
			GFCommon::log_debug( __METHOD__ . "(): User ID {$current_user->ID} requested deletion of views for form #{$form_id}." );
		}

		$form_view_table = self::get_form_view_table_name();

		//Delete form view
		$sql = $wpdb->prepare( "DELETE FROM $form_view_table WHERE form_id=%d", $form_id );
		$wpdb->query( $sql );

		/**
         * Fires after form views are deleted
         *
         * @param int $form_id The ID of the form that views were deleted from
         */
		do_action( 'gform_post_form_views_deleted', $form_id );
	}

	public static function delete_form( $form_id ) {

		if ( gf_upgrade()->get_submissions_block() ) {
			return new WP_Error( 'submissions_blocked', __( 'Submissions are currently blocked due to an upgrade in progress', 'gravityforms' ) );
		}

		global $wpdb, $current_user;

		// Log user login for user requesting deletion of form
		if ( ! empty( $current_user->user_login ) ) {
			GFCommon::log_debug( __METHOD__ . "(): User ID {$current_user->ID} requested deletion of form #{$form_id}." );
		}

        /**
         * Fires before a form is deleted
         *
         * @param int $form_id The ID of the form being deleted
         */
		do_action( 'gform_before_delete_form', $form_id );

		$form_meta_table      = self::get_meta_table_name();
		$form_table           = self::get_form_table_name();
		$form_revisions_table = self::get_form_revisions_table_name();

		//Deleting form Entries
		self::delete_leads_by_form( $form_id );

		//Delete form meta
		$sql = $wpdb->prepare( "DELETE FROM $form_meta_table WHERE form_id=%d", $form_id );
		$wpdb->query( $sql );

		//Delete form revisions
		$sql = $wpdb->prepare( "DELETE FROM $form_revisions_table WHERE form_id=%d", $form_id );
		$wpdb->query( $sql );

		//Deleting form Views
		self::delete_views( $form_id );

		//Delete form
		$sql = $wpdb->prepare( "DELETE FROM $form_table WHERE id=%d", $form_id );
		$wpdb->query( $sql );

		// Prepare the cache key.
		$key = get_current_blog_id() . '_' . $form_id;

		// Remove the cached form.
		self::$_current_forms[ $key ] = null;

        /**
         * Fires after a form is deleted
         *
         * @param int $form_id The ID of the form that was deleted
         */
		do_action( 'gform_after_delete_form', $form_id );
	}

	public static function trash_form( $form_id ) {

		if ( gf_upgrade()->get_submissions_block() ) {
			return new WP_Error( 'submissions_blocked', __( 'Submissions are currently blocked due to an upgrade in progress', 'gravityforms' ) );
		}

		global $wpdb, $current_user;
		// Log user login for user moving the form to trash
		if ( ! empty( $current_user->user_login ) ) {
			GFCommon::log_debug( __METHOD__ . "(): User ID {$current_user->ID} requested moving of form #{$form_id} to trash." );
		}
		$form_table_name = self::get_form_table_name();
		$sql             = $wpdb->prepare( "UPDATE $form_table_name SET is_trash=1 WHERE id=%d", $form_id );
		$result          = $wpdb->query( $sql );

		$key = get_current_blog_id() . '_' . $form_id;
		self::$_current_forms[ $key ] = null;

		$success = $result == false;

		/**
		 * Fires after a form is trashed
		 *
		 * @since 1.9
		 *
		 * @param int $form_id The ID of the form that was trashed
		 */
		do_action( 'gform_post_form_trashed', $form_id );

		self::update_recent_forms( $form_id, true );

		return $success;
	}

	public static function restore_form( $form_id ) {

		if ( gf_upgrade()->get_submissions_block() ) {
			return new WP_Error( 'submissions_blocked', __( 'Submissions are currently blocked due to an upgrade in progress', 'gravityforms' ) );
		}

		global $wpdb;
		$form_table_name = self::get_form_table_name();
		$sql             = $wpdb->prepare( "UPDATE $form_table_name SET is_trash=0 WHERE id=%d", $form_id );
		$result          = $wpdb->query( $sql );

		$key = get_current_blog_id() . '_' . $form_id;

		self::$_current_forms[ $key ] = null;

		$success = $result == false;

        /**
         * Fires after a form is restored from trash
         *
         * @since 1.9
         *
         * @param int $form_id The ID of the form that was restored
         */
		do_action( 'gform_post_form_restored', $form_id );

		return $success;
	}

	/**
	 * Duplicate form.
	 *
	 * @access public
	 * @static
	 * @param  int $form_id Form ID to duplicate.
	 *
	 * @return int|WP_Error
	 */
	public static function duplicate_form( $form_id ) {

		if ( gf_upgrade()->get_submissions_block() ) {
			return new WP_Error( 'submissions_blocked', __( 'Submissions are currently blocked due to an upgrade in progress', 'gravityforms' ) );
		}

		// Get form to be duplicated.
		$form = self::get_form( $form_id );

		// Set initial form title.
		$title = $form->title;

		// Check for form count in form title.
		preg_match_all( '/(\\(([0-9])*\\))$/mi', $title, $count_exists_in_title );

		// If count does not exist, set count to 1.
		if ( empty( $count_exists_in_title[0] ) ) {

			// Set initial count.
			$count = 1;

		} else {

			// Set existing count to current count plus one.
			$count = (int) $count_exists_in_title[2][0] + 1;

			// Remove existing count from title.
			$title = preg_replace( '/(\\(([0-9])*\\))$/mi', null, $title );

		}

		// Trim title.
		$title = trim( $title );

		// Add copy count to form title.
		$new_title = $title . " ($count)";

		// If new form title is not unique, increment the count until a unique form title is created.
		while ( ! self::is_unique_title( $new_title ) ) {
			$count++;
			$new_title = $title . " ($count)";
		}

		// Create new form.
		$new_id = self::insert_form( $new_title );

		// Copying form meta to new form.
		$meta          = self::get_form_meta( $form_id );
		$meta['title'] = $new_title;
		$meta['id']    = $new_id;

		// Add notifications to new form.
		self::update_form_meta( $new_id, $meta['notifications'], 'notifications' );
		unset( $meta['notifications'] );

		// Add confirmations to new form.
		self::update_form_meta( $new_id, $meta['confirmations'], 'confirmations' );
		unset( $meta['confirmations'] );

		// Save form meta.
		self::update_form_meta( $new_id, $meta );

		// Set active state.
		self::update_form_active( $new_id, $form->is_active );

		// The gform_after_duplicate_form action is deprecated since version 1.9. Please use gform_post_form_duplicated instead

        /**
         * @deprecated
         * @see gform_post_form_duplicated
         */
        do_action( 'gform_after_duplicate_form', $form_id, $new_id );

		/**
		 * Fires after a form is duplicated
		 *
		 * @param int $form_id The original form's ID
		 * @param int $new_id  The ID of the new, duplicated form
		 */
		do_action( 'gform_post_form_duplicated', $form_id, $new_id );

		return $new_id;

	}


	public static function is_unique_title( $title, $form_id=0 ) {
		$forms = self::get_forms();
		foreach ( $forms as $form ) {
			if ( strtolower( $form->title ) === strtolower( $title ) && $form->id !== $form_id ) {
				return false;
			}
		}

		return true;
	}

	public static function ensure_tables_exist() {
		global $wpdb;
		$form_table_name = self::get_form_table_name();
		$form_count      = $wpdb->get_var( "SELECT count(0) FROM {$form_table_name}" );
		if ( $wpdb->last_error ) {
			GFCommon::log_debug( 'GFFormsModel::ensure_tables_exist(): Blog ' . get_current_blog_id() . ' - Form database table does not exist. Forcing database setup.' );
			gf_upgrade()->upgrade_schema();
		}
	}

	public static function insert_form( $form_title ) {
		global $wpdb;
		$form_table_name = self::get_form_table_name();

		//creating new form
		$wpdb->query( $wpdb->prepare( "INSERT INTO $form_table_name(title, date_created) VALUES(%s, utc_timestamp())", $form_title ) );

		//returning newly created form id
		return $wpdb->insert_id;

	}

	/**
	 * Update form meta.
	 *
	 * @since 2.4 Added the form revision creation functionality.
	 *
	 * @param int    $form_id Form id.
	 * @param array  $form_meta Form meta.
	 * @param string $meta_name Meta name.
	 *
	 * @return false|int Number of rows affected/selected or false on error.
	 */
	public static function update_form_meta( $form_id, $form_meta, $meta_name = 'display_meta' ) {
		global $wpdb;

		$form_meta = gf_apply_filters( array( 'gform_form_update_meta', $form_id ), $form_meta, $form_id, $meta_name );

		$meta_table_name = self::get_meta_table_name();
		$new_display_meta = $form_meta;
		$form_meta       = json_encode( $form_meta );

		if ( $meta_name === 'display_meta' ) {
			self::maybe_create_form_revision( $new_display_meta, $form_id );
		}

		if ( intval( $wpdb->get_var( $wpdb->prepare( "SELECT count(0) FROM $meta_table_name WHERE form_id=%d", $form_id ) ) ) > 0 ) {
			$result = $wpdb->query( $wpdb->prepare( "UPDATE $meta_table_name SET $meta_name=%s WHERE form_id=%d", $form_meta, $form_id ) );
		} else {
			$result = $wpdb->query( $wpdb->prepare( "INSERT INTO $meta_table_name(form_id, $meta_name) VALUES(%d, %s)", $form_id, $form_meta ) );
		}

		$key = get_current_blog_id() . '_' . $form_id;
		self::$_current_forms[ $key ] = null;
		if ( isset( self::$_confirmations[ $key ] ) ) {
			self::$_confirmations[ $key ] = null;
		}

		/**
		 * Fires after form meta has been updated for any form
		 *
		 * @param mixed  $form_meta The Form Meta object from the database
		 * @param int    $form_id   The ID of the form data was updated
		 * @param string $meta_name The name of the meta updated
		 */
		gf_do_action( array( 'gform_post_update_form_meta', $form_id ), $form_meta, $form_id, $meta_name );

		return $result;
	}

	/**
	 * Create form revision if conditions met.
	 *
	 * @since 2.4
	 *
	 * @param array $new_display_meta Form meta.
	 * @param int    $form_id Form ID.
	 */
	public static function maybe_create_form_revision( $new_display_meta, $form_id ) {
		global $wpdb;

		// Make sure the form isn't in the cache before calling get_form_meta().
		$key = get_current_blog_id() . '_' . $form_id;
		if ( isset( self::$_current_forms[ $key ] ) ) {
			unset( GFFormsModel::$_current_forms[ $key ] );
		}
		$form = self::get_form_meta( $form_id );
		// check if form has consent field.
		if ( GFCommon::has_consent_field( $new_display_meta ) ) {
			$revisions_table_name = self::get_form_revisions_table_name();

			// create the first revision.
			if ( intval( $wpdb->get_var( $wpdb->prepare( "SELECT count(0) FROM $revisions_table_name WHERE form_id=%d", $form_id ) ) ) === 0 ) {
				$wpdb->query( $wpdb->prepare( "INSERT INTO $revisions_table_name(form_id, display_meta, date_created) VALUES(%d, %s, utc_timestamp())", $form_id, json_encode( $new_display_meta ) ) );

				return;
			}

			$old_consent_fields = array();
			foreach ( $form['fields'] as $field ) {
				if ( $field->type === 'consent' ) {
					$old_consent_fields[ $field->id ] = $field->description;
				}
			}

			// check if consent field description changed.
			$create_revision = false;
			foreach ( $new_display_meta['fields'] as $field ) {
				if ( $field['type'] === 'consent' ) {
					if ( $field['description'] !== rgar( $old_consent_fields, $field['id'] ) ) {
						$create_revision = true;

						break;
					}
				}
			}

			if ( $create_revision ) {
				$wpdb->query( $wpdb->prepare( "INSERT INTO $revisions_table_name(form_id, display_meta, date_created) VALUES(%d, %s, utc_timestamp())", $form_id, json_encode( $new_display_meta ) ) );
			}
		}
	}

	/**
	 * Get the latest revision ID from form revisions.
	 *
	 * @since 2.4
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return int Revision ID.
	 */
	public static function get_latest_form_revisions_id( $form_id ) {
		global $wpdb;
		$revisions_table_name = GFFormsModel::get_form_revisions_table_name();
		$value                = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $revisions_table_name WHERE form_id=%d ORDER BY date_created DESC, id DESC LIMIT 1", $form_id ) );

		return $value;
	}

	public static function delete_files( $lead_id, $form = null ) {
		$lead = self::get_lead( $lead_id );

		if ( $form == null ) {
			$form = self::get_form_meta( $lead['form_id'] );
		}

		$field_types = self::get_delete_file_field_types( $form );
		$fields      = self::get_fields_by_type( $form, $field_types );

		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {

				if ( $field->multipleFiles ) {
					$value_json = self::get_lead_field_value( $lead, $field );
					if ( ! empty( $value_json ) ) {
						$files = json_decode( $value_json, true );
						if ( false === empty( $files ) && is_array( $files ) ) {
							foreach ( $files as $file ) {
								self::delete_physical_file( $file );
							}
						}
					}
				} else {
					$value = self::get_lead_field_value( $lead, $field );
					self::delete_physical_file( $value );
				}
			}
		}
	}

	public static function delete_files_by_form( $form_id, $status = '' ) {
		global $wpdb;

		$entry_table_name = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_lead_table_name() : self::get_entry_table_name();

		$form        = self::get_form_meta( $form_id );
		$field_types = self::get_delete_file_field_types( $form );
		$fields      = self::get_fields_by_type( $form, $field_types );

		if ( empty( $fields ) ) {
			return;
		}

		$status_filter = empty( $status ) ? '' : $wpdb->prepare( 'AND status=%s', $status );
		$results       = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$entry_table_name} WHERE form_id=%d {$status_filter}", $form_id ), ARRAY_A );

		foreach ( $results as $result ) {
			self::delete_files( $result['id'], $form );
		}
	}

	/**
	 * Returns an array of field types for which can uploaded files can be deleted.
	 *
	 * @since 2.4.6.1
	 *
	 * @param array $form The current form.
	 *
	 * @return array
	 */
	public static function get_delete_file_field_types( $form ) {
		$field_types = array( 'fileupload', 'post_image' );

		/**
		 * Allows more files to be deleted
		 *
		 * @since 1.9.10
		 *
		 * @param array $field_types Field types which contain file uploads
		 * @param array $form The Form Object
		 */
		return gf_apply_filters( array( 'gform_field_types_delete_files', $form['id'] ), $field_types, $form );
	}

	/**
	 * Deletes the uploaded files for the specified form and field.
	 *
	 * Note: Does not delete the file URLs from the entries, that is done by GFFormsModel::delete_field_values().
	 *
	 * @since 2.4.6.1
	 *
	 * @param int $form_id The current form ID.
	 * @param int $field_id The ID of field being deleted.
	 */
	public static function delete_field_files( $form_id, $field_id ) {
		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return;
		}

		global $wpdb;

		$entry_meta_table_name = self::get_entry_meta_table_name();

		$values = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$entry_meta_table_name} WHERE form_id=%d AND meta_key=%s", $form_id, $field_id ) );

		if ( is_array( $values ) ) {
			foreach ( $values as $value ) {
				if ( empty( $value ) ) {
					continue;
				}

				if ( $value[0] == '[' ) {
					// Value from a multi-file enabled field.
					$files = json_decode( $value );
					if ( is_array( $files ) ) {
						foreach ( $files as $file ) {
							self::delete_physical_file( $file );
						}
					}
				} else {
					// Value from a single file or post image field.
					self::delete_physical_file( $value );
				}
			}
		}
	}

	public static function delete_file( $entry_id, $field_id, $file_index = 0 ) {
		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			GF_Forms_Model_Legacy::delete_file( $entry_id, $field_id, $file_index );
			return;
		}


		if ( $entry_id == 0 || $field_id == 0 ) {
			return;
		}

		$entry          = self::get_lead( $entry_id );
		$form_id        = $entry['form_id'];
		$form           = self::get_form_meta( $form_id );
		$field          = self::get_field( $form, $field_id );
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

		// Update entry field value - simulate form submission.
		$entry_meta_table_name = self::get_entry_meta_table_name();
		$sql                   = $wpdb->prepare( "SELECT id FROM {$entry_meta_table_name} WHERE entry_id=%d AND meta_key = %s", $entry_id, $field_id );
		$entry_meta_id         = $wpdb->get_var( $sql );

		self::update_entry_field_value( $form, $entry, $field, $entry_meta_id, $field_id, $field_value );

	}

	private static function delete_physical_file( $file_url ) {
		$ary = explode( '|:|', $file_url );
		$url = rgar( $ary, 0 );
		if ( empty( $url ) ) {
			return;
		}

		$file_path = self::get_physical_file_path( $url );

		/**
		 * Allow the file path to be overridden so files stored outside the /wp-content/uploads/gravity_forms/ directory can be deleted.
		 *
		 * @since 2.2.3.1
		 *
		 * @param string $file_path The path of the file to be deleted.
		 * @param string $url       The URL of the file to be deleted.
		 */
		$file_path = apply_filters( 'gform_file_path_pre_delete_file', $file_path, $url );

		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}

	public static function get_physical_file_path( $url ) {

		// convert from url to physical path
		if ( is_multisite() && get_site_option( 'ms_files_rewriting' ) ) {
			$file_path = preg_replace( "|^(.*?)/files/gravity_forms/|", BLOGUPLOADDIR . 'gravity_forms/', $url );
		} else {
			$file_path = str_replace( self::get_upload_url_root(), self::get_upload_root(), $url );
		}

		return $file_path;
	}

	public static function delete_field( $form_or_id, $field_id, $save_form = true ) {

		$form = is_numeric( $form_or_id ) ? self::get_form_meta( $form_or_id ) : $form_or_id;

		if ( empty( $form['id'] ) || ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return null;
		}

		$form_id = $form['id'];

        /**
         * Fires before a field is deleted
         *
         * @param int $form_id  The ID of the form that the field is being deleted from
         * @param int $field_id The ID of the field being deleted
         */
		do_action( 'gform_before_delete_field', $form_id, $field_id );

		$field_type = '';

		$count = sizeof( $form['fields'] );
		for ( $i = $count - 1; $i >= 0; $i -- ) {
			/** @var GF_Field $field */
			$field = $form['fields'][ $i ];

			// Deleting associated conditional logic rules.
			if ( ! empty( $field->conditionalLogic ) ) {
				$field->conditionalLogic = self::delete_field_from_conditional_logic( $field->conditionalLogic, $field_id );
			}

			if ( $field->type === 'page' && ! empty( $field->nextButton['conditionalLogic'] ) ) {
				$field->nextButton['conditionalLogic'] = self::delete_field_from_conditional_logic( $field->nextButton['conditionalLogic'], $field_id );
			}

			// Deleting field from form meta.
			if ( $field->id == $field_id ) {
				$field_type = $field->type;
				unset( $form['fields'][ $i ] );
			}
		}

		// The field has already been removed from the form passed by GFFormDetail::save_form_info(), get the field from the db.
		if ( empty( $field_type ) && $deleted_field = self::get_field( $form_id, $field_id ) ) {
			$field_type = $deleted_field->type;
		}

		// Removing post content and title template if the field being deleted is a post content field or post title field.
		if ( $field_type == 'post_content' ) {
			$form['postContentTemplateEnabled'] = false;
			$form['postContentTemplate']        = '';
		} else if ( $field_type == 'post_title' ) {
			$form['postTitleTemplateEnabled'] = false;
			$form['postTitleTemplate']        = '';
		}

		if ( ! empty( $form['button']['conditionalLogic'] ) ) {
			$form['button']['conditionalLogic'] = self::delete_field_from_conditional_logic( $form['button']['conditionalLogic'], $field_id );
		}

		// Notifications/confirmations are not present in the form passed by GFFormDetail::save_form_info() but they could be present in other scenarios.
		$form = GFFormsModel::delete_field_from_confirmations( $form, $field_id );
		$form = GFFormsModel::delete_field_from_notifications( $form, $field_id );

		if ( in_array( $field_type, self::get_delete_file_field_types( $form ) ) ) {
			self::delete_field_files( $form_id, $field_id );
		}

		$form['fields'] = array_values( $form['fields'] );

		if ( $save_form ) {
			self::update_form_meta( $form_id, $form );
		}

		//Delete from grid column meta
		$columns = self::get_grid_column_meta( $form_id );
		if ( is_array( $columns ) ) {
			$count = sizeof( $columns );
			for ( $i = $count - 1; $i >= 0; $i -- ) {
				if ( intval( rgar( $columns, $i ) ) == intval( $field_id ) ) {
					unset( $columns[ $i ] );
				}
			}
			self::update_grid_column_meta( $form_id, $columns );
		}

		self::delete_field_values( $form_id, $field_id );

		/**
		 * Fires after a field is deleted
		 *
		 * @param int $form_id  The form ID where the form was deleted
		 * @param int $field_id The ID of the field that was deleted
         *
		 */
		do_action( 'gform_after_delete_field', $form_id, $field_id );

		return $form;
	}

	/**
	 * Deletes confirmation conditional logic rules based on the deleted field.
	 *
	 * @since 2.4.6.1
	 *
	 * @param array $form     The form containing the confirmations to be processed.
	 * @param int   $field_id The ID of the field being deleted.
	 *
	 * @return array
	 */
	public static function delete_field_from_confirmations( $form, $field_id ) {
		if ( empty( $form['confirmations'] ) ) {
			return $form;
		}

		$save = false;

		foreach ( $form['confirmations'] as &$confirmation ) {
			if ( ! empty( $confirmation['conditionalLogic'] ) ) {
				$processed = self::delete_field_from_conditional_logic( $confirmation['conditionalLogic'], $field_id );
				if ( $confirmation['conditionalLogic'] != $processed ) {
					$confirmation['conditionalLogic'] = $processed;
					$save                             = true;
				}
			}
		}

		if ( $save ) {
			GFFormsModel::update_form_meta( $form['id'], $form['confirmations'], 'confirmations' );
		}

		return $form;
	}

	/**
	 * Deletes notification routing and conditional logic rules based on the deleted field.
	 *
	 * @since 2.4.6.1
	 *
	 * @param array $form     The form containing the notifications to be processed.
	 * @param int   $field_id The ID of the field being deleted.
	 *
	 * @return array
	 */
	public static function delete_field_from_notifications( $form, $field_id ) {
		if ( empty( $form['notifications'] ) ) {
			return $form;
		}

		$save = false;

		foreach ( $form['notifications'] as &$notification ) {
			if ( ! empty( $notification['routing'] ) ) {
				$dirty = false;

				foreach ( $notification['routing'] as $key => $rule ) {
					if ( intval( rgar( $rule, 'fieldId' ) ) == $field_id ) {
						unset( $notification['routing'][ $key ] );
						$dirty = true;
					}
				}

				if ( $dirty ) {
					$notification['routing'] = empty( $notification['routing'] ) ? null : array_values( $notification['routing'] );
					$save                    = true;
				}
			}

			if ( ! empty( $notification['conditionalLogic'] ) ) {
				$processed = self::delete_field_from_conditional_logic( $notification['conditionalLogic'], $field_id );
				if ( $notification['conditionalLogic'] != $processed ) {
					$notification['conditionalLogic'] = $processed;
					$save                             = true;
				}
			}
		}

		if ( $save ) {
			GFFormsModel::update_form_meta( $form['id'], $form['notifications'], 'notifications' );
		}

		return $form;
	}

	/**
	 * Deletes conditional logic rules based on the deleted field.
	 *
	 * If no rules remain following the deletion conditional logic is disabled.
	 *
	 * @since 2.4.6.1
	 *
	 * @param array $logic    The conditional logic object to be processed.
	 * @param int   $field_id The ID of the field being deleted.
	 *
	 * @return null|array
	 */
	public static function delete_field_from_conditional_logic( $logic, $field_id ) {
		if ( empty( $logic['rules'] ) ) {
			return null;
		}

		$dirty = false;

		foreach ( $logic['rules'] as $key => $rule ) {
			if ( intval( rgar( $rule, 'fieldId' ) ) == $field_id ) {
				unset( $logic['rules'][ $key ] );
				$dirty = true;
			}
		}

		if ( $dirty ) {
			if ( empty( $logic['rules'] ) ) {
				$logic = null;
			} else {
				$logic['rules'] = array_values( $logic['rules'] );
			}
		}

		return $logic;
	}

	public static function delete_field_values( $form_id, $field_id ) {
		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			GF_Forms_Model_Legacy::delete_field_values( $form_id, $field_id );
			return;
		}

		$entry_table      = self::get_entry_table_name();
		$entry_meta_table = self::get_entry_meta_table_name();

		// Delete from entry meta
		$sql = $wpdb->prepare( "DELETE FROM $entry_meta_table WHERE form_id=%d AND meta_key = %s", $form_id, $field_id );
		if ( is_numeric( $field_id ) ) {
			$sql .= $wpdb->prepare( " OR form_id=%d AND meta_key LIKE %s", $form_id, sprintf( '%d.%%', $field_id ) );
		}
		$wpdb->query( $sql );

		// Delete leads with no details
		$sql = $wpdb->prepare(
			" DELETE FROM $entry_table
	            WHERE form_id=%d
	            AND id NOT IN(
	                SELECT DISTINCT(entry_id) FROM $entry_meta_table WHERE form_id=%d
	            )", $form_id, $form_id
		);
		$wpdb->query( $sql );
	}

	/**
	 * Deletes a lead.
	 *
	 * @param $lead_id
	 */
	public static function delete_lead( $lead_id ) {
		self::delete_entry( $lead_id );
	}

	public static function delete_entry( $entry_id ) {
		global $wpdb, $current_user;

		// Log if user requested deletion of entries
		if ( ! empty( $current_user->user_login ) ) {
			GFCommon::log_debug( __METHOD__ . "(): User ID {$current_user->ID} requested deletion of entry #{$entry_id}" );
		}

		if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
			GF_Forms_Model_Legacy::delete_lead( $entry_id );
			return;
		}

		GFCommon::log_debug( __METHOD__ . "(): Deleting entry #{$entry_id}." );

		/**
		 * Fires before an entry is deleted.
		 *
		 * @param $entry_id
		 */
		do_action( 'gform_delete_entry', $entry_id );

		/**
		 * Fires before a lead is deleted
		 * @param $lead_id
		 * @deprecated Use gform_delete_entry instead
		 * @see gform_delete_entry
		 */
		do_action( 'gform_delete_lead', $entry_id );


		$entry_table             = self::get_entry_table_name();
		$entry_notes_table       = self::get_entry_notes_table_name();
		$entry_meta_table_name = self::get_entry_meta_table_name();

		// Deleting uploaded files
		self::delete_files( $entry_id );

		// Delete from entry meta
		$sql = $wpdb->prepare( "DELETE FROM $entry_meta_table_name WHERE entry_id=%d", $entry_id );
		$wpdb->query( $sql );

		// Delete from lead notes
		$sql = $wpdb->prepare( "DELETE FROM $entry_notes_table WHERE entry_id=%d", $entry_id );
		$wpdb->query( $sql );


		// Delete from entry table
		$sql = $wpdb->prepare( "DELETE FROM $entry_table WHERE id=%d", $entry_id );
		$wpdb->query( $sql );
	}

	public static function add_note( $entry_id, $user_id, $user_name, $note, $note_type = 'note' ) {
		global $wpdb;

		if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
			GF_Forms_Model_Legacy::add_note( $entry_id, $user_id, $user_name, $note, $note_type );
			return;
		}

		$table_name = self::get_entry_notes_table_name();
		$sql        = $wpdb->prepare( "INSERT INTO $table_name(entry_id, user_id, user_name, value, note_type, date_created) values(%d, %d, %s, %s, %s, utc_timestamp())", $entry_id, $user_id, $user_name, $note, $note_type );

		$wpdb->query( $sql );

		/**
		 * Fires after a note has been added to an entry
		 *
		 * @param int    $wpdb->insert_id The row ID of this note in the database
		 * @param int    $entry_id         The ID of the entry that the note was added to
		 * @param int    $user_id         The ID of the current user adding the note
		 * @param string $user_name       The user name of the current user
		 * @param string $note            The content of the note being added
		 * @param string $note_type       The type of note being added.  Defaults to 'note'
		 */
		do_action( 'gform_post_note_added', $wpdb->insert_id, $entry_id, $user_id, $user_name, $note, $note_type );
	}

	public static function delete_note( $note_id ) {
		global $wpdb;

		if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
			GF_Forms_Model_Legacy::delete_note( $note_id );
			return;
		}

		$table_name = self::get_entry_notes_table_name();

		$lead_id = $wpdb->get_var( $wpdb->prepare( "SELECT entry_id FROM $table_name WHERE id = %d", $note_id ) );

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

	public static function delete_notes( $notes ) {
		if ( ! is_array( $notes ) ) {
			return;
		}

		foreach ( $notes as $note_id ) {
			self::delete_note( $note_id );
		}
	}

	/**
	 * Gets the IP to be used within the entry.
	 *
	 * @since 2.2 Using $_SERVER['REMOTE_ADDR'].
	 *
	 * @return string The IP to be stored in the entry.
	 */
	public static function get_ip() {

		$ip = rgar( $_SERVER, 'REMOTE_ADDR' );

		/**
		 * Allows the IP address of the client to be modified.
		 *
		 * Use this filter if the server is behind a proxy.
		 *
		 * @since 2.2
		 * @example https://docs.gravityforms.com/gform_ip_address/
		 *
		 * @param string $ip The IP being used.
		 */
		$ip = apply_filters( 'gform_ip_address', $ip );

		// HTTP_X_FORWARDED_FOR can return a comma separated list of IPs; use the first one
		$ips = explode( ',', $ip );

		return $ips[0];
	}

	public static function save_lead( $form, &$entry ) {
		self::save_entry( $form, $entry );
	}

	/**
	 * Save Entry to database.
	 *
	 * @since 2.4.8.13 Updated created_by property to save as an empty value when undefined.
	 * @since Unknown
	 *
	 * @param array $form  Form object.
	 * @param array $entry Entry object.
	 */
	public static function save_entry( $form, &$entry ) {
		global $wpdb, $current_user;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			GF_Forms_Model_Legacy::save_lead( $form, $entry );
			$entry = GFAPI::get_entry( $entry['id'] );
			return;
		}

		GFCommon::log_debug( __METHOD__ . '(): Saving entry.' );

		$is_form_editor  = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		if ( $is_admin && ! GFCommon::current_user_can_any( 'gravityforms_edit_entries' ) ) {
			wp_die( esc_html__( "You don't have adequate permission to edit entries.", 'gravityforms' ) );
		}

		$entry_meta_table = self::get_entry_meta_table_name();
		$is_new_lead      = empty( $entry );

		// Log user login for user updating the entry
		if ( ! $is_new_lead && ! empty( $entry['id'] ) && ! empty( $current_user->ID ) ) {
			GFCommon::log_debug( __METHOD__ . "(): User ID {$current_user->ID} requested update of entry #{$entry['id']}." );
		}

		if ( ! $is_new_lead && ! self::entry_exists( rgar( $entry, 'id' ) ) ) {
			// Force a new entry to be saved when an entry does not exist for the supplied id.
			$entry       = array();
			$is_new_lead = true;
		}

		$die_message = esc_html__( 'An error prevented the entry for this form submission being saved. Please contact support.', 'gravityforms' );

		$entry_table = GFFormsModel::get_entry_table_name();

		$current_date = $wpdb->get_var( 'SELECT utc_timestamp()' );

		if ( $is_new_lead ) {
			// Saving the new entry.

			$user_id = $current_user && $current_user->ID ? $current_user->ID : null;

			$user_agent = self::truncate( rgar( $_SERVER, 'HTTP_USER_AGENT' ), 250 );
			$user_agent = sanitize_text_field( $user_agent );
			$source_url = self::truncate( self::get_current_page_url(), 200 );

			/**
			 * Allow the currency code to be overridden.
			 *
			 * @param string $currency The three character ISO currency code to be stored in the entry. Default is value returned by GFCommon::get_currency()
			 * @param array $form The form currently being processed.
			 *
			 */
			$currency = gf_apply_filters( array( 'gform_currency_pre_save_entry', $form['id'] ), GFCommon::get_currency(), $form );

			$ip = rgars( $form, 'personalData/preventIP' ) ? '' : self::get_ip();

			$wpdb->insert(
				$entry_table,
				array(
					'form_id'      => $form['id'],
					'ip'           => $ip,
					'source_url'   => $source_url,
					'date_created' => $current_date,
					'date_updated' => $current_date,
					'user_agent'   => $user_agent,
					'currency'     => $currency,
					'created_by'   => $user_id,
				),
				array(
					'form_id'      => '%d',
					'ip'           => '%s',
					'source_url'   => '%s',
					'date_created' => '%s',
					'date_updated' => '%s',
					'user_agent'   => '%s',
					'currency'     => '%s',
					'created_by'   => '%s',
				)
			);

			// Reading newly created lead id
			$lead_id = $wpdb->insert_id;

			if ( $lead_id == 0 ) {
				GFCommon::log_error( __METHOD__ . '(): Unable to save entry. ' . $wpdb->last_error );
				wp_die( $die_message );
			}

			$entry = array(
				'id'               => (string) $lead_id,
				'status'           => 'active',
				'form_id'          => (string) $form['id'],
				'ip'               => $ip,
				'source_url'       => $source_url,
				'currency'         => $currency,
				'post_id'          => null,
				'date_created'     => $current_date,
				'date_updated'     => $current_date,
				'is_starred'       => 0,
				'is_read'          => 0,
				'user_agent'       => $user_agent,
				'payment_status'   => null,
				'payment_date'     => null,
				'payment_amount'   => null,
				'payment_method'   => '',
				'transaction_id'   => null,
				'is_fulfilled'     => null,
				'created_by'       => (string) $user_id,
				'transaction_type' => null,
			);

			GFCommon::log_debug( __METHOD__ . "(): Entry record created in the database. ID: {$lead_id}." );
		} else {
			GFCommon::log_debug( __METHOD__ . "(): Updating existing entry. ID: {$entry['id']}." );

			// Ensures the entry being updated contains all the current properties and registered meta.
			self::add_properties_to_entry( $entry );
			self::add_meta_to_entry( $entry );

			GFAPI::update_entry_property( $entry['id'], 'date_updated', $current_date );
			$entry['date_updated'] = $current_date;
		}

		$current_fields = $wpdb->get_results( $wpdb->prepare( "SELECT id, meta_key, item_index FROM $entry_meta_table WHERE entry_id=%d", $entry['id'] ) );

		$total_fields = array();
		/* @var $calculation_fields GF_Field[] */
		$calculation_fields = array();
		$recalculate_total  = false;

		GFCommon::log_debug( __METHOD__ . '(): Saving entry fields.' );

		GFFormsModel::begin_batch_field_operations();

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

			/**
			 * Specify whether to fetch values from the $_POST when evaluating a field's conditional logic. Defaults to true
			 * for new entries and false for existing entries.
			 *
			 * @since 2.3.1.11
			 *
			 * @param bool  $read_value_from_post Should value be fetched from $_POST?
			 * @param array $form                The current form object.
			 * @param array $entry               The current entry object.
			 */
			$read_value_from_post = gf_apply_filters( array( 'gform_use_post_value_for_conditional_logic_save_entry', $form['id'] ), $is_new_lead || ! isset( $entry[ 'date_created' ] ), $form, $entry );

			// Only save fields that are not hidden (except when updating an entry)
			if ( $is_entry_detail || ! GFFormsModel::is_field_hidden( $form, $field, array(), $read_value_from_post ? null : $entry ) ) {

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
						self::save_input( $form, $field, $entry, $current_fields, $input['id'] );
					}
				} else {
					self::save_input( $form, $field, $entry, $current_fields, $field->id );
				}
			}
		}

		$results = GFFormsModel::commit_batch_field_operations();

		if ( $is_new_lead && is_wp_error( $results['inserts'] ) ) {
			/* @var WP_Error $error */
			$error = $results['inserts'];
			GFCommon::log_error( __METHOD__ . '(): Error while saving field values for new entry. ' . $error->get_error_message() );
			wp_die( $die_message );
		}

		if ( ! empty( $calculation_fields ) ) {
			GFFormsModel::begin_batch_field_operations();
			foreach ( $calculation_fields as $calculation_field ) {
				$inputs = $calculation_field->get_entry_inputs();
				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {
						self::save_input( $form, $calculation_field, $entry, $current_fields, $input['id'] );
					}
				} else {
					self::save_input( $form, $calculation_field, $entry, $current_fields, $calculation_field->id );
				}
			}
			$results = GFFormsModel::commit_batch_field_operations();

			if ( $is_new_lead && is_wp_error( $results['inserts'] ) ) {
				/* @var WP_Error $error */
				$error = $results['inserts'];
				GFCommon::log_error( __METHOD__ . '(): Error while saving calculation field values for new entry. ' . $error->get_error_message() );
				wp_die( $die_message );
			}

			self::refresh_product_cache( $form, $entry );
		}

		//saving total field as the last field of the form.
		if ( ! empty( $total_fields ) ) {
			GFFormsModel::begin_batch_field_operations();
			foreach ( $total_fields as $total_field ) {
				self::save_input( $form, $total_field, $entry, $current_fields, $total_field->id );
			}
			$results = GFFormsModel::commit_batch_field_operations();

			if ( $is_new_lead && is_wp_error( $results['inserts'] ) ) {
				/* @var WP_Error $error */
				$error = $results['inserts'];
				GFCommon::log_error( __METHOD__ . '(): Error while saving total field values for new entry. ' . $error->get_error_message() );
				wp_die( $die_message );
			}
		}

		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */

			if ( $field->displayOnly ) {
				continue;
			}

			$inputs = $field->get_entry_inputs();

			if ( is_array( $inputs ) ) {
				foreach ( $inputs as $input ) {
					$entry[ (string) $input['id'] ] = gf_apply_filters( array( 'gform_get_input_value', $form['id'], $field->id, $input['id'] ), rgar( $entry, (string) $input['id'] ), $entry, $field, $input['id'] );
				}
			} else {

				$value = rgar( $entry, (string) $field->id );

				if ( GFFormsModel::is_openssl_encrypted_field( $entry['id'], $field->id ) ) {
					$value = GFCommon::openssl_decrypt( $value );
				}

				$entry[ (string) $field->id ] = gf_apply_filters( array( 'gform_get_input_value', $form['id'], $field->id ), $value, $entry, $field, '' );

			}
		}

		self::hydrate_repeaters( $entry, $form );

		GFCommon::log_debug( __METHOD__ . '(): Finished saving entry fields.' );
	}

	/**
	 * Populates the supplied entry with missing properties.
	 *
	 * @since 2.4.5.8
	 *
	 * @param array $entry The partial or complete entry currently being updated.
	 */
	public static function add_properties_to_entry( &$entry ) {
		if ( empty( $entry['id'] ) ) {
			return;
		}

		global $wpdb;
		$entry_table = GFFormsModel::get_entry_table_name();
		$sql         = $wpdb->prepare( "SELECT * FROM $entry_table WHERE id=%d", $entry['id'] );
		$properties  = $wpdb->get_row( $sql, ARRAY_A );

		foreach ( $properties as $key => $property ) {
			if ( ! isset( $entry[ (string) $key ] ) ) {
				// Add the missing entry property.
				$entry[ (string) $key ] = $properties[ $key ];
			}
		}
	}

	/**
	 * Populates the supplied entry with missing meta.
	 *
	 * @since 2.4.5.8
	 *
	 * @param array $entry The partial or complete entry currently being updated.
	 */
	public static function add_meta_to_entry( &$entry ) {
		if ( empty( $entry['id'] ) || empty( $entry['form_id'] ) ) {
			return;
		}

		$meta_keys = array_keys( self::get_entry_meta( $entry['form_id'] ) );

		foreach ( $meta_keys as $meta_key ) {
			if ( ! isset( $entry[ $meta_key ] ) ) {
				// Add the missing entry meta.
				$entry[ $meta_key ] = gform_get_meta( $entry['id'], $meta_key );
			}
		}
	}

	public static function hydrate_repeaters( &$entry, $form ) {
		$fields = $form['fields'];
		foreach( $fields as $field ) {
			if ( $field instanceof GF_Field_Repeater && isset( $field->fields ) && is_array( $field->fields ) ) {
				/* @var GF_Field_Repeater $field */
				$entry = $field->hydrate( $entry, $form );
			}
		}
	}

	public static function create_lead( $form ) {
		global $current_user;

		$total_fields       = array();
		$calculation_fields = array();

		$lead                 = array();
		$lead['id']           = null;
		$lead['post_id']      = null;
		$lead['date_created'] = null;
		$lead['date_updated'] = null;
		$lead['form_id']      = $form['id'];
		$lead['ip']           = rgars( $form, 'personalData/preventIP' ) ? '' : self::get_ip();
		$source_url           = self::truncate( self::get_current_page_url(), 200 );
		$lead['source_url']   = esc_url_raw( $source_url );
		$user_agent           = self::truncate( rgar( $_SERVER, 'HTTP_USER_AGENT' ), 250 );
		$lead['user_agent']   = sanitize_text_field( $user_agent );
		$lead['created_by']   = $current_user && $current_user->ID ? $current_user->ID : 'NULL';

		/**
		 * Allow the currency code to be overridden.
		 *
		 * @param string $currency The three character ISO currency code to be stored in the entry. Default is value returned by GFCommon::get_currency()
		 * @param array $form The form currently being processed.
		 *
		 */
		$lead['currency'] = gf_apply_filters( array( 'gform_currency_pre_save_entry', $form['id'] ), GFCommon::get_currency(), $form );

		foreach ( $form['fields'] as $field ) {
			/* @var $field GF_Field */

			// ignore fields that are marked as display only
			if ( $field->displayOnly && $field->type != 'password' ) {
				continue;
			}

			// process total field after all fields have been saved
			if ( $field->type == 'total' ) {
				$total_fields[] = $field;
				continue;
			}

			// process calculation fields after all fields have been saved
			if ( $field->has_calculation() ) {
				$calculation_fields[] = $field;
				continue;
			}

			// only save fields that are not hidden
			if ( ! RGFormsModel::is_field_hidden( $form, $field, array() ) ) {

				if ( $field->type == 'post_category' ) {
					$field = GFCommon::add_categories_as_choices( $field, '' );
				}

				$inputs = $field->get_entry_inputs();

				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {
						$lead[ (string) $input['id'] ] = self::get_prepared_input_value( $form, $field, $lead, $input['id'] );
					}
				} else {
					$lead[ $field->id ] = self::get_prepared_input_value( $form, $field, $lead, $field->id );
				}
			}
		}

		if ( ! empty( $calculation_fields ) ) {
			foreach ( $calculation_fields as $field ) {
				/* @var $field GF_Field */

				// only save fields that are not hidden
				if ( RGFormsModel::is_field_hidden( $form, $field, array() ) ) {
					continue;
				}

				$inputs = $field->get_entry_inputs();

				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {
						$lead[ (string) $input['id'] ] = self::get_prepared_input_value( $form, $field, $lead, $input['id'] );
					}
				} else {
					$lead[ $field->id ] = self::get_prepared_input_value( $form, $field, $lead, $field->id );
				}
			}
			self::refresh_product_cache( $form, $lead );
		}

		// saving total field as the last field of the form.
		if ( ! empty( $total_fields ) ) {
			foreach ( $total_fields as $total_field ) {
				$lead[ $total_field->id ] = self::get_prepared_input_value( $form, $total_field, $lead, $total_field->id );
			}
		}

		return $lead;
	}

	public static function get_prepared_input_value( $form, $field, $lead, $input_id ) {

		$input_name = 'input_' . str_replace( '.', '_', $input_id );
		if ( $field->enableCopyValuesOption && rgpost( 'input_' . $field->id . '_copy_values_activated' ) ) {
			$source_field_id   = $field->copyValuesOptionField;
			$source_input_name = str_replace( 'input_' . $field->id, 'input_' . $source_field_id, $input_name );
			$value             = rgpost( $source_input_name );
		} else {
			$value = rgpost( $input_name );
		}

		$is_form_editor = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin = $is_form_editor || $is_entry_detail;

		if ( empty( $value ) && $field->is_administrative() && ! $is_admin ) {
			$value = self::get_default_value( $field, $input_id );
		}

		switch ( self::get_input_type( $field ) ) {

			case 'post_image':
				$file_info = self::get_temp_filename( $form['id'], $input_name );
				if ( ! empty( $file_info ) ) {
					$file_path = self::get_file_upload_path( $form['id'], $file_info['uploaded_filename'] );
					$url       = $file_path['url'];

					$image_title       = isset( $_POST[ "{$input_name}_1" ] ) ? strip_tags( $_POST[ "{$input_name}_1" ] ) : '';
					$image_caption     = isset( $_POST[ "{$input_name}_4" ] ) ? strip_tags( $_POST[ "{$input_name}_4" ] ) : '';
					$image_description = isset( $_POST[ "{$input_name}_7" ] ) ? strip_tags( $_POST[ "{$input_name}_7" ] ) : '';

					$value = ! empty( $url ) ? $url . '|:|' . $image_title . '|:|' . $image_caption . '|:|' . $image_description : '';
				}
				break;

			case 'fileupload' :
				if ( $field->multipleFiles ) {
					if ( ! empty( $value ) ) {
						$value = json_encode( $value );
					}
				} else {
					$file_info = self::get_temp_filename( $form['id'], $input_name );
					if ( ! empty( $file_info ) ) {
						$file_path = self::get_file_upload_path( $form['id'], $file_info['uploaded_filename'] );
						$value     = $file_path['url'];
					}
				}

				break;

			default:

				// processing values so that they are in the correct format for each input type
				$value = self::prepare_value( $form, $field, $value, $input_name, rgar( $lead, 'id' ), $lead );

		}

		return gf_apply_filters( array( 'gform_save_field_value', $form['id'], $field->id ), $value, $lead, $field, $form, $input_id );
	}

	public static function refresh_product_cache( $form, $lead, $use_choice_text = false, $use_admin_label = false ) {

		$cache_options = array(
			array( false, false ),
			array( false, true ),
			array( true, false ),
			array( true, true ),
		);

		foreach ( $cache_options as $cache_option ) {
			list( $use_choice_text, $use_admin_label ) = $cache_option;
			if ( gform_get_meta( rgar( $lead, 'id' ), "gform_product_info_{$use_choice_text}_{$use_admin_label}" ) ) {
				gform_delete_meta( rgar( $lead, 'id' ), "gform_product_info_{$use_choice_text}_{$use_admin_label}" );
				GFCommon::get_product_fields( $form, $lead, $use_choice_text, $use_admin_label );
			}
		}

	}

	/**
	 * Check whether a field is hidden via conditional logic.
	 *
	 * @param array    $form         Form object.
	 * @param GF_Field $field        Field object.
	 * @param array    $field_values Default field values for this form. Used when form has not yet been submitted. Pass an array if no default field values are available/required.
	 * @param array    $lead         Optional, default is null. If lead object is available, pass the lead.
	 *
	 * @return mixed
	 */
	public static function is_field_hidden( $form, $field, $field_values, $lead = null ) {

		if ( empty( $field ) ) {
			return false;
		}

		$cache_key = 'GFFormsModel::is_field_hidden_' . $form['id'] . '_' . $field->id;
		$display   = GFCache::get( $cache_key, $is_hit, false );
		if ( $display !== false ) {
			return $display;
		}

		$section         = self::get_section( $form, $field->id );
		$section_display = self::get_field_display( $form, $section, $field_values, $lead );

		//if section is hidden, hide field no matter what. if section is visible, see if field is supposed to be visible
		if ( $section_display == 'hide' ) {
			$display = 'hide';
		} else if ( self::is_page_hidden( $form, $field->pageNumber, $field_values, $lead ) ) {
			$display = 'hide';
		} else {
			$display = self::get_field_display( $form, $field, $field_values, $lead );

			return $display == 'hide';
		}

		GFCache::set( $cache_key, $display );

		return $display == 'hide';
	}

	/***
	 * Determines if the submit button was supposed to be hidden by conditional logic. This function helps ensure that
	 *  the form doesn't get submitted when the submit button is hidden by conditional logic.
	 *
	 * @param $form The Form object
	 *
	 * @return bool Returns true if the submit button is hidden by conditional logic, false otherwise.
	 */
	public static function is_submit_button_hidden( $form ) {

		if( ! isset( $form['button']['conditionalLogic'] ) ){
			return false;
		}

		$is_visible = self::evaluate_conditional_logic( $form, $form['button']['conditionalLogic'], array() );

		return ! $is_visible;
	}

	public static function is_page_hidden( $form, $page_number, $field_values, $lead = null ) {
		$page = self::get_page_by_number( $form, $page_number );

		if ( ! $page ) {
			return false;
		}

		$display = self::get_field_display( $form, $page, $field_values, $lead );

		return $display == 'hide';
	}

	public static function get_page_by_number( $form, $page_number ) {
		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'page' && $field->pageNumber == $page_number ) {
				return $field;
			}
		}

		return null;
	}

	//gets the section that the specified field belongs to, or null if none
	public static function get_section( $form, $field_id ) {
		$current_section = null;
		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'section' ) {
				$current_section = $field;
			}

			//stop section at a page break (sections don't go cross page)
			if ( $field->type == 'page' ) {
				$current_section = null;
			}

			if ( $field->id == $field_id ) {
				return $current_section;
			}
		}

		return null;
	}

    /**
	 * Determines if the field value matches the conditional logic rule value.
	 *
	 * @param mixed         $field_value  The field value to be checked.
	 * @param mixed         $target_value The conditional logic rule value.
	 * @param string        $operation    The conditional logic rule operator.
	 * @param null|GF_Field $source_field The field the rule is based on.
	 * @param null|array    $rule         The conditional logic rule properties.
	 * @param null|array    $form         The current form.
	 *
	 * @return bool
	 */
	public static function is_value_match( $field_value, $target_value, $operation = 'is', $source_field = null, $rule = null, $form = null ) {

		$is_match = false;

		if ( $source_field && is_subclass_of( $source_field, 'GF_Field' ) ) {
			if ( $source_field->type == 'post_category' ) {
				$field_value = GFCommon::prepare_post_category_value( $field_value, $source_field, 'conditional_logic' );
			} elseif ( $source_field instanceof GF_Field_MultiSelect && ! empty( $field_value ) && ! is_array( $field_value ) ) {
				// Convert the comma-delimited string into an array.
				$field_value = $source_field->to_array( $field_value );
			} elseif ( $source_field->get_input_type() != 'checkbox' && is_array( $field_value ) && $source_field->id != $rule['fieldId'] && is_array( $source_field->get_entry_inputs() ) ) {
				// Get the specific input value from the full field value.
				$field_value = rgar( $field_value, $rule['fieldId'] );
			}
		}

		$form_id = $source_field instanceof GF_Field ? $source_field->formId : 0;

		$target_value = GFFormsModel::maybe_trim_input( $target_value, $form_id, $source_field );


		if ( is_array( $field_value ) ) {
			$field_value = array_values( $field_value ); // Returning array values, ignoring keys if array is associative.
			$match_count = 0;
			foreach ( $field_value as $val ) {
				$val = GFFormsModel::maybe_trim_input( GFCommon::get_selection_value( $val ), $form_id, $source_field );
				if ( self::matches_operation( $val, $target_value, $operation ) ) {
					$match_count ++;
				}
			}
			// If operation is Is Not, none of the values in the array can match the target value.
			$is_match = $operation == 'isnot' ? $match_count == count( $field_value ) : $match_count > 0;
		} else if ( self::matches_operation( GFFormsModel::maybe_trim_input( GFCommon::get_selection_value( $field_value ), $form_id, $source_field ), $target_value, $operation ) ) {
			$is_match = true;
		}

		return apply_filters( 'gform_is_value_match', $is_match, $field_value, $target_value, $operation, $source_field, $rule );
	}

	private static function try_convert_float( $text ) {

		/*
		global $wp_locale;
		$number_format = $wp_locale->number_format['decimal_point'] == ',' ? 'decimal_comma' : 'decimal_dot';

		if ( is_numeric( $text ) && $number_format == 'decimal_comma' ) {
			return GFCommon::format_number( $text, 'decimal_comma' );
		} else if ( GFCommon::is_numeric( $text, $number_format ) ) {
			return GFCommon::clean_number( $text, $number_format );
		}
		*/

		$number_format = 'decimal_dot';
		if ( GFCommon::is_numeric( $text, $number_format ) ) {
			return GFCommon::clean_number( $text, $number_format );
		}

		return 0;
	}

	public static function matches_operation( $val1, $val2, $operation ) {

		$val1 = ! rgblank( $val1 ) ? strtolower( $val1 ) : '';
		$val2 = ! rgblank( $val2 ) ? strtolower( $val2 ) : '';

		switch ( $operation ) {
			case 'is' :
				return $val1 == $val2;
				break;

			case 'isnot' :
				return $val1 != $val2;
				break;

			case 'greater_than':
			case '>' :
				$val1 = self::try_convert_float( $val1 );
				$val2 = self::try_convert_float( $val2 );

				return $val1 > $val2;
				break;

			case 'less_than':
			case '<' :
				$val1 = self::try_convert_float( $val1 );
				$val2 = self::try_convert_float( $val2 );

				return $val1 < $val2;
				break;

			case 'contains' :
				return ! rgblank( $val2 ) && strpos( $val1, $val2 ) !== false;
				break;

			case 'starts_with' :
				return ! rgblank( $val2 ) && strpos( $val1, $val2 ) === 0;
				break;

			case 'ends_with' :
				// If target value is a 0 set $val2 to 0 rather than the empty string it currently is to prevent false positives.
				if ( empty( $val2 ) ) {
					$val2 = 0;
				}

				$start = strlen( $val1 ) - strlen( $val2 );

				if ( $start < 0 ) {
					return false;
				}

				$tail = substr( $val1, $start );

				return $val2 == $tail;
				break;
		}


		return false;
	}

	/**
	 * @param          $form
	 * @param GF_Field $field
	 * @param          $field_values
	 * @param null     $lead
	 *
	 * @return string
	 */
	private static function get_field_display( $form, $field, $field_values, $lead = null ) {

		if ( empty( $field ) ) {
			return 'show';
		}

		$logic = $field->conditionalLogic;

		//if this field does not have any conditional logic associated with it, it won't be hidden
		if ( empty( $logic ) ) {
			return 'show';
		}

		$is_visible = self::evaluate_conditional_logic( $form, $logic, $field_values, $lead );

		return $is_visible ? 'show' : 'hide';
	}



	public static function get_custom_choices() {
		$choices = get_option( 'gform_custom_choices' );
		if ( ! $choices ) {
			$choices = array();
		}

		return $choices;
	}

	public static function delete_custom_choice( $name ) {
		$choices = self::get_custom_choices();
		if ( array_key_exists( $name, $choices ) ) {
			unset( $choices[ $name ] );
		}

		update_option( 'gform_custom_choices', $choices );
	}

	public static function save_custom_choice( $previous_name, $new_name, $choices ) {
		$all_choices = self::get_custom_choices();

		if ( array_key_exists( $previous_name, $all_choices ) ) {
			unset( $all_choices[ $previous_name ] );
		}

		$all_choices[ $new_name ] = $choices;

		update_option( 'gform_custom_choices', $all_choices );
	}


	/**
	 * Returns the value for a field.
	 *
	 * @param GF_Field $field
	 * @param array    $field_values
	 * @param bool     $get_from_post Whether to get the value from the $_POST array as opposed to $field_values
	 *
	 * @return array|mixed|string
	 */
	public static function get_field_value( &$field, $field_values = array(), $get_from_post = true ) {

		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		if ( $field->type == 'post_category' ) {
			$field = GFCommon::add_categories_as_choices( $field, '' );
		}

		$value = $field->get_value_submission( $field_values, $get_from_post );

		if ( $field->get_input_type() == 'list' && $field->enableColumns && $get_from_post && rgpost( 'is_submit_' . $field->formId ) ) {
			/** @var GF_Field_List $field */
			$value = $field->create_list_array_recursive( $value );
		}

		return $value;
	}

	/**
	 * @deprecated 2.4
	 *
	 * @param int $expiration_days
	 *
	 * @return false|int
	 */
	public static function purge_expired_incomplete_submissions( $expiration_days = 30 ) {
		_deprecated_function( 'GFFormsModel::purge_expired_incomplete_submissions', '2.4', 'GFFormsModel::purge_expired_draft_submissions' );
		return self::purge_expired_draft_submissions( $expiration_days = 30 );
	}

	/**
	 * Purges expired draft submissions.
	 *
	 * @since 2.4
	 *
	 * @param int $expiration_days
	 *
	 * @return false|int
	 */
	public static function purge_expired_draft_submissions( $expiration_days = 30 ) {
		global $wpdb;

		/**
		 * Overrides the number of days until draft submissions are purged.
		 *
		 * @since 1.9
		 *
		 * @param int $expiration_days The number of days until expiration. Defaults to 30.
		 */
		$expiration_days = apply_filters( 'gform_incomplete_submissions_expiration_days', $expiration_days );
		$expiration_date = gmdate( 'Y-m-d H:i:s', time() - ( $expiration_days * 24 * 60 * 60 ) );

		$table  = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_incomplete_submissions_table_name() : self::get_draft_submissions_table_name();

		$query = array(
			'delete' => 'DELETE',
			'from'   => sprintf( 'FROM %s', $table ),
			'where'  => $wpdb->prepare( 'WHERE date_created < %s', $expiration_date ),
		);

		/**
		 * Allows the query used to purge expired draft (save and continue) submissions to be overridden.
		 *
		 * @since 2.1.1.20
		 *
		 * @param array $query The delete, from, and where arguments to be used when the query is performed.
		 */
		$query = apply_filters( 'gform_purge_expired_incomplete_submissions_query', $query );

		$result = $wpdb->query( implode( "\n", $query ) );
		return $result;
	}

	/**
	 *
	 * @deprecated 2.4
	 *
	 * @param $token
	 *
	 * @return false|int
	 */
	public static function delete_incomplete_submission( $token ) {
		_deprecated_function( 'GFFormsModel::delete_incomplete_submission', '2.4', 'GFFormsModel::delete_draft_submission' );
		return self::delete_draft_submission( $token );
	}

	/**
	 * Deletes a draft submission.
	 *
	 * @since 2.4
	 *
	 * @param $token
	 *
	 * @return false|int
	 */
	public static function delete_draft_submission( $token ) {
		global $wpdb;

		$table  = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_incomplete_submissions_table_name() : self::get_draft_submissions_table_name();
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE uuid = %s", $token ) );

		return $result;
	}

	/**
	 *
	 * @deprecated 2.4
	 *
	 * @param        $form
	 * @param        $entry
	 * @param        $field_values
	 * @param        $page_number
	 * @param        $files
	 * @param        $form_unique_id
	 * @param        $ip
	 * @param        $source_url
	 * @param string $resume_token
	 *
	 * @return bool|false|int|string
	 */
	public static function save_incomplete_submission( $form, $entry, $field_values, $page_number, $files, $form_unique_id, $ip, $source_url, $resume_token = '' ) {
		_deprecated_function( 'GFFormsModel::save_incomplete_submission', '2.4', 'GFFormsModel::save_draft_submission' );
		return self::save_draft_submission( $form, $entry, $field_values, $page_number, $files, $form_unique_id, $ip, $source_url, $resume_token );
	}

	/**
	 * Saves the draft submission.
	 *
	 * @since 2.4
	 *
	 * @param        $form
	 * @param        $entry
	 * @param        $field_values
	 * @param        $page_number
	 * @param        $files
	 * @param        $form_unique_id
	 * @param        $ip
	 * @param        $source_url
	 * @param string $resume_token
	 *
	 * @return bool|false|int|string
	 */
	public static function save_draft_submission( $form, $entry, $field_values, $page_number, $files, $form_unique_id, $ip, $source_url, $resume_token = '' ) {
		if ( ! is_array( $form['fields'] ) ) {
			return;
		}
		global $wpdb;

		$table  = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_incomplete_submissions_table_name() : self::get_draft_submissions_table_name();

		$submitted_values = array();
		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */
			if ( $field->type == 'creditcard' ) {
				continue;
			}

			$submitted_values[ $field->id ] = RGFormsModel::get_field_value( $field, $field_values );
		}

		/**
		 * Allows the modification of submitted values before the draft submission is saved.
		 *
		 * @since 1.9
		 *
		 * @param array $submitted_values The submitted values
		 * @param array $form             The Form object
		 */
		$submitted_values = apply_filters( 'gform_submission_values_pre_save', $submitted_values, $form );

		$submission['submitted_values'] = $submitted_values;
		$submission['partial_entry']    = $entry;
		$submission['field_values']     = $field_values;
		$submission['page_number']      = $page_number;
		$submission['files']            = $files;
		$submission['gform_unique_id']  = $form_unique_id;

		// Issue a new token if no longer valid
		if ( ! empty( $resume_token ) ) {
			$sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE uuid = %s", $resume_token );
			$count = $wpdb->get_var( $sql );
			if ( $count != 1 ) {
				$resume_token = false;
			}
		}

		$is_new = empty( $resume_token );

		if ( $is_new ) {
			$resume_token = self::get_uuid();
		}

		$submission_json = json_encode( $submission );

		$submission_json = self::filter_draft_submission_pre_save( $submission_json, $resume_token, $form );

		if ( $is_new ) {
			$result = $wpdb->insert(
				$table,
				array(
					'uuid'         => $resume_token,
					'form_id'      => $form['id'],
					'date_created' => current_time( 'mysql', true ),
					'submission'   => $submission_json,
					'ip'           => $ip,
					'source_url'   => $source_url,
				),
				array(
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
				)
			);
		} else {
			$result = $wpdb->update(
				$table,
				array(
					'form_id'      => $form['id'],
					'date_created' => current_time( 'mysql', true ),
					'submission'   => $submission_json,
					'ip'           => $ip,
					'source_url'   => $source_url,
				),
				array( 'uuid' => $resume_token ),
				array(
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
				),
				array( '%s' )
			);
		}

		/**
		 * Fires after an draft submission is saved
		 *
		 * @since 1.9
		 *
		 * @param array  $submission   Contains the partially submitted entry, fields, values, and files.
		 * @param string $resume_token The unique resume token that was generated for this partial submission
		 * @param array  $form         The Form object
		 * @param array  $entry        The Entry object
		 */
		do_action( 'gform_incomplete_submission_post_save', $submission, $resume_token, $form, $entry );

		return $result ? $resume_token : $result;
	}

	/**
	 * Filters the submission json string before saving.
	 *
	 * @since 2.4
	 *
	 * @param $submission_json
	 * @param $resume_token
	 * @param $form
	 *
	 * @return string
	 */
	private static function filter_draft_submission_pre_save( $submission_json, $resume_token, $form ) {
		/**
		 * Allows the draft submission to be overridden before it is saved to the database.
		 *
		 * @since 2.3.3.1
		 *
		 * @param string $submission_json {
		 *    JSON encoded associative array containing this incomplete submission.
		 *
		 *    @type array      $submitted_values The submitted values.
		 *    @type array      $partial_entry    The draft entry created from the submitted values.
		 *    @type null|array $field_values     The dynamic population field values.
		 *    @type int        $page_number      The forms current page number.
		 *    @type array      $files            The uploaded file properties.
		 *    @type string     $gform_unique_id  The unique id for this submission.
		 * }
		 * @param string $resume_token The unique token which can be used to resume this incomplete submission at a later date/time.
		 * @param array  $form         The form which this incomplete submission was created for.
		 */
		$submission_json = apply_filters( 'gform_incomplete_submission_pre_save', $submission_json, $resume_token, $form );

		return $submission_json;
	}

	/**
	 * Updates a draft submission.
	 *
	 * @since 2.4
	 *
	 * @param string $resume_token The uuid of the draft submission to be updated.
	 * @param array  $form
	 * @param string $date_created
	 * @param string $ip
	 * @param string $source_url
	 * @param string $submission_json
	 *
	 * @return bool|false|int|string
	 */
	public static function update_draft_submission( $resume_token, $form, $date_created, $ip, $source_url, $submission_json ) {
		global $wpdb;

		$form_id = $form['id'];

		$submission_json = self::filter_draft_submission_pre_save( $submission_json, $resume_token, $form );

		$table = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_incomplete_submissions_table_name() : self::get_draft_submissions_table_name();

		$result = $wpdb->update(
			$table,
			array(
				'form_id'      => $form_id,
				'date_created' => $date_created,
				'submission'   => $submission_json,
				'ip'           => $ip,
				'source_url'   => $source_url,
			),
			array( 'uuid' => $resume_token ),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			),
			array( '%s' )
		);

		return $result ? $resume_token : $result;
	}

	/**
	 * Returns a UUID. Uses openssl_random_pseudo_bytes() if available and falls back to mt_rand().
	 *
	 * source: http://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
	 *
	 * @param string $s The separator e.g. '-'
	 *
	 * @return string
	 */
	public static function get_uuid( $s = '' ) {

		if ( function_exists( 'openssl_random_pseudo_bytes' ) ) { // PHP 5 >= 5.3.0
			$data = openssl_random_pseudo_bytes( 16 );

			$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
			$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

			return vsprintf( "%s%s{$s}%s{$s}%s{$s}%s{$s}%s%s%s", str_split( bin2hex( $data ), 4 ) );
		} else {
			return sprintf(
				"%04x%04x{$s}%04x{$s}%04x{$s}%04x{$s}%04x%04x%04x",
				// 32 bits for "time_low"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

				// 16 bits for 'time_mid'
				mt_rand( 0, 0xffff ),

				// 16 bits for 'time_hi_and_version',
				// four most significant bits holds version number 4
				mt_rand( 0, 0x0fff ) | 0x4000,

				// 16 bits, 8 bits for 'clk_seq_hi_res',
				// 8 bits for 'clk_seq_low',
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand( 0, 0x3fff ) | 0x8000,

				// 48 bits for 'node'
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
			);
		}

	}

	/**
	 * @deprecated 2.4
	 *
	 * @param $resume_token
	 *
	 * @return array|null|object
	 */
	public static function get_incomplete_submission_values( $resume_token ) {
		_deprecated_function( 'GFFormsModel::get_incomplete_submission_values', '2.4', 'GFFormsModel::get_draft_submission_values' );
		return self::get_draft_submission_values( $resume_token );
	}

	/**
	 * Returns the values for the draft submission.
	 *
	 * @since 2.4
	 *
	 * @param $resume_token
	 *
	 * @return array|null|object
	 */
	public static function get_draft_submission_values( $resume_token ) {
		global $wpdb;

		self::purge_expired_draft_submissions();

		$table = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_incomplete_submissions_table_name() : self::get_draft_submissions_table_name();
		$sql   = $wpdb->prepare( "SELECT date_created, form_id, submission, source_url FROM {$table} WHERE uuid = %s", $resume_token );
		$row   = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! empty( $row ) ) {
			$form = self::get_form_meta( $row['form_id'] );
			$row['submission'] = self::filter_draft_submission_post_get( $row['submission'], $resume_token, $form );
		}

		return $row;
	}

	/**
	 * Filters the draft submission after reading it from the database.
	 *
	 * @since 2.4
	 *
	 * @param $submission_json
	 * @param $resume_token
	 * @param $form
	 *
	 * @return string
	 */
	private static function filter_draft_submission_post_get( $submission_json, $resume_token, $form ) {

		/**
		 * Allows the draft submission to be overridden after it is retrieved from the database but before it used to populate the form.
		 *
		 * @since 2.3.3.1
		 *
		 * @param string $submission_json {
		 *    JSON encoded associative array containing the draft submission being resumed.
		 *
		 *    @type array      $submitted_values The submitted values.
		 *    @type array      $partial_entry    The draft entry created from the submitted values.
		 *    @type null|array $field_values     The dynamic population field values.
		 *    @type int        $page_number      The forms current page number.
		 *    @type array      $files            The uploaded file properties.
		 *    @type string     $gform_unique_id  The unique id for this submission.
		 * }
		 * @param string $resume_token The unique token which was used to resume this incomplete submission.
		 * @param array  $form         The form which this incomplete submission was created for.
		 */
		$submission_json = apply_filters( 'gform_incomplete_submission_post_get', $submission_json, $resume_token, $form );
		return $submission_json;
	}

	/**
	 *
	 * @deprecated 2.4
	 *
	 * @param $token
	 * @param $email
	 *
	 * @return false|int
	 */
	public static function add_email_to_incomplete_sumbmission( $token, $email ) {
		_deprecated_function( 'GFFormsModel::add_email_to_incomplete_sumbmission', '2.4', 'GFFormsModel::add_email_to_draft_sumbmission' );
		return self::add_email_to_draft_sumbmission( $token, $email );
	}

	/**
	 * Adds the email address to the draft submission.
	 *
	 * @since 2.4
	 *
	 * @param $token
	 * @param $email
	 *
	 * @return false|int
	 */
	public static function add_email_to_draft_sumbmission( $token, $email ) {
		global $wpdb;
		self::purge_expired_draft_submissions();

		$table  = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_incomplete_submissions_table_name() : self::get_draft_submissions_table_name();
		$sql    = $wpdb->prepare( "UPDATE $table SET email = %s WHERE uuid = %s", $email, $token );
		$result = $wpdb->query( $sql );

		return $result;
	}

	public static function maybe_trim_input( $value, $form_id, $field ) {
		$trim_value = apply_filters( 'gform_trim_input_value', true, $form_id, $field );

		if ( $trim_value ) {
			$value = is_array( $value ) ? GFCommon::trim_deep( $value ) : trim( $value );
		}

		return $value;
	}

	public static function get_parameter_value( $name, $field_values, $field ) {
		$value = stripslashes_deep( rgget( $name ) );
		if ( rgblank( $value ) ) {
			$value = rgget( $name, $field_values );
		}

		// Converting list format
		if ( ! empty( $value ) && RGFormsModel::get_input_type( $field ) == 'list' ) {

			// Transforms this: col1|col2,col1b|col2b into this: col1,col2,col1b,col2b
			$column_count = is_array( $field->choices ) ? count( $field->choices ) : 0;

			$rows = is_array( $value ) ? $value : explode( ',', $value );

			if ( ! empty( $rows ) ) {
				$ary_rows = array();

				foreach ( $rows as $row ) {
					/**
					 * Allow modification of the delimiter used to parse List field URL parameters.
					 *
					 * @since 2.0.0
					 *
					 * @param string $delimiter    Defaults to '|';
					 * @param array  $field        GF_Field object for the current field.
					 * @param string $name         Name of the current dynamic population parameter.
					 * @param array  $field_values Array of values provided for pre-population into the form.
					 */
					$delimiter = apply_filters( 'gform_list_field_parameter_delimiter', '|', $field, $name, $field_values );
					$ary_rows  = array_merge( $ary_rows, rgexplode( $delimiter, $row, $column_count ) );
				}

				$value = $ary_rows;
			}
		}

		return gf_apply_filters( array( 'gform_field_value', $name ), $value, $field, $name );
	}

	public static function get_default_value( $field, $input_id ) {
		if ( ! is_array( $field->choices ) ) {
			// if entry is saved in separate inputs get requsted input's default value ($input_id = 2.1)
			// some fields (like Date, Time) do not save their values in separate inputs and are correctly filtered out by this condition ($input_id = 2)
			// other fields (like Email w/ Confirm-enabled) also do not save their values in separate inputs but *should be* processed as input-specific submissions ($input_id = 2)
			if ( is_array( $field->get_entry_inputs() ) || ( $field->get_input_type() == 'email' && is_array( $field->inputs ) ) ) {
				$input = RGFormsModel::get_input( $field, $input_id );
				return rgar( $input, 'defaultValue' );
			} else {
				$value = $field->get_value_default();
				if( ! IS_ADMIN ) {
					if( is_array( $value ) ) {
						foreach( $value as &$_value ) {
							$_value = GFCommon::replace_variables_prepopulate( $_value );
						}
					} else {
						$value = GFCommon::replace_variables_prepopulate( $value );
					}
				}
				return $value;
			}
		} else if ( $field->type == 'checkbox' ) {
			for ( $i = 0, $count = sizeof( $field->inputs ); $i < $count; $i ++ ) {
				$input  = $field->inputs[ $i ];
				$choice = $field->choices[ $i ];
				if ( $input['id'] == $input_id && rgar( $choice, 'isSelected' ) ) {
					return $choice['value'];
				}
			}

			return '';
		} else {
			foreach ( $field->choices as $choice ) {
				if ( rgar( $choice, 'isSelected' ) || $field->type == 'post_category' ) {
					return $choice['value'];
				}
			}

			return '';
		}

	}

	/**
	 * @param GF_Field $field
	 *
	 * @return string
	 */
	public static function get_input_type( $field ) {
		// TODO: Deprecate
		if ( ! $field instanceof GF_Field ) {
			return empty( $field['inputType'] ) ? $field['type'] : $field['inputType'];
		}

		return $field->get_input_type();
	}

	private static function get_post_field_value( $field, $lead ) {

		if ( is_array( $field->get_entry_inputs() ) ) {
			$value = array();
			foreach ( $field->inputs as $input ) {
				$val = isset( $lead[ strval( $input['id'] ) ] ) ? $lead[ strval( $input['id'] ) ] : '';
				if ( ! empty( $val ) ) {

					// replace commas in individual values to prevent individual value from being split into multiple values (checkboxes, multiselects)
					if ( $field->get_input_type() === 'checkbox' ) {
						$val = str_replace( ',', '&#44;', $val );
					}

					$value[] = $val;
				}
			}
			$value = implode( ',', $value );
		} else {
			$value = isset( $lead[ $field->id ] ) ? $lead[ $field->id ] : '';

			if ( ! empty( $value ) && $field->get_input_type() === 'multiselect' ) {
				$items = $field->to_array( $value );

				foreach ( $items as &$item ) {
					$item = str_replace( ',', '&#44;', $item );
				}

				$value = implode( ',', $items );
			}
		}

		return $value;
	}

	private static function get_post_fields( $form, $lead ) {

		$post_data                       = array();
		$post_data['post_custom_fields'] = array();
		$post_data['tags_input']         = array();
		$categories                      = array();
		$images                          = array();

		foreach ( $form['fields'] as $field ) {
			if ( self::is_field_hidden( $form, $field, array(), $lead ) ) {
				continue;
			}

			if ( $field->type == 'post_category' ) {
				$field = GFCommon::add_categories_as_choices( $field, '' );
			}

			$value = self::get_post_field_value( $field, $lead );

			switch ( $field->type ) {
				case 'post_title' :
				case 'post_excerpt' :
				case 'post_content' :
					// Prevent shortcodes from being parsed.
					$post_data[ $field->type ] = GFCommon::encode_shortcodes( $value );
					break;

				case 'post_tags' :
					$tags = explode( ',', $value );
					if ( is_array( $tags ) && sizeof( $tags ) > 0 ) {
						$post_data['tags_input'] = array_merge( $post_data['tags_input'], $tags );
					}
					break;

				case 'post_custom_field' :

					$type = self::get_input_type( $field );
					if ( 'fileupload' === $type && $field->multipleFiles ) {
						$value = json_decode( $value, true );
					}

					$meta_name = $field->postCustomFieldName;

					if ( ! isset( $post_data['post_custom_fields'][ $meta_name ] ) ) {
						$post_data['post_custom_fields'][ $meta_name ] = $value;
					} else if ( ! is_array( $post_data['post_custom_fields'][ $meta_name ] ) ) {
						$post_data['post_custom_fields'][ $meta_name ] = array( $post_data['post_custom_fields'][ $meta_name ], $value );
					} else {
						$post_data['post_custom_fields'][ $meta_name ][] = $value;
					}

					break;

				case 'post_category' :
					foreach ( explode( ',', $value ) as $cat_string ) {
						$cat_array = explode( ':', $cat_string );
						// the category id is the last item in the array, access it using end() in case the category name includes colons.
						array_push( $categories, end( $cat_array ) );
					}
					break;

				case 'post_image' :
					$ary         = ! empty( $value ) ? explode( '|:|', $value ) : array();
					$url         = count( $ary ) > 0 ? $ary[0] : '';
					$title       = count( $ary ) > 1 ? $ary[1] : '';
					$caption     = count( $ary ) > 2 ? $ary[2] : '';
					$description = count( $ary ) > 3 ? $ary[3] : '';

					array_push( $images, array( 'field_id' => $field->id, 'url' => $url, 'title' => $title, 'description' => $description, 'caption' => $caption ) );
					break;
			}
		}

		$post_data['post_status']   = rgar( $form, 'postStatus' );
		$post_data['post_category'] = ! empty( $categories ) ? $categories : array( rgar( $form, 'postCategory' ) );
		$post_data['images']        = $images;

		//setting current user as author depending on settings
		$post_data['post_author'] = $form['useCurrentUserAsAuthor'] && ! empty( $lead['created_by'] ) ? $lead['created_by'] : rgar( $form, 'postAuthor' );

		return $post_data;
	}

	/**
	 * Retrieves the custom field names (meta keys) for the custom field select in the form editor.
	 *
	 * @since unknown
	 *
	 * @return array
	 */
	public static function get_custom_field_names() {
		$form_id = absint( rgget( 'id' ) );

		/**
		 * Allow the postmeta query which retrieves the custom field names (meta keys) to be disabled.
		 *
		 * @since 2.3.4.1
		 *
		 * @param bool $disable_query Indicates if the custom field names query should be disabled. Default is false.
		 */
		$disable_query = gf_apply_filters( array( 'gform_disable_custom_field_names_query', $form_id ), false );

		if ( $disable_query ) {
			return array();
		}

		global $wpdb;
		$sql = "SELECT DISTINCT meta_key
			FROM $wpdb->postmeta
			WHERE meta_key NOT BETWEEN '_' AND '_z'
			HAVING meta_key NOT LIKE %s
			ORDER BY meta_key";
		$keys = $wpdb->get_col( $wpdb->prepare( $sql, $wpdb->esc_like( '_' ) . '%' ) );

		return $keys;
	}

	public static function get_input_masks() {

		$masks = array(
			'US Phone'       => '(999) 999-9999',
			'US Phone + Ext' => '(999) 999-9999? x99999',
			'Date'           => '99/99/9999',
			'Tax ID'         => '99-9999999',
			'SSN'            => '999-99-9999',
			'Zip Code'       => '99999',
			'Full Zip Code'  => '99999?-9999',
		);

		return apply_filters( 'gform_input_masks', $masks );
	}

	private static function get_default_post_title() {
		global $wpdb;
		$title = 'Untitled';
		$count = 1;

		$titles = $wpdb->get_col( "SELECT post_title FROM $wpdb->posts WHERE post_title like '%Untitled%'" );
		$titles = array_values( $titles );
		while ( in_array( $title, $titles ) ) {
			$title = "Untitled_$count";
			$count ++;
		}

		return $title;
	}

	public static function prepare_date( $date_format, $value ) {
		$format    = empty( $date_format ) ? 'mdy' : $date_format;
		$date_info = GFCommon::parse_date( $value, $format );
		if ( ! empty( $date_info ) && ! GFCommon::is_empty_array( $date_info ) ) {
			$value = sprintf( '%s-%02d-%02d', $date_info['year'], $date_info['month'], $date_info['day'] );
		} else {
			$value = '';
		}

		return $value;
	}

	/**
	 * Prepare the value before saving it to the lead. For multi-input fields this will be called for each input.
	 *
	 * @param mixed    $form
	 * @param GF_Field $field
	 * @param mixed    $value
	 * @param mixed    $input_name
	 * @param mixed    $lead_id the current lead ID, used for fields that are processed after other fields have been saved (ie Total, Calculations)
	 * @param mixed    $lead    passed by the RGFormsModel::create_lead() method, lead ID is not available for leads created by this function
	 *
	 * @return mixed
	 */
	public static function prepare_value( $form, $field, $value, $input_name, $lead_id, $lead = array() ) {

		$value = $field->get_value_save_entry( $value, $form, $input_name, $lead_id, $lead );


		// special format for Post Category fields
		if ( $field->type == 'post_category' ) {
			$is_multiselect = $field->inputType === 'multiselect';
			$full_values    = array();

			if ( ! is_array( $value ) ) {
				$value = $is_multiselect ? $field->to_array( $value ) : explode( ',', $value );
			}

			foreach ( $value as $cat_id ) {
				$cat           = get_term( $cat_id, 'category' );
				$full_values[] = ! is_wp_error( $cat ) && is_object( $cat ) ? $cat->name . ':' . $cat_id : '';
			}

			$value = $is_multiselect ? $field->to_string( $full_values ) : implode( ',', $full_values );
		}

		//do not save price fields with blank price
		if ( $field->enablePrice ) {
			$ary   = explode( '|', $value );
			$label = count( $ary ) > 0 ? $ary[0] : '';
			$price = count( $ary ) > 1 ? $ary[1] : '';

			$is_empty = ( strlen( trim( $price ) ) <= 0 );
			if ( $is_empty ) {
				$value = '';
			}
		}

		return $value;
	}

	public static function is_checkbox_checked( $field_id, $field_label, $lead, $form ) {

		//looping through lead detail values trying to find an item identical to the column label. Mark with a tick if found.
		$lead_field_keys = array_keys( $lead );
		foreach ( $lead_field_keys as $input_id ) {
			//mark as a tick if input label (from form meta) is equal to submitted value (from lead)
			if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field_id ) ) {
				if ( $lead[ $input_id ] == $field_label ) {
					return $lead[ $input_id ];
				} else {
					$field = RGFormsModel::get_field( $form, $field_id );
					if ( $field->enableChoiceValue || $field->enablePrice ) {
						foreach ( $field->choices as $choice ) {
							if ( $choice['value'] == $lead[ $field_id ] ) {
								return $choice['value'];
							} else if ( $field->enablePrice ) {
								$ary   = explode( '|', $lead[ $field_id ] );
								$val   = count( $ary ) > 0 ? $ary[0] : '';
								$price = count( $ary ) > 1 ? $ary[1] : '';

								if ( $val == $choice['value'] ) {
									return $choice['value'];
								}
							}
						}
					}
				}
			}
		}

		return false;
	}

	public static function get_fileupload_value( $form_id, $input_name ) {
		_deprecated_function( 'GFFormsModel::get_fileupload_value', '1.9', 'GF_Field_Fileupload::get_fileupload_value' );
		global $_gf_uploaded_files;

		GFCommon::log_debug( 'GFFormsModel::get_fileupload_value(): Starting.' );

		if ( empty( $_gf_uploaded_files ) ) {
			GFCommon::log_debug( 'GFFormsModel::get_fileupload_value(): No files uploaded. Exiting.' );
			$_gf_uploaded_files = array();
		}


		if ( ! isset( $_gf_uploaded_files[ $input_name ] ) ) {

			//check if file has already been uploaded by previous step
			$file_info     = self::get_temp_filename( $form_id, $input_name );
			$temp_filepath = self::get_upload_path( $form_id ) . '/tmp/' . $file_info['temp_filename'];
			GFCommon::log_debug( 'GFFormsModel::get_fileupload_value(): Temp file path: ' . $temp_filepath );
			if ( $file_info && file_exists( $temp_filepath ) ) {
				GFCommon::log_debug( 'GFFormsModel::get_fileupload_value(): Moving temp file: ' . $temp_filepath );
				$_gf_uploaded_files[ $input_name ] = self::move_temp_file( $form_id, $file_info );
			} else if ( ! empty( $_FILES[ $input_name ]['name'] ) ) {
				GFCommon::log_debug( 'GFFormsModel::get_fileupload_value(): Uploading file: ' . $_FILES[ $input_name ]['name'] );
				$_gf_uploaded_files[ $input_name ] = self::upload_file( $form_id, $_FILES[ $input_name ] );
			}
		}

		return rgget( $input_name, $_gf_uploaded_files );
	}

	public static function get_form_unique_id( $form_id ) {
		$unique_id = '';
		if ( rgpost( 'gform_submit' ) == $form_id ) {
			$posted_uid = rgpost( 'gform_unique_id' );
			if ( false === empty( $posted_uid ) && ctype_alnum( $posted_uid )) {
				$unique_id = $posted_uid;
				self::$unique_ids[ $form_id ] = $unique_id;
			} elseif ( isset( self::$unique_ids[ $form_id ] ) ) {
				$unique_id = self::$unique_ids[ $form_id ];
			} else {
				$unique_id = uniqid();
				self::$unique_ids[ $form_id ] = $unique_id;
			}
		}

		return $unique_id;
	}

	public static function get_temp_filename( $form_id, $input_name ) {

		$uploaded_filename = ! empty( $_FILES[ $input_name ]['name'] ) && $_FILES[ $input_name ]['error'] === 0 ? $_FILES[ $input_name ]['name'] : '';

		if ( empty( $uploaded_filename ) && isset( self::$uploaded_files[ $form_id ] ) ) {
			$uploaded_filename = rgget( $input_name, self::$uploaded_files[ $form_id ] );
		}

		if ( empty( $uploaded_filename ) ) {
			return false;
		}

		$form_unique_id = self::get_form_unique_id( $form_id );
		$pathinfo       = pathinfo( $uploaded_filename );

		GFCommon::log_debug( __METHOD__ . '(): Uploaded filename is ' . $uploaded_filename . ' and temporary filename is ' . $form_unique_id . '_' . $input_name . '.' . $pathinfo['extension'] );
		return array( 'uploaded_filename' => $uploaded_filename, 'temp_filename' => "{$form_unique_id}_{$input_name}.{$pathinfo['extension']}" );

	}

	public static function get_choice_text( $field, $value, $input_id = 0 ) {
		if ( ! is_array( $field->choices ) ) {
			return $value;
		}

		foreach ( $field->choices as $choice ) {
			if ( is_array( $value ) && self::choice_value_match( $field, $choice, $value[ $input_id ] ) ) {
				return $choice['text'];
			} else if ( ! is_array( $value ) && self::choice_value_match( $field, $choice, $value ) ) {
				return $choice['text'];
			}
		}

		return is_array( $value ) ? '' : $value;
	}


	public static function choice_value_match( $field, $choice, $value ) {
		$choice_value = GFFormsModel::maybe_trim_input( $choice['value'], $field->formId, $field );
		$value        = GFFormsModel::maybe_trim_input( $value, $field->formId, $field );

		$allowed_html    = wp_kses_allowed_html( 'post' );
		$sanitized_value = wp_kses( $value, $allowed_html );

		if ( $choice_value == $value || $choice_value == $sanitized_value ) {
			return true;
		} else if ( $field->enablePrice ) {
			$ary = explode( '|', $value );

			$val           = count( $ary ) > 0 ? $ary[0] : '';
			$sanitized_val = wp_kses( $val, $allowed_html );

			$price = count( $ary ) > 1 ? $ary[1] : '';

			if ( $choice['value'] == $val || $choice['value'] == $sanitized_val ) {
				return true;
			}
		} // add support for prepopulating multiselects @alex
		else if ( RGFormsModel::get_input_type( $field ) == 'multiselect' ) {
			$values           = $field->to_array( $value );
			$sanitized_values = $field->to_array( $sanitized_value );

			if ( in_array( $choice_value, $values ) || in_array( $choice_value, $sanitized_values ) ) {
				return true;
			}
		}

		return false;
	}

	public static function choices_value_match( $field, $choices, $value ) {
		foreach ( $choices as $choice ) {
			if ( self::choice_value_match( $field, $choice, $value ) ) {
				return true;
			}
		}

		return false;
	}

	public static function create_post( $form, &$lead ) {

		GFCommon::log_debug( 'GFFormsModel::create_post(): Starting.' );

		$has_post_field = false;
		foreach ( $form['fields'] as $field ) {
			$is_hidden = self::is_field_hidden( $form, $field, array(), $lead );
			if ( ! $is_hidden && in_array( $field->type, array( 'post_category', 'post_title', 'post_content', 'post_excerpt', 'post_tags', 'post_custom_field', 'post_image' ) ) ) {
				$has_post_field = true;
				break;
			}
		}

		//if this form does not have any post fields, don't create a post
		if ( ! $has_post_field ) {
			GFCommon::log_debug( "GFFormsModel::create_post(): Stopping. The form doesn't have any post fields." );

			return $lead;
		}


		//processing post fields
		GFCommon::log_debug( 'GFFormsModel::create_post(): Getting post fields.' );
		$post_data = self::get_post_fields( $form, $lead );

		//allowing users to change post fields before post gets created
		$post_data = gf_apply_filters( array( 'gform_post_data', $form['id'] ), $post_data, $form, $lead );

		//adding default title if none of the required post fields are in the form (will make sure wp_insert_post() inserts the post)
		if ( empty( $post_data['post_title'] ) && empty( $post_data['post_content'] ) && empty( $post_data['post_excerpt'] ) ) {
			$post_data['post_title'] = self::get_default_post_title();
		}

		// remove original post status and save it for later
		$post_status = $post_data['post_status'];

		// replace original post status with 'draft' so other plugins know this post is not fully populated yet
		$post_data['post_status'] = 'draft';

		// inserting post
		GFCommon::log_debug( 'GFFormsModel::create_post(): Inserting post via wp_insert_post().' );
		$post_id = wp_insert_post( $post_data, true );
		GFCommon::log_debug( 'GFFormsModel::create_post(): Result from wp_insert_post(): ' . print_r( $post_id, 1 ) );

		if ( is_wp_error( $post_id ) ) {
			GFCommon::log_debug( __METHOD__ . '(): $post_data => ' . print_r( $post_data, 1 ) );

			return false;
		}

		// Add the post id to the entry so it is available during merge tag replacement.
		$lead['post_id'] = $post_id;

		//adding form id and entry id hidden custom fields
		add_post_meta( $post_id, '_gform-form-id', $form['id'] );
		add_post_meta( $post_id, '_gform-entry-id', $lead['id'] );

		$post_images = array();
		if ( ! empty( $post_data['images'] ) ) {
			// Creating post images.
			GFCommon::log_debug( 'GFFormsModel::create_post(): Processing post images.' );

			foreach ( $post_data['images'] as $image ) {
				if ( empty( $image['url'] ) ) {
					GFCommon::log_debug( __METHOD__ . '(): No image to process for field #' . $image['field_id'] );
					continue;
				}

				$image_meta = array(
					'post_excerpt' => $image['caption'],
					'post_content' => $image['description'],
				);

				// Adding title only if it is not empty. It will default to the file name if it is not in the array.
				if ( ! empty( $image['title'] ) ) {
					$image_meta['post_title'] = $image['title'];
				}

				GFCommon::log_debug( sprintf( '%s(): Field #%s. URL: %s', __METHOD__, $image['field_id'], $image['url'] ) );
				$media_id = self::media_handle_upload( $image['url'], $post_id, $image_meta );

				if ( $media_id ) {

					// Save media id for post body/title template variable replacement (below).
					$post_images[ $image['field_id'] ] = $media_id;
					$lead[ $image['field_id'] ] .= "|:|$media_id";

					// Setting the featured image.
					$field = RGFormsModel::get_field( $form, $image['field_id'] );
					if ( $field->postFeaturedImage ) {
						$result = set_post_thumbnail( $post_id, $media_id );
						GFCommon::log_debug( __METHOD__ . '(): Setting the featured image. Result from set_post_thumbnail(): ' . var_export( $result, 1 ) );
					}
				}
			}
		}

		//adding custom fields
		GFCommon::log_debug( 'GFFormsModel::create_post(): Adding custom fields.' );
		foreach ( $post_data['post_custom_fields'] as $meta_name => $meta_value ) {
			if ( ! is_array( $meta_value ) ) {
				$meta_value = array( $meta_value );
			}

			$meta_index = 0;
			foreach ( $meta_value as $value ) {
				GFCommon::log_debug( 'GFFormsModel::create_post(): Getting custom field: ' . $meta_name );
				$custom_field = self::get_custom_field( $form, $meta_name, $meta_index );

				//replacing template variables if template is enabled
				if ( $custom_field && $custom_field->customFieldTemplateEnabled ) {
					$value = self::process_post_template( $custom_field->customFieldTemplate, 'post_custom_field', $post_images, $post_data, $form, $lead );
				}
				switch ( RGFormsModel::get_input_type( $custom_field ) ) {
					case 'list' :
						$value = maybe_unserialize( $value );
						if ( is_array( $value ) ) {
							foreach ( $value as $item ) {
								if ( is_array( $item ) ) {
									$item = implode( '|', $item );
								}

								if ( ! rgblank( $item ) ) {
									add_post_meta( $post_id, $meta_name, $item );
								}
							}
						}
						break;

					case 'multiselect' :
					case 'checkbox' :
						$value = explode( ',', $value );
						if ( is_array( $value ) ) {
							foreach ( $value as $item ) {
								if ( ! rgblank( $item ) ) {
									// add post meta and replace HTML symbol in $item with real comma
									add_post_meta( $post_id, $meta_name, str_replace( '&#44;', ',', $item ) );
								}
							}
						}
						break;

					case 'date' :
						$value = GFCommon::date_display( $value, rgar( $custom_field, 'dateFormat' ) );
						if ( ! rgblank( $value ) ) {
							add_post_meta( $post_id, $meta_name, $value );
						}
						break;

					default :
						if ( ! rgblank( $value ) ) {
							add_post_meta( $post_id, $meta_name, $value );
						}
						break;
				}

				$meta_index ++;
			}
		}

		$has_content_field = sizeof( self::get_fields_by_type( $form, array( 'post_content' ) ) ) > 0;
		$has_title_field   = sizeof( self::get_fields_by_type( $form, array( 'post_title' ) ) ) > 0;
		$post              = false;

		//if a post field was configured with a content or title template, process template
		if ( ( rgar( $form, 'postContentTemplateEnabled' ) && $has_content_field ) || ( rgar( $form, 'postTitleTemplateEnabled' ) && $has_title_field ) ) {

			$post = get_post( $post_id );

			if ( rgar( $form, 'postContentTemplateEnabled' ) && $has_content_field ) {
				$post_content = self::process_post_template( $form['postContentTemplate'], 'post_content', $post_images, $post_data, $form, $lead );

				//updating post content
				$post->post_content = $post_content;
			}

			if ( rgar( $form, 'postTitleTemplateEnabled' ) && $has_title_field ) {
				$post_title = self::process_post_template( $form['postTitleTemplate'], 'post_title', $post_images, $post_data, $form, $lead );

				//updating post
				$post->post_title = $post_title;
				$post->post_name  = $post_title;
			}
		}

		// update post status back to original status (if not draft)
		if ( $post_status != 'draft' ) {
			$post              = is_object( $post ) ? $post : get_post( $post_id );
			$post->post_status = $post_status;
		}

		// if post has been modified since creation, save updates
		if ( is_object( $post ) ) {
			GFCommon::log_debug( 'GFFormsModel::create_post(): Updating post.' );
			wp_update_post( $post );
		}


		//adding post format
		if ( current_theme_supports( 'post-formats' ) && rgar( $form, 'postFormat' ) ) {

			$formats     = get_theme_support( 'post-formats' );
			$post_format = rgar( $form, 'postFormat' );

			if ( is_array( $formats ) ) {
				$formats = $formats[0];
				if ( in_array( $post_format, $formats ) ) {
					set_post_format( $post_id, $post_format );
				} else if ( '0' == $post_format ) {
					set_post_format( $post_id, false );
				}
			}
		}

		// Update the post_id in the database for this entry.
		GFCommon::log_debug( 'GFFormsModel::create_post(): Updating entry with post id.' );
		self::update_lead_property( $lead['id'], 'post_id', $post_id );

		/**
		 * Fires after a post, from a form with post fields, is created
		 *
		 * @param int   $form['id'] The ID of the form where the new post was created
		 * @param int   $post_id    The new Post ID created after submission
		 * @param array $lead       The Lead Object
		 * @param array $form       The Form Object for the form used to create the post
		 */
		gf_do_action( array( 'gform_after_create_post', $form['id'] ), $post_id, $lead, $form );

		return $post_id;
	}

	/**
	 * Process any merge tags and shortcodes found in the template.
	 *
	 * @param string $template The template.
	 * @param string $field_type The field type currently being processed. Possible values: post_custom_field, post_content, or post_title.
	 * @param array $post_images The uploaded post images.
	 * @param array $post_data The post data prepared from the current entry.
	 * @param array $form The form currently being processed.
	 * @param array $entry The entry currently being processed.
	 *
	 * @return string
	 */
	public static function process_post_template( $template, $field_type, $post_images, $post_data, $form, $entry ) {
		GFCommon::log_debug( __METHOD__ . "(): Processing {$field_type} template." );

		//replacing post image variables
		$template = GFCommon::replace_variables_post_image( $template, $post_images, $entry );

		//replacing all other variables
		$template = GFCommon::replace_variables( $template, $form, $entry, false, false, false );

		if ( $field_type != 'post_content' ) {
			$process_template_shortcodes = true;

			/**
			 * Allow shortcode processing of custom field and post title templates to be disabled.
			 *
			 * @param boolean $process_template_shortcodes Should the shortcodes be processed? Default is true.
			 * @param string $field_type The field type currently being processed. Possible values: post_custom_field, post_content, or post_title.
			 * @param array $post_data The post data prepared from the current entry.
			 * @param array $form The form currently being processed.
			 * @param array $entry The entry currently being processed.
			 *
			 * @since 2.0.0.4
			 */
			$process_template_shortcodes = apply_filters( 'gform_process_template_shortcodes_pre_create_post', $process_template_shortcodes, $field_type, $post_data, $form, $entry );
			$process_template_shortcodes = apply_filters( 'gform_process_template_shortcodes_pre_create_post_' . $form['id'], $process_template_shortcodes, $field_type, $post_data, $form, $entry );


			if ( $process_template_shortcodes ) {
				$template = do_shortcode( $template );
			}
		}

		return $template;
	}

	private static function get_custom_field( $form, $meta_name, $meta_index ) {
		$custom_fields = self::get_fields_by_type( $form, array( 'post_custom_field' ) );

		$index = 0;
		foreach ( $custom_fields as $field ) {
			if ( $field->postCustomFieldName == $meta_name ) {
				if ( $meta_index == $index ) {
					return $field;
				}
				$index ++;
			}
		}

		return false;
	}

	private static function copy_post_image( $url, $post_id ) {
		$time = current_time( 'mysql' );

		if ( $post = get_post( $post_id ) ) {
			if ( substr( $post->post_date, 0, 4 ) > 0 ) {
				$time = $post->post_date;
			}
		}

		//making sure there is a valid upload folder
		if ( ! ( ( $upload_dir = wp_upload_dir( $time ) ) && false === $upload_dir['error'] ) ) {
			return false;
		}

		$form_id = get_post_meta( $post_id, '_gform-form-id', true );

		/**
		 * Filter the media upload location.
		 *
		 * @param array $upload_dir The current upload directory’s path and url.
		 * @param int $form_id The ID of the form currently being processed.
		 * @param int $post_id The ID of the post created from the entry currently being processed.
		 */
		$upload_dir = gf_apply_filters( 'gform_media_upload_path', $form_id, $upload_dir, $form_id, $post_id );

		if ( ! file_exists( $upload_dir['path'] ) ) {
			if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
				return false;
			}
		}

		$name     = basename( $url );
		$filename = wp_unique_filename( $upload_dir['path'], $name );

		// the destination path
		$new_file = $upload_dir['path'] . "/$filename";

		// the source path
		$y                = substr( $time, 0, 4 );
		$m                = substr( $time, 5, 2 );
		$target_root      = self::get_upload_path( $form_id ) . "/$y/$m/";
		$target_root_url  = self::get_upload_url( $form_id ) . "/$y/$m/";
		$upload_root_info = array( 'path' => $target_root, 'url' => $target_root_url );
		$upload_root_info = gf_apply_filters( 'gform_upload_path', $form_id, $upload_root_info, $form_id );
		$path             = str_replace( $upload_root_info['url'], $upload_root_info['path'], $url );

		// copy the file to the destination path
		if ( ! copy( $path, $new_file ) ) {
			return false;
		}

		// Set correct file permissions
		$stat  = stat( dirname( $new_file ) );
		$perms = $stat['mode'] & 0000666;
		@ chmod( $new_file, $perms );

		// Compute the URL
		$url = $upload_dir['url'] . "/$filename";

		if ( is_multisite() ) {
			delete_transient( 'dirsize_cache' );
		}

		$type = wp_check_filetype( $new_file );

		return array( 'file' => $new_file, 'url' => $url, 'type' => $type['type'] );

	}

	public static function media_handle_upload( $url, $post_id, $post_data = array() ) {

		// WordPress Administration API required for the media_handle_upload() function.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );

		$name = basename( $url );

		$file = self::copy_post_image( $url, $post_id );

		if ( ! $file ) {
			GFCommon::log_debug( __METHOD__ . '(): Image could not be copied to the media directory.' );

			return false;
		}

		$name_parts = pathinfo( $name );
		$name       = trim( substr( $name, 0, - ( 1 + strlen( $name_parts['extension'] ) ) ) );

		$url     = $file['url'];
		$type    = $file['type'];
		$file    = $file['file'];
		$title   = $name;
		$content = '';

		// use image exif/iptc data for title and caption defaults if possible
		if ( $image_meta = @wp_read_image_metadata( $file ) ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
				$title = $image_meta['title'];
			}
			if ( trim( $image_meta['caption'] ) ) {
				$content = $image_meta['caption'];
			}
		}

		// Construct the attachment array
		$attachment = array_merge(
			array(
				'post_mime_type' => $type,
				'guid'           => $url,
				'post_parent'    => $post_id,
				'post_title'     => $title,
				'post_content'   => $content,
			), $post_data
		);

		// Save the data
		$id = wp_insert_attachment( $attachment, $file, $post_id );
		if ( ! is_wp_error( $id ) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		}

		GFCommon::log_debug( __METHOD__ . '(): Image copied to the media directory. Result from wp_insert_attachment(): ' . print_r( $id, 1 ) );

		return $id;
	}

	public static function save_input( $form, $field, &$lead, $current_fields, $input_id ) {

		if ( isset( $field->fields ) && is_array( $field->fields ) ) {
			foreach( $field->fields as $sub_field ) {
				$inputs = $sub_field->get_entry_inputs();
				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {
						self::save_input( $form, $sub_field, $lead, $current_fields, $input['id'] );
					}
				} else {
					self::save_input( $form, $sub_field, $lead, $current_fields, $sub_field->id );
				}
				foreach ( $current_fields as $current_field ) {
					if ( intval( $current_field->meta_key ) == $sub_field->id && ! isset( $current_field->update ) ) {
						$current_field->delete = true;
						$result = self::queue_batch_field_operation( $form, $lead, $sub_field, $current_field->id, $current_field->meta_key, '', $current_field->item_index );
						GFCommon::log_debug( __METHOD__ . "(): Deleting: {$field->label}(#{$sub_field->id}{$current_field->item_index} - {$field->type}). Result: " . var_export( $result, 1 ) );
					}
				}
			}
			return;
		}

		$input_name = 'input_' . str_replace( '.', '_', $input_id );

		if ( $field->enableCopyValuesOption && rgpost( 'input_' . $field->id . '_copy_values_activated' ) ) {
			$source_field_id   = $field->copyValuesOptionField;
			$source_input_name = str_replace( 'input_' . $field->id, 'input_' . $source_field_id, $input_name );
			$value             = rgpost( $source_input_name );
		} else {
			$value = rgpost( $input_name );
		}

		$value = self::maybe_trim_input( $value, $form['id'], $field );

		//ignore file upload when nothing was sent in the admin
		//ignore post fields in the admin
		$type           = self::get_input_type( $field );
		$multiple_files = $field->multipleFiles;
		$uploaded_files = GFFormsModel::$uploaded_files;
		$form_id        = $form['id'];
		if ( rgget( 'view' ) == 'entry' && $type == 'fileupload' && ( ( ! $multiple_files && empty( $_FILES[ $input_name ]['name'] ) ) || ( $multiple_files && ! isset( $uploaded_files[ $form_id ][ $input_name ] ) ) ) ) {
			return;
		} else if ( rgget( 'view' ) == 'entry' && in_array( $field->type, array( 'post_category', 'post_title', 'post_content', 'post_excerpt', 'post_tags', 'post_custom_field', 'post_image' ) ) ) {
			return;
		}

		$is_form_editor = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin = $is_form_editor || $is_entry_detail;

		if ( empty( $value ) && $field->is_administrative() && ! $is_admin ) {
			$value = self::get_default_value( $field, $input_id );
		}

		self::queue_save_input_value( $value, $form, $field, $lead, $current_fields, $input_id );
	}

	/**
	 * Queues the input value for saving.
	 *
	 * @since 2.4
	 *
	 * @param string|array $value
	 * @param array        $form
	 * @param GF_Field     $field
	 * @param array        $lead
	 * @param array        $current_fields
	 * @param string       $input_id
	 * @param string       $item_index
	 */
	public static function queue_save_input_value( $value, $form, $field, &$lead, $current_fields, $input_id, $item_index = '' ) {

		$input_name = 'input_' . str_replace( '.', '_', $input_id );
		if ( is_array( $value ) && ! ( $field->is_value_submission_array() && ! is_array( $value[0] ) ) ) {
			foreach ( $value as $i => $v ) {
				$new_item_index = $item_index . '_' . $i;
				if ( is_array( $v ) && ! ( $field->is_value_submission_array() && ! is_array( $v[0] ) ) ) {
					self::queue_save_input_value( $v, $form, $field, $lead, $current_fields, $input_id, $new_item_index );
					continue;
				}
				//processing values so that they are in the correct format for each input type
				$v = self::prepare_value( $form, $field, $v, $input_name, rgar( $lead, 'id' ) );

				$lead_detail_id               = self::get_lead_detail_id( $current_fields, $input_id, $new_item_index );
				$result                       = self::queue_batch_field_operation( $form, $lead, $field, $lead_detail_id, $input_id, $v, $new_item_index );
				GFCommon::log_debug( __METHOD__ . "(): Saving: {$field->label}(#{$input_id}{$item_index} - {$field->type}). Result: " . var_export( $result, 1 ) );
				foreach ( $current_fields as $current_field ) {
					if ( $current_field->meta_key == $input_id && $current_field->item_index == $new_item_index ) {
						$current_field->update = true;
					}
				}
			}

		} else {
			//processing values so that they are in the correct format for each input type
			$value = self::prepare_value( $form, $field, $value, $input_name, rgar( $lead, 'id' ), $lead );

			//ignore fields that have not changed
			if ( $lead != null && isset( $lead[ $input_id ] ) && $value === rgget( (string) $input_id, $lead ) ) {
				return;
			}

			$lead_detail_id = self::get_lead_detail_id( $current_fields, $input_id );
			$result         = self::queue_batch_field_operation( $form, $lead, $field, $lead_detail_id, $input_id, $value );
			GFCommon::log_debug( __METHOD__ . "(): Queued field operation: {$field->label}(#{$input_id} - {$field->type})." );

		}
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
		return self::update_entry_field_value( $form, $lead, $field, $lead_detail_id, $input_id, $value );
	}

	/**
	 * Updates an existing field value in the database.
	 *
	 * @since 2.3
	 *
	 * @param array $form
	 * @param array $entry
	 * @param GF_Field $field
	 * @param int $entry_meta_id
	 * @param string $input_id
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function update_entry_field_value( $form, $entry, $field, $entry_meta_id, $input_id, $value, $item_index = '' ) {
		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::update_lead_field_value( $form, $entry, $field, $entry_meta_id, $input_id, $value );
		}

		/**
		 * Filter the value before it's saved to the database.
		 *
		 * @since 1.5.0
		 * @since 1.8.6 Added the $input_id parameter.
		 * @since 1.9.14 Added form and field specific versions.
		 *
		 * @param string|array $value The fields input value.
		 * @param array $entry The current entry object.
		 * @param GF_Field $field The current field object.
		 * @param array $form The current form object.
		 * @param string $input_id The ID of the input being saved or the field ID for single input field types.
		 */
		$value = apply_filters( 'gform_save_field_value', $value, $entry, $field, $form, $input_id );
		$value = apply_filters( "gform_save_field_value_{$form['id']}", $value, $entry, $field, $form, $input_id );

		if ( is_object( $field ) ) {
			$value = apply_filters( "gform_save_field_value_{$form['id']}_{$field->id}", $value, $entry, $field, $form, $input_id );
		}

		if ( is_array( $value ) ) {
			GFCommon::log_debug( __METHOD__ . '(): bailing. value is an array.' );
			return false;
		}

		$entry_id                = $entry['id'];
		$form_id                = $form['id'];
		$entry_meta_table_name      = self::get_entry_meta_table_name();

		// Add emoji support.
		if ( version_compare( get_bloginfo( 'version' ), '4.2', '>=' ) ) {

			// Get charset for lead detail value column .
			$charset = $wpdb->get_col_charset( $entry_meta_table_name, 'meta_value' );

			// If entry detail value column is UTF-8, encode emoji.
			if ( 'utf8' === $charset ) {
				$value = wp_encode_emoji( $value );
			}
		}

		if ( ! rgblank( $value ) ) {

			if ( $entry_meta_id > 0 ) {

				$result = $wpdb->update( $entry_meta_table_name, array( 'meta_value' => $value ), array( 'id' => $entry_meta_id ), array( '%s' ), array( '%d' ) );
				if ( false === $result ) {
					return false;
				}

			} else {
				$result = $wpdb->insert( $entry_meta_table_name, array( 'entry_id' => $entry_id, 'form_id' => $form_id, 'meta_key' => $input_id, 'meta_value' => $value, 'item_index' => $item_index ), array( '%d', '%d', '%s', '%s', '%s' ) );
				if ( false === $result ) {
					return false;
				}

			}

		} else {
			// when the value is empty and no $entry_meta_id was set, check if it's a repeater field.
			if ( empty( $entry_meta_id ) && $field instanceof GF_Field_Repeater && isset( $field->fields ) && is_array( $field->fields ) ) {
				foreach ( $field->fields as $subfield ) {
					self::update_entry_field_value( $form, $entry, $subfield, 0, $subfield->id, '' );
				}
			} else {
				// Deleting details for this field
				if ( is_array( $field->inputs ) ) {
					$_input_id = ( false === strpos( $input_id, '.' ) ) ? sprintf( '%d.%%', $input_id ) : $input_id;
					$sql = $wpdb->prepare( "DELETE FROM $entry_meta_table_name WHERE entry_id=%d AND meta_key LIKE %s ", $entry_id, $_input_id );
				} else {
					$sql = $wpdb->prepare( "DELETE FROM $entry_meta_table_name WHERE entry_id=%d AND meta_key = %s ", $entry_id, $input_id );
				}
				if ( $item_index ) {
					$sql .= $wpdb->prepare( ' AND item_index=%s', $item_index );
				}
				$result = $wpdb->query( $sql );
				if ( false === $result ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Returns the SQL to update or insert field values..
	 *
	 * @param array    $form
	 * @param array    $entry
	 * @param GF_Field $field
	 * @param int      $entry_meta_id
	 * @param string   $input_id
	 * @param string   $value
	 * @param string   $item_index
	 *
	 * @return bool
	 */
	public static function queue_batch_field_operation( $form, &$entry, $field, $entry_meta_id, $input_id, $value, $item_index = '' ) {
		/**
		 * Filter the value before it's saved to the database.
		 *
		 * @since 1.5.0
		 * @since 1.8.6 Added the $input_id parameter.
		 * @since 1.9.14 Added form and field specific versions.
		 *
		 * @param string|array $value The fields input value.
		 * @param array $entry The current entry object.
		 * @param GF_Field $field The current field object.
		 * @param array $form The current form object.
		 * @param string $input_id The ID of the input being saved or the field ID for single input field types.
		 */
		$value = apply_filters( 'gform_save_field_value', $value, $entry, $field, $form, $input_id );
		$value = apply_filters( "gform_save_field_value_{$form['id']}", $value, $entry, $field, $form, $input_id );

		if ( is_object( $field ) ) {
			$value = apply_filters( "gform_save_field_value_{$form['id']}_{$field->id}", $value, $entry, $field, $form, $input_id );
		}

		if ( is_array( $value ) ) {
			GFCommon::log_debug( __METHOD__ . '(): bailing. value is an array.' );
			return false;
		}

		$entry[ (string) $input_id . $item_index ] = $value;

		$entry_id = $entry['id'];
		$form_id  = $form['id'];

		if ( ! rgblank( $value ) ) {
			if ( $entry_meta_id > 0 ) {
				self::$_batch_field_updates[] = array( 'meta_value' => $value, 'id' => $entry_meta_id );
			} else {
				self::$_batch_field_inserts[] = array( 'entry_id' => $entry_id, 'form_id' => $form_id, 'meta_key' => $input_id, 'meta_value' => $value, 'item_index' => $item_index );
			}
		} elseif ( $entry_meta_id > 0 && ! in_array( $input_id, GFFormsModel::get_lead_db_columns() ) ) {
			self::$_batch_field_deletes[] = $entry_meta_id;
		}

		return true;
	}

	public static function flush_batch_field_operations() {
		self::$_batch_field_updates = array();
		self::$_batch_field_inserts = array();
		self::$_batch_field_deletes = array();
	}

	public static function begin_batch_field_operations() {
		self::flush_batch_field_operations();
	}


	/**
	 * Performs the update, inserts and deletes registered by queue_batch_field_operation()
	 *
	 * @return array An array of results.
	 */
	public static function commit_batch_field_operations() {
		global $wpdb;

		$meta_table = self::get_entry_meta_table_name();

		$results = array(
			'updates' => null,
			'inserts' => null,
			'deletes' => null,
		);

		// Updates
		if ( ! empty( self::$_batch_field_updates ) ) {
			$values = array();
			foreach ( self::$_batch_field_updates as $update ) {
				$values[] = $wpdb->prepare( '(%s,%s)', $update['id'], $update['meta_value'] );
			}
			$values_str = join( ',', $values );
			$update_sql =  "INSERT INTO {$meta_table} (id,meta_value)
						VALUES {$values_str}
						ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value);";
			$result = $wpdb->query( $update_sql );
			if ( $result === false ) {
				$result = new WP_Error( 'update_error', $wpdb->last_error );
			}
			$results['updates'] = $result;
		}

		// Inserts
		if ( ! empty( self::$_batch_field_inserts ) ) {
			$values = array();
			foreach ( self::$_batch_field_inserts as $insert ) {
				$values[] = $wpdb->prepare( '(%d,%d,%s,%s,%s)', $insert['entry_id'], $insert['form_id'], $insert['meta_key'], $insert['meta_value'], $insert['item_index'] );
			}
			$values_str = join( ',', $values );
			$insert_sql = "INSERT INTO {$meta_table} (entry_id, form_id, meta_key, meta_value, item_index)  VALUES {$values_str};";
			$result = $wpdb->query( $insert_sql );
			if ( $result === false ) {
				$result = new WP_Error( 'insert_error', $wpdb->last_error );
			}
			$results['inserts'] = $result;
		}

		// Deletes
		if ( ! empty( self::$_batch_field_deletes ) ) {
			$in_str_arr    = array_fill( 0, count( self::$_batch_field_deletes ), '%d' );
			$in_str        = join( ',', $in_str_arr );
			$ids = array_map( 'absint', self::$_batch_field_deletes );
			$delete_sql = $wpdb->prepare( "DELETE FROM {$meta_table} WHERE id IN ( {$in_str} )", $ids);
			$result = $wpdb->query( $delete_sql );
			if ( $result === false ) {
				$result = new WP_Error( 'delete_error', $wpdb->last_error );
			}
			$results['deletes'] = $result;
		}

		self::flush_batch_field_operations();

		return $results;
	}

	private static function move_temp_file( $form_id, $tempfile_info ) {
		_deprecated_function( 'move_temp_file', '1.9', 'GF_Field_Fileupload::move_temp_file' );

		$target = self::get_file_upload_path( $form_id, $tempfile_info['uploaded_filename'] );
		$source = self::get_upload_path( $form_id ) . '/tmp/' . $tempfile_info['temp_filename'];

		if ( rename( $source, $target['path'] ) ) {
			self::set_permissions( $target['path'] );

			return $target['url'];
		} else {
			return 'FAILED (Temporary file could not be moved.)';
		}
	}

	public static function set_permissions( $path ) {
		$permission = apply_filters( 'gform_file_permission', 0644, $path );
		if ( $permission ) {
			@chmod( $path, $permission );
		}
	}

	public static function upload_file( $form_id, $file ) {
		_deprecated_function( 'upload_file', '1.9', 'GF_Field_Fileupload::upload_file' );
		$target = self::get_file_upload_path( $form_id, $file['name'] );
		if ( ! $target ) {
			GFCommon::log_debug( 'GFFormsModel::upload_file(): FAILED (Upload folder could not be created.)' );

			return 'FAILED (Upload folder could not be created.)';
		}


		if ( move_uploaded_file( $file['tmp_name'], $target['path'] ) ) {
			GFCommon::log_debug( 'GFFormsModel::upload_file(): Setting permissions on ' . $target['path'] );
			self::set_permissions( $target['path'] );

			return $target['url'];
		} else {
			GFCommon::log_debug( 'GFFormsModel::upload_file(): FAILED (Temporary file could not be copied.)' );

			return 'FAILED (Temporary file could not be copied.)';
		}
	}


	public static function get_upload_root() {
		$dir = wp_upload_dir();

		if ( $dir['error'] ) {
			return null;
		}

		return $dir['basedir'] . '/gravity_forms/';
	}

	public static function get_upload_url_root() {
		$dir = wp_upload_dir();

		if ( $dir['error'] ) {
			return null;
		}

		return $dir['baseurl'] . '/gravity_forms/';
	}

	public static function get_upload_path( $form_id ) {
		$form_id = absint( $form_id );
		return self::get_upload_root() . $form_id . '-' . wp_hash( $form_id );
	}

	public static function get_upload_url( $form_id ) {
		$form_id = absint( $form_id );
		$dir = wp_upload_dir();

		return $dir['baseurl'] . "/gravity_forms/$form_id" . '-' . wp_hash( $form_id );
	}

	public static function get_file_upload_path( $form_id, $file_name ) {

		if ( get_magic_quotes_gpc() ) {
			$file_name = stripslashes( $file_name );
		}

		$form_id = absint( $form_id );

		// Where the file is going to be placed
		// Generate the yearly and monthly dirs
		$time            = current_time( 'mysql' );
		$y               = substr( $time, 0, 4 );
		$m               = substr( $time, 5, 2 );
		$default_target_root     = self::get_upload_path( $form_id ) . "/$y/$m/";
		$default_target_root_url = self::get_upload_url( $form_id ) . "/$y/$m/";

		//adding filter to upload root path and url
		$upload_root_info = array( 'path' => $default_target_root, 'url' => $default_target_root_url );
		$upload_root_info = gf_apply_filters( array( 'gform_upload_path', $form_id ), $upload_root_info, $form_id );

		$target_root     = $upload_root_info['path'];
		$target_root_url = $upload_root_info['url'];

		$target_root = trailingslashit( $target_root );

		if ( ! is_dir( $target_root ) ) {
			if ( ! wp_mkdir_p( $target_root ) ) {
				return false;
			}

			// Adding index.html files to all subfolders.
			if ( $default_target_root != $target_root && ! file_exists( $target_root . 'index.html' ) ) {
				GFCommon::recursive_add_index_file( $target_root );
			} elseif ( ! file_exists( self::get_upload_root() . '/index.html' ) ) {
				GFCommon::recursive_add_index_file( self::get_upload_root() );
			} elseif ( ! file_exists( self::get_upload_path( $form_id ) . '/index.html' ) ) {
				GFCommon::recursive_add_index_file( self::get_upload_path( $form_id ) );
			} elseif ( ! file_exists( self::get_upload_path( $form_id ) . "/$y/index.html" ) ) {
				GFCommon::recursive_add_index_file( self::get_upload_path( $form_id ) . "/$y" );
			} else {
				GFCommon::recursive_add_index_file( self::get_upload_path( $form_id ) . "/$y/$m" );
			}
		}

		//Add the original filename to our target path.
		//Result is "uploads/filename.extension"
		$file_info = pathinfo( $file_name );
		$extension = rgar( $file_info, 'extension' );
		if ( ! empty( $extension ) ) {
			$extension = '.' . $extension;
		}
		$file_name = basename( $file_info['basename'], $extension );

		$file_name = sanitize_file_name( $file_name );

		$counter     = 1;
		$target_path = $target_root . $file_name . $extension;
		while ( file_exists( $target_path ) ) {
			$target_path = $target_root . $file_name . "$counter" . $extension;
			$counter ++;
		}

		//Remove '.' from the end if file does not have a file extension
		$target_path = trim( $target_path, '.' );

		//creating url
		$target_url = str_replace( $target_root, $target_root_url, $target_path );

		return array( 'path' => $target_path, 'url' => $target_url );
	}

	public static function get_tables() {
		return array(
			self::get_form_view_table_name(),
			self::get_meta_table_name(),
			self::get_form_table_name(),
			self::get_form_revisions_table_name(),
			self::get_entry_table_name(),
			self::get_entry_meta_table_name(),
			self::get_entry_notes_table_name(),
			self::get_draft_submissions_table_name(),
			self::get_rest_api_keys_table_name(),
		);
	}

	public static function drop_tables() {
		global $wpdb;
		remove_filter( 'query', array( 'GFForms', 'filter_query' ) );
		foreach ( GF_Forms_Model_Legacy::get_legacy_tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
		foreach ( self::get_tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
		add_filter( 'query', array( 'GFForms', 'filter_query' ) );
	}

	/**
	 * Target for the wpmu_drop_tables filter. Adds all tables for Gravity Forms and the Add-On Framework to list
	 * of tables to drop when a site is deleted.
	 *
	 * @param $drop_tables
	 *
	 * @return array
	 */
	public static function mu_drop_tables( $drop_tables ) {
		global $wpdb;

		$addon_tables = array(
			$wpdb->prefix . 'gf_addon_feed',
			$wpdb->prefix . 'gf_addon_payment_callback',
			$wpdb->prefix . 'gf_addon_payment_transaction',
		);

		$drop_tables = array_merge( $drop_tables, $addon_tables );

		$core_tables = self::get_tables();

		$drop_tables = array_merge( $drop_tables, $core_tables );

		$legacy_tables = GF_Forms_Model_Legacy::get_legacy_tables();

		$drop_tables = array_merge( $drop_tables, $legacy_tables );

		// Prevent the legacy table query notice when they are dropped by wp_uninitialize_site().
		remove_filter( 'query', array( 'GFForms', 'filter_query' ) );

		return $drop_tables;
	}

	public static function insert_form_view( $form_id, $deprecated = null ) {
		global $wpdb;
		$table_name = self::get_form_view_table_name();

		$sql = $wpdb->prepare(
			" SELECT id FROM $table_name
				WHERE form_id=%d
				AND date_created BETWEEN DATE_SUB(utc_timestamp(), INTERVAL 1 DAY) AND utc_timestamp()", $form_id
		);

		$id = $wpdb->get_var( $sql, 0, 0 );

		if ( empty( $id ) ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO $table_name(form_id, date_created, ip) values(%d, utc_timestamp(), %s)", $form_id, '' ) );
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET count = count+1 WHERE id=%d", $id ) );
		}
	}

	public static function is_duplicate( $form_id, $field, $value ) {
		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::is_duplicate( $form_id, $field, $value );
		}

		$entry_meta_table_name = self::get_entry_meta_table_name();
		$entry_table_name        = self::get_entry_table_name();

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

		$inner_sql_template = "SELECT %s as input, ld.entry_id
                                FROM {$entry_meta_table_name} ld
                                INNER JOIN {$entry_table_name} l ON l.id = ld.entry_id\n";


		$inner_sql_template .= "WHERE l.form_id=%d AND ld.form_id=%d
                                AND ld.meta_key = %s
                                AND status='active' AND ld.meta_value = %s";

		$sql = "SELECT count(distinct input) as match_count FROM ( ";

		$input_count = 1;
		if ( is_array( $field->get_entry_inputs() ) ) {
			$input_count = sizeof( $field->inputs );
			$inner_sql = '';
			foreach ( $field->inputs as $input ) {
				$union = empty( $inner_sql ) ? '' : ' UNION ALL ';
				$inner_sql .= $union . $wpdb->prepare( $inner_sql_template, $input['id'], $form_id, $form_id, $input['id'], $value[ $input['id'] ] );
			}
		} else {
			$inner_sql = $wpdb->prepare( $inner_sql_template, $field->id, $form_id, $form_id, $field->id, $value );
		}

		$sql .= $inner_sql . "
                ) as count
                GROUP BY entry_id
                ORDER BY match_count DESC";

		$count = gf_apply_filters( array( 'gform_is_duplicate', $form_id ), $wpdb->get_var( $sql ), $form_id, $field, $value );

		return $count != null && $count >= $input_count;
	}

	public static function get_lead( $lead_id ) {
		$entry = GFAPI::get_entry( $lead_id );
		if ( is_wp_error( $entry ) ) {
			$entry = false;
		}
		return $entry;
	}

	public static function get_entry( $entry_id ) {
		return GFAPI::get_entry( $entry_id );
	}

	public static function get_lead_notes( $lead_id ) {
		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_lead_notes( $lead_id );
		}

		$notes_table = self::get_entry_notes_table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"  SELECT n.id, n.user_id, n.date_created, n.value, n.note_type, ifnull(u.display_name,n.user_name) as user_name, u.user_email
                                                    FROM $notes_table n
                                                    LEFT OUTER JOIN $wpdb->users u ON n.user_id = u.id
                                                    WHERE entry_id=%d ORDER BY id", $lead_id
			)
		);
	}

	public static function refresh_lead_field_value( $lead_id, $field_id ) {
		if ( version_compare( GFForms::$version, '2.3-dev', '>=' ) ) {
			_deprecated_function( 'GFFormsModel::refresh_lead_field_value', '2.3' );
		}
		$cache_key = 'GFFormsModel::get_lead_field_value_' . $lead_id . '_' . $field_id;
		GFCache::delete( $cache_key );
	}

	/**
	 * @param $lead
	 * @param $field GF_Field
	 *
	 * @return array|bool|mixed|string|null
	 */
	public static function get_lead_field_value( $lead, $field ) {

		if ( empty( $lead ) ) {
			return null;
		}

		$field_id = $field instanceof GF_Field ? $field->id : $field['id'];

		$value = array();

		$inputs = $field instanceof GF_Field ? $field->get_entry_inputs() : rgar( $field, 'inputs' );

		if ( is_array( $inputs ) ) {
			// making sure values submitted are sent in the value even if
			// there isn't an input associated with it
			$lead_field_keys = array_keys( $lead );
			natsort( $lead_field_keys );
			foreach ( $lead_field_keys as $input_id ) {
				if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field_id ) ) {
					$val = $lead[ $input_id ];
					$value[ $input_id ] = $val;
				}
			}
		} else {
			$value = rgget( $field_id, $lead );
		}

		// filtering lead value
		$value = apply_filters( 'gform_get_field_value', $value, $lead, $field );

		return $value;
	}

	/**
	 *
	 * @deprecated 2.0
	 * @param      $lead
	 * @param      $field_number
	 * @param      $form
	 * @param bool $apply_filter
	 *
	 * @return mixed|null|string
	 */
	public static function get_field_value_long( $lead, $field_number, $form, $apply_filter = true ) {
		_deprecated_function( 'get_field_value_long', '2.0', 'get_lead_field_value' );

		global $wpdb;
		$detail_table_name = self::get_lead_details_table_name();
		$long_table_name   = self::get_lead_details_long_table_name();

		$sql = $wpdb->prepare(
			" SELECT l.value FROM $detail_table_name d
                                INNER JOIN $long_table_name l ON l.lead_detail_id = d.id
                                WHERE lead_id=%d AND field_number BETWEEN %s AND %s", $lead['id'], doubleval( $field_number ) - 0.0001, doubleval( $field_number ) + 0.0001
		);

		$val = $wpdb->get_var( $sql );

		//running aform_get_input_value when needed
		if ( $apply_filter ) {
			$field    = RGFormsModel::get_field( $form, $field_number );
			$input_id = (string) $field_number == (string) $field->id ? '' : $field_number;
			$val      = gf_apply_filters( array( 'gform_get_input_value', $field->formId, $field->id, $input_id ), $val, $lead, $field, $input_id );
		}

		return $val;
	}

	/**
	 * @param $meta_key
	 * @param $meta_value
	 *
	 * @return array
	 */
	public static function get_leads_by_meta( $meta_key, $meta_value ) {
		return self::get_entries_by_meta( $meta_key, $meta_value );
	}

	/**
	 * Searches entries by entry meta
	 *
	 * @since 2.3
	 *
	 * @param $meta_key
	 * @param $meta_value
	 *
	 * @return array
	 */
	public static function get_entries_by_meta( $meta_key, $meta_value ) {
		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_leads_by_meta( $meta_key, $meta_value );
		}
		$args = array(
			'meta_key'     => $meta_key,
			'meta_value'   => $meta_value,
			'meta_compare' => '=',
		);
		$query = new GF_Query( $args );
		return $query->entries;
	}

	/**
	 *
	 * @deprecated 2.3
	 *
	 * @param $form_id
	 * @param int $sort_field_number
	 * @param string $sort_direction
	 * @param string $search
	 * @param int $offset
	 * @param int $page_size
	 * @param null $star
	 * @param null $read
	 * @param bool $is_numeric_sort
	 * @param null $start_date
	 * @param null $end_date
	 * @param string $status
	 * @param bool $payment_status
	 *
	 * @return mixed
	 */
	public static function get_leads( $form_id, $sort_field_number = 0, $sort_direction = 'DESC', $search = '', $offset = 0, $page_size = 30, $star = null, $read = null, $is_numeric_sort = false, $start_date = null, $end_date = null, $status = 'active', $payment_status = false ) {

		_deprecated_function( 'GFFormsModel::get_leads', '2.3', 'GFAPI::get_entries' );

		$search_criteria = array(
			'status' => $status,
		);

		if ( ! empty( $search ) ) {
			$search_criteria['field_filters'][] = array( 'value' => $search );
		}

		if ( ! is_null( $star ) ) {
			$search_criteria['field_filters'][] = array( 'is_starred' => $star );
		}

		if ( ! is_null( $read ) ) {
			$search_criteria['field_filters'][] = array( 'is_read' => $read );
		}

		if ( $payment_status ) {
			$search_criteria['field_filters'][] = array( 'payment_status' => $read );
		}

		$sorting = array(
			'key' => $sort_field_number,
			'direction' => $sort_direction
		);

		if ( $is_numeric_sort ) {
			$sorting['is_numeric'] = true;
		}

		$paging = array(
			'offset' => $offset,
			'page_size' => $page_size,
		);

		if ( ! is_null( $start_date ) ) {
			$search_criteria['start_date'] = $start_date;
		}

		if ( ! is_null( $end_date ) ) {
			$search_criteria['end_date'] = $end_date;
		}

		return GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );
	}


	/**
	 *
	 * @deprecated 2.3
	 *
	 * @param $args
	 *
	 * @return string
	 */
	public static function get_leads_where_sql( $args ) {

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_leads_where_sql( $args ) ;
		}

		return self::get_entries_where_sql( $args );
	}

	/**
	 * @deprecated 2.3
	 * @param $results
	 *
	 * @return array
	 */
	public static function build_lead_array( $results ) {
		return GF_Forms_Model_Legacy::build_lead_array( $results );
	}


	/***
	 * Saves the Gravity Forms license key to the database and registers the site and license key with the Gravity Forms licensing server.
	 *
	 * @since 1.0
	 *
	 * @param string $new_key Gravity Forms license key to be saved.
	 */
	public static function save_key( $new_key ) {

		$new_key = trim( $new_key );
		$previous_key = get_option( 'rg_gforms_key' );

		if ( empty( $new_key ) ) {

			delete_option( 'rg_gforms_key' );

			GFCommon::update_site_registration( '' );

		} else if ( $previous_key != $new_key ) {

			$key_md5 = md5( $new_key );

			// Saving new key
			update_option( 'rg_gforms_key', $key_md5 );

			// Updating site registration with Gravity Server
			GFCommon::update_site_registration( $key_md5, true );

		} else {

			// Updating site registration even if keys did not change.
			// This will boost site registration from sites that already have a license key entered
			GFCommon::update_site_registration( $new_key, true );

		}

	}

	/**
	 * Use GFAPI::count_entries() instead.
	 *
	 * @deprecated 2.3.0.1
	 *
	 *
	 * @param $form_id
	 * @param $search
	 * @param null $star
	 * @param null $read
	 * @param null $start_date
	 * @param null $end_date
	 * @param null $status
	 * @param null $payment_status
	 *
	 * @return null|string
	 */
	public static function get_lead_count( $form_id, $search, $star = null, $read = null, $start_date = null, $end_date = null, $status = null, $payment_status = null ) {

		_deprecated_function( 'GFFormsModel::get_lead_count', '2.3.0.1', 'GFAPI::count_entries');

		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_lead_count( $form_id, $search, $star, $read, $start_date, $end_date, $status, $payment_status ) ;
		}

		if ( ! is_numeric( $form_id ) ) {
			return '';
		}

		$entry_meta_table_name = self::get_entry_meta_table_name();
		$entry_table_name   = self::get_entry_table_name();

		$where = self::get_entries_where_sql( compact( 'form_id', 'search', 'status', 'star', 'read', 'start_date', 'end_date', 'payment_status', 'is_default' ) );

		$sql = "SELECT count(distinct l.id)
                FROM $entry_table_name l
                INNER JOIN $entry_meta_table_name ld ON l.id = ld.entry_id
                $where";

		return $wpdb->get_var( $sql );
	}

	/**
	 * Returns the WHERE clause for an entry search.
	 *
	 * This function is not used and is only included for backwards compatibility. Use GFAPI::count_entries() instead.
	 *
	 * @deprecated 2.3.0.1
	 *
	 * @since 2.3.0.1
	 *
	 * @param $args
	 *
	 * @return string
	 */
	public static function get_entries_where_sql( $args ) {

		_doing_it_wrong( 'GFFormsModel::get_entries_where_sql', 'Use GFAPI::count_entries instead', '2.3.0.1');

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
			$where[] = $wpdb->prepare( 'meta_value LIKE %s', "%$search%" );
		} else if ( $search ) {
			$where[] = $wpdb->prepare( 'd.meta_value LIKE %s', "%$search%" );
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

	/**
	 *
	 *
	 * @param $form_id
	 * @param $search
	 * @param null $star
	 * @param null $read
	 * @param null $start_date
	 * @param null $end_date
	 * @param null $status
	 * @param null $payment_status
	 *
	 * @return array|string
	 */
	public static function get_lead_ids( $form_id, $search, $star = null, $read = null, $start_date = null, $end_date = null, $status = null, $payment_status = null ) {
		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_lead_ids( $form_id, $search, $star, $read, $start_date, $end_date, $status, $payment_status ) ;
		}

		if ( ! is_numeric( $form_id ) ) {
			return '';
		}

		$entry_meta_table_name = self::get_entry_meta_table_name();
		$entry_table_name   = self::get_entry_table_name();

		$where = self::get_entries_where_sql( compact( 'form_id', 'search', 'status', 'star', 'read', 'start_date', 'end_date', 'payment_status', 'is_default' ) );

		$sql = "SELECT distinct l.id
                FROM $entry_table_name l
                INNER JOIN $entry_meta_table_name ld ON l.id = ld.entry_id
                $where";

		$rows = $wpdb->get_results( $sql );

		if ( empty( $rows ) ) {
			return array();
		}

		$entry_ids = array();

		foreach ( $rows as $row ) {
			$entry_ids[] = $row->id;
		}

		return $entry_ids;

	}

	public static function get_grid_columns( $form_id, $input_label_only = false ) {
		$form      = self::get_form_meta( $form_id );
		$field_ids = self::get_grid_column_meta( $form_id );

		if ( ! is_array( $field_ids ) ) {
			$field_ids = array();

			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */

				//loading post category fields with choices and inputs
				if ( $field->type == 'post_category' ) {
					$field = GFCommon::add_categories_as_choices( $field, '' );
				}

				if ( $field->displayOnly || $field->get_input_type() == 'list' || $field->get_input_type() == 'repeater' ) {
					continue;
				}

				$inputs = $field->get_entry_inputs();
				if ( is_array( $inputs ) ) {
					if ( $field->type == 'name' ) {
						$field_ids[] = $field->id . '.3'; //adding first name
						$field_ids[] = $field->id . '.6'; //adding last name
					} else {
						foreach ( $inputs as $input ) {
							if ( rgar( $input, 'isHidden' ) ) {
								continue;
							}

							$field_ids[] = $input['id']; //getting first input
							break;
						}
					}
				} else {
					$field_ids[] = $field->id;
				}

				if ( count( $field_ids ) >= 5 ) {
					break;
				}
			}
			//adding default entry meta columns
			$entry_metas = GFFormsModel::get_entry_meta( $form_id );
			foreach ( $entry_metas as $key => $entry_meta ) {
				if ( rgar( $entry_meta, 'is_default_column' ) ) {
					$field_ids[] = $key;
				}
			}
		}

		$columns    = array();
		$entry_meta = self::get_entry_meta( $form_id );
		foreach ( $field_ids as $field_id ) {

			switch ( $field_id ) {
				case 'id' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'Entry Id', 'gravityforms' ), 'type' => 'id' );
					break;
				case 'ip' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'User IP', 'gravityforms' ), 'type' => 'ip' );
					break;
				case 'date_created' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'Entry Date', 'gravityforms' ), 'type' => 'date_created' );
					break;
				case 'source_url' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'Source Url', 'gravityforms' ), 'type' => 'source_url' );
					break;
				case 'payment_status' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'Payment Status', 'gravityforms' ), 'type' => 'payment_status' );
					break;
				case 'transaction_id' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'Transaction Id', 'gravityforms' ), 'type' => 'transaction_id' );
					break;
				case 'payment_date' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'Payment Date', 'gravityforms' ), 'type' => 'payment_date' );
					break;
				case 'payment_amount' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'Payment Amount', 'gravityforms' ), 'type' => 'payment_amount' );
					break;
				case 'created_by' :
					$columns[ $field_id ] = array( 'label' => esc_html__( 'User', 'gravityforms' ), 'type' => 'created_by' );
					break;
				case ( ( is_string( $field_id ) || is_int( $field_id ) ) && array_key_exists( $field_id, $entry_meta ) ) :
					$columns[ $field_id ] = array( 'label' => $entry_meta[ $field_id ]['label'], 'type' => $field_id );
					break;
				default :
					$field = self::get_field( $form, $field_id );
					if ( $field ) {
						if ( $field->type === 'consent' ) {
							if ( false !== strpos( $field_id, '.1' ) ) {
								$columns[ strval( $field_id ) ] = array( 'label'     => self::get_label( $field, $field_id, false ),
								                                         'type'      => $field->type,
								                                         'inputType' => $field->inputType
								);
							}
						} else {
							$input_label_only               = apply_filters( 'gform_entry_list_column_input_label_only', $input_label_only, $form, $field );
							$columns[ strval( $field_id ) ] = array( 'label'     => self::get_label( $field, $field_id, $input_label_only ),
							                                         'type'      => $field->type,
							                                         'inputType' => $field->inputType
							);
						}
					}
			}
		}

		return $columns;
	}

	/**
	 * @param GF_Field $field
	 * @param int $input_id
	 * @param bool $input_only
	 *
	 * @return string
	 */
	public static function get_label( $field, $input_id = 0, $input_only = false, $allow_admin_label = true ) {
		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		$field_label = ( GFForms::get_page() ||
		                 RG_CURRENT_PAGE == 'select_columns.php' ||
		                 RG_CURRENT_PAGE == 'print-entry.php' ||
		                 rgget( 'gf_page', $_GET ) == 'select_columns' ||
		                 rgget( 'gf_page', $_GET ) == 'print-entry' ||
		                 $field->get_context_property( 'use_admin_label' )
		               ) && ! empty( $field->adminLabel ) && $allow_admin_label ? $field->adminLabel : $field->label;

		$input = self::get_input( $field, $input_id );

		if ( self::get_input_type( $field ) == 'checkbox' && $input != null ) {
			return $input['label'];
		} else if ( $input != null ) {
			if ( self::get_input_type( $field ) === 'consent' &&
			     ( RG_CURRENT_PAGE == 'select_columns.php' ||
			       RG_CURRENT_PAGE == 'print-entry.php' ||
			       rgget( 'gf_page', $_GET ) == 'select_columns' ||
			       rgget( 'gf_page', $_GET ) == 'print-entry' ||
			       GFForms::get_page() === 'entry_list'
			     ) ) {
				return $field_label;
			}

			$input_label = rgar( $input, 'customLabel', rgar( $input, 'label' ) );

			return $input_only ? $input_label : $field_label . ' (' . $input_label . ')';
		} else {
			return $field_label;
		}
	}

	/**
	 * @param GF_Field $field
	 * @param          $id
	 *
	 * @return null
	 */
	public static function get_input( $field, $id ) {
		if ( is_array( $field->inputs ) ) {
			foreach ( $field->inputs as $input ) {
				if ( $input['id'] == $id ) {
					return $input;
				}
			}
		}

		return null;
	}

	public static function has_input( $field, $input_id ) {
		if ( ! is_array( $field->inputs ) ) {
			return false;
		} else {
			foreach ( $field->inputs as $input ) {
				if ( $input['id'] == $input_id ) {
					return true;
				}
			}

			return false;
		}
	}

	public static function get_current_page_url( $force_ssl = false ) {
		$pageURL = 'http';
		if ( RGForms::get( 'HTTPS', $_SERVER ) == 'on' || $force_ssl ) {
			$pageURL .= 's';
		}
		$pageURL .= '://';

		$pageURL .= RGForms::get( 'HTTP_HOST', $_SERVER ) . rgget( 'REQUEST_URI', $_SERVER );

		return $pageURL;
	}

	public static function get_submitted_fields( $form_id ) {
		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_submitted_fields( $form_id );
		}

		$entry_meta_table_name = self::get_entry_meta_table_name();
		$field_list             = '';
		$fields                 = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT meta_key FROM $entry_meta_table_name WHERE form_id=%d", $form_id ) );
		foreach ( $fields as $field ) {
			$field_list .= intval( $field->meta_key ) . ',';
		}

		if ( ! empty( $field_list ) ) {
			$field_list = substr( $field_list, 0, strlen( $field_list ) - 1 );
		}

		return $field_list;
	}

	/**
	 * Returns the field object for the requested field or input ID from the supplied or specified form.
	 *
	 * @since  2.3 Updated to support being passed the form id or form object as the first parameter.
	 * @since  unknown.
	 * @access public
	 *
	 * @param array|int  $form_or_id The Form Object or ID.
	 * @param string|int $field_id   The field or input ID.
	 *
	 * @return GF_Field|null
	 */
	public static function get_field( $form_or_id, $field_id ) {
		$form = is_numeric( $form_or_id ) ? self::get_form_meta( $form_or_id ) : $form_or_id;

		if ( ! isset( $form['fields'] ) || ! isset( $form['id'] ) || ! is_array( $form['fields'] ) ) {
			return null;
		}

		if ( is_numeric( $field_id ) ) {
			// Removing floating part of field (i.e 1.3 -> 1) to return field by input id.
			$field_id = intval( $field_id );
		}

		global $_fields;
		$key = $form['id'] . '_' . $field_id;
		$return = null;
		if (isset( $_fields[ $key ] ) ) {
			return $_fields[ $key ];
		}

		$_fields[ $key ] = null;
		foreach ( $form['fields'] as $field ) {
			if ( $field->id == $field_id ) {
				$_fields[ $key ] = $field;
				$return          = $field;
				break;
			} elseif ( is_array( $field->fields ) ) {
				$sub_field = self::get_sub_field( $field, $field_id );
				if ( $sub_field ) {
					$_fields[ $key ] = $sub_field;
					return $sub_field;
				}

			}
		}

		return $return;
	}

	/**
	 * Returns the field inside a repeater field with the specified ID.
	 *
	 * @since 2.4
	 *
	 * @param GF_Field_Repeater $repeater_field The repeater field.
	 * @param int $field_id                     The field ID.
	 *
	 * @return null|GF_Field
	 */
	public static function get_sub_field( $repeater_field, $field_id ) {
		if ( is_array( $repeater_field->fields ) ) {
			foreach ( $repeater_field->fields as $field ) {
				if ( $field->id == $field_id ) {
					return $field;
				} elseif ( is_array( $field->fields ) ) {
					$f = self::get_sub_field( $field, $field_id );
					if ( $f ) {
						return $f;
					}
				}
			}
		}
		return null;
	}

	public static function is_html5_enabled() {
		return get_option( 'rg_gforms_enable_html5' );
	}

	/**
	 * Return the current lead being processed. Should only be called when a form has been submitted.
	 * If called before the "real" lead has been saved to the database, uses self::create_lead() to create
	 * a temporary lead to work with.
	 */
	public static function get_current_lead() {

		// if a GF submission is not in process, always return false
		if ( ! rgpost( 'gform_submit' ) ) {
			return false;
		}

		if ( ! self::$_current_lead ) {
			$form_id             = absint( rgpost( 'gform_submit' ) );
			$form                = self::get_form_meta( $form_id );
			self::$_current_lead = self::create_lead( $form );
		}

		return self::$_current_lead;
	}

	/**
	 * Set RGFormsModel::$lead for use in hooks where $lead is not explicitly passed.
	 *
	 * @param mixed $lead
	 */
	public static function set_current_lead( $lead ) {
		GFCache::flush();
		self::$_current_lead = $lead;
	}

	/**
	 * v1.7 introduces conditional confirmations. If the form's "confirmations" key is empty, grab the existing confirmation
	 * and populate it in the form's "confirmations" property.
	 *
	 * @param mixed $form
	 * @return array
	 */
	public static function convert_confirmation( $form ) {

		$id = uniqid();

		// convert confirmation to new confirmations format
		$confirmation              = rgar( $form, 'confirmation' );
		$confirmation['id']        = $id;
		$confirmation['name']      = esc_html__( 'Default Confirmation', 'gravityforms' );
		$confirmation['isDefault'] = true;

		$form['confirmations'] = array( $id => $confirmation );

		self::save_form_confirmations( $form['id'], $form['confirmations'] );

		return $form;
	}

	public static function load_confirmations( $form ) {

		$confirmations = self::get_form_confirmations( $form['id'] );

		// if there are no confirmations, convert existing (singular) confirmation (prior to 1.7) to new (plural) confirmations format
		if ( empty( $confirmations ) ) {
			$form = self::convert_confirmation( $form );
		} else {
			$form['confirmations'] = $confirmations;
		}

		return $form;
	}

	public static function get_form_confirmations( $form_id ) {
		global $wpdb;

		$key = get_current_blog_id() . '_' . $form_id;

		if ( isset( self::$_confirmations[ $key ] ) ) {
			return self::$_confirmations[ $key ];
		}

		$tablename     = GFFormsModel::get_meta_table_name();
		$sql           = $wpdb->prepare( "SELECT confirmations FROM $tablename WHERE form_id = %d", $form_id );
		$results       = $wpdb->get_results( $sql, ARRAY_A );
		$confirmations = rgars( $results, '0/confirmations' );

		self::$_confirmations[ $key ] = $confirmations ? self::unserialize( $confirmations ) : array();

		return self::$_confirmations[ $key ];
	}

	public static function save_form_confirmations( $form_id, $confirmations ) {
		return self::update_form_meta( $form_id, $confirmations, 'confirmations' );
	}

	public static function save_form_notifications( $form_id, $notifications ) {
		return self::update_form_meta( $form_id, $notifications, 'notifications' );
	}

	public static function get_form_ids( $active = true, $trash = false ) {
		global $wpdb;
		$table   = self::get_form_table_name();
		$sql     = $wpdb->prepare( "SELECT id from $table where is_active = %d and is_trash = %d", (bool) $active, (bool) $trash );
		$results = $wpdb->get_col( $sql );

		return $results;
	}

	public static function get_entry_meta( $form_ids ) {
		global $_entry_meta;

		if ( $form_ids == 0 ) {
			$form_ids = self::get_form_ids();
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

	public static function set_entry_meta( $lead, $form ) {
		$entry_meta = self::get_entry_meta( $form['id'] );
		$keys       = array_keys( $entry_meta );
		foreach ( $keys as $key ) {
			if ( isset( $entry_meta[ $key ]['update_entry_meta_callback'] ) ) {
				$callback = $entry_meta[ $key ]['update_entry_meta_callback'];
				$value    = call_user_func_array( $callback, array( $key, $lead, $form ) );
				gform_update_meta( $lead['id'], $key, $value );
				$lead[ $key ] = $value;
			}
		}

		return $lead;
	}

	/**
	 *
	 * @param $form_id
	 * @param array $search_criteria
	 * @param null $sorting
	 * @param null $paging
	 *
	 * @return array
	 */
	public static function search_leads( $form_id, $search_criteria = array(), $sorting = null, $paging = null ) {
		return GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );
	}


	public static function search_lead_ids( $form_id, $search_criteria = array() ) {
		return GFAPI::get_entry_ids( $form_id, $search_criteria );
	}

	/**
	 * Returns the gf_entry table field names.
	 *
	 * @since 2.3.2.13 Added date_updated.
	 * @since unknown
	 *
	 * @return array
	 */
	public static function get_lead_db_columns() {
		return array( 'id', 'form_id', 'post_id', 'date_created', 'date_updated', 'is_starred', 'is_read', 'ip', 'source_url', 'user_agent', 'currency', 'payment_status', 'payment_date', 'payment_amount', 'transaction_id', 'is_fulfilled', 'created_by', 'transaction_type', 'status', 'payment_method' );
	}

	/**
	 *
	 * @param $form_id
	 * @param array $search_criteria
	 *
	 * @return null|string
	 */
	public static function count_search_leads( $form_id, $search_criteria = array() ) {
		return GFAPI::count_entries( $form_id, $search_criteria );
	}

	/**
	 * Returns the lead (entry) count for all forms.
	 *
	 * @param string $status
	 *
	 * @return null|string
	 */
	public static function get_lead_count_all_forms( $status = 'active' ) {
		return self::get_entry_count_all_forms( $status );
	}

	/**
	 * Returns the entry count for all forms.
	 *
	 * @param string $status
	 *
	 * @return null|string
	 */
	public static function get_entry_count_all_forms( $status = 'active' ) {
		global $wpdb;

		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_lead_count_all_forms( $status );
		}

		$entry_table_name   = self::get_entry_table_name();

		if ( ! GFCommon::table_exists( $entry_table_name ) ) {
			return 0;
		}

		$sql = $wpdb->prepare( "SELECT count(id)
								FROM $entry_table_name
								WHERE status=%s", $status );

		return $wpdb->get_var( $sql );
	}

	public static function get_entry_meta_counts() {
		global $wpdb;


		if ( version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ) {
			return GF_Forms_Model_Legacy::get_entry_meta_counts();
		}

		$meta_table_name = self::get_entry_meta_table_name();
		$notes_table_name = self::get_entry_notes_table_name();

		if ( ! GFCommon::table_exists( $meta_table_name ) ) {
			return array(
				'details' => 0,
				'meta'    => 0,
				'notes'   => 0,
			);
		}

		$results = $wpdb->get_results(
			"
            SELECT
            (SELECT count(0) FROM $meta_table_name) as meta,
            (SELECT count(0) FROM $notes_table_name) as notes
            "
		);

		return array(
			'details' => intval( $results[0]->meta ),
			'meta'    => intval( $results[0]->meta ),
			'notes'   => intval( $results[0]->notes ),
		);

	}

	/**
	 * @deprecated 2.2 Use gf_upgrade()->dbDelta() instead
	 */
	public static function dbDelta( $sql ) {
		_deprecated_function( 'dbDelta', '2.2', 'gf_upgrade()->dbDelta()' );

		gf_upgrade()->dbDelta( $sql );

	}

	public static function get_db_charset() {
		global $wpdb;

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		return $charset_collate;
	}

	public static function is_valid_table( $table_name ){
		global $wpdb;

		$tables = array(
			GFFormsModel::get_form_table_name(),
			GFFormsModel::get_form_view_table_name(),
			GFFormsModel::get_meta_table_name(),
			GFFormsModel::get_lead_table_name(),
			GFFormsModel::get_lead_notes_table_name(),
			GFFormsModel::get_lead_details_table_name(),
			GFFormsModel::get_lead_details_long_table_name(),
			GFFormsModel::get_lead_meta_table_name(),
			GFFormsModel::get_incomplete_submissions_table_name(),
			"{$wpdb->prefix}gf_addon_feed",
			"{$wpdb->prefix}gf_addon_payment_transaction",
			"{$wpdb->prefix}gf_addon_payment_callback",

			GFFormsModel::get_entry_table_name(),
			GFFormsModel::get_entry_notes_table_name(),
			GFFormsModel::get_entry_meta_table_name(),
			GFFormsModel::get_draft_submissions_table_name(),
		);

		return in_array( $table_name, $tables );
	}

	public static function is_valid_index( $index_name ){

		$indexes = array(
			'id',
			'form_id',
			'status',
			'lead_id',
			'lead_user_key',
			'lead_field_number',
			'lead_detail_id',
			'lead_detail_key',
			'meta_key',
			'form_id_meta_key',
			'uuid',
			'transaction_type',
			'type_lead',
			'slug_callback_id',
			'addon_slug_callback_id',
			'addon_form',
		);

		return in_array( $index_name, $indexes );
	}

	/**
	 * Trims values inside choice texts, choice values, input labels, field labels and field conditionalLogic
	 *
	 * @param array $form         Form object.
	 * @param bool  $form_updated Output parameter.
	 *
	 * @return array $form
	 */
	public static function trim_form_meta_values( $form, &$form_updated = false ) {
		$form_id = $form['id'];
		GFCommon::log_debug( 'GFFormsModel::trim_form_meta_values(): Starting.' );
		if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as &$field ) {
				$trim_value = apply_filters( 'gform_trim_input_value', true, $form_id, $field );
				if ( ! $trim_value ) {
					continue;
				}

				if ( isset( $field->label ) && $field->label != trim( $field->label ) ) {
					$field->label = trim( $field->label );
					$form_updated = true;
				}
				if ( is_array( $field->choices ) ) {
					foreach ( $field->choices as &$choice ) {
						if ( isset( $choice['text'] ) && $choice['text'] != trim( $choice['text'] ) ) {
							$choice['text'] = trim( $choice['text'] );
							$form_updated   = true;
						}
						if ( isset( $choice['value'] ) && $choice['value'] != trim( $choice['value'] ) ) {
							$choice['value'] = trim( $choice['value'] );
							$form_updated    = true;
						}
					}
				}
				if ( is_array( $field->inputs ) ) {
					foreach ( $field->inputs as &$input ) {
						if ( isset( $input['label'] ) && $input['label'] != trim( $input['label'] ) ) {
							$input['label'] = trim( $input['label'] );
							$form_updated   = true;
						}
					}
				}
			}
			$form['fields'] = GFFormsModel::trim_conditional_logic_values( $form['fields'], $form, $form_updated );
		}
		if ( $form_updated ) {
			GFCommon::log_debug( 'GFFormsModel::trim_form_meta_values(): Form values trimmed.' );
		}

		return $form;
	}

	/**
	 * Trims values from an array of elements e.g. notifications and confirmations
	 *
	 * @param array $meta_array Form object.
	 * @param array $form       Form object.
	 * @param bool  $updated    Output parameter.
	 *
	 * @return array $meta_array
	 */
	public static function trim_conditional_logic_values( $meta_array, $form, &$updated = false ) {
		GFCommon::log_debug( 'GFFormsModel::trim_conditional_logic_values(): Starting.' );
		if ( is_array( $meta_array ) ) {
			foreach ( $meta_array as &$meta ) {
				$meta = self::trim_conditional_logic_values_from_element( $meta, $form, $updated );
			}
		}
		if ( $updated ) {
			GFCommon::log_debug( 'GFFormsModel::trim_conditional_logic_values(): Conditional logic values trimmed.' );
		}

		return $meta_array;
	}

	/**
	 * Trims values from elements e.g. fields, notifications and confirmations
	 *
	 * @param array $element Form object.
	 * @param array $form    Form object.
	 * @param bool  $updated Output parameter.
	 *
	 * @return array $element
	 */
	public static function trim_conditional_logic_values_from_element( $element, $form = array(), &$updated = false ) {
		if ( $element instanceof GF_Field ) {

			/* @var GF_Field $element */
			if ( is_array( $element->conditionalLogic ) && isset( $element->conditionalLogic['rules'] ) && is_array( $element->conditionalLogic['rules'] ) ) {
				foreach ( $element->conditionalLogic['rules'] as &$rule ) {
					$value = (string) $rule['value'];
					if ( $value !== trim( $value ) ) {
						$field      = isset( $form['fields'] ) ? GFFormsModel::get_field( $form, $rule['fieldId'] ) : array();
						$trim_value = apply_filters( 'gform_trim_input_value', true, rgar( $form, 'id' ), $field );
						if ( $trim_value ) {
							$rule['value'] = trim( $rule['value'] );
							$updated       = true;
						}
					}
				}
			}
		} else {
			if ( isset( $element['conditionalLogic'] ) && is_array( $element['conditionalLogic'] ) && isset( $element['conditionalLogic']['rules'] ) && is_array( $element['conditionalLogic']['rules'] ) ) {
				foreach ( $element['conditionalLogic']['rules'] as &$rule ) {
					$value = (string) rgar( $rule, 'value' );
					if ( $value !== trim( $value ) ) {
						$field      = isset( $form['fields'] ) ? GFFormsModel::get_field( $form, $rule['fieldId'] ) : array();
						$trim_value = apply_filters( 'gform_trim_input_value', true, rgar( $form, 'id' ), $field );
						if ( $trim_value ) {
							$rule['value'] = trim( $rule['value'] );
							$updated       = true;
						}
					}
				}
			}
		}

		return $element;
	}

	/**
	 * Returns an array of field IDs that have been encrypted using GFCommon::encrypt()
	 *
	 * @deprecated
	 *
	 * @since unknown
	 *
	 * @param $entry_id
	 *
	 * @return array|bool|mixed
	 */
	public static function get_encrypted_fields( $entry_id ) {

		_deprecated_function( 'GFCommon:get_encrypted_fields', '2.3', 'GFCommon:get_openssl_encrypted_fields' );

		$encrypted_fields = gform_get_meta( $entry_id, '_encrypted_fields' );

		if ( empty( $encrypted_fields ) ) {
			$encrypted_fields = array();
		}

		return $encrypted_fields;
	}

	/**
	 * Stores the field IDs that have been encrypted using GFCommon::encrypt()
	 *
	 * @deprecated
	 *
	 * @since unknown
	 *
	 * @param $entry_id
	 * @param $field_ids
	 *
	 * @return bool
	 */
	public static function set_encrypted_fields( $entry_id, $field_ids ) {

		_deprecated_function( 'GFCommon:set_encrypted_fields', '2.3', 'GFCommon:set_openssl_encrypted_fields' );

		if ( ! is_array( $field_ids ) ) {
			$field_ids = array( $field_ids );
		}

		$encrypted_fields = array_merge( self::get_encrypted_fields( $entry_id ), $field_ids );

		gform_update_meta( $entry_id, '_encrypted_fields', $encrypted_fields );

		return true;
	}

	/**
	 * Checks whether the given field was encrypted using GFCommon::encrpyt() and registered using GFCommon::set_encrypted_fields()
	 *
	 * @deprecated
	 *
	 * @since unknown
	 *
	 * @param $entry_id
	 * @param $field_id
	 *
	 * @return bool|mixed|void
	 */
	public static function is_encrypted_field( $entry_id, $field_id ) {

		_deprecated_function( 'GFCommon:is_encrypted_field', '2.3', 'GFCommon:is_openssl_encrypted_field' );

		/**
		 * Determines if an entry field is stored encrypted. Use this hook to change the default behavior of decrypting fields that have been encrypted or to completely disable the
		 * process if checking for encrypted fields.
		 *
		 * @param int $entry_id The current Entry ID
		 * @param int $field_id The current Field ID.
		 */
		$is_encrypted = apply_filters('gform_is_encrypted_field', '', $entry_id, $field_id );
		if (  $is_encrypted !== '' ){
			return $is_encrypted;
		}

		$encrypted_fields = self::get_encrypted_fields( $entry_id );

		return in_array( $field_id, $encrypted_fields );
	}

	/**
	 * Returns an array of field IDs that have been encrypted using GFCommon::openssl_encrypt()
	 *
	 * @since 2.3
	 *
	 * @param $entry_id
	 *
	 * @return array|bool|mixed
	 */
	public static function get_openssl_encrypted_fields( $entry_id ) {

		$encrypted_fields = gform_get_meta( $entry_id, '_openssl_encrypted_fields' );

		if ( empty( $encrypted_fields ) ) {
			$encrypted_fields = array();
		}

		return $encrypted_fields;
	}

	/**
	 * Adds the field IDs that have been encrypted using GFCommon::encrypt(). Merges the new IDs with the existing IDs.
	 *
	 * @since 2.3
	 *
	 * @param $entry_id
	 * @param $field_ids
	 *
	 * @return bool
	 */
	public static function set_openssl_encrypted_fields( $entry_id, $field_ids ) {

		if ( ! is_array( $field_ids ) ) {
			$field_ids = array( $field_ids );
		}

		$encrypted_fields = array_merge( self::get_openssl_encrypted_fields( $entry_id ), $field_ids );

		gform_update_meta( $entry_id, '_openssl_encrypted_fields', $encrypted_fields );

		return true;
	}

	/**
	 * Checks whether the given field was encrypted using GFCommon::encrpyt() and registered using GFCommon::set_encrypted_fields()
	 *
	 * @since 2.3
	 *
	 * @param $entry_id
	 * @param $field_id
	 *
	 * @return bool|mixed|void
	 */
	public static function is_openssl_encrypted_field( $entry_id, $field_id ) {

		/**
		 * Determines if an entry field is stored encrypted. Use this hook to change the default behavior of decrypting fields that have been encrypted or to completely disable the
		 * process if checking for encrypted fields.
		 *
		 * @param int $entry_id The current Entry ID
		 * @param int $field_id The current Field ID.
		 */
		$is_encrypted = apply_filters('gform_is_encrypted_field', '', $entry_id, $field_id );
		if (  $is_encrypted !== '' ){
			return $is_encrypted;
		}

		$encrypted_fields = self::get_openssl_encrypted_fields( $entry_id );

		return in_array( $field_id, $encrypted_fields );
	}


	public static function delete_password( $entry, $form ) {
		$password_fields = self::get_fields_by_type( $form, array( 'password' ) );
		if ( is_array( $password_fields ) ) {
			foreach ( $password_fields as $password_field ) {
				$entry[ $password_field->id ] = '';
			}
		}
		GFAPI::update_entry( $entry );

		return $entry;
	}

	public static function maybe_sanitize_form_settings( $form ) {
		if (  isset( $form['version'] ) && version_compare( $form['version'], '1.9.6.10', '>=' ) ) {
			$form = self::sanitize_settings( $form );
		}
		return $form;
	}

	public static function sanitize_settings( $form ) {

		$form['version'] = GFForms::$version;

		if ( apply_filters( 'gform_disable_form_settings_sanitization', false ) ) {
			return $form;
		}

		// -- standard form settings --

		$form['title'] = sanitize_text_field( rgar( $form, 'title' ) );
		if ( isset( $form['description'] ) ) {
			$form['description'] = self::maybe_wp_kses( $form['description'] );
		}

		if ( isset( $form['labelPlacement'] ) ) {
			$form['labelPlacement'] = GFCommon::whitelist( $form['labelPlacement'], array( 'top_label', 'left_label', 'right_label' ) );
		}
		if ( isset( $form['descriptionPlacement'] ) ) {
			$form['descriptionPlacement'] = GFCommon::whitelist( $form['descriptionPlacement'], array( 'below', 'above' ) );
		}

		if ( isset( $form['subLabelPlacement'] ) ) {
			$form['subLabelPlacement']    = GFCommon::whitelist( $form['subLabelPlacement'], array( 'below', 'above' ) );
		}

		// -- advanced form settings --

		if ( isset( $form['cssClass'] ) ) {
			$form['cssClass'] = sanitize_text_field( $form['cssClass'] );
		}

		if ( isset( $form['enableHoneypot'] ) ) {
			$form['enableHoneypot']  = (bool) $form['enableHoneypot'];
		}

		if ( isset( $form['enableAnimation'] ) ) {
			$form['enableAnimation'] = (bool) $form['enableAnimation'];
		}

		// form button settings
		if ( isset( $form['button'] ) ) {
			$form['button']['type']     = GFCommon::whitelist( $form['button']['type'], array( 'text', 'image' ) );
			$form['button']['text']     = $form['button']['type'] == 'text' ? sanitize_text_field( $form['button']['text'] ) : '';
			$form['button']['imageUrl'] = $form['button']['type'] == 'image' ? sanitize_text_field( $form['button']['imageUrl'] ) : '';
		}
		if ( isset( $form['button']['conditionalLogic'] ) ) {
			$form['button']['conditionalLogic'] = self::sanitize_conditional_logic( $form['button']['conditionalLogic'] );
		}

		// Save and Continue settings
		if ( isset( $form['save'] ) ) {
			$form['save']['enabled']        = (bool) $form['save']['enabled'] ;
			$form['save']['button']['type'] = 'link';
			$form['save']['button']['text'] = sanitize_text_field( $form['save']['button']['text'] );
		}


		// limit entries settings
		if ( isset( $form['limitEntries'] ) ) {
			$form['limitEntries']        = (bool) $form['limitEntries'];
			$form['limitEntriesCount']   = $form['limitEntries'] ? absint( $form['limitEntriesCount'] ) : '';
			$form['limitEntriesPeriod']  = $form['limitEntries'] ? GFCommon::whitelist( $form['limitEntriesPeriod'], array( '', 'day', 'week', 'month', 'year' ) ) : '';
			$form['limitEntriesMessage'] = $form['limitEntries'] ? self::maybe_wp_kses( $form['limitEntriesMessage'] ) : '';
		}

		// form scheduling settings
		if ( isset( $form['scheduleForm'] ) ) {
			$form['scheduleForm']           = (bool) $form['scheduleForm'];
			$form['scheduleStart']          = $form['scheduleForm'] ? wp_strip_all_tags( $form['scheduleStart'] ) : '';
			$form['scheduleStartHour']      = $form['scheduleForm'] ? GFCommon::int_range( $form['scheduleStartHour'], 1, 12 ) : '';
			$form['scheduleStartMinute']    = $form['scheduleForm'] ? GFCommon::int_range( $form['scheduleStartMinute'], 1, 60 ) : '';
			$form['scheduleStartAmpm']      = $form['scheduleForm'] ? GFCommon::whitelist( $form['scheduleStartAmpm'], array( 'am', 'pm' ) ) : '';
			$form['scheduleEnd']            = $form['scheduleForm'] ? wp_strip_all_tags( $form['scheduleEnd'] ) : '';
			$form['scheduleEndHour']        = $form['scheduleForm'] ? GFCommon::int_range( $form['scheduleEndHour'], 1, 12 ) : '';
			$form['scheduleEndMinute']      = $form['scheduleForm'] ? GFCommon::int_range( $form['scheduleEndMinute'], 1, 60 ) : '';
			$form['scheduleEndAmpm']        = $form['scheduleForm'] ? GFCommon::whitelist( $form['scheduleEndAmpm'], array( 'am', 'pm' ) ) : '';
			$form['schedulePendingMessage'] = $form['scheduleForm'] ? self::maybe_wp_kses( $form['schedulePendingMessage'] ) : '';
			$form['scheduleMessage']        = $form['scheduleForm'] ? self::maybe_wp_kses( $form['scheduleMessage'] ) : '';

		}

		// require login settings
		if ( isset( $form['requireLogin'] ) ) {
			$form['requireLogin']        = (bool) $form['requireLogin'];
			$form['requireLoginMessage'] = $form['requireLogin'] ? self::maybe_wp_kses( $form['requireLoginMessage'] ) : '';
		}

		if ( isset( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				$field->sanitize_settings();
			}
		}

		return $form;
	}

	public static function sanitize_conditional_logic( $logic ) {
		if ( ! is_array( $logic ) ) {
			return $logic;
		}

		if ( apply_filters( 'gform_disable_form_settings_sanitization', false ) ) {
			return $logic;
		}

		if ( isset( $logic['actionType'] ) && ! in_array( $logic['actionType'], array( 'show', 'hide' ) ) ) {
			$logic['actionType'] = 'show';
		}
		if (  isset( $logic['logicType'] ) && ! in_array( $logic['logicType'], array( 'all', 'any' ) ) ) {
			$logic['logicType'] = 'all';
		}

		if ( isset( $logic['rules'] ) && is_array( $logic['rules'] ) ) {
			foreach ( $logic['rules'] as &$rule ) {
				if ( isset( $rule['fieldId'] ) ) {
					// Field ID could be meta key
					$rule['fieldId'] = wp_strip_all_tags( $rule['fieldId'] );
				}
				if ( isset( $rule['operator'] ) ) {
					$is_valid_operator = self::is_valid_operator( $rule['operator'] );
					$rule['operator'] = $is_valid_operator ? $rule['operator'] : 'is';
				}

				if ( isset( $rule['value'] ) ) {
					$rule['value'] = wp_strip_all_tags( $rule['value'] );
				}
			}
		}
		return $logic;
	}

	private static function maybe_wp_kses( $html, $allowed_html = 'post', $allowed_protocols = array() ) {
		return GFCommon::maybe_wp_kses( $html, $allowed_html, $allowed_protocols );
	}

	/**
	 * Returns an array containing the form fields of the specified type or types.
	 *
	 * @since 1.9.9.10
	 * @param array $form
	 * @param array|string $types
	 * @param bool $use_input_type
	 *
	 * @return GF_Field[]
	 */
	public static function get_fields_by_type( $form, $types, $use_input_type = false ) {
		$fields = array();
		if ( ! is_array( rgar( $form, 'fields' ) ) ) {
			return $fields;
		}

		if ( ! is_array( $types ) ) {
			$types = array( $types );
		}

		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */
			$type = $use_input_type ? $field->get_input_type() : $field->type;
			if ( in_array( $type, $types ) ) {
				$fields[] = $field;
			}
		}

		return $fields;
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

	/**
	 * Update the recent forms list for the current user when a form is edited or trashed.
	 *
	 * @since 2.0.7.14
	 *
	 * @param int $form_id The ID of the current form.
	 * @param bool $trashed Indicates if the form was trashed. Default is false, form was opened for editing.
	 */
	public static function update_recent_forms( $form_id, $trashed = false ) {
		if ( ! get_option( 'gform_enable_toolbar_menu' ) ) {
			return;
		}

		$current_user_id = get_current_user_id();
		$recent_form_ids = self::get_recent_forms( $current_user_id );

		$i = array_search( $form_id, $recent_form_ids );

		if ( $i !== false ) {
			unset( $recent_form_ids[ $i ] );
			$recent_form_ids = array_values( $recent_form_ids );
		}

		if ( ! $trashed ) {
			// Add the current form to the top of the list.
			array_unshift( $recent_form_ids, $form_id );

			$recent_form_ids = array_slice( $recent_form_ids, 0, 10 );
		}

		update_user_meta( $current_user_id, 'gform_recent_forms', $recent_form_ids );
	}

	/**
	 * Get the recent forms list for the current user.
	 *
	 * @since 2.2.1.14
	 *
	 * @param int $current_user_id The ID of the currently logged in user.
	 *
	 * @return array
	 */
	public static function get_recent_forms( $current_user_id = 0 ) {
		if ( ! $current_user_id ) {
			$current_user_id = get_current_user_id();
		}

		$recent_form_ids = get_user_meta( $current_user_id, 'gform_recent_forms', true );

		if ( empty( $recent_form_ids ) ) {
			$all_form_ids    = self::get_form_ids();
			$all_form_ids    = array_reverse( $all_form_ids );
			$recent_form_ids = array_slice( $all_form_ids, 0, 10 );
			if ( $recent_form_ids ) {
				update_user_meta( $current_user_id, 'gform_recent_forms', $recent_form_ids );
			}
		}

		return $recent_form_ids;
	}

	/**
	 * Evaluates the conditional logic based on the specified $logic variable.
	 * @param $form         The current Form object.
	 * @param $logic        The conditional logic configuration array with all the specified rules.
	 * @param $field_values Default field values for this form. Used when form has not yet been submitted. Pass an array if no default field values are available/required.
	 * @param $entry        Optional, default is null. If entry object is available, pass the entry.
	 *
	 * @return bool         Returns true if the conditional logic passes (i.e. field/button is supposed to be displayed), false otherwise.
	 */
	private static function evaluate_conditional_logic( $form, $logic, $field_values, $entry = null ) {

		if ( empty( $logic ) ){
			return true;
		}

		$match_count = 0;
		foreach ( $logic['rules'] as $rule ) {
			$source_field   = RGFormsModel::get_field( $form, $rule['fieldId'] );
			$field_value    = empty( $entry ) ? self::get_field_value( $source_field, $field_values ) : self::get_lead_field_value( $entry, $source_field );
			$is_value_match = self::is_value_match( $field_value, $rule['value'], $rule['operator'], $source_field, $rule, $form );

			if ( $is_value_match ) {
				$match_count ++;
			}
		}

		$do_action = ( $logic['logicType'] == 'all' && $match_count == sizeof( $logic['rules'] ) ) || ( $logic['logicType'] == 'any' && $match_count > 0 );
		$is_hidden = ( $do_action && $logic['actionType'] == 'hide' ) || ( ! $do_action && $logic['actionType'] == 'show' );

		return ! $is_hidden;
	}

	/**
	 * Returns all the draft submissions.
	 *
	 * @since 2.4
	 *
	 * @return array|null|object The query result.
	 */
	public static function get_draft_submissions() {
		global $wpdb;

		self::purge_expired_draft_submissions();

		$table = version_compare( self::get_database_version(), '2.3-dev-1', '<' ) ? self::get_incomplete_submissions_table_name() : self::get_draft_submissions_table_name();
		$sql   = "SELECT uuid, date_created, form_id, ip, submission, source_url FROM {$table}";
		$rows   = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $rows as $row ) {
			$form = self::get_form_meta( $row['form_id'] );
			$row['submission'] = self::filter_draft_submission_post_get( $row['submission'], $row['uuid'], $form );
		}

		return $rows;
	}

	/**
	 * Sanitizes the names of the files that have been uploaded to the tmp directory and sent in
	 * $_POST['gform_uploaded_files'] and caches them in GFFormsModel::$uploaded_files.
	 *
	 * @since 2.4.3.5
	 *
	 * @param $form_id
	 *
	 * @return array
	 */
	public static function set_uploaded_files( $form_id ) {
		$files = GFCommon::json_decode( stripslashes( GFForms::post( 'gform_uploaded_files' ) ) );
		if ( ! is_array( $files ) ) {
			$files = array();
		}

		foreach ( $files as &$upload_field ) {
			if ( is_array( $upload_field ) ) {
				if ( isset( $upload_field[0] ) && is_array( $upload_field[0] ) ) {
					foreach ( $upload_field as &$upload ) {
						if ( isset( $upload['temp_filename'] ) ) {
							$upload['temp_filename'] = sanitize_file_name( basename( $upload['temp_filename'] ) );
						}
						if ( isset( $upload['uploaded_filename'] ) ) {
							$upload['uploaded_filename'] = sanitize_file_name( basename( $upload['uploaded_filename'] ) );
						}
					}
				}
			} else {
				$upload_field = basename( $upload_field );
			}
		}

		self::$uploaded_files[ $form_id ] = $files;

		return $files;
	}

	/**
	 * Checks if an entry exists for the supplied ID.
	 *
	 * @since 2.4.5.8
	 *
	 * @param int $entry_id The ID to be checked.
	 *
	 * @return bool
	 */
	public static function entry_exists( $entry_id ) {
		$entry_id = intval( $entry_id );
		if ( $entry_id <= 0 ) {
			return false;
		}

		global $wpdb;
		$entry_table_name = GFFormsModel::get_entry_table_name();

		$sql    = $wpdb->prepare( "SELECT count(id) FROM {$entry_table_name} WHERE id = %d", $entry_id );
		$result = intval( $wpdb->get_var( $sql ) );

		return $result > 0;
	}

}

class RGFormsModel extends GFFormsModel {
}

/**
 * In-memory cache of entry meta using "{blog_id}_{entry_id}_{meta_key}" as the key.
 *
 * @since 2.3 Prefixed cache key with the blog id.
 * @since unknown
 *
 * @global array $_gform_lead_meta
 */
global $_gform_lead_meta;
$_gform_lead_meta = array();

// Functions to handle lead meta
function gform_get_meta( $entry_id, $meta_key ) {

	if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
		return GF_Forms_Model_Legacy::gform_get_meta( $entry_id, $meta_key );
	}

	global $wpdb, $_gform_lead_meta;

	//get from cache if available
	$cache_key = get_current_blog_id() . '_' . $entry_id . '_' . $meta_key;
	if ( array_key_exists( $cache_key, $_gform_lead_meta ) ) {
		return maybe_unserialize( $_gform_lead_meta[ $cache_key ] );
	}

	$table_name                   = GFFormsModel::get_entry_meta_table_name();
	$results                      = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$table_name} WHERE entry_id=%d AND meta_key=%s", $entry_id, $meta_key ) );
	$value                        = isset( $results[0] ) ? $results[0]->meta_value : null;
	$meta_value                   = $value === null ? false : maybe_unserialize( $value );
	$_gform_lead_meta[ $cache_key ] = $meta_value;

	return $meta_value;
}

function gform_get_meta_values_for_entries( $entry_ids, $meta_keys ) {
	global $wpdb;

	if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
		return GF_Forms_Model_Legacy::gform_get_meta_values_for_entries( $entry_ids, $meta_keys );
	}

	if ( empty( $meta_keys ) || empty( $entry_ids ) ) {
		return array();
	}

	$table_name            = RGFormsModel::get_entry_meta_table_name();
	$meta_key_select_array = array();

	foreach ( $meta_keys as $meta_key ) {
		$meta_key_select_array[] = "max(case when meta_key = '$meta_key' then meta_value end) as `$meta_key`";
	}

	$entry_ids_str = join( ',', $entry_ids );

	$meta_key_select = join( ',', $meta_key_select_array );

	$sql_query = "  SELECT
                    entry_id, $meta_key_select
                    FROM $table_name
                    WHERE entry_id IN ($entry_ids_str)
                    GROUP BY entry_id";

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
 * Add or update metadata associated with an entry.
 *
 * Data will be serialized. Don't forget to sanitize user input.
 *
 * @since Unknown
 *
 * @param int      $entry_id   The ID of the entry to be updated.
 * @param string   $meta_key   The key for the meta data to be stored.
 * @param mixed    $meta_value The data to be stored for the entry.
 * @param int|null $form_id    The form ID of the entry (optional, saves extra query if passed when creating the metadata).
 */
function gform_update_meta( $entry_id, $meta_key, $meta_value, $form_id = null ) {
	global $wpdb, $_gform_lead_meta;

	if ( gf_upgrade()->get_submissions_block() ) {
		return;
	}

	if ( intval( $entry_id ) <= 0 ) {
		return;
	}

	if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
		GF_Forms_Model_Legacy::gform_update_meta( $entry_id, $meta_key, $meta_value, $form_id );
		return;
	}
	$table_name = GFFormsModel::get_entry_meta_table_name();


	if ( false === $meta_value ) {
		$meta_value = '0';
	}

	$serialized_meta_value  = maybe_serialize( $meta_value );
	$meta_exists = gform_get_meta( $entry_id, $meta_key ) !== false;
	if ( $meta_exists ) {
		$wpdb->update( $table_name, array( 'meta_value' => $serialized_meta_value ), array( 'entry_id' => $entry_id, 'meta_key' => $meta_key ), array( '%s' ), array( '%d', '%s' ) );
	} else {

		if ( empty( $form_id ) ) {
			$entry_table_name = GFFormsModel::get_entry_table_name();
			$form_id         = $wpdb->get_var( $wpdb->prepare( "SELECT form_id from $entry_table_name WHERE id=%d", $entry_id ) );
		} else {
			$form_id = intval( $form_id );
		}

		$wpdb->insert( $table_name, array( 'form_id' => $form_id, 'entry_id' => $entry_id, 'meta_key' => $meta_key, 'meta_value' => $serialized_meta_value ), array( '%d', '%d', '%s', '%s' ) );
	}

	//updates cache
	$cache_key = get_current_blog_id() . '_' . $entry_id . '_' . $meta_key;
	if ( array_key_exists( $cache_key, $_gform_lead_meta ) ) {
		$_gform_lead_meta[ $cache_key ] = $meta_value;
	}
}

/**
 * Add metadata associated with an entry.
 *
 * Data will be serialized; Don't forget to sanitize user input.
 *
 * @since Unknown
 *
 * @param int      $entry_id   The ID of the entry where metadata is to be added.
 * @param string   $meta_key   The key for the meta data to be stored.
 * @param mixed    $meta_value The data to be stored for the entry.
 * @param int|null $form_id    The form ID of the entry (optional, saves extra query if passed when creating the metadata).
 */
function gform_add_meta( $entry_id, $meta_key, $meta_value, $form_id = null ) {
	global $wpdb, $_gform_lead_meta;

	if ( gf_upgrade()->get_submissions_block() ) {
		return;
	}

	if ( intval( $entry_id ) <= 0 ) {
		return;
	}

	if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
		GF_Forms_Model_Legacy::gform_add_meta( $entry_id, $meta_key, $meta_value, $form_id );
		return;
	}

	$table_name = RGFormsModel::get_entry_meta_table_name();

	if ( false === $meta_value ) {
		$meta_value = '0';
	}
	$serialized_meta_value  = maybe_serialize( $meta_value );

	if ( empty( $form_id ) ) {
		$entry_table_name = GFFormsModel::get_entry_table_name();
		$form_id         = $wpdb->get_var( $wpdb->prepare( "SELECT form_id from $entry_table_name WHERE id=%d", $entry_id ) );
	} else {
		$form_id = intval( $form_id );
	}

	$wpdb->insert( $table_name, array( 'form_id' => $form_id, 'entry_id' => $entry_id, 'meta_key' => $meta_key, 'meta_value' => $serialized_meta_value ), array( '%d', '%d', '%s', '%s' ) );

	$cache_key                      = get_current_blog_id() . '_' . $entry_id . '_' . $meta_key;
	$_gform_lead_meta[ $cache_key ] = $meta_value;
}

/**
 * Delete metadata associated with an entry.
 *
 * @since Unknown
 *
 * @param int    $entry_id The ID of the entry to be deleted.
 * @param string $meta_key The key for the meta data to be deleted.
 */
function gform_delete_meta( $entry_id, $meta_key = '' ) {
	global $wpdb, $_gform_lead_meta;

	if ( gf_upgrade()->get_submissions_block() ) {
		return;
	}

	if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
		GF_Forms_Model_Legacy::gform_delete_meta( $entry_id, $meta_key );
		return;
	}

	$table_name  = RGFormsModel::get_entry_meta_table_name();
	$meta_filter = empty( $meta_key ) ? '' : $wpdb->prepare( 'AND meta_key=%s', $meta_key );

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE entry_id=%d {$meta_filter}", $entry_id ) );

	//clears cache.
	$_gform_lead_meta = array();
}
