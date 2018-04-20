<?php

class GF_Installation_Wizard_Step_Background_Updates extends GF_Installation_Wizard_Step {

	protected $_name = 'background_updates';

	// Defaults
	public $defaults = array(
		'background_updates' => 'enabled',
		'accept_terms' => false,
	);

	function display() {

		?>
		<p>
			<?php
			esc_html_e( 'Gravity Forms will download important bug fixes, security enhancements and plugin updates automatically. Updates are extremely important to the security of your WordPress site.', 'gravityforms' );
			?>
		</p>
		<p>
			<strong>
				<?php
				esc_html_e( 'This feature is activated by default unless you opt to disable it below. We only recommend disabling background updates if you intend on managing updates manually.', 'gravityforms' );
				?>
			</strong>
		</p>
		<?php
		$license_key_step_settings = get_option( 'gform_installation_wizard_license_key' );
		$is_valid_license_key      = $license_key_step_settings['is_valid_key'];
		if ( ! $is_valid_license_key ) :
			?>
			<p>
				<strong>
					<?php esc_html_e( 'Updates will only be available if you have entered a valid License Key', 'gravityforms' ); ?>
				</strong>
			</p>
		<?php
		endif;
		?>


		<div>
			<label>
				<input type="radio" id="background_updates_enabled" value="enabled" <?php checked( 'enabled', $this->background_updates ); ?> name="background_updates"/>
				<?php esc_html_e( 'Keep background updates enabled', 'gravityforms' ); ?>
			</label>
		</div>
		<div>
			<label>
				<input type="radio" id="background_updates_disabled" value="disabled" <?php checked( 'disabled', $this->background_updates ); ?> name="background_updates"/>
				<?php esc_html_e( 'Turn off background updates', 'gravityforms' ); ?>
			</label>
		</div>
		<div id="accept_terms_container" style="display:none;">
			<div id="are_you_sure" style="background: #fff none repeat scroll 0 0;box-shadow: 0 1px 1px 0 rgba(0, 0, 0, 0.1);padding: 1px 12px;border-left: 4px solid #dd3d36;margin: 5px 0 15px;display: inline-block;">

				<h3 style="margin-top:0.6em;"><i class="fa fa-exclamation-triangle gf_invalid"></i> <?php _e( 'Are you sure?', 'gravityforms' ); ?>
				</h3>
				<p>
					<strong><?php esc_html_e( 'By disabling background updates your site may not get critical bug fixes and security enhancements. We only recommend doing this if you are experienced at managing a WordPress site and accept the risks involved in manually keeping your WordPress site updated.', 'gravityforms' ); ?></strong>
				</p>
			</div>
			<label>
				<input type="checkbox" id="accept_terms" value="1" <?php checked( 1, $this->accept_terms ); ?> name="accept_terms"/>
				<?php esc_html_e( 'I understand and accept the risk of not enabling background updates.', 'gravityforms' ); ?> <span class="gfield_required">*</span>
			</label>

			<?php $this->validation_message( 'accept_terms' ); ?>
		</div>

		<script type="text/javascript">
			(function($) {
				$(document).ready(function() {
					var backgroundUpdatesDisabled = $('#background_updates_disabled').is(':checked');

					$('#accept_terms_container').toggle(backgroundUpdatesDisabled);

					$('#background_updates_disabled').click(function(){
						$("#accept_terms_container").slideDown();
					});
					$('#background_updates_enabled').click(function(){
						$('#accept_terms').prop('checked', false);
						$("#accept_terms_container").slideUp();
					});
				})
			})(jQuery);
		</script>

	<?php
	}

	function get_title(){
		return esc_html__( 'Background Updates', 'gravityforms' );
	}

	function validate() {
		$valid = true;
		if ( $this->background_updates == 'enabled' ) {
			$this->accept_terms = false;
		} elseif ( empty( $this->accept_terms ) ) {
			$this->set_field_validation_result( 'accept_terms', esc_html__( 'Please accept the terms.' ) );
			$valid = false;
		}

		return $valid;
	}

	function summary( $echo = true ){
		$html = $this->background_updates !== 'disabled' ? esc_html__( 'Enabled', 'gravityforms' ) . '&nbsp;<i class="fa fa-check gf_valid"></i>' :   esc_html__( 'Disabled', 'gravityforms' ) . '&nbsp;<i class="fa fa-times gf_invalid"></i>' ;
		if ( $echo ) {
			echo $html;
		}
		return $html;
	}

	function install(){

		update_option( 'gform_enable_background_updates', $this->background_updates != 'disabled' );

	}

}