<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Upgrade_Wizard {
	private $_step_class_names = array();

	function __construct(){

	}

	public function display(){
		//not implemented
		return false;
	}
/*
	public function display(){

		// register admin styles
		wp_print_styles( array( 'jquery-ui-styles', 'gform_admin' ) );

		?>

		<div class="wrap about-wrap gform_installation_progress_step_wrap">

			<h1><?php esc_html_e( 'Gravity Forms Upgrade', 'gravityforms' ) ?></h1>

			<hr/>

			<h2><?php esc_html_e( 'Database Update Required', 'gravityforms' ); ?></h2>

			<p><?php esc_html_e( 'Gravity Forms has been updated! Before we send you on your way, we have to update your database to the newest version.', 'gravityforms' ); ?></p>
			<p><?php esc_html_e( 'The database update process may take a little while, so please be patient.', 'gravityforms' ); ?></p>

			<input class="button button-primary" type="submit" value="<?php esc_attr_e( 'Upgrade', 'gravityforms' ) ?>" name="_upgrade"/>

			<script type="text/javascript">

				function gform_start_upgrade(){

					gform_message( 'Progress: 0%' );

					//TODO: implement AJAX callbacks for manual upgrade

					jQuery.post(ajaxurl, {
						action			: "gf_upgrade",
						gf_upgrade		: '<?php echo wp_create_nonce( 'gf_upgrade' ); ?>',
					})
					.done(function( data ) {
						gform_success_message();
					})

					setTimeout( 'gform_check_upgrade_status', 1000 );

				}

				function gform_check_upgrade_status(){

					jQuery.post(ajaxurl, {
						action				: "gf_check_upgrade_status",
						gf_upgrade_status	: '<?php echo wp_create_nonce( 'gf_upgrade_status' ); ?>',
					})
					.done(function( data ) {
						if( data == '100' ){
							gform_success_message();
						}
						else{
							gform_message( 'Progress: ' + parseInt( data ) + '%' );
						}
					})

				}

				function gform_message( message ){
					jQuery( '#gform_upgrade_message' ).html( message );
				}

				function gform_success_message(){
					gform_message( 'Database upgrade complete' );
				}
			</script>
		</div>

	<?php

		return true;
	}
*/
}
