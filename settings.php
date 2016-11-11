<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GFSettings
 *
 * Generates the Gravity Forms settings page
 */
class GFSettings {

	/**
	 * Settings pages associated with add-ons
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var array $addon_pages
	 */
	public static $addon_pages = array();

	/**
	 * Adds a settings page to the Gravity Forms settings.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFSettings::$addon_pages
	 *
	 * @param string       $name      The settings page slug.
	 * @param string|array $handler   The callback function to run for this settings page.
	 * @param string       $icon_path The path to the icon for the settings tab.
	 */
	public static function add_settings_page( $name, $handler, $icon_path ) {

		$title = '';

		// if name is an array, assume that an array of args is passed
		if ( is_array( $name ) ) {

			extract(
				wp_parse_args(
					$name, array(
						'name'      => '',
						'title'     => '',
						'tab_label' => '',
						'handler'   => false,
						'icon_path' => '',
					)
				)
			);

		}

		if ( ! isset( $tab_label ) || ! $tab_label ) {
			$tab_label = $name;
		}

		/**
		 * Adds additional actions after settings pages are registered.
		 *
		 * @since Unknown
		 *
		 * @param string|array $handler The callback function being run.
		 */
		add_action( 'gform_settings_' . str_replace( ' ', '_', $name ), $handler );
		self::$addon_pages[ $name ] = array( 'name' => $name, 'title' => $title, 'tab_label' => $tab_label, 'icon' => $icon_path );
	}

	/**
	 * Determines the content displayed on the Gravity Forms settings page.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFSettings::get_subview()
	 * @uses GFSettings::gravityforms_settings_page()
	 * @uses GFSettings::settings_uninstall_page()
	 * @uses GFSettings::page_header()
	 * @uses GFSettings::page_footer()
	 *
	 * @return void
	 */
	public static function settings_page() {

		$subview = self::get_subview();

		switch ( $subview ) {
			case 'settings':
				self::gravityforms_settings_page();
				break;
			case 'uninstall':
				self::settings_uninstall_page();
				break;
			default:
				self::page_header();

				/**
				 * Fires in the settings page depending on which page of the settings page you are in (the Subview).
				 *
				 * @since Unknown
				 *
				 * @param mixed $subview The sub-section of the main Form's settings
				 */
				do_action( 'gform_settings_' . str_replace( ' ', '_', $subview ) );
				self::page_footer();
		}
	}

	/**
	 * Displays the Gravity Forms uninstall page.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFSettings::settings_page()
	 * @uses    GFSettings::page_header()
	 * @uses    GFCommon::current_user_can_any()
	 * @uses    GFFormsModel::drop_tables()
	 * @uses    GFCommon::delete_directory()
	 * @uses    GFFormsModel::get_upload_root()
	 * @uses    GFCommon::current_user_can_any()
	 * @uses    GFSettings::page_footer()
	 */
	public static function settings_uninstall_page() {
		self::page_header( __( 'Uninstall Gravity Forms', 'gravityforms' ), '' );
		if ( isset( $_POST['uninstall'] ) ) {

			check_admin_referer( 'gform_uninstall', 'gform_uninstall_nonce' );

			if ( ! GFCommon::current_user_can_any( 'gravityforms_uninstall' ) || ( function_exists( 'is_multisite' ) && is_multisite() && ! is_super_admin() ) ) {
				die( esc_html__( "You don't have adequate permission to uninstall Gravity Forms.", 'gravityforms' ) );
			}

			// Removing cron task
			wp_clear_scheduled_hook( 'gravityforms_cron' );

			// Dropping all tables
			RGFormsModel::drop_tables();

			// Removing options
			delete_option( 'rg_form_version' );
			delete_option( 'rg_gforms_key' );
			delete_option( 'rg_gforms_disable_css' );
			delete_option( 'rg_gforms_enable_html5' );
			delete_option( 'rg_gforms_captcha_public_key' );
			delete_option( 'rg_gforms_captcha_private_key' );
			delete_option( 'rg_gforms_message' );
			delete_option( 'gform_enable_noconflict' );
			delete_option( 'gform_enable_background_updates' );
			delete_option( 'gform_sticky_admin_messages' );
			delete_option( 'gf_dismissed_upgrades' );
			delete_option( 'rg_gforms_currency' );
			delete_option( 'gform_api_count' );
			delete_option( 'gform_email_count' );
			delete_option( 'gform_enable_toolbar_menu' );

			// Removing gravity forms upload folder
			GFCommon::delete_directory( RGFormsModel::get_upload_root() );

			// Deactivating plugin
			$plugin = 'gravityforms/gravityforms.php';
			deactivate_plugins( $plugin );
			update_option( 'recently_activated', array( $plugin => time() ) + (array) get_option( 'recently_activated' ) );

			?>
			<div class="updated fade" style="padding:20px;"><?php echo sprintf( esc_html__( 'Gravity Forms has been successfully uninstalled. It can be re-activated from the %splugins page%s.', 'gravityforms' ), "<a href='plugins.php'>", '</a>' ) ?></div>
			<?php
			return;
		}
		?>

		<form action="" method="post">
			<?php if ( GFCommon::current_user_can_any( 'gravityforms_uninstall' ) && ( ! function_exists( 'is_multisite' ) || ! is_multisite() || is_super_admin() ) ) {

				wp_nonce_field( 'gform_uninstall', 'gform_uninstall_nonce' );
				?>
				<h3><span><i class="fa fa-times"></i> <?php esc_html_e( 'Uninstall Gravity Forms', 'gravityforms' ); ?></span>
				</h3>
				<div class="delete-alert alert_red">

					<h3>
						<i class="fa fa-exclamation-triangle gf_invalid"></i> <?php esc_html_e( 'Warning', 'gravityforms' ); ?>
					</h3>

					<div class="gf_delete_notice"><strong><?php esc_html_e( 'This operation deletes ALL Gravity Forms data.', 'gravityforms' ); ?></strong> <?php esc_html_e( 'If you continue, you will not be able to retrieve or restore your forms or entries.', 'gravityforms' ); ?>
				</div>

				<?php
				$uninstall_button = '<input type="submit" name="uninstall" value="' . esc_attr__( 'Uninstall Gravity Forms', 'gravityforms' ) . '" class="button" onclick="return confirm(\'' . esc_js( __( "Warning! ALL Gravity Forms data, including form entries will be deleted. This cannot be undone. 'OK' to delete, 'Cancel' to stop", 'gravityforms' ) ) . '\');" onkeypress="return confirm(\'' . esc_js( __( "Warning! ALL Gravity Forms data, including form entries will be deleted. This cannot be undone. 'OK' to delete, 'Cancel' to stop", 'gravityforms' ) ) . '\');"/>';

				/**
				 * Allows for the modification of the Gravity Forms uninstall button.
				 *
				 * @since Unknown
				 *
				 * @param string $uninstall_button The HTML of the uninstall button.
				 */
				echo apply_filters( 'gform_uninstall_button', $uninstall_button );
				?>

				</div>
			<?php } ?>
		</form>

		<?php
		self::page_footer();
	}

