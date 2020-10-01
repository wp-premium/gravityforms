<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GF_Update
 *
 * Handles the Updates subview on the System Status page.
 */
class GF_Update {

	/**
	 * Display updates page.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @uses GFSystemReport::get_system_report()
	 * @uses GFSystemReport::maybe_process_action()
	 * @uses GFSystemReport::prepare_item_value()
	 * @uses GFSystemStatus::page_footer()
	 * @uses GFSystemStatus::page_header()
	 */
	public static function updates() {

		// If user does not have access to this page, die.
		if ( ! GFCommon::current_user_can_any( 'gravityforms_view_updates' ) ) {
			wp_die( esc_html__( "You don't have permissions to view this page", 'gravityforms' ) );
		}

		// Get available updates.
		$updates = self::available_updates();

		// Display page header.
		GF_System_Status::page_header();

		wp_print_styles( array( 'thickbox' ) );

		?>
		<h3><span><?php esc_html_e( 'Updates', 'gravityforms' )?></span></h3>
		<table class="wp-list-table widefat plugins">
			<thead>
			<tr>
				<td id="cb" class="manage-column column-cb check-column">
					&nbsp;
				</td>
				<th scope="col" id="name" class="manage-column column-name column-primary"><?php esc_html_e( 'Plugin', 'gravityforms' ); ?></th>
				<th scope="col" id="description" class="manage-column column-description"><?php esc_html_e( 'Description', 'gravityforms' ); ?></th>
			</tr>
			</thead>

			<tbody id="the-list">
			<?php
			// All installed plugins
			$plugins = get_plugins();

			// Loop through updates.
			foreach ( $updates as $update ) {
				$update_available = version_compare( $update['installed_version'], $update['latest_version'], '<' );
				$update_class = $update_available ? ' update' : '';
				$settings_link = $update['slug'] == 'gravityforms' ? admin_url( 'admin.php?page=gf_settings' ) : admin_url( 'admin.php?page=gf_settings&subview=' . $update['slug'] );
				$plugin = $plugins[ $update['path'] ];

				?>
				<tr class="inactive<?php echo $update_class?>" data-slug="admin-bar-form-search" data-plugin="gw-admin-bar-form-manager.php">
					<th scope="row" class="check-column">
						&nbsp;
					</th>
					<td class="plugin-title column-primary"><strong><?php echo $update['name'] ?></strong>
						<div class="row-actions visible">
							<span class="deactivate"><a href="<?php echo $settings_link ?>"><?php esc_html_e( 'Settings', 'gravityforms' ) ?></a></span>
						</div>
					</td>
					<td class="column-description desc">
						<div class="plugin-description">
							<p><?php echo $plugin['Description']?></p>
						</div>
						<div class="active second plugin-version-author-uri">
							Version <?php echo $update['installed_version'] ?> |
							<a href="<?php echo $plugin['PluginURI'] ?>"><?php esc_html_e( 'Visit plugin page', 'gravityforms' ) ?></a>
						</div>
					</td>
				</tr>

				<?php if ( $update_available ) { ?>
				<tr class="plugin-update-tr inactive">
					<td colspan="3" class="plugin-update colspanchange">
						<div class="update-message notice inline notice-warning notice-alt">
							<p>
								<?php

									printf( esc_html__( 'There is a new version of %s available. ', 'gravityforms' ), $update['name'] );

									if ( $update['is_valid_key'] ) {
										// Changelog URL is different in a multisite network.
										$changelog_url = wp_nonce_url( self_admin_url( 'admin-ajax.php?action=gf_get_changelog&plugin=' . urlencode( $update['slug'] ) . '&TB_iframe=true&width=640&height=808' ) );

										if ( ! current_user_can( 'update_plugins' ) ) {
											printf( esc_html__( '%1$sView version %2$s details %3$s. ', 'gravityforms' ),
												'<a href="' . $changelog_url . '" class="thickbox open-plugin-details-modal">',
												$update['latest_version'],
												'</a>'
											);
										} else {
											printf( esc_html__( '%1$sView version %2$s details %3$s or %4$supdate now%5$s.', 'gravityforms' ),
												'<a href="' . $changelog_url . '" class="thickbox open-plugin-details-modal">',
												$update['latest_version'],
												'</a>',
												'<a href="' . $update['upgrade_url'] . '" class="update-link">',
												'</a>'
											);
										}

									} else {

										printf(
											esc_html__( '%sRegister%s your copy of Gravity Forms to receive access to automatic updates and support. Need a license key? %sPurchase one now%s.', 'gravityforms' ),
											'<a href="admin.php?page=gf_settings">',
											'</a>',
											'<a href="https://www.gravityforms.com">',
											'</a>'
										);

									}
								?>
							</p>
						</div>
					</td>
				</tr>
				<?php
				}
			}
			?>
			</tbody>
		</table>

		<?php

		// Display page footer.
		GF_System_Status::page_footer();

	}

