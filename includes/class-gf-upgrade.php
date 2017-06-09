<?php
/*
 * NOTE: To add upgrade logic to a new version,
 * look into the post_upgrade_schema() function for a sample and instructions on how to do it.
 */

/** WordPress Upgrade Functions */
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

class GF_Upgrade {

	private $versions = null;

	/**
	 * Contains all DB versions that require a manual upgrade via the upgrade wizard.
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
		} elseif ( $this->requires_upgrade() && ! $this->requires_upgrade_wizard() ) {

			// An upgrade is required, and it can be done automatically.
			// If upgrading to this version requires the Upgrade Wizard, nothing will be done here. Instead, the upgrade will happen via the Upgrade Wizard.
			// Upgrades Gravity Forms.
			$this->upgrade();

		}

		// Updating current Gravity Forms version.
		if ( $versions['current_version'] != $versions['version'] ) {
			update_option( 'rg_form_version', $versions['version'] );
			$this->flush_versions();
		}

	}

	/**
	 * Performs an upgrade of Gravity Forms.
	 *
	 * @since 2.2.1.14 Update cached remote message.
	 * @since 2.2
	 */
	public function upgrade( $from_db_version = null, $force_upgrade = false ) {

		if ( ! $this->set_upgrade_started( $force_upgrade ) ) {
			// Upgrade can't be started. Abort.
			return false;
		}

		$versions = $this->get_versions();

		if ( $from_db_version === null ) {
			$from_db_version = empty( $versions['current_db_version'] ) ? 0 : $versions['current_db_version'];
		}

		// Actions before uprading schema
		$this->pre_upgrade_schema( $from_db_version );

		// Upgrading schema
		$this->upgrade_schema();

		// Start upgrade routine
		$this->post_upgrade_schema( $from_db_version );

		// Updating previous DB version ( used when upgrade process is re-run )
		if ( $from_db_version != $versions['version'] ) {
			update_option( 'gf_previous_db_version', $from_db_version );
		}

		// Updating DB version
		update_option( 'gf_db_version', $versions['version'] );

		/**
		 * Fires after Gravity Forms is fully upgraded.
		 *
		 * @since 2.2
		 *
		 * @param int $version         The new $version.
		 * @param int $current_version The old ( pre upgrade ) $version.
		 */
		do_action( 'gform_post_upgrade', $versions['version'], $from_db_version, $force_upgrade );

		$this->set_upgrade_ended();

		$this->flush_versions();

		// Updating cached message so the extremely outdated version message is removed.
		GFCommon::cache_remote_message();

	}

	/**
	 * Performs initial install of Gravity Forms
	 * @since  2.2
	 */
	public function install() {

		// Installing schema
		$this->upgrade_schema();

		// Turn background updates on by default for all new installations.
		update_option( 'gform_enable_background_updates', true );

		// Auto-setting and auto-validating license key based on value configured via the GF_LICENSE_KEY constant or the gf_license_key variable
		// Auto-populating reCAPTCHA keys base on constant
		$this->maybe_populate_keys();

		// Auto-importing forms based on GF_IMPORT_FILE AND GF_THEME_IMPORT_FILE
		$this->maybe_import_forms();

		// Setting Database version
		update_option( 'gf_db_version', GFForms::$version );

		/**
		 * Fires after Gravity Forms is fully installed.
		 *
		 * @since 2.2
		 *
		 * @param int $version The new $version.
		 */
		do_action( 'gform_post_install', GFForms::$version );

	}

	public function is_upgrading() {
		global $wpdb;

		$is_upgrading = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='gf_is_upgrading'" );

		return $is_upgrading ? true : false;
	}

	private function set_upgrade_started( $force_upgrade = false ) {
		global $wpdb;

		$is_upgrading = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='gf_is_upgrading'" );

		if ( $force_upgrade ) {
			//force upgrading is set
			$is_upgrading = $is_upgrading === null ? null : '0';
		}

		if ( $is_upgrading === '1' ) {

			GFCommon::log_debug( 'Upgrade in process. Aborting' );

			//Abort. Upgrade already in process.
			return false;
		}

		if ( $is_upgrading === null ) {

			//Lock upgrade
			$wpdb->query( "INSERT INTO {$wpdb->options}(option_name, option_value) VALUES('gf_is_upgrading', '1')" );
			GFCommon::log_debug( 'Upgrade Locked' );
		} else {

			//Lock upgrade
			$wpdb->query( "UPDATE {$wpdb->options} SET option_value='1' WHERE option_name='gf_is_upgrading'" );
			GFCommon::log_debug( 'Upgrade Locked.' );
		}

		return true;
	}

	private function set_upgrade_ended() {
		global $wpdb;

		//Unlock upgrade
		$wpdb->query( "UPDATE {$wpdb->options} SET option_value='0' WHERE option_name='gf_is_upgrading'" );

		GFCommon::log_debug( 'Upgrade Completed.' );
	}