	/**
	 * Displays the main Gravity Forms settings page.
	 *
	 * @since  Unknown
	 * @access public
	 * @global $wpdb
	 *
	 * @used-by GFSettings::settings_page()
	 * @uses    GFCommon::ensure_wp_version()
	 * @uses    GFForms::setup()
	 * @uses    GFCommon::current_user_can_any()
	 * @uses    GFFormsModel::save_key()
	 * @uses    GFSettings::get_posted_akismet_setting()
	 * @uses    GFCommon::log_debug()
	 * @uses    GF_Field_CAPTCHA
	 * @uses    GF_Field_CAPTCHA::verify_recaptcha_response()
	 * @uses    RGCurrency::get_currencies()
	 * @uses    GFCommon::cache_remote_message()
	 * @uses    GFCommon::get_version_info()
	 * @uses    GFCommon::get_key()
	 * @uses    GFCommon::get_base_url()
	 * @uses    wpdb::db_version()
	 * @uses    GF_MIN_WP_VERSION_SUPPORT_TERMS
	 * @uses    GF_MIN_WP_VERSION
	 * @uses    GFCommon::$version
	 * @uses    GFSettings::page_footer()
	 *
	 * @return void
	 */
	public static function gravityforms_settings_page() {
		global $wpdb;

		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		if ( isset( $_GET['setup'] ) ) {
			// Forcing setup
			RGForms::setup( true );
		}

		require_once( 'currency.php' );

		if ( isset( $_POST['submit'] ) ) {
			check_admin_referer( 'gforms_update_settings', 'gforms_update_settings' );

			if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_settings' ) ) {
				die( esc_html__( "You don't have adequate permission to edit settings.", 'gravityforms' ) );
			}

			RGFormsModel::save_key( sanitize_text_field( $_POST['gforms_key'] ) );
			update_option( 'rg_gforms_disable_css', (bool) rgpost( 'gforms_disable_css' ) );
			update_option( 'rg_gforms_enable_html5', (bool) rgpost( 'gforms_enable_html5' ) );
			update_option( 'gform_enable_noconflict', (bool) rgpost( 'gform_enable_noconflict' ) );
			update_option( 'gform_enable_background_updates', (bool) rgpost( 'gform_enable_background_updates' ) );
			update_option( 'gform_enable_toolbar_menu', (bool) rgpost( 'gform_enable_toolbar_menu' ) );
			update_option( 'rg_gforms_enable_akismet', self::get_posted_akismet_setting() ); // do not cast to bool, option is enabled by default; need a "1" or a "0"
			update_option( 'rg_gforms_captcha_public_key', sanitize_text_field( rgpost( 'gforms_captcha_public_key' ) ) );
			update_option( 'rg_gforms_captcha_private_key', sanitize_text_field( rgpost( 'gforms_captcha_private_key' ) ) );

			if( rgpost( 'gform_recaptcha_reset' ) ) {

				$site = get_option( 'rg_gforms_captcha_public_key' );
				$secret = get_option( 'rg_gforms_captcha_private_key' );
				$response = rgpost( 'g-recaptcha-response' );

				GFCommon::log_debug( 'site:' . $site );
				GFCommon::log_debug( 'secret:' . $secret );
				GFCommon::log_debug( 'response:' . $response );

				if( $site && $secret && $response ) {
					$recaptcha = new GF_Field_CAPTCHA();
					$recaptcha_response = $recaptcha->verify_recaptcha_response( $response, $secret );
					GFCommon::log_debug( 'recaptcha response:' . $recaptcha_response );
					update_option( 'gform_recaptcha_keys_status',  $recaptcha_response );
				} else {
					delete_option( 'gform_recaptcha_keys_status' );
				}

			}

			if ( ! rgempty( 'gforms_currency' ) && in_array( rgpost( 'gforms_currency' ), array_keys( RGCurrency::get_currencies() ) ) ) {
				update_option( 'rg_gforms_currency', rgpost( 'gforms_currency' ) );
			}


			// Updating message because key could have been changed
			GFCommon::cache_remote_message();

			// Re-caching version info
			$version_info = GFCommon::get_version_info( false );
			?>
			<div class="updated fade" style="padding:6px;">
				<?php esc_html_e( 'Settings Updated', 'gravityforms' ); ?>.
			</div>
		<?php
		}

