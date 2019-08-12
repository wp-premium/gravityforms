<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

GFForms::include_addon_framework();

/**
 * Gravity Forms Logging.
 *
 * @since     2.2
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GFLogging extends GFAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  2.2
	 * @access private
	 * @var    object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the plugin slug.
	 *
	 * @since  2.2
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformslogging';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  2.2
	 * @access protected
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformslogging/logging.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  2.2
	 * @access protected
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  2.2
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'Logging';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  2.2
	 * @access protected
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Logging';

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  2.2
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_logging';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  2.2
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_logging';

	/**
	 * Defines the capabilities needed for the Logging Add-On
	 *
	 * @since  2.2
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_logging' );

	/**
	 * Defines the nonce action used when deleting logs from the logging page.
	 *
	 * @since  2.2-beta-2
	 * @access protected
	 * @var    string $nonce_action Nonce action for deleting logs from the logging page.
	 */
	protected $_nonce_action = 'gform_delete_log';

	/**
	 * Contains the KLogger objects for plugins with logging enabled.
	 *
	 * @since  2.2
	 * @access private
	 * @var    array $loggers KLogger objects for plugins with logging enabled.
	 */
	private $loggers = array();

	/**
	 * Defines the maximum file size for a log file.
	 *
	 * @since  2.2.3.3 Reduced max file size from 100MB to 5MB.
	 * @since  2.2
	 * @access private
	 * @var    string $max_file_size Maximum file size for a log file.
	 */
	private $max_file_size = 5242880;

	/**
	 * Defines the maximum number of log files to store for a plugin.
	 *
	 * @since  2.2
	 * @access private
	 * @var    string $max_file_count Maximum number of log files to store for a plugin.
	 */
	private $max_file_count = 10;

	/**
	 * Defines the date format for logged messages.
	 *
	 * @since  2.2
	 * @access private
	 * @var    string $date_format_log_file Date format for logged messages.
	 */
	private $date_format_log_file = 'YmdGis';

	/**
	 * Get instance of this class.
	 *
	 * @since  2.2
	 * @access public
	 * @static
	 *
	 * @return GFLogging
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;

	}

	/**
	 * Register needed hooks and included needed libraries.
	 *
	 * @since  2.2
	 * @access public
	 */
	public function init() {

		parent::init();

		$this->include_logger();

	}

	/**
	 * Maybe delete log files.
	 *
	 * @since  2.2
	 * @access public
	 */
	public function plugin_settings_page() {


		// If the delete_log parameter is set, delete the log file and redirect.
		$plugin_slug = rgget( 'delete_log' );
		if ( $plugin_slug ) {

			$supported_plugins = $this->get_supported_plugins();

			if ( isset( $supported_plugins[ $plugin_slug ] ) ) {
				if ( wp_verify_nonce( rgget( $this->_nonce_action ), $this->_nonce_action ) && $this->delete_log_file( $plugin_slug ) ) {

					// Prepare redirect URL.
					$redirect_url = remove_query_arg( array( 'delete_log', 'gform_delete_log' ) );
					$redirect_url = add_query_arg( array( 'deleted' => '1' ), $redirect_url );
					$redirect_url = esc_url_raw( $redirect_url );

					?>
					<script type="text/javascript">
						document.location.href = <?php echo json_encode( $redirect_url ); ?>;
					</script>
					<?php
					die();

				} else {

					// Display error message.
					GFCommon::add_error_message( esc_html__( 'Log file could not be deleted.', 'gravityforms' ) );
				}
			} else {
				GFCommon::add_error_message( esc_html__( 'Invalid log file.', 'gravityforms' ) );
			}
		}

		// If a log file was deleted, display message.
		if ( '1' === rgget( 'deleted' ) ) {
			GFCommon::add_message( esc_html__( 'Log file was successfully deleted.', 'gravityforms' ) );
		}

		parent::plugin_settings_page();

	}

	/**
	 * Setup plugin settings fields.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		// Get supported plugin fields.
		$plugin_fields = $this->supported_plugins_fields();

		// Add save button to the plugin fields array.
		$plugin_fields[] = array(
			'type'     => 'save',
			'messages' => array(
				'success' => esc_html__( 'Plugin logging settings have been updated.', 'gravityforms' ),
			),
		);

		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => $plugin_fields,
			),
		);

	}

	/**
	 * Setup plugin settings description.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @return string $html
	 */
	public function plugin_settings_description() {

		$html  = '<p>';
		$html .= esc_html__( 'Logging assists in tracking down issues by logging debug and error messages in Gravity Forms Core and Gravity Forms Add-Ons. Important information may be included in the logging messages, including API usernames, passwords and credit card numbers. Logging is intended only to be used temporarily while trying to track down issues. Once the issue is identified and resolved, it should be disabled.', 'gravityforms' );
		$html .= '</p>';

		return $html;

	}

	/**
	 * Setup plugin settings title.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @return string
	 */
	public function plugin_settings_title() {

		return esc_html__( 'Plugin Logging Settings', 'gravityforms' );

	}

	/**
	 * Prevent Settings link from being added to plugin action links if Logging Add-On is available.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @param array  $links An array of plugin action links.
	 * @param string $file  Path to the plugin file.
	 *
	 * @return array
	 */
	public function plugin_settings_link( $links, $file ) {

		return $links;

	}

	/**
	 * Prevent uninstall section from appearing.
	 *
	 * @since  2.2
	 * @access public
	 */
	public function render_uninstall() {}

	/**
	 * Prepares a checkbox and select field based on the $field array
	 *
	 * @since 1.2
	 * @param array $field - Field array containing the configuration options of this field.
	 *
	 * @return array $field
	 */
	public function prepare_settings_checkbox_and_select( $field ) {

		// Prepare checkbox.
		$checkbox_input = rgars( $field, 'checkbox' );

		$checkbox_field = array(
			'type'       => 'checkbox',
			'name'       => $field['name'] . 'Enable',
			'label'      => esc_html__( 'Enable', 'gravityforms' ),
			'horizontal' => true,
			'value'      => '1',
			'choices'    => false,
			'tooltip'    => false,
		);

		$checkbox_field = wp_parse_args( $checkbox_input, $checkbox_field );

		// Prepare select.
		$select_input = rgars( $field, 'select' );

		$select_field = array(
			'name'    => $field['name'] . 'Value',
			'type'    => 'select',
			'class'   => '',
			'tooltip' => false,
		);

		$select_field['class'] .= ' ' . $select_field['name'];

		$select_field = wp_parse_args( $select_input, $select_field );

		// A little more with the checkbox.
		if ( empty( $checkbox_field['choices'] ) ) {
			$checkbox_field['choices'] = array(
				array(
					'name'          => $checkbox_field['name'],
					'label'         => $checkbox_field['label'],
					'onchange'      => sprintf( "( function( $, elem ) {
						$( elem ).parents( 'td' ).css( 'position', 'relative' );
						if( $( elem ).prop( 'checked' ) ) {
							$( elem ).parent().siblings( 'span' ).css( 'visibility', 'visible' );
						} else {
							$( elem ).parent().siblings( 'span' ).css( 'visibility', 'hidden' );
						}
					} )( jQuery, this );",
					"#{$select_field['name']}Span" ),
				),
			);
		}

		$field['select'] = $select_field;
		$field['checkbox'] = $checkbox_field;

		return $field;
	}

	/**
	 * Prepare supported plugins as plugin settings fields.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @return array $fields
	 */
	public function supported_plugins_fields() {

		// Get the supported plugins.
		$supported_plugins = $this->get_supported_plugins();

		// Setup logging options.
		$logging_options = array(
			array(
				'label' => esc_html__( 'and log all messages', 'gravityforms' ),
				'value' => KLogger::DEBUG,
			),
			array(
				'label' => esc_html__( 'and log only error messages', 'gravityforms' ),
				'value' => KLogger::ERROR,
			),
		);

		$plugin_fields = array();
		$nonce         = wp_create_nonce( $this->_nonce_action );

		// Build the supported plugins fields array.
		foreach ( $supported_plugins as $plugin_slug => $plugin_name ) {

			$after_select = '';

			if ( $this->log_file_exists( $plugin_slug ) ) {
				$delete_url = add_query_arg( array( 'delete_log' => $plugin_slug, $this->_nonce_action => $nonce ), admin_url( 'admin.php?page=gf_settings&subview=gravityformslogging' ) );

				$after_select  = '<br />';
				$after_select .= '<span style="font-size:85%"><a href="' . esc_attr( $this->get_log_file_url( $plugin_slug ) ) . '" target="_blank">' . esc_html__( 'view log', 'gravityforms' ) . '</a>';
				$after_select .= '&nbsp;&nbsp;<a href="' . $delete_url . '">' . esc_html__( 'delete log', 'gravityforms' ) . '</a>';
				$after_select .= '&nbsp;&nbsp;(' . $this->get_log_file_size( $plugin_slug ) . ')</span>';
			}

			$plugin_fields[] = array(
				'name'         => $plugin_slug,
				'label'        => $plugin_name . $after_select,
				'type'         => 'checkbox_and_select',
				'checkbox'     => array(
					'label' => esc_html__( 'Enable logging', 'gravityforms' ),
					'name'  => $plugin_slug . '[enable]',
				),
				'select' => array(
					'name'    => $plugin_slug . '[log_level]',
					'choices' => $logging_options,
				),
			);

			$random = function_exists( 'random_bytes' ) ? random_bytes( 12 ) : wp_generate_password( 24, true, true );
			$plugin_fields[] = array(
				'name'          => $plugin_slug . '[file_name]',
				'type'          => 'hidden',
				'default_value' => sha1( $plugin_slug . $random ),
			);

		}

		return $plugin_fields;

	}

	/**
	 * Log message.
	 *
	 * @access public
	 *
	 * @param string   $plugin Plugin name.
	 * @param string   $message (default: null) Message to log.
	 * @param constant $message_type (default: KLogger::DEBUG) Message type.
	 *
	 * NOTE: This function is static for backwards compatibility reasons. Some legacy add-ons still reference this function statically
	 */
	public static function log_message( $plugin, $message = null, $message_type = KLogger::DEBUG ) {

		// If message is empty, exit.
		if ( rgblank( $message ) || ! class_exists( 'GFForms' ) || ! get_option( 'gform_enable_logging' ) ) {
			return;
		}

		// Include KLogger library.
		self::include_logger();
		$instance = self::get_instance();

		// Get logging setting for plugin.
		$plugin_setting = $instance->get_plugin_setting( $plugin );

		// If logging is turned off, exit.
		if ( rgempty( 'enable', $plugin_setting ) ) {
			return;
		}

		// Log message.
		$log = $instance->get_logger( $plugin, $plugin_setting['log_level'] );
		$log->Log( $message, $message_type );

	}

	/**
	 * Delete log file for plugin.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @param  string $plugin_name Plugin name.
	 *
	 * @return bool If file was successfully deleted
	 */
	public function delete_log_file( $plugin_name ) {

		// Get log file path.
		$log_file = $this->get_log_file_name( $plugin_name );

		// Delete log file.
		return file_exists( $log_file ) ? unlink( $log_file ) : false;

	}

	/**
	 * Delete all log files and log file directory.
	 *
	 * @since  2.2
	 * @access public
	 *
	 */
	public function delete_log_files() {

		$dir = $this->get_log_dir();

		if ( is_dir( $dir ) ) {
			$files = glob( "{$dir}{,.}*" ); // Get all file names.
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file ); // Delete file.
				}
			}
			@rmdir( $dir );
		}

	}

	/**
	 * Get path to log file directory.
	 *
	 * @since  2.2
	 * @access public
	 *
	 *
	 * @return string
	 */
	public function get_log_dir() {

		return GFFormsModel::get_upload_root() . 'logs/';

	}

	/**
	 * Get url to log file directory.
	 *
	 * @since  2.2
	 * @access public
	 *
	 *
	 * @return string
	 */
	public function get_log_dir_url() {

		return GFFormsModel::get_upload_url_root() . 'logs/';

	}

	/**
	 * Get file name for plugin log file.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @param  string $plugin_name Plugin name.
	 *
	 * @return string File path to log file.
	 */
	public function get_log_file_name( $plugin_name ) {

		$log_dir = $this->get_log_dir();

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			@touch( $log_dir . 'index.html' );
		}

		$plugin_setting = $this->get_plugin_setting( $plugin_name );

		return $log_dir . $plugin_name . '_' . $plugin_setting['file_name'] . '.txt';

	}

	/**
	 * Get file url for plugin log file.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @param  string $plugin_name Plugin name.
	 *
	 * @return string URL to log file.
	 */
	public function get_log_file_url( $plugin_name ) {

		$plugin_setting = $this->get_plugin_setting( $plugin_name );

		return $this->get_log_dir_url() . $plugin_name . '_' . $plugin_setting['file_name'] . '.txt';

	}

	/**
	 * Check if log file exists for plugin.
	 *
	 * @since  2.2
	 * @access public
	 * @param  string $plugin_name Plugin slug.
	 * @return bool
	 */
	public function log_file_exists( $plugin_name ) {

		$log_filename = $this->get_log_file_name( $plugin_name );

		return file_exists( $log_filename );

	}

	/**
	 * Get log file size for plugin
	 *
	 * @since 1.2.1
	 * @access public
	 * @param  string $plugin_name Plugin slug.
	 *
	 * @return string File size with unit of measurement.
	 */
	public function get_log_file_size( $plugin_name ) {

		// Get log file name.
		$file = $this->get_log_file_name( $plugin_name );

		// Get log file size.
		$size = filesize( $file );

		// Convert log file size to human readable format.
		$units  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$factor = floor( ( strlen( $size ) - 1 ) / 3 );
		$size   = sprintf( '%.2f', $size / pow( 1024, $factor ) ) . ' ' . $units[ $factor ];

		return $size;

	}

	/**
	 * Include KLogger library.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * NOTE: This function is static for backwards compatibility reasons. Some legacy add-ons still reference this function statically
	 */
	public static function include_logger() {

		if ( ! class_exists( 'KLogger' ) ) {
			require_once 'includes/KLogger.php';
		}

	}

	/**
	 * Get logging object for plugin.
	 *
	 * @since  2.2
	 * @access private
	 *
	 * @param  string   $plugin Plugin slug.
	 * @param  constant $log_level Level messages are being logged at.
	 *
	 * @return object $log
	 */
	private function get_logger( $plugin, $log_level ) {

		if ( isset( $this->loggers[ $plugin ] ) ) {

			// Use existing logger.
			$log = $this->loggers[ $plugin ];

		} else {

			// Get time offset.
			$offset = get_option( 'gmt_offset' ) * 3600;

			// Get log file name.
			$log_file_name = $this->get_log_file_name( $plugin );

			// Clean up log files.
			$this->maybe_reset_logs( $log_file_name, $offset );

			// Create new logger class.
			$log = new KLogger( $log_file_name, $log_level, $offset, $plugin );

			// Set date format.
			$log->DateFormat = 'Y-m-d G:i:s.u';

			// Assign logger class to loggers array.
			$this->loggers[ $plugin ] = $log;

		}

		return $log;

	}

	/**
	 * Disable all logging.
	 *
	 * @since  2.2
	 * @access public
	 *
	 */
	public function disable_logging() {

		$this->update_plugin_settings( array() );

	}

	/**
	 * Clean up log files.
	 *
	 * @since  2.2.5
	 * @access private
	 *
	 * @param  string $file_path Path to log.
	 * @param  string $gmt_offset GMT time offset.
	 */
	private function maybe_reset_logs( $file_path, $gmt_offset ) {

		$path      = pathinfo( $file_path );
		$folder    = $path['dirname'] . '/';
		$file_base = $path['filename'];
		$file_ext  = $path['extension'];

		// Check size of current file. If greater than max file size, rename using time.
		if ( file_exists( $file_path ) && filesize( $file_path ) > $this->max_file_size ) {

			$adjusted_date = gmdate( $this->date_format_log_file, time() + $gmt_offset );
			$new_file_name = $file_base . '_' . $adjusted_date . '.' . $file_ext;
			rename( $file_path, $folder . $new_file_name );

		}

		// Get files which match the base name.
		$similar_files = glob( $folder . $file_base . '*.*' );
		$file_count    = count( $similar_files );

		// Check quantity of files and delete older ones if too many.
		if ( false !== $similar_files && $file_count > $this->max_file_count ) {

			// Sort by date so oldest are first.
			usort( $similar_files, array( $this, 'filemtime_diff' ) );

			$delete_count = $file_count - $this->max_file_count;

			for ( $i = 0; $i < $delete_count; $i++ ) {
				if ( file_exists( $similar_files[ $i ] ) ) {
					unlink( $similar_files[ $i ] );
				}
			}

		}

	}

	/**
	 * Calculate the difference between file modified times.
	 *
	 * @param string $a The path to the first file.
	 * @param string $b The path to the second file.
	 * 
	 * @return int The difference between the two files.
	 */
	private function filemtime_diff( $a, $b ) {
		return filemtime( $a ) - filemtime( $b );
	}

	/**
	 * Run necessary upgrade routines.
	 *
	 * @since  2.2
	 * @access public
	 * @param  string $previous_version The version of the Logging Add-On we're upgrading from.
	 */
	public function upgrade( $previous_version ) {

		// If previous version is empty, run pre Add-On Framework upgrade.
		if ( empty( $previous_version ) ) {
			$this->upgrade_from_pre_addon_framework();
		}

		// If previous version is before the UI update, run settings conversion.
		$previous_is_pre_ui = ! empty( $previous_version ) && version_compare( $previous_version, '1.3', '<' );
		if ( $previous_is_pre_ui ) {
			$this->upgrade_from_pre_ui_update();
		}

	}

	/***
	 * Enables all loggers to "log all messages".
	 */
	public function enable_all_loggers(){

		$supported_plugins = $this->get_supported_plugins();

		$settings = array();
		foreach ( $supported_plugins as $plugin_slug => $plugin_name ) {
			$random = function_exists( 'random_bytes' ) ? random_bytes( 12 ) : wp_generate_password( 24, true, true );
			$settings[ $plugin_slug ] = array(
				'log_level' => '1',
				'enable'    => '1',
				'file_name' => sha1( $plugin_slug . $random ),
			);
		}
		$this->update_plugin_settings( $settings );
	}

	/**
	 * Update plugin settings to use new structure introduced in 1.3.
	 *
	 * @since  1.3
	 * @access public
	 */
	public function upgrade_from_pre_ui_update() {

		// Include KLogger library.
		$this->include_logger();

		// Get plugin settings.
		$settings = $this->get_plugin_settings();

		if ( ! is_array( $settings ) ) {
			return;
		}

		// Loop through settings.
		foreach ( $settings as $plugin_slug => &$plugin_setting ) {

			// If log level is not off, set enabled to 1.
			$plugin_setting['enable'] = $plugin_setting['log_level'] == KLogger::OFF ? '0' : '1';

		}

		// Save plugin settings.
		$this->update_plugin_settings( $settings );

	}

	/**
	 * Upgrade plugin from pre Add-On Framework version.
	 *
	 * @since  2.1.1.2
	 * @access public
	 */
	public function upgrade_from_pre_addon_framework() {

		if ( is_multisite() ) {

			// Get network sites. get_sites() is available with WP 4.6+.
			$sites = function_exists( 'get_sites' ) ? get_sites() : wp_get_sites();

			foreach ( $sites as $site ) {

				$blog_id = $site instanceof WP_Site ? $site->blog_id : $site['blog_id'];

				// Get old settings.
				$old_settings = get_blog_option( $blog_id, 'gf_logging_settings', array() );

				// If old settings don't exist, exit.
				if ( ! $old_settings ) {
					continue;
				}

				// Build new settings.
				$new_settings = array();

				foreach ( $old_settings as $plugin_slug => $log_level ) {
					$random = function_exists( 'random_bytes' ) ? random_bytes( 12 ) : wp_generate_password( 24, true, true );
					$new_settings[ $plugin_slug ] = array(
						'log_level' => $log_level,
						'file_name' => sha1( $plugin_slug . $random ),
					);
				}

				// Save new settings.
				update_blog_option( $blog_id, 'gravityformsaddon_' . $this->_slug . '_settings', $new_settings );

				// Delete old settings.
				delete_blog_option( $blog_id, 'gf_logging_settings' );

			}

		} else {

			// Get old settings.
			$old_settings = get_option( 'gf_logging_settings' );

			// If old settings don't exist, exit.
			if ( ! $old_settings ) {
				return;
			}

			// Build new settings.
			$new_settings = array();

			foreach ( $old_settings as $plugin_slug => $log_level ) {
				$random = function_exists( 'random_bytes' ) ? random_bytes( 12 ) : wp_generate_password( 24, true, true );
				$new_settings[ $plugin_slug ] = array(
					'log_level' => $log_level,
					'file_name' => sha1( $plugin_slug . $random ),
				);
			}

			// Save new settings.
			$this->update_plugin_settings( $new_settings );

			// Delete old settings.
			delete_option( 'gf_logging_settings' );

		}

	}


	/**
	 * Logging itself does not support logging.
	 *
	 * @since  2.2
	 * @param  array $plugins The plugins which support logging.
	 *
	 * @return array
	 */
	public function set_logging_supported( $plugins ) {

		return $plugins;

	}

	/**
	 * Get list of plugins that support Logging.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @return array $supported_plugins
	 */
	public function get_supported_plugins() {

		$supported_plugins = apply_filters( 'gform_logging_supported', array() );
		asort( $supported_plugins );

		return $supported_plugins;

	}


	public function delete_settings() {
		delete_option( 'gform_enable_logging' );
		delete_option( 'gravityformsaddon_' . $this->_slug . '_settings' );
	}

	/**
	 * Initializing translations using the gravityforms domain.
	 *
	 * @since  2.2
	 * @access public
	 */
	public function load_text_domain() {
		GFCommon::load_gf_text_domain();
	}

	/**
	 * Register Gravity Forms capabilities with Gravity Forms group in User Role Editor plugin.
	 *
	 * @since  2.4
	 *
	 * @param array  $groups Current capability groups.
	 * @param string $cap_id Capability identifier.
	 *
	 * @return array
	 */
	public function filter_ure_custom_capability_groups( $groups = array(), $cap_id = '' ) {

		// Get Add-On capabilities.
		$caps = $this->_capabilities;

		// If capability belongs to Add-On, register it to group.
		if ( in_array( $cap_id, $caps, true ) ) {
			$groups[] = 'gravityforms';
		}

		return $groups;

	}

}

/**
 * Returns an instance of the GFLogging class
 *
 * @see    GFLogging::get_instance()
 * @return object GFLogging
 */
function gf_logging() {
	return GFLogging::get_instance();
}

if ( get_option( 'gform_enable_logging' ) ) {
	gf_logging();
}
