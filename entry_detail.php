<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFEntryDetail {

	/**
	 * The current entry array.
	 *
	 * @var null|array
	 */
	private static $_entry = null;

	/**
	 * The current form object.
	 *
	 * @var null|form
	 */
	private static $_form = null;

	/**
	 * The total number of entries in the current filter sent from the entry list.
	 *
	 * @var int
	 */
	private static $_total_count = 0;

	/**
	 * Prepare meta boxes and screen options.
	 */
	public static function add_meta_boxes() {

		$entry = self::get_current_entry();
		if ( is_wp_error( $entry ) ) {
			return;
		}

		$meta_boxes = array(
			'submitdiv'     => array(
				'title'    => esc_html__( 'Entry', 'gravityforms' ),
				'callback' => array( 'GFEntryDetail', 'meta_box_entry_info' ),
				'context'  => 'side',
			),
		);

		if ( GFCommon::current_user_can_any( 'gravityforms_edit_entry_notes' ) ) {
			$meta_boxes['notifications'] = array(
				'title'    => esc_html__( 'Notifications', 'gravityforms' ),
				'callback' => array( 'GFEntryDetail', 'meta_box_notifications' ),
				'context'  => 'side',
			);
		}

		if ( GFCommon::current_user_can_any( 'gravityforms_view_entry_notes' ) ) {
			$meta_boxes['notes'] = array(
				'title'    => esc_html__( 'Notes', 'gravityforms' ),
				'callback' => array( 'GFEntryDetail', 'meta_box_notes' ),
				'context'  => 'normal',
			);
		}

		if ( ! empty( $entry['payment_status'] ) ) {
			$meta_boxes['payment'] = array(
				'title'    => $entry['transaction_type'] == 2 ? esc_html__( 'Subscription Details', 'gravityforms' ) : esc_html__( 'Payment Details', 'gravityforms' ),
				'callback' => array( 'GFEntryDetail', 'meta_box_payment_details' ),
				'context'  => 'side',
			);
		}

		$form = self::get_current_form();

		/**
		 * Allow custom meta boxes to be added to the entry detail page.
		 *
		 * @since 2.0-beta-3
		 *
		 * @param array $meta_boxes The properties for the meta boxes.
		 * @param array $entry      The entry currently being viewed/edited.
		 * @param array $form       The form object used to process the current entry.
		 */
		$meta_boxes = apply_filters( 'gform_entry_detail_meta_boxes', $meta_boxes, $entry, $form );

		foreach ( $meta_boxes as $id => $meta_box ) {
			$screen = get_current_screen();
			add_meta_box(
				$id,
				$meta_box['title'],
				$meta_box['callback'],
				$screen->id,
				$meta_box['context'],
				isset( $meta_box['priority'] ) ? $meta_box['priority'] : 'default',
				isset( $meta_box['callback_args'] ) ? $meta_box['callback_args'] : null
			);
		}
	}

	public static function get_current_form() {

		if ( isset( self::$_form ) ) {
			return  self::$_form;
		}

		$form = GFFormsModel::get_form_meta( absint( $_GET['id'] ) );

		$form_id = absint( $form['id'] );

		$form    = apply_filters( 'gform_admin_pre_render', $form );
		$form    = apply_filters( 'gform_admin_pre_render_' . $form_id, $form );

		self::set_current_form( $form );

		return $form;
	}

	/**
	 * Caches the current form.
	 *
	 * @since 2.4.4.1
	 *
	 * @param array $form The form to be cached.
	 */
	public static function set_current_form( $form ) {
		self::$_form = $form;
	}

	public static function get_current_entry() {
		if ( isset( self::$_entry ) ) {
			return self::$_entry;
		}
		$form    = self::get_current_form();
		$form_id = absint( $form['id'] );
		$lead_id = rgpost( 'entry_id' ) ? absint( rgpost( 'entry_id' ) ) : absint( rgget( 'lid' ) );

		$filter = rgget( 'filter' );
		$status = in_array( $filter, array( 'trash', 'spam' ) ) ? $filter : 'active';

		$position       = rgget( 'pos' ) ? rgget( 'pos' ) : 0;
		$sort_direction = rgget( 'order' ) ? rgget( 'order' ) : 'DESC';

		$sort_field      = empty( $_GET['orderby'] ) ? 0 : $_GET['orderby'];
		$sort_field_meta = RGFormsModel::get_field( $form, $sort_field );
		$is_numeric      = rgar( $sort_field_meta, 'type' ) == 'number';

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
		 * @since 1.9.14.30
		 *
		 * @param array $search_criteria An array containing the search criteria.
		 * @param int   $form_id         The ID of the current form.
		 */
		$search_criteria = gf_apply_filters( array( 'gform_search_criteria_entry_list', $form_id ), $search_criteria, $form_id );

		$paging = array( 'offset' => $position, 'page_size' => 1 );

		if ( ! empty( $sort_field ) ) {
			$sorting = array( 'key' => $sort_field, 'direction' => $sort_direction, 'is_numeric' => $is_numeric );
		} else {
			$sorting = array();
		}

		$leads = GFAPI::get_entries( $form['id'], $search_criteria, $sorting, $paging, self::$_total_count );

		if ( ! $lead_id ) {
			$lead = ! empty( $leads ) ? $leads[0] : false;
		} else {
			$lead = GFAPI::get_entry( $lead_id );
		}

		self::set_current_entry( $lead );

		return $lead;
	}

	public static function set_current_entry( $entry ) {
		self::$_entry = $entry;
	}

	public static function get_total_count() {
		return self::$_total_count;
	}

	public static function lead_detail_page() {
		global $current_user;

		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		echo GFCommon::get_remote_message();

		$requested_form_id = absint( $_GET['id'] );
		if ( empty( $requested_form_id ) ) {
			return;
		}

		$lead = self::get_current_entry();
		if ( is_wp_error( $lead ) || ! $lead ) {
			esc_html_e( "Oops! We couldn't find your entry. Please try again", 'gravityforms' );

			return;
		}

		$lead_id  = $lead['id'];
		$form     = self::get_current_form();
		$form_id  = absint( $form['id'] );

		/**
		 * Fires before the entry detail page is shown or any processing is handled.
		 *
		 * @param array $form The form object for the entry.
		 * @param array $lead The entry object.
		 *
		 * @since 2.3.3.9
		 */
		gf_do_action( array( 'gform_pre_entry_detail', $form['id'] ), $form, $lead );

		$total_count = self::get_total_count();
		$position    = rgget( 'pos' ) ? rgget( 'pos' ) : 0;
		$prev_pos    = ! rgblank( $position ) && $position > 0 ? $position - 1 : false;
		$next_pos    = ! rgblank( $position ) && $position < self::$_total_count - 1 ? $position + 1 : false;

		$filter = rgget( 'filter' );

		// unread filter requires special handling for pagination since entries are filter out of the query as they are read
		if ( $filter == 'unread' ) {
			$next_pos = $position;

			if ( $next_pos + 1 == $total_count ) {
				$next_pos = false;
			}
		}

		GFFormsModel::update_entry_property( $lead['id'], 'is_read', 1 );

		switch ( RGForms::post( 'action' ) ) {
			case 'update' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

				$original_entry = $lead;

				// Set files that have been uploaded to temp folder
				GFFormsModel::set_uploaded_files( $form_id );

				GFFormsModel::save_lead( $form, $lead );

				/**
				 * Fires after the Entry is updated from the entry detail page.
				 *
				 * @param array   $form           The form object for the entry.
				 * @param integer $lead['id']     The entry ID.
				 * @param array   $original_entry The entry object before being updated.
				 */
				gf_do_action( array( 'gform_after_update_entry', $form['id'] ), $form, $lead['id'], $original_entry );

				$lead = GFFormsModel::get_entry( $lead['id'] );
				$lead = GFFormsModel::set_entry_meta( $lead, $form );
				self::set_current_entry( $lead );

				// Check if there's consent field, and values updated.
				if ( GFCommon::has_consent_field( $form ) ) {
					$user_data           = get_userdata( $current_user->ID );
					$consent_update_note = '';

					foreach ( $form['fields'] as $field ) {
						if ( $field['type'] === 'consent' ) {
							$field_obj             = GFFormsModel::get_field( $form, $field['id'] );
							$revision_id           = GFFormsModel::get_latest_form_revisions_id( $form['id'] );
							$current_description   = $field_obj->get_field_description_from_revision( $revision_id );
							$submitted_description = $field_obj->get_field_description_from_revision( $original_entry[ $field['id'] . '.3' ] );

							if ( $lead[ $field['id'] . '.1' ] !== $original_entry[ $field['id'] . '.1' ] || $field['checkboxLabel'] !== $original_entry[ $field['id'] . '.2' ] || $current_description !== $submitted_description ) {
								if ( ! empty( $consent_update_note ) ) {
									$consent_update_note .= "\n";
								}
								$consent_update_note .= empty( $lead[ $field['id'] . '.1' ] ) ? sprintf( esc_html__( '%s: Unchecked "%s"', 'gravityforms' ), GFCommon::get_label( $field ), wp_strip_all_tags( $original_entry[ $field['id'] . '.2' ] ) ) : sprintf( esc_html__( '%s: Checked "%s"', 'gravityforms' ), GFCommon::get_label( $field ), wp_strip_all_tags( $lead[ $field['id'] . '.2' ] ) );
							}
						}
					}

					if ( ! empty( $consent_update_note ) ) {
						GFFormsModel::add_note( $lead['id'], $current_user->ID, $user_data->display_name, $consent_update_note );
					}
				}

				break;

			case 'add_note' :
				check_admin_referer( 'gforms_update_note', 'gforms_update_note' );
				$user_data = get_userdata( $current_user->ID );
				GFFormsModel::add_note( $lead['id'], $current_user->ID, $user_data->display_name, stripslashes( $_POST['new_note'] ) );

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
				GFFormsModel::add_note( $lead['id'], $current_user->ID, $user_data->display_name, stripslashes( $_POST['quick_note'] ) );
				break;

			case 'bulk' :
				check_admin_referer( 'gforms_update_note', 'gforms_update_note' );
				if ( $_POST['bulk_action'] == 'delete' ) {
					if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_entry_notes' ) ) {
						wp_die( esc_html__( "You don't have adequate permission to delete notes.", 'gravityforms' ) );
					}
					GFFormsModel::delete_notes( $_POST['note'] );
				}
				break;

			case 'trash' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				if ( ! GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
					wp_die( esc_html__( "You don't have adequate permission to trash entries.", 'gravityforms' ) );
				}
				GFFormsModel::update_entry_property( $lead['id'], 'status', 'trash' );
				$admin_url = admin_url( 'admin.php?page=gf_entries&view=entries&id=' . absint( $form['id'] ) . '&trashed_entry=' . absint( $lead['id'] ) );
				?>
				<script type="text/javascript">
					document.location.href = <?php echo json_encode( $admin_url ); ?>;
				</script>
				<?php
				break;

			case 'restore' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				if ( ! GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
					wp_die( esc_html__( "You don't have adequate permission to restore entries.", 'gravityforms' ) );
				}
				GFFormsModel::update_entry_property( $lead['id'], 'status', 'active' );
				$lead = RGFormsModel::get_lead( $lead['id'] );
				self::set_current_entry( $lead );
				break;

			case 'unspam' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				GFFormsModel::update_entry_property( $lead['id'], 'status', 'active' );
				$lead = GFFormsModel::get_entry( $lead['id'] );
				self::set_current_entry( $lead );
				break;

			case 'spam' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				GFFormsModel::update_entry_property( $lead['id'], 'status', 'spam' );
				$lead = GFFormsModel::get_entry( $lead['id'] );
				self::set_current_entry( $lead );
				break;

			case 'delete' :
				check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );
				if ( ! GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
					wp_die( esc_html__( "You don't have adequate permission to delete entries.", 'gravityforms' ) );
				}
				GFFormsModel::delete_entry( $lead['id'] );
				$admin_url = admin_url( 'admin.php?page=gf_entries&view=entries&id=' . absint( $form['id'] ) . '&deleted=' . absint( $lead['id'] ) );
				?>
				<script type="text/javascript">
					document.location.href = <?php echo json_encode( $admin_url ); ?>;
				</script>
				<?php
				break;
		} // End switch().

		$mode = empty( $_POST['screen_mode'] ) ? 'view' : $_POST['screen_mode'];

		$screen = get_current_screen();

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		?>
		<link rel="stylesheet" href="<?php echo GFCommon::get_base_url() ?>/css/admin<?php echo $min; ?>.css?ver=<?php echo GFForms::$version ?>" />
		<script type="text/javascript">

			jQuery(document).ready(function () {
				toggleNotificationOverride(true);
				jQuery('#gform_update_button').prop('disabled', false);
				if(typeof postboxes != 'undefined'){
					jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
					postboxes.add_postbox_toggles( <?php echo json_encode( $screen->id ); ?>);
				}
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

				var $input = jQuery( 'input[name="input_' + fieldId + '"]' ),
					files  = jQuery.parseJSON( $input.val() );

				delete files[ fileIndex ];
				$input.val( jQuery.toJSON( files ) );

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
					displayMessage(<?php echo json_encode( __( 'You must select at least one type of notification to resend.', 'gravityforms' ) ); ?>, 'error', '#notifications');
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
							displayMessage(response, "error", "#notifications");
						} else {
							displayMessage(<?php echo json_encode( esc_html__( 'Notifications were resent successfully.', 'gravityforms' ) ); ?>, "updated", "#notifications" );

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
		<?php
		$editable_class = GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ? ' gform_settings_page_title_editable' : '';
		?>
		<form method="post" id="entry_form" enctype='multipart/form-data'>
			<?php wp_nonce_field( 'gforms_save_entry', 'gforms_save_entry' ) ?>
			<input type="hidden" name="action" id="action" value="" />
			<input type="hidden" name="screen_mode" id="screen_mode" value="<?php echo esc_attr( rgpost( 'screen_mode' ) ) ?>" />

			<input type="hidden" name="entry_id" id="entry_id" value="<?php echo absint( $lead['id'] ) ?>" />

			<div class="wrap gf_entry_wrap">
				<h2 class="gf_admin_page_title">
					<span id='gform_settings_page_title' class='gform_settings_page_title<?php echo $editable_class?>' onclick='GF_ShowEditTitle()'><?php echo esc_html( rgar( $form, 'title' ) ); ?></span>
					<?php GFForms::form_switcher(); ?>
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

					<span class="gf_admin_page_subtitle">
						<span class="gf_admin_page_formid">ID: <?php echo absint( $form['id'] ); ?></span>
					</span>

					<?php
					$gf_entry_locking = new GFEntryLocking();
					$gf_entry_locking->lock_info( $lead_id ); ?>
				</h2>
				<?php GFForms::edit_form_title( $form ); ?>

				<?php GFCommon::display_dismissible_message(); ?>

				<?php RGForms::top_toolbar() ?>

				<div id="poststuff">
					<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
					<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>


					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<?php
							/**
							 * Fires before the entry detail content is displayed
							 *
							 * @param array $form The Form object
							 * @param array $lead The Entry object
							 */
							do_action( 'gform_entry_detail_content_before', $form, $lead );

							if ( 'edit' === $mode && GFCommon::current_user_can_any( 'gravityforms_edit_entries' ) ) {
								self::lead_detail_edit( $form, $lead );
							} else {
								self::lead_detail_grid( $form, $lead, true );
							}

							/**
							 * Fires when entry details are displayed
							 *
							 * @param array $form The Form object
							 * @param array $lead The Entry object
							 */
							do_action( 'gform_entry_detail', $form, $lead );
							?>
						</div>

						<div id="postbox-container-1" class="postbox-container">

							<?php
							/**
							 * Fires before the entry detail sidebar is generated
							 *
							 * @param array $form The Form object
							 * @param array $lead The Entry object
							 */
							do_action( 'gform_entry_detail_sidebar_before', $form, $lead );
							?>
							<?php

							do_meta_boxes( $screen->id, 'side', array( 'form' => $form, 'entry' => $lead, 'mode' => $mode ) ); ?>

							<?php
							/**
							 * Inserts information into the middle of the entry detail sidebar
							 *
							 * @param array $form The Form object
							 * @param array $lead The Entry object
							 */
							do_action( 'gform_entry_detail_sidebar_middle', $form, $lead );

							?>

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

						<div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes( $screen->id, 'normal', array( 'form' => $form, 'entry' => $lead, 'mode' => $mode ) ); ?>
							<?php

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
			<div class="updated fade">
				<p><?php esc_html_e( 'Entry Updated.', 'gravityforms' ); ?></p>
			</div>
			<?php
		}
	}

	public static function lead_detail_edit( $form, $lead ) {
		$form_id = absint( $form['id'] );

		if ( empty( $form_id ) ) {
			return;
		}
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
								$value = RGFormsModel::get_lead_field_value( $lead, $field );
								$td_id = 'field_' . $form_id . '_' . $field_id;
								$td_id = esc_attr( $td_id );

								if ( is_array( $field->fields ) ) {
									// Ensure the top level repeater has the right nesting level so the label is not duplicated.
									$field->nestingLevel = 0;
									$field_label = '';
								} else {
									$field_label = "<label class='detail-label'>" . esc_html( GFCommon::get_label( $field ) ) . '</label>';
								}

								$content = "<tr valign='top'><td class='detail-view' id='{$td_id}'>" .
								           $field_label .
								           GFCommon::get_field_input( $field, $value, $lead['id'], $form_id, $form ) .
								           '</td></tr>';

								break;
						}

						/**
						 * Filters the field content.
						 *
						 * @since 2.1.2.14 Added form and field ID modifiers.
						 *
						 * @param string $content    The field content.
						 * @param array  $field      The Field Object.
						 * @param string $value      The field value.
						 * @param int    $lead['id'] The entry ID.
						 * @param int    $form['id'] The form ID.
						 */
						$content = gf_apply_filters( array( 'gform_field_content', $form['id'], $field->id ), $content, $field, $value, $lead['id'], $form['id'] );

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
					<th id="notes"><?php esc_html_e( 'Notes', 'gravityforms' ) ?></th>
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

				// Prepare note classes.
				$classes = array();

				// Add base note class.
				if ( $note->note_type ) {
					$classes[] = sprintf( 'gforms_note_%s', $note->note_type );
				}

				// Add sub type note class.
				if ( rgobj( $note, 'sub_type' ) ) {
					$classes[] = sprintf( 'gforms_note_%s', $note->sub_type );
				}

				// Escape note classes.
				$classes = array_map( 'esc_attr', $classes );

				?>
				<tr valign="top" data-id="<?php echo esc_attr( $note->id ); ?>" data-type="<?php echo esc_attr( $note->note_type ); ?>"<?php echo rgobj( $note, 'sub_type' ) ? ' data-sub-type="' . esc_attr( $note->sub_type ) . '"' : ''; ?>>
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
						?>
						<div class="note-meta-container">
							<div class="note-avatar">
								<?php

								if ( $note->user_id ) {
									$avatar = get_avatar( $note->user_id, 48 );
								} else {
									$avatar = sprintf(
										'<img src="%s" alt="%s" />',
										GFCommon::get_base_url() . '/images/note-placeholder.svg',
										esc_attr( $note->user_name )
									);
								}

								/**
								 * Allows filtering of the notes avatar
								 *
								 * @param array $note The Note object that is being filtered when modifying the avatar
								 */
								echo apply_filters( 'gform_notes_avatar', $avatar, $note ); ?>
							</div>
							<div class="note-meta">
								<h6 class="note-author"><?php echo esc_html( $note->user_name ) ?></h6>
								<?php if ( $note->user_email ): ?><a href="mailto:<?php echo esc_attr( $note->user_email ) ?>" class="note-email"><?php echo esc_html( $note->user_email ) ?></a><?php endif; ?><br />
								<time class="note-date"><?php esc_html_e( 'added', 'gravityforms' ); ?> <?php echo esc_html( GFCommon::format_date( $note->date_created, true ) ) ?></time>
							</div>
						</div>
						<div class="detail-note-content <?php echo implode( ' ', $classes ); ?>"><?php echo nl2br( esc_html( $note->value ) ) ?></div>
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

		if ( empty( $form_id ) ) {
			return;
		}

		$display_empty_fields = self::maybe_display_empty_fields( $allow_display_empty_fields, $form, $lead );


		?>
		<table cellspacing="0" class="widefat fixed entry-detail-view">
			<thead>
			<tr>
				<th id="details">
					<?php
					$title = sprintf( '%s : %s %s', esc_html( $form['title'] ), esc_html__( 'Entry # ', 'gravityforms' ), absint( $lead['id'] ) );
					/**
					 * Filters the title displayed on the entry detail page.
					 *
					 * @since 1.9
					 *
					 * @param string $title The title used.
					 * @param array  $form  The Form Object.
					 * @param array  $entry The Entry Object.
					 */
					echo apply_filters( 'gform_entry_detail_title', $title, $form, $lead );
					?>
				</th>
				<th style="width:auto; font-size:10px; text-align: right;">
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
						// Ignore captcha, html, password, page field.
						break;

					default :
						// Ignore product fields as they will be grouped together at the end of the grid.
						if ( GFCommon::is_product_field( $field->type ) ) {
							$has_product_fields = true;
							break;
						}

						$value = RGFormsModel::get_lead_field_value( $lead, $field );

						if ( is_array( $field->fields ) ) {
							// Ensure the top level repeater has the right nesting level so the label is not duplicated.
							$field->nestingLevel = 0;
						}

						$display_value = GFCommon::get_lead_field_display( $field, $value, $lead['currency'] );

						/**
						 * Filters a field value displayed within an entry.
						 *
						 * @since 1.5
						 *
						 * @param string   $display_value The value to be displayed.
						 * @param GF_Field $field         The Field Object.
						 * @param array    $lead          The Entry Object.
						 * @param array    $form          The Form Object.
						 */
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

				/**
				 * Filters the field content.
				 *
				 * @since 2.1.2.14 Added form and field ID modifiers.
				 *
				 * @param string $content    The field content.
				 * @param array  $field      The Field Object.
				 * @param string $value      The field value.
				 * @param int    $lead['id'] The entry ID.
				 * @param int    $form['id'] The form ID.
				 */
				$content = gf_apply_filters( array( 'gform_field_content', $form['id'], $field->id ), $content, $field, $value, $lead['id'], $form['id'] );

				echo $content;
			}

			$products = array();
			if ( $has_product_fields ) {
				$products = GFCommon::get_product_fields( $form, $lead, false, true );
				if ( ! empty( $products['products'] ) ) {
				    ob_start();
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
												$price = GFCommon::to_number( $product['price'], $lead['currency'] );
												if ( is_array( rgar( $product, 'options' ) ) ) {
													$count = sizeof( $product['options'] );
													$index = 1;
													foreach ( $product['options'] as $option ) {
														$price += GFCommon::to_number( $option['price'], $lead['currency'] );
														$class = $index == $count ? " class='lastitem'" : '';
														$index ++;
														?>
														<li<?php echo $class ?>><?php echo $option['option_label'] ?></li>
														<?php
													}
												}
												$quantity = GFCommon::to_number( $product['quantity'], $lead['currency'] );

												$subtotal = $quantity * $price;
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
					/**
					 * Filter the markup of the order summary which appears on the Entry Detail, the {all_fields} merge tag and the {pricing_fields} merge tag.
                     *
                     * @since 2.1.2.5
                     * @see   https://docs.gravityforms.com/gform_order_summary/
					 *
					 * @var string $markup          The order summary markup.
					 * @var array  $form            Current form object.
					 * @var array  $lead            Current entry object.
					 * @var array  $products        Current order summary object.
					 * @var string $format          Format that should be used to display the summary ('html' or 'text').
					 */
                    $order_summary = gf_apply_filters( array( 'gform_order_summary', $form['id'] ), trim( ob_get_clean() ), $form, $lead, $products, 'html' );
                    echo $order_summary;
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

		return '<a ' . $href . ' class="' . $class . '" aria-label="' . esc_attr( $label ) . '"><i aria-hidden="true" class="fa-lg ' . esc_attr( $icon ) . '" title="' . esc_attr( $label ) . '"></i></a>';
	}

	public static function payment_details_box( $entry, $form ) {
		_deprecated_function( __function__, '2.0', 'Use add_meta_box() with GFEntryDetail::meta_box_payment_details as the "callback" parameter.' );
		?>
		<!-- PAYMENT BOX -->
		<div id="submitdiv" class="stuffbox">

			<h3 class="hndle" style="cursor:default;">
				<span><?php echo $entry['transaction_type'] == 2 ? esc_html__( 'Subscription Details', 'gravityforms' ) : esc_html__( 'Payment Details', 'gravityforms' ); ?></span>
			</h3>

			<div class="inside">
				<?php self::meta_box_payment_details( compact( 'entry', 'form' ) ); ?>
			</div>

		</div>
		<?php
	}

	public static function meta_box_payment_details( $args ) {

		$entry = $args['entry'];
		$form  = $args['form'];

		?>

		<div id="submitcomment" class="submitbox">
			<div id="minor-publishing">
				<?php

				$payment_status = apply_filters( 'gform_payment_status', GFCommon::get_entry_payment_status_text( $entry['payment_status'] ), $form, $entry );
				if ( ! empty( $payment_status ) ) {
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
					 * @param array $entry The Lead object to filter through
					 */
					$payment_date = apply_filters( 'gform_payment_date', GFCommon::format_date( $entry['payment_date'], false, 'Y/m/d', $entry['transaction_type'] != 2 ), $form, $entry );
					if ( ! empty( $payment_date ) ) {
						?>
						<div id="gf_payment_date" class="gf_payment_detail">
							<?php echo $entry['transaction_type'] == 2 ? esc_html__( 'Start Date', 'gravityforms' ) : esc_html__( 'Date', 'gravityforms' ) ?>:
							<span id='gform_payment_date'><?php echo $payment_date; // May contain HTML ?></span>
						</div>
						<?php
					}

					/**
					 * Allows filtering through a payment transaction ID
					 *
					 * @param int   $entry['transaction_id'] The transaction ID that can be modified
					 * @param array $form                   The Form object to be filtered when modifying the transaction ID
					 * @param array $entry                   The Lead object to be filtered when modifying the transaction ID
					 */
					$transaction_id = apply_filters( 'gform_payment_transaction_id', $entry['transaction_id'], $form, $entry );
					if ( ! empty( $transaction_id ) ) {
						?>
						<div id="gf_payment_transaction_id" class="gf_payment_detail">
							<?php echo $entry['transaction_type'] == 2 ? esc_html__( 'Subscription Id', 'gravityforms' ) : esc_html__( 'Transaction Id', 'gravityforms' ); ?>:
							<span id='gform_payment_transaction_id'><?php echo $transaction_id; // May contain HTML ?></span>
						</div>
						<?php
					}

					/**
					 * Filter through the way the Payment Amount is rendered
					 *
					 * @param string $entry['payment_amount'] The payment amount taken from the lead object
					 * @param string $entry['currency']       The payment currency taken from the lead object
					 * @param array  $form                   The Form object to filter through
					 * @param array  $entry                   The lead object to filter through
					 */
					$payment_amount = apply_filters( 'gform_payment_amount', GFCommon::to_money( $entry['payment_amount'], $entry['currency'] ), $form, $entry );
					if ( ! rgblank( $payment_amount ) ) {
						?>
						<div id="gf_payment_amount" class="gf_payment_detail">
							<?php echo $entry['transaction_type'] == 2 ? esc_html__( 'Recurring Amount', 'gravityforms' ) : esc_html__( 'Amount', 'gravityforms' ); ?>:
							<span id='gform_payment_amount'><?php echo $payment_amount; // May contain HTML ?></span>
						</div>
						<?php
					}
				}

				/**
				 * Fires after the Form Payment Details (The type of payment, the cost, the ID, etc)
				 *
				 * @param int   $form['id'] The current Form ID
				 * @param array $entry       The current Lead object
				 */
				do_action( 'gform_payment_details', $form['id'], $entry );

				?>
			</div>
		</div>

		<?php
	}

	public static function meta_box_notes( $args, $metabox ) {
		$entry = $args['entry'];
		$form  = $args['form'];
		?>
		<form method="post">
			<?php wp_nonce_field( 'gforms_update_note', 'gforms_update_note' ) ?>
			<div class="inside">
				<?php
				$notes = RGFormsModel::get_lead_notes( $entry['id'] );

				//getting email values
				$email_fields = GFCommon::get_email_fields( $form );
				$emails = array();

				foreach ( $email_fields as $email_field ) {
					if ( ! empty( $entry[ $email_field->id ] ) ) {
						$emails[] = $entry[ $email_field->id ];
					}
				}
				//displaying notes grid
				$subject = '';
				self::notes_grid( $notes, true, $emails, $subject );
				?>
			</div>
		</form>
		<?php
	}

	public static function meta_box_entry_info( $args, $metabox ) {
		$form  = $args['form'];
		$entry = $args['entry'];
		$mode  = $args['mode'];
		?>
		<div id="submitcomment" class="submitbox">
			<div id="minor-publishing" style="padding:10px;">
				<?php esc_html_e( 'Entry Id', 'gravityforms' ); ?>: <?php echo absint( $entry['id'] ) ?><br /><br />
				<?php esc_html_e( 'Submitted on', 'gravityforms' ); ?>: <?php echo esc_html( GFCommon::format_date( $entry['date_created'], false, 'Y/m/d' ) ) ?>
				<br /><br />
				<?php
				if ( ! empty( $entry['date_updated'] ) && $entry['date_updated'] != $entry['date_created'] ) {
					esc_html_e( 'Updated', 'gravityforms' ); ?>: <?php echo esc_html( GFCommon::format_date( $entry['date_updated'], false, 'Y/m/d' ) );
					echo '<br /><br />';
				}

				if ( ! empty( $entry['ip'] ) ) {
					esc_html_e( 'User IP', 'gravityforms' ); ?>: <?php echo esc_html( $entry['ip'] );
					echo '<br /><br />';
				}

				if ( ! empty( $entry['created_by'] ) && $usermeta = get_userdata( $entry['created_by'] ) ) {
					?>
					<?php esc_html_e( 'User', 'gravityforms' ); ?>:
					<a href="user-edit.php?user_id=<?php echo absint( $entry['created_by'] ) ?>"><?php echo esc_html( $usermeta->user_login ) ?></a>
					<br /><br />
					<?php
				}

				esc_html_e( 'Embed Url', 'gravityforms' ); ?>:
				<a href="<?php echo esc_url( $entry['source_url'] ) ?>" target="_blank">.../<?php echo esc_html( GFCommon::truncate_url( $entry['source_url'] ) ) ?></a>
				<br /><br />
				<?php
				if ( ! empty( $entry['post_id'] ) ) {
					$post = get_post( $entry['post_id'] );
					?>
					<?php esc_html_e( 'Edit Post', 'gravityforms' ); ?>:
					<a href="post.php?action=edit&post=<?php echo absint( $post->ID ) ?>"><?php echo esc_html( $post->post_title ) ?></a>
					<br /><br />
					<?php
				}

				/**
				 * Adds additional information to the entry details
				 *
				 * @param int   $form['id'] The form ID
				 * @param array $lead       The Entry object
				 */
				do_action( 'gform_entry_info', $form['id'], $entry );

				?>
			</div>
			<div id="major-publishing-actions">
				<div id="delete-action">
					<?php
					switch ( $entry['status'] ) {
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
							if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
							?>
								<a onclick="jQuery('#action').val('restore'); jQuery('#entry_form').submit()" href="#"><?php esc_html_e( 'Restore', 'gravityforms' ) ?></a>
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
					if ( GFCommon::current_user_can_any( 'gravityforms_edit_entries' ) && $entry['status'] != 'trash' ) {
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
		<?php
	}

	public static function meta_box_notifications( $args, $metabox ){
		$form    = $args['form'];
		$form_id = $form['id'];

		if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_entry_notes' ) ) {
			return;
		}
		?>

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

		<?php
	}

	/**
	 * Helper to determine if empty fields should be displayed when the lead detail grid is processed.
	 *
	 * @param bool $allow_display_empty_fields Determines if the value of the 'show empty fields' checkbox should be used. True when viewing the entry and false when in edit mode.
	 * @param array $form The Form object for the current Entry.
	 * @param array|bool $lead The current Entry object or false.
	 *
	 * @return bool
	 */
	public static function maybe_display_empty_fields( $allow_display_empty_fields, $form, $lead = false ) {
		$display_empty_fields = false;
		if ( $allow_display_empty_fields ) {
			$display_empty_fields = (bool) rgget( 'gf_display_empty_fields', $_COOKIE );
		}

		if ( ! $lead ) {
			$lead = self::get_current_entry();
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
