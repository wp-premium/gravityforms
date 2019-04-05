<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/*
 * NOTE: To add upgrade logic to a new version,
 * look into the post_upgrade_schema() function for a sample and instructions on how to do it.
 */

class GF_Upgrade {

	private $versions = null;

	/**
	 * Contains all DB versions that require a manual upgrade via the upgrade wizard.
	 *
	 * @var array
	 */
	private $manual_upgrade_versions = array();

	private static $_instance = null;

	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new GF_Upgrade();
		}

		return self::$_instance;
	}

	/**
	 * Determines if the installation wizard or upgrade wizard should be displayed and renders the appropriate screen.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @return bool Returns true if a wizard is displayed, false otherwise?
	 */
	public function maybe_display_wizard() {

		$result = false;
		if ( $this->requires_install_wizard() ) {

			require_once( GFCommon::get_base_path() . '/includes/wizard/class-gf-installation-wizard.php' );
			$wizard = new GF_Installation_Wizard;
			$result = $wizard->display();

		} elseif ( $this->requires_upgrade_wizard() ) {

			require_once( GFCommon::get_base_path() . '/includes/wizard/class-gf-upgrade-wizard.php' );
			$wizard = new GF_Upgrade_Wizard;
			$result = $wizard->display();

		}

		return $result;
	}

	/**
	 * Decides to execute a fresh install of Gravity Forms, upgrade an existing installation, or do nothing if versions are up-to-date.
	 *
	 * @since  2.2
	 */
	public function maybe_upgrade() {

		$versions = $this->get_versions();

		if ( $this->requires_install() ) {
			// First time install
			$this->install();

			// Show installation wizard for all new installations as long as the key wasn't already set e.g. by the CLI.
			if ( ! get_option( 'rg_gforms_key' ) ) {
				update_option( 'gform_pending_installation', true );
			}
		} elseif ( $this->is_downgrading() ) {

			GFForms::$background_upgrader->clear_queue( true );
			$this->clear_previous_upgrade();

			$this->update_db_version();

			update_option( 'rg_form_version', GFForms::$version, false );

		} elseif ( $this->requires_upgrade() && ! $this->requires_upgrade_wizard() ) {

			$this->maybe_clear_previous_upgrade();

			// An upgrade is required, and it can be done automatically.
			// If upgrading to this version requires the Upgrade Wizard, nothing will be done here. Instead, the upgrade will happen via the Upgrade Wizard.
			// Upgrades Gravity Forms.
			$this->upgrade();

		}

	}

	/**
	 * Is currently downgrading?
	 *
	 * @since 2.2
	 *
	 * @return mixed
	 */
	public function is_downgrading() {
		$versions         = $this->get_versions();
		$is_downgrading = version_compare( $versions['version'], $versions['current_version'], '<' );

		if ( $is_downgrading ) {

			// Making sure version has really changed. Gets around aggressive caching issue on some sites that cause setup to run multiple times.
			$versions['current_version'] = $this->get_wp_option( 'rg_form_version' );

			$is_downgrading = version_compare( $versions['version'], $versions['current_version'], '<' );
		}
		return $is_downgrading;
	}

	/**
	 * Performs an upgrade of Gravity Forms.
	 *
	 * @since  2.2
	 *
	 * @param bool|null $from_db_version
	 * @param bool $force_upgrade
	 *
	 * @return bool
	 */
	public function upgrade( $from_db_version = null, $force_upgrade = false ) {

		if ( $force_upgrade ) {
			$this->clear_previous_upgrade();
		}

		$versions = $this->get_versions();

		if ( $from_db_version === null ) {
			$from_db_version = empty( $versions['current_db_version'] ) ? $versions['current_version'] : $versions['current_db_version'];
			if ( $from_db_version != $versions['previous_db_version'] ) {
				// Updating previous DB version ( used when upgrade process is re-run )
				update_option( 'gf_previous_db_version', $from_db_version );
				$this->flush_versions();
			}
		}

		if ( ! $this->set_upgrade_started( $from_db_version, $force_upgrade ) ) {

			// Upgrade can't be started. Abort.
			return false;
		}

		// Actions before upgrading schema
		$this->pre_upgrade_schema( $from_db_version );

		// Upgrading schema
		$this->upgrade_schema();

		// Start upgrade routine
		if ( $force_upgrade || ! ( defined( 'GFORM_AUTO_DB_MIGRATION_DISABLED' ) && GFORM_AUTO_DB_MIGRATION_DISABLED ) ) {
			$this->post_upgrade_schema( $from_db_version, $force_upgrade );
		}

		return true;
	}

	/**
	 * Performs initial install of Gravity Forms.
	 *
	 * @since  2.2
	 */
	public function install() {
		$this->flush_versions();

		// Setting Database version
		update_option( 'gf_db_version', GFForms::$version, false );

		update_option( 'rg_form_version', GFForms::$version, false );

		// Installing schema
		$this->upgrade_schema();

		// Turn background updates on by default for all new installations.
		update_option( 'gform_enable_background_updates', true );

		// Auto-setting and auto-validating license key based on value configured via the GF_LICENSE_KEY constant or the gf_license_key variable
		// Auto-populating reCAPTCHA keys base on constant
		$this->maybe_populate_keys();

		// Auto-importing forms based on GF_IMPORT_FILE AND GF_THEME_IMPORT_FILE
		$this->maybe_import_forms();

		/**
		 * Fires after Gravity Forms is fully installed.
		 *
		 * @since 2.2
		 *
		 * @param int $version The new $version.
		 */
		do_action( 'gform_post_install', GFForms::$version );

	}

	/**
	 * Checks whether an upgrade is already in progress.
	 *
	 * @since 2.2
	 *
	 * @return bool
	 */
	public function is_upgrading() {
		global $wpdb;

		$wpdb->flush();

		$is_upgrading = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='gf_upgrade_lock'" );

		return $is_upgrading ? true : false;
	}

	private function set_upgrade_started( $from_db_version = null, $force_upgrade = false ) {
		global $wpdb;

		$lock_params = $this->get_upgrade_lock();

		if ( $lock_params && ! $force_upgrade ) {

			GFCommon::log_debug( __METHOD__ . '(): Upgrade in process. Aborting' );

			// Abort. Upgrade already in process.
			return false;
		}

		$insert = $lock_params === null;

		$versions = $this->get_versions();

		$lock_params = array(
			'from_gf_version' => $versions['current_version'],
			'to_version' => $versions['version'],
			'from_db_version' => $from_db_version,
			'force_upgrade' => $force_upgrade,
		);

		$lock_params_serialized = serialize( $lock_params );

		if ( $insert ) {

			$lock_params_serialized = serialize( $lock_params );

			$sql = $wpdb->prepare( "INSERT INTO {$wpdb->options}(option_name, option_value) VALUES('gf_upgrade_lock', %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`)", $lock_params_serialized );

			// Lock upgrade
			$wpdb->query( $sql );
			GFCommon::log_debug( __METHOD__ . '(): Upgrade Locked.' );
		} else {

			$sql = $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name='gf_upgrade_lock'", $lock_params_serialized );

			// Lock upgrade
			$wpdb->query( $sql );
			GFCommon::log_debug( __METHOD__ . '(): Upgrade Locked.' );
		}

		$system_status_link_open  = sprintf( '<a href="%s">', admin_url( 'admin.php?page=gf_system_status' ) );
		$system_status_link_close = '</a>';

		/* translators: 1: version number 2: open link tag 3: closing link tag. */
		$message = sprintf( esc_html__( 'Gravity Forms is currently upgrading the database to version %1$s. For sites with a large number of entries this may take a long time. Check the %2$sSystem Status%3$s page for further details.', 'gravityforms' ), GFForms::$version, $system_status_link_open, $system_status_link_close );

		$key = sanitize_key( 'gravityforms_upgrading_' . $versions['version'] );

		GFCommon::add_dismissible_message( $message, $key, 'warning', 'gform_full_access', true );

		return true;
	}

	public function set_upgrade_ended() {

		$lock_params = $this->get_upgrade_lock();

		// Unlock upgrade
		$this->clear_upgrade_lock();

		$version = GFForms::$version;

		$force_upgrade = (bool) $lock_params['force_upgrade'];
		$from_db_version = $lock_params['from_db_version'];

		/**
		 * Fires after Gravity Forms is fully upgraded.
		 *
		 * @since 2.2
		 *
		 * @param string $version         The new version.
		 * @param string $from_db_version The old ( pre upgrade ) $version.
		 * @param bool   $force_upgrade
		 *
		 */
		do_action( 'gform_post_upgrade', $version, $from_db_version, $force_upgrade );

		delete_option( 'gform_upgrade_status' );

		// Updating current Gravity Forms version.
		update_option( 'rg_form_version', $version );

		$this->flush_versions();

		$key = sanitize_key( 'gravityforms_upgrading_' . GFForms::$version );

		GFCommon::remove_dismissible_message( $key );

		// Clear all transients to make sure the new version doesn't use cached results.
		GFCache::flush( true );

		$this->add_post_upgrade_admin_notices();

		GFCommon::log_debug( __METHOD__ . '(): Upgrade Completed.' );
	}

	/**
	 * Performs upgrade tasks needed to be done after the DB schema has been upgraded.
	 *
	 * @since  2.2
	 *
	 * @param $from_db_version
	 */
	protected function pre_upgrade_schema( $from_db_version ) {

	}

	/**
	 * Sets up the database for Gravity Forms
	 *
	 * @since  2.2
	 * @global $wpdb
	 *
	 * @return void
	 */
	/**
	 * Sets up the database for Gravity Forms
	 *
	 * @since  2.2
	 * @global $wpdb
	 *
	 * @return void
	 */
	public function upgrade_schema() {

		$schema = $this->get_db_schema();

		foreach ( $schema as $table_name => $sql ) {
			$this->dbDelta( $sql );
		}

	}

	public function get_db_schema( $table_name = null ) {

		global $wpdb;

		/*
		 * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
		 * As of 4.2, however, WP core moved to utf8mb4, which uses 4 bytes per character. This means that an index which
		 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
		 */
		$max_index_length = 191;

		$tables = array();

		$charset_collate = $wpdb->get_charset_collate();

		$form_table_name            = $wpdb->prefix . 'gf_form';
		$tables[ $form_table_name ] =
			'CREATE TABLE ' . $form_table_name . " (
              id mediumint(8) unsigned not null auto_increment,
              title varchar(150) not null,
              date_created datetime not null,
              date_updated datetime,
              is_active tinyint(1) not null default 1,
              is_trash tinyint(1) not null default 0,
              PRIMARY KEY  (id)
            ) $charset_collate;";

		$meta_table_name            = $wpdb->prefix . 'gf_form_meta';
		$tables[ $meta_table_name ] = 'CREATE TABLE ' . $meta_table_name . " (
              form_id mediumint(8) unsigned not null,
              display_meta longtext,
              entries_grid_meta longtext,
              confirmations longtext,
              notifications longtext,
              PRIMARY KEY  (form_id)
            ) $charset_collate;";

		$form_view_table_name            = $wpdb->prefix . 'gf_form_view';
		$tables[ $form_view_table_name ] =
			'CREATE TABLE ' . $form_view_table_name . " (
              id bigint(20) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              date_created datetime not null,
              ip char(15),
              count mediumint(8) unsigned not null default 1,
              PRIMARY KEY  (id),
              KEY date_created (date_created),
              KEY form_id (form_id)
            ) $charset_collate;";

		$revisions_table_name            = GFFormsModel::get_form_revisions_table_name();
		$tables[ $revisions_table_name ] = 'CREATE TABLE ' . $revisions_table_name . " (
		      id bigint(20) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              display_meta longtext,
              date_created datetime not null,
              PRIMARY KEY  (id),
              KEY date_created (date_created),
              KEY form_id (form_id)
            ) $charset_collate;";

		$entry_table_name = GFFormsModel::get_entry_table_name();
		$tables[ $entry_table_name ] =
			'CREATE TABLE ' . $entry_table_name . " (
              id int(10) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              post_id bigint(20) unsigned,
              date_created datetime not null,
              date_updated datetime,
              is_starred tinyint(1) not null default 0,
              is_read tinyint(1) not null default 0,
              ip varchar(39) not null,
              source_url varchar(200) not null default '',
              user_agent varchar(250) not null default '',
              currency varchar(5),
              payment_status varchar(15),
              payment_date datetime,
              payment_amount decimal(19,2),
              payment_method varchar(30),
              transaction_id varchar(50),
              is_fulfilled tinyint(1),
              created_by bigint(20) unsigned,
              transaction_type tinyint(1),
              status varchar(20) not null default 'active',
              PRIMARY KEY  (id),
              KEY form_id (form_id),
              KEY form_id_status (form_id,status)
            ) $charset_collate;";

		$entry_notes_table_name = GFFormsModel::get_entry_notes_table_name();
		$tables[ $entry_notes_table_name ] =
			'CREATE TABLE ' . $entry_notes_table_name . " (
              id int(10) unsigned not null auto_increment,
              entry_id int(10) unsigned not null,
              user_name varchar(250),
              user_id bigint(20),
              date_created datetime not null,
              value longtext,
              note_type varchar(50),
              sub_type varchar(50),
              PRIMARY KEY  (id),
              KEY entry_id (entry_id),
              KEY entry_user_key (entry_id,user_id)
            ) $charset_collate;";

		$entry_meta_table_name = GFFormsModel::get_entry_meta_table_name();
		$tables[ $entry_meta_table_name ] =
			'CREATE TABLE ' . $entry_meta_table_name . " (
              id bigint(20) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null default 0,
              entry_id bigint(20) unsigned not null,
              meta_key varchar(255),
              meta_value longtext,
              item_index varchar(60),
              PRIMARY KEY  (id),
              KEY meta_key (meta_key($max_index_length)),
              KEY entry_id (entry_id),
              KEY meta_value (meta_value($max_index_length))
            ) $charset_collate;";

		$draft_submissions_table_name = GFFormsModel::get_draft_submissions_table_name();
		$tables[ $draft_submissions_table_name ] =
			'CREATE TABLE ' . $draft_submissions_table_name . " (
              uuid char(32) not null,
              email varchar(255),
              form_id mediumint(8) unsigned not null,
              date_created datetime not null,
              ip varchar(39) not null,
              source_url longtext not null,
              submission longtext not null,
              PRIMARY KEY  (uuid),
              KEY form_id (form_id)
            ) $charset_collate;";

		//Return schema for a particular table if the table name parameter is specified.
		if ( $table_name ) {
			return isset( $tables[ $table_name ] ) ? $tables[ $table_name ] : false;
		} else {
			//Return schema for all tables
			return $tables;
		}
	}

	public function check_table_schema( $table_name ) {

		$schema    = $this->get_db_schema( $table_name );
		$to_update = $this->dbDelta( $schema, false );

		//If $to_update is empty, that means table has been updated correctly.
		if ( empty( $to_update ) ) {
			return true;
		} else {

			return false;
		}

	}

	public function dbDelta( $sql, $execute = true ) {
		global $wpdb;

		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

		//Fixes issue with dbDelta lower-casing table names, which cause problems on case sensitive DB servers.
		add_filter( 'dbdelta_create_queries', array( $this, 'dbdelta_fix_case' ) );

		$result = dbDelta( $sql, $execute );

		remove_filter( 'dbdelta_create_queries', array( $this, 'dbdelta_fix_case' ) );

		return $result;
	}

	/**
	 * Performs upgrade tasks needed to be done after the DB schema has been upgraded.
	 *
	 * @since  2.2
	 *
	 * @param string $from_db_version
	 * @param bool   $force_upgrade
	 */
	protected function post_upgrade_schema( $from_db_version, $force_upgrade ) {

		$versions = $this->get_versions();

		// If the version is not set in the DB, use 0
		$current_db_version = empty( $from_db_version ) ? $versions['current_version'] : $from_db_version;

		//-- Data Upgrade Process Start --//

		$this->update_upgrade_status( esc_html__( 'Queued for upgrade.', 'gravityforms' ) );

		if ( version_compare( $current_db_version, '2.0.4.7', '<' ) ) {
			$this->post_upgrade_schema_2047();
		}

		if ( version_compare( $current_db_version, '2.3-dev-1', '<' ) ) {
			GFForms::$background_upgrader->push_to_queue( array( $this, 'gf_upgrade_block_submissions' ) );
			GFForms::$background_upgrader->push_to_queue( array( $this, 'gf_upgrade_230_migrate_forms' ) );
			GFForms::$background_upgrader->push_to_queue( array( $this, 'gf_upgrade_230_migrate_leads' ) );
			GFForms::$background_upgrader->push_to_queue( array( $this, 'gf_upgrade_230_migrate_incomplete_submissions' ) );
			GFForms::$background_upgrader->push_to_queue( array( $this, 'gf_upgrade_230_migrate_lead_notes' ) );
			GFForms::$background_upgrader->push_to_queue( array( $this, 'gf_upgrade_release_submissions_block' ) );
		}

		/*
		 * To add new upgrade logic, create a function formatted post_upgrade_schema_VERSION()
		 * and add an if statement like the one below so that it gets executed when upgrading to the right version

		if ( version_compare( $current_db_version, '2.3', '<' )) {
			$this->post_upgrade_schema_230();
		}
		if ( version_compare( $current_db_version, '2.4', '<' ) ) {
			$this->post_upgrade_schema_240();
		}
		*/

		if ( GFForms::$background_upgrader->get_data() ) {
			GFForms::$background_upgrader->push_to_queue( array( $this, 'post_background_upgrade' ) );
			GFForms::$background_upgrader->save();
			if ( $force_upgrade ) {
				// Simulate triggering the cron task
				GFForms::$background_upgrader->handle_cron_healthcheck();
			} else {
				GFForms::$background_upgrader->dispatch();
			}
		} else {
			GFCommon::log_debug( __METHOD__ . '(): Background upgrade not necessary. Setting new version.' );
			$this->update_db_version();
			$this->set_upgrade_ended();
		}
	}

	/**
	 * Performs any final tasks after the background upgrade tasks have finished.
	 *
	 * @return false Return false to remove this final task from the queue.
	 */
	public function post_background_upgrade() {
		// Return false to remove this final task from the background updates queue.
		$this->update_db_version();

		GFCommon::log_debug( __METHOD__ . '(): Background upgrade complete' );

		$this->set_upgrade_ended();

		return false;
	}

	/**
	 * Updates the status of the upgrade process
	 *
	 * @param $new_status
	 */
	public function update_upgrade_status( $new_status ) {
		update_option( 'gform_upgrade_status', $new_status );
	}

	/**
	 * Upgrade task to block submissions.
	 *
	 * @return bool
	 */
	public function gf_upgrade_block_submissions() {
		$this->set_submissions_block();
		return false;
	}

	/**
	 * Upgrade task to release the submissions block.
	 *
	 * @return bool
	 */
	public function gf_upgrade_release_submissions_block() {
		$this->clear_submissions_block();
		return false;
	}

	/**
	 * Upgrade forms to 2.3
	 *
	 * @return bool
	 */
	public function gf_upgrade_230_migrate_forms() {
		global $wpdb;
		$this->update_upgrade_status( esc_html__( 'Migrating forms.', 'gravityforms' ) );

		// Migrate form headers

		$legacy_forms_table = $wpdb->prefix . 'rg_form';
		$new_forms_table = $wpdb->prefix . 'gf_form';

		$sql = "
INSERT INTO {$new_forms_table}
(id, title, date_created, date_updated, is_active, is_trash)
SELECT
id, title, date_created, null, is_active, is_trash
FROM
  {$legacy_forms_table} lf
WHERE lf.id NOT IN
      ( SELECT id
      	FROM {$new_forms_table}
      	)";

		$wpdb->query( $sql );

		// Migrate form meta

		$legacy_form_meta_table = $wpdb->prefix . 'rg_form_meta';
		$new_form_meta_table = $wpdb->prefix . 'gf_form_meta';

		$sql = "
INSERT INTO {$new_form_meta_table}
(form_id, display_meta, entries_grid_meta, confirmations, notifications)
SELECT
form_id, display_meta, entries_grid_meta, confirmations, notifications
FROM
  {$legacy_form_meta_table} lfm
WHERE lfm.form_id NOT IN
      ( SELECT form_id
      	FROM {$new_form_meta_table}
      	)";

		$wpdb->query( $sql );

		// Migrate form view data

		$legacy_form_view_table = $wpdb->prefix . 'rg_form_view';
		$new_form_view_table = $wpdb->prefix . 'gf_form_view';

		$sql = "
INSERT INTO {$new_form_view_table}
(id, form_id, date_created, ip, count)
SELECT
id, form_id, date_created, ip, count
FROM
  {$legacy_form_view_table} lfv
WHERE lfv.id NOT IN
      ( SELECT id
      	FROM {$new_form_view_table}
      	)";

		$wpdb->query( $sql );

		$this->update_upgrade_status( esc_html__( 'Forms migrated.', 'gravityforms' ) );
		return false;
	}

	/**
	 * Upgrade leads to 2.3
	 *
	 * @return bool
	 */
	public function gf_upgrade_230_migrate_leads() {
		global $wpdb;

		if ( defined( 'GFORM_DB_MIGRATION_BATCH_SIZE' ) ) {
			$limit = GFORM_DB_MIGRATION_BATCH_SIZE;
		} else {
			$limit = 20000;
		}

		$lead_table = GFFormsModel::get_lead_table_name();
		$entry_table = GFFormsModel::get_entry_table_name();
		$time_start = microtime( true );

		$lead_ids_sql = "SELECT l2.id
FROM {$lead_table} l2
WHERE l2.id NOT IN ( SELECT e.id FROM {$entry_table} e )
LIMIT {$limit}";

		do {

			$lead_ids = $wpdb->get_col( $lead_ids_sql );

			if ( $wpdb->last_error ) {
				/* translators: %s: the database error */
				$this->update_upgrade_status( sprintf( esc_html__( 'Error Migrating Entry Headers: %s', 'gravityforms' ), $wpdb->last_error ) );
				// wp_die() is not used here because it would trigger another async task
				exit;
			}

			if ( ! empty( $lead_ids ) ) {
				$lead_ids = array_map( 'absint', $lead_ids );

				$count_lead_ids = count( $lead_ids );

				if ( ! empty( $count_lead_ids ) ) {
					$lead_ids_in = join( ',', $lead_ids );
					// Add the lead header to the data
					$sql = "
INSERT INTO $entry_table
(id, form_id, post_id, date_created, date_updated, is_starred, is_read, ip, source_url, user_agent, currency, payment_status, payment_date, payment_amount, payment_method, transaction_id, is_fulfilled, created_by, transaction_type, status)
SELECT
id, form_id, post_id, date_created, null, is_starred, is_read, ip, source_url, user_agent, currency, payment_status, payment_date, payment_amount, payment_method, transaction_id, is_fulfilled, created_by, transaction_type, status
FROM
$lead_table l
WHERE l.id IN ( {$lead_ids_in} )";

					$wpdb->query( $sql );

					if ( $wpdb->last_error ) {
						/* translators: %s: the database error */
						$this->update_upgrade_status( sprintf( esc_html__( 'Error Migrating Entry Headers: %s', 'gravityforms' ), $wpdb->last_error ) );
						// wp_die() is not used here because it would trigger another async task
						exit;
					}

					$current_time = microtime( true );
					$execution_time = ( $current_time - $time_start );
					if ( $execution_time > 15 ) {
						$sql_remaining = "SELECT COUNT(l2.id)
FROM {$lead_table} l2
WHERE l2.id NOT IN ( SELECT e.id FROM {$entry_table} e )";
						$remaining = $wpdb->get_var( $sql_remaining );
						if ( $remaining > 0 ) {
							$this->update_upgrade_status( sprintf( esc_html__( 'Migrating leads. Step 1/3 Migrating entry headers. %d rows remaining.', 'gravityforms' ), $remaining ) );
							return true;
						}
					}
				}
			}
		} while ( ! empty( $lead_ids ) );

		// Lead details

		$lead_details_table = GFFormsModel::get_lead_details_table_name();
		$entry_meta_table = GFFormsModel::get_entry_meta_table_name();

		$lead_detail_ids_sql = "
SELECT ld.id
FROM {$lead_details_table} ld
WHERE ld.id NOT IN ( SELECT em.id FROM {$entry_meta_table} em )
LIMIT {$limit}";

		do {
			$lead_detail_ids = $wpdb->get_col( $lead_detail_ids_sql );

			if ( $wpdb->last_error ) {
				error_log('error: ' . $wpdb->last_error );
				/* translators: %s: the database error */
				$this->update_upgrade_status( sprintf( esc_html__( 'Error Migrating Entry Details: %s', 'gravityforms' ), $wpdb->last_error ) );
				// wp_die() is not used here because it would trigger another async task
				exit;
			}

			if ( ! empty( $lead_detail_ids ) ) {
				$lead_detail_ids = array_map( 'absint', $lead_detail_ids );

				$lead_detail_ids_in = join( ',', $lead_detail_ids );

				// Add the lead header to the data
				$sql = "
INSERT INTO {$entry_meta_table}
  (id, entry_id, form_id, meta_key, meta_value)
SELECT
  id, lead_id, form_id, CAST(field_number AS CHAR), value
FROM
  {$lead_details_table} ld
WHERE ld.id IN ( {$lead_detail_ids_in} )";

				$wpdb->query( $sql );

				if ( $wpdb->last_error ) {
					error_log('error: ' . $wpdb->last_error );
					/* translators: %s: the database error */
					$this->update_upgrade_status( sprintf( esc_html__( 'Error Migrating Entry Details: %s', 'gravityforms' ), $wpdb->last_error ) );
					// wp_die() is not used here because it would trigger another async task
					exit;
				}

				$current_time = microtime( true );
				$execution_time = ( $current_time - $time_start );
				if ( $execution_time > 15 ) {
					$sql_remaining = "
SELECT COUNT(ld.id)
FROM {$lead_details_table} ld
WHERE ld.id NOT IN ( SELECT em.id FROM {$entry_meta_table} em )";
					$remaining = $wpdb->get_var( $sql_remaining );
					if ( $remaining > 0 ) {
						$this->update_upgrade_status( sprintf( esc_html__( 'Migrating leads. Step 2/3 Migrating entry details. %d rows remaining.', 'gravityforms' ), $remaining ) );
						return true;
					}
				}
			}
		} while ( ! empty( $lead_detail_ids ) );

		// Lead meta

		$lead_meta_table = GFFormsModel::get_lead_meta_table_name();
		$entry_meta_table = GFFormsModel::get_entry_meta_table_name();

		$charset_db = empty( $wpdb->charset ) ? 'utf8mb4' : $wpdb->charset;

		$collate = ! empty( $wpdb->collate ) ? " COLLATE {$wpdb->collate}" : '';

		$lead_meta_ids_sql = "
SELECT
  id
FROM
  {$lead_meta_table} lm
WHERE NOT EXISTS
      (SELECT * FROM {$entry_meta_table} em WHERE em.entry_id = lm.lead_id AND CONVERT(em.meta_key USING {$charset_db}) = CONVERT(lm.meta_key USING {$charset_db}) {$collate})
LIMIT {$limit}";

		do {
			$lead_meta_ids = $wpdb->get_col( $lead_meta_ids_sql );

			if ( $wpdb->last_error ) {
				/* translators: %s: the database error */
				$this->update_upgrade_status( sprintf( esc_html__( 'Error Migrating Entry Meta: %s', 'gravityforms' ), $wpdb->last_error ) );
				// wp_die() is not used here because it would trigger another async task
				exit;
			}

			if ( ! empty( $lead_meta_ids ) ) {
				$lead_meta_ids = array_map( 'absint', $lead_meta_ids );

				$lead_meta_ids_in = join( ',', $lead_meta_ids );

				// Add the lead header to the data
				$sql = "
INSERT INTO {$entry_meta_table}
  (entry_id, form_id, meta_key, meta_value)
SELECT
  lead_id, form_id, meta_key, meta_value
FROM
  {$lead_meta_table} lm
WHERE lm.id IN ( {$lead_meta_ids_in} )";

				$wpdb->query( $sql );

				if ( $wpdb->last_error ) {
					/* translators: %s: the database error */
					$this->update_upgrade_status( sprintf( esc_html__( 'Error Migrating Entry Meta: %s', 'gravityforms' ), $wpdb->last_error ) );
					// wp_die() is not used here because it would trigger another async task
					exit;
				}

				$current_time = microtime( true );
				$execution_time = ( $current_time - $time_start );
				if ( $execution_time > 15 ) {
					$sql_remaining = "
SELECT COUNT(id)
FROM
  {$lead_meta_table} lm
WHERE NOT EXISTS
      (SELECT * FROM {$entry_meta_table} em WHERE em.entry_id = lm.lead_id AND CONVERT(em.meta_key USING {$charset_db}) = CONVERT(lm.meta_key USING {$charset_db}) {$collate})";
					$remaining = $wpdb->get_var( $sql_remaining );
					if ( $remaining > 0 ) {
						$this->update_upgrade_status( sprintf( esc_html__( 'Migrating leads. Step 3/3 Migrating entry meta. %d rows remaining.', 'gravityforms' ), $remaining ) );
						return true;
					}
				}
			}
		} while ( ! empty( $lead_meta_ids ) );

		$this->update_upgrade_status( esc_html__( 'Entry details migrated.', 'gravityforms' ) );
		return false;
	}

	/**
	 * Upgrade incomplete submissions
	 *
	 * @return bool
	 */
	public function gf_upgrade_230_migrate_incomplete_submissions() {
		global $wpdb;

		$this->update_upgrade_status( esc_html__( 'Migrating incomplete submissions.', 'gravityforms' ) );

		$incomplete_submissions_table = GFFormsModel::get_incomplete_submissions_table_name();

		if ( ! GFCommon::table_exists( $incomplete_submissions_table ) ) {
			// The table doesn't exist. Maybe an upgrade from a very early version.
			return false;
		}

		$draft_submissions_table = GFFormsModel::get_draft_submissions_table_name();

		$charset_db = empty( $wpdb->charset ) ? 'utf8mb4' : $wpdb->charset;

		$collate = ! empty( $wpdb->collate ) ? " COLLATE {$wpdb->collate}" : '';

		$sql = "
INSERT INTO {$draft_submissions_table}
SELECT *
FROM
  {$incomplete_submissions_table} insub
WHERE CONVERT(insub.uuid USING {$charset_db}) {$collate} NOT IN
      ( SELECT uuid FROM {$draft_submissions_table} )";

		$wpdb->query( $sql );

		if ( $wpdb->last_error ) {
			/* translators: %s: the database error */
			$this->update_upgrade_status( sprintf( esc_html__( 'Error Migrating incomplete submissions: %s', 'gravityforms' ), $wpdb->last_error ) );
			// wp_die() is not used here because it would trigger another async task
			exit;
		}
		return false;
	}

	/**
	 * Upgrade lead notes to 2.3
	 *
	 * @return bool
	 */
	public function gf_upgrade_230_migrate_lead_notes() {
		global $wpdb;

		$this->update_upgrade_status( esc_html__( 'Migrating entry notes.', 'gravityforms' ) );

		$lead_notes_details_table = GFFormsModel::get_lead_notes_table_name();
		$entry_notes_table = GFFormsModel::get_entry_notes_table_name();

		$sql = "
INSERT INTO {$entry_notes_table}
  (id, entry_id, user_name, user_id, date_created, value, note_type )
SELECT
  id, lead_id, user_name, user_id, date_created, value, note_type
FROM
  {$lead_notes_details_table} ln
WHERE ln.id NOT IN
      ( SELECT id
      	FROM {$entry_notes_table}
      	)";

		$wpdb->query( $sql );
		return false;
	}

	/**
	 * Imports Gravity Forms license keys, and reCAPTCHA keys from global variables.
	 *
	 * @since  2.2
	 * @access protected
	 * @global $gf_license_key
	 *
	 * @uses   GF_RECAPTCHA_PRIVATE_KEY
	 * @uses   GF_RECAPTCHA_PUBLIC_KEY
	 * @uses   GF_LICENSE_KEY
	 */
	protected function maybe_populate_keys() {

		global $gf_license_key;
		$license_key = defined( 'GF_LICENSE_KEY' ) && empty( $gf_license_key ) ? GF_LICENSE_KEY : $gf_license_key;
		if ( ! empty( $license_key ) ) {
			RGFormsModel::save_key( $license_key );
			GFCommon::cache_remote_message();
			GFCommon::get_version_info( false );
		}

		// Auto-setting recaptcha keys based on value configured via the constant or global variable
		global $gf_recaptcha_public_key, $gf_recaptcha_private_key;
		$private_key = defined( 'GF_RECAPTCHA_PRIVATE_KEY' ) && empty( $gf_recaptcha_private_key ) ? GF_RECAPTCHA_PRIVATE_KEY : $gf_recaptcha_private_key;
		if ( ! empty( $private_key ) ) {
			update_option( 'rg_gforms_captcha_private_key', $private_key );
		}

		$public_key = defined( 'GF_RECAPTCHA_PUBLIC_KEY' ) && empty( $gf_recaptcha_public_key ) ? GF_RECAPTCHA_PUBLIC_KEY : $gf_recaptcha_public_key;
		if ( ! empty( $public_key ) ) {
			update_option( 'rg_gforms_captcha_public_key', $public_key );
		}

	}

	/**
	 * Auto imports forms when Gravity Forms is installed based on GF_IMPORT_FILE constant.
	 *
	 * @since  2.2
	 * @access protected
	 *
	 * @uses   GF_IMPORT_FILE
	 * @uses   GFCommon::get_base_path()
	 * @uses   GFExport::import_file()
	 */
	protected function maybe_import_forms() {

		if ( defined( 'GF_IMPORT_FILE' ) && ! get_option( 'gf_imported_file' ) ) {

			require_once( GFCommon::get_base_path() . '/export.php' );
			GFExport::import_file( GF_IMPORT_FILE );
			update_option( 'gf_imported_file', true );
		}
	}

	/**
	 * Imports theme-specific forms, if needed.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses   GF_THEME_IMPORT_FILE
	 * @uses   GFExport::import_file()
	 * @uses   GFCommon::get_base_path()
	 *
	 * @return void
	 */
	public function maybe_import_theme_forms() {

		//Import theme specific forms if configured. Will only import forms once per theme.
		if ( defined( 'GF_THEME_IMPORT_FILE' ) ) {
			$themes = get_option( 'gf_imported_theme_file' );
			if ( ! is_array( $themes ) ) {
				$themes = array();
			}

			//if current theme has already imported it's forms, don't import again
			$theme = get_template();
			if ( ! isset( $themes[ $theme ] ) ) {

				require_once( GFCommon::get_base_path() . '/export.php' );

				//importing forms
				GFExport::import_file( get_stylesheet_directory() . '/' . GF_THEME_IMPORT_FILE );

				//adding current theme to the list of imported themes. So that forms are not imported again for it.
				$themes[ $theme ] = true;
				update_option( 'gf_imported_theme_file', $themes );
			}
		}

	}

	/**
	 * Gets the value of an option directly from the wp_options table. This is useful for double checking the value of
	 * autoload options returned by get_option().
	 *
	 * The result is cached by wpdb so this is only really useful once per request.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @param string $option_name The option to find.
	 *
	 * @return string|null The option value, if found.
	 */
	public function get_wp_option( $option_name ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name=%s", $option_name ) );
	}


	/**
	 * Upgrade routine from gravity forms version 2.0.4.7 and below
	 */
	protected function post_upgrade_schema_2047() {

		global $wpdb;

		$versions = $this->get_versions();

		$form_table_name      = RGFormsModel::get_form_table_name();
		$meta_table_name      = RGFormsModel::get_meta_table_name();
		$lead_meta_table_name = RGFormsModel::get_lead_meta_table_name();

		// dropping table that was created by mistake in version 1.6.3.2
		$wpdb->query( 'DROP TABLE IF EXISTS A' . $form_table_name );

		// dropping outdated form_id index (if one exists)
		$this->drop_index( $meta_table_name, 'form_id' );

		// The format the version info changed to JSON. Make sure the old format is not cached.
		if ( version_compare( $versions['current_version'], '1.8.0.3', '<' ) ) {
			delete_option( 'gform_version_info' );
		}

		//fix leading and trailing spaces in Form objects and entry values
		if ( version_compare( $versions['current_version'], '1.8.3.1', '<' ) ) {
			$this->fix_leading_and_trailing_spaces();
		}

		// The rest only needs to run if the lead tables exist.

		$long_table_name = GFFormsModel::get_lead_details_long_table_name();

		$result = $wpdb->query( "SHOW TABLES LIKE '{$long_table_name}'" );

		if ( $wpdb->num_rows !== 1 ) {
			return;
		}

		// dropping meta_key and form_id_meta_key (if they exist) to prevent duplicate keys error on upgrade
		if ( version_compare( $versions['current_version'], '1.9.8.12', '<' ) ) {
			$this->drop_index( $lead_meta_table_name, 'meta_key' );
			$this->drop_index( $lead_meta_table_name, 'form_id_meta_key' );
		}

		//fix form_id value needed to update from version 1.6.11
		$this->fix_lead_meta_form_id_values();

		//fix checkbox value. needed for version 1.0 and below but won't hurt for higher versions
		$this->fix_checkbox_value();

		$this->maybe_upgrade_lead_detail_table();

	}

	/**
	 * Fixes case for database queries.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $cqueries Queries to be fixed.
	 *
	 * @return array $queries Queries after processing.
	 */
	public function dbdelta_fix_case( $cqueries ) {
		$queries = array();
		foreach ( $cqueries as $table => $qry ) {
			$table_name = $table;
			if ( preg_match( '|CREATE TABLE ([^ ]*)|', $qry, $matches ) ) {
				$query_table_name = trim( $matches[1], '`' );

				//fix table names that are different just by their casing
				if ( strtolower( $query_table_name ) == $table ) {
					$table_name = $query_table_name;
				}
			}
			$queries[ $table_name ] = $qry;
		}

		return $queries;
	}

	/**
	 * Fixes leading and trailing spaces within Gravity Forms tables.
	 *
	 * @since  Unknown
	 * @access private
	 * @global $wpdb
	 *
	 * @return array $results Content that was processed through the function.
	 */
	private function fix_leading_and_trailing_spaces() {

		global $wpdb;

		$meta_table_name    = GFFormsModel::get_meta_table_name();
		$lead_details_table = GFFormsModel::get_lead_details_table_name();

		$result = $wpdb->query( "UPDATE {$lead_details_table} SET value = TRIM(value)" );

		$results = $wpdb->get_results( "SELECT form_id, display_meta, confirmations, notifications FROM {$meta_table_name}", ARRAY_A );

		foreach ( $results as &$result ) {
			$form_id = $result['form_id'];

			$form         = GFFormsModel::unserialize( $result['display_meta'] );
			$form_updated = false;
			$form         = GFFormsModel::trim_form_meta_values( $form, $form_updated );
			if ( $form_updated ) {
				GFFormsModel::update_form_meta( $form_id, $form );
			}

			$confirmations         = GFFormsModel::unserialize( $result['confirmations'] );
			$confirmations_updated = false;
			$confirmations         = GFFormsModel::trim_conditional_logic_values( $confirmations, $form, $confirmations_updated );
			if ( $confirmations_updated ) {
				GFFormsModel::update_form_meta( $form_id, $confirmations, 'confirmations' );
			}

			$notifications         = GFFormsModel::unserialize( $result['notifications'] );
			$notifications_updated = false;
			$notifications         = GFFormsModel::trim_conditional_logic_values( $notifications, $form, $notifications_updated );
			if ( $notifications_updated ) {
				GFFormsModel::update_form_meta( $form_id, $notifications, 'notifications' );
			}
		}

		return $results;
	}

	/**
	 * Fixes checkbox values in the database.
	 *
	 * @since  Unknown
	 * @access private
	 * @global $wpdb
	 */
	private function fix_checkbox_value() {
		global $wpdb;

		$table_name = RGFormsModel::get_lead_details_table_name();

		$sql     = "select * from {$table_name} where value= '!'";
		$results = $wpdb->get_results( $sql );
		foreach ( $results as $result ) {
			$form  = RGFormsModel::get_form_meta( $result->form_id );
			$field = RGFormsModel::get_field( $form, $result->field_number );
			if ( $field->type == 'checkbox' ) {
				$input = GFCommon::get_input( $field, $result->field_number );
				$wpdb->update( $table_name, array( 'value' => $input['label'] ), array( 'id' => $result->id ) );
			}
		}
	}

	/**
	 * Changes form_id values from default value "0" to the correct value.
	 *
	 * Needed when upgrading users from 1.6.11.
	 *
	 * @since  Unknown
	 * @access private
	 * @global $wpdb
	 */
	private function fix_lead_meta_form_id_values() {
		global $wpdb;

		$lead_meta_table_name = RGFormsModel::get_lead_meta_table_name();
		$lead_table_name      = RGFormsModel::get_lead_table_name();

		$sql = "UPDATE $lead_meta_table_name lm,$lead_table_name l SET lm.form_id = l.form_id
				WHERE lm.form_id=0 AND lm.lead_id = l.id;
				";
		$wpdb->get_results( $sql );

	}

	/**
	 * Drops a table index.
	 *
	 * @since  Unknown
	 * @access public
	 * @global       $wpdb
	 *
	 * @param string $table The table that the index will be dropped from.
	 * @param string $index The index to be dropped.
	 *
	 * @return void
	 */
	public function drop_index( $table, $index ) {
		global $wpdb;

		if ( ! GFFormsModel::is_valid_table( $table ) || ! GFFormsModel::is_valid_index( $index ) ) {
			return;
		}

		// check first if the table exists to prevent errors on first install
		$has_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $has_table ) {

			$has_index = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name=%s", $index ) );

			if ( $has_index ) {
				$wpdb->query( "DROP INDEX {$index} ON {$table}" );
			}
		}
	}

	/**
	 * Upgrades the lead detail table.
	 *
	 * @since  Unknown
	 * @access private
	 * @global $wpdb
	 *
	 * @return void
	 */
	private function maybe_upgrade_lead_detail_table() {
		global $wpdb;

		$versions        = $this->get_versions();
		$current_version = $versions['current_version'];

		GFCommon::log_debug( __METHOD__ . '(): Starting' );

		if ( ! $this->can_upgrade_longtext() ) {
			GFCommon::log_debug( __METHOD__ . '(): Bailing' );

			return;
		}

		// Populate the details value with long table values
		$result = $wpdb->query( "
UPDATE {$wpdb->prefix}rg_lead_detail d
INNER JOIN {$wpdb->prefix}rg_lead_detail_long l ON d.id = l.lead_detail_id
SET d.value = l.value"
		);

		GFCommon::remove_dismissible_message( 'gform_long_table_upgrade' );

		GFCommon::log_debug( __METHOD__ . '(): result: ' . print_r( $result, true ) );
	}

	/**
	 * Validates that Gravity Forms is doing the database upgrade, and has permissions to do so.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param null   $do_upgrade Not used.
	 * @param string $hook_extra The plugin triggering the upgrade.
	 *
	 * @return bool|WP_Error True if successful.  Otherwise WP_Error object.
	 */
	public function validate_upgrade( $do_upgrade, $hook_extra ) {

		if ( rgar( $hook_extra, 'plugin' ) == 'gravityforms/gravityforms.php' && ! $this->has_database_permission( $error ) ) {
			return new WP_Error( 'no_db_permission', $error );
		}

		return true;
	}

	/**
	 * Checks if Gravity Forms has permissions to make changes to the database.
	 *
	 * @since   Unknown
	 * @access  private
	 * @global       $wpdb
	 *
	 * @used-by GFForms::validate_upgrade()
	 *
	 * @param string $error Error, if there was a problem somewhere.
	 *
	 * @return bool $has_permissions True if permissions are fine.  False otherwise.
	 */
	public function has_database_permission( &$error ) {
		global $wpdb;

		$wpdb->hide_errors();

		$has_permission = true;

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rg_test ( col1 int PRIMARY KEY )";
		$wpdb->query( $sql );
		$error = 'Current database user does not have necessary permissions to create tables. Gravity Forms requires that the database user has CREATE and ALTER permissions. If you need assistance in changing database user permissions, contact your hosting provider.';
		if ( ! empty( $wpdb->last_error ) ) {
			$has_permission = false;
		}

		if ( $has_permission ) {
			$sql = "ALTER TABLE {$wpdb->prefix}rg_test ADD COLUMN a" . uniqid() . ' int';
			$wpdb->query( $sql );
			$error = 'Current database user does not have necessary permissions to modify (ALTER) tables. Gravity Forms requires that the database user has CREATE and ALTER permissions. If you need assistance in changing database user permissions, contact your hosting provider.';
			if ( ! empty( $wpdb->last_error ) ) {
				$has_permission = false;
			}

			$sql = "DROP TABLE {$wpdb->prefix}rg_test";
			$wpdb->query( $sql );
		}

		$wpdb->show_errors();

		return $has_permission;
	}


	/**
	 * Checks whether the values in the longtext table should be copied over to the
	 *
	 * @return bool
	 */
	private function can_upgrade_longtext() {
		global $wpdb;

		$versions        = $this->get_versions();
		$current_version = $versions['current_version'];

		if ( empty( $current_version ) ) {
			return false;
		}

		// The gform_longtext_ready option was set in 1.9.x to indicate that the lead details table had been upgraded.
		// It was also set for new installations of 1.9.x.
		$is_longtext_ready = (bool) get_option( 'gform_longtext_ready' );

		if ( $is_longtext_ready ) {
			return false;
		}

		// The gform_longtext_upgraded option was added by the Upgrade Wizard Support Tool used to help debug upgrade issues.
		$upgraded = (bool) get_option( 'gform_longtext_upgraded' );

		if ( $upgraded ) {
			return false;
		}

		// Check the length of the value column in the lead detail table to make sure it's now longtext.

		$lead_detail_table_name = GFFormsModel::get_lead_details_table_name();

		$is_longtext = $this->check_column( $lead_detail_table_name, 'value', 'longtext' );

		$first_entry_value = $wpdb->get_results( "SELECT value FROM $lead_detail_table_name LIMIT 1" );

		$col_type = $wpdb->get_col_info( 'type', 0 ); // Get type of column from the last wpdb query.

		if ( ! $is_longtext ) {
			if ( $col_type == '252' || $col_type == 'blob' ) {
				$is_longtext = true;
			} else {
				$lead_detail_table = GFFormsModel::get_lead_details_table_name();

				$result = $wpdb->query( "ALTER TABLE {$lead_detail_table} MODIFY `value` LONGTEXT;" );
				if ( empty( $wpdb->last_error ) ) {
					$is_longtext = true;
				} else {
					$wpdb->show_errors();
				}
			}
		}

		if ( ! $is_longtext ) {

			// Something's wrong with the lead detail value column. Log, add a dismissible admin message and bail.

			GFCommon::log_debug( __METHOD__ . '(): lead detail value column issue' );

			GFCommon::add_dismissible_message( esc_html__( 'There appears to be an issue with one of the Gravity Forms database tables. Please get in touch with support.', 'gravityforms' ), 'gform_long_table_upgrade', 'error', 'gform_full_access', true );

			return false;
		}

		if ( empty( $first_entry_value ) ) {
			// Make sure previous upgrade failure admin message is removed for sites with no entries.
			GFCommon::remove_dismissible_message( 'gform_long_table_upgrade' );

			return false;
		}

		$can_upgrade = false;

		if ( version_compare( $current_version, '2.0-beta-3.2', '<' )  // No upgrades have been attempted.
		     || ( version_compare( $current_version, '2.0.2.6', '<' ) && ! method_exists( $wpdb, 'get_col_length' ) )          // $wpdb->get_col_length() was introduced in WP 4.2.1. Attempts to upgrade will have caused a fatal error.
		     || ( version_compare( $current_version, '2.0.2.6', '<' )  // Some upgrades prior to 2.0.2.6 failed because $wpdb->get_col_length() returned false. e.g. installations using HyperDB
		          && method_exists( $wpdb, 'get_col_length' )
		          && $wpdb->get_col_length( $wpdb->prefix . 'rg_lead_detail', 'value' ) === false )
		     || ( version_compare( $current_version, '2.0.4.6', '<' ) // Upgrades failed where db layers returned 'blob' as longtext column type.
		          && $col_type == 'blob' )
		) {

			// Check that all IDs in the detail table are unique.

			$results = $wpdb->get_results( "
SELECT id
FROM {$wpdb->prefix}rg_lead_detail
GROUP BY id
HAVING count(*) > 1;" );

			if ( count( $results ) == 0 ) {

				$can_upgrade = true;

			} else {

				// IDs are not unique - log, add a dismissible admin message.

				GFCommon::log_debug( __METHOD__ . '(): lead detail IDs issue' );

				GFCommon::add_dismissible_message( esc_html__( 'There appears to be an issue with the data in the Gravity Forms database tables. Please get in touch with support.', 'gravityforms' ), 'gform_long_table_upgrade', 'error', 'gform_full_access', true );
			}
		}

		GFCommon::log_debug( __METHOD__ . '(): can_upgrade: ' . $can_upgrade );

		return $can_upgrade;
	}


	/**
	 * Check column matches criteria.
	 *
	 * Based on the WordPress check_column() function.
	 *
	 * @since  2.0.2.6
	 * @access public
	 * @global wpdb  $wpdb       WordPress database abstraction object.
	 *
	 * @param string $table_name Table name.
	 * @param string $col_name   Column name.
	 * @param string $col_type   Column type.
	 * @param bool   $is_null    Optional. Check is null.
	 * @param mixed  $key        Optional. Key info.
	 * @param mixed  $default    Optional. Default value.
	 * @param mixed  $extra      Optional. Extra value.
	 *
	 * @return bool True, if matches. False, if not matching.
	 */
	private function check_column( $table_name, $col_name, $col_type, $is_null = null, $key = null, $default = null, $extra = null ) {
		global $wpdb;
		$diffs   = 0;
		$results = $wpdb->get_results( "DESC $table_name" );

		foreach ( $results as $row ) {

			if ( $row->Field == $col_name ) {

				// Got our column, check the params.
				if ( ( $col_type != null ) && ( $row->Type != $col_type ) ) {
					++ $diffs;
				}
				if ( ( $is_null != null ) && ( $row->Null != $is_null ) ) {
					++ $diffs;
				}
				if ( ( $key != null ) && ( $row->Key != $key ) ) {
					++ $diffs;
				}
				if ( ( $default != null ) && ( $row->Default != $default ) ) {
					++ $diffs;
				}
				if ( ( $extra != null ) && ( $row->Extra != $extra ) ) {
					++ $diffs;
				}
				if ( $diffs > 0 ) {
					return false;
				}

				return true;
			} // end if found our column
		}

		return false;
	}

	/**
	 * Returns the version numbers for the codebase, the current
	 *
	 * @return array|null
	 */
	public function get_versions() {

		if ( ! empty( $this->versions ) ) {
			return $this->versions;
		}

		$previous_db_version = get_option( 'gf_previous_db_version' );


		$this->versions = array(
			'version'             => GFForms::$version,
			'current_version'     => get_option( 'rg_form_version' ),
			'current_db_version'  => GFFormsModel::get_database_version(),
			'previous_db_version' => empty( $previous_db_version ) ? '0' : $previous_db_version,
		);

		return $this->versions;
	}

	/**
	 * Flushes cached versions.
	 */
	public function flush_versions() {
		$this->versions = null;
		wp_cache_delete( 'gf_db_version' );
		wp_cache_delete( 'rg_form_version' );
	}

	/**
	 * Returns true if Gravity Forms need to be installed. False otherwise.
	 * @since 2.2
	 * @return bool
	 */
	public function requires_install() {

		$versions = $this->get_versions();

		// If current version isn't set, go through an initial install.
		$requires_install = rgempty( 'current_version', $versions );

		return $requires_install;
	}

	/**
	 * Returns true if Gravity Forms need to be upgraded. False otherwise.
	 *
	 * @since 2.2
	 * @return bool
	 */
	public function requires_upgrade() {

		// Upgrade is not required on a fresh install. Go through installation process instead.
		if ( $this->requires_install() ) {
			return false;
		}

		$versions         = $this->get_versions();
		$upgrade_required = version_compare( $versions['version'], $versions['current_version'], '>' );

		if ( $upgrade_required ) {

			// Making sure version has really changed. Gets around aggressive caching issue on some sites that cause setup to run multiple times.
			$versions['current_version'] = $this->get_wp_option( 'rg_form_version' );

			$upgrade_required = version_compare( $versions['version'], $versions['current_version'], '>' );
		}

		return $upgrade_required;
	}

	/**
	 * Returns true if the install wizard should be displayed. False otherwise.
	 *
	 * @since 2.2
	 * @return bool
	 */
	public function requires_install_wizard() {

		if ( defined( 'GF_LICENSE_KEY' ) && is_multisite() && ! is_main_site() ) {
			return false;
		}

		$pending_installation = get_option( 'gform_pending_installation' ) || isset( $_GET['gform_installation_wizard'] );

		//Display install wizard if this is a fresh install or if the installation wizard is in progress ( i.e. pending )
		$install_wizard_required = $this->requires_install() || $pending_installation;

		return $install_wizard_required;
	}

	/**
	 * Returns true if the upgrade wizard should be displayed. False otherwise.
	 *
	 * @since 2.2
	 *
	 * @return bool
	 */
	public function requires_upgrade_wizard() {

		// Version is up-to-date. No need to upgrade, so no need for upgrade wizard.
		if ( ! $this->requires_upgrade() ) {
			return false;
		}

		$versions = $this->get_versions();

		foreach ( $this->manual_upgrade_versions as $manually_upgraded_version ) {

			// Display the upgrade wizard if current DB version is prior to any version that requires an upgrade wizard.
			if ( version_compare( $versions['current_db_version'], $manually_upgraded_version, '<' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Update DB version to current.
	 *
	 * @since 2.2
	 *
	 * @param string $version
	 */
	public function update_db_version( $version = null ) {
		$version = is_null( $version ) ? GFForms::$version : $version;
		update_option( 'gf_db_version', $version, false );
	}

	/**
	 * Checks whether the previous upgrade can be cleared and then clears it.
	 *
	 * @since 2.3
	 */
	public function maybe_clear_previous_upgrade() {

		$lock_params = $this->get_upgrade_lock();

		if ( $lock_params ) {
			$to_version = rgar( $lock_params, 'to_version' );

			$versions = $this->get_versions();

			if ( $to_version != $versions['version'] ) {
				$this->clear_previous_upgrade();
			}
		}

	}

	/**
	 * Clears the previous upgrade.
	 *
	 * @since 2.3
	 */
	public function clear_previous_upgrade() {

		// Clear the queue for this blog.
		GFForms::$background_upgrader->clear_queue();

		// Remove the status update
		update_option( 'gform_upgrade_status', false );

		// Remove dismissible messages
		$lock_params = $this->get_upgrade_lock();
		if ( $lock_params ) {
			$to_version = rgar( $lock_params, 'to_gf_version' );
			$key        = sanitize_key( 'gravityforms_upgrading_' . $to_version );
			GFCommon::remove_dismissible_message( $key );
		}

		// Clear the upgrade lock
		$this->clear_upgrade_lock();

		$this->clear_submissions_block();
	}

	/**
	 * Clears the upgrade lock.
	 *
	 * @since 2.3
	 *
	 * @return bool False if value was not updated and true if value was updated.
	 */
	public function clear_upgrade_lock() {
		$result = update_option( 'gf_upgrade_lock', false );
		return $result;
	}

	/**
	 * Returns the upgrade lock.
	 *
	 * @since 2.3
	 *
	 * @return array|null
	 */
	public function get_upgrade_lock() {
		global $wpdb;

		$lock_params_serialized = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='gf_upgrade_lock'" );

		$lock_params = maybe_unserialize( $lock_params_serialized );

		return $lock_params;
	}

	/**
	 * Blocks submissions.
	 *
	 * @since 2.3
	 *
	 * @return bool False if value was not updated and true if value was updated.
	 */
	public function set_submissions_block() {
		$result = update_option( 'gf_submissions_block', time() );
		return $result;
	}

	/**
	 * Clears the submissions block.
	 *
	 * @since 2.3
	 *
	 * @return bool False if value was not updated and true if value was updated.
	 */
	public function clear_submissions_block() {
		$result = update_option( 'gf_submissions_block', false );
		return $result;
	}

	/**
	 * Returns the timestamp of the submissions block or null if not locked.
	 *
	 * @since 2.3
	 *
	 * @return string|null
	 */
	public function get_submissions_block() {
		global $wpdb;

		$timestamp = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='gf_submissions_block'" );

		return $timestamp;
	}

	/**
	 * Adds dismissible admin notices.
	 *
	 * @since 2.3
	 */
	public function add_post_upgrade_admin_notices() {

		$previous_db_version = get_option( 'gf_previous_db_version' );

		$key = sanitize_key( 'gravityforms_outdated_addons_2.3' );

		if ( version_compare( $previous_db_version, '2.3-beta-1', '>' ) ) {
			GFCommon::remove_dismissible_message( $key );
			return;
		}

		$add_ons = $this->get_min_addon_requirements();

		$outdated = array();

		foreach ( $add_ons as $plugin_slug => $add_on ) {
			$plugin_path = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_slug;
			if ( ! file_exists( $plugin_path ) ) {
				continue;
			}
			$plugin_data     = get_plugin_data( $plugin_path, false, false );
			$current_version = $plugin_data['Version'];
			$add_on          = $add_ons[ $plugin_slug ];
			$min_version     = $add_on['min_version'];
			if ( version_compare( $current_version, $min_version, '<' ) ) {
				$name       = $add_on['name'];
				$outdated[] = $name;
			}
		}

		if ( empty( $outdated ) ) {
			return;
		}

		$number_outdated = count( $outdated );

		if ( $number_outdated == 1 ) {
			/* translators: %s: the add-on name */
			$message = sprintf( esc_html__( 'The %s is not compatible with this version of Gravity Forms. See the plugins list for further details.', 'gravityforms' ), $outdated[0] );
		} else {
			/* translators: %d: the number of outdated add-ons */
			$message = sprintf( esc_html__( 'There are %d add-ons installed that are not compatible with this version of Gravity Forms. See the plugins list for further details.', 'gravityforms' ), $number_outdated );
		}

		GFCommon::add_dismissible_message( $message, $key, 'error', 'gform_full_access', true, 'site-wide' );
	}

	/**
	 * Returns an array of add-ons with the minimum version required for this version of Gravity Forms.
	 *
	 * @since 2.3
	 *
	 * @return array
	 */
	public function get_min_addon_requirements() {
		return array(
			'gravityformspaypal/paypal.php'                       => array(
				'name'        => 'Gravity Forms PayPal Add-On',
				'min_version' => '2.9',
			),
			'gravityformsauthorizenet/authorizenet.php'           => array(
				'name'        => 'Gravity Forms Authorize.Net Add-On',
				'min_version' => '2.4',
			),
			'gravityformspartialentries/partialentries.php'       => array(
				'name'        => 'Gravity Forms Partial Entries Add-On',
				'min_version' => '1.1',
			),
			'gravityformspaypalpaymentspro/paypalpaymentspro.php' => array(
				'name'        => 'Gravity Forms PayPal Payments Pro Add-On',
				'min_version' => '2.3',
			),
			'gravityformssignature/signature.php'                 => array(
				'name'        => 'Gravity Forms Signature Add-On',
				'min_version' => '3.4',
			),
			'gravityformsuserregistration/userregistration.php'   => array(
				'name'        => 'Gravity Forms User Registration Add-On',
				'min_version' => '3.9',
			),
			'gravityformspaypalpro/paypalpro.php'                 => array(
				'name'        => 'Gravity Forms PayPal Pro Add-On',
				'min_version' => '1.8',
			),
		);
	}
}
