<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFFormList {

	public static function form_list_page() {
		global $wpdb;

		// todo: hook up bulk action confirmation js
		// todo: apply button filter


		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		echo GFCommon::get_remote_message();

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

		<?php if ( GFCommon::current_user_can_any( 'gravityforms_create_form' ) ) { ?>

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
					echo apply_filters( 'gform_new_form_button', '<input id="save_new_form" type="button" class="button button-large button-primary" value="' . esc_html__( 'Create Form', 'gravityforms' ) . '" onclick="saveNewForm();" onkeypress="saveNewForm();" tabindex="9002" />' ); ?>
					<div id="gf_new_form_error_message" style="display:inline-block;"></div>
				</div>

			</div>
		</div>

		<?php } // - end of new form modal - // ?>

		<script text="text/javascript">
			function TrashForm(form_id) {
				jQuery("#single_action_argument").val(form_id);
				jQuery("#single_action").val("trash");
				jQuery("#form_list_form")[0].submit();
			}

			function RestoreForm(form_id) {
				jQuery("#single_action_argument").val(form_id);
				jQuery("#single_action").val("restore");
				jQuery("#form_list_form")[0].submit();
			}

			function DeleteForm(form_id) {
				jQuery("#single_action_argument").val(form_id);
				jQuery("#single_action").val("delete");
				jQuery("#form_list_form")[0].submit();
			}

			function ConfirmDeleteForm(form_id){
				if( confirm(<?php echo json_encode( __( 'WARNING: You are about to delete this form and ALL entries associated with it. ', 'gravityforms' ) . esc_html__( 'Cancel to stop, OK to delete.', 'gravityforms' ) ); ?>) ){
					DeleteForm(form_id);
				}
			}

			function DuplicateForm(form_id) {
				jQuery("#single_action_argument").val(form_id);
				jQuery("#single_action").val("duplicate");
				jQuery("#form_list_form")[0].submit();
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

		<link rel="stylesheet" href="<?php echo esc_url( GFCommon::get_base_url() ); ?>/css/admin<?php echo $min; ?>.css?ver=<?php echo GFForms::$version ?>"/>
		<div class="wrap <?php echo sanitize_html_class( GFCommon::get_browser_class() ); ?>">

		<h2>
			<?php esc_html_e( 'Forms', 'gravityforms' );
			if ( GFCommon::current_user_can_any( 'gravityforms_create_form' ) ) {
				echo '<a class="add-new-h2" href="" onclick="return loadNewFormModal();" onkeypress="return loadNewFormModal();">' . esc_html__( 'Add New', 'gravityforms' ) . '</a>';
			} ?>
		</h2>

		<?php GFCommon::display_dismissible_message(); ?>

		<form id="form_list_form" method="post">
			<?php

			$table = new GF_Form_List_Table();
			$table->process_action();
			$table->views();
			$table->prepare_items();

			$table->display();
			?>
		</form>
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
				<?php if ( rgget( 'page' ) == 'gf_new_form' ) :	?>
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

				jQuery( '#current-page-selector').keyup( function( event ) {
					if (event.keyCode == 13) {
						var url = <?php echo json_encode( esc_url_raw( remove_query_arg( 'paged' ) ) ); ?>;
						var page = parseInt( this.value );
						document.location = url + '&paged=' + page;
						event.preventDefault();
					}
				});

			} );

			function loadNewFormModal() {
				resetNewFormModal();
				tb_show(<?php echo json_encode( esc_html__( 'Create a New Form', 'gravityforms' ) ); ?>, '#TB_inline?width=375&amp;inlineId=gf_new_form_modal');
				jQuery('#new_form_title').focus();

				jQuery( '#new_form_title').keyup( function( event ) {
					if (event.keyCode == 13) {
						saveNewForm();
					}
				});

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

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class GF_Form_List_Table extends WP_List_Table {

	public $filter = '';

	public $locking_info;

	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable, 'title' );
		$this->locking_info = new GFFormLocking();
	}

	function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'id'    => array( 'id', false ),
			'lead_count'    => array( 'lead_count', false ),
			'view_count'    => array( 'view_count', false ),
			'conversion'    => array( 'conversion', false ),
		);
	}

	function get_views() {
		$form_count = RGFormsModel::get_form_count();

		$all_class = ( $this->filter == '' ) ? 'current' : '';

		$active_class = ( $this->filter == 'active' ) ? 'current' : '';

		$inactive_class = ( $this->filter == 'inactive' ) ? 'current' : '';

		$trash_class = ( $this->filter == 'trash' ) ? 'current' : '' ;

		$views = array(
			'all' => '<a class="' . $all_class . '" href="?page=gf_edit_forms">' . esc_html( _x( 'All', 'Form List', 'gravityforms' ) ) . ' <span class="count">(<span id="all_count">' . $form_count['total'] . '</span>)</span></a>',
			'active' => '<a class="' . $active_class . '" href="?page=gf_edit_forms&filter=active">' . esc_html( _x( 'Active', 'Form List', 'gravityforms' ) ) . ' <span class="count">(<span id="all_count">' . $form_count['active'] . '</span>)</span></a>',
			'inactive' => '<a class="' . $inactive_class . '" href="?page=gf_edit_forms&filter=inactive">' . esc_html( _x( 'Inactive', 'Form List', 'gravityforms' ) ) . ' <span class="count">(<span id="all_count">' . $form_count['inactive'] . '</span>)</span></a>',
			'trash' => '<a class="' . $trash_class . '" href="?page=gf_edit_forms&filter=trash">' . esc_html( _x( 'Trash', 'Form List', 'gravityforms' ) ) . ' <span class="count">(<span id="all_count">' . $form_count['trash'] . '</span>)</span></a>',
		);
		return $views;
	}

	function prepare_items() {

		$sort_column    = empty( $_GET['orderby'] ) ? 'title' : $_GET['orderby'];
		$sort_columns = array_keys( $this->get_sortable_columns() );

		if ( ! in_array( strtolower( $sort_column ), $sort_columns ) ) {
			$sort_column = 'title';
		}

		$sort_direction = empty( $_GET['order'] ) ? 'ASC' : strtoupper( $_GET['order'] );
		$sort_direction = $sort_direction == 'ASC' ? 'ASC' : 'DESC';
		$filter = rgget( 'filter' );
		$trash = false;
		switch ( $filter ) {
			case '':
				$active = null;
			break;
			case 'active' :
				$active = true;
				break;
			case 'inactive' :
				$active = false;
				break;
			case 'trash' :
				$active = null;
				$trash = true;
		}
		$forms   = RGFormsModel::get_forms( $active, $sort_column, $sort_direction, $trash );

		$per_page = $this->get_items_per_page( 'gform_forms_per_page', 20 );

		$per_page = apply_filters( 'gform_page_size_form_list', $per_page );

		$this->set_pagination_args( array(
			'total_items' => count( $forms ),
			'per_page'    => $per_page,
		) );

		if ( $trash ) {
			$this->filter = 'trash';
		} else {
			$this->filter = 'active';
		}

		if ( in_array( $sort_column, array( 'view_count', 'lead_count', 'conversion' ) ) ) {
			usort( $forms, array( $this, 'compare_' . $sort_column . '_'  . $sort_direction ) );
		}

		$offset = ( $this->get_pagenum() - 1 ) * $per_page;

		$this->items = array_slice( $forms, $offset, $per_page );
	}

	function get_bulk_actions() {
		if ( $this->filter == 'trash' ) {
			$actions = array(
				'restore' => esc_html__( 'Restore', 'gravityforms' ),
				'delete' => esc_html__( 'Delete permanently', 'gravityforms' ),
			);
		} else {
			$actions = array(
				'activate' => esc_html__( 'Mark as Active', 'gravityforms' ),
				'deactivate' => esc_html__( 'Mark as Inactive', 'gravityforms' ),
				'reset_views' => esc_html__( 'Reset Views', 'gravityforms' ),
				'delete_entries' => esc_html__( 'Permanently Delete Entries', 'gravityforms' ),
				'trash' => esc_html__( 'Move to trash', 'gravityforms' ),
			);
		}
		return $actions;
	}

	function get_columns() {

		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'is_active'  => '',
			'title'      => esc_html__( 'Title', 'gravityforms' ),
			'id'         => esc_html__( 'ID', 'gravityforms' ),
			'lead_count' => esc_html__( 'Entries', 'gravityforms' ),
			'view_count' => esc_html__( 'Views', 'gravityforms' ),
			'conversion' => esc_html__( 'Conversion', 'gravityforms' ),
		);

		$columns = apply_filters( 'gform_form_list_columns', $columns );

		return $columns;
	}

	function single_row_columns( $item ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$classes = "$column_name column-$column_name";
			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}

			if ( in_array( $column_name, $hidden ) ) {
				$classes .= ' hidden';
			}

			// Comments column uses HTML in the display name with screen reader text.
			// Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
			$data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';

			$attributes = "class='$classes' $data";

			if ( 'cb' === $column_name ) {
				echo '<th scope="row" class="check-column">';
				echo $this->column_cb( $item );
				echo '</th>';
			} elseif ( has_action( 'gform_form_list_column_' . $column_name ) ) {
				echo "<td $attributes>";
				do_action( 'gform_form_list_column_' . $column_name, $item );
				echo $this->handle_row_actions( $item, $column_name, $primary );
				echo "</td>";				
			} elseif ( method_exists( $this, '_column_' . $column_name ) ) {
				echo call_user_func(
					array( $this, '_column_' . $column_name ),
					$item,
					$classes,
					$data,
					$primary
				);
			} elseif ( method_exists( $this, 'column_' . $column_name ) ) {
				echo "<td $attributes>";
				echo call_user_func( array( $this, 'column_' . $column_name ), $item );
				echo $this->handle_row_actions( $item, $column_name, $primary );
				echo "</td>";
			} else {
				echo "<td $attributes>";
				echo $this->column_default( $item, $column_name );
				echo $this->handle_row_actions( $item, $column_name, $primary );
				echo "</td>";
			}
		}
	}


	function get_primary_column_name() {
		return 'title';
	}

	function _column_is_active( $form, $classes, $data, $primary ) {
		echo '<th scope="row" class="manage-column column-is_active">';
		if ( $this->filter !== 'trash' ) {
			?>
			<img class="gform_active_icon" src="<?php echo esc_url( GFCommon::get_base_url() ); ?>/images/active<?php echo intval( $form->is_active ) ?>.png" style="cursor: pointer;" alt="<?php echo $form->is_active ? esc_attr__( 'Active', 'gravityforms' ) : esc_attr__( 'Inactive', 'gravityforms' ); ?>" title="<?php echo $form->is_active ? esc_attr__( 'Active', 'gravityforms' ) : esc_attr__( 'Inactive', 'gravityforms' ); ?>" onclick="ToggleActive(this, <?php echo absint( $form->id ); ?>); " onkeypress="ToggleActive(this, <?php echo absint( $form->id ); ?>); " />
			<?php
		}
		echo '</th>';
	}

	function column_title( $form ) {
		echo '<strong><a href="?page=gf_edit_forms&id='. absint( $form->id ) .'">' . esc_html( $form->title ) . '</a></strong>';
	}

	function column_id( $form ) {
		echo '<a href="?page=gf_edit_forms&id='. absint( $form->id ) .'">' .absint( $form->id ) . '</a>';
	}

	function column_view_count( $form ) {
		echo absint( $form->view_count );
	}

	function column_lead_count( $form ) {
		echo '<a href="?page=gf_entries&id='. absint( $form->id ) .'">' . absint( $form->lead_count ) . '</a>';
	}

	function column_conversion( $form ) {
		$conversion = '0%';
		if ( $form->view_count > 0 ) {
			$conversion = ( number_format( $form->lead_count / $form->view_count, 3 ) * 100 ) . '%';
		}
		echo $conversion;
	}

	function column_cb( $form ) {
		$form_id = $form->id;
		?>
		<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $form_id ); ?>"><?php _e( 'Select form' ); ?></label>
		<input type="checkbox" class="gform_list_checkbox" name="form[]" value="<?php echo esc_attr( $form_id ); ?>" />
		<?php
		$this->locking_info->lock_indicator();
	}

	protected function handle_row_actions( $form, $column_name, $primary ) {

		if ( $primary !== $column_name ) {
			return '';
		}

		?>
		<div class="row-actions">
			<?php

			if ( $this->filter == 'trash' ) {
				$form_actions['restore'] = array(
					'label'        => __( 'Restore', 'gravityforms' ),
					'title'        => __( 'Restore', 'gravityforms' ),
					'url'          => '#',
					'onclick'      => 'RestoreForm(' . absint( $form->id ) . ');',
					'onkeypress'   => 'RestoreForm(' . absint( $form->id ) . ');',
					'capabilities' => 'gravityforms_delete_forms',
					'priority'     => 600,
				);
				$form_actions['delete']  = array(
					'label'        => __( 'Delete permanently', 'gravityforms' ),
					'title'        => __( 'Delete permanently', 'gravityforms' ),
					'menu_class'   => 'delete',
					'url'          => '#',
					'onclick'      => 'ConfirmDeleteForm(' . absint( $form->id ) . ');',
					'onkeypress'   => 'ConfirmDeleteForm(' . absint( $form->id ) . ');',
					'capabilities' => 'gravityforms_delete_forms',
					'priority'     => 500,
				);

			} else {

				$this->locking_info->lock_info( $form->id );

				require_once( GFCommon::get_base_path() . '/form_settings.php' );

				$form_actions = GFForms::get_toolbar_menu_items( $form->id, true );

				$form_actions['duplicate'] = array(
					'label'        => __( 'Duplicate', 'gravityforms' ),
					'title'        => __( 'Duplicate this form', 'gravityforms' ),
					'url'          => '#',
					'onclick'      => 'DuplicateForm(' . absint( $form->id ) . ');return false;',
					'onkeypress'   => 'DuplicateForm(' . absint( $form->id ) . ');return false;',
					'capabilities' => 'gravityforms_create_form',
					'priority'     => 600,
				);

				$form_actions['trash'] = array(
					'label'        => __( 'Trash', 'gravityforms' ),
					'title'        => __( 'Move this form to the trash', 'gravityforms' ),
					'url'          => '#',
					'onclick'      => 'TrashForm(' . absint( $form->id ) . ');return false;',
					'onkeypress'   => 'TrashForm(' . absint( $form->id ) . ');return false;',
					'capabilities' => 'gravityforms_delete_forms',
					'menu_class'   => 'trash',
					'priority'     => 500,
				);

			}

			$form_actions = apply_filters( 'gform_form_actions', $form_actions, $form->id );

			echo GFForms::format_toolbar_menu_items( $form_actions, true );

			?>

		</div>
		<?php
		return $column_name === $primary ? '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>' : '';
	}

	function no_items() {
		if ( $this->filter == 'trash' ) {
			esc_html_e( 'There are no forms in the trash.', 'gravityforms' );
		} else {
			printf( esc_html__( "You don't have any forms. Let's go %screate one%s!", 'gravityforms' ), '<a href="admin.php?page=gf_new_form">', '</a>' );
		}
	}

	function process_action() {

		$single_action = rgpost( 'single_action' );
		$remote_action = rgget( 'action' ); //action initiated at other pages (i.e. trash command from form menu)

		$bulk_action = $this->current_action();

		if ( ! ( $single_action || $bulk_action || $remote_action ) ) {
			return;
		}

		if ( $single_action ) {

			check_admin_referer( 'gforms_update_forms', 'gforms_update_forms' );

			$form_id = rgpost( 'single_action_argument' );
			switch ( $single_action ) {
				case 'trash' :
					RGFormsModel::trash_form( $form_id );
					$message = __( 'Form moved to the trash.', 'gravityforms' );
					break;
				case 'restore' :
					RGFormsModel::restore_form( $form_id );
					$message = __( 'Form restored.', 'gravityforms' );
					break;
				case 'delete' :
					if ( GFCommon::current_user_can_any( 'gravityforms_delete_forms' ) ) {
						RGFormsModel::delete_form( $form_id );
						$message = __( 'Form deleted.', 'gravityforms' );
					} else {
						$message = __( "You don't have adequate permission to delete forms.", 'gravityforms' );
					}
					break;
				case 'duplicate' :
					RGFormsModel::duplicate_form( $form_id );
					$message = __( 'Form duplicated.', 'gravityforms' );
					break;

			}
		} elseif ( $remote_action ){

			$form_id = rgget( 'arg' );
			switch ( $remote_action ) {
				case 'trash' :

					check_admin_referer( "gf_delete_form_{$form_id}" );

					RGFormsModel::trash_form( $form_id );
					$message = __( 'Form moved to the trash.', 'gravityforms' );
					break;				
				case 'duplicate' :
					check_ajax_referer( "gf_duplicate_form_{$form_id}" );
					RGFormsModel::duplicate_form( $form_id );
					$message = __( 'Form duplicated.', 'gravityforms' );
					break;

			}

		} elseif ( $bulk_action ) {

			check_admin_referer( 'gforms_update_forms', 'gforms_update_forms' );

			$form_ids   = is_array( rgpost( 'form' ) ) ? rgpost( 'form' ) : array();
			$form_count = count( $form_ids );
			$message = '';

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

			if ( ! empty( $message ) ) {

				$message = sprintf( $message, $form_count );
			}
		}

		if ( ! empty( $message ) ) {

			echo '<div id="message" class="updated notice is-dismissible"><p>' . $message . '</p></div>';
		};
	}

	function extra_tablenav( $which ) {
		if ( $which !== 'top' ) {
			return;
		}
		wp_nonce_field( 'gforms_update_forms', 'gforms_update_forms' );
		?>
		<input type="hidden" id="single_action" name="single_action" />
		<input type="hidden" id="single_action_argument" name="single_action_argument" />
		<?php
	}

	public function single_row( $form ) {
		echo '<tr class="' . $this->locking_info->list_row_class( $form->id, false ) . '">';
		$this->single_row_columns( $form );
		echo '</tr>';
	}

	public static function compare_view_count_asc( $a, $b ) {
	    return $a->view_count > $b->view_count;
	}

	public static function compare_view_count_desc( $a, $b ) {
	    return $a->view_count < $b->view_count;
	}

	public static function compare_lead_count_asc( $a, $b ) {
	    return $a->lead_count > $b->lead_count;
	}

	public static function compare_lead_count_desc( $a, $b ) {
	    return $a->lead_count < $b->lead_count;
	}

	public static function compare_conversion_asc( $a, $b ) {
		$a_conversion = $a->view_count > 0 ? $a->lead_count / $a->view_count : 0;
		$b_conversion = $b->view_count > 0 ? $b->lead_count / $b->view_count : 0;
	    return $a_conversion > $b_conversion;
	}

	public static function compare_conversion_desc( $a, $b ) {
	    $a_conversion = $a->view_count > 0 ? $a->lead_count / $a->view_count : 0;
		$b_conversion = $b->view_count > 0 ? $b->lead_count / $b->view_count : 0;
	    return $a_conversion < $b_conversion;
	}
}
