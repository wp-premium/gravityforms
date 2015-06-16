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

if ( ! GFCommon::current_user_can_any( 'gravityforms_view_entries' ) ) {
	die( __( "You don't have adequate permission to view entries.", 'gravityforms' ) );
}

$form_id = absint( rgget( 'fid' ) );
$leads = rgget( 'lid' );
if ( 0 == $leads ) {
	// get all the lead ids for the current filter / search
	$filter                    = rgget( 'filter' );
	$search                    = rgget( 'search' );
	$star                      = $filter == 'star' ? 1 : null;
	$read                      = $filter == 'unread' ? 0 : null;
	$status                    = in_array( $filter, array( 'trash', 'spam' ) ) ? $filter : 'active';
	$search_criteria['status'] = $status;

	if ( $star ) {
		$search_criteria['field_filters'][] = array( 'key' => 'is_starred', 'value' => (bool) $star );
	}
	if ( ! is_null( $read ) ) {
		$search_criteria['field_filters'][] = array( 'key' => 'is_read', 'value' => (bool) $read );
	}

	$search_field_id = rgget( 'field_id' );
	$search_operator = rgget( 'operator' );
	if ( isset( $_GET['field_id'] ) && $_GET['field_id'] !== '' ) {
		$key            = $search_field_id;
		$val            = rgget( 's' );
		$strpos_row_key = strpos( $search_field_id, '|' );
		if ( $strpos_row_key !== false ) { //multi-row
			$key_array = explode( '|', $search_field_id );
			$key       = $key_array[0];
			$val       = $key_array[1] . ':' . $val;
		}
		$search_criteria['field_filters'][] = array(
			'key'      => $key,
			'operator' => rgempty( 'operator', $_GET ) ? 'is' : rgget( 'operator' ),
			'value'    => $val,
		);
	}
	$lead_ids = GFFormsModel::search_lead_ids( $form_id, $search_criteria );
} else {
	$lead_ids = explode( ',', $leads );
}


$page_break = rgget( 'page_break' ) ? 'print-page-break' : false;

// sort lead IDs numerically
sort( $lead_ids );

if ( empty( $form_id ) || empty( $lead_ids ) ) {
	die( esc_html__( 'Form Id and Lead Id are required parameters.', 'gravityforms' ) );
}

$form = RGFormsModel::get_form_meta( $form_id );

$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<meta name="keywords" content="" />
	<meta name="description" content="" />
	<meta name="MSSmartTagsPreventParsing" content="true" />
	<meta name="Robots" content="noindex, nofollow" />
	<meta http-equiv="Imagetoolbar" content="No" />
	<title>
		Print Preview :
		<?php echo esc_html( $form['title'] ) ?> :
		<?php echo count( $lead_ids ) > 1 ? esc_html__( 'Entry # ', 'gravityforms' ) . $lead_ids[0] : esc_html__( 'Bulk Print', 'gravityforms' ); ?>
	</title>
	<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/print<?php echo $min; ?>.css' type='text/css' />
<?php
$styles = apply_filters( 'gform_print_styles', false, $form );
if ( ! empty( $styles ) ) {
	wp_print_styles( $styles );
}


?>
</head>
<body onload="window.print();">

<div id="print_preview_hdr" style="display:none">
	<div>
		<span class="actionlinks"><a href="javascript:;" onclick="window.print();" class="header-print-link">print this page</a> | <a href="javascript:window.close()" class="close_window"><?php esc_html_e( 'close window', 'gravityforms' ) ?></a></span><?php esc_html_e( 'Print Preview', 'gravityforms' ) ?>
	</div>
</div>
<div id="view-container">
<?php

require_once( GFCommon::get_base_path() . '/entry_detail.php' );

foreach ( $lead_ids as $lead_id ) {

	$lead = RGFormsModel::get_lead( $lead_id );

	do_action( 'gform_print_entry_header', $form, $lead );

	// Separate each entry inside a form element so radio buttons don't get treated as a single group across multiple entries.
	echo '<form>';

	GFEntryDetail::lead_detail_grid( $form, $lead );

	echo '</form>';

	if ( rgget( 'notes' ) ) {
		$notes = RGFormsModel::get_lead_notes( $lead['id'] );
		if ( ! empty( $notes ) ) {
			GFEntryDetail::notes_grid( $notes, false );
		}
	}

	// output entry divider/page break
	if ( array_search( $lead_id, $lead_ids ) < count( $lead_ids ) - 1 ) {
		echo '<div class="print-hr ' . $page_break . '"></div>';
	}

	do_action( 'gform_print_entry_footer', $form, $lead );
}

?>
</div>
</body>
</html>