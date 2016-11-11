<?php

// For backwards compatibility, load WordPress if it hasn't been loaded yet
// Will be used if this file is being called directly
if ( ! class_exists( 'RGForms' ) ) {
	for ( $i = 0; $i < $depth = 10; $i ++ ) {
		$wp_root_path = str_repeat( '../', $i );

		if ( file_exists( "{$wp_root_path}wp-load.php" ) ) {
			require_once( "{$wp_root_path}wp-load.php" );
			require_once( "{$wp_root_path}wp-admin/includes/admin.php" );
			break;
		}
	}

	auth_redirect();
}

/**
 * Class GFSelectColumns
 *
 * Handles the changing of what columns are shown on the Entry page
 *
 * @since Unknown
 */
class GFSelectColumns {

	/**
	 * Renders the column selection page.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::get_form_meta()
	 * @uses GFFormsModel::get_grid_columns()
	 * @uses GFSelectColumns::get_selectable_entry_meta()
	 * @uses GFFormsModel::convert_field_objects()
	 * @uses GFFormsModel::get_input_type()
	 * @uses GF_Field::get_entry_inputs()
	 * @uses GFCommon::get_label()
	 *
	 * @return void
	 */
	public static function select_columns_page() {

		$form_id = $_GET['id'];
		if ( empty( $form_id ) ) {
			echo __( 'Oops! We could not locate your form. Please try again.', 'gravityforms' );
			exit;
		}

		// Reading form metadata
		$form = RGFormsModel::get_form_meta( $form_id );

		?>
		<html>
		<head>
		<?php
		wp_print_styles( array( 'wp-admin', 'colors-fresh' ) );
		wp_print_scripts( array( 'jquery-ui-sortable' ) );

		//adds touchscreen support on mobile devices
		if ( wp_is_mobile() ) {
			wp_print_scripts( array( 'jquery-touch-punch' ) );
		}
		?>

			<style type="text/css">
				body {
					font-family: "Lucida Grande", Verdana, Arial, sans-serif;
				}

				#sortable_available, #sortable_selected {
					list-style-type: none;
					margin: 0;
					padding: 2px;
					height: 250px;
					border: 1px solid #eaeaea;
					-moz-border-radius: 4px;
					-webkit-border-radius: 4px;
					-khtml-border-radius: 4px;
					border-radius: 4px;
					background-color: #FFF;
					overflow: auto;
				}
				#sortable_available li, #sortable_selected li {
					margin: 0 2px 2px 2px;
					padding: 2px;
					width: 96%;
					border: 1px solid white;
					cursor: pointer;
					font-size: 13px;
				}

				.field_hover {
					border: 1px dashed #2175A9 !important;
				}

				.placeholder {
					background-color: #FFF0A5;
					height: 20px;
				}

				.gcolumn_wrapper {
					height: 290px;
					padding: 0 20px;
				}

				.gcolumn_container_left, .gcolumn_container_right {
					width: 46%;
				}

				.gcolumn_container_left {
					float: left;
				}

				.gcolumn_container_right {
					float: right;
				}

				.gform_select_column_heading {
					font-weight: bold;
					padding-bottom: 7px;
					font-size: 13px;
				}

				.column-arrow-mid {
					float: left;
					width: 45px;
					height: 250px;
					background-image: url(<?php echo GFCommon::get_base_url(); ?>/images/arrow-rightleft.png);
					background-repeat: no-repeat;
					background-position: center center;
					margin-top: 26px;
				}

				.panel-instructions {
					border-bottom: 1px solid #dfdfdf;
					color: #555;
					font-size: 11px;
					padding: 10px 20px;
					margin-bottom: 6px
				}

				div.panel-buttons {
					margin-top: 8px;
					padding: 0 20px;
				}

				div.panel-buttons {
					*margin-top: 0px
				}

				/* ie specific */
			</style>

			<script type="text/javascript">
				jQuery(document).ready(function () {

					jQuery("#sortable_available, #sortable_selected").sortable({connectWith: '.sortable_connected', placeholder: 'placeholder'});

					jQuery(".sortable_connected li").hover(
						function () {
							jQuery(this).addClass("field_hover");
						},
						function () {
							jQuery(this).removeClass("field_hover");
						}
					);

				});
				var columns = new Array();

				function SelectColumns() {
					jQuery("#sortable_selected li").each(function () {
						columns.push(this.id);
					});
					self.parent.parent.ChangeColumns(columns);
				}
			</script>

		</head>
		<body>
		<?php
		$columns = RGFormsModel::get_grid_columns( $form_id );
		$field_ids = array_keys( $columns );
		$form = RGFormsModel::get_form_meta( $form_id );
		array_push( $form['fields'], array( 'id' => 'id', 'label' => __( 'Entry Id', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'date_created', 'label' => __( 'Entry Date', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'ip', 'label' => __( 'User IP', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'source_url', 'label' => __( 'Source Url', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'payment_status', 'label' => __( 'Payment Status', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'transaction_id', 'label' => __( 'Transaction Id', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'payment_amount', 'label' => __( 'Payment Amount', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'payment_date', 'label' => __( 'Payment Date', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'created_by', 'label' => __( 'User', 'gravityforms' ) ) );

		$form = self::get_selectable_entry_meta( $form );
		$form = GFFormsModel::convert_field_objects( $form );
		?>
		<div class="panel-instructions"><?php esc_html_e( 'Drag & drop to order and select which columns are displayed in the entries table.', 'gravityforms' ) ?></div>
		<div class="gcolumn_wrapper">
			<div class="gcolumn_container_left">
				<div class="gform_select_column_heading"><?php esc_html_e( 'Active Columns', 'gravityforms' ); ?></div>
				<ul id="sortable_selected" class="sortable_connected">
				<?php
				foreach ( $columns as $field_id => $field_info ) {
					?>
					<li id="<?php echo esc_attr( $field_id ) ?>"><?php echo esc_html( $field_info['label'] ) ?></li>
				<?php
				}
				?>
				</ul>
			</div>

			<div class="column-arrow-mid"></div>

			<div class="gcolumn_container_right" id="available_column">
				<div class="gform_select_column_heading"> <?php esc_html_e( 'Inactive Columns', 'gravityforms' ); ?></div>
				<ul id="sortable_available" class="sortable_connected">
				<?php
				foreach ( $form['fields'] as $field ) {
					/* @var GF_Field $field */
					if ( RGFormsModel::get_input_type( $field ) == 'checkbox' && ! in_array( $field->id, $field_ids ) ) {
						?>
						<li id="<?php echo esc_attr( $field->id ); ?>"><?php echo esc_html( GFCommon::get_label( $field ) ) ?></li>
					<?php
					}

					$inputs = $field->get_entry_inputs();

					if ( is_array( $inputs ) ) {
						foreach ( $inputs as $input ) {
							if ( rgar( $input, 'isHidden' ) ) {
								continue;
							}

							if ( ! in_array( $input['id'], $field_ids ) && ! ( $field->type == 'creditcard' && in_array( $input['id'], array( floatval( "{$field->id}.2" ), floatval( "{$field->id}.3" ), floatval( "{$field->id}.5" ) ) ) ) ) {
								?>
								<li id="<?php echo esc_attr( $input['id'] ); ?>"><?php echo esc_html( GFCommon::get_label( $field, $input['id'] ) ); ?></li>
							<?php
							}
						}
					} else if ( ! $field->displayOnly && ! in_array( $field->id, $field_ids ) && RGFormsModel::get_input_type( $field ) != 'list' ) {
						?>
						<li id="<?php echo $field->id ?>"><?php echo esc_html( GFCommon::get_label( $field ) ); ?></li>
					<?php
					}
				}
				?>
				</ul>
			</div>
		</div>

		<div class="panel-buttons">
			<input type="button" value="  <?php esc_attr_e( 'Save', 'gravityforms' ); ?>  " class="button-primary" onclick="SelectColumns();" onkeypress="SelectColumns();" />&nbsp;
			<input type="button" value="<?php esc_attr_e( 'Cancel', 'gravityforms' ); ?>" class="button" onclick="self.parent.tb_remove();" onkeypress="self.parent.tb_remove();" />
		</div>

		</body>
		</html>

	<?php
	}

	/**
	 * Adds the entry meta to the Form object.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::get_entry_meta()
	 *
	 * @param array $form The Form object.
	 *
	 * @return array $form The Form object.
	 */
	public static function get_selectable_entry_meta( $form ) {
		$entry_meta = GFFormsModel::get_entry_meta( $form['id'] );
		$keys       = array_keys( $entry_meta );
		foreach ( $keys as $key ) {
			array_push( $form['fields'], array( 'id' => $key, 'label' => $entry_meta[ $key ]['label'] ) );
		}

		return $form;
	}


}

GFSelectColumns::select_columns_page();