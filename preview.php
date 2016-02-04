<?php

//For backwards compatibility, load wordpress if it hasn't been loaded yet
//Will be used if this file is being called directly
if ( ! class_exists( 'RGForms' ) ) {
	for ( $i = 0; $i < $depth = 10; $i ++ ) {
		$wp_root_path = str_repeat( '../', $i );

		if ( file_exists( "{$wp_root_path}wp-load.php" ) ) {
			require_once( "{$wp_root_path}wp-load.php" );
			require_once( "{$wp_root_path}wp-admin/includes/admin.php" );
			break;
		}
	}

	//redirect to the login page if user is not authenticated
	auth_redirect();
}

if ( ! GFCommon::current_user_can_any( array( 'gravityforms_edit_forms', 'gravityforms_create_form', 'gravityforms_preview_forms' ) ) ) {
	die( esc_html__( "You don't have adequate permission to preview forms.", 'gravityforms' ) );
}

$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="Imagetoolbar" content="No" />
	<meta name="viewport" content="width=device-width; initial-scale=1.0;"> 
	<title><?php esc_html_e( 'Form Preview', 'gravityforms' ) ?></title>
	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/reset<?php echo $min; ?>.css' type='text/css' />
	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/formreset<?php echo $min; ?>.css' type='text/css' />
	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/datepicker<?php echo $min; ?>.css' type='text/css' />
	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/formsmain<?php echo $min; ?>.css' type='text/css' />
	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/readyclass<?php echo $min; ?>.css' type='text/css' />
	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/browsers<?php echo $min; ?>.css' type='text/css' />

<?php
if ( is_rtl() ) {
	?>
	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/rtl<?php echo $min; ?>.css' type='text/css' />
	<?php
}
?>

	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/preview<?php echo $min; ?>.css' type='text/css' />
	<?php
	require_once( GFCommon::get_base_path() . '/form_display.php' );
	$form = RGFormsModel::get_form_meta( $_GET['id'] );
	GFFormDisplay::enqueue_form_scripts( $form );
	wp_print_scripts();

	$styles = apply_filters( 'gform_preview_styles', array(), $form );
	if ( ! empty( $styles ) ) {
		wp_print_styles( $styles );
	}
?>

</head>
<body>
<div id="preview_top">
	<div id="preview_hdr">
		<div>
			<span class="actionlinks"><a href="javascript:window.close()" class="close_window"><?php esc_html_e( 'close window', 'gravityforms' ) ?></a></span><h2><?php esc_html_e( 'Form Preview', 'gravityforms' ) ?></h2>
		</div>
	</div>
	<div id="preview_note"><?php esc_html_e( 'Note: This is a simple form preview. This form may display differently when added to your page based on inheritance from individual theme styles.', 'gravityforms' ) ?></div>
</div>
<div id="preview_form_container">
	<?php
	echo RGForms::get_form( $_GET['id'], true, true, true );

	?>
</div>
<?php

/**
 * Fires in the footer of a Form Preview page
 *
 * @param int $_GET['id'] The ID of the form currently being previewed
 */
do_action( 'gform_preview_footer', $_GET['id'] );
?>
</body>
</html>