<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFEntryDetail {

	public static function lead_detail_page() {
		global $current_user;

		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		echo GFCommon::get_remote_message();

		$form    = RGFormsModel::get_form_meta( absint( $_GET['id'] ) );
		$form_id = absint( $form['id'] );
		$form    = gf_apply_filters( array( 'gform_admin_pre_render', $form_id ), $form );
		$lead_id = rgpost( 'entry_id' ) ? absint( rgpost( 'entry_id' ) ): absint( rgget( 'lid' ) );

		$filter = rgget( 'filter' );
		$status = in_array( $filter, array( 'trash', 'spam' ) ) ? $filter : 'active';

		$position       = rgget( 'pos' ) ? rgget( 'pos' ) : 0;
		$sort_direction = rgget( 'dir' ) ? rgget( 'dir' ) : 'DESC';

		$sort_field      = empty( $_GET['sort'] ) ? 0 : $_GET['sort'];
		$sort_field_meta = RGFormsModel::get_field( $form, $sort_field );
		$is_numeric      = $sort_field_meta['type'] == 'number';

		$search_criteria['status'] = $status;

		require_once( 'entry_list.php' );
		$filter_links = GFEntryList::get_filter_links( $form, false );

		foreach ( $filter_links as $filter_link ) {
			if ( $filter == $filter_link['id'] ) {
				$search_criteria['field_filters'] = $filter_link['field_filters'];
				break;
			}
		}

		$search_field_id = rgget( 'field_id' );

		if ( isset( $_GET['field_id'] ) && $_GET['field_id'] !== '' ) {
			$key            = $search_field_id;
			$val            = rgget( 's' );
			$strpos_row_key = strpos( $search_field_id, '|' );
			if ( $strpos_row_key !== false ) { //multi-row likert
				$key_array = explode( '|', $search_field_id );
				$key       = $key_array[0];
				$val       = $key_array[1] . ':' . $val;
			}

			$search_criteria['field_filters'][] = array(
				'key'      => $key,
				'operator' => rgempty( 'operator', $_GET ) ? 'is' : rgget( 'operator' ),
				'value'    => $val,
			);

			$type = rgget( 'type' );
			if ( empty( $type ) ) {
				if ( rgget( 'field_id' ) == '0' ) {
					$search_criteria['type'] = 'global';
				}
			}
		}

		/**
		 * Allow the entry list search criteria to be overridden.
		 *
		 * @since  1.9.14.30
		 *
		 * @param array $search_criteria An array containing the search criteria.
		 * @param int $form_id The ID of the current form.
		 */
		$search_criteria = gf_apply_filters( array( 'gform_search_criteria_entry_list', $form_id ), $search_criteria, $form_id );

		$paging = array( 'offset' => $position, 'page_size' => 1 );

		if ( ! empty( $sort_field ) ) {
			$sorting = array( 'key' => $_GET['sort'], 'direction' => $sort_direction, 'is_numeric' => $is_numeric );
		} else {
			$sorting = array();
		}
		$total_count = 0;
		$leads       = GFAPI::get_entries( $form['id'], $search_criteria, $sorting, $paging, $total_count );

		$prev_pos = ! rgblank( $position ) && $position > 0 ? $position - 1 : false;
		$next_pos = ! rgblank( $position ) && $position < $total_count - 1 ? $position + 1 : false;

		// unread filter requires special handling for pagination since entries are filter out of the query as they are read
		if ( $filter == 'unread' ) {
			$next_pos = $position;

			if ( $next_pos + 1 == $total_count ) {
				$next_pos = false;
			}
		}

		if ( ! $lead_id ) {
			$lead = ! empty( $leads ) ? $leads[0] : false;
		} else {
			$lead = GFAPI::get_entry( $lead_id );
		}

		if ( is_wp_error( $lead ) || ! $lead ) {
			esc_html_e( "Oops! We couldn't find your entry. Please try again", 'gravityforms' );

			return;
		}

		RGFormsModel::update_lead_property( $lead['id'], 'is_read', 1 );

		switch ( RGForms::post( 'action' ) ) {
			case 'update' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				//Loading files that have been uploaded to temp folder
				$files = GFCommon::json_decode( stripslashes( RGForms::post( 'gform_uploaded_files' ) ) );
				if ( ! is_array( $files ) ) {
					$files = array();
				}

				$original_entry = $lead;

				GFFormsModel::$uploaded_files[ $form_id ] = $files;
				GFFormsModel::save_lead( $form, $lead );

				/**
				 * Fires after the Entry is updated from the entry detail page.
				 *
				 * @param array   $form           The form object for the entry.
				 * @param integer $lead['id']     The entry ID.
				 * @param array   $original_entry The entry object before being updated.
				 */
				gf_do_action( array( 'gform_after_update_entry', $form['id'] ), $form, $lead['id'], $original_entry );

				$lead = RGFormsModel::get_lead( $lead['id'] );
				$lead = GFFormsModel::set_entry_meta( $lead, $form );

				break;

			case 'add_note' :
				check_admin_referer( 'gforms_update_note', 'gforms_update_note' );
				$user_data = get_userdata( $current_user->ID );
				RGFormsModel::add_note( $lead['id'], $current_user->ID, $user_data->display_name, stripslashes( $_POST['new_note'] ) );

				//emailing notes if configured
				if ( rgpost( 'gentry_email_notes_to' ) ) {
					GFCommon::log_debug( 'GFEntryDetail::lead_detail_page(): Preparing to email entry notes.' );
					$email_to      = $_POST['gentry_email_notes_to'];
					$email_from    = $current_user->user_email;
					$email_subject = stripslashes( $_POST['gentry_email_subject'] );
					$body = stripslashes( $_POST['new_note'] );

					$headers = "From: \"$email_from\" <$email_from> \r\n";
					GFCommon::log_debug( "GFEntryDetail::lead_detail_page(): Emailing notes - TO: $email_to SUBJECT: $email_subject BODY: $body HEADERS: $headers" );
					$is_success  = wp_mail( $email_to, $email_subject, $body, $headers );
					$result = is_wp_error( $is_success ) ? $is_success->get_error_message() : $is_success;
					GFCommon::log_debug( "GFEntryDetail::lead_detail_page(): Result from wp_mail(): {$result}" );
					if ( ! is_wp_error( $is_success ) && $is_success ) {
						GFCommon::log_debug( 'GFEntryDetail::lead_detail_page(): Mail was passed from WordPress to the mail server.' );
					} else {
						GFCommon::log_error( 'GFEntryDetail::lead_detail_page(): The mail message was passed off to WordPress for processing, but WordPress was unable to send the message.' );
					}

					if ( has_filter( 'phpmailer_init' ) ) {
						GFCommon::log_debug( __METHOD__ . '(): The WordPress phpmailer_init hook has been detected, usually used by SMTP plugins, it can impact mail delivery.' );
					}

					/**
					 * Fires after a note is attached to an entry and sent as an email
					 *
					 * @param string $result        The Error message or success message when the entry note is sent
					 * @param string $email_to      The email address to send the entry note to
					 * @param string $email_from    The email address from which the email is sent from
					 * @param string $email_subject The subject of the email that is sent
					 * @param mixed  $body          The Full body of the email containing the message after the note is sent
					 * @param array  $form          The current form object
					 * @param array  $lead          The Current lead object
					 */
					do_action( 'gform_post_send_entry_note', $result, $email_to, $email_from, $email_subject, $body, $form, $lead );
				}
				break;

			case 'add_quick_note' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				$user_data = get_userdata( $current_user->ID );
				RGFormsModel::add_note( $lead['id'], $current_user->ID, $user_data->display_name, stripslashes( $_POST['quick_note'] ) );
				break;

			case 'bulk' :
				check_admin_referer( 'gforms_update_note', 'gforms_update_note' );
				if ( $_POST['bulk_action'] == 'delete' ) {
					if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_entry_notes' ) ) {
						die( esc_html__( "You don't have adequate permission to delete notes.", 'gravityforms' ) );
					}
					RGFormsModel::delete_notes( $_POST['note'] );
				}
				break;

			case 'trash' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				RGFormsModel::update_lead_property( $lead['id'], 'status', 'trash' );
				$lead = RGFormsModel::get_lead( $lead['id'] );
				break;

			case 'restore' :
			case 'unspam' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				RGFormsModel::update_lead_property( $lead['id'], 'status', 'active' );
				$lead = RGFormsModel::get_lead( $lead['id'] );
				break;

			case 'spam' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				RGFormsModel::update_lead_property( $lead['id'], 'status', 'spam' );
				$lead = RGFormsModel::get_lead( $lead['id'] );
				break;

			case 'delete' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				if ( ! GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
					die( esc_html__( "You don't have adequate permission to delete entries.", 'gravityforms' ) );
				}
				RGFormsModel::delete_lead( $lead['id'] );
				?>
				<script type="text/javascript">
					document.location.href = '<?php echo 'admin.php?page=gf_entries&view=entries&id=' . absint( $form['id'] )?>';
				</script>
				<?php

				break;
		}

		$mode = empty( $_POST['screen_mode'] ) ? 'view' : $_POST['screen_mode'];

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		?>
		<link rel="stylesheet" href="<?php echo GFCommon::get_base_url() ?>/css/admin<?php echo $min; ?>.css" />
		<script type="text/javascript">

			jQuery(document).ready(function () {
				toggleNotificationOverride(true);
				jQuery('#gform_update_button').prop('disabled', false);
			});

			function DeleteFile(leadId, fieldId, deleteButton) {
				if (confirm(<?php echo json_encode( __( "Would you like to delete this file? 'Cancel' to stop. 'OK' to delete", 'gravityforms' ) ); ?>)) {
					var fileIndex = jQuery(deleteButton).parent().index();
					var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' )?>");
					mysack.execute = 1;
					mysack.method = 'POST';
					mysack.setVar("action", "rg_delete_file");
					mysack.setVar("rg_delete_file", "<?php echo wp_create_nonce( 'rg_delete_file' ) ?>");
					mysack.setVar("lead_id", leadId);
					mysack.setVar("field_id", fieldId);
					mysack.setVar("file_index", fileIndex);
					mysack.onError = function () {
						alert(<?php echo json_encode( __( 'Ajax error while deleting field.', 'gravityforms' ) ); ?>)
					};
					mysack.runAJAX();

					return true;
				}
			}

			function EndDeleteFile(fieldId, fileIndex) {
				var previewFileSelector = "#preview_existing_files_" + fieldId + " .ginput_preview";
				var $previewFiles = jQuery(previewFileSelector);
				var rr = $previewFiles.eq(fileIndex);
				$previewFiles.eq(fileIndex).remove();
				var $visiblePreviewFields = jQuery(previewFileSelector);
				if ($visiblePreviewFields.length == 0) {
					jQuery('#preview_' + fieldId).hide();
					jQuery('#upload_' + fieldId).show('slow');
				}
			}

			function ToggleShowEmptyFields() {
				if (jQuery("#gentry_display_empty_fields").is(":checked")) {
					createCookie("gf_display_empty_fields", true, 10000);
					document.location = document.location.href;
				}
				else {
					eraseCookie("gf_display_empty_fields");
					document.location = document.location.href;
				}
			}

			function createCookie(name, value, days) {
				if (days) {
					var date = new Date();
					date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
					var expires = "; expires=" + date.toGMTString();
				}
				else var expires = "";
				document.cookie = name + "=" + value + expires + "; path=/";
			}

			function eraseCookie(name) {
				createCookie(name, "", -1);
			}

			function ResendNotifications() {

				var selectedNotifications = new Array();
				jQuery(".gform_notifications:checked").each(function () {
					selectedNotifications.push(jQuery(this).val());
				});

				var sendTo = jQuery('#notification_override_email').val();

				if (selectedNotifications.length <= 0) {
					displayMessage(<?php echo json_encode( __( 'You must select at least one type of notification to resend.', 'gravityforms' ) ); ?>, 'error', '#notifications_container');
					return;
				}

				jQuery('#please_wait_container').fadeIn();

				jQuery.post(ajaxurl, {
						action                 : "gf_resend_notifications",
						gf_resend_notifications: '<?php echo wp_create_nonce( 'gf_resend_notifications' ); ?>',
						notifications          : jQuery.toJSON(selectedNotifications),
						sendTo                 : sendTo,
						leadIds                : '<?php echo absint( $lead['id'] ); ?>',
						formId                 : '<?php echo absint( $form['id'] ); ?>'
					},
					function (response) {
						if (response) {
							displayMessage(response, "error", "#notifications_container");
						} else {
							displayMessage(<?php echo json_encode( esc_html__( 'Notifications were resent successfully.', 'gravityforms' ) ); ?>, "updated", "#notifications_container" );

							// reset UI
							jQuery(".gform_notifications").attr( 'checked', false );
							jQuery('#notification_override_email').val('');

							toggleNotificationOverride();

						}

						jQuery('#please_wait_container').hide();
						setTimeout(function () {
							jQuery('#notifications_container').find('.message').slideUp();
						}, 5000);
					}
				);

			}

			function displayMessage( message, messageClass, container ) {
				jQuery( container ).find( '.message' ).hide().html( message ).attr( 'class', 'message ' + messageClass ).slideDown();
			}

			function toggleNotificationOverride(isInit) {

				if (isInit)
					jQuery('#notification_override_email').val('');

				if (jQuery(".gform_notifications:checked").length > 0) {
					jQuery('#notifications_override_settings').slideDown();
				}
				else {
					jQuery('#notifications_override_settings').slideUp(function () {
						jQuery('#notification_override_email').val('');
					});
				}
			}

		</script>

		<form method="post" id="entry_form" enctype='multipart/form-data'>
		<?php wp_nonce_field( 'gforms_save_entry', 'gforms_save_entry' ) ?>
		<input type="hidden" name="action" id="action" value="" />
		<input type="hidden" name="screen_mode" id="screen_mode" value="<?php echo esc_attr( rgpost( 'screen_mode' ) ) ?>" />

		<input type="hidden" name="entry_id" id="entry_id" value="<?php echo absint( $lead['id'] ) ?>" />

		<div class="wrap gf_entry_wrap">
		<h2 class="gf_admin_page_title">
			<span><?php echo esc_html__( 'Entry #', 'gravityforms' ) . absint( $lead['id'] ); ?></span><span class="gf_admin_page_subtitle"><span class="gf_admin_page_formid">ID: <?php echo absint( $form['id'] ); ?></span><span class='gf_admin_page_formname'><?php esc_html_e( 'Form Name', 'gravityforms' ) ?>: <?php echo esc_html( $form['title'] );
				$gf_entry_locking = new GFEntryLocking();
				$gf_entry_locking->lock_info( $lead_id ); ?></span></span></h2>

		<?php if ( isset( $_GET['pos'] ) ) { ?>
			<div class="gf_entry_detail_pagination">
				<ul>
					<li class="gf_entry_count">
						<span>entry <strong><?php echo $position + 1; ?></strong> of <strong><?php echo $total_count; ?></strong></span>
					</li>
					<li class="gf_entry_prev gf_entry_pagination"><?php echo GFEntryDetail::entry_detail_pagination_link( $prev_pos, 'Previous Entry', 'gf_entry_prev_link', 'fa fa-arrow-circle-o-left' ); ?></li>
					<li class="gf_entry_next gf_entry_pagination"><?php echo GFEntryDetail::entry_detail_pagination_link( $next_pos, 'Next Entry', 'gf_entry_next_link', 'fa fa-arrow-circle-o-right' ); ?></li>
				</ul>
			</div>
		<?php } ?>

		<?php RGForms::top_toolbar() ?>

		<div id="poststuff" class="metabox-holder has-right-sidebar">
		<div id="side-info-column" class="inner-sidebar">
		<?php
        /**
         * Fires before the entry detail sidebar is generated
         *
         * @param array $form The Form object
         * @param array $lead The Entry object
         */
        do_action( 'gform_entry_detail_sidebar_before', $form, $lead );
        ?>

		<!-- INFO BOX -->
		<div id="submitdiv" class="stuffbox">
			<h3 class="hndle" style="cursor:default;">
				<span><?php esc_html_e( 'Entry', 'gravityforms' ); ?></span>
			</h3>

			<div class="inside">
				<div id="submitcomment" class="submitbox">
					<div id="minor-publishing" style="padding:10px;">
						<?php esc_html_e( 'Entry Id', 'gravityforms' ); ?>: <?php echo absint( $lead['id'] ) ?><br /><br />
						<?php esc_html_e( 'Submitted on', 'gravityforms' ); ?>: <?php echo esc_html( GFCommon::format_date( $lead['date_created'], false, 'Y/m/d' ) ) ?>
						<br /><br />
						<?php esc_html_e( 'User IP', 'gravityforms' ); ?>: <?php echo esc_html( $lead['ip'] ); ?>
						<br /><br />
						<?php
						if ( ! empty( $lead['created_by'] ) && $usermeta = get_userdata( $lead['created_by'] ) ) {
							?>
							<?php esc_html_e( 'User', 'gravityforms' ); ?>:
							<a href="user-edit.php?user_id=<?php echo absint( $lead['created_by'] ) ?>" alt="<?php esc_attr_e( 'View user profile', 'gravityforms' ); ?>" title="<?php esc_attr_e( 'View user profile', 'gravityforms' ); ?>"><?php echo esc_html( $usermeta->user_login ) ?></a>
							<br /><br />
						<?php
						}
						?>

						<?php esc_html_e( 'Embed Url', 'gravityforms' ); ?>:
						<a href="<?php echo esc_url( $lead['source_url'] ) ?>" target="_blank" alt="<?php echo esc_attr( $lead['source_url'] ) ?>" title="<?php echo esc_attr( $lead['source_url'] ) ?>">.../<?php echo esc_html( GFCommon::truncate_url( $lead['source_url'] ) ) ?></a>
						<br /><br />
						<?php
						if ( ! empty( $lead['post_id'] ) ) {
							$post = get_post( $lead['post_id'] );
							?>
							<?php esc_html_e( 'Edit Post', 'gravityforms' ); ?>:
							<a href="post.php?action=edit&post=<?php echo absint( $post->ID ) ?>" alt="<?php esc_attr_e( 'Click to edit post', 'gravityforms' ); ?>" title="<?php esc_attr_e( 'Click to edit post', 'gravityforms' ); ?>"><?php echo esc_html( $post->post_title ) ?></a>
							<br /><br />
						<?php
						}

                        /**
                         * Enables payment details within the entry details
                         *
                         * @param bool
                         * @param array $lead The Entry object
                         */
						if ( do_action( 'gform_enable_entry_info_payment_details', true, $lead ) ) {

							if ( ! empty( $lead['payment_status'] ) ) {
								echo $lead['transaction_type'] != 2 ? esc_html__( 'Payment Status', 'gravityforms' ) : esc_html__( 'Subscription Status', 'gravityforms' ); ?>:
								<span id="gform_payment_status"><?php
									/**
									 * Filters through a form payment status and allows modification
									 *
									 * @param string $lead['payment_status] A payment status to filter though
									 * @param array $form The Form Object to filter through
									 * @param array $lead The Lead Object to filter through
									 */
									echo apply_filters( 'gform_payment_status', $lead['payment_status'], $form, $lead ) ?></span>
								<br /><br />
								<?php
								if ( ! empty( $lead['payment_date'] ) ) {
									echo $lead['transaction_type'] != 2 ? esc_html__( 'Payment Date', 'gravityforms' ) : esc_html__( 'Start Date', 'gravityforms' ) ?>: <?php echo GFCommon::format_date( $lead['payment_date'], false, 'Y/m/d', $lead['transaction_type'] != 2 ) ?>
									<br /><br />
								<?php
								}

								if ( ! empty( $lead['transaction_id'] ) ) {
									echo $lead['transaction_type'] != 2 ? esc_html__( 'Transaction Id', 'gravityforms' ) : esc_html__( 'Subscriber Id', 'gravityforms' ); ?>: <?php echo esc_html( $lead['transaction_id'] ); ?>
									<br /><br />
								<?php
								}

								if ( ! rgblank( $lead['payment_amount'] ) ) {
									echo $lead['transaction_type'] != 2 ? esc_html__( 'Payment Amount', 'gravityforms' ) : esc_html__( 'Subscription Amount', 'gravityforms' ); ?>: <?php echo GFCommon::to_money( $lead['payment_amount'], $lead['currency'] ) ?>
									<br /><br />
								<?php
								}
							}
						}

                        /**
                         * Adds additional information to the entry details
                         *
                         * @param int   $form['id'] The form ID
                         * @param array $lead       The Entry object
                         */
						do_action( 'gform_entry_info', $form['id'], $lead );

						?>
					</div>
					<div id="major-publishing-actions">
						<div id="delete-action">
							<?php
							switch ( $lead['status'] ) {
								case 'spam' :
									if ( GFCommon::spam_enabled( $form['id'] ) ) {
										?>
										<a onclick="jQuery('#action').val('unspam'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Not Spam', 'gravityforms' ) ?></a>
										<?php
										echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ? '|' : '';
									}
									if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
										?>
										<a class="submitdelete deletion" onclick="if ( confirm('<?php echo esc_js( __( "You are about to delete this entry. 'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ); ?>') ) {jQuery('#action').val('delete'); jQuery('#entry_form').submit(); return true;} return false;" href="#"><?php esc_html_e( 'Delete Permanently', 'gravityforms' ) ?></a>
									<?php
									}

									break;

								case 'trash' :
									?>
									<a onclick="jQuery('#action').val('restore'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Restore', 'gravityforms' ) ?></a>
									<?php
									if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
										?>
										|
										<a class="submitdelete deletion" onclick="if ( confirm('<?php echo esc_js( __( "You are about to delete this entry. 'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ); ?>') ) {jQuery('#action').val('delete'); jQuery('#entry_form').submit(); return true;} return false;" href="#"><?php esc_html_e( 'Delete Permanently', 'gravityforms' ) ?></a>
									<?php
									}

									break;

								default :
									if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
										?>
										<a class="submitdelete deletion" onclick="jQuery('#action').val('trash'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Move to Trash', 'gravityforms' ) ?></a>
										<?php
										echo GFCommon::spam_enabled( $form['id'] ) ? '|' : '';
									}
									if ( GFCommon::spam_enabled( $form['id'] ) ) {
										?>
										<a class="submitdelete deletion" onclick="jQuery('#action').val('spam'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Mark as Spam', 'gravityforms' ) ?></a>
									<?php
									}
							}

							?>
						</div>
						<div id="publishing-action">
							<?php
							if ( GFCommon::current_user_can_any( 'gravityforms_edit_entries' ) && $lead['status'] != 'trash' ) {
								$button_text      = $mode == 'view' ? __( 'Edit', 'gravityforms' ) : __( 'Update', 'gravityforms' );
								$disabled         = $mode == 'view' ? '' : ' disabled="disabled" ';
								$update_button_id = $mode == 'view' ? 'gform_edit_button' : 'gform_update_button';
								$button_click     = $mode == 'view' ? "jQuery('#screen_mode').val('edit');" : "jQuery('#action').val('update'); jQuery('#screen_mode').val('view');";
								$update_button    = '<input id="' . $update_button_id . '" ' . $disabled . ' class="button button-large button-primary" type="submit" tabindex="4" value="' . esc_attr( $button_text ) . '" name="save" onclick="' . $button_click . '"/>';

								/**
								 * A filter to allow the modification of the button to update an entry detail
								 *
								 * @param string $update_button The HTML Rendered for the Entry Detail update button
								 */
								echo apply_filters( 'gform_entrydetail_update_button', $update_button );
								if ( $mode == 'edit' ) {
									echo '&nbsp;&nbsp;<input class="button button-large" type="submit" tabindex="5" value="' . esc_attr__( 'Cancel', 'gravityforms' ) . '" name="cancel" onclick="jQuery(\'#screen_mode\').val(\'view\');"/>';
								}
							}
							?>
						</div>
						<div class="clear"></div>
					</div>
				</div>
			</div>
		</div>

		<?php
		
		/**
		 * A filter to enable or disable the extra payment details box in an entry
		 *
		 * @param bool To enable (true) or disable (false)
		 * @param array $lead The Lead object to filter
		 */
		if ( ! empty( $lead['payment_status'] ) && ! apply_filters( 'gform_enable_entry_info_payment_details', true, $lead ) ) {
			self::payment_details_box( $lead, $form );
		}
		?>

		<?php
        /**
         * Inserts information into the middle of the entry detail sidebar
         *
         * @param array $form The Form object
         * @param array $lead The Entry object
         */
        do_action( 'gform_entry_detail_sidebar_middle', $form, $lead );
        ?>

		<?php if ( GFCommon::current_user_can_any( 'gravityforms_edit_entry_notes' ) ) { ?>
			<!-- start notifications -->
			<div class="postbox" id="notifications_container">
				<h3 class="hndle" style="cursor:default;">
					<span><?php esc_html_e( 'Notifications', 'gravityforms' ); ?></span>
				</h3>

				<div class="inside">
					<div class="message" style="display:none;padding:10px;"></div>
					<div>
						<?php

						$notifications = GFCommon::get_notifications( 'resend_notifications', $form );

						if ( ! is_array( $notifications ) || count( $form['notifications'] ) <= 0 ) {
							?>
							<p class="description"><?php esc_html_e( 'You cannot resend notifications for this entry because this form does not currently have any notifications configured.', 'gravityforms' ); ?></p>

							<a href="<?php echo admin_url( "admin.php?page=gf_edit_forms&view=settings&subview=notification&id={$form_id}" ) ?>" class="button"><?php esc_html_e( 'Configure Notifications', 'gravityforms' ) ?></a>
						<?php
						} else {
							foreach ( $notifications as $notification ) {
								?>
								<input type="checkbox" class="gform_notifications" value="<?php echo esc_attr( $notification['id'] ); ?>" id="notification_<?php echo esc_attr( $notification['id'] ); ?>" onclick="toggleNotificationOverride();" />
								<label for="notification_<?php echo esc_attr( $notification['id'] ); ?>"><?php echo esc_html( $notification['name'] ); ?></label>
								<br /><br />
							<?php
							}
							?>

							<div id="notifications_override_settings" style="display:none;">

								<p class="description" style="padding-top:0; margin-top:0; width:99%;">You may override the default notification settings
									by entering a comma delimited list of emails to which the selected notifications should be sent.</p>
								<label for="notification_override_email"><?php esc_html_e( 'Send To', 'gravityforms' ); ?> <?php gform_tooltip( 'notification_override_email' ) ?></label><br />
								<input type="text" name="notification_override_email" id="notification_override_email" style="width:99%;" />
								<br /><br />

							</div>

							<input type="button" name="notification_resend" value="<?php esc_attr_e( 'Resend Notifications', 'gravityforms' ) ?>" class="button" style="" onclick="ResendNotifications();" />
							<span id="please_wait_container" style="display:none; margin-left: 5px;">
								<i class='gficon-gravityforms-spinner-icon gficon-spin'></i> <?php esc_html_e( 'Resending...', 'gravityforms' ); ?>
                            </span>
						<?php
						}
						?>

					</div>
				</div>
			</div>
			<!-- / end notifications -->
		<?php } ?>

		<!-- begin print button -->
		<div class="detail-view-print">
			<a href="javascript:;" onclick="var notes_qs = jQuery('#gform_print_notes').is(':checked') ? '&notes=1' : ''; var url='<?php echo trailingslashit( site_url() ) ?>?gf_page=print-entry&fid=<?php echo absint( $form['id'] ) ?>&lid=<?php echo absint( $lead['id'] ); ?>' + notes_qs; window.open (url,'printwindow');" class="button"><?php esc_html_e( 'Print', 'gravityforms' ) ?></a>
			<?php if ( GFCommon::current_user_can_any( 'gravityforms_view_entry_notes' ) ) { ?>
				<input type="checkbox" name="print_notes" value="print_notes" checked="checked" id="gform_print_notes" />
				<label for="print_notes"><?php esc_html_e( 'include notes', 'gravityforms' ) ?></label>
			<?php } ?>
		</div>
		<!-- end print button -->
		<?php
        /**
         * Fires after the entry detail sidebar information.
         *
         * @param array $form The Form object
         * @param array $lead The Entry object
         */
        do_action( 'gform_entry_detail_sidebar_after', $form, $lead );
        ?>
		</div>

		<div id="post-body" class="has-sidebar">
			<div id="post-body-content" class="has-sidebar-content">
				<?php
                /**
                 * Fires before the entry detail content is displayed
                 *
                 * @param array $form The Form object
                 * @param array $lead The Entry object
                 */
				do_action( 'gform_entry_detail_content_before', $form, $lead );

				$form = gf_apply_filters( array( 'gform_admin_pre_render', $form['id'] ), $form );

				if ( $mode == 'view' ) {
					self::lead_detail_grid( $form, $lead, true );
				} else {
					self::lead_detail_edit( $form, $lead );
				}

                /**
                 * Fires when entry details are displayed
                 *
                 * @param array $form The Form object
                 * @param array $lead The Entry object
                 */
				do_action( 'gform_entry_detail', $form, $lead );

				if ( GFCommon::current_user_can_any( 'gravityforms_view_entry_notes' ) ) {
					?>
					<div class="postbox">
						<h3>
							<label for="name"><?php esc_html_e( 'Notes', 'gravityforms' ); ?></label>
						</h3>

						<form method="post">
							<?php wp_nonce_field( 'gforms_update_note', 'gforms_update_note' ) ?>
							<div class="inside">
								<?php
								$notes = RGFormsModel::get_lead_notes( $lead['id'] );

								//getting email values
								$email_fields = GFCommon::get_email_fields( $form );
								$emails = array();

								foreach ( $email_fields as $email_field ) {
									if ( ! empty( $lead[ $email_field->id ] ) ) {
										$emails[] = $lead[ $email_field->id ];
									}
								}
								//displaying notes grid
								$subject = '';
								self::notes_grid( $notes, true, $emails, $subject );
								?>
							</div>
						</form>
					</div>
				<?php
				}

                /**
                 * Fires after the entry detail content is displayed
                 *
                 * @param array $form The Form object
                 * @param array $lead The Entry object
                 */
				do_action( 'gform_entry_detail_content_after', $form, $lead );
				?>
			</div>
		</div>
		</div>
		</div>
		</form>
		<?php

		if ( rgpost( 'action' ) == 'update' ) {
			?>
			<div class="updated fade" style="padding:6px;">
				<?php esc_html_e( 'Entry Updated.', 'gravityforms' ); ?>
			</div>
		<?php
		}
	}

	public static function lead_detail_edit( $form, $lead ) {
		$form_id = absint( $form['id'] );
		?>
		<div class="postbox">
			<h3>
				<label for="name"><?php esc_html_e( 'Details', 'gravityforms' ); ?></label>
			</h3>

			<div class="inside">
				<table class="form-table entry-details">
					<tbody>
					<?php
					foreach ( $form['fields'] as $field ) {
						$field_id = $field->id;
						$content = $value = '';

						switch ( $field->get_input_type() ) {
							case 'section' :

								$content = '
								<tr valign="top">
									<td class="detail-view">
										<div style="margin-bottom:10px; border-bottom:1px dotted #ccc;">
											<h2 class="detail_gsection_title">' . esc_html( GFCommon::get_label( $field ) ) . '</h2>
										</div>
									</td>
								</tr>';

								break;

							case 'captcha':
							case 'html':
							case 'password':
								//ignore certain fields
								break;

							default :
								$value   = RGFormsModel::get_lead_field_value( $lead, $field );
								$td_id   = 'field_' . $form_id . '_' . $field_id;
								$td_id = esc_attr( $td_id );
								$content = "<tr valign='top'><td class='detail-view' id='{$td_id}'><label class='detail-label'>" . esc_html( GFCommon::get_label( $field ) ) . '</label>' .
									GFCommon::get_field_input( $field, $value, $lead['id'], $form_id, $form ) . '</td></tr>';

								break;
						}

						$content = apply_filters( 'gform_field_content', $content, $field, $value, $lead['id'], $form['id'] );

						echo $content;
					}
					?>
					</tbody>
				</table>
				<br />

				<div class="gform_footer">
					<input type="hidden" name="gform_unique_id" value="" />
					<input type="hidden" name="gform_uploaded_files" id='gform_uploaded_files_<?php echo absint( $form_id ); ?>' value="" />
				</div>
			</div>
		</div>
	<?php
	}

	public static function notes_grid( $notes, $is_editable, $emails = null, $subject = '' ) {
		if ( sizeof( $notes ) > 0 && $is_editable && GFCommon::current_user_can_any( 'gravityforms_edit_entry_notes' ) ) {
			?>
			<div class="alignleft actions" style="padding:3px 0;">
				<label class="hidden" for="bulk_action"><?php esc_html_e( ' Bulk action', 'gravityforms' ) ?></label>
				<select name="bulk_action" id="bulk_action">
					<option value=''><?php esc_html_e( ' Bulk action ', 'gravityforms' ) ?></option>
					<option value='delete'><?php esc_html_e( 'Delete', 'gravityforms' ) ?></option>
				</select>
				<?php
				$apply_button = '<input type="submit" class="button" value="' . esc_attr__( 'Apply', 'gravityforms' ) . '" onclick="jQuery(\'#action\').val(\'bulk\');" style="width: 50px;" />';
				/**
				 * A filter to allow you to modify the note apply button
				 *
				 * @param string $apply_button The Apply Button HTML
				 */
				echo apply_filters( 'gform_notes_apply_button', $apply_button );
				?>
			</div>
		<?php
		}
		?>
		<table class="widefat fixed entry-detail-notes" cellspacing="0">
			<?php
			if ( ! $is_editable ) {
				?>
				<thead>
				<tr>
					<th id="notes">Notes</th>
				</tr>
				</thead>
			<?php
			}
			?>
			<tbody id="the-comment-list" class="list:comment">
			<?php
			$count = 0;
			$notes_count = sizeof( $notes );
			foreach ( $notes as $note ) {
				$count ++;
				$is_last = $count >= $notes_count ? true : false;
				?>
				<tr valign="top">
					<?php
					if ( $is_editable && GFCommon::current_user_can_any( 'gravityforms_edit_entry_notes' ) ) {
					?>
					<th class="check-column" scope="row" style="padding:9px 3px 0 0">
						<input type="checkbox" value="<?php echo $note->id ?>" name="note[]" />
					</th>
					<td colspan="2">
						<?php
						}
						else {
						?>
					<td class="entry-detail-note<?php echo $is_last ? ' lastrow' : '' ?>">
						<?php
						}
						$class = $note->note_type ? " gforms_note_{$note->note_type}" : '';
						?>
						<div style="margin-top:4px;">
							<div class="note-avatar"><?php
								/**
								 * Allows filtering of the notes avatar
								 *
								 * @param array $note The Note object that is being filtered when modifying the avatar
								 */
								echo apply_filters( 'gform_notes_avatar', get_avatar( $note->user_id, 48 ), $note ); ?></div>
							<h6 class="note-author"><?php echo esc_html( $note->user_name ) ?></h6>
							<p class="note-email">
								<a href="mailto:<?php echo esc_attr( $note->user_email ) ?>"><?php echo esc_html( $note->user_email ) ?></a><br />
								<?php esc_html_e( 'added on', 'gravityforms' ); ?> <?php echo esc_html( GFCommon::format_date( $note->date_created, false ) ) ?>
							</p>
						</div>
						<div class="detail-note-content<?php echo $class ?>"><?php echo nl2br( esc_html( $note->value ) ) ?></div>
					</td>

				</tr>
			<?php
			}
			if ( $is_editable && GFCommon::current_user_can_any( 'gravityforms_edit_entry_notes' ) ) {
				?>
				<tr>
					<td colspan="3" style="padding:10px;" class="lastrow">
						<textarea name="new_note" style="width:100%; height:50px; margin-bottom:4px;"></textarea>
						<?php
						$note_button = '<input type="submit" name="add_note" value="' . esc_attr__( 'Add Note', 'gravityforms' ) . '" class="button" style="width:auto;padding-bottom:2px;" onclick="jQuery(\'#action\').val(\'add_note\');"/>';

						/**
						 * Allows for modification of the "Add Note" button for Entry Notes
						 *
						 * @param string $note_button The HTML for the "Add Note" Button
						 */
						echo apply_filters( 'gform_addnote_button', $note_button );

						if ( ! empty( $emails ) ) {
							?>
							&nbsp;&nbsp;
							<span>
                                <select name="gentry_email_notes_to" onchange="if(jQuery(this).val() != '') {jQuery('#gentry_email_subject_container').css('display', 'inline');} else{jQuery('#gentry_email_subject_container').css('display', 'none');}">
									<option value=""><?php esc_html_e( 'Also email this note to', 'gravityforms' ) ?></option>
									<?php foreach ( $emails as $email ) { ?>
										<option value="<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></option>
									<?php } ?>
								</select>
                                &nbsp;&nbsp;

                                <span id='gentry_email_subject_container' style="display:none;">
                                    <label for="gentry_email_subject"><?php esc_html_e( 'Subject:', 'gravityforms' ) ?></label>
                                    <input type="text" name="gentry_email_subject" id="gentry_email_subject" value="" style="width:35%" />
                                </span>
                            </span>
						<?php } ?>
					</td>
				</tr>
			<?php
			}
			?>
			</tbody>
		</table>
	<?php
	}

	public static function lead_detail_grid( $form, $lead, $allow_display_empty_fields = false ) {
		$form_id = absint( $form['id'] );

		$display_empty_fields = self::maybe_display_empty_fields( $allow_display_empty_fields, $form, $lead );

		?>
		<table cellspacing="0" class="widefat fixed entry-detail-view">
			<thead>
			<tr>
				<th id="details">
					<?php
					$title = sprintf( '%s : %s %s', esc_html( $form['title'] ), esc_html__( 'Entry # ', 'gravityforms' ), absint( $lead['id'] ) );
					echo apply_filters( 'gform_entry_detail_title', $title, $form, $lead );
					?>
				</th>
				<th style="width:140px; font-size:10px; text-align: right;">
					<?php
					if ( $allow_display_empty_fields ) {
						?>
						<input type="checkbox" id="gentry_display_empty_fields" <?php echo $display_empty_fields ? "checked='checked'" : '' ?> onclick="ToggleShowEmptyFields();" />&nbsp;&nbsp;
						<label for="gentry_display_empty_fields"><?php esc_html_e( 'show empty fields', 'gravityforms' ) ?></label>
					<?php
					}
					?>
				</th>
			</tr>
			</thead>
			<tbody>
			<?php
			$count = 0;
			$field_count = sizeof( $form['fields'] );
			$has_product_fields = false;
			foreach ( $form['fields'] as $field ) {

				$content = $value = '';

				switch ( $field->get_input_type() ) {
					case 'section' :
						if ( ! GFCommon::is_section_empty( $field, $form, $lead ) || $display_empty_fields ) {
							$count ++;
							$is_last = $count >= $field_count ? ' lastrow' : '';

							$content = '
                                <tr>
                                    <td colspan="2" class="entry-view-section-break' . $is_last . '">' . esc_html( GFCommon::get_label( $field ) ) . '</td>
                                </tr>';
						}
						break;

					case 'captcha':
					case 'html':
					case 'password':
					case 'page':
						//ignore captcha, html, password, page field
						break;

					default :
						//ignore product fields as they will be grouped together at the end of the grid
						if ( GFCommon::is_product_field( $field->type ) ) {
							$has_product_fields = true;
							continue;
						}

						$value         = RGFormsModel::get_lead_field_value( $lead, $field );
						$display_value = GFCommon::get_lead_field_display( $field, $value, $lead['currency'] );

						$display_value = apply_filters( 'gform_entry_field_value', $display_value, $field, $lead, $form );

						if ( $display_empty_fields || ! empty( $display_value ) || $display_value === '0' ) {
							$count ++;
							$is_last  = $count >= $field_count && ! $has_product_fields ? true : false;
							$last_row = $is_last ? ' lastrow' : '';

							$display_value = empty( $display_value ) && $display_value !== '0' ? '&nbsp;' : $display_value;

							$content = '
                                <tr>
                                    <td colspan="2" class="entry-view-field-name">' . esc_html( GFCommon::get_label( $field ) ) . '</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="entry-view-field-value' . $last_row . '">' . $display_value . '</td>
                                </tr>';
						}
						break;
				}

				$content = apply_filters( 'gform_field_content', $content, $field, $value, $lead['id'], $form['id'] );

				echo $content;

			}

			$products = array();
			if ( $has_product_fields ) {
				$products = GFCommon::get_product_fields( $form, $lead );
				if ( ! empty( $products['products'] ) ) {
					?>
					<tr>
						<td colspan="2" class="entry-view-field-name"><?php echo esc_html( gf_apply_filters( array( 'gform_order_label', $form_id ), __( 'Order', 'gravityforms' ), $form_id ) ); ?></td>
					</tr>
					<tr>
						<td colspan="2" class="entry-view-field-value lastrow">
							<table class="entry-products" cellspacing="0" width="97%">
								<colgroup>
									<col class="entry-products-col1" />
									<col class="entry-products-col2" />
									<col class="entry-products-col3" />
									<col class="entry-products-col4" />
								</colgroup>
								<thead>
								<th scope="col"><?php echo gf_apply_filters( array( 'gform_product', $form_id ), __( 'Product', 'gravityforms' ), $form_id ); ?></th>
								<th scope="col" class="textcenter"><?php echo esc_html( gf_apply_filters( array( 'gform_product_qty', $form_id ), __( 'Qty', 'gravityforms' ), $form_id ) ); ?></th>
								<th scope="col"><?php echo esc_html( gf_apply_filters( array( 'gform_product_unitprice', $form_id ), __( 'Unit Price', 'gravityforms' ), $form_id ) ); ?></th>
								<th scope="col"><?php echo esc_html( gf_apply_filters( array( 'gform_product_price', $form_id ), __( 'Price', 'gravityforms' ), $form_id ) ); ?></th>
								</thead>
								<tbody>
								<?php

								$total = 0;
								foreach ( $products['products'] as $product ) {
									?>
									<tr>
										<td>
											<div class="product_name"><?php echo esc_html( $product['name'] ); ?></div>
											<ul class="product_options">
												<?php
												$price = GFCommon::to_number( $product['price'] );
												if ( is_array( rgar( $product, 'options' ) ) ) {
													$count = sizeof( $product['options'] );
													$index = 1;
													foreach ( $product['options'] as $option ) {
														$price += GFCommon::to_number( $option['price'] );
														$class = $index == $count ? " class='lastitem'" : '';
														$index ++;
														?>
														<li<?php echo $class ?>><?php echo $option['option_label'] ?></li>
													<?php
													}
												}
												$subtotal = floatval( $product['quantity'] ) * $price;
												$total += $subtotal;
												?>
											</ul>
										</td>
										<td class="textcenter"><?php echo esc_html( $product['quantity'] ); ?></td>
										<td><?php echo GFCommon::to_money( $price, $lead['currency'] ) ?></td>
										<td><?php echo GFCommon::to_money( $subtotal, $lead['currency'] ) ?></td>
									</tr>
								<?php
								}
								$total += floatval( $products['shipping']['price'] );
								?>
								</tbody>
								<tfoot>
								<?php
								if ( ! empty( $products['shipping']['name'] ) ) {
									?>
									<tr>
										<td colspan="2" rowspan="2" class="emptycell">&nbsp;</td>
										<td class="textright shipping"><?php echo esc_html( $products['shipping']['name'] ); ?></td>
										<td class="shipping_amount"><?php echo GFCommon::to_money( $products['shipping']['price'], $lead['currency'] ) ?>&nbsp;</td>
									</tr>
								<?php
								}
								?>
								<tr>
									<?php
									if ( empty( $products['shipping']['name'] ) ) {
										?>
										<td colspan="2" class="emptycell">&nbsp;</td>
									<?php
									}
									?>
									<td class="textright grandtotal"><?php esc_html_e( 'Total', 'gravityforms' ) ?></td>
									<td class="grandtotal_amount"><?php echo GFCommon::to_money( $total, $lead['currency'] ) ?></td>
								</tr>
								</tfoot>
							</table>
						</td>
					</tr>

				<?php
				}
			}
			?>
			</tbody>
		</table>
	<?php
	}

	public static function entry_detail_pagination_link( $pos, $label = '', $class = '', $icon = '' ) {
		$url = add_query_arg( array( 'pos' => $pos ), remove_query_arg( array( 'pos', 'lid' ) ) );

		$href = ! rgblank( $pos ) ? 'href="' . esc_url( $url ) . '"' : '';
		$class .= ' gf_entry_pagination_link';
		$class .= $pos !== false ? ' gf_entry_pagination_link_active' : ' gf_entry_pagination_link_inactive';

		return '<a ' . $href . ' class="' . $class . '" title="' . esc_attr( $label ) . '"><i class="fa-lg ' . esc_attr( $icon ) . '"></i></a></li>';
	}

	public static function payment_details_box( $lead, $form )
	{
		?>
		<!-- PAYMENT BOX -->
		<div id="submitdiv" class="stuffbox">
			<h3 class="hndle" style="cursor:default;">
                <span><?php echo $lead['transaction_type'] == 2 ? esc_html__( 'Subscription Details', 'gravityforms' ) : esc_html__( 'Payment Details', 'gravityforms' ); ?></span>
			</h3>

			<div class="inside">
				<div id="submitcomment" class="submitbox">
					<div id="minor-publishing" style="padding:10px;">
						<?php

						$payment_status = apply_filters( 'gform_payment_status', $lead['payment_status'], $form, $lead );
						if ( ! empty( $payment_status ) ){
							?>
							<div id="gf_payment_status" class="gf_payment_detail">
								<?php esc_html_e( 'Status', 'gravityforms' ) ?>:
								<span id="gform_payment_status"><?php echo $payment_status; // May contain HTML ?></span>
							</div>

							<?php

							/**
							 * Allows for modification on the form payment date format
							 *
							 * @param array $form The Form object to filter through
							 * @param array $lead The Lead object to filter through
							 */
							$payment_date = apply_filters( 'gform_payment_date', GFCommon::format_date( $lead['payment_date'], false, 'Y/m/d', $lead['transaction_type'] != 2 ), $form, $lead );
							if ( ! empty( $payment_date ) ) {
								?>
								<div id="gf_payment_date" class="gf_payment_detail">
									<?php echo $lead['transaction_type'] == 2 ? esc_html__( 'Start Date', 'gravityforms' ) : esc_html__( 'Date', 'gravityforms' ) ?>:
									<span id='gform_payment_date'><?php echo $payment_date; // May contain HTML ?></span>
								</div>
							<?php
							}

							/**
							 * Allows filtering through a payment transaction ID
							 *
							 * @param int   $lead['transaction_id'] The transaction ID that can be modified
							 * @param array $form                   The Form object to be filtered when modifying the transaction ID
							 * @param array $lead                   The Lead object to be filtered when modifying the transaction ID
							 */
							$transaction_id = apply_filters( 'gform_payment_transaction_id', $lead['transaction_id'], $form, $lead );
							if ( ! empty( $transaction_id ) ) {
								?>
								<div id="gf_payment_transaction_id" class="gf_payment_detail">
									<?php echo $lead['transaction_type'] == 2 ? esc_html__( 'Subscription Id', 'gravityforms' ) : esc_html__( 'Transaction Id', 'gravityforms' ); ?>:
									<span id='gform_payment_transaction_id'><?php echo $transaction_id; // May contain HTML ?></span>
								</div>
							<?php
							}

							/**
							 * Filter through the way the Payment Amount is rendered
							 *
							 * @param string $lead['payment_amount'] The payment amount taken from the lead object
							 * @param string $lead['currency']       The payment currency taken from the lead object
							 * @param array  $form                   The Form object to filter through
							 * @param array  $lead                   The lead object to filter through
							 */
							$payment_amount = apply_filters( 'gform_payment_amount', GFCommon::to_money( $lead['payment_amount'], $lead['currency'] ), $form, $lead );
							if ( ! rgblank( $payment_amount ) ) {
								?>
								<div id="gf_payment_amount" class="gf_payment_detail">
									<?php echo $lead['transaction_type'] == 2 ? esc_html__( 'Recurring Amount', 'gravityforms' ) : esc_html__( 'Amount', 'gravityforms' ); ?>:
									<span id='gform_payment_amount'><?php echo $payment_amount; // May contain HTML ?></span>
								</div>
							<?php
							}
						}

						/**
						 * Fires after the Form Payment Details (The type of payment, the cost, the ID, etc)
						 *
						 * @param int   $form['id'] The current Form ID
						 * @param array $lead       The current Lead object
						 */
						do_action( 'gform_payment_details', $form['id'], $lead );

						?>
					</div>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Helper to determine if empty fields should be displayed when the lead detail grid is processed.
	 *
	 * @param bool $allow_display_empty_fields Determines if the value of the 'show empty fields' checkbox should be used. True when viewing the entry and false when in edit mode.
	 * @param array $form The Form object for the current Entry.
	 * @param array $lead The current Entry object.
	 *
	 * @return bool
	 */
	public static function maybe_display_empty_fields( $allow_display_empty_fields, $form, $lead ) {
		$display_empty_fields = false;
		if ( $allow_display_empty_fields ) {
			$display_empty_fields = rgget( 'gf_display_empty_fields', $_COOKIE );
		}

		/**
		 * A filter to determine if empty fields should be displayed in the entry details.
		 *
		 * @param bool $display_empty_fields True or false to show the fields
		 * @param array $form The Form object to filter
		 * @param array $lead The Entry object to filter
		 */
		return apply_filters( 'gform_entry_detail_grid_display_empty_fields', $display_empty_fields, $form, $lead );
	}
}