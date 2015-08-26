<?php

class GF_Installation_Wizard_Step_License_Key extends GF_Installation_Wizard_Step {
	public $required = true;

	protected $_name = 'license_key';

	public $defaults = array(
		'license_key' => '',
		'accept_terms' => false,
	);

	function display() {

		if ( ! $this->license_key && defined( 'GF_LICENSE_KEY' ) ) {
			$this->license_key = GF_LICENSE_KEY;
		}

		?>
		<p>
			<?php echo sprintf( esc_html__( 'Enter your Gravity Forms License Key below.  Your key unlocks access to automatic updates, the add-on installer, and support.  You can find your key on the My Account page on the %sGravity Forms%s site.', 'gravityforms' ), '<a href="http://www.gravityforms.com">', '</a>' ); ?>

		</p>
		<div>
			<input type="text" class="regular-text" id="license_key" value="<?php echo esc_attr( $this->license_key ); ?>" name="license_key" placeholder="<?php esc_attr_e('Enter Your License Key', 'gravityforms'); ?>" />
			<?php
			$key_error = $this->validation_message( 'license_key', false );
			if ( $key_error ) {
				echo $key_error;
			}
			?>
		</div>

		<?php
		$message = $this->validation_message( 'accept_terms', false );
		if ( $message || $key_error || $this->accept_terms ) {
			?>
			<p>
				<?php esc_html_e( "If you don't enter a valid license key, you will not be able to update Gravity Forms when important bug fixes and security enhancements are released. This can be a serious security risk for your site.", 'gravityforms' ); ?>
			</p>
			<div>
				<label>
					<input type="checkbox" id="accept_terms" value="1" <?php checked( 1, $this->accept_terms ); ?> name="accept_terms" />
					<?php esc_html_e( 'I understand the risks', 'gravityforms' ); ?> <span class="gfield_required">*</span>
				</label>
				<?php echo $message ?>
			</div>
		<?php
		}
	}

	function get_title(){
		return esc_html__( 'License Key', 'gravityforms' );
	}

	function validate() {

		$this->is_valid_key = true;
		$license_key = $this->license_key;

		if ( empty ( $license_key ) ) {
			$message = esc_html__( 'Please enter a valid license key.', 'gravityforms' ) . '</span>';
			$this->set_field_validation_result( 'license_key', $message );
			$this->is_valid_key = false;
		} else {
			$key_info = GFCommon::get_key_info( $license_key );
			if ( empty( $key_info ) || ( ! $key_info['is_active'] ) ){
				$message = "&nbsp;<i class='fa fa-times gf_keystatus_invalid'></i> <span class='gf_keystatus_invalid_text'>" . __( 'Invalid or Expired Key : Please make sure you have entered the correct value and that your key is not expired.', 'gravityforms' ) . '</span>';
				$this->set_field_validation_result( 'license_key', $message );
				$this->is_valid_key = false;
			}
		}

		if ( ! $this->is_valid_key && ! $this->accept_terms ) {
			$this->set_field_validation_result( 'accept_terms', __( 'Please accept the terms', 'gravityforms' ) );
		}

		$valid = $this->is_valid_key || ( ! $this->is_valid_key && $this->accept_terms );
		return $valid;
	}

	function install(){
		if ( $this->license_key ) {
			$key = trim( $this->license_key );
			update_option( 'rg_gforms_key', md5( $key ) );

			$version_info = GFCommon::get_version_info( false );
		}
	}

	function get_previous_button_text(){
		return '';
	}

}