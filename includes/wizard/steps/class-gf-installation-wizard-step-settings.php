<?php

class GF_Installation_Wizard_Step_Settings extends GF_Installation_Wizard_Step {

	protected $_name = 'settings';

	public $defaults = array(
		'currency' => '',
		'enable_noconflict' => false,
		'enable_toolbar_menu' => true,
		'enable_akismet' => true,
	);

	function display() {
		$disabled = apply_filters( 'gform_currency_disabled', false ) ? "disabled='disabled'" : ''
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label for="gforms_currency"><?php esc_html_e( 'Currency', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_currency' ) ?>
				</th>
				<td>
					<?php
					$disabled = apply_filters( 'gform_currency_disabled', false ) ? "disabled='disabled'" : ''
					?>

					<select id="gforms_currency" name="currency" <?php echo $disabled ?>>
						<option value=""><?php esc_html_e( 'Select a Currency', 'gravityforms' ) ?></option>
						<?php
						require_once( GFCommon::get_base_path() . '/currency.php' );
						$current_currency = $this->currency;

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
					<label for="gform_enable_noconflict"><?php esc_html_e( 'No-Conflict Mode', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_noconflict' ) ?>
				</th>
				<td>
					<input type="radio" name="enable_noconflict" value="1" <?php echo $this->enable_noconflict == 1 ? "checked='checked'" : '' ?> id="gform_enable_noconflict" /> <?php esc_html_e( 'On', 'gravityforms' ); ?>&nbsp;&nbsp;
					<input type="radio" name="enable_noconflict" value="0" <?php echo  $this->enable_noconflict == 1 ? '' : "checked='checked'" ?> id="gform_disable_noconflict" /> <?php esc_html_e( 'Off', 'gravityforms' ); ?>
					<br />
					<span class="gf_settings_description"><?php esc_html_e( 'Set this to ON to prevent extraneous scripts and styles from being printed on Gravity Forms admin pages, reducing conflicts with other plugins and themes.', 'gravityforms' ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="gform_enable_toolbar_menu"><?php esc_html_e( 'Toolbar Menu', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_toolbar_menu' ) ?>
				</th>
				<td>
					<input type="radio" name="enable_toolbar_menu" value="1" <?php checked( $this->enable_toolbar_menu, true ); ?> id="gform_enable_toolbar_menu" /> <?php esc_html_e( 'On', 'gravityforms' ); ?>&nbsp;&nbsp;
					<input type="radio" name="enable_toolbar_menu" value="0" <?php checked( $this->enable_toolbar_menu, false );?> id="gform_disable_toolbar_menu" /> <?php esc_html_e( 'Off', 'gravityforms' ); ?>
					<br />
					<span class="gf_settings_description"><?php esc_html_e( 'Set this to ON to display the Forms menu in the WordPress top toolbar. The Forms menu will display the latest ten forms recently opened in the form editor.', 'gravityforms' ); ?></span>
				</td>
			</tr>

			<?php if ( GFCommon::has_akismet() ) { ?>
				<tr valign="top">
					<th scope="row">
						<label for="gforms_enable_akismet"><?php esc_html_e( 'Akismet Integration', 'gravityforms' ); ?></label>  <?php gform_tooltip( 'settings_akismet' ) ?>
					</th>
					<td>
						<input type="radio" name="enable_akismet" value="1" <?php checked( $this->enable_akismet, true ) ?> id="gforms_enable_akismet" /> <?php esc_html_e( 'Yes', 'gravityforms' ); ?>&nbsp;&nbsp;
						<input type="radio" name="enable_akismet" value="0" <?php checked( $this->enable_akismet, false ) ?> /> <?php esc_html_e( 'No', 'gravityforms' ); ?>
						<br />
						<span class="gf_settings_description"><?php esc_html_e( 'Protect your form entries from spam using Akismet.', 'gravityforms' ); ?></span>
					</td>
				</tr>
			<?php } ?>
		</table>

	<?php
	}

	function get_title() {
		return esc_html__( 'Global Settings', 'gravityforms' );
	}

	function install() {
		update_option( 'gform_enable_noconflict', (bool) $this->enable_noconflict );
		update_option( 'rg_gforms_enable_akismet', (bool) $this->enable_akismet );
		update_option( 'rg_gforms_currency', $this->currency );
		update_option( 'gform_enable_toolbar_menu', (bool) $this->enable_toolbar_menu );
	}
}
