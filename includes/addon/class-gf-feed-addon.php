<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Specialist Add-On class designed for use by Add-Ons that require form feed settings
 * on the form settings tab.
 *
 * @package GFFeedAddOn
 */

require_once( 'class-gf-addon.php' );

abstract class GFFeedAddOn extends GFAddOn {

	/**
	 * If set to true, Add-On can have multiple feeds configured. If set to false, feed list page doesn't exist and only one feed can be configured.
	 * @var bool
	 */
	protected $_multiple_feeds = true;

	/**
	 * If true, only first matching feed will be processed. Multiple feeds can still be configured, but only one is executed during the submission (i.e. Payment Add-Ons)
	 * @var bool
	 */
	protected $_single_feed_submission = false;

	/**
	 * If $_single_feed_submission is true, $_single_submission_feed will store the current single submission feed as stored by the get_single_submission_feed() method.
	 * @var mixed (bool | Feed Object)
	 */
	protected $_single_submission_feed = false;

	/**
	 * If true, users can configure what order feeds are executed in from the feed list page.
	 * @var bool
	 */
	protected $_supports_feed_ordering = false;

	/**
	 * If true, feeds will be processed asynchronously in the background.
	 *
	 * @since 2.2
	 * @var bool
	 */
	protected $_async_feed_processing = false;

	/**
	 * If true, maybe_delay_feed() checks will be bypassed allowing the feeds to be processed.
	 * @var bool
	 */
	protected $_bypass_feed_delay = false;

	/**
	 * @var string Version number of the Add-On Framework
	 */
	private $_feed_version = '0.13';
	private $_feed_settings_fields = array();
	private $_current_feed_id = false;

	/**
	 * Plugin starting point. Handles hooks and loading of language files.
	 */
	public function init() {

		parent::init();

		add_filter( 'gform_entry_post_save', array( $this, 'maybe_process_feed' ), 10, 2 );

	}

	/**
	 * Override this function to add AJAX hooks or to add initialization code when an AJAX request is being performed
	 */
	public function init_ajax() {

		parent::init_ajax();

		add_action( "wp_ajax_gf_feed_is_active_{$this->_slug}", array( $this, 'ajax_toggle_is_active' ) );
		add_action( 'wp_ajax_gf_save_feed_order', array( $this, 'ajax_save_feed_order' ) );

	}

	/**
	 * Override this function to add initialization code (i.e. hooks) for the admin site (WP dashboard)
	 */
	public function init_admin() {

		parent::init_admin();

		add_filter( 'gform_notification_events', array( $this, 'notification_events' ), 10, 2 );
		add_filter( 'gform_notes_avatar', array( $this, 'notes_avatar' ), 10, 2 );
		add_action( 'gform_post_form_duplicated', array( $this, 'post_form_duplicated' ), 10, 2 );

	}

	/**
	 * Performs upgrade tasks when the version of the Add-On changes. To add additional upgrade tasks, override the upgrade() function, which will only get executed when the plugin version has changed.
	 */
	public function setup() {
		// upgrading Feed Add-On base class
		$installed_version = get_option( 'gravityformsaddon_feed-base_version' );
		if ( $installed_version != $this->_feed_version ) {
			$this->upgrade_base( $installed_version );
			update_option( 'gravityformsaddon_feed-base_version', $this->_feed_version );
		}

		parent::setup();
	}

	private function upgrade_base( $previous_version ) {
		global $wpdb;

		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		$sql = "CREATE TABLE {$wpdb->prefix}gf_addon_feed (
                  id mediumint(8) unsigned not null auto_increment,
                  form_id mediumint(8) unsigned not null,
                  is_active tinyint(1) not null default 1,
                  feed_order mediumint(8) unsigned not null default 0,
                  meta longtext,
                  addon_slug varchar(50),
                  PRIMARY KEY  (id),
                  KEY addon_form (addon_slug,form_id)
                ) $charset_collate;";

