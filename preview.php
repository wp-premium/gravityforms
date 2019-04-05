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

// If user doesn't have appropriate permissions, die.
if ( ! GFCommon::current_user_can_any( array( 'gravityforms_edit_forms', 'gravityforms_create_form', 'gravityforms_preview_forms' ) ) ) {
	die( esc_html__( "You don't have adequate permission to preview forms.", 'gravityforms' ) );
}

// Load form display class.
require_once( GFCommon::get_base_path() . '/form_display.php' );

// Get form ID.
$form_id = absint( rgget( 'id' ) );

// Get form object.
$form = RGFormsModel::get_form_meta( $_GET['id'] );

// Determine if we're loading minified scripts.
$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="Imagetoolbar" content="No" />
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Form Preview', 'gravityforms' ) ?></title>
	<?php

		// If form exists, enqueue its scripts.
		if ( ! empty( $form ) ) {
			GFFormDisplay::enqueue_form_scripts( $form );
		}

		wp_enqueue_script( 'gform_preview' );

		wp_print_head_scripts();

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

			<span class="toggle_helpers">
				<input type="checkbox" name="showgrid" id="showgrid" value="Y" class="show-grid-input" /><label for="showgrid" class="show-grid-label"><?php esc_html_e( 'display grid', 'gravityforms' ) ?></label>
				<input type="checkbox" name="showme" id="showme" value="Y" class="show-helpers-input" /><label for="showme" class="show-helpers-label"><?php esc_html_e( 'show structure', 'gravityforms' ) ?></label>
			</span>

			<h2><?php esc_html_e( 'Form Preview', 'gravityforms' ) ?> : ID <?php echo $form_id; ?></h2>
		</div>
	</div>
	<div id="preview_note" class="preview_notice">
		<?php esc_html_e( 'Note: This is a simple form preview. This form may display differently when added to your page based on normal inheritance from parent theme styles.', 'gravityforms' ) ?> <i class="hidenotice" title="<?php esc_html_e( 'dismiss', 'gravityforms' ) ?>"></i>
	</div>
</div>
<div id="helper_legend_container">
	<ul id="helper_legend">
		<li class="showid">Element ID</li>
		<li class="showclass">Class Name</li>
	</ul>
</div>
<div id="preview_form_container">
	<span class="rule25"></span>
	<span class="rule33"></span>
	<span class="rule50"></span>
	<span class="rule66"></span>
	<span class="rule75"></span>
	<?php echo RGForms::get_form( $form_id, true, true, true ); ?>
</div>
<div id="browser_size_info"></div>

<!-- load up the styles -->

<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/reset<?php echo $min; ?>.css' type='text/css' />
<?php

wp_print_footer_scripts();

/**
 * Fires in the footer of a Form Preview page
 *
 * @param int $_GET['id'] The ID of the form currently being previewed
 */
do_action( 'gform_preview_footer', $form_id );
?>

<?php if ( is_rtl() ) { ?><link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/rtl<?php echo $min; ?>.css' type='text/css' /><?php } ?>
<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/preview<?php echo $min; ?>.css' type='text/css' />


</body>
</html>