		if ( ! isset( $version_info ) ) {
			$version_info = GFCommon::get_version_info();
		}
		self::page_header( __( 'General Settings', 'gravityforms' ), '' );
		?>
		<form id="gforms_settings" method="post">
			<?php wp_nonce_field( 'gforms_update_settings', 'gforms_update_settings' ) ?>
			<h3><span><i class="fa fa-cogs"></i> <?php esc_html_e( 'General Settings', 'gravityforms' ); ?></span></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="gforms_key"><?php esc_html_e( 'Support License Key', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_license_key' ) ?>
					</th>
					<td>
						<?php
						$key = GFCommon::get_key();
						$key_field = '<input type="password" name="gforms_key" id="gforms_key" style="width:350px;" value="' . $key . '" />';
						if ( ! rgempty( 'is_error', $version_info ) ) {
							$key_field .= "&nbsp;<img src='" . GFCommon::get_base_url() . "/images/exclamation.png' class='gf_keystatus_error gf_tooltip' alt='There was an error validating your key' title='<h6>" . esc_attr__( 'Validation Error', 'gravityforms' ) . '</h6>' . esc_attr__( 'There was an error while validating your license key. Gravity Forms will continue to work, but automatic upgrades will not be available. Please contact support to resolve this issue.', 'gravityforms' ) . "'/>";
						} else if ( rgar( $version_info, 'is_valid_key' ) ) {
							$key_field .= "&nbsp;<i class='fa fa-check gf_keystatus_valid'></i> <span class='gf_keystatus_valid_text'>" . esc_html__( 'Valid Key : Your license key has been successfully validated.', 'gravityforms' ) . '</span>';
						} else if ( ! empty( $key ) ) {
							$key_field .= "&nbsp;<i class='fa fa-times gf_keystatus_invalid'></i> <span class='gf_keystatus_invalid_text'>" . esc_html__( 'Invalid or Expired Key : Please make sure you have entered the correct value and that your key is not expired.', 'gravityforms' ) . '</span>';
						}

						echo apply_filters( 'gform_settings_key_field', $key_field );
						?>
						<br />
						<span class="gf_settings_description"><?php esc_html_e( 'The license key is used for access to automatic upgrades and support.', 'gravityforms' ); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="gforms_disable_css"><?php esc_html_e( 'Output CSS', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_output_css' ) ?>
					</th>
					<td>
						<input type="radio" name="gforms_disable_css" value="0" id="gforms_css_output_enabled" <?php echo get_option( 'rg_gforms_disable_css' ) == 1 ? '' : "checked='checked'" ?> /> <?php esc_html_e( 'Yes', 'gravityforms' ); ?>&nbsp;&nbsp;
						<input type="radio" name="gforms_disable_css" value="1" id="gforms_css_output_disabled" <?php echo get_option( 'rg_gforms_disable_css' ) == 1 ? "checked='checked'" : '' ?> /> <?php esc_html_e( 'No', 'gravityforms' ); ?>
						<br />
						<span class="gf_settings_description"><?php esc_html_e( 'Set this to No if you would like to disable the plugin from outputting the form CSS.', 'gravityforms' ); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="gforms_enable_html5"><?php esc_html_e( 'Output HTML5', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_html5' ) ?>
					</th>
					<td>
						<input type="radio" name="gforms_enable_html5" value="1" <?php echo get_option( 'rg_gforms_enable_html5' ) == 1 ? "checked='checked'" : '' ?> id="gforms_enable_html5" /> <?php esc_html_e( 'Yes', 'gravityforms' ); ?>&nbsp;&nbsp;
						<input type="radio" name="gforms_enable_html5" value="0" <?php echo get_option( 'rg_gforms_enable_html5' ) == 1 ? '' : "checked='checked'" ?> /> <?php esc_html_e( 'No', 'gravityforms' ); ?>
						<br />
						<span class="gf_settings_description"><?php esc_html_e( 'Set this to No if you would like to disable the plugin from outputting HTML5 form fields.', 'gravityforms' ); ?></span>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">
						<label for="gform_enable_noconflict"><?php esc_html_e( 'No-Conflict Mode', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_noconflict' ) ?>
					</th>
					<td>
						<input type="radio" name="gform_enable_noconflict" value="1" <?php echo get_option( 'gform_enable_noconflict' ) == 1 ? "checked='checked'" : '' ?> id="gform_enable_noconflict" /> <?php esc_html_e( 'On', 'gravityforms' ); ?>&nbsp;&nbsp;
						<input type="radio" name="gform_enable_noconflict" value="0" <?php echo get_option( 'gform_enable_noconflict' ) == 1 ? '' : "checked='checked'" ?> id="gform_disable_noconflict" /> <?php esc_html_e( 'Off', 'gravityforms' ); ?>
						<br />
						<span class="gf_settings_description"><?php esc_html_e( 'Set this to ON to prevent extraneous scripts and styles from being printed on Gravity Forms admin pages, reducing conflicts with other plugins and themes.', 'gravityforms' ); ?></span>
					</td>
				</tr>

				<?php if ( GFCommon::has_akismet() ) { ?>
					<tr valign="top">
						<th scope="row">
							<label for="gforms_enable_akismet"><?php esc_html_e( 'Akismet Integration', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_akismet' ) ?>
						</th>
						<td>
							<?php
							$akismet_setting = get_option( 'rg_gforms_enable_akismet' );
							$is_akismet_enabled = $akismet_setting === false || ! empty( $akismet_setting ); //Akismet is enabled by default.
							?>
							<input type="radio" name="gforms_enable_akismet" value="1" <?php checked( $is_akismet_enabled, true ) ?> id="gforms_enable_akismet" /> <?php esc_html_e( 'Yes', 'gravityforms' ); ?>&nbsp;&nbsp;
							<input type="radio" name="gforms_enable_akismet" value="0" <?php checked( $is_akismet_enabled, false ) ?> /> <?php esc_html_e( 'No', 'gravityforms' ); ?>
							<br />
							<span class="gf_settings_description"><?php esc_html_e( 'Protect your form entries from spam using Akismet.', 'gravityforms' ); ?></span>
						</td>
					</tr>
				<?php } ?>

				<tr valign="top">
					<th scope="row">
						<label for="gforms_currency"><?php esc_html_e( 'Currency', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_currency' ) ?>
					</th>
					<td>
						<?php
						$disabled = apply_filters( 'gform_currency_disabled', false ) ? "disabled='disabled'" : ''
						?>

						<select id="gforms_currency" name="gforms_currency" <?php echo $disabled ?>>
							<option><?php esc_html_e( 'Select a Currency', 'gravityforms' ) ?></option>
							<?php
							$current_currency = GFCommon::get_currency();

							foreach ( RGCurrency::get_currencies() as $code => $currency ) {
								?>
								<option value="<?php echo esc_attr( $code ) ?>" <?php echo $current_currency == $code ? "selected='selected'" : '' ?>><?php echo esc_html( $currency['name'] ) ?></option>
							<?php
							}
							?>
						</select>
						<?php do_action( 'gform_currency_setting_message', '' ); ?>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">
						<label for="gform_enable_background_updates"><?php esc_html_e( 'Background updates', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_background_updates' ) ?>
					</th>
					<td>
						<input type="radio" name="gform_enable_background_updates" value="1" <?php echo get_option( 'gform_enable_background_updates' ) == 1 ? "checked='checked'" : '' ?> id="gform_enable_background_updates" /> <?php esc_html_e( 'On', 'gravityforms' ); ?>&nbsp;&nbsp;
						<input type="radio" name="gform_enable_background_updates" value="0" <?php echo get_option( 'gform_enable_background_updates' ) == 1 ? '' : "checked='checked'" ?> id="gform_disable_background_updates" /> <?php esc_html_e( 'Off', 'gravityforms' ); ?>
						<br />
						<span class="gf_settings_description"><?php esc_html_e( 'Set this to ON to allow Gravity Forms to download and install bug fixes and security updates automatically in the background. Requires a valid license key.', 'gravityforms' ); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="gform_toolbar_menu"><?php esc_html_e( 'Toolbar Menu', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_toolbar_menu' ) ?>
					</th>
					<td>
						<input type="radio" name="gform_enable_toolbar_menu" value="1" <?php echo get_option( 'gform_enable_toolbar_menu' ) == 1 ? "checked='checked'" : '' ?> id="gform_enable_toolbar_menu" /> <?php esc_html_e( 'On', 'gravityforms' ); ?>&nbsp;&nbsp;
						<input type="radio" name="gform_enable_toolbar_menu" value="0" <?php echo get_option( 'gform_enable_toolbar_menu' ) == 1 ? '' : "checked='checked'" ?> id="gform_disable_toolbar_menu" /> <?php esc_html_e( 'Off', 'gravityforms' ); ?>
						<br />
						<span class="gf_settings_description"><?php esc_html_e( 'Set this to ON to display the Forms menu in the WordPress top toolbar. The Forms menu will display the latest ten forms recently opened in the form editor.', 'gravityforms' ); ?></span>
					</td>
				</tr>
			</table>

			<div class="hr-divider"></div>

			<h3><span><i class="fa fa-cogs"></i> <?php esc_html_e( 'reCAPTCHA Settings', 'gravityforms' ); ?></span></h3>

			<p style="text-align: left;">
				<?php esc_html_e( 'Gravity Forms integrates with reCAPTCHA, a free CAPTCHA service that helps to digitize books while protecting your forms from spam bots. ', 'gravityforms' ); ?>
				<?php printf( esc_html__( '%sPlease note%s, these settings are required only if you decide to use the reCAPTCHA field.', 'gravityforms' ), '<strong>', '</strong>' ); ?>
				<a href="http://www.google.com/recaptcha/" target="_blank"><?php esc_html_e( 'Read more about reCAPTCHA', 'gravityforms' ); ?></a>.
			</p>

			<table class="form-table">

				<?php $key_status = get_option( 'gform_recaptcha_keys_status', null ); ?>

				<tr valign="top">
					<th scope="row">
						<label for="gforms_captcha_public_key"><?php esc_html_e( 'Site Key', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_recaptcha_public' ) ?>
					</th>
					<td>
						<input type="text" name="gforms_captcha_public_key" style="width:350px;" value="<?php echo esc_attr( get_option( 'rg_gforms_captcha_public_key' ) ); ?>" onchange="loadRecaptcha();" />
						<?php if( $key_status !== null ): ?>
							<span class="gforms_captcha_site_key_status">
								<?php if( $key_status ): ?>
									<i class="fa fa-check gf_valid"></i>
								<?php else: ?>
									<i class="fa fa-times gf_invalid"></i>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="gforms_captcha_private_key"><?php esc_html_e( 'Secret Key', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_recaptcha_private' ) ?>
					</th>
					<td>
						<input type="text" name="gforms_captcha_private_key" style="width:350px;" value="<?php echo esc_attr( get_option( 'rg_gforms_captcha_private_key' ) ) ?>" onchange="loadRecaptcha();" />
						<?php if( $key_status !== null ): ?>
							<span class="gforms_captcha_site_key_status">
								<?php if( $key_status ): ?>
									<i class="fa fa-check gf_valid"></i>
								<?php else: ?>
									<i class="fa fa-times gf_invalid"></i>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr valign="top" id="gforms_confirm_recaptcha" style="display:none;">
					<th scope="row">
						<label for="gforms_validate_recaptcha"><?php esc_html_e( 'Validate Keys', 'gravityforms' ); ?></label> <?php gform_tooltip( 'gforms_validate_recaptcha' ) ?>
					</th>
					<td>

						<p style="margin-bottom:10px;"><?php esc_html_e( 'Please complete the reCAPTCHA widget to validate your reCAPTCHA keys:' ); ?></p>
						<div id="recaptcha"></div>
						<input name="gform_recaptcha_reset" type="hidden" value="" />

						<script src="https://www.google.com/recaptcha/api.js" async defer></script>
						<script type="text/javascript">
							( function( $ ) {

								var $row        = $( '#gforms_confirm_recaptcha' ),
									$siteKey    = $( 'input[name="gforms_captcha_public_key"]' ),
									$secretKey  = $( 'input[name="gforms_captcha_private_key"]' ),
									$reset      = $( 'input[name="gform_recaptcha_reset"]' ),
									$keyStatus  = $( 'span.gforms_captcha_site_key_status' );

								window.loadRecaptcha = function() {

									var $recaptcha = $( '#recaptcha' ),
										$save      = $( '#save' );

									// flush all the things
									window.___grecaptcha_cfg.clients = {};
									$recaptcha.html( '' );
									$reset.val( 1 );
									$keyStatus.remove();

									if( ! $siteKey.val() || ! $secretKey.val() ) {
										$save.prop( 'disabled', false );
										return;
									} else {
										$save.prop( 'disabled', true );
									}

									grecaptcha.render( 'recaptcha', {
										'sitekey' : $siteKey.val(),
										'callback' : function() {
											$save.prop( 'disabled', false );
										}
									} );

									$row.show();

								};

							} )( jQuery );
						</script>

					</td>
				</tr>

			</table>

			<?php if ( GFCommon::current_user_can_any( 'gravityforms_edit_settings' ) ) { ?>
				<p class="submit" style="text-align: left;">
					<?php
					$save_button = '<input type="submit" name="submit" value="' . esc_html__( 'Save Settings', 'gravityforms' ) . '" class="button-primary gfbutton" id="save" />';

					/**
					 * Filters through and allows modification of the Settings save button HTML for the overall Gravity Forms Settings.
					 *
					 * @since Unknown
					 *
					 * @param string $save_button The HTML rendered for the save button.
					 */
					echo apply_filters( 'gform_settings_save_button', $save_button );
					?>
				</p>
			<?php } ?>
		</form>

		<div id='gform_upgrade_license' style="display:none;"></div>
		<script type="text/javascript">
			jQuery(document).ready(function () {
				jQuery.post(ajaxurl, {
						action            : 'gf_upgrade_license',
						gf_upgrade_license: "<?php echo wp_create_nonce( 'gf_upgrade_license' ) ?>"},

					function (data) {
						if (data.trim().length > 0)
							jQuery("#gform_upgrade_license").replaceWith(data);
					}
				);
			});
		</script>
		<?php
		/**
		 * Allows you to disable the Gravity Forms installation status section
		 *
		 * @since Unknown
		 *
		 * @param bool false Set to true to disable the installation status. Defaults to false.
		 */
		if ( ! apply_filters( 'gform_disable_installation_status', false ) ) { ?>
			<div class="hr-divider"></div>

			<h3><span><i class="fa fa-dashboard"></i> <?php esc_html_e( 'Installation Status', 'gravityforms' ); ?><span></h3>
			<table class="form-table">

				<tr valign="top">
					<th scope="row"><label><?php esc_html_e( 'PHP Version', 'gravityforms' ); ?></label></th>
					<td class="installation_item_cell">
						<strong><?php echo phpversion(); ?></strong>
					</td>
					<td>
						<?php
						if ( version_compare( phpversion(), '5.0.0', '>' ) ) {
							?>
							<i class="fa fa-check gf_valid"></i>
						<?php
						} else {
							?>
							<i class="fa fa-times gf_invalid"></i>
							<span class="installation_item_message"><?php esc_html_e( 'Gravity Forms requires PHP 5 or above.', 'gravityforms' ); ?></span>
						<?php
						}
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label><?php esc_html_e( 'MySQL Version', 'gravityforms' ); ?></label></th>
					<td class="installation_item_cell">
						<strong><?php echo esc_html( $wpdb->db_version() ); ?></strong>
					</td>
					<td>
						<?php
						if ( version_compare( $wpdb->db_version(), '5.0.0', '>' ) ) {
							?>
							<i class="fa fa-check gf_valid"></i>
						<?php
						} else {
							?>
							<i class="fa fa-times gf_invalid"></i>
							<span class="installation_item_message"><?php esc_html_e( 'Gravity Forms requires MySQL 5 or above.', 'gravityforms' ); ?></span>
						<?php
						}
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label><?php esc_html_e( 'WordPress Version', 'gravityforms' ); ?></label></th>
					<td class="installation_item_cell">
						<strong><?php echo esc_html( get_bloginfo( 'version' ) ); ?></strong>
					</td>
					<td>
						<?php
						if ( version_compare( get_bloginfo( 'version' ), GF_MIN_WP_VERSION_SUPPORT_TERMS, '>=' ) ) {
							?>
							<i class="fa fa-check gf_valid"></i>
						<?php
						} elseif ( version_compare( get_bloginfo( 'version' ), GF_MIN_WP_VERSION, '>=' ) ) {
							?>
							<i class="fa fa-times gf_invalid"></i>
							<span class="installation_item_message"><?php printf( esc_html__( 'The Gravity Forms support agreement requires WordPress v%s or greater. This site must be upgraded in order to be eligible for support.', 'gravityforms' ), GF_MIN_WP_VERSION_SUPPORT_TERMS ); ?></span>
							<?php
						} else {
							?>
							<i class="fa fa-times gf_invalid"></i>
							<span class="installation_item_message"><?php printf( esc_html__( 'Gravity Forms requires WordPress v%s or greater. You must upgrade WordPress in order to use this version of Gravity Forms.', 'gravityforms' ), GF_MIN_WP_VERSION ); ?></span>
						<?php
						}
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label><?php esc_html_e( 'Gravity Forms Version', 'gravityforms' ); ?></label></th>
					<td class="installation_item_cell">
						<strong><?php echo esc_html( GFCommon::$version ) ?></strong>
					</td>
					<td>
						<?php
						if ( version_compare( GFCommon::$version, $version_info['version'], '>=' ) ) {
							?>
							<i class="fa fa-check gf_valid"></i>
						<?php
						} else {
							echo sprintf( esc_html__( 'New version %s available. Automatic upgrade available on the %splugins page%s', 'gravityforms' ), esc_html( $version_info['version'] ), '<a href="plugins.php">', '</a>' );
						}
						?>
					</td>
				</tr>
			</table>
		<?php
		}

		self::page_footer();
	}

	/**
	 * Handles license upgrades from the Settings page.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::get_key()
	 * @uses GFCommon::post_to_manager()
	 *
	 * @return void
	 */
	public static function upgrade_license() {
		$key                = GFCommon::get_key();
		$body               = "key=$key";
		$options            = array( 'method' => 'POST', 'timeout' => 3, 'body' => $body );
		$options['headers'] = array(
			'Content-Type'   => 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ),
			'Content-Length' => strlen( $body ),
			'User-Agent'     => 'WordPress/' . get_bloginfo( 'version' ),
			'Referer'        => get_bloginfo( 'url' ),
		);

		$raw_response = GFCommon::post_to_manager( 'api.php', 'op=upgrade_message&key=' . GFCommon::get_key(), $options );

		if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code'] ) {
			$message = '';
		} else {
			$message = $raw_response['body'];
		}

		// Validating that message is a valid Gravity Form message. If message is invalid, don't display anything.
		if ( substr( $message, 0, 10 ) != '<!--GFM-->' ) {
			$message = '';
		}

		echo $message;

		exit;
	}

	/**
	 * Outputs the settings page header.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses SCRIPT_DEBUG
	 * @uses GFSettings::get_subview()
	 * @uses GFSettings::$addon_pages
	 * @uses GFCommon::get_browser_class()
	 * @uses GFCommon::display_dismissible_message()
	 *
	 * @param string $title   Optional. The page title to be used. Defaults to an empty string.
	 * @param string $message Optional. The message to display in the header. Defaults to empty string.
	 *
	 * @return void
	 */
	public static function page_header( $title = '', $message = '' ) {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		// Register admin styles.
		wp_register_style( 'gform_admin', GFCommon::get_base_url() . "/css/admin{$min}.css" );
		wp_print_styles( array( 'jquery-ui-styles', 'gform_admin' ) );

		$current_tab = self::get_subview();

		// Build left side options, always have GF Settings first and Uninstall last, put add-ons in the middle.
		$setting_tabs = array( '10' => array( 'name' => 'settings', 'label' => __( 'Settings', 'gravityforms' ) ) );

		if ( ! empty( self::$addon_pages ) ) {

			$sorted_addons = self::$addon_pages;
			asort( $sorted_addons );

			// Add add-ons to menu
			foreach ( $sorted_addons as $sorted_addon ) {
				$setting_tabs[] = array(
					'name'  => urlencode( $sorted_addon['name'] ),
					'label' => esc_html( $sorted_addon['tab_label'] ),
					'title' => esc_html( rgar( $sorted_addon, 'title' ) ),
				);
			}
		}

		// Prevent Uninstall tab from being added for users that don't have gravityforms_uninstall capability.
		if ( GFCommon::current_user_can_any( 'gravityforms_uninstall' ) ) {
			$setting_tabs[] = array( 'name' => 'uninstall', 'label' => __( 'Uninstall', 'gravityforms' ) );
		}

		/**
		 * Filters the Settings menu tabs.
		 *
		 * @since Unknown
		 *
		 * @param array $setting_tabs The settings tab names and labels.
		 */
		$setting_tabs = apply_filters( 'gform_settings_menu', $setting_tabs );
		ksort( $setting_tabs, SORT_NUMERIC );

		// Kind of boring having to pass the title, optionally get it from the settings tab
		if ( ! $title ) {
			foreach ( $setting_tabs as $tab ) {
				if ( $tab['name'] == urlencode( $current_tab ) ) {
					$title = ! empty( $tab['title'] ) ? $tab['title'] : $tab['label'];
				}
			}
		}

		?>

		<div class="wrap <?php echo GFCommon::get_browser_class() ?> gforms_settings_wrap">

			<?php if ( $message ) { ?>
				<div id="message" class="updated"><p><?php echo $message; ?></p></div>
			<?php } ?>

			<h2><?php echo esc_html( $title ) ?></h2>

			<?php GFCommon::display_dismissible_message(); ?>

			<div id="gform_tab_group" class="gform_tab_group vertical_tabs">
				<ul id="gform_tabs" class="gform_tabs">
					<?php
					foreach ( $setting_tabs as $tab ) {
						$name = $tab['label'];
						$url  = add_query_arg( array( 'subview' => $tab['name'] ), admin_url( 'admin.php?page=gf_settings' ) );
						?>
						<li <?php echo urlencode( $current_tab ) == $tab['name'] ? "class='active'" : '' ?>>
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $tab['label'] ) ?></a>
						</li>
					<?php
					}
					?>
				</ul>

				<div id="gform_tab_container" class="gform_tab_container">
					<div class="gform_tab_content" id="tab_<?php echo esc_attr( $current_tab ); ?>">

	<?php
	}

	/**
	 * Outputs the Settings page footer.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return void
	 */
	public static function page_footer() {
					?>
				</div>
				<!-- / gform_tab_content -->
			</div>
			<!-- / gform_tab_container -->
		</div>
		<!-- / gform_tab_group -->

		<br class="clear" style="clear: both;" />

	</div> <!-- / wrap -->

	<script type="text/javascript">
		// JS fix for keep content contained on tabs with less content
		jQuery(document).ready(function ($) {
			$('#gform_tab_container').css('minHeight', jQuery('#gform_tabs').height() + 100);
		});
	</script>

	<?php
	}

	/**
	 * Gets the Settings page subview based on the query string.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return string The subview.
	 */
	public static function get_subview() {

		// Default to subview, if no subview provided support
		$subview = rgget( 'subview' ) ? rgget( 'subview' ) : rgget( 'addon' );

		if ( ! $subview ) {
			$subview = 'settings';
		}

		return $subview;
	}

	/**
	 * Handles the enabling/disabling of the Akismet Integration setting
	 *
	 * Called from GFSettings::gravityforms_settings_page
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFSettings::gravityforms_settings_page()
	 *
	 * @return string $akismet_setting '1' if turning on, '2' if turning off.
	 */
	public static function get_posted_akismet_setting() {

		$akismet_setting = rgpost( 'gforms_enable_akismet' );

		if( $akismet_setting ) {
			$akismet_setting = '1';
		} elseif( $akismet_setting === false ) {
			$akismet_setting = false;
		} else {
			$akismet_setting = '0';
		}

		return $akismet_setting;
	}


	/**
	 * Handles the registration of a new site when a new license key is entered
	 *
	 * @access public
	 * @static
	 * @see GFForms::include_gravity_api
	 * @see gapi()
	 * @see Gravity_Api::register_current_site
	 *
	 * @param string $value     The new key after edits
	 * @param string $old_value The previous key
	 *
	 * @return string $value The new key
	 */
	public static function action_add_option_rg_gforms_key( $option, $value ){

		self::update_site_registration( '', $value );

	}

	/**
	 * Handles updates to the Gravity Forms license key
	 *
	 * @access public
	 * @static
	 * @see GFForms::include_gravity_api
	 * @see gapi()
	 * @see Gravity_Api::update_current_site
	 *
	 * @param string $value     The new key after edits
	 * @param string $old_value The previous key
	 *
	 * @return string $value The new key
	 */
	public static function action_update_option_rg_gforms_key( $old_value, $value ){

		self::update_site_registration( $old_value, $value );

	}

	/**
	 * Handles the deletion of the Gravity Forms key by de-registering the site
	 *
	 * @access public
	 * @static
	 * @see GFForms::include_gravity_api
	 * @see gapi()
	 * @see Gravity_Api::deregister_current_site
	 */
	public static function action_delete_option_rg_gforms_key() {


		GFForms::include_gravity_api();

		if ( gapi()->is_site_registered() ) {

			gapi()->deregister_current_site();
		}
	}


	private static function update_site_registration( $previous_key_md5, $new_key_md5 ){

		GFForms::include_gravity_api();

		$result = null;

		if ( empty( $new_key_md5 ) ) {

			//De-registering site when key is removed
			$result = gapi()->deregister_current_site();

		}
		else if ( $previous_key_md5 != $new_key_md5 ) {

			//Key has changed, update site record appropriately.

			//Get new key information
			$version_info = GFCommon::get_version_info( false );

			//Has site been already registered?
			$is_site_registered = gapi()->is_site_registered();

			$is_valid_new 			= $version_info['is_valid_key'] && !$is_site_registered;
			$is_valid_registered 	= $version_info['is_valid_key'] && $is_site_registered;
			$is_invalid				= !$version_info['is_valid_key'] && $is_site_registered;

			if ( $is_valid_new ) {
				//Site is new (not registered) and license key is valid
				//Register new site
				$result = gapi()->register_current_site( $new_key_md5, true );
			}
			else if ( $is_valid_registered ) {

				//Site is already registered and new license key is valid
				//Update site with new key
				$result = gapi()->update_current_site( $new_key_md5 );
			}

			else if ( $is_invalid ){

				//invalid key, deregister site
				$result = gapi()->deregister_current_site();
			}

		}

		if ( is_wp_error( $result ) ){
			GFCommon::log_error( 'Failed to update site registration with Gravity Manager. ' . print_r( $result, true ) );
		}

	}
}