		gf_upgrade()->dbDelta( $sql );

	}

	/**
	 * Gets called when Gravity Forms upgrade process is completed. This function is intended to be used internally, override the upgrade() function to execute database update scripts.
	 * @param $db_version - Current Gravity Forms database version
	 * @param $previous_db_version - Previous Gravity Forms database version
	 * @param $force_upgrade - True if this is a request to force an upgrade. False if this is a standard upgrade (due to version change)
	 */
	public function post_gravityforms_upgrade( $db_version, $previous_db_version, $force_upgrade ) {

		// Forcing Upgrade
		if ( $force_upgrade ) {

			$installed_version = get_option( 'gravityformsaddon_feed-base_version' );

			$this->upgrade_base( $installed_version );

			update_option( 'gravityformsaddon_feed-base_version', $this->_feed_version );

		}

		parent::post_gravityforms_upgrade( $db_version, $previous_db_version, $force_upgrade );
	}

	public function scripts() {

		$min     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		$scripts = array(
			array(
				'handle'  => 'gform_form_admin',
				'enqueue' => array( array( 'admin_page' => array( 'form_settings' ) ) ),
			),
			array(
				'handle'  => 'gform_gravityforms',
				'enqueue' => array( array( 'admin_page' => array( 'form_settings' ) ) ),
			),
			array(
				'handle'  => 'gform_forms',
				'enqueue' => array( array( 'admin_page' => array( 'form_settings' ) ) ),
			),
			array(
				'handle'  => 'json2',
				'enqueue' => array( array( 'admin_page' => array( 'form_settings' ) ) ),
			),
			array(
				'handle'  => 'gform_placeholder',
				'enqueue' => array(
					array(
						'admin_page'  => array( 'form_settings' ),
						'field_types' => array( 'feed_condition' ),
					),
				)
			),
		);

		if ( $this->_supports_feed_ordering ) {
			$scripts[] = array(
				'handle'    => 'gaddon_feedorder',
				'src'       => $this->get_gfaddon_base_url() . "/js/gaddon_feedorder{$min}.js",
				'version'   => GFCommon::$version,
				'deps'      => array( 'jquery', 'jquery-ui-sortable' ),
				'in_footer' => false,
				'enqueue'   => array(
					array(
						'admin_page' => array( 'form_settings' )
					),
				),
			);
		}

		return array_merge( parent::scripts(), $scripts );
	}

	public function uninstall() {
		global $wpdb;
		$sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}gf_addon_feed WHERE addon_slug=%s", $this->_slug );
		$wpdb->query( $sql );

	}

	//-------- Front-end methods ---------------------------

	/**
	 * Determines what feeds need to be processed for the provided entry.
	 *
	 * @access public
	 * @param array $entry The Entry Object currently being processed.
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return array $entry
	 */
	public function maybe_process_feed( $entry, $form ) {

		if ( 'spam' === $entry['status'] ) {
			$this->log_debug( "GFFeedAddOn::maybe_process_feed(): Entry #{$entry['id']} is marked as spam; not processing feeds for {$this->_slug}." );

			return $entry;
		}

		$this->log_debug( __METHOD__ . "(): Checking for feeds to process for entry #{$entry['id']} for {$this->_slug}." );

		$feeds = false;

		// If this is a single submission feed, get the first feed. Otherwise, get all feeds.
		if ( $this->_single_feed_submission ) {
			$feed = $this->get_single_submission_feed( $entry, $form );
			if ( $feed ) {
				$feeds = array( $feed );
			}
		} else {
			$feeds = $this->get_feeds( $form['id'] );
		}

		// Run filters before processing feeds.
		$feeds = $this->pre_process_feeds( $feeds, $entry, $form );

		// If there are no feeds to process, return.
		if ( empty( $feeds ) ) {
			$this->log_debug( __METHOD__ . "(): No feeds to process for entry #{$entry['id']}." );
			return $entry;
		}

		// Determine if feed processing needs to be delayed.
		$is_delayed = $this->maybe_delay_feed( $entry, $form );

		// Initialize array of feeds that have been processed.
		$processed_feeds = array();

		// Loop through feeds.
		foreach ( $feeds as $feed ) {

			// Get the feed name.
			$feed_name = rgempty( 'feed_name', $feed['meta'] ) ? rgar( $feed['meta'], 'feedName' ) : rgar( $feed['meta'], 'feed_name' );

			// If this feed is inactive, log that it's not being processed and skip it.
			if ( ! $feed['is_active'] ) {
				$this->log_debug( "GFFeedAddOn::maybe_process_feed(): Feed is inactive, not processing feed (#{$feed['id']} - {$feed_name}) for entry #{$entry['id']}." );
				continue;
			}

			// If this feed's condition is not met, log that it's not being processed and skip it.
			if ( ! $this->is_feed_condition_met( $feed, $form, $entry ) ) {
				$this->log_debug( "GFFeedAddOn::maybe_process_feed(): Feed condition not met, not processing feed (#{$feed['id']} - {$feed_name}) for entry #{$entry['id']}." );
				continue;
			}

			// process feed if not delayed
			if ( ! $is_delayed ) {

				// If asynchronous feed processing is enabled, add it to the processing queue.
				if ( $this->is_asynchronous( $feed, $entry, $form ) ) {

					// Log that feed processing is being delayed.
					$this->log_debug( "GFFeedAddOn::maybe_process_feed(): Adding feed (#{$feed['id']} - {$feed_name}) for entry #{$entry['id']} for {$this->_slug} to the processing queue." );

					// Add feed to processing queue.
					gf_feed_processor()->push_to_queue(
						array(
							'addon' => $this,
							'feed'  => $feed,
							'entry_id' => $entry['id'],
							'form_id'  => $form['id'],
						)
					);

				} else {

					// All requirements are met; process feed.
					$this->log_debug( "GFFeedAddOn::maybe_process_feed(): Starting to process feed (#{$feed['id']} - {$feed_name}) for entry #{$entry['id']} for {$this->_slug}" );
					$returned_entry = $this->process_feed( $feed, $entry, $form );

					// If returned value from the process feed call is an array containing an id, set the entry to its value.
					if ( is_array( $returned_entry ) && rgar( $returned_entry, 'id' ) ) {
						$entry = $returned_entry;
					}

					/**
					 * Perform a custom action when a feed has been processed.
					 *
					 * @param array $feed The feed which was processed.
					 * @param array $entry The current entry object, which may have been modified by the processed feed.
					 * @param array $form The current form object.
					 * @param GFAddOn $addon The current instance of the GFAddOn object which extends GFFeedAddOn or GFPaymentAddOn (i.e. GFCoupons, GF_User_Registration, GFStripe).
					 *
					 * @since 2.0
					 */
					do_action( 'gform_post_process_feed', $feed, $entry, $form, $this );
					do_action( "gform_{$this->_slug}_post_process_feed", $feed, $entry, $form, $this );

					// Log that Add-On has been fulfilled.
					$this->log_debug( 'GFFeedAddOn::maybe_process_feed(): Marking entry #' . $entry['id'] . ' as fulfilled for ' . $this->_slug );
					gform_update_meta( $entry['id'], "{$this->_slug}_is_fulfilled", true );

					// Adding this feed to the list of processed feeds
					$processed_feeds[] = $feed['id'];
				}

			} else {

				// Log that feed processing is being delayed.
				$this->log_debug( 'GFFeedAddOn::maybe_process_feed(): Feed processing is delayed, not processing feed for entry #' . $entry['id'] . ' for ' . $this->_slug );

				// Delay feed.
				$this->delay_feed( $feed, $entry, $form );

			}
		}

		// If any feeds were processed, save the processed feed IDs.
		if ( ! empty( $processed_feeds ) ) {

			// Get current processed feeds.
			$meta = gform_get_meta( $entry['id'], 'processed_feeds' );

			// If no feeds have been processed for this entry, initialize the meta array.
			if ( empty( $meta ) ) {
				$meta = array();
			}

			// Add this Add-On's processed feeds to the entry meta.
			$meta[ $this->_slug ] = $processed_feeds;

			// Update the entry meta.
			gform_update_meta( $entry['id'], 'processed_feeds', $meta );

		}

		// Return the entry object.
		return $entry;

	}

	/**
	 * Determines if feed processing is delayed by the PayPal Standard Add-On.
	 *
	 * Also enables use of the gform_is_delayed_pre_process_feed filter.
	 *
	 * @param array $entry The Entry Object currently being processed.
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return bool
	 */
	public function maybe_delay_feed( $entry, $form ) {
		if ( $this->_bypass_feed_delay ) {
			return false;
		}

		$is_delayed = false;
		$slug       = $this->get_slug();

		if ( $slug != 'gravityformspaypal' && class_exists( 'GFPayPal' ) && function_exists( 'gf_paypal' ) ) {
			if ( gf_paypal()->is_payment_gateway( $entry['id'] ) ) {
				$paypal_feed = gf_paypal()->get_single_submission_feed( $entry );
				if ( $paypal_feed && $this->is_delayed( $paypal_feed ) ) {
					$is_delayed = true;
				}
			}
		}

		/**
		 * Allow feed processing to be delayed.
		 *
		 * @param bool $is_delayed Is feed processing delayed?
		 * @param array $form The Form Object currently being processed.
		 * @param array $entry The Entry Object currently being processed.
		 * @param string $slug The Add-On slug e.g. gravityformsmailchimp
		 */
		$is_delayed = apply_filters( 'gform_is_delayed_pre_process_feed', $is_delayed, $form, $entry, $slug );
		$is_delayed = apply_filters( 'gform_is_delayed_pre_process_feed_' . $form['id'], $is_delayed, $form, $entry, $slug );

		return $is_delayed;
	}

	/**
	 * Retrieves the delay setting for the current add-on from the PayPal feed.
	 *
	 * @param array $paypal_feed The PayPal feed which is being used to process the current submission.
	 *
	 * @return bool|null
	 */
	public function is_delayed( $paypal_feed ) {
		$delay = rgar( $paypal_feed['meta'], 'delay_' . $this->_slug );

		return $delay;
	}

	/**
	 * Determines if feed processing should happen asynchronously.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @param array $feed  The Feed Object currently being processed.
	 * @param array $form  The Form Object currently being processed.
	 * @param array $entry The Entry Object currently being processed.
	 *
	 * @return bool
	 */
	public function is_asynchronous( $feed, $entry, $form ) {

		/**
		 * Allow feed to be processed asynchronously.
		 *
		 * @since 2.2
		 *
		 * @param bool   $is_asynchronous Is feed being processed asynchronously?
		 * @param array  $feed            The Feed Object currently being processed.
		 * @param array  $entry           The Entry Object currently being processed.
		 * @param array  $form            The Form Object currently being processed.
		 */
		$is_asynchronous = gf_apply_filters( array( 'gform_is_feed_asynchronous', $form['id'], $feed['id'] ), $this->_async_feed_processing, $feed, $entry, $form );

		return $is_asynchronous;

	}

	/**
	 * Processes feed action.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array  $feed  The Feed Object currently being processed.
	 * @param array  $entry The Entry Object currently being processed.
	 * @param array  $form  The Form Object currently being processed.
	 *
	 * @return array|null Returns a modified entry object or null.
	 */
	public function process_feed( $feed, $entry, $form ) {

		return;
	}

	public function delay_feed( $feed, $entry, $form ) {

		return;
	}

	public function is_feed_condition_met( $feed, $form, $entry ) {

		$feed_meta            = $feed['meta'];
		$is_condition_enabled = rgar( $feed_meta, 'feed_condition_conditional_logic' ) == true;
		$logic                = rgars( $feed_meta, 'feed_condition_conditional_logic_object/conditionalLogic' );

		if ( ! $is_condition_enabled || empty( $logic ) ) {
			return true;
		}

		return GFCommon::evaluate_conditional_logic( $logic, $form, $entry );
	}

	/**
	 * Create nonce for asynchronous feed processing.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @return string The nonce.
	 */
	public function create_feed_nonce() {

		$action = 'gform_' . $this->_slug . '_process_feed';
		$i      = wp_nonce_tick();

		return substr( wp_hash( $i . $action, 'nonce' ), - 12, 10 );

	}

	/**
	 * Verify nonce for asynchronous feed processing.
	 *
	 * @since  1.0
	 * @access public
	 * @param  string $nonce Nonce to be verified.
	 *
	 * @return int|bool
	 */
	public function verify_feed_nonce( $nonce ) {

		$action = 'gform_' . $this->_slug . '_process_feed';
		$i      = wp_nonce_tick();

		// Nonce generated 0-12 hours ago.
		if ( substr( wp_hash( $i . $action, 'nonce' ), - 12, 10 ) === $nonce ) {
			return 1;
		}

		// Nonce generated 12-24 hours ago.
		if ( substr( wp_hash( ( $i - 1 ) . $action, 'nonce' ), - 12, 10 ) === $nonce ) {
			return 2;
		}

		// Log that nonce was unable to be verified.
		$this->log_error( __METHOD__ . '(): Aborting. Unable to verify nonce.' );

		return false;

	}

	/**
	 * Retrieves notification events supported by Add-On.
	 *
	 * @access public
	 * @param array $form
	 * @return array
	 */
	public function supported_notification_events( $form ) {

		return array();

	}

	/**
	 * Add notifications events supported by Add-On to notification events list.
	 *
	 * @access public
	 * @param array $events
	 * @param array $form
	 * @return array $events
	 */
	public function notification_events( $events, $form ) {

		/* Get the supported notification events for this Add-On. */
		$supported_events = $this->supported_notification_events( $form );

		/* If no events are supported, return the current array of events. */
		if ( empty( $supported_events ) ) {
			return $events;
		}

		return array_merge( $events, $supported_events );

	}

	//--------  Feed data methods  -------------------------

	public function get_feeds( $form_id = null ) {
		global $wpdb;

		$form_filter = is_numeric( $form_id ) ? $wpdb->prepare( 'AND form_id=%d', absint( $form_id ) ) : '';

		$sql = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gf_addon_feed
                               WHERE addon_slug=%s {$form_filter} ORDER BY `feed_order`, `id` ASC", $this->_slug
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $results as &$result ) {
			$result['meta'] = json_decode( $result['meta'], true );
		}

		return $results;
	}

	public function get_active_feeds( $form_id = null ) {
		global $wpdb;

		$form_filter = is_numeric( $form_id ) ? $wpdb->prepare( 'AND form_id=%d', absint( $form_id ) ) : '';

		$sql = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gf_addon_feed
                               WHERE addon_slug=%s AND is_active=1 {$form_filter} ORDER BY `feed_order`, `id` ASC", $this->_slug
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $results as &$result ) {
			$result['meta'] = json_decode( $result['meta'], true );
		}

		return $results;
	}

	public function get_feeds_by_slug( $slug, $form_id = null ) {
		global $wpdb;

		$form_filter = is_numeric( $form_id ) ? $wpdb->prepare( 'AND form_id=%d', absint( $form_id ) ) : '';

		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gf_addon_feed
                               WHERE addon_slug=%s {$form_filter} ORDER BY `feed_order`, `id` ASC", $slug );

		$results = $wpdb->get_results( $sql, ARRAY_A );
		foreach( $results as &$result ) {
			$result['meta'] = json_decode( $result['meta'], true );
		}

		return $results;
	}

	public function get_current_feed() {
		$feed_id = $this->get_current_feed_id();

		return empty( $feed_id ) ? false : $this->get_feed( $feed_id );
	}

	public function get_current_feed_id() {
		if ( $this->_current_feed_id ) {
			return $this->_current_feed_id;
		} elseif ( ! rgempty( 'gf_feed_id' ) ) {
			return rgpost( 'gf_feed_id' );
		} else {
			return rgget( 'fid' );
		}
	}

	public function get_feed( $id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gf_addon_feed WHERE id=%d", $id );

		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! $row ) {
			return false;
		}

		$row['meta'] = json_decode( $row['meta'], true );

		return $row;
	}

	public function get_feeds_by_entry( $entry_id ) {
		$processed_feeds = gform_get_meta( $entry_id, 'processed_feeds' );
		if ( ! $processed_feeds ) {
			return false;
		}

		return rgar( $processed_feeds, $this->_slug );
	}

	public function has_feed( $form_id, $meets_conditional_logic = null ) {

		$feeds = $this->get_feeds( $form_id );
		if ( ! $feeds ) {
			return false;
		}

		$has_active_feed = false;

		if ( $meets_conditional_logic ) {
			$form  = GFFormsModel::get_form_meta( $form_id );
			$entry = GFFormsModel::create_lead( $form );
		}

		foreach ( $feeds as $feed ) {
			if ( ! $has_active_feed && $feed['is_active'] ) {
				$has_active_feed = true;
			}

			if ( $meets_conditional_logic && $feed['is_active'] && $this->is_feed_condition_met( $feed, $form, $entry ) ) {
				return true;
			}
		}

		return $meets_conditional_logic ? false : $has_active_feed;
	}

	public function get_single_submission_feed( $entry = false, $form = false ) {

		if ( ! $entry && ! $form ) {
			return false;
		}

		$feed = false;

		if ( ! empty( $this->_single_submission_feed ) ) {

			$feed = $this->_single_submission_feed;

		} elseif ( $entry['id'] ) {

			$feeds = $this->get_feeds_by_entry( $entry['id'] );

			if ( empty( $feeds ) ) {
				$feed = $this->get_single_submission_feed_by_form( $form, $entry );
			} else {
				$feed = $this->get_feed( $feeds[0] );
			}

		} elseif ( $form ) {

			$feed                          = $this->get_single_submission_feed_by_form( $form, $entry );
			$this->_single_submission_feed = $feed;

		}

		return $feed;
	}

	/**
	 * Return the active feed to be used when processing the current entry, evaluating conditional logic if configured.
	 *
	 * @param array $form The current form.
	 * @param array|false $entry The current entry.
	 *
	 * @return bool|array
	 */
	public function get_single_submission_feed_by_form( $form, $entry ) {
		if ( $form ) {
			$feeds = $this->get_feeds( $form['id'] );

			foreach ( $feeds as $_feed ) {
				if ( $_feed['is_active'] && $this->is_feed_condition_met( $_feed, $form, $entry ) ) {

					return $_feed;
				}
			}
		}

		return false;
	}

	public function pre_process_feeds( $feeds, $entry, $form ) {

		/**
		 * Modify feeds before they are processed.
		 *
		 * @param array $feeds An array of $feed objects
		 * @param array $entry Current entry for which feeds will be processed
		 * @param array $form Current form object.
		 *
		 * @since 2.0
		 *
		 * @return array An array of $feeds
		 */
		$feeds = apply_filters( 'gform_addon_pre_process_feeds', $feeds, $entry, $form );
		$feeds = apply_filters( "gform_addon_pre_process_feeds_{$form['id']}", $feeds, $entry, $form );
		$feeds = apply_filters( "gform_{$this->_slug}_pre_process_feeds", $feeds, $entry, $form );
		$feeds = apply_filters( "gform_{$this->_slug}_pre_process_feeds_{$form['id']}", $feeds, $entry, $form );

		return $feeds;
	}

	/**
	 * Get default feed name.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return string
	 */
	public function get_default_feed_name() {

		/**
		 * Query db to look for two formats that the feed name could have been auto-generated with
		 * format from migration to add-on framework: 'Feed ' . $counter
		 * new auto-generated format when adding new feed: $short_title . ' Feed ' . $counter
		 */

		// Set to zero unless a new number is found while checking existing feed names (will be incremented by 1 at the end).
		$counter_to_use = 0;

		// Get Add-On feeds.
		$feeds_to_filter = $this->get_feeds_by_slug( $this->_slug );

		// If feeds were found, loop through and increase counter.
		if ( $feeds_to_filter ) {

			// Loop through feeds and look for name pattern to find what to make default feed name.
			foreach ( $feeds_to_filter as $check ) {

				// Get feed name and trim.
				$name = rgars( $check, 'meta/feed_name' ) ? rgars( $check, 'meta/feed_name' ) : rgars( $check, 'meta/feedName' );
				$name = trim( $name );

				// Prepare feed name pattern.
				$pattern = '/(^Feed|^' . $this->_short_title . ' Feed)\s\d+/';

				// Search for feed name pattern.
				preg_match( $pattern,$name,$matches );

				// If matches were found, increase counter.
				if ( $matches ) {

					// Number should be characters at the end after a space
					$last_space = strrpos( $matches[0], ' ' );

					$digit = substr( $matches[0], $last_space );

					// Counter in existing feed name greater, use it instead.
					if ( $digit >= $counter_to_use ){
						$counter_to_use = $digit;
					}

				}

			}

		}

		// Set default feed name
		$value = $this->_short_title . ' Feed ' . ($counter_to_use + 1);

		return $value;

	}

	public function is_unique_feed_name( $name, $form_id ) {
		$feeds = $this->get_feeds( $form_id );
		foreach ( $feeds as $feed ) {
			$feed_name = rgars( $feed, 'meta/feed_name' ) ? rgars( $feed, 'meta/feed_name' ) : rgars( $feed, 'meta/feedName' );
			if ( strtolower( $feed_name ) === strtolower( $name ) ) {
				return false;
			}
		}

		return true;
	}

	public function update_feed_meta( $id, $meta ) {
		global $wpdb;

		$meta = json_encode( $meta );
		$wpdb->update( "{$wpdb->prefix}gf_addon_feed", array( 'meta' => $meta ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );

		return $wpdb->rows_affected > 0;
	}

	public function update_feed_active( $id, $is_active ) {
		global $wpdb;
		$is_active = $is_active ? '1' : '0';

		$wpdb->update( "{$wpdb->prefix}gf_addon_feed", array( 'is_active' => $is_active ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );

		return $wpdb->rows_affected > 0;
	}

	public function insert_feed( $form_id, $is_active, $meta ) {
		global $wpdb;

		$meta = json_encode( $meta );
		$wpdb->insert( "{$wpdb->prefix}gf_addon_feed", array( 'addon_slug' => $this->_slug, 'form_id' => $form_id, 'is_active' => $is_active, 'meta' => $meta ), array( '%s', '%d', '%d', '%s' ) );

		return $wpdb->insert_id;
	}

	public function delete_feed( $id ) {
		global $wpdb;

		$wpdb->delete( "{$wpdb->prefix}gf_addon_feed", array( 'id' => $id ), array( '%d' ) );
	}

	public function delete_feeds( $form_id = null ) {
		global $wpdb;

		$where  = is_numeric( $form_id ) ? array( 'addon_slug' => $this->_slug, 'form_id' => $form_id ) : array( 'addon_slug' => $this->_slug );
		$format = is_numeric( $form_id ) ? array( '%s', '%d' ) : array( '%s' );

		$wpdb->delete( "{$wpdb->prefix}gf_addon_feed", $where, $format );
	}

	/**
	 * Duplicates the feed.
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 * @param mixed $new_form_id False when using feed actions or the ID of the new form when duplicating a form.
	 */
	public function duplicate_feed( $id, $new_form_id = false ) {

		/* Get original feed. */
		$original_feed = is_array( $id ) ? $id : $this->get_feed( $id );

		/* If feed doesn't exist, exit. */
		if ( ! $original_feed || ! $this->can_duplicate_feed( $original_feed ) ) {
			return;
		}

		/* Get feed name key. */
		$feed_name_key = rgars( $original_feed, 'meta/feed_name' ) ? 'feed_name' : 'feedName';

		/* Make sure the new feed name is unique. */
		$count = 2;
		$feed_name = rgars( $original_feed, 'meta/' . $feed_name_key ) . ' - ' . esc_html__( 'Copy 1', 'gravityforms' );
		while ( ! $this->is_unique_feed_name( $feed_name, $original_feed['form_id'] ) ) {
			$feed_name = rgars( $original_feed, 'meta/' . $feed_name_key ) . ' - ' . sprintf( esc_html__( 'Copy %d', 'gravityforms' ), $count );
			$count ++;
		}

		/* Copy the feed meta. */
		$meta                   = $original_feed['meta'];
		$meta[ $feed_name_key ] = $feed_name;

		if ( ! $new_form_id ) {
			$new_form_id = $original_feed['form_id'];
		}

		/* Create the new feed. */
		$this->insert_feed( $new_form_id, $original_feed['is_active'], $meta );

	}

	/**
	 * Maybe duplicate feeds when a form is duplicated.
	 *
	 * @param int $form_id The ID of the original form.
	 * @param int $new_id The ID of the duplicate form.
	 */
	public function post_form_duplicated( $form_id, $new_id ) {

		$feeds = $this->get_feeds( $form_id );

		if ( ! $feeds ) {
			return;
		}

		foreach ( $feeds as $feed ) {
			$this->duplicate_feed( $feed, $new_id );
		}

	}

	/**
	 * Save order of feeds.
	 *
	 * @since  2.0
	 * @access public
	 *
	 * @param array $feed_order Array of feed IDs in desired order.
	 */
	public function save_feed_order( $feed_order ) {

		global $wpdb;

		// Reindex feed order to start at 1 instead of 0.
		$feed_order = array_combine( range( 1, count( $feed_order ) ), array_values( $feed_order ) );

		// Swap array keys and values.
		$feed_order = array_flip( $feed_order );

		// Update each feed.
		foreach ( $feed_order as $feed_id => $position ) {

			$wpdb->update(
				$wpdb->prefix . 'gf_addon_feed',
				array( 'feed_order' => $position ),
				array( 'id' => $feed_id ),
				array( '%d' ),
				array( '%d' )
			);

		}

	}

	//---------- Form Settings Pages --------------------------

	public function form_settings_init() {
		parent::form_settings_init();
	}

	public function ajax_toggle_is_active() {
		$feed_id   = rgpost( 'feed_id' );
		$is_active = rgpost( 'is_active' );

		$this->update_feed_active( $feed_id, $is_active );
		die();
	}

	public function ajax_save_feed_order() {
		check_ajax_referer( 'gform_feed_order', 'nonce' );

		if ( ! $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			return;
		}

		$addon      = sanitize_text_field( rgpost( 'addon' ) );
		$form_id    = absint( rgpost( 'form_id' ) );
		$feed_order = rgpost( 'feed_order' ) ? rgpost( 'feed_order' ) : array();
		$feed_order = array_map( 'absint', $feed_order );

		if ( $addon == $this->_slug ) {
			$this->save_feed_order( $feed_order );
		}
	}

	public function form_settings_sections() {
		return array();
	}

	public function form_settings( $form ) {
		if ( ! $this->_multiple_feeds || $this->is_detail_page() ) {

			// feed edit page
			$feed_id = $this->_multiple_feeds ? $this->get_current_feed_id() : $this->get_default_feed_id( $form['id'] );

			$this->feed_edit_page( $form, $feed_id );
		} else {
			// feed list UI
			$this->feed_list_page( $form );
		}
	}

	public function is_feed_list_page() {
		return ! isset( $_GET['fid'] );
	}

	public function is_detail_page() {
		return ! $this->is_feed_list_page();
	}

	public function form_settings_header() {
		if ( $this->is_feed_list_page() ) {
			$title = $this->form_settings_title();
			$url = add_query_arg( array( 'fid' => 0 ) );
			return $title . " <a class='add-new-h2' href='" . esc_html( $url ) . "'>" . esc_html__( 'Add New', 'gravityforms' ) . '</a>';
		}
	}

	public function form_settings_title() {
		return sprintf( esc_html__( '%s Feeds', 'gravityforms' ), $this->get_short_title() );
	}

	public function feed_edit_page( $form, $feed_id ) {

		$title = '<h3><span>' . $this->feed_settings_title() . '</span></h3>';

		if ( ! $this->can_create_feed() ) {
			echo $title . '<div>' . $this->configure_addon_message() . '</div>';

			return;
		}

		// Save feed if appropriate
		$feed_id = $this->maybe_save_feed_settings( $feed_id, $form['id'] );

		$this->_current_feed_id = $feed_id; // So that current feed functions work when creating a new feed

		?>
		<script type="text/javascript">
			<?php GFFormSettings::output_field_scripts() ?>
		</script>
		<?php

		echo $title;

		$feed = $this->get_feed( $feed_id );
		$this->set_settings( $feed['meta'] );

		GFCommon::display_admin_message();

		$this->render_settings( $this->get_feed_settings_fields( $form ) );

	}

	public function settings( $sections ) {

		parent::settings( $sections );

		?>
		<input type="hidden" name="gf_feed_id" value="<?php echo esc_attr( $this->get_current_feed_id() ); ?>" />
		<?php

	}

	public function feed_settings_title() {
		return esc_html__( 'Feed Settings', 'gravityforms' );
	}

	public function feed_list_page( $form = null ) {
		$action = $this->get_bulk_action();
		if ( $action ) {
			check_admin_referer( 'feed_list', 'feed_list' );
			$this->process_bulk_action( $action );
		}

		$single_action = rgpost( 'single_action' );
		if ( ! empty( $single_action ) ) {
			check_admin_referer( 'feed_list', 'feed_list' );
			$this->process_single_action( $single_action );
		}

		?>

		<h3><span><?php echo $this->feed_list_title() ?></span></h3>
		<form id="gform-settings" action="" method="post">
			<?php
			$feed_list = $this->get_feed_table( $form );
			$feed_list->prepare_items();
			$feed_list->display();
			?>

			<!--Needed to save state after bulk operations-->
			<input type="hidden" value="gf_edit_forms" name="page">
			<input type="hidden" value="settings" name="view">
			<input type="hidden" value="<?php echo esc_attr( $this->_slug ); ?>" name="subview">
			<input type="hidden" value="<?php echo esc_attr( rgar( $form, 'id' ) ); ?>" name="id">
			<input id="single_action" type="hidden" value="" name="single_action">
			<input id="single_action_argument" type="hidden" value="" name="single_action_argument">
			<?php wp_nonce_field( 'feed_list', 'feed_list' ) ?>
		</form>

		<script type="text/javascript">
			<?php

				GFCommon::gf_vars();

				if ( $this->_supports_feed_ordering ) {

					// Prepare feed ordering options.
					$feed_order_options = array(
						'addon'  => $this->_slug,
						'formId' => rgar( $form, 'id' ),
						'nonce'  => wp_create_nonce( 'gform_feed_order' ),
					);

					echo 'jQuery( document ).ready( function() {
						window.GFFeedOrderObj = new GFFeedOrder( ' . json_encode( $feed_order_options ) . ');
					} );';

				}

			?>
		</script>

	<?php
	}

	public function get_feed_table( $form ) {

		$columns               = $this->feed_list_columns();
		$column_value_callback = array( $this, 'get_column_value' );
		$feeds                 = $this->get_feeds( rgar( $form, 'id' ) );
		$bulk_actions          = $this->get_bulk_actions();
		$action_links          = $this->get_action_links();
		$no_item_callback      = array( $this, 'feed_list_no_item_message' );
		$message_callback      = array( $this, 'feed_list_message' );

		return new GFAddOnFeedsTable( $feeds, $this->_slug, $columns, $bulk_actions, $action_links, $column_value_callback, $no_item_callback, $message_callback, $this );
	}

	public function feed_list_title() {
		if ( ! $this->can_create_feed() ) {
			return $this->form_settings_title();
		}

		$url = add_query_arg( array( 'fid' => '0' ) );
		$url = esc_url( $url );

		return $this->form_settings_title() . " <a class='add-new-h2' href='{$url}'>" . esc_html__( 'Add New', 'gravityforms' ) . '</a>';
	}

	public function maybe_save_feed_settings( $feed_id, $form_id ) {

		if ( ! rgpost( 'gform-settings-save' ) ) {
			return $feed_id;
		}

		check_admin_referer( $this->_slug . '_save_settings', '_' . $this->_slug . '_save_settings_nonce' );

		if ( ! $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			GFCommon::add_error_message( esc_html__( "You don't have sufficient permissions to update the form settings.", 'gravityforms' ) );
			return $feed_id;
		}

		// store a copy of the previous settings for cases where action would only happen if value has changed
		$feed = $this->get_feed( $feed_id );
		$this->set_previous_settings( $feed['meta'] );

		$settings = $this->get_posted_settings();
		$sections = $this->get_feed_settings_fields();
		$settings = $this->trim_conditional_logic_vales( $settings, $form_id );

		$is_valid = $this->validate_settings( $sections, $settings );
		$result   = false;

		if ( $is_valid ) {
			$settings = $this->filter_settings( $sections, $settings );
			$feed_id = $this->save_feed_settings( $feed_id, $form_id, $settings );
			if ( $feed_id ) {
				GFCommon::add_message( $this->get_save_success_message( $sections ) );
			} else {
				GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
			}
		} else {
			GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
		}

		return $feed_id;
	}

	public function trim_conditional_logic_vales( $settings, $form_id ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}
		if ( isset( $settings['feed_condition_conditional_logic_object'] ) && is_array( $settings['feed_condition_conditional_logic_object'] ) ) {
			$form                                                = GFFormsModel::get_form_meta( $form_id );
			$settings['feed_condition_conditional_logic_object'] = GFFormsModel::trim_conditional_logic_values_from_element( $settings['feed_condition_conditional_logic_object'], $form );
		}

		return $settings;
	}

	public function get_save_success_message( $sections ) {
		if ( ! $this->is_detail_page() )
			return parent::get_save_success_message( $sections );

		$save_button = $this->get_save_button( $sections );

		return isset( $save_button['messages']['success'] ) ? $save_button['messages']['success'] : esc_html__( 'Feed updated successfully.', 'gravityforms' );
	}

	public function get_save_error_message( $sections ) {
		if ( ! $this->is_detail_page() )
			return parent::get_save_error_message( $sections );

		$save_button = $this->get_save_button( $sections );

		return isset( $save_button['messages']['error'] ) ? $save_button['messages']['error'] : esc_html__( 'There was an error updating this feed. Please review all errors below and try again.', 'gravityforms' );
	}

	public function save_feed_settings( $feed_id, $form_id, $settings ) {

		if ( $feed_id ) {
			$this->update_feed_meta( $feed_id, $settings );
			$result = $feed_id;
		} else {
			$result = $this->insert_feed( $form_id, true, $settings );
		}

		return $result;
	}

	public function get_feed_settings_fields() {

		if ( ! empty( $this->_feed_settings_fields ) ) {
			return $this->_feed_settings_fields;
		}

		/**
		 * Filter the feed settings fields (typically before they are rendered on the Feed Settings edit view).
		 *
		 * @param array $feed_settings_fields An array of feed settings fields which will be displayed on the Feed Settings edit view.
		 * @param object $addon The current instance of the GFAddon object (i.e. GF_User_Registration, GFPayPal).
		 *
		 * @since 2.0
		 *
		 * @return array
		 */
		$feed_settings_fields = apply_filters( 'gform_addon_feed_settings_fields', $this->feed_settings_fields(), $this );
		$feed_settings_fields = apply_filters( "gform_{$this->_slug}_feed_settings_fields", $feed_settings_fields, $this );

		$this->_feed_settings_fields = $this->add_default_feed_settings_fields_props( $feed_settings_fields );

		return $this->_feed_settings_fields;
	}

	public function feed_settings_fields() {
		return array();
	}

	public function add_default_feed_settings_fields_props( $fields ) {

		foreach ( $fields as &$section ) {
			foreach ( $section['fields'] as &$field ) {
				switch ( $field['type'] ) {

					case 'hidden':
						$field['hidden'] = true;
						break;
				}

				if ( rgar( $field, 'name' ) === 'feedName' ) {
					$field['default_value'] = $this->get_default_feed_name();
				}
			}
		}

		return $fields;
	}

	private function get_bulk_action() {
		$action = rgpost( 'action' );
		if ( empty( $action ) || $action == '-1' ) {
			$action = rgpost( 'action2' );
		}

		return empty( $action ) || $action == '-1' ? false : $action;
	}

	/***
	 * Override this function to add custom bulk actions
	 */
	public function get_bulk_actions() {
		$bulk_actions = array(
			'delete'    => esc_html__( 'Delete', 'gravityforms' ),
		);

		return $bulk_actions;
	}

	/***
	 * Override this function to process custom bulk actions added via the get_bulk_actions() function
	 *
	 * @param string $action : The bulk action selected by the user
	 */
	public function process_bulk_action( $action ) {
		if ( 'delete' === $action ) {
			$feeds = rgpost( 'feed_ids' );
			if ( is_array( $feeds ) ) {
				foreach ( $feeds as $feed_id ) {
					$this->delete_feed( $feed_id );
				}
			}
		}
		if ( 'duplicate' === $action ) {
			$feeds = rgpost( 'feed_ids' );
			if ( is_array( $feeds ) ) {
				foreach ( $feeds as $feed_id ) {
					$this->duplicate_feed( $feed_id );
				}
			}
		}
	}

	public function process_single_action( $action ) {
		if ( $action == 'delete' ) {
			$feed_id = absint( rgpost( 'single_action_argument' ) );
			$this->delete_feed( $feed_id );
		}
		if ( $action == 'duplicate' ) {
			$feed_id = absint( rgpost( 'single_action_argument' ) );
			$this->duplicate_feed( $feed_id );
		}
	}

	public function get_action_links() {
		$feed_id       = '_id_';
		$edit_url      = add_query_arg( array( 'fid' => $feed_id ) );
		$links         = array(
			'edit'      => '<a title="' . esc_attr__( 'Edit this feed', 'gravityforms' ) . '" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'gravityforms' ) . '</a>',
			'duplicate' => '<a title="' . esc_attr__( 'Duplicate this feed', 'gravityforms' ) . '" href="#" onclick="gaddon.duplicateFeed(\'' . esc_js( $feed_id ) . '\');" onkeypress="gaddon.duplicateFeed(\'' . esc_js( $feed_id ) . '\');">' . esc_html__( 'Duplicate', 'gravityforms' ) . '</a>',
			'delete'    => '<a title="' . esc_attr__( 'Delete this feed', 'gravityforms' ) . '" class="submitdelete" onclick="javascript: if(confirm(\'' . esc_js( __( 'WARNING: You are about to delete this item.', 'gravityforms' ) ) . esc_js( __( "'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ) . '\')){ gaddon.deleteFeed(\'' . esc_js( $feed_id ) . '\'); }" onkeypress="javascript: if(confirm(\'' . esc_js( __( 'WARNING: You are about to delete this item.', 'gravityforms' ) ) . esc_js( __( "'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ) . '\')){ gaddon.deleteFeed(\'' . esc_js( $feed_id ) . '\'); }" style="cursor:pointer;">' . esc_html__( 'Delete', 'gravityforms' ) . '</a>'
		);

		return $links;
	}

	public function feed_list_columns() {
		return array();
	}

	/**
	 * Override this function to change the message that is displayed when the feed list is empty
	 * @return string The message
	 */
	public function feed_list_no_item_message() {
		$url = add_query_arg( array( 'fid' => 0 ) );
		return sprintf( esc_html__( "You don't have any feeds configured. Let's go %screate one%s!", 'gravityforms' ), "<a href='" . esc_url( $url ) . "'>", '</a>' );
	}

	/**
	 * Override this function to force a message to be displayed in the feed list (instead of data). Useful to alert users when main plugin settings haven't been completed.
	 * @return string|false
	 */
	public function feed_list_message() {
		if ( ! $this->can_create_feed() ) {
			return $this->configure_addon_message();
		}

		return false;
	}

	public function configure_addon_message() {

		$settings_label = sprintf( __( '%s Settings', 'gravityforms' ), $this->get_short_title() );
		$settings_link  = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

		return sprintf( __( 'To get started, please configure your %s.', 'gravityforms' ), $settings_link );

	}

	/**
	 * Override this function to prevent the feed creation UI from being rendered.
	 * @return boolean|true
	 */
	public function can_create_feed() {
		return true;
	}

	/**
	 * Override this function to allow the feed to being duplicated.
	 *
	 * @access public
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 * @return boolean|true
	 */
	public function can_duplicate_feed( $id ) {
		return false;
	}

	public function get_column_value( $item, $column ) {
		if ( is_callable( array( $this, "get_column_value_{$column}" ) ) ) {
			return call_user_func( array( $this, "get_column_value_{$column}" ), $item );
		} elseif ( isset( $item[ $column ] ) ) {
			return $item[ $column ];
		} elseif ( isset( $item['meta'][ $column ] ) ) {
			return $item['meta'][ $column ];
		}
	}


	public function update_form_settings( $form, $new_form_settings ) {
		$feed_id = rgar( $new_form_settings, 'id' );
		foreach ( $new_form_settings as $key => $value ) {
			$form[ $this->_slug ]['feeds'][ $feed_id ][ $key ] = $value;
		}

		return $form;
	}

	public function get_default_feed_id( $form_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}gf_addon_feed WHERE addon_slug=%s AND form_id = %d LIMIT 0,1", $this->_slug, $form_id );

		$feed_id = $wpdb->get_var( $sql );
		if ( ! $feed_id ) {
			$feed_id = 0;
		}

		return $feed_id;
	}

	public function settings_feed_condition( $field, $echo = true ) {

		$conditional_logic = $this->get_feed_condition_conditional_logic();
		$checkbox_field = $this->get_feed_condition_checkbox( $field );

		$hidden_field = $this->get_feed_condition_hidden_field();
		$instructions = isset( $field['instructions'] ) ? $field['instructions'] : esc_html__( 'Process this feed if', 'gravityforms' );
		$html         = $this->settings_checkbox( $checkbox_field, false );
		$html .= $this->settings_hidden( $hidden_field, false );
		$html .= '<div id="feed_condition_conditional_logic_container"><!-- dynamically populated --></div>';
		$html .= '<script type="text/javascript"> var feedCondition = new FeedConditionObj({' .
			'strings: { objectDescription: "' . esc_attr( $instructions ) . '" },' .
			'logicObject: ' . $conditional_logic .
			'}); </script>';

		if ( $this->field_failed_validation( $field ) ) {
			$html .= $this->get_error_icon( $field );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function get_feed_condition_checkbox( $field ) {
		$checkbox_label = isset( $field['checkbox_label'] ) ? $field['checkbox_label'] : esc_html__( 'Enable Condition', 'gravityforms' );

		$checkbox_field  = array(
			'name'    => 'feed_condition_conditional_logic',
			'type'    => 'checkbox',
			'choices' => array(
				array(
					'label' => $checkbox_label,
					'name'  => 'feed_condition_conditional_logic',
				),
			),
			'onclick' => 'ToggleConditionalLogic( false, "feed_condition" );',
		);

		return $checkbox_field;
	}

	public function get_feed_condition_hidden_field() {
		$conditional_logic = $this->get_feed_condition_conditional_logic();
		$hidden_field = array(
			'name'  => 'feed_condition_conditional_logic_object',
			'value' => $conditional_logic,
		);
		return $hidden_field;
	}

	public function get_feed_condition_conditional_logic() {
		$conditional_logic_object = $this->get_setting( 'feed_condition_conditional_logic_object' );
		if ( $conditional_logic_object ) {
			$form_id           = rgget( 'id' );
			$form              = GFFormsModel::get_form_meta( $form_id );
			$conditional_logic = json_encode( GFFormsModel::trim_conditional_logic_values_from_element( $conditional_logic_object, $form ) );
		} else {
			$conditional_logic = '{}';
		}
		return $conditional_logic;
	}

	public function validate_feed_condition_settings( $field, $settings ) {
		$checkbox_field = $this->get_feed_condition_checkbox( $field );
		$this->validate_checkbox_settings( $checkbox_field, $settings );

		if ( ! isset( $settings['feed_condition_conditional_logic_object'] ) ) {
			return;
		}

		$conditional_logic_object = $settings['feed_condition_conditional_logic_object'];
		if ( ! isset( $conditional_logic_object['conditionalLogic'] ) ) {
			return;
		}
		$conditional_logic = $conditional_logic_object['conditionalLogic'];
		$conditional_logic_safe = GFFormsModel::sanitize_conditional_logic( $conditional_logic );
		if ( serialize( $conditional_logic ) != serialize( $conditional_logic_safe ) ) {
			$this->set_field_error( $field, esc_html__( 'Invalid value', 'gravityforms' ) );
		}
	}

	public static function add_entry_meta( $form ) {
		$entry_meta = GFFormsModel::get_entry_meta( $form['id'] );
		$keys       = array_keys( $entry_meta );
		foreach ( $keys as $key ) {
			array_push( $form['fields'], array( 'id' => $key, 'label' => $entry_meta[ $key ]['label'] ) );
		}

		return $form;
	}

	public function has_feed_condition_field() {

		$fields = $this->settings_fields_only( 'feed' );

		foreach ( $fields as $field ) {
			if ( $field['type'] == 'feed_condition' ) {
				return true;
			}
		}

		return false;
	}

	public function add_delayed_payment_support( $options ) {
		$this->delayed_payment_integration = $options;

		if ( is_admin() ) {
			add_filter( 'gform_gravityformspaypal_feed_settings_fields', array( $this, 'add_paypal_post_payment_actions' ) );
		}

		add_action( 'gform_paypal_fulfillment', array( $this, 'paypal_fulfillment' ), 10, 4 );
	}

	/**
	 * Add the Post Payments Actions setting to the PayPal feed.
	 *
	 * @param array $feed_settings_fields The PayPal feed settings.
	 *
	 * @return array
	 */
	public function add_paypal_post_payment_actions( $feed_settings_fields ) {

		$form_id = absint( rgget( 'id' ) );
		if ( $this->has_feed( $form_id ) ) {

			$addon_label = rgar( $this->delayed_payment_integration, 'option_label' );
			$choice      = array(
				'label' => $addon_label ? $addon_label : sprintf( esc_html__( 'Process %s feed only when payment is received.', 'gravityforms' ), $this->get_short_title() ),
				'name'  => 'delay_' . $this->_slug,
			);

			$field_name = 'post_payment_actions';
			$field      = $this->get_field( $field_name, $feed_settings_fields );

			if ( ! $field ) {

				$fields = array(
					array(
						'name'    => $field_name,
						'label'   => esc_html__( 'Post Payment Actions', 'gravityforms' ),
						'type'    => 'checkbox',
						'choices' => array( $choice ),
						'tooltip' => '<h6>' . esc_html__( 'Post Payment Actions', 'gravityforms' ) . '</h6>' . esc_html__( 'Select which actions should only occur after payment has been received.', 'gravityforms' )
					)
				);

				$feed_settings_fields = $this->add_field_after( 'options', $fields, $feed_settings_fields );

			} else {

				$field['choices'][]   = $choice;
				$feed_settings_fields = $this->replace_field( $field_name, $field, $feed_settings_fields );

			}
		}

		return $feed_settings_fields;
	}

	public function paypal_fulfillment( $entry, $paypal_config, $transaction_id, $amount ) {

		$this->log_debug( 'GFFeedAddOn::paypal_fulfillment(): Checking PayPal fulfillment for transaction ' . $transaction_id . ' for ' . $this->_slug );
		$is_fulfilled = gform_get_meta( $entry['id'], "{$this->_slug}_is_fulfilled" );
		if ( $is_fulfilled ) {
			$this->log_debug( 'GFFeedAddOn::paypal_fulfillment(): Entry ' . $entry['id'] . ' is already fulfilled for ' . $this->_slug . '. No action necessary.' );
			return false;
		}

		$form                     = RGFormsModel::get_form_meta( $entry['form_id'] );
		$this->_bypass_feed_delay = true;
		$this->maybe_process_feed( $entry, $form );

	}

	//--------------- Notes ------------------
	public function add_feed_error( $error_message, $feed, $entry, $form ) {

		/* Log debug error before we prepend the error name. */
		$backtrace = debug_backtrace();
		$method    = $backtrace[1]['class'] . '::' . $backtrace[1]['function'];
		$this->log_error( $method . '(): ' . $error_message );

		/* Prepend feed name to the error message. */
		$feed_name     = rgars( $feed, 'meta/feed_name' ) ? rgars( $feed, 'meta/feed_name' ) : rgars( $feed, 'meta/feedName' );
		$error_message = $feed_name . ': ' . $error_message;

		/* Add error note to the entry. */
		$this->add_note( $entry['id'], $error_message, 'error' );

		/* Get Add-On slug */
		$slug = str_replace( 'gravityforms', '', $this->_slug );

		/* Process any error actions. */
		gf_do_action( array( "gform_{$slug}_error", $form['id'] ), $feed, $entry, $form );

	}

	// TODO: Review for Deprecation ------------------

	public function get_paypal_feed( $form_id, $entry ) {

		if ( ! class_exists( 'GFPayPal' ) ) {
			return false;
		}

		if ( method_exists( 'GFPayPal', 'get_config_by_entry' ) ) {
			$feed = GFPayPal::get_config_by_entry( $entry );
		} elseif ( method_exists( 'GFPayPal', 'get_config' ) ) {
			$feed = GFPayPal::get_config( $form_id );
		} else {
			$feed = false;
		}

		return $feed;
	}

	public function has_paypal_payment( $feed, $form, $entry ) {

		$products = GFCommon::get_product_fields( $form, $entry );

		$payment_field   = $feed['meta']['transactionType'] === 'product' ? $feed['meta']['paymentAmount'] : $feed['meta']['recurringAmount'];
		$setup_fee_field = rgar( $feed['meta'], 'setupFee_enabled' ) ? $feed['meta']['setupFee_product'] : false;
		$trial_field     = rgar( $feed['meta'], 'trial_enabled' ) ? rgars( $feed, 'meta/trial_product' ) : false;

		$amount       = 0;
		$line_items   = array();
		$discounts    = array();
		$fee_amount   = 0;
		$trial_amount = 0;
		foreach ( $products['products'] as $field_id => $product ) {

			$quantity      = $product['quantity'] ? $product['quantity'] : 1;
			$product_price = GFCommon::to_number( $product['price'] );

			$options = array();
			if ( is_array( rgar( $product, 'options' ) ) ) {
				foreach ( $product['options'] as $option ) {
					$options[] = $option['option_name'];
					$product_price += $option['price'];
				}
			}

			$is_trial_or_setup_fee = false;

			if ( ! empty( $trial_field ) && $trial_field === $field_id ) {

				$trial_amount          = $product_price * $quantity;
				$is_trial_or_setup_fee = true;

			} elseif ( ! empty( $setup_fee_field ) && $setup_fee_field === $field_id ) {

				$fee_amount            = $product_price * $quantity;
				$is_trial_or_setup_fee = true;
			}

			// Do not add to line items if the payment field selected in the feed is not the current field.
			if ( is_numeric( $payment_field ) && $payment_field != $field_id ) {
				continue;
			}

			// Do not add to line items if the payment field is set to "Form Total" and the current field was used for trial or setup fee.
			if ( $is_trial_or_setup_fee && ! is_numeric( $payment_field ) ) {
				continue;
			}

			$amount += $product_price * $quantity;

		}


		if ( ! empty( $products['shipping']['name'] ) && ! is_numeric( $payment_field ) ) {
			$line_items[] = array( 'id'          => '',
			                       'name'        => $products['shipping']['name'],
			                       'description' => '',
			                       'quantity'    => 1,
			                       'unit_price'  => GFCommon::to_number( $products['shipping']['price'] ),
			                       'is_shipping' => 1
			);
			$amount += $products['shipping']['price'];
		}

		return $amount > 0;
	}

	public function is_delayed_payment( $entry, $form, $is_delayed ) {
		if ( $this->_slug == 'gravityformspaypal' ) {
			return false;
		}

		$paypal_feed = $this->get_paypal_feed( $form['id'], $entry );
		if ( ! $paypal_feed ) {
			return false;
		}

		$has_payment = self::get_paypal_payment_amount( $form, $entry, $paypal_feed ) > 0;

		return rgar( $paypal_feed['meta'], "delay_{$this->_slug}" ) && $has_payment && ! $is_delayed;
	}

	public static function get_paypal_payment_amount( $form, $entry, $paypal_config ) {

		// TODO: need to support old "paypal_config" format as well as new format when delayed payment suported feed addons are released
		$products        = GFCommon::get_product_fields( $form, $entry, true );
		$recurring_field = rgar( $paypal_config['meta'], 'recurring_amount_field' );
		$total           = 0;
		foreach ( $products['products'] as $id => $product ) {

			if ( $paypal_config['meta']['type'] != 'subscription' || $recurring_field == $id || $recurring_field == 'all' ) {
				$price = GFCommon::to_number( $product['price'] );
				if ( is_array( rgar( $product, 'options' ) ) ) {
					foreach ( $product['options'] as $option ) {
						$price += GFCommon::to_number( $option['price'] );
					}
				}

				$total += $price * $product['quantity'];
			}
		}

		if ( 'all' === $recurring_field && ! empty( $products['shipping']['price'] ) ) {
			$total += floatval( $products['shipping']['price'] );
		}

		return $total;
	}

}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class GFAddOnFeedsTable extends WP_List_Table {

	private $_feeds;
	private $_slug;
	private $_columns;
	private $_bulk_actions;
	private $_action_links;
	private $_addon_class;

	private $_column_value_callback = array();
	private $_no_items_callback = array();
	private $_message_callback = array();

	function __construct( $feeds, $slug, $columns = array(), $bulk_actions, $action_links, $column_value_callback, $no_items_callback, $message_callback, $addon_class ) {
		$this->_bulk_actions          = $bulk_actions;
		$this->_feeds                 = $feeds;
		$this->_slug                  = $slug;
		$this->_columns               = $columns;
		$this->_column_value_callback = $column_value_callback;
		$this->_action_links          = $action_links;
		$this->_no_items_callback     = $no_items_callback;
		$this->_message_callback      = $message_callback;
		$this->_addon_class           = $addon_class;

		$standard_cols = array(
			'cb'        => esc_html__( 'Checkbox', 'gravityforms' ),
			'is_active' => '',
		);

		$all_cols = array_merge( $standard_cols, $columns );

		$this->_column_headers = array(
			$all_cols,
			array(),
			array(),
			rgar( array_keys( $all_cols ), 2 ),
		);

		parent::__construct(
			array(
				'singular' => esc_html__( 'feed', 'gravityforms' ),
				'plural'   => esc_html__( 'feeds', 'gravityforms' ),
				'ajax'     => false,
			)
		);
	}

	function prepare_items() {
		$this->items = isset( $this->_feeds ) ? $this->_feeds : array();
	}

	function get_columns() {
		return $this->_column_headers[0];
	}

	function get_bulk_actions() {
		return $this->_bulk_actions;
	}

	function no_items() {
		echo call_user_func( $this->_no_items_callback );
	}

	function display_rows_or_placeholder() {
		$message = call_user_func( $this->_message_callback );

		if ( $message !== false ) {
			?>
			<tr class="no-items">
				<td class="colspanchange" colspan="<?php echo $this->get_column_count() ?>">
					<?php echo $message ?>
				</td>
			</tr>
		<?php
		} else {
			parent::display_rows_or_placeholder();
		}

	}

	function column_default( $item, $column ) {

		if ( is_callable( $this->_column_value_callback ) ) {
			$value = call_user_func( $this->_column_value_callback, $item, $column );
		}

		// Adding action links to the first column of the list
		$columns = array_keys( $this->_columns );
		if ( is_array( $columns ) && count( $columns ) > 0 && $columns[0] == $column ) {
			$value = $this->add_action_links( $item, $column, $value );
		}

		return $value;
	}

	function column_cb( $item ) {
		$feed_id = rgar( $item, 'id' );

		return sprintf(
			'<input type="checkbox" name="feed_ids[]" value="%s" />', esc_attr( $feed_id )
		);
	}

	function add_action_links( $item, $column, $value ) {

		/**
		 * Adds action links to feed items
		 *
		 * @param array  $this->_action_links Action links to be filtered.
		 * @param array  $item                The feed item being filtered.
		 * @param string $column              The column ID
		 */
		$actions = apply_filters( $this->_slug . '_feed_actions', $this->_action_links, $item, $column );

		// Replacing _id_ merge variable with actual feed id
		foreach ( $actions as $action => &$link ) {
			$link = str_replace( '_id_', $item['id'], $link );
		}

		if ( ! $this->_addon_class->can_duplicate_feed( $item['id'] ) ) {
			unset( $actions['duplicate'] );
		}

		return sprintf( '%1$s %2$s', $value, $this->row_actions( $actions ) );
	}

	function _column_is_active( $item, $classes, $data, $primary ) {

		// Open cell as a table header.
		echo '<th scope="row" class="manage-column column-is_active">';

		// Get feed active state.
		$is_active = intval( rgar( $item, 'is_active' ) );

		// Get active image URL.
		$src = GFCommon::get_base_url() . "/images/active{$is_active}.png";

		// Get image title tag.
		$title = $is_active ? esc_attr__( 'Active', 'gravityforms' ) : esc_attr__( 'Inactive', 'gravityforms' );

		// Display feed active button.
		echo sprintf( '<img src="%s" title="%s" onclick="gaddon.toggleFeedActive(this, \'%s\', \'%s\');" onkeypress="gaddon.toggleFeedActive(this, \'%s\', \'%s\');" style="cursor:pointer";/>', $src, $title, esc_js( $this->_slug ), esc_js( $item['id'] ), esc_js( $this->_slug ), esc_js( $item['id'] ) );

		// Close cell.
		echo '</th>';

	}

}
