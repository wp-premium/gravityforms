<?php

/**
 * Adds integration with osDXP.
 *
 * @since 2.4.15
 */
class GF_OSDXP {

	/**
	 * @var GF_OSDXP
	 */
	private static $instance;

	/**
	 * @var array The array of page slugs.
	 */
	private $slugs = array();

	/**
	 * Class constructor.
	 *
	 * @since 2.4.15
	 */
	private function __construct() {
		$this->slugs = [
			'gf_settings'      => esc_html__( 'Settings', 'gravityforms' ),
			'gf_export'        => esc_html__( 'Import/Export', 'gravityforms' ),
			'gf_addons'        => esc_html__( 'Add-Ons', 'gravityforms' ),
			'gf_help'          => esc_html__( 'Help', 'gravityforms' ),
			'gf_system_status' => esc_html__( 'System Status', 'gravityforms' ),
		];
		add_filter( 'osdxp_license_key_gravityforms', array( $this, 'license_key' ) );
		add_filter( 'osdxp_dashboard_license_submit_response', array( $this, 'process_license_key_submit' ), 10, 3 );
		add_filter( 'osdxp_dashboard_license_deletion_response', array(
			$this,
			'process_license_key_deletion',
		), 10, 2 );
		add_filter( 'osdxp_add_module_settings_page', array( $this, 'settings_page' ) );
		add_filter( 'parent_file', array( $this, 'settings_page_highlight' ) );
		add_action( 'in_admin_header', array( $this, 'nav_tabs' ) );
	}

	/**
	 * Get a class instance.
	 *
	 * @since 2.4.15
	 *
	 * @return GF_OSDXP instance of GF_OSDXP.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Handles the license key display.
	 *
	 * @since 2.4.15
	 *
	 * @param $license_key
	 *
	 * @return string|null Return a string or null to display the text box.
	 */
	public static function license_key( $license_key ) {
		$version_info = GFCommon::get_version_info( false );
		$key          = GFCommon::get_key();

		if ( ! rgempty( 'is_error', $version_info ) ) {
			return esc_attr__( 'There was an error while validating your license key. Gravity Forms will continue to work, but automatic upgrades will not be available. Please contact support to resolve this issue.', 'gravityforms' );
		} elseif ( rgar( $version_info, 'is_valid_key' ) ) {
			return esc_html__( 'Valid', 'gravityforms' );

		} elseif ( ! empty( $key ) ) {
			return esc_html__( 'Invalid or Expired.', 'gravityforms' );
		}

	}

	/**
	 * Process license key submit.
	 *
	 * Callback for the 'osdxp_dashboard_license_submit_response' filter.
	 *
	 * @since 2.4.15
	 *
	 * @param array  $response    Response.
	 * @param string $plugin_slug Plugin slug.
	 * @param string $license_key License key.
	 *
	 * @return array Response array.
	 */
	public static function process_license_key_submit( $response, $plugin_slug, $license_key ) {

		if ( 'gravityforms' !== $plugin_slug ) {
			return $response;
		}

		if ( ! is_array( $response ) ) {
			$response = array();
		}

		// Sanitize license key.
		$license_key = sanitize_text_field( $license_key );

		GFFormsModel::save_key( $license_key );

		// Updating message because key could have been changed.
		GFCommon::cache_remote_message();

		// Re-caching version info.
		$version_info = GFCommon::get_version_info( false );

		$error_message = '';

		$key = GFCommon::get_key();

		if ( ! rgempty( 'is_error', $version_info ) ) {
			$error_message = esc_attr__( 'There was an error while validating your license key. Gravity Forms will continue to work, but automatic upgrades will not be available. Please contact support to resolve this issue.', 'gravityforms' );
		} elseif ( rgar( $version_info, 'is_valid_key' ) ) {

			update_option( 'gform_pending_installation', 0 );
			if ( ! get_option( 'rg_gforms_currency' ) ) {
				update_option( 'rg_gforms_currency', 'USD' );
			}

			$success_message = esc_html__( 'Valid Key : Your license key has been successfully validated.', 'gravityforms' );

			if ( empty( $response['success_messages'] ) || ! is_array( $response['success_messages'] ) ) {
				$response['success_messages'] = array();
			}

			$response['success_messages'][] = $success_message;
		} elseif ( rgar( $version_info, 'reason' ) == 'requires_enterprise_license' ) {
			$error_message = esc_html__( 'Invalid Key - an Enterprise license is required.', 'gravityforms' );
		} elseif ( ! empty( $key ) ) {
			$error_message = esc_html__( 'Invalid or Expired Key - Please make sure you have entered the correct value and that your key is not expired.', 'gravityforms' );
		}

		if ( ! empty( $error_message ) ) {

			if ( empty( $response['error_messages'] ) || ! is_array( $response['error_messages'] ) ) {
				$response['error_messages'] = array();
			}

			$response['error_messages'][] = $error_message;
		}

		return $response;
	}

	/**
	 * Process the license key deletion.
	 *
	 * @since 2.4.15
	 *
	 * @param $response
	 * @param $plugin_slug
	 *
	 * @return array
	 */
	public static function process_license_key_deletion( $response, $plugin_slug ) {

		if ( $plugin_slug != 'gravityforms' ) {
			return $response;
		}

		if ( ! is_array( $response ) ) {
			$response = array();
		}

		GFFormsModel::save_key( '' );

		$response['success'] = 1;

		if ( empty( $response['success_messages'] ) || ! is_array( $response['success_messages'] ) ) {
			$response['success_messages'] = array();
		}

		$response['success_messages'][] = esc_html__( 'License successfully removed.', 'gravityforms' );

		return $response;
	}

