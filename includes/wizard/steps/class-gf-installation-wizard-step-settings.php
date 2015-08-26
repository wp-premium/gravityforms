<?php

class GF_Installation_Wizard_Step_Settings extends GF_Installation_Wizard_Step {

	protected $_name = 'settings';

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
					<input type="radio" name="enable_noconflict" value="1" <?php echo $this->enable_noconflict == 1 ? "checked='checked'" : '' ?> id="gform_enable_noconflict" /> <?php _e( 'On', 'gravityforms' ); ?>&nbsp;&nbsp;
					<input type="radio" name="enable_noconflict" value="0" <?php echo  $this->enable_noconflict == 1 ? '' : "checked='checked'" ?> id="gform_disable_noconflict" /> <?php esc_html_e( 'Off', 'gravityforms' ); ?>
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
						$akismet_setting = $this->enable_akismet;
						$is_akismet_enabled = $akismet_setting === false || ! empty( $akismet_setting ); //Akismet is enabled by default.
						?>
						<input type="radio" name="enable_akismet" value="1" <?php checked( $is_akismet_enabled, true ) ?> id="gforms_enable_akismet" /> <?php esc_html_e( 'Yes', 'gravityforms' ); ?>&nbsp;&nbsp;
						<input type="radio" name="enable_akismet" value="0" <?php checked( $is_akismet_enabled, false ) ?> /> <?php esc_html_e( 'No', 'gravityforms' ); ?>
						<br />
						<span class="gf_settings_description"><?php esc_html_e( 'Protect your form entries from spam using Akismet.', 'gravityforms' ); ?></span>
					</td>
				</tr>
			<?php } ?>
		</table>

	<?php
	}

	function get_title(){
		return esc_html__( 'Global Settings', 'gravityforms' );
	}

	function summary( $echo = true ){
		$enabled = '&nbsp;<i class="fa fa-check gf_valid"></i>';
		$disabled = '&nbsp;<i class="fa fa-times gf_invalid"></i>';
		$html = '<ul>';
		$html .= sprintf( '<li>%s: %s</li>', esc_html__( 'No-Conflict Mode', 'gravityforms' ), $this->enable_noconflict ? esc_html__( 'Enabled', 'gravityforms' ) . $enabled : esc_html__( 'Disabled', 'gravityforms' ) . $disabled );
		$html .= sprintf( '<li>%s: %s</li>', esc_html__( 'Akismet Integration', 'gravityforms' ), $this->enable_akismet ? esc_html__( 'Enabled', 'gravityforms' ) . $enabled : esc_html__( 'Disabled', 'gravityforms' ) . $disabled );
		$html .= sprintf( '<li>%s: %s</li>', esc_html__( 'Currency', 'gravityforms' ), $this->currency ? $this->currency . $enabled : esc_html__( 'Not set', 'gravityforms' ) . $disabled );
		$html .= '</ul>';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	function install(){
		update_option( 'gform_enable_noconflict', $this->enable_noconflict );
		update_option( 'rg_gforms_enable_akismet', $this->enable_akismet );
		update_option( 'rg_gforms_currency', $this->currency );
	}

}