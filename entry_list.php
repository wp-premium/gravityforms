<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFEntryList {
	public static function all_leads_page() {

		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		$forms = RGFormsModel::get_forms( null, 'title' );
		$id    = RGForms::get( 'id' );

		if ( sizeof( $forms ) == 0 ) {
			?>
			<div style="margin:50px 0 0 10px;">
				<?php echo sprintf( __( "You don't have any active forms. Let's go %screate one%s", 'gravityforms' ), '<a href="?page=gf_new_form">', '</a>' ); ?>
			</div>
		<?php
		} else {
			if ( empty( $id ) ) {
				$id = $forms[0]->id;
			}

			self::leads_page( $id );
		}
	}

	public static function leads_page( $form_id ) {
		global $wpdb;

		//quit if version of wp is not supported
		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		$form_id = absint( $form_id );

		echo GFCommon::get_remote_message();
		$action     = RGForms::post( 'action' );
		$filter     = rgget( 'filter' );
		$search     = stripslashes( rgget( 's' ) );
		$page_index = empty( $_GET['paged'] ) ? 0 : intval( $_GET['paged'] ) - 1;
		$star       = $filter == 'star' ? 1 : null;
		$read       = $filter == 'unread' ? 0 : null;
		$status     = in_array( $filter, array( 'trash', 'spam' ) ) ? $filter : 'active';
		$form       = RGFormsModel::get_form_meta( $form_id );

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
			$val            = stripslashes( rgget( 's' ) );
			$strpos_row_key = strpos( $search_field_id, '|' );
			if ( $strpos_row_key !== false ) { //multi-row likert
				$key_array = explode( '|', $search_field_id );
				$key       = $key_array[0];
				$val       = $key_array[1] . ':' . $val;
			}
			if ( 'entry_id' == $key ) {
				$key = 'id';
			}
			$filter_operator = empty( $search_operator ) ? 'is' : $search_operator;

			$field = GFFormsModel::get_field( $form, $key );
			if ( $field ) {
				$input_type = GFFormsModel::get_input_type( $field );
				if ( $field->type == 'product' && in_array( $input_type, array( 'radio', 'select' ) ) ) {
					$filter_operator = 'contains';
				}
			}

			$search_criteria['field_filters'][] = array(
				'key'      => $key,
				'operator' => $filter_operator,
				'value'    => $val,
			);


		}

		$update_message = '';
		switch ( $action ) {
			case 'delete' :
				check_admin_referer( 'gforms_entry_list', 'gforms_entry_list' );
				$lead_id = $_POST['action_argument'];
				if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
					RGFormsModel::delete_lead( $lead_id );
					$update_message = __( 'Entry deleted.', 'gravityforms' );
				} else {
					$update_message = __( "You don't have adequate permission to delete entries.", 'gravityforms' );
				}

				break;

			case 'bulk':
				check_admin_referer( 'gforms_entry_list', 'gforms_entry_list' );

				$bulk_action = ! empty( $_POST['bulk_action'] ) ? $_POST['bulk_action'] : $_POST['bulk_action2'];
				$select_all  = rgpost( 'all_entries' );
				$leads       = empty( $select_all ) ? $_POST['lead'] : GFFormsModel::search_lead_ids( $form_id, $search_criteria );

				$entry_count = count( $leads ) > 1 ? sprintf( __( '%d entries', 'gravityforms' ), count( $leads ) ) : __( '1 entry', 'gravityforms' );

				switch ( $bulk_action ) {
					case 'delete':
						if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
							RGFormsModel::delete_leads( $leads );
							$update_message = sprintf( __( '%s deleted.', 'gravityforms' ), $entry_count );
						} else {
							$update_message = __( "You don't have adequate permission to delete entries.", 'gravityforms' );
						}
						break;

					case 'trash':
						RGFormsModel::update_leads_property( $leads, 'status', 'trash' );
						$update_message = sprintf( __( '%s moved to Trash.', 'gravityforms' ), $entry_count );
						break;

					case 'restore':
						RGFormsModel::update_leads_property( $leads, 'status', 'active' );
						$update_message = sprintf( __( '%s restored from the Trash.', 'gravityforms' ), $entry_count );
						break;

					case 'unspam':
						RGFormsModel::update_leads_property( $leads, 'status', 'active' );
						$update_message = sprintf( __( '%s restored from the spam.', 'gravityforms' ), $entry_count );
						break;

					case 'spam':
						RGFormsModel::update_leads_property( $leads, 'status', 'spam' );
						$update_message = sprintf( __( '%s marked as spam.', 'gravityforms' ), $entry_count );
						break;

					case 'mark_read':
						RGFormsModel::update_leads_property( $leads, 'is_read', 1 );
						$update_message = sprintf( __( '%s marked as read.', 'gravityforms' ), $entry_count );
						break;

					case 'mark_unread':
						RGFormsModel::update_leads_property( $leads, 'is_read', 0 );
						$update_message = sprintf( __( '%s marked as unread.', 'gravityforms' ), $entry_count );
						break;

					case 'add_star':
						RGFormsModel::update_leads_property( $leads, 'is_starred', 1 );
						$update_message = sprintf( __( '%s starred.', 'gravityforms' ), $entry_count );
						break;

					case 'remove_star':
						RGFormsModel::update_leads_property( $leads, 'is_starred', 0 );
						$update_message = sprintf( __( '%s unstarred.', 'gravityforms' ), $entry_count );
						break;
				}
				break;

			case 'change_columns':
				check_admin_referer( 'gforms_entry_list', 'gforms_entry_list' );
				$columns = GFCommon::json_decode( stripslashes( $_POST['grid_columns'] ), true );
				RGFormsModel::update_grid_column_meta( $form_id, $columns );
				break;
		}

		if ( rgpost( 'button_delete_permanently' ) ) {
			if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
				RGFormsModel::delete_leads_by_form( $form_id, $filter );
			}
		}

		$sort_field      = empty( $_GET['sort'] ) ? 0 : $_GET['sort'];
		$sort_direction  = empty( $_GET['dir'] ) ? 'DESC' : $_GET['dir'];

		$sort_field_meta = RGFormsModel::get_field( $form, $sort_field );
		$is_numeric      = $sort_field_meta['type'] == 'number';

		$page_size        = apply_filters( 'gform_entry_page_size', apply_filters( "gform_entry_page_size_{$form_id}", 20, $form_id ), $form_id );
		$first_item_index = $page_index * $page_size;

		if ( ! empty( $sort_field ) ) {
			$sorting = array( 'key' => $_GET['sort'], 'direction' => $sort_direction, 'is_numeric' => $is_numeric );
		} else {
			$sorting = array();
		}

		$paging      = array( 'offset' => $first_item_index, 'page_size' => $page_size );
		$total_count = 0;

		$leads = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging, $total_count );

		$summary           = RGFormsModel::get_form_counts( $form_id );
		$active_lead_count = $summary['total'];
		$unread_count      = $summary['unread'];
		$starred_count     = $summary['starred'];
		$spam_count        = $summary['spam'];
		$trash_count       = $summary['trash'];

		$columns = RGFormsModel::get_grid_columns( $form_id, true );

		$search_qs                  = empty( $search ) ? '' : '&s=' . esc_attr( urlencode( $search ) );
		$sort_qs                    = empty( $sort_field ) ? '' : '&sort=' . esc_attr( $sort_field );
		$dir_qs                     = empty( $sort_direction ) ? '' : '&dir=' . esc_attr( $sort_direction );
		$star_qs                    = $star !== null ? '&star=' . esc_attr( $star ) : '';
		$read_qs                    = $read !== null ? '&read=' . esc_attr( $read ) : '';
		$filter_qs                  = '&filter=' . esc_attr( $filter );
		$search_field_id_qs         = ! isset( $_GET['field_id'] ) ? '' : '&field_id=' . esc_attr( $search_field_id );
		$search_operator_urlencoded = urlencode( $search_operator );
		$search_operator_qs         = empty( $search_operator_urlencoded ) ? '' : '&operator=' . esc_attr( $search_operator_urlencoded );

		$display_total = ceil( $total_count / $page_size );
		$page_links    = paginate_links(
			array(
				'base'      => admin_url( 'admin.php' ) . "?page=gf_entries&view=entries&id=$form_id&%_%" . $search_qs . $sort_qs . $dir_qs . $star_qs . $read_qs . $filter_qs . $search_field_id_qs . $search_operator_qs,
				'format'    => 'paged=%#%',
				'prev_text' => __( '&laquo;', 'gravityforms' ),
				'next_text' => __( '&raquo;', 'gravityforms' ),
				'total'     => $display_total,
				'current'   => $page_index + 1,
				'show_all'  => false,
			)
		);

		wp_print_styles( array( 'thickbox' ) );

		$field_filters = GFCommon::get_field_filter_settings( $form );

		$init_field_id       = empty( $search_field_id ) ? 0 : $search_field_id;
		$init_field_operator = empty( $search_operator ) ? 'contains' : $search_operator;
		$init_filter_vars = array(
			'mode'    => 'off',
			'filters' => array(
				array(
					'field'    => $init_field_id,
					'operator' => $init_field_operator,
					'value'    => $search,
				),
			)
		);

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		?>

		<script type="text/javascript">

		var messageTimeout = false,
			gformFieldFilters = <?php echo json_encode( $field_filters ) ?>,
			gformInitFilter = <?php echo json_encode( $init_filter_vars ) ?>

				function ChangeColumns(columns) {
					jQuery("#action").val("change_columns");
					jQuery("#grid_columns").val(jQuery.toJSON(columns));
					tb_remove();
					jQuery("#lead_form")[0].submit();
				}

		function Search(sort_field_id, sort_direction, form_id, search, star, read, filter, field_id, operator) {
			var search_qs = search == "" ? "" : "&s=" + encodeURIComponent(search);
			var star_qs = star == "" ? "" : "&star=" + star;
			var read_qs = read == "" ? "" : "&read=" + read;
			var filter_qs = filter == "" ? "" : "&filter=" + filter;
			var field_id_qs = field_id == "" ? "" : "&field_id=" + field_id;
			var operator_qs = operator == "" ? "" : "&operator=" + operator;

			var location = "?page=gf_entries&view=entries&id=" + form_id + "&sort=" + sort_field_id + "&dir=" + sort_direction + search_qs + star_qs + read_qs + filter_qs + field_id_qs + operator_qs;
			document.location = location;
		}

		function ToggleStar(img, lead_id, filter) {
			var is_starred = img.src.indexOf("star1.png") >= 0;
			if (is_starred)
				img.src = img.src.replace("star1.png", "star0.png");
			else
				img.src = img.src.replace("star0.png", "star1.png");

			jQuery("#lead_row_" + lead_id).toggleClass("lead_starred");
			//if viewing the starred entries, hide the row and adjust the paging counts
			if (filter == "star") {
				var title = jQuery("#lead_row_" + lead_id);
				title.css("display", 'none');
				UpdatePagingCounts(1);
			}

			UpdateCount("star_count", is_starred ? -1 : 1);

			UpdateLeadProperty(lead_id, "is_starred", is_starred ? 0 : 1);
		}

		function ToggleRead(lead_id, filter) {
			var title = jQuery("#lead_row_" + lead_id);
			var marking_read = title.hasClass("lead_unread");

			jQuery("#mark_read_" + lead_id).css("display", marking_read ? "none" : "inline");
			jQuery("#mark_unread_" + lead_id).css("display", marking_read ? "inline" : "none");
			jQuery("#is_unread_" + lead_id).css("display", marking_read ? "inline" : "none");
			title.toggleClass("lead_unread");
			//if viewing the unread entries, hide the row and adjust the paging counts
			if (filter == "unread") {
				title.css("display", "none");
				UpdatePagingCounts(1);
			}

			UpdateCount("unread_count", marking_read ? -1 : 1);
			UpdateLeadProperty(lead_id, "is_read", marking_read ? 1 : 0);
		}

		function UpdateLeadProperty(lead_id, name, value) {
			var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' )?>");
			mysack.execute = 1;
			mysack.method = 'POST';
			mysack.setVar("action", "rg_update_lead_property");
			mysack.setVar("rg_update_lead_property", "<?php echo wp_create_nonce( 'rg_update_lead_property' ) ?>");
			mysack.setVar("lead_id", lead_id);
			mysack.setVar("name", name);
			mysack.setVar("value", value);
			mysack.onError = function () {
				alert('<?php echo esc_js( __( 'Ajax error while setting lead property', 'gravityforms' ) ) ?>')
			};
			mysack.runAJAX();

			return true;
		}

		function UpdateCount(element_id, change) {
			var element = jQuery("#" + element_id);
			var count = parseInt(element.html()) + change
			element.html(count + "");
		}

		function UpdatePagingCounts(change) {
			//update paging header/footer Displaying # - # of #, use counts from header, no need to use footer since they are the same, just update footer paging with header info
			var paging_range_max_header = jQuery("#paging_range_max_header");
			var paging_range_max_footer = jQuery("#paging_range_max_footer");
			var range_change_max = parseInt(paging_range_max_header.html()) - change;
			var paging_total_header = jQuery("#paging_total_header");
			var paging_total_footer = jQuery("#paging_total_footer");
			var total_change = parseInt(paging_total_header.html()) - change;
			var paging_range_min_header = jQuery("#paging_range_min_header");
			var paging_range_min_footer = jQuery("#paging_range_min_footer");
			//if min and max are the same, this is the last entry item on the page, clear out the displaying # - # of # text
			if (parseInt(paging_range_min_header.html()) == parseInt(paging_range_max_header.html())) {
				var paging_header = jQuery("#paging_header");
				paging_header.html("");
				var paging_footer = jQuery("#paging_footer");
				paging_footer.html("");
			}
			else {
				paging_range_max_header.html(range_change_max + "");
				paging_range_max_footer.html(range_change_max + "");
				paging_total_header.html(total_change + "");
				paging_total_footer.html(total_change + "");
			}
			gformVars.countAllEntries = gformVars.countAllEntries - change;
			setSelectAllText();
		}

		function DeleteLead(lead_id) {
			jQuery("#action").val("delete");
			jQuery("#action_argument").val(lead_id);
			jQuery("#lead_form")[0].submit();
			return true;
		}

		function handleBulkApply(actionElement) {

			var action = jQuery("#" + actionElement).val();
			var defaultModalOptions = '';
			var leadIds = getLeadIds();

			if (leadIds.length == 0) {
				alert('<?php _e( 'Please select at least one entry.', 'gravityforms' ); ?>');
				return false;
			}

			switch (action) {

				case 'resend_notifications':
					resetResendNotificationsUI();
					tb_show('<?php _e( 'Resend Notifications', 'gravityforms' ); ?>', '#TB_inline?width=350&amp;inlineId=notifications_modal_container', '');
					return false;
					break;

				case 'print':
					resetPrintUI();
					tb_show('<?php _e( 'Print Entries', 'gravityforms' ); ?>', '#TB_inline?width=350&amp;height=250&amp;inlineId=print_modal_container', '');
					return false;
					break;

				default:
					jQuery('#action').val('bulk');
			}

		}

		function getLeadIds() {
			var all = jQuery("#all_entries").val();
			//compare string, the boolean isn't correct, even when casting to a boolean the 0 is set to true
			if (all == "1")
				return 0;

			var leads = jQuery(".check-column input[name='lead[]']:checked");
			var leadIds = new Array();

			jQuery(leads).each(function (i) {
				leadIds[i] = jQuery(leads[i]).val();
			});

			return leadIds;
		}

		function BulkResendNotifications() {


			var selectedNotifications = new Array();
			jQuery(".gform_notifications:checked").each(function () {
				selectedNotifications.push(jQuery(this).val());
			});
			var leadIds = getLeadIds();

			var sendTo = jQuery('#notification_override_email').val();

			if (selectedNotifications.length <= 0) {
				displayMessage("<?php _e( 'You must select at least one type of notification to resend.', 'gravityforms' ); ?>", "error", "#notifications_container");
				return;
			}

			jQuery('#please_wait_container').fadeIn();

			jQuery.post(ajaxurl, {
					action                 : "gf_resend_notifications",
					gf_resend_notifications: '<?php echo wp_create_nonce( 'gf_resend_notifications' ); ?>',
					notifications          : jQuery.toJSON(selectedNotifications),
					sendTo                 : sendTo,
					leadIds                : leadIds,
					filter                 : '<?php echo esc_js( rgget( 'filter' ) ) ?>',
					search                 : '<?php echo esc_js( rgget( 's' ) ) ?>',
					operator               : '<?php echo esc_js( rgget( 'operator' ) ) ?>',
					fieldId                : '<?php echo esc_js( rgget( 'field_id' ) ) ?>',
					formId                 : '<?php echo absint( $form['id'] ); ?>'
				},
				function (response) {

					jQuery('#please_wait_container').hide();

					if (response) {
						displayMessage(response, 'error', '#notifications_container');
					} else {
						var message = '<?php _e( 'Notifications for %s were resent successfully.', 'gravityforms' ); ?>';
						var c = leadIds == 0 ? gformVars.countAllEntries : leadIds.length;
						displayMessage(message.replace('%s', c + ' ' + getPlural(c, '<?php _e( 'entry', 'gravityforms' ); ?>', '<?php _e( 'entries', 'gravityforms' ); ?>')), "updated", "#lead_form");
						closeModal(true);
					}

				}
			);

		}

		function resetResendNotificationsUI() {

			jQuery('#notification_admin, #notification_user').attr('checked', false);
			jQuery('#notifications_container .message, #notifications_override_settings').hide();

		}

		function BulkPrint() {

			var leadIds = getLeadIds();
			if (leadIds != 0)
				leadIds = leadIds.join(',');
			var leadsQS = '&lid=' + leadIds;
			var notesQS = jQuery('#gform_print_notes').is(':checked') ? '&notes=1' : '';
			var pageBreakQS = jQuery('#gform_print_page_break').is(':checked') ? '&page_break=1' : '';
			var filterQS = '&filter=<?php echo esc_js( rgget( 'filter' ) ) ?>';
			var searchQS = '&s=<?php echo esc_js( rgget( 's' ) ) ?>';
			var searchFieldIdQS = '&field_id=<?php echo esc_js( rgget( 'field_id' ) ) ?>';
			var searchOperatorQS = '&operator=<?php echo esc_js( rgget( 'operator' ) ) ?>';

			var url = '<?php echo trailingslashit( site_url() ) ?>?gf_page=print-entry&fid=<?php echo absint( $form['id'] ) ?>' + leadsQS + notesQS + pageBreakQS + filterQS + searchQS + searchFieldIdQS + searchOperatorQS;
			window.open(url, 'printwindow');

			closeModal(true);
			hideMessage('#lead_form', false);
		}

		function resetPrintUI() {

			jQuery('#print_options input[type="checkbox"]').attr('checked', false);

		}

		function displayMessage(message, messageClass, container) {

			hideMessage(container, true);

			var messageBox = jQuery('<div class="message ' + messageClass + '" style="display:none;"><p>' + message + '</p></div>');
			jQuery(messageBox).prependTo(container).slideDown();

			if (messageClass == 'updated')
				messageTimeout = setTimeout(function () {
					hideMessage(container, false);
				}, 10000);

		}

		function hideMessage(container, messageQueued) {

			if (messageTimeout)
				clearTimeout(messageTimeout);

			var messageBox = jQuery(container).find('.message');

			if (messageQueued)
				jQuery(messageBox).remove();
			else
				jQuery(messageBox).slideUp(function () {
					jQuery(this).remove();
				});

		}

		function closeModal(isSuccess) {

			if (isSuccess)
				jQuery('.check-column input[type="checkbox"]').attr('checked', false);

			tb_remove();

		}

		function getPlural(count, singular, plural) {
			return count > 1 ? plural : singular;
		}

		function toggleNotificationOverride(isInit) {

			if (isInit)
				jQuery('#notification_override_email').val('');

			if (jQuery(".gform_notifications:checked").length > 0) {
				jQuery('#notifications_override_settings').slideDown();
			} else {
				jQuery('#notifications_override_settings').slideUp(function () {
					jQuery('#notification_override_email').val('');
				});
			}

		}

		// Select All

		var gformStrings = {
			"allEntriesOnPageAreSelected": "<?php printf( __( 'All %s{0}%s entries on this page are selected.', 'gravityforms' ), '<strong>', '</strong>' ) ?>",
			"selectAll"                  : "<?php printf( __(  'Select all %s{0}%s entries.', 'gravityforms' ), '<strong>', '</strong>' ) ?>",
			"allEntriesSelected"         : "<?php printf( __( 'All %s{0}%s entries have been selected.', 'gravityforms' ), '<strong>', '</strong>' ) ?>",
			"clearSelection"             : "<?php _e( 'Clear selection', 'gravityforms' ) ?>"
		}

		var gformVars = {
			"countAllEntries": <?php echo intval( $total_count ); ?>,
			"perPage"        : <?php echo intval( $page_size ); ?>
		}

		function setSelectAllText() {
			var tr = getSelectAllText();
			jQuery("#gform-select-all-message td").html(tr);
		}

		function getSelectAllText() {
			var count;
			count = jQuery("#gf_entry_list tr:visible:not('#gform-select-all-message')").length;
			return gformStrings.allEntriesOnPageAreSelected.format(count) + " <a href='javascript:void(0)' onclick='selectAllEntriesOnAllPages();'>" + gformStrings.selectAll.format(gformVars.countAllEntries) + "</a>";
		}

		function getSelectAllTr() {
			var t = getSelectAllText();
			var colspan = jQuery("#gf_entry_list").find("tr:first td").length + 1;
			return "<tr id='gform-select-all-message' style='display:none;background-color:lightyellow;text-align:center;'><td colspan='{0}'>{1}</td></tr>".format(colspan, t);
		}
		function toggleSelectAll(visible) {
			if (gformVars.countAllEntries <= gformVars.perPage) {
				jQuery('#gform-select-all-message').hide();
				return;
			}

			if (visible)
				setSelectAllText();
			jQuery('#gform-select-all-message').toggle(visible);
		}


		function clearSelectAllEntries() {
			jQuery(".check-column input[type=checkbox]").prop('checked', false);
			clearSelectAllMessage();
		}

		function clearSelectAllMessage() {
			jQuery("#all_entries").val("0");
			jQuery("#gform-select-all-message").hide();
			jQuery("#gform-select-all-message td").html('');
		}

		function selectAllEntriesOnAllPages() {
			var trHtmlClearSelection;
			trHtmlClearSelection = gformStrings.allEntriesSelected.format(gformVars.countAllEntries) + " <a href='javascript:void(0);' onclick='clearSelectAllEntries();'>" + gformStrings.clearSelection + "</a>";
			jQuery("#all_entries").val("1");
			jQuery("#gform-select-all-message td").html(trHtmlClearSelection);
		}

		function initSelectAllEntries() {

			if (gformVars.countAllEntries > gformVars.perPage) {
				var tr = getSelectAllTr();
				jQuery("#gf_entry_list").prepend(tr);
				jQuery(".headercb").click(function () {
					toggleSelectAll(jQuery(this).prop('checked'));
				});
				jQuery("#gf_entry_list .check-column input[type=checkbox]").click(function () {
					clearSelectAllMessage();
				})
			}
		}

		String.prototype.format = function () {
			var args = arguments;
			return this.replace(/{(\d+)}/g, function (match, number) {
				return typeof args[number] != 'undefined' ? args[number] : match;
			});
		};

		// end Select All

		jQuery(document).ready(function () {


			var action = '<?php echo esc_js( $action ); ?>';
			var message = '<?php echo esc_js( $update_message ); ?>';
			if (action && message)
				displayMessage(message, 'updated', '#lead_form');

			var list = jQuery("#gf_entry_list").wpList({ alt: '<?php echo esc_js( __( 'Entry List', 'gravityforms' ) ) ?>'});
			list.bind('wpListDelEnd', function (e, s, list) {

				var currentStatus = "<?php echo $filter == 'trash' || $filter == 'spam' ? esc_js( $filter ) : 'active' ?>";
				var filter = "<?php echo esc_js( $filter ); ?>";
				var movingTo = "active";
				if (s.data.status == "trash")
					movingTo = "trash";
				else if (s.data.status == "spam")
					movingTo = "spam";
				else if (s.data.status == "delete")
					movingTo = "delete";

				var id = s.data.entry;
				var title = jQuery("#lead_row_" + id);
				var isUnread = title.hasClass("lead_unread");
				var isStarred = title.hasClass("lead_starred");

				if (movingTo != "delete") {
					//Updating All count
					var allCount = currentStatus == "active" ? -1 : 1;
					UpdateCount("all_count", allCount);

					//Updating Unread count
					if (isUnread) {
						var unreadCount = currentStatus == "active" ? -1 : 1;
						UpdateCount("unread_count", unreadCount);
					}

					//Updating Starred count
					if (isStarred) {
						var starCount = currentStatus == "active" ? -1 : 1;
						UpdateCount("star_count", starCount);
					}
				}

				//Updating Spam count
				if (currentStatus == "spam" || movingTo == "spam") {
					var spamCount = movingTo == "spam" ? 1 : -1;
					UpdateCount("spam_count", spamCount);
					//adjust paging counts
					if (filter == "spam") {
						UpdatePagingCounts(1);
					}
					else {
						UpdatePagingCounts(spamCount);
					}
				}

				//Updating trash count
				if (currentStatus == "trash" || movingTo == "trash") {
					var trashCount = movingTo == "trash" ? 1 : -1;
					UpdateCount("trash_count", trashCount);
					//adjust paging counts
					if (filter == "trash") {
						UpdatePagingCounts(1);
					}
					else {
						UpdatePagingCounts(trashCount);
					}
				}

			});

			initSelectAllEntries();

			jQuery('#entry_filters').gfFilterUI(gformFieldFilters, gformInitFilter, false);
			jQuery("#entry_filters").on("keypress", ".gform-filter-value", (function (event) {
				if (event.keyCode == 13) {
					Search('<?php echo esc_js( $sort_field ); ?>', '<?php echo esc_js( $sort_direction ); ?>', <?php echo absint( $form_id ) ?>, jQuery('.gform-filter-value').val(), '<?php echo esc_js( $star ); ?>', '<?php echo esc_js( $read ); ?>', '<?php echo esc_js( $filter ); ?>', jQuery('.gform-filter-field').val(), jQuery('.gform-filter-operator').val());
					event.preventDefault();
				}
			}));
		});


		</script>
		<link rel="stylesheet" href="<?php echo GFCommon::get_base_url() ?>/css/admin<?php echo $min; ?>.css" type="text/css" />
		<style>
			/*#TB_window { height: 400px !important; }
			#TB_ajaxContent[style] { height: 370px !important; }*/
			.lead_unread a, .lead_unread td {
				font-weight: bold;
			}

			.lead_spam_trash a, .lead_spam_trash td {
				font-weight: normal;
			}

			.row-actions a {
				font-weight: normal;
			}

			.entry_nowrap {
				overflow: hidden;
				white-space: nowrap;
			}

			.message {
				margin: 15px 0 0 !important;
			}

			.gform-filter-operator {
				width: 100px
			}
		</style>


		<div class="wrap <?php echo GFCommon::get_browser_class() ?>">
		<h2 class="gf_admin_page_title">
			<span><?php _e( 'Entries', 'gravityforms' ) ?></span><span class="gf_admin_page_subtitle"><span class="gf_admin_page_formid">ID: <?php echo absint( $form['id'] ); ?></span><span class="gf_admin_page_formname"><?php _e( 'Form Name', 'gravityforms' ) ?>: <?php echo esc_html( $form['title'] ); ?></span></span>
		</h2>

		<?php RGForms::top_toolbar() ?>

		<form id="lead_form" method="post">
		<?php wp_nonce_field( 'gforms_entry_list', 'gforms_entry_list' ) ?>

		<input type="hidden" value="" name="grid_columns" id="grid_columns" />
		<input type="hidden" value="" name="action" id="action" />
		<input type="hidden" value="" name="action_argument" id="action_argument" />
		<input type="hidden" value="" name="all_entries" id="all_entries" />

		<ul class="subsubsub">
			<li>
				<a class="<?php echo empty( $filter ) ? 'current' : '' ?>" href="?page=gf_entries&view=entries&id=<?php echo absint( $form_id ) ?>"><?php _ex( 'All', 'Entry List', 'gravityforms' ); ?>
					<span class="count">(<span id="all_count"><?php echo $active_lead_count ?></span>)</span></a> |
			</li>
			<li>
				<a class="<?php echo $read !== null ? 'current' : '' ?>" href="?page=gf_entries&view=entries&id=<?php echo absint( $form_id ) ?>&filter=unread"><?php _ex( 'Unread', 'Entry List', 'gravityforms' ); ?>
					<span class="count">(<span id="unread_count"><?php echo $unread_count ?></span>)</span></a> |
			</li>
			<li>
				<a class="<?php echo $star !== null ? 'current' : '' ?>" href="?page=gf_entries&view=entries&id=<?php echo absint( $form_id ) ?>&filter=star"><?php _ex( 'Starred', 'Entry List', 'gravityforms' ); ?>
					<span class="count">(<span id="star_count"><?php echo $starred_count ?></span>)</span></a> |
			</li>
			<?php
			if ( GFCommon::spam_enabled( $form_id ) ) {
				?>
				<li>
					<a class="<?php echo $filter == 'spam' ? 'current' : '' ?>" href="?page=gf_entries&view=entries&id=<?php echo absint( $form_id ) ?>&filter=spam"><?php _e( 'Spam', 'gravityforms' ); ?>
						<span class="count">(<span id="spam_count"><?php echo esc_html( $spam_count ); ?></span>)</span></a> |
				</li>
			<?php
			}
			?>
			<li>
				<a class="<?php echo $filter == 'trash' ? 'current' : '' ?>" href="?page=gf_entries&view=entries&id=<?php echo absint( $form_id ) ?>&filter=trash"><?php _e( 'Trash', 'gravityforms' ); ?>
					<span class="count">(<span id="trash_count"><?php echo esc_html( $trash_count ); ?></span>)</span></a></li>
		</ul>
		<div style="margin-top:12px;float:right;">
			<a style="float:right;" class="button" id="lead_search_button" href="javascript:Search('<?php echo esc_js( $sort_field ); ?>', '<?php echo esc_js( $sort_direction ) ?>', <?php echo absint( $form_id ); ?>, jQuery('.gform-filter-value').val(), '<?php echo esc_js( $star ); ?>', '<?php echo esc_js( $read ); ?>', '<?php echo $filter ?>', jQuery('.gform-filter-field').val(), jQuery('.gform-filter-operator').val());"><?php _e( 'Search', 'gravityforms' ) ?></a>

			<div id="entry_filters" style="float:right"></div>
		</div>
		<div class="tablenav">

			<div class="alignleft actions" style="padding:8px 0 7px 0;">
				<label class="hidden" for="bulk_action"> <?php _e( 'Bulk action', 'gravityforms' ) ?></label>
				<select name="bulk_action" id="bulk_action">
					<option value=''><?php _e( ' Bulk action ', 'gravityforms' ) ?></option>
					<?php
					switch ( $filter ) {
						case 'trash' :
							?>
							<option value='restore'><?php _e( 'Restore', 'gravityforms' ) ?></option>
							<?php
							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
								?>
								<option value='delete'><?php _e( 'Delete Permanently', 'gravityforms' ) ?></option>
							<?php
							}
							break;
						case 'spam' :
							?>
							<option value='unspam'><?php _e( 'Not Spam', 'gravityforms' ) ?></option>
							<?php
							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
								?>
								<option value='delete'><?php _e( 'Delete Permanently', 'gravityforms' ) ?></option>
							<?php
							}
							break;

						default:
							?>
								<option value='mark_read'><?php _e( 'Mark as Read', 'gravityforms' ) ?></option>
								<option value='mark_unread'><?php _e( 'Mark as Unread', 'gravityforms' ) ?></option>
								<option value='add_star'><?php _e( 'Add Star', 'gravityforms' ) ?></option>
								<option value='remove_star'><?php _e( 'Remove Star', 'gravityforms' ) ?></option>
								<option value='resend_notifications'><?php _e( 'Resend Notifications', 'gravityforms' ) ?></option>
								<option value='print'><?php _e( 'Print', 'gravityforms' ) ?></option>

							<?php
							if ( GFCommon::spam_enabled( $form_id ) ) {
								?>
								<option value='spam'><?php _e( 'Spam', 'gravityforms' ) ?></option>
							<?php
							}

							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
								?>
								<option value='trash'><?php _e( 'Trash', 'gravityforms' ) ?></option>
							<?php
							}
					}?>
				</select>
				<?php
				$apply_button = '<input type="submit" class="button" value="' . __( 'Apply', 'gravityforms' ) . '" onclick="return handleBulkApply(\'bulk_action\');" />';
				echo apply_filters( 'gform_entry_apply_button', $apply_button );

				if ( in_array( $filter, array( 'trash', 'spam' ) ) ) {
					$message      = $filter == 'trash' ? __( "WARNING! This operation cannot be undone. Empty trash? \'Ok\' to empty trash. \'Cancel\' to abort.", 'gravityforms' ) : __( "WARNING! This operation cannot be undone. Permanently delete all spam? \'Ok\' to delete. \'Cancel\' to abort.", 'gravityforms' );
					$button_label = $filter == 'trash' ? __( 'Empty Trash', 'gravityforms' ) : __( 'Delete All Spam', 'gravityforms' );
					?>
					<input type="submit" class="button" name="button_delete_permanently" value="<?php echo $button_label ?>" onclick="return confirm('<?php echo esc_attr( $message ) ?>');" />
				<?php
				}
				?>
				<div id="notifications_modal_container" style="display:none;">
					<div id="notifications_container">

						<div id="post_tag" class="tagsdiv">
							<div id="resend_notifications_options">

								<?php

								$notifications = GFCommon::get_notifications( 'resend_notifications', $form );

								if ( ! is_array( $notifications ) || count( $form['notifications'] ) <= 0 ) {
									?>
									<p class="description"><?php _e( 'You cannot resend notifications for these entries because this form does not currently have any notifications configured.', 'gravityforms' ); ?></p>

									<a href="<?php echo admin_url( "admin.php?page=gf_edit_forms&view=settings&subview=notification&id={$form['id']}" ) ?>" class="button"><?php _e( 'Configure Notifications', 'gravityforms' ) ?></a>
								<?php
								} else {
									?>
									<p class="description"><?php _e( 'Specify which notifications you would like to resend for the selected entries.', 'gravityforms' ); ?></p>
									<?php
									foreach ( $notifications as $notification ) {
										?>
										<input type="checkbox" class="gform_notifications" value="<?php echo esc_attr( $notification['id'] ); ?>" id="notification_<?php echo esc_attr( $notification['id'] ); ?>" onclick="toggleNotificationOverride();" />
										<label for="notification_<?php echo esc_attr( $notification['id'] ); ?>"><?php echo esc_html( $notification['name'] ); ?></label>
										<br /><br />
									<?php
									}

									?>
									<div id="notifications_override_settings" style="display:none;">

										<p class="description" style="padding-top:0; margin-top:0;">You may override the default notification settings
											by entering a comma delimited list of emails to which the selected notifications should be sent.</p>
										<label for="notification_override_email"><?php _e( 'Send To', 'gravityforms' ); ?> <?php gform_tooltip( 'notification_override_email' ) ?></label><br />
										<input type="text" name="notification_override_email" id="notification_override_email" style="width:99%;" /><br /><br />

									</div>

									<input type="button" name="notification_resend" id="notification_resend" value="<?php _e( 'Resend Notifications', 'gravityforms' ) ?>" class="button" style="" onclick="BulkResendNotifications();" />
									<span id="please_wait_container" style="display:none; margin-left: 5px;">
                                                <i class='gficon-gravityforms-spinner-icon gficon-spin'></i> <?php _e( 'Resending...', 'gravityforms' ); ?>
                                            </span>
								<?php
								}
								?>

							</div>

							<div id="resend_notifications_close" style="display:none;margin:10px 0 0;">
								<input type="button" name="resend_notifications_close_button" value="<?php _e( 'Close Window', 'gravityforms' ) ?>" class="button" style="" onclick="closeModal(true);" />
							</div>

						</div>

					</div>
				</div>
				<!-- / Resend Notifications -->

				<div id="print_modal_container" style="display:none;">
					<div id="print_container">

						<div class="tagsdiv">
							<div id="print_options">

								<p class="description"><?php _e( 'Print all of the selected entries at once.', 'gravityforms' ); ?></p>

								<?php if ( GFCommon::current_user_can_any( 'gravityforms_view_entry_notes' ) ) { ?>
									<input type="checkbox" name="gform_print_notes" value="print_notes" checked="checked" id="gform_print_notes" />
									<label for="gform_print_notes"><?php _e( 'Include notes', 'gravityforms' ); ?></label>
									<br /><br />
								<?php } ?>

								<input type="checkbox" name="gform_print_page_break" value="print_notes" checked="checked" id="gform_print_page_break" />
								<label for="gform_print_page_break"><?php _e( 'Add page break between entries', 'gravityforms' ); ?></label>
								<br /><br />

								<input type="button" value="<?php _e( 'Print', 'gravityforms' ); ?>" class="button" onclick="BulkPrint();" />

							</div>
						</div>

					</div>
				</div>
				<!-- / Print -->

			</div>

			<?php echo self::display_paging_links( 'header', $page_links, $first_item_index, $page_size, $total_count ); ?>

			<div class="clear"></div>
		</div>

		<table class="widefat fixed" cellspacing="0">
		<thead>
		<tr>
			<th scope="col" id="cb" class="manage-column column-cb check-column">
				<input type="checkbox" class="headercb" /></th>
			<?php
			if ( ! in_array( $filter, array( 'spam', 'trash' ) ) ) {
				?>
				<th scope="col" id="cb" class="manage-column column-cb check-column">&nbsp;</th>
			<?php
			}

			foreach ( $columns as $field_id => $field_info ) {
				$dir = $field_id == 0 ? 'DESC' : 'ASC'; //default every field so ascending sorting except date_created (id=0)
				if ( $field_id == $sort_field ) {
					//reverting direction if clicking on the currently sorted field
					$dir = $sort_direction == 'ASC' ? 'DESC' : 'ASC';
				}
				?>
				<th scope="col" class="manage-column entry_nowrap" onclick="Search('<?php echo esc_js( $field_id ); ?>', '<?php echo esc_js( $dir ); ?>', <?php echo absint( $form_id ); ?>, '<?php echo esc_js( $search ); ?>', '<?php echo esc_js( $star );?>', '<?php echo esc_js( $read ); ?>', '<?php echo esc_js( $filter ); ?>', '<?php echo esc_js( $search_field_id ); ?>', '<?php echo esc_js( $search_operator ); ?>');" style="cursor:pointer;"><?php echo esc_html( $field_info['label'] ) ?></th>
			<?php
			}
			?>
			<th scope="col" align="right" width="50">
				<a title="<?php _e( 'click to select columns to display', 'gravityforms' ) ?>" href="<?php echo trailingslashit( site_url( null, 'admin' ) ) ?>?gf_page=select_columns&id=<?php echo absint( $form_id ); ?>&TB_iframe=true&height=365&width=600" class="thickbox entries_edit_icon"><i class="fa fa-cog"></i></a>
			</th>
		</tr>
		</thead>
		<tfoot>
		<tr>
			<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
			<?php
			if ( ! in_array( $filter, array( 'spam', 'trash' ) ) ) {
				?>
				<th scope="col" id="cb" class="manage-column column-cb check-column">&nbsp;</th>
			<?php
			}
			foreach ( $columns as $field_id => $field_info ) {
				$dir = $field_id == 0 ? 'DESC' : 'ASC'; //default every field so ascending sorting except date_created (id=0)
				if ( $field_id == $sort_field ) { //reverting direction if clicking on the currently sorted field
					$dir = $sort_direction == 'ASC' ? 'DESC' : 'ASC';
				}
				?>
				<th scope="col" class="manage-column entry_nowrap" onclick="Search('<?php echo esc_js( $field_id ); ?>', '<?php echo esc_js( $dir ); ?>', <?php echo absint( $form_id ); ?>, '<?php echo esc_js( $search ); ?>', '<?php echo esc_js( $star ); ?>', '<?php echo esc_js( $read ); ?>', '<?php echo esc_js( $filter ); ?>');" style="cursor:pointer;"><?php echo esc_html( $field_info['label'] ) ?></th>
			<?php
			}
			?>
			<th scope="col" style="width:15px;">
				<a title="<?php _e( 'click to select columns to display', 'gravityforms' ) ?>" href="<?php echo trailingslashit( site_url() ) ?>?gf_page=select_columns&id=<?php echo absint( $form_id ); ?>&TB_iframe=true&height=365&width=600" class="thickbox entries_edit_icon"><i class=fa-cog"></i></a>
			</th>
		</tr>
		</tfoot>

		<tbody data-wp-lists="list:gf_entry" class="user-list" id="gf_entry_list">
		<?php
		if ( sizeof( $leads ) > 0 ) {
			$field_ids        = array_keys( $columns );
			$gf_entry_locking = new GFEntryLocking();
			$alternate_row    = false;
			foreach ( $leads as $position => $lead ) {

				$position = ( $page_size * $page_index ) + $position;

				?>
				<tr id="lead_row_<?php echo esc_attr( $lead['id'] ) ?>" class='author-self status-inherit <?php echo $lead['is_read'] ? '' : 'lead_unread' ?> <?php echo $lead['is_starred'] ? 'lead_starred' : '' ?> <?php echo in_array( $filter, array( 'trash', 'spam' ) ) ? 'lead_spam_trash' : '' ?> <?php $gf_entry_locking->list_row_class( $lead['id'] ); ?> <?php echo ( $alternate_row = ! $alternate_row ) ? 'alternate' : '' ?>' valign="top" data-id="<?php echo esc_attr( $lead['id'] ) ?>">
				<th scope="row" class="check-column">
					<input type="checkbox" name="lead[]" value="<?php echo esc_attr( $lead['id'] ); ?>" />
					<?php $gf_entry_locking->lock_indicator(); ?>
				</th>
				<?php
				if ( ! in_array( $filter, array( 'spam', 'trash' ) ) ) {
					?>
					<td>
						<img id="star_image_<?php echo esc_attr( $lead['id'] ) ?>" src="<?php echo GFCommon::get_base_url() ?>/images/star<?php echo intval( $lead['is_starred'] ) ?>.png" onclick="ToggleStar(this, <?php echo esc_js( $lead['id'] ) . ",'" . esc_js( $filter ) . "'" ?>);" />
					</td>
				<?php
				}

				$is_first_column = true;

				$nowrap_class = 'entry_nowrap';
				foreach ( $field_ids as $field_id ) {

					$field = RGFormsModel::get_field( $form, $field_id );
					$value = rgar( $lead, $field_id );

					if ( ! empty( $field ) && $field->type == 'post_category' ) {
						$value = GFCommon::prepare_post_category_value( $value, $field, 'entry_list' );
					}

					//filtering lead value
					$value = apply_filters( 'gform_get_field_value', $value, $lead, $field );

					$input_type = ! empty( $columns[ $field_id ]['inputType'] ) ? $columns[ $field_id ]['inputType'] : $columns[ $field_id ]['type'];
					switch ( $input_type ) {

						case 'source_url' :
							$value = "<a href='" . esc_attr( $lead['source_url'] ) . "' target='_blank' alt='" . esc_attr( $lead['source_url'] ) . "' title='" . esc_attr( $lead['source_url'] ) . "'>.../" . esc_attr( GFCommon::truncate_url( $lead['source_url'] ) ) . '</a>';
							break;

						case 'date_created' :
						case 'payment_date' :
							$value = GFCommon::format_date( $value, false );
							break;

						case 'payment_amount' :
							$value = GFCommon::to_money( $value, $lead['currency'] );
							break;

						case 'created_by' :
							if ( ! empty( $value ) ) {
								$userdata = get_userdata( $value );
								if ( ! empty( $userdata ) ) {
									$value = $userdata->user_login;
								}
							}
							break;

						default:
							if ( $field !== null ) {
								$value = $field->get_value_entry_list( $value, $lead, $field_id, $columns, $form );
							} else {
								$value = esc_html( $value );
							}
					}

					$value = apply_filters( 'gform_entries_field_value', $value, $form_id, $field_id, $lead );

					/* ^ maybe move to function */

					$query_string = "gf_entries&view=entry&id={$form_id}&lid={$lead['id']}{$search_qs}{$sort_qs}{$dir_qs}{$filter_qs}&paged=" . ( $page_index + 1 );
					if ( $is_first_column ) {
						?>
						<td class="column-title">
							<a href="admin.php?page=gf_entries&view=entry&id=<?php echo absint( $form_id ); ?>&lid=<?php echo esc_attr( $lead['id'] . $search_qs . $sort_qs . $dir_qs . $filter_qs ); ?>&paged=<?php echo( $page_index + 1 ) ?>&pos=<?php echo $position; ?>&field_id=<?php echo esc_attr( $search_field_id ); ?>&operator=<?php echo esc_attr( $search_operator ); ?>"><?php echo esc_attr( $value ); ?></a>

							<?php $gf_entry_locking->lock_info( $lead['id'] ); ?>

							<div class="row-actions">
								<?php
								switch ( $filter ) {
									case 'trash' :
										?>
										<span class="edit">
                                                            <a title="<?php _e( 'View this entry', 'gravityforms' ); ?>" href="admin.php?page=gf_entries&view=entry&id=<?php echo absint( $form_id ); ?>&lid=<?php echo esc_attr( $lead['id'] . $search_qs . $sort_qs . $dir_qs . $filter_qs ); ?>&paged=<?php echo( $page_index + 1 ) ?>&pos=<?php echo $position; ?>&field_id=<?php echo esc_attr( $search_field_id ); ?>&operator=<?php echo esc_attr( $search_operator ); ?>"><?php _e( 'View', 'gravityforms' ); ?></a>
                                                            |
                                                        </span>

										<span class="edit">
                                                            <a data-wp-lists='delete:gf_entry_list:lead_row_<?php echo esc_attr( $lead['id'] );?>::status=active&entry=<?php echo esc_attr( $lead['id'] ); ?>' title="<?php echo _e( 'Restore this entry', 'gravityforms' ) ?>" href="<?php echo wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) ?>"><?php _e( 'Restore', 'gravityforms' ); ?></a>
											<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ? '|' : '' ?>
                                                        </span>

										<?php
										if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
											?>
											<span class="delete">
                                                                <?php
																$delete_link = '<a data-wp-lists="delete:gf_entry_list:lead_row_' . esc_attr( $lead['id'] ) . '::status=delete&entry=' . esc_attr( $lead['id'] ) . '" title="' . __( 'Delete this entry permanently', 'gravityforms' ) . '"  href="' . wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) . '">' . __( 'Delete Permanently', 'gravityforms' ) . '</a>';
																echo apply_filters( 'gform_delete_entry_link', $delete_link );
																?>
                                                            </span>
										<?php
										}
										break;

									case 'spam' :
										?>
										<span class="edit">
                                                            <a title="<?php _e( 'View this entry', 'gravityforms' ); ?>" href="admin.php?page=gf_entries&view=entry&id=<?php echo absint( $form_id ); ?>&lid=<?php echo esc_attr( $lead['id'] . $search_qs . $sort_qs . $dir_qs . $filter_qs ); ?>&paged=<?php echo( $page_index + 1 ) ?>&pos=<?php echo $position; ?>"><?php _e( 'View', 'gravityforms' ); ?></a>
                                                            |
                                                        </span>

										<span class="unspam">
                                                            <a data-wp-lists='delete:gf_entry_list:lead_row_<?php echo esc_attr( $lead['id'] ); ?>::status=unspam&entry=<?php echo esc_attr( $lead['id'] ); ?>' title="<?php echo _e( 'Mark this entry as not spam', 'gravityforms' ) ?>" href="<?php echo wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) ?>"><?php _e( 'Not Spam', 'gravityforms' ); ?></a>
											<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ? '|' : '' ?>
                                                        </span>

										<?php
										if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
											?>
											<span class="delete">
                                                                <?php
																$delete_link = '<a data-wp-lists="delete:gf_entry_list:lead_row_' . esc_attr( $lead['id'] ) . '::status=delete&entry=' . esc_attr( $lead['id'] ) . '" title="' . __( 'Delete this entry permanently', 'gravityforms' ) . '"  href="' . wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) . '">' . __( 'Delete Permanently', 'gravityforms' ) . '</a>';
																echo apply_filters( 'gform_delete_entry_link', $delete_link );
																?>
                                                            </span>
										<?php
										}

										break;

									default:
										?>
											<span class="edit">
                                                            <a title="<?php _e( 'View this entry', 'gravityforms' ); ?>" href="admin.php?page=gf_entries&view=entry&id=<?php echo absint( $form_id ); ?>&lid=<?php echo esc_attr( $lead['id'] . $search_qs . $sort_qs . $dir_qs . $filter_qs ); ?>&paged=<?php echo( $page_index + 1 ) ?>&pos=<?php echo $position; ?>&field_id=<?php echo esc_attr( $search_field_id ); ?>&operator=<?php echo esc_attr( $search_operator ); ?>"><?php _e( 'View', 'gravityforms' ); ?></a>
                                                            |
                                                        </span>
											<span class="edit">
                                                            <a id="mark_read_<?php echo esc_attr( $lead['id'] ); ?>" title="Mark this entry as read" href="javascript:ToggleRead(<?php echo esc_js( $lead['id'] ) . ",'" . esc_js( $filter ) . "'" ?>);" style="display:<?php echo $lead['is_read'] ? 'none' : 'inline' ?>;"><?php _e( 'Mark read', 'gravityforms' ); ?></a><a id="mark_unread_<?php echo esc_attr( $lead['id'] ); ?>" title="<?php _e( 'Mark this entry as unread', 'gravityforms' ); ?>" href="javascript:ToggleRead(<?php echo esc_js( $lead['id'] ) . ",'" . esc_js( $filter ) . "'" ?>);" style="display:<?php echo $lead['is_read'] ? 'inline' : 'none' ?>;"><?php _e( 'Mark unread', 'gravityforms' ); ?></a>
												<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) || GFCommon::akismet_enabled( $form_id ) ? '|' : '' ?>
                                                        </span>
										<?php
										if ( GFCommon::spam_enabled( $form_id ) ) {
											?>
											<span class="spam">
                                                                <a data-wp-lists='delete:gf_entry_list:lead_row_<?php echo esc_attr( $lead['id'] ) ?>::status=spam&entry=<?php echo esc_attr( $lead['id'] ); ?>' title="<?php _e( 'Mark this entry as spam', 'gravityforms' ) ?>" href="<?php echo wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) ?>"><?php _e( 'Spam', 'gravityforms' ); ?></a>
												<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ? '|' : '' ?>
                                                            </span>

										<?php
										}
										if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
											?>
											<span class="trash">
                                                                <a data-wp-lists='delete:gf_entry_list:lead_row_<?php echo esc_attr( $lead['id'] ); ?>::status=trash&entry=<?php echo esc_attr( $lead['id'] ); ?>' title="<?php _e( 'Move this entry to the trash', 'gravityforms' ) ?>" href="<?php echo wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) ?>"><?php _e( 'Trash', 'gravityforms' ); ?></a>
                                                            </span>
										<?php
										}
										break;
								}

								do_action( 'gform_entries_first_column_actions', $form_id, $field_id, $value, $lead, $query_string );
								?>

							</div>
							<?php
							do_action( 'gform_entries_first_column', $form_id, $field_id, $value, $lead, $query_string );
							?>
						</td>
					<?php

					} else {
						?>
						<td class="<?php echo $nowrap_class ?>">
							<?php echo apply_filters( 'gform_entries_column_filter', $value, $form_id, $field_id, $lead, $query_string ); ?>&nbsp;
							<?php do_action( 'gform_entries_column', $form_id, $field_id, $value, $lead, $query_string ); ?>
						</td>
					<?php
					}
					$is_first_column = false;
				}
				?>
				<td>&nbsp;</td>
				</tr>
			<?php
			}
		} else {

			$column_count = sizeof( $columns ) + 3;

			switch ( $filter ) {
				case 'unread' :
					$message = isset( $_GET['field_id'] ) ? __( 'This form does not have any unread entries matching the search criteria.', 'gravityforms' ) : __( 'This form does not have any unread entries.', 'gravityforms' );
					break;

				case 'star' :
					$message = isset( $_GET['field_id'] ) ? __( 'This form does not have any starred entries matching the search criteria.', 'gravityforms' ) : __( 'This form does not have any starred entries.', 'gravityforms' );
					break;

				case 'spam' :
					$message      = __( 'This form does not have any spam.', 'gravityforms' );
					$column_count = sizeof( $columns ) + 2;
					break;

				case 'trash' :
					$message      = isset( $_GET['field_id'] ) ? __( 'This form does not have any entries in the trash matching the search criteria.', 'gravityforms' ) : __( 'This form does not have any entries in the trash.', 'gravityforms' );
					$column_count = sizeof( $columns ) + 2;
					break;

				default :
					$message = isset( $_GET['field_id'] ) ? __( 'This form does not have any entries matching the search criteria.', 'gravityforms' ) : __( 'This form does not have any entries yet.', 'gravityforms' );

			}
			?>
			<tr>
				<td colspan="<?php echo $column_count ?>" style="padding:20px;"><?php echo $message ?></td>
			</tr>
		<?php
		}
		?>
		</tbody>
		</table>

		<div class="clear"></div>

		<div class="tablenav">

			<div class="alignleft actions" style="padding:8px 0 7px 0;">
				<label class="hidden" for="bulk_action2"> <?php _e( 'Bulk action', 'gravityforms' ) ?></label>
				<select name="bulk_action2" id="bulk_action2">
					<option value=''><?php _e( ' Bulk action ', 'gravityforms' ) ?></option>
					<?php
					switch ( $filter ) {
						case 'trash' :
							?>
							<option value='restore'><?php _e( 'Restore', 'gravityforms' ) ?></option>
							<?php
							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
								?>
								<option value='delete'><?php _e( 'Delete Permanently', 'gravityforms' ) ?></option>
							<?php
							}
							break;
						case 'spam' :
							?>
							<option value='unspam'><?php _e( 'Not Spam', 'gravityforms' ) ?></option>
							<?php
							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
								?>
								<option value='delete'><?php _e( 'Delete Permanently', 'gravityforms' ) ?></option>
							<?php
							}
							break;

						default:
							?>
								<option value='mark_read'><?php _e( 'Mark as Read', 'gravityforms' ) ?></option>
								<option value='mark_unread'><?php _e( 'Mark as Unread', 'gravityforms' ) ?></option>
								<option value='add_star'><?php _e( 'Add Star', 'gravityforms' ) ?></option>
								<option value='remove_star'><?php _e( 'Remove Star', 'gravityforms' ) ?></option>
								<option value='resend_notifications'><?php _e( 'Resend Notifications', 'gravityforms' ) ?></option>
								<option value='print'><?php _e( 'Print Entries', 'gravityforms' ) ?></option>
							<?php
							if ( GFCommon::spam_enabled( $form_id ) ) {
								?>
								<option value='spam'><?php _e( 'Spam', 'gravityforms' ) ?></option>
							<?php
							}

							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
								?>
								<option value='trash'><?php _e( 'Move to Trash', 'gravityforms' ) ?></option>
							<?php
							}
					}?>
				</select>
				<?php
				$apply_button = '<input type="submit" class="button" value="' . __( 'Apply', 'gravityforms' ) . '" onclick="return handleBulkApply(\'bulk_action2\');" />';
				echo apply_filters( 'gform_entry_apply_button', $apply_button );
				?>
			</div>

			<?php echo self::display_paging_links( 'footer', $page_links, $first_item_index, $page_size, $total_count ); ?>

			<div class="clear"></div>
		</div>

		</form>
		</div>
	<?php
	}


	public static function get_icon_url( $path ) {
		$info = pathinfo( $path );
		switch ( strtolower( rgar( $info, 'extension' ) ) ) {

			case 'css' :
				$file_name = 'icon_css.gif';
				break;

			case 'doc' :
				$file_name = 'icon_doc.gif';
				break;

			case 'fla' :
				$file_name = 'icon_fla.gif';
				break;

			case 'html' :
			case 'htm' :
			case 'shtml' :
				$file_name = 'icon_html.gif';
				break;

			case 'js' :
				$file_name = 'icon_js.gif';
				break;

			case 'log' :
				$file_name = 'icon_log.gif';
				break;

			case 'mov' :
				$file_name = 'icon_mov.gif';
				break;

			case 'pdf' :
				$file_name = 'icon_pdf.gif';
				break;

			case 'php' :
				$file_name = 'icon_php.gif';
				break;

			case 'ppt' :
				$file_name = 'icon_ppt.gif';
				break;

			case 'psd' :
				$file_name = 'icon_psd.gif';
				break;

			case 'sql' :
				$file_name = 'icon_sql.gif';
				break;

			case 'swf' :
				$file_name = 'icon_swf.gif';
				break;

			case 'txt' :
				$file_name = 'icon_txt.gif';
				break;

			case 'xls' :
				$file_name = 'icon_xls.gif';
				break;

			case 'xml' :
				$file_name = 'icon_xml.gif';
				break;

			case 'zip' :
				$file_name = 'icon_zip.gif';
				break;

			case 'gif' :
			case 'jpg' :
			case 'jpeg':
			case 'png' :
			case 'bmp' :
			case 'tif' :
			case 'eps' :
				$file_name = 'icon_image.gif';
				break;

			case 'mp3' :
			case 'wav' :
			case 'wma' :
				$file_name = 'icon_audio.gif';
				break;

			case 'mp4' :
			case 'avi' :
			case 'wmv' :
			case 'flv' :
				$file_name = 'icon_video.gif';
				break;

			default:
				$file_name = 'icon_generic.gif';
				break;
		}

		return GFCommon::get_base_url() . "/images/doctypes/$file_name";
	}

	private static function update_message() {


	}

	private static function display_paging_links( $which, $page_links, $first_item_index, $page_size, $total_lead_count ) {
		//Displaying paging links if appropriate
		//$which - header or footer, so the items can have unique names
		if ( $page_links ) {
			$paging_html = '
			<div class="tablenav-pages">
			<span id="paging_' . $which . '" class="displaying-num">';
			$range_max   = '<span id="paging_range_max_' . $which . '">';
			if ( ( $first_item_index + $page_size ) > $total_lead_count ) {
				$range_max .= $total_lead_count;
			} else {
				$range_max .= ( $first_item_index + $page_size );
			}
			$range_max .= '</span>';
			$range_min    = '<span id="paging_range_min_' . $which . '">' . ( $first_item_index + 1 ) . '</span>';
			$paging_total = '<span id="paging_total_' . $which . '">' . $total_lead_count . '</span>';
			$paging_html .= sprintf( __( 'Displaying %s - %s of %s', 'gravityforms' ), $range_min, $range_max, $paging_total );
			$paging_html .= '</span>' . $page_links . '</div>';
		} else {
			$paging_html = '';
		}

		return $paging_html;
	}
}