	/**
	 * Registers plugin pages with osDXP.
	 *
	 * @since 2.4.15
	 *
	 * @param $pages
	 *
	 * @return array
	 */
	public function settings_page( $pages ) {

		/**
		 * Setup.
		 */
		$has_full_access = current_user_can( 'gform_full_access' );
		$min_cap         = GFCommon::current_user_can_which( GFCommon::all_caps() );
		if ( empty( $min_cap ) ) {
			$min_cap = 'gform_full_access';
		}

		$parent_menu = array(
			'name' => 'gf_edit_forms',
			'callback' => array('GFForms', 'forms')
        );

		/**
		 * Remove Classic WP-Admin menu items.
		 */
		global $admin_page_hooks;
		static $removed = false;
		if ( ! $removed && is_array( $admin_page_hooks ) ) {
			$removed = true;
			remove_menu_page( $parent_menu['name'] );
			remove_submenu_page( $parent_menu['name'], 'gf_settings' );
			remove_submenu_page( $parent_menu['name'], 'gf_export' );
			remove_submenu_page( $parent_menu['name'], 'gf_addons' );
			remove_submenu_page( $parent_menu['name'], 'gf_system_status' );
			remove_submenu_page( $parent_menu['name'], 'gf_help' );
		}

		/**
		 * Add osDXP specific pages.
		 */
		// Top-level action page.
		$pages[] = array(
			'function'   => $parent_menu['callback'],
			'type'       => 'menu',
			'menu_slug'  => $parent_menu['name'],
			'page_title' => esc_html__( 'Forms', 'gravityforms' ),
			'menu_title' => esc_html__( 'Forms', 'gravityforms' ),
			'capability' => $has_full_access ? 'gform_full_access' : $min_cap,
			'icon_url'   => GFForms::get_admin_icon_b64( '#FFF' ),
		);
		// Settings page.
		$pages[] = array(
			'function'   => array( 'GFForms', 'settings_page' ),
			'menu_slug'  => 'gf_settings',
			'page_title' => esc_html__( 'Form Settings', 'gravityforms' ),
			'menu_title' => esc_html__( 'Form Settings', 'gravityforms' ),
			'capability' => $has_full_access ? 'gform_full_access' : 'gravityforms_view_settings',
		);
		// Export page.
		$pages[] = array(
			'function'   => array( 'GFForms', 'export_page' ),
			'menu_slug'  => 'gf_export',
			'type'       => 'endpoint',
			'page_title' => esc_html__( 'Import/Export', 'gravityforms' ),
			'menu_title' => esc_html__( 'Import/Export', 'gravityforms' ),
			'capability' => $has_full_access ? 'gform_full_access' : ( current_user_can( 'gravityforms_export_entries' ) ? 'gravityforms_export_entries' : 'gravityforms_edit_forms' ),
		);
		if ( current_user_can( 'install_plugins' ) ) {
			// Add-ons page.
			$pages[] = array(
				'function'   => array( 'GFForms', 'addons_page' ),
				'menu_slug'  => 'gf_addons',
				'type'       => 'endpoint',
				'page_title' => esc_html__( 'Add-Ons', 'gravityforms' ),
				'menu_title' => esc_html__( 'Add-Ons', 'gravityforms' ),
				'capability' => $has_full_access ? 'gform_full_access' : 'gravityforms_view_addons',
			);
		}
		// System status page.
		$pages[] = array(
			'function'   => array( 'GFForms', 'system_status' ),
			'menu_slug'  => 'gf_system_status',
			'type'       => 'endpoint',
			'page_title' => esc_html__( 'System Status', 'gravityforms' ),
			'menu_title' => esc_html__( 'System Status', 'gravityforms' ),
			'capability' => $has_full_access ? 'gform_full_access' : 'gravityforms_system_status',
		);
		// Help page.
		$pages[] = array(
			'function'   => array( 'GFForms', 'help_page' ),
			'menu_slug'  => 'gf_help',
			'type'       => 'endpoint',
			'page_title' => esc_html__( 'Help', 'gravityforms' ),
			'menu_title' => esc_html__( 'Help', 'gravityforms' ),
			'capability' => $has_full_access ? 'gform_full_access' : $min_cap,
		);

		return $pages;
	}

	/**
	 * Helper function to check if a page slug is a setting/misc page.
     *
     * @since 2.4.15
	 *
	 * @param $plugin_page
	 *
	 * @return bool
	 */
	private function is_setting_page( $plugin_page ) {
		return array_key_exists( $plugin_page, $this->slugs );
	}

	/**
	 * Highlights appropriate menu item for misc pages.
     *
     * @since 2.4.15
	 *
	 * @param $file
	 *
	 * @return $file
	 */
	public function settings_page_highlight( $file ) {
		global $plugin_page, $submenu_file;

		if ( $this->is_setting_page( $plugin_page ) ) {
			$file         = 'dxp-module-settings';
			$submenu_file = 'gf_settings';
		}

		return $file;
	}

	/**
	 * Outputs Nav Tabs for settings&misc pages.
     *
     * @since 2.4.15
	 */
	public function nav_tabs() {
		global $plugin_page;

		if ( $this->is_setting_page( $plugin_page ) ) {
			?>
            <ul class="osdxp-nav-tabs">
				<?php
				foreach ( $this->slugs as $path => $name ) {
					?>
                    <li <?php echo ( $path === $plugin_page ) ? 'class="active"' : '' ?>>
                        <a href="<?php echo admin_url( 'admin.php?page=' . $path ); ?>">
							<?php echo $name; ?>
                        </a>
                    </li>
					<?php
				} ?>
            </ul>
			<?php
		}
	}
}

GF_OSDXP::get_instance();
