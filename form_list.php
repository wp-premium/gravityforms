<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFFormList {

	public static function form_list_page() {
		global $wpdb;

		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		echo GFCommon::get_remote_message();

		$action      = RGForms::post( 'action' );
		$bulk_action = RGForms::post( 'bulk_action' );
		$bulk_action = ! empty( $bulk_action ) ? $bulk_action : RGForms::post( 'bulk_action2' );

		if ( $action == 'trash' ) {
			check_admin_referer( 'gforms_update_forms', 'gforms_update_forms' );
			$form_id = RGForms::post( 'action_argument' );
			RGFormsModel::trash_form( $form_id );
			$message = __( 'Form moved to the trash.', 'gravityforms' );
		} else if ( $action == 'restore' ) {
			check_admin_referer( 'gforms_update_forms', 'gforms_update_forms' );
			$form_id = RGForms::post( 'action_argument' );
			RGFormsModel::restore_form( $form_id );
			$message = __( 'Form restored.', 'gravityforms' );
		} else if ( $action == 'delete' ) {
			check_admin_referer( 'gforms_update_forms', 'gforms_update_forms' );
			$form_id = RGForms::post( 'action_argument' );
			if ( GFCommon::current_user_can_any( 'gravityforms_delete_forms' ) ) {
				RGFormsModel::delete_form( $form_id );
				$message = __( 'Form deleted.', 'gravityforms' );
			} else {
				$message = __( "You don't have adequate permission to delete forms.", 'gravityforms' );
			}
		} else if ( $action == 'duplicate' ) {
			check_admin_referer( 'gforms_update_forms', 'gforms_update_forms' );
			$form_id = RGForms::post( 'action_argument' );
			RGFormsModel::duplicate_form( $form_id );
			$message = __( 'Form duplicated.', 'gravityforms' );
		}

		if ( $bulk_action ) {

			check_admin_referer( 'gforms_update_forms', 'gforms_update_forms' );
			$form_ids   = is_array( rgpost( 'form' ) ) ? rgpost( 'form' ) : array();
			$form_count = count( $form_ids );

			switch ( $bulk_action ) {
				case 'trash':
					GFFormsModel::trash_forms( $form_ids );
					$message = _n( '%s form moved to the trash.', '%s forms moved to the trash.', $form_count, 'gravityforms' );
					break;
				case 'restore':
					GFFormsModel::restore_forms( $form_ids );
					$message = _n( '%s form restored.', '%s forms restored.', $form_count, 'gravityforms' );
					break;
				case 'delete':
					if ( GFCommon::current_user_can_any( 'gravityforms_delete_forms' ) ) {
						GFFormsModel::delete_forms( $form_ids );
						$message = _n( '%s form deleted.', '%s forms deleted.', $form_count, 'gravityforms' );
					} else {
						$message = __( "You don't have adequate permissions to delete forms.", 'gravityforms' );
					}
					break;
				case 'reset_views':
					foreach ( $form_ids as $form_id ) {
						GFFormsModel::delete_views( $form_id );
					}
					$message = _n( 'Views for %s form have been reset.', 'Views for %s forms have been reset.', $form_count, 'gravityforms' );
					break;
				case 'delete_entries':
					if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
						foreach ( $form_ids as $form_id ) {
							GFFormsModel::delete_leads_by_form( $form_id );
						}
						$message = _n( 'Entries for %s form have been deleted.', 'Entries for %s forms have been deleted.', $form_count, 'gravityforms' );
					} else {
						$message = __( "You don't have adequate permission to delete entries.", 'gravityforms' );
					}

					break;
				case 'activate':
					foreach ( $form_ids as $form_id ) {
						GFFormsModel::update_form_active( $form_id, 1 );
					}
					$message = _n( '%s form has been marked as active.', '%s forms have been marked as active.', $form_count, 'gravityforms' );
					break;
				case 'deactivate':
					foreach ( $form_ids as $form_id ) {
						GFFormsModel::update_form_active( $form_id, 0 );
					}
					$message = _n( '%s form has been marked as inactive.', '%s forms have been marked as inactive.', $form_count, 'gravityforms' );
					break;
			}

			if ( isset( $message ) ) {
				$message = sprintf( $message, $form_count );
			}
		}
		$sort_column    = empty( $_GET['sort'] ) ? 'title' : $_GET['sort'];
		$db_columns = GFFormsModel::get_form_db_columns();

		if ( ! in_array( strtolower( $sort_column ), $db_columns ) ) {
			$sort_column = 'title';
		}

		$sort_direction = empty( $_GET['dir'] ) ? 'ASC' : $_GET['dir'];
		$active         = RGForms::get( 'active' ) == '' ? null : (bool) RGForms::get( 'active' );
		$trash          = RGForms::get( 'trash' ) == '' ? false : (bool) RGForms::get( 'trash' );
		$forms          = RGFormsModel::get_forms( $active, $sort_column, $sort_direction, $trash );

		$form_count = RGFormsModel::get_form_count();

		wp_print_styles( array( 'thickbox' ) );

		add_action( 'admin_print_footer_scripts', array( __class__, 'output_form_list_script_block' ), 20 );

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		?>

		<script type="text/javascript">
			// checked by the ToggleActive method to prevent errors when form status icon is clicked before page has fully loaded
			var gfPageLoaded = false;
		</script>

		<style type="text/css">
			body div#TB_window[style] {
				width: 405px !important;
				height: 340px !important;
				margin-left: -202px !important;
			}

			body #TB_ajaxContent {
				height: 290px !important;
				overflow: hidden;
			}

			.gf_new_form_modal_container {
				padding: 30px;
			}

			.gf_new_form_modal_container .setting-row {
				margin: 0 0 10px;
			}

			.gf_new_form_modal_container .setting-row label {
				line-height: 24px;
			}

			.gf_new_form_modal_container .setting-row input,
			.gf_new_form_modal_container .setting-row textarea {
				display: block;
				width: 100%;
			}

			.gf_new_form_modal_container .setting-row textarea {
				height: 110px;
			}

			.gf_new_form_modal_container .submit-row {
				margin-top: 18px;
			}

			.gf_new_form_modal_container #gf_new_form_error_message {
				margin: 0 0 18px 5px !important;
				color: #BC0B0B;
			}

			.gf_new_form_modal_container img.gfspinner {
				position: relative;
				top: 5px;
				left: 5px;
			}

			.gf_not_ready { opacity: 0.25; }

		</style>

		<div id="gf_new_form_modal" style="display:none;">
			<div class="gf_new_form_modal_container">

				<div class="setting-row">
					<label for="new_form_title"><?php esc_html_e( 'Form Title', 'gravityforms' ); ?>
						<span class="gfield_required">*</span></label><br />
					<input type="text" class="regular-text" value="" id="new_form_title" tabindex="9000">
				</div>

				<div class="setting-row">
					<label for="new_form_description"><?php esc_html_e( 'Form Description', 'gravityforms' ); ?></label><br />
					<textarea class="regular-text" id="new_form_description" tabindex="9001"></textarea>
				</div>

				<div class="submit-row">

					<?php

					/**
					 * Allows for modification of the "New Form" button HTML
					 *
					 * @param string The HTML rendered for the "New Form" button.
					 */
					echo apply_filters( 'gform_new_form_button', '<input id="save_new_form" type="button" class="button button-large button-primary" value="' . esc_html__( 'Create Form', 'gravityforms' ) . '" onclick="saveNewForm();" tabindex="9002" />' ); ?>
					<div id="gf_new_form_error_message" style="display:inline-block;"></div>
				</div>

			</div>
		</div>

		<?php // - end of new form modal - // ?>

		<script text="text/javascript">
			function TrashForm(form_id) {
				jQuery("#action_argument").val(form_id);
				jQuery("#action").val("trash");
				jQuery("#forms_form")[0].submit();
			}

			function RestoreForm(form_id) {
				jQuery("#action_argument").val(form_id);
				jQuery("#action").val("restore");
				jQuery("#forms_form")[0].submit();
			}

			function DeleteForm(form_id) {
				jQuery("#action_argument").val(form_id);
				jQuery("#action").val("delete");
				jQuery("#forms_form")[0].submit();
			}

			function ConfirmDeleteForm(form_id){
				if( confirm(<?php echo json_encode( __( 'WARNING: You are about to delete this form and ALL entries associated with it. ', 'gravityforms' ) . esc_html__( 'Cancel to stop, OK to delete.', 'gravityforms' ) ); ?>) ){
					DeleteForm(form_id);
				}
			}

			function DuplicateForm(form_id) {
				jQuery("#action_argument").val(form_id);
				jQuery("#action").val("duplicate");
				jQuery("#forms_form")[0].submit();
			}

			function ToggleActive(img, form_id) {

				if( ! gfPageLoaded ) {
					return;
				}

				var is_active = img.src.indexOf("active1.png") >= 0
				if (is_active) {
					img.src = img.src.replace("active1.png", 'active0.png');
					jQuery(img).attr('title', <?php echo json_encode( esc_attr__( 'Inactive', 'gravityforms' ) ); ?>).attr('alt', <?php echo json_encode( esc_attr__( 'Inactive', 'gravityforms' ) ); ?>);
				}
				else {
					img.src = img.src.replace("active0.png", 'active1.png');
					jQuery(img).attr('title', <?php echo json_encode( esc_attr__( 'Active', 'gravityforms' ) ); ?>).attr('alt', <?php echo json_encode( esc_attr__( 'Active', 'gravityforms' ) ); ?>);
				}

				UpdateCount("active_count", is_active ? -1 : 1);
				UpdateCount("inactive_count", is_active ? 1 : -1);

				var mysack = new sack(<?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>);
				mysack.execute = 1;
				mysack.method = 'POST';
				mysack.setVar("action", "rg_update_form_active");
				mysack.setVar("rg_update_form_active", <?php echo json_encode( wp_create_nonce( 'rg_update_form_active' ) ); ?>);
				mysack.setVar("form_id", form_id);
				mysack.setVar("is_active", is_active ? 0 : 1);
				mysack.onError = function () {
					alert(<?php echo json_encode( __( 'Ajax error while updating form', 'gravityforms' ) ); ?>)
				};
				mysack.runAJAX();

				return true;
			}
			function UpdateCount(element_id, change) {
				var element = jQuery("#" + element_id);
				var count = parseInt(element.html()) + change
				element.html(count + "");
			}

			function gfConfirmBulkAction(element_id) {
				var element = "#" + element_id;
				if (jQuery(element).val() == 'delete')
					return confirm(<?php echo json_encode( __( 'WARNING: You are about to delete these forms and ALL entries associated with them. ', 'gravityforms' ) . __( "'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ); ?>);
				else if (jQuery(element).val() == 'reset_views')
					return confirm(<?php echo json_encode( __( 'Are you sure you would like to reset the Views for the selected forms? ', 'gravityforms' ) . __( "'Cancel' to stop, 'OK' to reset.", 'gravityforms' ) ); ?>);
				else if (jQuery(element).val() == 'delete_entries')
					return confirm(<?php echo json_encode( __( 'WARNING: You are about to delete ALL entries associated with the selected forms. ', 'gravityforms' ) . __( "'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ); ?>);

				return true;
			}
		</script>

		<link rel="stylesheet" href="<?php echo esc_url( GFCommon::get_base_url() ); ?>/css/admin<?php echo $min; ?>.css"/>
		<div class="wrap <?php echo sanitize_html_class( GFCommon::get_browser_class() ); ?>">

		<h2>
			<?php esc_html_e( 'Forms', 'gravityforms' ); ?>
			<a class="add-new-h2" href="" onclick="return loadNewFormModal();"><?php esc_html_e( 'Add New', 'gravityforms' ) ?></a>
		</h2>

		<?php if ( isset( $message ) ) { ?>
			<div class="updated below-h2" id="message"><p><?php echo esc_html( $message ); ?></p></div>
		<?php } ?>

		<form id="forms_form" method="post">
		<?php wp_nonce_field( 'gforms_update_forms', 'gforms_update_forms' ) ?>
		<input type="hidden" id="action" name="action" />
		<input type="hidden" id="action_argument" name="action_argument" />

		<ul class="subsubsub">
			<li>
				<a class="<?php echo ( $active === null ) ? 'current' : '' ?>" href="?page=gf_edit_forms"><?php echo esc_html( _x( 'All', 'Form List', 'gravityforms' ) ); ?>
					<span class="count">(<span id="all_count"><?php echo $form_count['total'] ?></span>)</span></a> |
			</li>
			<li>
				<a class="<?php echo $active == '1' ? 'current' : '' ?>" href="?page=gf_edit_forms&active=1"><?php echo esc_html( _x( 'Active', 'Form List', 'gravityforms' ) ); ?>
					<span class="count">(<span id="active_count"><?php echo $form_count['active'] ?></span>)</span></a> |
			</li>
			<li>
				<a class="<?php echo $active == '0' ? 'current' : '' ?>" href="?page=gf_edit_forms&active=0"><?php echo esc_html( _x( 'Inactive', 'Form List', 'gravityforms' ) ); ?>
					<span class="count">(<span id="inactive_count"><?php echo $form_count['inactive'] ?></span>)</span></a> |
			</li>
			<li>
				<a class="<?php echo $active == '0' ? 'current' : '' ?>" href="?page=gf_edit_forms&trash=1"><?php esc_html_e( 'Trash', 'gravityforms' ); ?>
					<span class="count">(<span id="trash_count"><?php echo $form_count['trash'] ?></span>)</span></a>
			</li>
		</ul>

		<?php
		if ( GFCommon::current_user_can_any( 'gravityforms_delete_forms' ) ) {
			?>
			<div class="tablenav">
				<div class="alignleft actions" style="padding:8px 0 7px 0;">

					<label class="hidden" for="bulk_action"><?php esc_html_e( 'Bulk action', 'gravityforms' ) ?></label>
					<select name="bulk_action" id="bulk_action">
						<option value=''> <?php esc_html_e( 'Bulk action', 'gravityforms' ) ?> </option>
						<?php if ( $trash ): ?>
							<option value='restore'><?php esc_html_e( 'Restore', 'gravityforms' ) ?></option>
							<option value='delete'><?php esc_html_e( 'Delete permanently', 'gravityforms' ) ?></option>
						<?php else : ?>
							<option value='activate'><?php esc_html_e( 'Mark as Active', 'gravityforms' ) ?></option>
							<option value='deactivate'><?php esc_html_e( 'Mark as Inactive', 'gravityforms' ) ?></option>
							<option value='reset_views'><?php esc_html_e( 'Reset Views', 'gravityforms' ) ?></option>
							<option value='delete_entries'><?php esc_html_e( 'Permanently Delete Entries', 'gravityforms' ) ?></option>
							<option value='trash'><?php esc_html_e( 'Move to trash', 'gravityforms' ) ?></option>
						<?php endif ?>
					</select>
					<?php
					$apply_button = '<input type="submit" class="button" value="' . __( 'Apply', 'gravityforms' ) . '" onclick="return gfConfirmBulkAction(\'bulk_action\');"/>';

					/**
					 * A filter that allows for modification of the form "Apply" button
					 *
					 * @param string $apply_button The HTML for the "Apply" Button
					 */
					echo apply_filters( 'gform_form_apply_button', $apply_button );
					?>

					<br class="clear" />

				</div>
			</div>
		<?php
		}
		?>

		<table class="widefat fixed" cellspacing="0">
			<thead>
			<tr>
				<?php
				if ( GFCommon::current_user_can_any( 'gravityforms_delete_forms' ) ) {
					?>
					<th scope="col" id="cb" class="manage-column column-cb check-column" style="">
						<input type="checkbox" name="form_bulk_check_all" onclick="jQuery('.gform_list_checkbox').attr('checked', this.checked);" />
					</th>
				<?php
				}
				$dir = $sort_column == 'is_active' && $sort_direction == 'ASC' ? 'DESC' : 'ASC';
				$url_active = admin_url( "admin.php?page=gf_edit_forms&sort=is_active&dir=$dir&trash=$trash" );
				?>
				<th scope="col" id="active" class="manage-column column-cb check-column" style="width:50px;cursor:pointer;" onclick="document.location='<?php echo esc_url( $url_active ); ?>'"></th>
				<?php
				$dir = $sort_column == 'id' && $sort_direction == 'ASC' ? 'DESC' : 'ASC';
				$url_id = admin_url( "admin.php?page=gf_edit_forms&sort=id&dir=$dir&trash=$trash" );
				?>
				<th scope="col" id="id" class="manage-column" style="width:50px;cursor:pointer;" onclick="document.location='<?php echo $url_id; ?>'"><?php esc_html_e( 'Id', 'gravityforms' ); ?></th>
				<?php
				$dir = $sort_column == 'title' && $sort_direction == 'ASC' ? 'DESC' : 'ASC';
				$url_title = admin_url( "admin.php?page=gf_edit_forms&sort=title&dir=$dir&trash=$trash" );
				?>
				<th width="410" scope="col" id="title" class="manage-column column-title" style="cursor:pointer;" onclick="document.location='<?php echo $url_title; ?>'"><?php esc_html_e( 'Title', 'gravityforms' ); ?></th>
				<th scope="col" id="author" class="manage-column column-author" style=""><?php esc_html_e( 'Views', 'gravityforms' ) ?></th>
				<th scope="col" id="template" class="manage-column" style=""><?php esc_html_e( 'Entries', 'gravityforms' ) ?></th>
				<th scope="col" id="template" class="manage-column" style=""><?php esc_html_e( 'Conversion', 'gravityforms' ) ?> <?php gform_tooltip( 'entries_conversion', 'tooltip_left' ) ?> </th>
			</tr>
			</thead>

			<tfoot>
			<tr>
				<?php
				if ( GFCommon::current_user_can_any( 'gravityforms_delete_forms' ) ) {
					?>
					<th scope="col" id="cb" class="manage-column column-cb check-column" style="">
						<input type="checkbox" name="form_bulk_check_all" onclick="jQuery('.gform_list_checkbox').attr('checked', this.checked);" />
					</th>
				<?php
				}
				?>
				<th scope="col" id="active" class="manage-column column-cb check-column"></th>
				<th scope="col" id="id" class="manage-column" style="cursor:pointer;" onclick="document.location='<?php echo $url_id; ?>'"><?php esc_html_e( 'Id', 'gravityforms' ) ?></th>
				<th width="410" scope="col" id="title" style="cursor:pointer;" class="manage-column column-title" onclick="document.location='<?php echo $url_title; ?>'"><?php esc_html_e( 'Title', 'gravityforms' ) ?></th>
				<th scope="col" id="author" class="manage-column column-author" style=""><?php esc_html_e( 'Views', 'gravityforms' ) ?></th>
				<th scope="col" id="template" class="manage-column" style=""><?php esc_html_e( 'Entries', 'gravityforms' ) ?></th>
				<th scope="col" id="template" class="manage-column" style=""><?php esc_html_e( 'Conversion', 'gravityforms' ) ?></th>
			</tr>
			</tfoot>

			<tbody class="list:user user-list">
			<?php
			if ( sizeof( $forms ) > 0 ) {
				$alternate_row = false;
				foreach ( $forms as $form ) {
					$conversion = '0%';
					if ( $form->view_count > 0 ) {
						$conversion = ( number_format( $form->lead_count / $form->view_count, 3 ) * 100 ) . '%';
					}
					$gf_form_locking = new GFFormLocking();
					?>
					<tr class='author-self status-inherit <?php $gf_form_locking->list_row_class( $form->id ); ?> <?php echo ( $alternate_row = ! $alternate_row ) ? 'alternate' : '' ?>' valign="top" data-id="<?php echo absint( $form->id ) ?>">
						<?php
						if ( GFCommon::current_user_can_any( 'gravityforms_delete_forms' ) ) {
							?>
							<th scope="row" class="check-column">
								<input type="checkbox" name="form[]" value="<?php echo absint( $form->id ) ?>" class="gform_list_checkbox" /><?php $gf_form_locking->lock_indicator(); ?>
							</th>
						<?php
						}
						?>

						<td>
							<?php if ( ! $trash ) : ?>
								<img class="gform_active_icon gf_not_ready" src="<?php echo esc_url( GFCommon::get_base_url() ); ?>/images/active<?php echo intval( $form->is_active ) ?>.png" style="cursor: pointer;" alt="<?php echo $form->is_active ? esc_attr__( 'Active', 'gravityforms' ) : esc_attr__( 'Inactive', 'gravityforms' ); ?>" title="<?php echo $form->is_active ? esc_attr__( 'Active', 'gravityforms' ) : esc_attr__( 'Inactive', 'gravityforms' ); ?>" onclick="ToggleActive(this, <?php echo absint( $form->id ); ?>); " />
							<?php endif ?>
						</td>
						<td class="column-id"><?php echo absint( $form->id ); ?></td>
						<td class="column-title">
							<?php
							if ( $trash ) :
								echo esc_html( $form->title );
							else :
								?>
								<strong><a class="row-title" disabled="<?php disabled( true, $trash ); ?>"
										   href="admin.php?page=gf_edit_forms&id=<?php echo absint( $form->id ); ?>"
										   title="<?php esc_attr_e( 'Edit', 'gravityforms' ) ?>"><?php echo esc_html( $form->title ) ?></a></strong>
								<?php $gf_form_locking->lock_info( $form->id );
							endif
							?>
							<div class="row-actions">
								<?php

								if ( $trash ) {
									$form_actions['restore'] = array(
										'label'        => __( 'Restore', 'gravityforms' ),
										'title'        => __( 'Restore', 'gravityforms' ),
										'url'          => '#',
										'onclick'      => 'RestoreForm(' . absint( $form->id ) . ');',
										'capabilities' => 'gravityforms_delete_forms',
										'priority'     => 600,
									);
									$form_actions['delete']  = array(
										'label'        => __( 'Delete permanently', 'gravityforms' ),
										'title'        => __( 'Delete permanently', 'gravityforms' ),
										'menu_class'   => 'delete',
										'url'          => '#',
										'onclick'      => 'ConfirmDeleteForm(' . absint( $form->id ) . ');',
										'capabilities' => 'gravityforms_delete_forms',
										'priority'     => 500,
									);

								} else {
									require_once( GFCommon::get_base_path() . '/form_settings.php' );

									$form_actions = GFForms::get_toolbar_menu_items( $form->id, true );

									$form_actions['duplicate'] = array(
										'label'        => __( 'Duplicate', 'gravityforms' ),
										'title'        => __( 'Duplicate this form', 'gravityforms' ),
										'url'         => '#',
										'onclick'          => 'DuplicateForm(' . absint( $form->id ) . ');return false;',
										'capabilities' => 'gravityforms_create_form',
										'priority'     => 600,
									);

									$form_actions['trash'] = array(
										'label'        => __( 'Trash', 'gravityforms' ),
										'title'        => __( 'Move this form to the trash', 'gravityforms' ),
										'url'         => '#',
										'onclick'          => 'TrashForm(' . absint( $form->id ) . ');return false;',
										'capabilities' => 'gravityforms_delete_forms',
										'menu_class'   => 'trash',
										'priority'     => 500,
									);

								}

								$form_actions = apply_filters( 'gform_form_actions', $form_actions, $form->id );

								echo GFForms::format_toolbar_menu_items( $form_actions, true );

								?>

							</div>
						</td>
						<td class="column-date"><strong><?php echo absint( $form->view_count ) ?></strong></td>
						<td class="column-date">
							<strong>
								<?php if ( $form->lead_count > 0 && ! $trash ) { ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=gf_entries&view=entries&id=' . absint( $form->id ) ) ); ?>"><?php echo absint( $form->lead_count ); ?></a>
								<?php
								} else {
									echo absint( $form->lead_count );
								} ?>
							</strong>
						</td>
						<td class="column-date"><?php echo esc_html( $conversion ); ?></td>
					</tr>
				<?php
				}
			} else {
				?>
				<tr>
					<td colspan="6" style="padding:20px;">
						<?php
						if ( $trash ){
							esc_html_e( 'There are no forms in the trash.', 'gravityforms' );
						} else {
							printf( esc_html__( "You don't have any forms. Let's go %screate one%s!", 'gravityforms' ), '<a href="admin.php?page=gf_new_form">', '</a>' );
						}
						?>
					</td>
				</tr>
			<?php
			}
			?>
			</tbody>
		</table>
		<div class="tablenav">
			<div class="alignleft actions" style="padding:8px 0 7px 0;">
				<?php
				if ( GFCommon::current_user_can_any( 'gravityforms_delete_forms' ) ) {
					?>
					<label class="hidden" for="bulk_action2"><?php esc_html_e( 'Bulk action', 'gravityforms' ) ?></label>
					<select name="bulk_action2" id="bulk_action2">
						<option value=''> <?php esc_html_e( 'Bulk action', 'gravityforms' ) ?> </option>
						<?php if ( $trash ): ?>
							<option value='restore'><?php esc_html_e( 'Restore', 'gravityforms' ) ?></option>
							<option value='delete'><?php esc_html_e( 'Delete permanently', 'gravityforms' ) ?></option>
						<?php else : ?>
							<option value='activate'><?php esc_html_e( 'Mark as Active', 'gravityforms' ) ?></option>
							<option value='deactivate'><?php esc_html_e( 'Mark as Inactive', 'gravityforms' ) ?></option>
							<option value='reset_views'><?php esc_html_e( 'Reset Views', 'gravityforms' ) ?></option>
							<option value='delete_entries'><?php esc_html_e( 'Permanently Delete Entries', 'gravityforms' ) ?></option>
							<option value='trash'><?php esc_html_e( 'Move to trash', 'gravityforms' ) ?></option>
						<?php endif ?>
					</select>
					<?php
					$apply_button = '<input type="submit" class="button" value="' . esc_attr__( 'Apply', 'gravityforms' ) . '" onclick="return gfConfirmBulkAction(\'bulk_action2\');"/>';

					/**
					 * A filter that allows for modification of the form "Apply" button
					 *
					 * @param string $apply_button The HTML for the "Apply" Button
					 */
					echo apply_filters( 'gform_form_apply_button', $apply_button );
				}
				?>
				<br class="clear" />
			</div>
		</div>
		</form>
		</div>
	<?php
	}

	public static function save_new_form() {

		if ( ! check_admin_referer( 'gf_save_new_form', 'gf_save_new_form' ) ) {
			die( json_encode( array( 'error' => __( 'There was an issue creating your form.', 'gravityforms' ) ) ) );
		}

		GFFormsModel::ensure_tables_exist();

		require_once( GFCommon::get_base_path() . '/form_detail.php' );

		$form_json = rgpost( 'form' );

		$form = json_decode( $form_json, true );

		if ( empty( $form['title'] ) ) {
			$result = array( 'error' => __( 'Please enter a form title.', 'gravityforms' ) );
			die( json_encode( $result ) );
		}

		$result = GFFormDetail::save_form_info( 0, $form_json );

		switch ( rgar( $result, 'status' ) ) {
			case 'invalid_json':
				$result['error'] = __( 'There was an issue creating your form.', 'gravityforms' );
				die( json_encode( $result ) );

			case 'duplicate_title':
				$result['error'] = __( 'Please enter a unique form title.', 'gravityforms' );
				die( json_encode( $result ) );

			default:
				$form_id = absint( $result['status'] );
				die( json_encode( array( 'redirect' => admin_url( "admin.php?page=gf_edit_forms&id={$form_id}" ) ) ) );
		}
	}

	public static function output_form_list_script_block() {
		?>

		<script type="text/javascript">

			jQuery( document ).ready( function( $ ) {

				// load new form modal on New Form page
				<?php if ( rgget( 'page' ) == 'gf_new_form' ): ?>
				loadNewFormModal();
				<?php endif; ?>

				// form settings submenu support
				$('.gf_form_action_has_submenu').hover(function(){
					var l = jQuery(this).offset().left;
					jQuery(this).find('.gf_submenu')
						.toggle()
						.offset({ left: l });
				}, function(){
					jQuery(this).find('.gf_submenu').hide();
				});

				// enable form status icons
				gfPageLoaded = true;
				$( '.gform_active_icon' ).removeClass( 'gf_not_ready' );

			} );

			function loadNewFormModal() {
				resetNewFormModal();
				tb_show(<?php echo json_encode( esc_html__( 'Create a New Form', 'gravityforms' ) ); ?>, '#TB_inline?width=375&amp;inlineId=gf_new_form_modal');
				jQuery('#new_form_title').focus();
				return false;
			}

			function saveNewForm() {

				var createButton = jQuery('#save_new_form');
				var spinner = new gfAjaxSpinner(createButton, gf_vars.baseUrl + '/images/spinner.gif');

				// clear error message
				jQuery('#gf_new_form_error_message').html('');

				var origVal = createButton.val();
				createButton.val(<?php echo json_encode( esc_html__( 'Creating Form...', 'gravityforms' ) ); ?>);

				var form = {
					title: jQuery('#new_form_title').val(),
					description: jQuery('#new_form_description').val(),
					labelPlacement:'top_label',
					descriptionPlacement:'below',
					button: {
						type: 'text',
						text: <?php echo json_encode( esc_html__( 'Submit', 'gravityforms' ) ); ?>,
						imageUrl : ''
					},
					fields:[]
				}

				jQuery.post(ajaxurl, {
					form: jQuery.toJSON(form),
					action: 'gf_save_new_form',
					gf_save_new_form: <?php echo json_encode( wp_create_nonce( 'gf_save_new_form' ) ); ?>
				}, function(response){

					spinner.destroy();

					var respData = jQuery.parseJSON(response);

					if(respData['error']) {
						// adding class later otherwise WP moves box up to the top of the page
						jQuery('#gf_new_form_error_message').html( respData.error );
						addInputErrorIcon( '#new_form_title' );
						createButton.val(origVal);
					} else {
						location.href = respData.redirect;
						createButton.val(<?php echo json_encode( esc_html__( 'Saved! Redirecting...', 'gravityforms' ) ); ?>);
					}

				});

			}

			function resetNewFormModal() {
				jQuery('#new_form_title').val('');
				jQuery('#new_form_description').val('');
				jQuery('#gf_new_form_error_message').html('');
				removeInputErrorIcons( '.gf_new_form_modal_container' );
			}

			function addInputErrorIcon( elem ) {
				var elem = jQuery(elem);
				elem.before( '<span class="gf_input_error_icon"></span>');
			}

			function removeInputErrorIcons( elem ) {
				var elem = jQuery(elem);
				elem.find('span.gf_input_error_icon').remove();
			}

		</script>

	<?php
	}

}
