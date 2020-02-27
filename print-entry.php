<?php

/**
 * For backwards compatibility, load WordPress if it hasn't been loaded yet.
 * Will be used if this file is being called directly.
 */
if ( ! class_exists( 'GFForms' ) ) {

	for ( $i = 0; $i < $depth = 10; $i ++ ) {

		$wp_root_path = str_repeat( '../', $i );

		if ( file_exists( "{$wp_root_path}wp-load.php" ) ) {
			require_once( "{$wp_root_path}wp-load.php" );
			require_once( "{$wp_root_path}wp-admin/includes/admin.php" );
			break;
		}

	}

	// Redirect to the login page if user is not authenticated.
	auth_redirect();

}

if ( ! GFCommon::current_user_can_any( 'gravityforms_view_entries' ) ) {
	die( __( "You don't have adequate permission to view entries.", 'gravityforms' ) );
}

add_action( 'gform_print_entry_content', 'gform_default_entry_content', 10, 3 );
function gform_default_entry_content( $form, $entry, $entry_ids ) {

	$page_break = rgget( 'page_break' ) ? 'print-page-break' : false;

	/**
	 * @todo Review use of the form tag. The entry detail markup does not use inputs so they may no longer be needed.
	 *
	 * Previous comment: Separate each entry inside a form element so radio buttons don't get treated as a single group across multiple entries.
	 */
	echo '<form>';

	GFEntryDetail::lead_detail_grid( $form, $entry );

	echo '</form>';

	$print_entry_notes = rgget( 'notes' ) === '1';

	/**
	 * Allows printing of entry notes to be overridden.
	 *
	 * @since 2.4.17
	 *
	 * @param bool  $print_entry_notes Indicates if printing of notes was enabled via the entry list or detail pages.
	 * @param array $entry             The entry currently being printed.
	 * @param array $form              The form which created the current entry.
	 */
	$print_entry_notes = apply_filters( 'gform_print_entry_notes', $print_entry_notes, $entry, $form );

	if ( $print_entry_notes ) {
		$notes = GFFormsModel::get_lead_notes( $entry['id'] );
		if ( ! empty( $notes ) ) {
			GFEntryDetail::notes_grid( $notes, false );
		}
	}

	// Output entry divider/page break.
	if ( array_search( $entry['id'], $entry_ids ) < count( $entry_ids ) - 1 ) {
		echo '<div class="print-hr ' . $page_break . '"></div>';
	}

}

// Get form ID.
$form_id = absint( rgget( 'fid' ) );

// Get submitted entry IDs.
$entry_ids = rgget( 'lid' );

// If no entry IDs were defined, get all entry IDs.
if ( 0 == $entry_ids ) {

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

	// Prepare sorting.
	$sorting = array();

	if ( rgget( 'orderby' ) ) {

		// Add column to order by.
		$sorting['key'] = sanitize_text_field( rgget( 'orderby' ) );

		// Get sorting direction.
		$direction = rgget( 'order' );
		$direction = strtoupper( $direction );
		$direction = in_array( $direction, array( 'ASC', 'DESC' ) ) ? $direction : 'ASC';

		// Add sorting direction to array.
		$sorting['direction'] = $direction;

	}

	// Initialize paging array.
	$paging = array();

	/**
	 * Allow the entry list search criteria to be overridden.
	 *
	 * @deprecated 2.3 Use "gform_search_criteria_entry_list" instead.
	 *
	 * @since  1.9.14.30
	 *
	 * @param array $search_criteria An array containing the search criteria.
	 * @param int   $form_id         The ID of the current form.
	 */
	$search_criteria = gf_apply_filters( array( 'gform_search_criteria_entry_list', $form_id ), $search_criteria, $form_id );

	/**
	 * Filter the arguments that will be used to fetch entries for display on the Entry List view.
	 *
	 * @since 2.2.3.4
	 *
	 * @param array $args {
	 *
	 *     Array of arguments that will be passed to GFAPI::get_entries() to fetch the entries to be displayed.
	 *
	 *     @var int   $form_id         The form ID for which entries will be loaded.
	 *     @var array $search_criteria An array of search critiera that will be used to filter entries.
	 *     @var array $sorting         An array containing properties that specify how the entries will be sorted.
	 *     @var array $paging          An array containing properties that specify how the entries will be paginated.
	 * }
	 */
	$args = gf_apply_filters( array( 'gform_get_entries_args_entry_list', $form_id ), compact( 'form_id', 'search_criteria', 'sorting', 'paging' ) );

	$entry_ids = GFAPI::get_entry_ids( $args['form_id'], $args['search_criteria'], $args['sorting'], $args['paging'] );

} else {

	// Convert entry IDs to array.
	$entry_ids = explode( ',', $entry_ids );

}