	/**
	 * Performs upgrade tasks needed to be done after the DB schema has been upgraded.
	 * @since  2.2
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

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		$form_table_name            = RGFormsModel::get_form_table_name();
		$tables[ $form_table_name ] =
			'CREATE TABLE ' . $form_table_name . " (
              id mediumint(8) unsigned not null auto_increment,
              title varchar(150) not null,
              date_created datetime not null,
              is_active tinyint(1) not null default 1,
              is_trash tinyint(1) not null default 0,
              PRIMARY KEY  (id)
            ) $charset_collate;";

		$meta_table_name            = RGFormsModel::get_meta_table_name();
		$tables[ $meta_table_name ] = 'CREATE TABLE ' . $meta_table_name . " (
              form_id mediumint(8) unsigned not null,
              display_meta longtext,
              entries_grid_meta longtext,
              confirmations longtext,
              notifications longtext,
              PRIMARY KEY  (form_id)
            ) $charset_collate;";

		$form_view_table_name            = RGFormsModel::get_form_view_table_name();
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

		$lead_table_name            = RGFormsModel::get_lead_table_name();
		$tables[ $lead_table_name ] = 'CREATE TABLE ' . $lead_table_name . " (
              id int(10) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null,
              post_id bigint(20) unsigned,
              date_created datetime not null,
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
              KEY status (status)
            ) $charset_collate;";

		$lead_notes_table_name            = RGFormsModel::get_lead_notes_table_name();
		$tables[ $lead_notes_table_name ] =
			'CREATE TABLE ' . $lead_notes_table_name . " (
              id int(10) unsigned not null auto_increment,
              lead_id int(10) unsigned not null,
              user_name varchar(250),
              user_id bigint(20),
              date_created datetime not null,
              value longtext,
              note_type varchar(50),
              PRIMARY KEY  (id),
              KEY lead_id (lead_id),
              KEY lead_user_key (lead_id,user_id)
            ) $charset_collate;";

		$lead_detail_table_name            = RGFormsModel::get_lead_details_table_name();
		$tables[ $lead_detail_table_name ] =
			'CREATE TABLE ' . $lead_detail_table_name . " (
              id bigint(20) unsigned not null auto_increment,
              lead_id int(10) unsigned not null,
              form_id mediumint(8) unsigned not null,
              field_number float not null,
              value longtext,
              PRIMARY KEY  (id),
              KEY form_id (form_id),
              KEY lead_id (lead_id),
              KEY lead_field_number (lead_id,field_number),
              KEY lead_field_value (value($max_index_length))
            ) $charset_collate;";

		$lead_detail_long_table_name            = RGFormsModel::get_lead_details_long_table_name();
		$tables[ $lead_detail_long_table_name ] =
			'CREATE TABLE ' . $lead_detail_long_table_name . " (
              lead_detail_id bigint(20) unsigned not null,
              value longtext,
              PRIMARY KEY  (lead_detail_id)
            ) $charset_collate;";

		$lead_meta_table_name            = RGFormsModel::get_lead_meta_table_name();
		$tables[ $lead_meta_table_name ] =
			'CREATE TABLE ' . $lead_meta_table_name . " (
              id bigint(20) unsigned not null auto_increment,
              form_id mediumint(8) unsigned not null default 0,
              lead_id bigint(20) unsigned not null,
              meta_key varchar(255),
              meta_value longtext,
              PRIMARY KEY  (id),
              KEY meta_key (meta_key($max_index_length)),
              KEY lead_id (lead_id),
              KEY form_id_meta_key (form_id,meta_key($max_index_length))
            ) $charset_collate;";

		$incomplete_submissions_table_name            = RGFormsModel::get_incomplete_submissions_table_name();
		$tables[ $incomplete_submissions_table_name ] =
			'CREATE TABLE ' . $incomplete_submissions_table_name . " (
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
	 * @since  2.2
	 */
	protected function post_upgrade_schema( $from_db_version ) {

		$versions = $this->get_versions();

		// If the version is not set in the DB, use 0
		$current_db_version = empty( $from_db_version ) ? '0' : $from_db_version;

		//-- Data Upgrade Process Start --//

		if ( version_compare( $current_db_version, '2.2', '<' ) ) {
			$this->post_upgrade_schema_220();
		}

		/*
		 * To add new upgrade logic, create a function formatted post_upgrade_schema_VERSION()
		 * and add an if statement like the one below so that it gets executed when upgrading to the right version

		if ( $current_db_version < 2 ) {
			$this->post_upgrade_schema_230();
		}
		if ( $current_db_version < 3 ) {
			$this->post_upgrade_schema_240();
		}
		*/

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
	 * Gets the value of an option from the wp_options table.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @param string $option_name The option to find.
	 *
	 * @return string The option value, if found.
	 */
	public function get_wp_option( $option_name ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name=%s", $option_name ) );
	}


	/**
	 * Upgrade routine from gravity forms version 2.2 and below
	 */
	protected function post_upgrade_schema_220() {

		global $wpdb;

		$versions = $this->get_versions();

		$form_table_name      = RGFormsModel::get_form_table_name();
		$meta_table_name      = RGFormsModel::get_meta_table_name();
		$lead_meta_table_name = RGFormsModel::get_lead_meta_table_name();

		// droping table that was created by mistake in version 1.6.3.2
		$wpdb->query( 'DROP TABLE IF EXISTS A' . $form_table_name );

		// droping outdated form_id index (if one exists)
		$this->drop_index( $meta_table_name, 'form_id' );

		// The format the version info changed to JSON. Make sure the old format is not cached.
		if ( version_compare( $versions['current_version'], '1.8.0.3', '<' ) ) {
			delete_transient( 'gform_update_info' );
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

		//fix leading and trailing spaces in Form objects and entry values
		if ( version_compare( $versions['current_version'], '1.8.3.1', '<' ) ) {
			$this->fix_leading_and_trailing_spaces();
		}

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
		$upgrade_required = $versions['version'] != $versions['current_version'];

		if ( $upgrade_required ) {

			// Making sure version has really changed. Gets around aggressive caching issue on some sites that cause setup to run multiple times.
			$versions['current_version'] = $this->get_wp_option( 'rg_form_version' );

			$upgrade_required = $versions['version'] != $versions['current_version'];
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
}