	/**
	 * Get available Gravity Forms updates.
	 *
	 * @since  2.2
	 * @access public
	 *
	 * @uses GFCommon::get_version_info()
	 *
	 * @return array
	 */
	public static function available_updates() {

		// Initialize updates array.
		$updates = array();

		// Get Gravity Forms version info.
		$version_info = GFCommon::get_version_info( false );

		// Define Gravity Forms plugin path.
		$plugin_path = plugin_basename( GFCommon::get_base_path() . '/gravityforms.php' );

		// Get upgrade URL.
		$upgrade_url = wp_nonce_url( 'update.php?action=upgrade-plugin&amp;plugin=' . urlencode( $plugin_path ), 'upgrade-plugin_' . $plugin_path );

		// Prepare version message and icon.
		if ( version_compare( GFCommon::$version, $version_info['version'], '>=' ) ) {

			$version_icon    = 'dashicons-yes';
			$version_message = esc_html__( 'Your version of Gravity Forms is up to date.', 'gravityforms' );

		} else {

			if ( rgar( $version_info, 'is_valid_key' ) ) {

				$version_icon    = 'dashicons-no';
				$version_message = sprintf(
					'%s<p>%s</p>',
					esc_html__( 'There is a new version of Gravity Forms available.', 'gravityforms' ),
					esc_html__( 'You can update to the latest version automatically or download the update and install it manually.', 'gravityforms' )
				);
			} else {


				$version_icon    = 'dashicons-no';
				$version_message = sprintf(
					'%s<p>%s</p>',
					esc_html__( 'There is a new version of Gravity Forms available.', 'gravityforms' ),
					sprintf(
						esc_html__( '%sRegister%s your copy of Gravity Forms to receive access to automatic updates and support. Need a license key? %sPurchase one now%s.', 'gravityforms' ),
						'<a href="admin.php?page=gf_settings">',
						'</a>',
						'<a href="https://www.gravityforms.com">',
						'</a>'
					)
				);
			}
		}

		// Add Gravity Forms core to updates array.
		$updates[] = array(
			'is_valid_key'      => rgar( $version_info, 'is_valid_key' ),
			'name'              => esc_html__( 'Gravity Forms', 'gravityforms' ),
			'path'              => $plugin_path,
			'slug'              => 'gravityforms',
			'latest_version'    => $version_info['version'],
			'installed_version' => GFCommon::$version,
			'upgrade_url'       => $upgrade_url,
			'download_url'      => $version_info['url'],
			'version_icon'      => $version_icon,
			'version_message'   => $version_message,
		);

		/**
		 * Modify plugins displayed on the Updates page.
		 *
		 * @since 2.2
		 *
		 * @param array $updates An array of plugins displayed on the Updates page.
		 */
		$updates = apply_filters( 'gform_updates_list', $updates );

		return $updates;

	}

}