// If no form ID or entry IDs are set, exit.
if ( empty( $form_id ) || empty( $entry_ids ) ) {
	die( esc_html__( 'Form Id and Entry Id are required parameters.', 'gravityforms' ) );
}

// Get form.
$form = GFAPI::get_form( $form_id );

// Get script/styling extension.
$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
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
			<?php echo count( $entry_ids ) > 1 ? esc_html__( 'Bulk Print', 'gravityforms' ) : esc_html__( 'Entry # ', 'gravityforms' ) . absint( $entry_ids[0] ); ?>
		</title>
		<link rel='stylesheet' href='<?php echo GFCommon::get_base_url() ?>/css/print<?php echo $min; ?>.css' type='text/css' />
		<?php

			/**
			 * Determines if the Gravity Forms styles should be printed
			 *
			 * @since 1.7
			 *
			 * @param bool  false Set to true if style should be printed.
			 * @param array $form The Form object
			 */
			$styles = apply_filters( 'gform_print_styles', false, $form );

			// If styles were found, display them.
			if ( ! empty( $styles ) ) {
				wp_print_styles( $styles );
			}

			/**
			 * Disable auto-print when the Print Entry view has fully loaded.
			 *
			 * @since 1.9.14.16
			 *
			 * @param bool  false Auto print is enabled by default. Set to true to disable.
			 * @param array $form Current Form object.
			 *
			 * @see https://gist.github.com/spivurno/e7d1e4563986b3bc5ac4
			 */
			$auto_print = gf_apply_filters( array( 'gform_print_entry_disable_auto_print', $form['id'] ), false, $form ) ? '' : 'onload="window.print();"';

		?>
	</head>
	<body <?php echo $auto_print; ?>>

		<div id="print_preview_hdr" style="display:none">
			<div>
				<span class="actionlinks"><a href="#" onclick="window.print();" onkeypress="window.print();" class="header-print-link">print this page</a> | <a href="javascript:window.close()" class="close_window"><?php esc_html_e( 'close window', 'gravityforms' ) ?></a></span><?php esc_html_e( 'Print Preview', 'gravityforms' ) ?>
			</div>
		</div>
		<div id="view-container">
		<?php

			require_once( GFCommon::get_base_path() . '/entry_detail.php' );

			// Loop through entry IDs.
			foreach ( $entry_ids as $entry_id ) {

				// Get entry.
				$entry = GFAPI::get_entry( $entry_id );

				/**
				 * Adds actions to the entry printing view's header
				 *
				 * @since 1.5.2.8
				 *
				 * @param array $form  The Form object
				 * @param array $entry The Entry object
				 */
				do_action( 'gform_print_entry_header', $form, $entry );

				/**
				 * Output content for the current entry when looping through entries on the Print Entry view.
				 *
				 * @since 1.9.14.16
				 *
				 * @param array $form      Current Form object.
				 * @param array $entry     Current Entry object.
				 * @param array $entry_ids Array of entry IDs to be printed.
				 *
				 * @see https://gist.github.com/spivurno/d617ce30b47d8a8bc8a8
				 */
				do_action( 'gform_print_entry_content', $form, $entry, $entry_ids );

				/**
				 * Adds actions to the Print Entry page footer
				 *
				 * @since 1.5.2.8
				 *
				 * @param array $form  The Form object
				 * @param array $entry The Entry object
				 */
				do_action( 'gform_print_entry_footer', $form, $entry );

			}

		?>
		</div>
	</body>
</html>
