<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFEntryList {

	public static function all_entries_page() {

		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		$forms   = RGFormsModel::get_forms( null, 'title' );
		$form_id = RGForms::get( 'id' );

		if ( sizeof( $forms ) == 0 ) {
			?>
			<div style="margin:50px 0 0 10px;">
				<?php echo sprintf( esc_html__( "You don't have any active forms. Let's go %screate one%s", 'gravityforms' ), '<a href="?page=gf_new_form">', '</a>' ); ?>
			</div>
			<?php
		} else {
			if ( empty( $form_id ) ) {
				$form_id = $forms[0]->id;
			}

			/**
			 * Fires before the entry list content is generated.
			 *
			 * Echoed content would appear above the page title.
			 *
			 * @param int $form_id The ID of the form that the entry list is being displayed for.
			 */
			do_action( 'gform_pre_entry_list', $form_id );

			self::leads_page( $form_id );

			/**
			 * Fires after the entry list content is generated.
			 *
			 * Echoed content would appear after the bulk actions/paging links below the entry list table.
			 *
			 * @param int $form_id The ID of the form that the entry list is being displayed for.
			 */
			do_action( 'gform_post_entry_list', $form_id );
		}
	}

	/**
	 * Returns the default filter for the form ID specified in the URL. If no form ID is specified then the first form is used.
	 * @since 2.0
	 * @return string
	 */
	public static function get_default_filter() {

		$forms   = GFFormsModel::get_forms( null, 'title' );
		$form_id = rgget( 'id' );

		if ( sizeof( $forms ) == 0 ) {
			return '';
		} else {
			if ( empty( $form_id ) ) {
				$form_id = $forms[0]->id;
			}
		}

		$form = GFAPI::get_form( $form_id );

		$filters = self::get_filter_links( $form, false );

		$option_values = self::get_screen_options_values();

		// If the filter is not available for the form then use 'all'
		$selected_filter = 'all';
		foreach ( $filters as $filter ) {
			if ( $option_values['default_filter'] == $filter['id'] ) {
				$selected_filter = $option_values['default_filter'];
				break;
			}
		}

		return $selected_filter;
	}

	/**
	 * Returns the markup for the screen options.
	 *
	 * @since 2.0
	 *
	 * @param $status
	 * @param $args
	 *
	 * @return string
	 */
	public static function get_screen_options_markup( $status, $args ) {

		$return = $status;
		if ( ! GFForms::get_page() == 'entry_list' ) {
			return $return;
		}

		$screen_options = self::get_screen_options_values();

		$per_page = $screen_options['per_page'];

		$forms   = GFFormsModel::get_forms( null, 'title' );
		$form_id = rgget( 'id' );

		if ( sizeof( $forms ) == 0 ) {
			return '';
		} else {
			if ( empty( $form_id ) ) {
				$form_id = $forms[0]->id;
			}
		}

		$form = GFAPI::get_form( $form_id );

		$filters = self::get_filter_links( $form, false );

		$option_values = self::get_screen_options_values();

		// If the filter is not available for the form then use 'all'
		$selected_filter = 'all';
		foreach ( $filters as $filter ) {
			if ( $option_values['default_filter'] == $filter['id'] ) {
				$selected_filter = $option_values['default_filter'];
				break;
			}
		}

		$radios_arr = array();
		foreach ( $filters as $filter ) {
			$id           = esc_attr( $filter['id'] );
			$label        = esc_attr( $filter['label'] );
			$checked      = checked( $filter['id'], $selected_filter, false );
			$radios_arr[] = sprintf( '<input type="radio" name="gform_default_filter" value="%s" id="gform_default_filter_%s" %s /><label for="gform_default_filter_%s">%s</label>', $id, $id, $checked, $id, $label );
		}

		$radios_str = join( "\n", $radios_arr );

		$filter_title  = esc_html__( 'Default Filter', 'gravityforms' );
		$pagination_title = esc_html__( 'Pagination', 'gravityforms' );
		$entries_label = esc_html__( 'Number of entries per page:', 'gravityforms' );

		$button = get_submit_button( esc_html__( 'Apply', 'gravityforms' ), 'button button-primary', 'screen-options-apply', false );
		$return .= "
			<fieldset class='screen-options'>
            <legend>{$filter_title}</legend>
            <div>
				{$radios_str}
            </div>
            </fieldset>
            <fieldset class='screen-options'>
			<h5>{$pagination_title}</h5>

            	<label for='gform_per_page%s'>{$entries_label}</label>
            	<input type='number' step='1' min='1' max='100' class='screen-per-page' name='gform_per_page'
					id='gform_per_page' maxlength='3' value='{$per_page}' />
            	<input type='hidden' name='wp_screen_options[option]' value='gform_entries_screen_options' />
            	<input type='hidden' name='wp_screen_options[value]' value='yes' />
			</fieldset>
			<p class='submit'>
			$button
			</p>";
		return $return;
	}

	/**
	 * Returns the values for the user-specific screen options. If not saved by the current user, the default values are returned.
	 *
	 * @since 2.0
	 * @return array
	 */
	public static function get_screen_options_values() {
		$default_values = array(
			'per_page'       => 20,
			'default_filter' => 'all',
		);

		$option_values = get_user_option( 'gform_entries_screen_options' );

		if ( empty( $option_values ) || ! is_array( $option_values ) ) {
			$option_values = array();
		}
		$option_values = array_merge( $default_values, $option_values );

		return $option_values;
	}

	public static function leads_page( $form_id ) {
		global $wpdb;

		//quit if version of wp is not supported
		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		$form = GFFormsModel::get_form_meta( $form_id );

		$table = new GF_Entry_List_Table( array( 'form_id' => $form_id, 'form' => $form ) );
		$table->prepare_items();
		$table->output_styles();
		$table->output_scripts();

		wp_print_styles( array( 'thickbox' ) );

		echo GFCommon::get_remote_message();

		?>

		<div class="wrap <?php echo GFCommon::get_browser_class() ?>">

			<?php GFCommon::form_page_title( $form ); ?>

			<?php GFCommon::display_dismissible_message(); ?>

			<?php
			GFForms::top_toolbar();
				?>

				<div id="entry_search_container">
					<div id="entry_filters" style=""></div>
					<a style="" class="button" id="entry_search_button"
					   href="javascript:Search('<?php echo esc_js( $table->get_orderby() ); ?>', '<?php echo esc_js( $table->get_order() ) ?>', <?php echo absint( $form_id ); ?>, jQuery('.gform-filter-value').val(), '<?php echo esc_js( $table->get_filter() ) ?>', jQuery('.gform-filter-field').val(), jQuery('.gform-filter-operator').val());"><?php esc_html_e( 'Search', 'gravityforms' ) ?></a>

				</div>

			<form id="entry_list_form" method="post">
				<?php
				$table->views();
				$table->display();
				?>
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

	public static function get_filter_links( $form, $include_counts = true ) {

		$form_id = absint( $form['id'] );

		$summary = $include_counts ? GFFormsModel::get_form_counts( $form_id ) : array();

		$active_entry_count = rgar( $summary, 'total' );
		$unread_count      = rgar( $summary, 'unread' );
		$starred_count     = rgar( $summary, 'starred' );
		$spam_count        = rgar( $summary,'spam' );
		$trash_count       = rgar( $summary,'trash' );

		$filter_links = array(
			array(
				'id' => 'all',
				'field_filters' => array(),
				'count' => $active_entry_count,
				'label'   => esc_html_x( 'All', 'Entry List', 'gravityforms' ),
			),
			array(
				'id' => 'unread',
				'field_filters' => array(
					array( 'key' => 'is_read', 'value' => false ),
				),
				'count' => $unread_count,
				'label'   => esc_html_x( 'Unread', 'Entry List', 'gravityforms' ),
			),
			array(
				'id' => 'star',
				'field_filters' => array(
					array( 'key' => 'is_starred', 'value' => true ),
				),
				'count' => $starred_count,
				'label'   => esc_html_x( 'Starred', 'Entry List', 'gravityforms' ),
			),
		);
		if ( GFCommon::spam_enabled( $form_id ) ) {
			$filter_links[] = array(
				'id' => 'spam',
				'field_filters' => array(),
				'count' => $spam_count,
				'label'   => esc_html__( 'Spam', 'gravityforms' ),
			);
		}
		$filter_links[] = array(
			'id' => 'trash',
			'field_filters' => array(),
			'count' => $trash_count,
			'label'   => esc_html__( 'Trash', 'gravityforms' ),
		);

		/**
		 * Allow the row of filter links to be modified.
		 *
		 * Array elements:
		 * selected - bool
		 * filter   - string
		 * label    - string
		 *
		 * @param array $filter_links The filter links.
		 *
		 */
		$filter_links = apply_filters( 'gform_filter_links_entry_list', $filter_links, $form, $include_counts );

		return $filter_links;
	}

	public static function all_leads_page() {
		self::all_entries_page();
	}
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Class GF_Entry_List_Table
 *
 * @since 2.0
 */
final class GF_Entry_List_Table extends WP_List_Table {

	/**
	 * The current filter e.g. trash, spam, unread
	 *
	 * @var string
	 */
	public $filter = '';

	/**
	 * The name of the primary column. The primary column will not get collapsed on narrower displays.
	 *
	 * @var null|string
	 */
	public $primary_column_name = null;

	/**
	 * The locking mechanism for the entry list.
	 *
	 * @var GFEntryLocking
	 */
	public $locking_info;

	/**
	 * The Form array.
	 *
	 * @var array
	 */
	private $_form;

	/**
	 * Tracks the cuurent row during output.
	 *
	 * @var int
	 */
	public $row_index = 0;

	/**
	 * GF_Entry_List constructor.
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->_form = isset( $args['form'] ) ?  $args['form'] : null;
		if ( ! isset( $this->_form ) ) {
			$form_id = isset( $args['form_id'] ) ? $args['form_id'] : absint( rgget( 'id' ) );

			$this->_form = RGFormsModel::get_form_meta( $form_id );
		}

		$args = wp_parse_args( $args, array(
			'plural' => 'gf_entries',
			'singular' => 'gf_entry',
			'ajax' => false,
			'screen' => null,
			'filter' => sanitize_text_field( rgget( 'filter' ) ),
		) );

		parent::__construct( $args );
		$this->filter = $args['filter'];

		$this->set_columns();

		$this->locking_info = new GFEntryLocking();
	}

	/**
	 * Set the hidden, sortable and primary columns.
	 */
	public function set_columns() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$primary               = $this->get_primary_column_name();
		$this->_column_headers = array( $columns, $hidden, $sortable, $primary );
	}

	/**
	 * Returns the curent filter.
	 *
	 * @return string
	 */
	public function get_filter() {
		return $this->filter;
	}

	/**
	 * Returns the current form array.
	 *
	 * @return array
	 */
	public function get_form() {
		return $this->_form;
	}

	/**
	 * Returns the current form ID.
	 *
	 * @return int
	 */
	public function get_form_id() {
		$form_id = isset( $this->_form ) ? $this->_form['id'] : rgget( 'id' );
		return absint( $form_id );
	}

	/**
	 * Returns an associative array of views.
	 *
	 * @return array
	 */
	function get_views() {
		$views = array();

		$form_id = $this->get_form_id();

		$filter_links = $this->get_filter_links();

		$filter = $this->filter;

		foreach ( $filter_links as $filter_link_index => $filter_link ) {
			$filter_arg = '&filter=';
			if ( $filter_link['id'] !== 'all' ) {
				$filter_arg .= $filter_link['id'];
			}
			if ( $filter == '' ) {
				$selected = $filter_link['id'] == 'all' ? 'current' : '';
			} else {
				$selected = ( $filter == $filter_link['id'] ) ? 'current' : '';
			}
			$link = '<a class="' . $selected . '" href="?page=gf_entries&view=entries&id=' . $form_id . esc_attr( $filter_arg ) . '">' . esc_html( $filter_link['label'] ) .
			        '<span class="count"> (<span	id="' . esc_attr( $filter_link['id'] ) . '_count">' . absint( rgar( $filter_link, 'count' ) ) . '</span>)</span></a>';
			$views[ $filter_link['id'] ] = $link;
		}
		return $views;
	}

	/**
	 * Returns the array of filter links.
	 *
	 * @param bool $include_counts
	 *
	 * @return array|mixed|void
	 */
	public function get_filter_links( $include_counts = true ) {

		$form = $this->get_form();

		return GFEntryList::get_filter_links( $form, $include_counts );
	}

	/**
	 * Gets the ordering for the entry list table.
	 *
	 * Also formats the query string to uppercase. If none is present, sets it to ascending.
	 *
	 * @since 2.0.3.6
	 * @access public
	 *
	 * @return string The ordering to be used.
	 */
	public function get_order() {
		return empty( $_GET['order'] ) ? 'ASC' : strtoupper( $_GET['order'] );
	}

	/**
	 * Gets the column that list is ordered by.
	 *
	 * If none is set, defaults to 0 (the first column)
	 *
	 * @since 2.0.3.6
	 * @access public
	 *
	 * @return int The column to be used.
	 */
	public function get_orderby() {
		return empty( $_GET['orderby'] ) ? 0 : $_GET['orderby'];
	}

	/**
	 * Performs the search and prepares the entries for display.
	 */
	function prepare_items() {

		$this->process_action();

		$form_id = $this->get_form_id();

		$page_index = empty( $_GET['paged'] ) ? 0 : absint( $_GET['paged'] - 1 );

		$form       = RGFormsModel::get_form_meta( $form_id );

		$search_criteria = $this->get_search_criteria();

		$sort_direction = $this->get_order();

		$sort_field = $this->get_orderby();

		$sort_field_meta = RGFormsModel::get_field( $form, $sort_field );

		$is_numeric      = $sort_field_meta instanceof GF_Field ? $sort_field_meta->type == 'number' : $sort_field_meta['type'];

		$screen_options = get_user_option( 'gform_entries_screen_options' );
		$page_size = isset( $screen_options['per_page'] ) ? absint( $screen_options['per_page'] ) : 20;

		$page_size        = gf_apply_filters( array( 'gform_entry_page_size', $form_id ), $page_size, $form_id );
		$first_item_index = $page_index * $page_size;

		if ( ! empty( $sort_field ) ) {
			$sorting = array( 'key' => $_GET['orderby'], 'direction' => $sort_direction, 'is_numeric' => $is_numeric );
		} else {
			$sorting = array();
		}

		$paging      = array( 'offset' => $first_item_index, 'page_size' => $page_size );
		$total_count = 0;

		/**
		 * Filter the arguments that will be used to fetch entries for display on the Entry List view.
		 *
		 * @since 2.2.3.4
		 *
		 * @param array $args {
		 *
		 *     Array of arguments that will be passed to GFAPI::get_entries() to fetch the entries to be displayed.
		 *
		 *     @var int $form_id The form ID for which entries will be loaded.
		 *     @var array $search_criteria An array of search critiera that will be used to filter entries.
		 *     @var array $sorting An array containing properties that specify how the entries will be sorted.
		 *     @var array $paging An array containing properties that specify how the entries will be paginated.
		 * }
		 */
		$args = gf_apply_filters( array( 'gform_get_entries_args_entry_list', $form_id ), compact( 'form_id', 'search_criteria', 'sorting', 'paging' ) );

		$entries = GFAPI::get_entries( $args['form_id'], $args['search_criteria'], $args['sorting'], $args['paging'], $total_count );

		$this->set_pagination_args( array(
			'total_items' => $total_count,
			'per_page'    => $args['paging']['page_size'],
		) );

		$this->items = $entries;
	}

	/**
	 * Returns the array of search criteria.
	 *
	 * @return array
	 */
	function get_search_criteria() {

		$search_criteria = array();

		$filter_links = $this->get_filter_links( false );

		foreach ( $filter_links as $filter_link ) {
			if ( $this->filter == $filter_link['id'] ) {
				$search_criteria['field_filters'] = $filter_link['field_filters'];
				break;
			}
		}

		$search_field_id = rgget( 'field_id' );

		$search_operator = rgget( 'operator' );

		$status = in_array( $this->filter, array( 'trash', 'spam' ) ) ? $this->filter : 'active';
		$search_criteria['status'] = $status;

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
			$form = $this->get_form();
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

		$form_id = $this->get_form_id();

		/**
		 * Allow the entry list search criteria to be overridden.
		 *
		 * @since  1.9.14.30
		 *
		 * @param array $search_criteria An array containing the search criteria.
		 * @param int $form_id The ID of the current form.
		 */
		$search_criteria = gf_apply_filters( array( 'gform_search_criteria_entry_list', $form_id ), $search_criteria, $form_id );

		return $search_criteria;
	}

	/**
	 * Returns the associative array of columns for the table.
	 *
	 * @return array
	 */
	function get_columns() {
		$table_columns = array(
			'cb' => '<input type="checkbox" />',
		);
		if ( ! in_array( $this->filter, array( 'trash', 'spam' ) ) ) {
			$table_columns['is_starred'] = '';
		}
		$form_id = $this->get_form_id();
		$columns = GFFormsModel::get_grid_columns( $form_id, true );
		foreach ( $columns as $key => $column_info ) {
			$table_columns[ 'field_id-' . $key ] = $column_info['label'];
		}

		if ( empty( $columns ) ) {
			$table_columns['field_id-id'] = esc_html__( 'Entry Id', 'gravityforms' );
		}

		$table_columns['column_selector'] = '<a title="' . esc_attr__( 'click to select columns to display', 'gravityforms' ) . '" href="' . trailingslashit( site_url( null, 'admin' ) ) . '?gf_page=select_columns&id=' . absint( $form_id ) . '&TB_iframe=true&height=365&width=600" class="thickbox entries_edit_icon"><i class="fa fa-cog"></i></a>';

		/**
		 * Allow the columns to be displayed in the entry list table to be overridden.
		 *
		 * @since 2.0.7.6
		 *
		 * @param array $table_columns The columns to be displayed in the entry list table.
		 * @param int   $form_id       The ID of the form the entries to be listed belong to.
		 */
		$table_columns = apply_filters( 'gform_entry_list_columns', $table_columns, $form_id );

		return apply_filters( 'gform_entry_list_columns_' . $form_id, $table_columns, $form_id );
	}

	/**
	 * Returns the associative array of sortable columns for the table.
	 *
	 * @return array
	 */
	function get_sortable_columns() {
		$form_id = $this->get_form_id();
		$columns = GFFormsModel::get_grid_columns( $form_id, true );
		$table_columns = array();
		foreach ( $columns as $key => $column_info ) {
			$table_columns[ 'field_id-' . (string) $key ] = array( (string) $key, false );
		}
		return $table_columns;
	}

	/**
	 * Displays the checkbox column.
	 *
	 * @param array $entry
	 */
	function column_cb( $entry ) {
		$entry_id = $entry['id'];
		?>
		<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $entry_id ); ?>"><?php _e( 'Select entry' ); ?></label>
		<input type="checkbox" class="gform_list_checkbox" name="entry[]" value="<?php echo esc_attr( $entry_id ); ?>" />
		<?php
		$this->locking_info->lock_indicator();
	}

	/**
	 * Displays an empty cell for the column selector column.
	 *
	 * @param $entry
	 *
	 * @return string
	 */
	function column_column_selector( $entry ) {
		return '';
	}

	/**
	 * Displays the is_starred row for the given entry.
	 *
	 * @param $entry
	 * @param $classes
	 * @param $data
	 * @param $primary
	 */
	function _column_is_starred( $entry, $classes, $data, $primary ) {
		echo '<th scope="row" class="manage-column column-is_starred">';
		if ( $this->filter !== 'trash' ) {
			?>
			<img id="star_image_<?php echo esc_attr( $entry['id'] ) ?>" src="<?php echo GFCommon::get_base_url() ?>/images/star<?php echo intval( $entry['is_starred'] ) ?>.png" onclick="ToggleStar(this, '<?php echo esc_js( $entry['id'] ); ?>','<?php echo esc_js( $this->filter ); ?>');" />
			<?php
		}
		echo '</th>';
	}

	/**
	 * Displays the entry value.
	 *
	 * @param object $entry
	 * @param string $field_id
	 */
	function column_default( $entry, $column_id ) {
		$field_id = (string) str_replace( 'field_id-', '', $column_id );
		$form     = $this->get_form();
		$form_id  = $this->get_form_id();
		$field    = GFFormsModel::get_field( $form, $field_id );
		$columns  = GFFormsModel::get_grid_columns( $form_id, true );
		$value    = rgar( $entry, $field_id );

		if ( ! empty( $field ) && $field->type == 'post_category' ) {
			$value = GFCommon::prepare_post_category_value( $value, $field, 'entry_list' );
		}

		// Filtering lead value
		$value = apply_filters( 'gform_get_field_value', $value, $entry, $field );

		switch ( $field_id ) {

			case 'source_url' :
				$value = "<a href='" . esc_attr( $entry['source_url'] ) . "' target='_blank' alt='" . esc_attr( $entry['source_url'] ) . "' title='" . esc_attr( $entry['source_url'] ) . "'>.../" . esc_attr( GFCommon::truncate_url( $entry['source_url'] ) ) . '</a>';
				break;

			case 'date_created' :
			case 'payment_date' :
				$value = GFCommon::format_date( $value, false );
				break;

			case 'payment_amount' :
				$value = GFCommon::to_money( $value, $entry['currency'] );
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
					$value = $field->get_value_entry_list( $value, $entry, $field_id, $columns, $form );
				} else {
					$value = esc_html( $value );
				}
		}

		$value = apply_filters( 'gform_entries_field_value', $value, $form_id, $field_id, $entry );

		$primary      = $this->get_primary_column_name();
		$query_string = $this->get_detail_query_string( $entry );

		if ( $column_id == $primary ) {
			$edit_url = $this->get_detail_url( $entry );
			echo '<a title="' . esc_attr__( 'View this entry', 'gravityforms' ) . '" href="' . $edit_url .'">' . $value . '</a>';
		} else {

			/**
			 * Used to inject markup and replace the value of any non-first column in the entry list grid.
			 *
			 * @param string $value        The value of the field
			 * @param int    $form_id      The ID of the current form
			 * @param int    $field_id     The ID of the field
			 * @param array  $entry        The Entry object
			 * @param string $query_string The current page's query string
			 */
			echo apply_filters( 'gform_entries_column_filter', $value, $form_id, $field_id, $entry, $query_string );

			// Maintains gap between value and content from gform_entries_column which existed when using 1.9 and earlier.
			echo '&nbsp; ';

			/**
			 * Fired within the entries column
			 *
			 * Used to insert additional entry details
			 *
			 * @param int    $form_id      The ID of the current form
			 * @param int    $field_id     The ID of the field
			 * @param string $value        The value of the field
			 * @param array  $entry        The Entry object
			 * @param string $query_string The current page's query string
			 */
			do_action( 'gform_entries_column', $form_id, $field_id, $value, $entry, $query_string );
		}

	}

	/**
	 * Returns the entry detail query string.
	 *
	 * @param $entry
	 *
	 * @return string
	 */
	function get_detail_query_string( $entry ) {
		$form_id = $this->get_form_id();

		$search = stripslashes( rgget( 's' ) );

		$search_field_id = rgget( 'field_id' );
		$search_operator = rgget( 'operator' );

		$order   = $this->get_order();
		$orderby = $this->get_orderby();

		$search_qs  = empty( $search ) ? '' : '&s=' . esc_attr( urlencode( $search ) );
		$orderby_qs = empty( $orderby ) ? '' : '&orderby=' . esc_attr( $orderby );
		$order_qs   = empty( $order ) ? '' : '&order=' . esc_attr( $order );
		$filter_qs  = '&filter=' . esc_attr( $this->filter );

		$page_size  = $this->get_pagination_arg( 'per_page' );
		$page_num   = $this->get_pagenum();
		$page_index = $page_num - 1;

		$position = ( $page_size * $page_index ) + $this->row_index;

		$edit_url = 'page=gf_entries&view=entry&id=' . absint( $form_id ) . '&lid=' . esc_attr( $entry['id'] ) . $search_qs . $orderby_qs . $order_qs . $filter_qs . '&paged=' . $page_num .'&pos=' . $position .'&field_id=' . esc_attr( $search_field_id ) .  '&operator=' .  esc_attr( $search_operator );
		return $edit_url;
	}

	/**
	 * Returns the entry detail url.
	 *
	 * @param $entry
	 *
	 * @return string|void
	 */
	function get_detail_url( $entry ) {
		$query_string = $this->get_detail_query_string( $entry );
		$url          = admin_url( 'admin.php?' . $query_string );

		return $url;
	}

	/**
	 * Displays a single row.
	 *
	 * @param array $entry
	 */
	public function single_row( $entry ) {
		$class = 'entry_row';
		$class .= $entry['is_read'] ? '' : ' entry_unread';
		$class .= $this->locking_info->list_row_class( $entry['id'], false );
		$class .= $entry['is_starred'] ? ' entry_starred' : '';
		$class .= in_array( $this->filter, array( 'trash', 'spam' ) ) ? ' entry_spam_trash' : '';
		echo sprintf( '<tr id="entry_row_%d" class="%s" data-id="%d">', $entry['id'], $class, $entry['id'] );
		$this->single_row_columns( $entry );
		echo '</tr>';
	}

	/**
	 * Displays the no items message according to the context.
	 */
	function no_items() {

		switch ( $this->filter ) {
			case 'unread' :
				$message = isset( $_GET['field_id'] ) ? esc_html__( 'This form does not have any unread entries matching the search criteria.', 'gravityforms' ) : esc_html__( 'This form does not have any unread entries.', 'gravityforms' );
				break;

			case 'star' :
				$message = isset( $_GET['field_id'] ) ? esc_html__( 'This form does not have any starred entries matching the search criteria.', 'gravityforms' ) : esc_html__( 'This form does not have any starred entries.', 'gravityforms' );
				break;

			case 'spam' :
				$message = esc_html__( 'This form does not have any spam.', 'gravityforms' );
				break;

			case 'trash' :
				$message = isset( $_GET['field_id'] ) ? esc_html__( 'This form does not have any entries in the trash matching the search criteria.', 'gravityforms' ) : esc_html__( 'This form does not have any entries in the trash.', 'gravityforms' );
				break;

			default :
				$message = isset( $_GET['field_id'] ) ? esc_html__( 'This form does not have any entries matching the search criteria.', 'gravityforms' ) : esc_html__( 'This form does not have any entries yet.', 'gravityforms' );

		}
		echo $message;
	}

	/**
	 * Displays the row action if the column is primary.
	 *
	 * @param array $entry
	 * @param string $column_name
	 * @param string $primary
	 *
	 * @return string
	 */
	protected function handle_row_actions( $entry, $column_name, $primary ) {

		if ( $primary !== $column_name ) {
			return '';
		}

		$form_id = $this->get_form_id();

		$field_id = (string) str_replace( 'field_id-', '', $column_name );

		$value = $entry[ $field_id ];

		$detail_url = $this->get_detail_url( $entry );
		?>
		<div class="row-actions">
			<?php

			switch ( $this->filter ) {
				case 'trash' :
					?>
					<span class="edit">
                        <a title="<?php esc_attr_e( 'View this entry', 'gravityforms' ); ?>" href="<?php echo esc_url( $detail_url ) ?>"><?php esc_html_e( 'View', 'gravityforms' ); ?></a>
                        |
                    </span>

					<span class="edit">
                        <a data-wp-lists='delete:the-list:entry_row_<?php echo esc_attr( $entry['id'] );?>::status=active&entry=<?php echo esc_attr( $entry['id'] ); ?>' title="<?php esc_attr_e( 'Restore this entry', 'gravityforms' ) ?>" href="<?php echo wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) ?>"><?php esc_html_e( 'Restore', 'gravityforms' ); ?></a>
						<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ? '|' : '' ?>
                    </span>

					<?php
					if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
						?>
						<span class="delete">
                            <?php
                            $delete_link = '<a data-wp-lists="delete:the-list:entry_row_' . esc_attr( $entry['id'] ) . '::status=delete&entry=' . esc_attr( $entry['id'] ) . '" title="' . esc_attr__( 'Delete this entry permanently', 'gravityforms' ) . '"  href="' . wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) . '">' . esc_html__( 'Delete Permanently', 'gravityforms' ) . '</a>';

                            /**
                             * Allows for modification of a Form entry "delete" link
                             *
                             * @param string $delete_link The Entry Delete Link (Formatted in HTML)
                             */
                            echo apply_filters( 'gform_delete_entry_link', $delete_link );
                            ?>
                        </span>
						<?php
					}
					break;

				case 'spam' :
					?>
					<span class="edit">
                        <a title="<?php esc_attr_e( 'View this entry', 'gravityforms' ); ?>" href="<?php echo esc_url( $detail_url ) ?>"><?php esc_html_e( 'View', 'gravityforms' ); ?></a>
                        |
                    </span>

					<span class="unspam">
                        <a data-wp-lists='delete:the-list:entry_row_<?php echo esc_attr( $entry['id'] ); ?>::status=unspam&entry=<?php echo esc_attr( $entry['id'] ); ?>' title="<?php esc_attr_e( 'Mark this entry as not spam', 'gravityforms' ) ?>" href="<?php echo wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) ?>"><?php esc_html_e( 'Not Spam', 'gravityforms' ); ?></a>
						<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ? '|' : '' ?>
                    </span>

					<?php
					if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
						?>
						<span class="delete">
                            <?php
							$delete_link = '<a data-wp-lists="delete:the-list:entry_row_' . esc_attr( $entry['id'] ) . '::status=delete&entry=' . esc_attr( $entry['id'] ) . '" title="' . esc_attr__( 'Delete this entry permanently', 'gravityforms' ) . '"  href="' . wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) . '">' . esc_html__( 'Delete Permanently', 'gravityforms' ) . '</a>';

							/**
							 * Allows for modification of a Form entry "delete" link
							 *
							 * @param string $delete_link The Entry Delete Link (Formatted in HTML)
							 */
							echo apply_filters( 'gform_delete_entry_link', $delete_link );
							?>
                        </span>
						<?php
					}

					break;

				default:
					?>
					<span class="edit">
                        <a title="<?php esc_attr_e( 'View this entry', 'gravityforms' ); ?>" href="<?php echo esc_url( $detail_url ) ?>"><?php esc_html_e( 'View', 'gravityforms' ); ?></a>
                        |
                    </span>
					<span class="edit">
                        <a id="mark_read_<?php echo esc_attr( $entry['id'] ); ?>" title="Mark this entry as read" href="javascript:ToggleRead('<?php echo esc_js( $entry['id'] ); ?>', '<?php echo esc_js( $this->filter ); ?>');" style="display:<?php echo $entry['is_read'] ? 'none' : 'inline' ?>;"><?php esc_html_e( 'Mark read', 'gravityforms' ); ?></a><a id="mark_unread_<?php echo absint( $entry['id'] ); ?>" title="<?php esc_attr_e( 'Mark this entry as unread', 'gravityforms' ); ?>" href="javascript:ToggleRead('<?php echo esc_js( $entry['id'] ); ?>', '<?php echo esc_js( $this->filter ); ?>');" style="display:<?php echo $entry['is_read'] ? 'inline' : 'none' ?>;"><?php esc_html_e( 'Mark unread', 'gravityforms' ); ?></a>
						<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) || GFCommon::akismet_enabled( $form_id ) ? '|' : '' ?>
                    </span>
					<?php
					if ( GFCommon::spam_enabled( $form_id ) ) {
						?>
						<span class="spam">
                            <a data-wp-lists='delete:the-list:entry_row_<?php echo esc_attr( $entry['id'] ) ?>::status=spam&entry=<?php echo esc_attr( $entry['id'] ); ?>' title="<?php esc_attr_e( 'Mark this entry as spam', 'gravityforms' ) ?>" href="<?php echo wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) ?>"><?php esc_html_e( 'Spam', 'gravityforms' ); ?></a>
							<?php echo GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ? '|' : '' ?>
                        </span>

						<?php
					}
					if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
						?>
						<span class="trash">
	                        <a data-wp-lists='delete:the-list:entry_row_<?php echo esc_attr( $entry['id'] ); ?>::status=trash&entry=<?php echo esc_attr( $entry['id'] ); ?>' title="<?php esc_attr_e( 'Move this entry to the trash', 'gravityforms' ) ?>" href="<?php echo wp_nonce_url( '?page=gf_entries', 'gf_delete_entry' ) ?>"><?php esc_html_e( 'Trash', 'gravityforms' ); ?></a>
	                    </span>
						<?php
					}
					break;
			}

			$query_string = $this->get_detail_query_string( $entry );

			do_action( 'gform_entries_first_column_actions', $form_id, $field_id, $value, $entry, $query_string );

			?>
		</div>
		<?php
		/**
		 * Fires at the end of the first entry column
		 *
		 * Used to add content to the entry list's first column
		 *
		 * @param int    $form_id      The ID of the current form
		 * @param int    $field_id     The ID of the field
		 * @param string $value        The value of the field
		 * @param array  $entry         The Entry object
		 * @param string $query_string The current page's query string
		 */
		do_action( 'gform_entries_first_column', $form_id, $field_id, $value, $entry, $query_string );

		$this->row_index++;
		return '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';
	}

	/**
	 * Returns the name of the primary column.
	 *
	 * @return string
	 */
	function get_primary_column_name() {
		if ( ! isset( $this->primary_column_name ) ) {
			$columns = $this->get_columns();
			$column_keys = array_keys( $columns );
			$column_index = in_array( $this->filter, array( 'trash', 'spam' ) ) ? 1 : 2;
			$primary = isset( $column_keys[ $column_index ] ) ? $column_keys[ $column_index ] : '';
			$this->primary_column_name = $primary;
		}
		return $this->primary_column_name;
	}

	/**
	 * Returns the options for the bulk actions menu.
	 *
	 * @return array
	 */
	function get_bulk_actions() {

		$actions = array();

		switch ( $this->filter ) {
			case 'trash' :
				$actions['restore'] = esc_html__( 'Restore', 'gravityforms' );
				$actions['delete']  = esc_html__( 'Delete Permanently', 'gravityforms' );
				break;
			case 'spam' :
				$actions['unspam'] = esc_html__( 'Not Spam', 'gravityforms' );
				$actions['delete'] = esc_html__( 'Delete Permanently', 'gravityforms' );
				break;

			default:
				$actions['mark_read']            = esc_html__( 'Mark as Read', 'gravityforms' );
				$actions['mark_unread']          = esc_html__( 'Mark as Unread', 'gravityforms' );
				$actions['add_star']             = esc_html__( 'Add Star', 'gravityforms' );
				$actions['remove_star']          = esc_html__( 'Remove Star', 'gravityforms' );
				$actions['resend_notifications'] = esc_html__( 'Resend Notifications', 'gravityforms' );
				$actions['print']                = esc_html__( 'Print', 'gravityforms' );

				if ( GFCommon::spam_enabled( $this->get_form_id() ) ) {
					$actions['spam'] = esc_html__( 'Spam', 'gravityforms' );
				}
				if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
					$actions['trash'] = esc_html__( 'Trash', 'gravityforms' );
				}
		}

		// Get the current form ID.
		$form_id = $this->get_form_id();

		/**
		 * Modifies available bulk actions for the entries list.
		 *
		 * @since 2.2.3.12
		 *
		 * @param array $actions Bulk actions.
		 * @param int   $form_id The ID of the current form.
		 */
		return gf_apply_filters( array( 'gform_entry_list_bulk_actions', $form_id ), $actions, $form_id );

	}

	/**
	 * Displays the bulk actions.
	 *
	 * @param string $which
	 */
	function bulk_actions( $which = '' ) {
		parent::bulk_actions( $which );

		$filter = $this->filter;

		if ( in_array( $filter, array( 'trash', 'spam' ) ) ) {
			$message      = $filter == 'trash' ? esc_html__( "WARNING! This operation cannot be undone. Empty trash? 'Ok' to empty trash. 'Cancel' to abort.", 'gravityforms' ) : esc_html__( "WARNING! This operation cannot be undone. Permanently delete all spam? 'Ok' to delete. 'Cancel' to abort.", 'gravityforms' );
			$button_label = $filter == 'trash' ? __( 'Empty Trash', 'gravityforms' ) : __( 'Delete All Spam', 'gravityforms' );
			?>
			<input type="submit" class="button" name="button_delete_permanently"
			       value="<?php echo esc_attr( $button_label ); ?>"
			       onclick="return confirm('<?php echo esc_js( $message ) ?>');"/>
			<?php
		}
	}

	/**
	 * Processes a bulk or single action.
	 */
	function process_action() {

		$single_action = rgpost( 'single_action' );

		$bulk_action = $this->current_action();

		$delete_permanently = (bool) rgpost( 'button_delete_permanently' );

		if ( ! ( $single_action || $bulk_action || $delete_permanently ) ) {
			return;
		}

		check_admin_referer( 'gforms_entry_list', 'gforms_entry_list' );

		$form_id = $this->get_form_id();

		if ( $delete_permanently ) {
			if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
				RGFormsModel::delete_leads_by_form( $form_id, $this->filter );
			}
			return;
		}

		if ( $single_action ) {
			$entry_id = rgpost( 'single_action_argument' );
			switch ( $single_action ) {
				case 'delete' :
					if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
						RGFormsModel::delete_lead( $entry_id );
						$message = esc_html__( 'Entry deleted.', 'gravityforms' );
					} else {
						$message = esc_html__( "You don't have adequate permission to delete entries.", 'gravityforms' );
					}

					break;
				case 'change_columns':
					$columns = GFCommon::json_decode( stripslashes( $_POST['grid_columns'] ), true );
					RGFormsModel::update_grid_column_meta( $form_id, $columns );
					$this->set_columns();
					break;

			}

			/**
			 * Fires after the default entry list actions have been processed.
			 *
			 * @param string $action  Action being performed.
			 * @param array  $entries The entry IDs the action is being applied to.
			 * @param int    $form_id The current form ID.
			 */
			gf_do_action( array( 'gform_entry_list_action', $single_action, $form_id ), $single_action, array( $entry_id ), $form_id );

		} elseif ( $bulk_action ) {

			$select_all  = rgpost( 'all_entries' );
			$search_criteria = $this->get_search_criteria();

			$entries       = empty( $select_all ) ? $_POST['entry'] : GFFormsModel::search_lead_ids( $form_id, $search_criteria );

			$entry_count = count( $entries ) > 1 ? sprintf( esc_html__( '%d entries', 'gravityforms' ), count( $entries ) ) : esc_html__( '1 entry', 'gravityforms' );

			switch ( $bulk_action ) {
				case 'delete':
					if ( GFCommon::current_user_can_any( 'gravityforms_delete_entries' ) ) {
						RGFormsModel::delete_leads( $entries );
						$message = sprintf( esc_html__( '%s deleted.', 'gravityforms' ), $entry_count );
					} else {
						$message = esc_html__( "You don't have adequate permission to delete entries.", 'gravityforms' );
					}
					break;

				case 'trash':
					RGFormsModel::update_leads_property( $entries, 'status', 'trash' );
					$message = sprintf( esc_html__( '%s moved to Trash.', 'gravityforms' ), $entry_count );
					break;

				case 'restore':
					RGFormsModel::update_leads_property( $entries, 'status', 'active' );
					$message = sprintf( esc_html__( '%s restored from the Trash.', 'gravityforms' ), $entry_count );
					break;

				case 'unspam':
					RGFormsModel::update_leads_property( $entries, 'status', 'active' );
					$message = sprintf( esc_html__( '%s restored from the spam.', 'gravityforms' ), $entry_count );
					break;

				case 'spam':
					RGFormsModel::update_leads_property( $entries, 'status', 'spam' );
					$message = sprintf( esc_html__( '%s marked as spam.', 'gravityforms' ), $entry_count );
					break;

				case 'mark_read':
					RGFormsModel::update_leads_property( $entries, 'is_read', 1 );
					$message = sprintf( esc_html__( '%s marked as read.', 'gravityforms' ), $entry_count );
					break;

				case 'mark_unread':
					RGFormsModel::update_leads_property( $entries, 'is_read', 0 );
					$message = sprintf( esc_html__( '%s marked as unread.', 'gravityforms' ), $entry_count );
					break;

				case 'add_star':
					RGFormsModel::update_leads_property( $entries, 'is_starred', 1 );
					$message = sprintf( esc_html__( '%s starred.', 'gravityforms' ), $entry_count );
					break;

				case 'remove_star':
					RGFormsModel::update_leads_property( $entries, 'is_starred', 0 );
					$message = sprintf( esc_html__( '%s unstarred.', 'gravityforms' ), $entry_count );
					break;

			}

			/**
			 * Fires after the default entry list actions have been processed.
			 *
			 * @param string $action  Action being performed.
			 * @param array  $entries The entry IDs the action is being applied to.
			 * @param int    $form_id The current form ID.
			 */
			gf_do_action( array( 'gform_entry_list_action', $bulk_action, $form_id ), $bulk_action, $entries, $form_id );

		}

		if ( ! empty( $message ) ) {
			echo '<div id="message" class="updated notice is-dismissible"><p>' . $message . '</p></div>';
		};
	}

	/**
	 * Displays additional fields required by FORM and displays the modals.
	 *
	 * @param string $which
	 */
	function extra_tablenav( $which ) {
		if ( $which !== 'top' ) {
			return;
		}
		wp_nonce_field( 'gforms_entry_list', 'gforms_entry_list' );
		?>
		<input type="hidden" value="" name="grid_columns" id="grid_columns" />
		<input type="hidden" value="" name="all_entries" id="all_entries" />
		<input type="hidden" id="single_action" name="single_action" />
		<input type="hidden" id="single_action_argument" name="single_action_argument" />
		<?php
		$this->modals();
	}

	/**
	 * Output the styles
	 */
	function output_styles() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		?>
		<link rel="stylesheet" href="<?php echo GFCommon::get_base_url() ?>/css/admin<?php echo $min; ?>.css?ver=<?php echo GFForms::$version ?>" type="text/css" />
		<?php
	}

	/**
	 * Output scripts
	 */
	function output_scripts() {

		$form_id = $this->get_form_id();
		$form    = $this->get_form();
		$search  = isset( $_GET['s'] ) ? stripslashes( $_GET['s'] ) : null;

		$orderby      = empty( $_GET['orderby'] ) ? 0 : $_GET['orderby'];
		$order = empty( $_GET['order'] ) ? 'ASC' : strtoupper( $_GET['order'] );

		$filter = sanitize_text_field( rgget( 'filter ' ) );

		$field_filters = GFCommon::get_field_filter_settings( $form );

		$search_field_id = rgget( 'field_id' );
		$search_operator = rgget( 'operator' );

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
			),
		);

		?>

		<script type="text/javascript">

			var messageTimeout = false,
				gformFieldFilters = <?php echo json_encode( $field_filters ) ?>,
				gformInitFilter = <?php echo json_encode( $init_filter_vars ) ?>;

			function ChangeColumns(columns) {
				jQuery("#single_action").val("change_columns");
				jQuery("#grid_columns").val(jQuery.toJSON(columns));
				tb_remove();
				jQuery("#entry_list_form")[0].submit();
			}

			function Search(sort_field_id, sort_direction, form_id, search, filter, field_id, operator) {
				var search_qs = search == "" ? "" : "&s=" + encodeURIComponent(search);
				var filter_qs = filter == "" ? "" : "&filter=" + filter;
				var field_id_qs = field_id == "" ? "" : "&field_id=" + field_id;
				var operator_qs = operator == "" ? "" : "&operator=" + operator;

				var location = "?page=gf_entries&view=entries&id=" + form_id + "&orderby=" + sort_field_id + "&order=" + sort_direction + search_qs + filter_qs + field_id_qs + operator_qs;
				document.location = location;
			}

			function ToggleStar(img, lead_id, filter) {
				var is_starred = img.src.indexOf("star1.png") >= 0;
				if (is_starred)
					img.src = img.src.replace("star1.png", "star0.png");
				else
					img.src = img.src.replace("star0.png", "star1.png");

				jQuery("#entry_row_" + lead_id).toggleClass("entry_starred");
				//if viewing the starred entries, hide the row and adjust the paging counts
				if (filter == "star") {
					var title = jQuery("#entry_row_" + lead_id);
					title.css("display", 'none');
					UpdatePagingCounts(1);
				}

				UpdateCount("star_count", is_starred ? -1 : 1);

				UpdateEntryProperty(lead_id, "is_starred", is_starred ? 0 : 1);
			}

			function ToggleRead(entry_id, filter) {
				var title = jQuery("#entry_row_" + entry_id);
				var marking_read = title.hasClass("entry_unread");

				jQuery("#mark_read_" + entry_id).css("display", marking_read ? "none" : "inline");
				jQuery("#mark_unread_" + entry_id).css("display", marking_read ? "inline" : "none");
				jQuery("#is_unread_" + entry_id).css("display", marking_read ? "inline" : "none");
				title.toggleClass("entry_unread");
				//if viewing the unread entries, hide the row and adjust the paging counts
				if (filter == "unread") {
					title.css("display", "none");
					UpdatePagingCounts(1);
				}

				UpdateCount("unread_count", marking_read ? -1 : 1);
				UpdateEntryProperty(entry_id, "is_read", marking_read ? 1 : 0);
			}

			function UpdateEntryProperty(entry_id, name, value) {
				var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' )?>");
				mysack.execute = 1;
				mysack.method = 'POST';
				mysack.setVar("action", "rg_update_lead_property");
				mysack.setVar("rg_update_lead_property", "<?php echo wp_create_nonce( 'rg_update_lead_property' ) ?>");
				mysack.setVar("lead_id", entry_id);
				mysack.setVar("name", name);
				mysack.setVar("value", value);
				mysack.onError = function () {
					alert(<?php echo json_encode( __( 'Ajax error while setting lead property', 'gravityforms' ) ); ?>)
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
				jQuery("#single_action").val("delete");
				jQuery("#asingle_ction_argument").val(lead_id);
				jQuery("#entry_list_form")[0].submit();
				return true;
			}

			function handleBulkApply(actionElement) {

				var action = jQuery("#" + actionElement).val();
				var defaultModalOptions = '';
				var leadIds = getLeadIds();

				if (leadIds.length == 0) {
					alert(<?php echo json_encode( __( 'Please select at least one entry.', 'gravityforms' ) ); ?>);
					return false;
				}

				switch (action) {

					case 'resend_notifications':
						resetResendNotificationsUI();
						tb_show(<?php echo json_encode( esc_html__( 'Resend Notifications', 'gravityforms' ) ); ?>, '#TB_inline?width=350&amp;inlineId=notifications_modal_container', '');
						return false;
						break;

					case 'print':
						resetPrintUI();
						tb_show(<?php echo json_encode( esc_html__( 'Print Entries', 'gravityforms' ) ); ?>, '#TB_inline?width=350&amp;height=250&amp;inlineId=print_modal_container', '');
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

				var leads = jQuery(".check-column input[name='entry[]']:checked");
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
					displayMessage(<?php echo json_encode( esc_html__( 'You must select at least one type of notification to resend.', 'gravityforms' ) ); ?>, "error", "#notifications_container");
					return;
				}

				jQuery('#please_wait_container').fadeIn();

				jQuery.post(ajaxurl, {
						action                 : "gf_resend_notifications",
						gf_resend_notifications: '<?php echo wp_create_nonce( 'gf_resend_notifications' ); ?>',
						notifications          : jQuery.toJSON(selectedNotifications),
						sendTo                 : sendTo,
						leadIds                : leadIds,
						filter                 : <?php echo json_encode( rgget( 'filter' ) ) ?>,
						search                 : <?php echo json_encode( rgget( 's' ) ) ?>,
						operator               : <?php echo json_encode( rgget( 'operator' ) ) ?>,
						fieldId                : <?php echo json_encode( rgget( 'field_id' ) ) ?>,
						formId                 : <?php echo json_encode( $form_id ); ?>
					},
					function (response) {

						jQuery('#please_wait_container').hide();

						if (response) {
							displayMessage(response, 'error', '#notifications_container');
						} else {
							var message = <?php echo json_encode( __( 'Notifications for %s were resent successfully.', 'gravityforms' ) ); ?>;
							var c = leadIds == 0 ? gformVars.countAllEntries : leadIds.length;
							displayMessage(message.replace('%s', c + ' ' + getPlural(c, <?php echo json_encode( __( 'entry', 'gravityforms' ) ); ?>, <?php echo json_encode( __( 'entries', 'gravityforms' ) ); ?>)), "updated", "#entry_list_form");
							closeModal(true);
						}

					}
				);

			}

			function resetResendNotificationsUI() {

				jQuery(".gform_notifications").attr('checked', false);
				jQuery('#notifications_container .message, #notifications_override_settings').hide();

			}

			function BulkPrint() {

				var leadIds = getLeadIds();
				if (leadIds != 0)
					leadIds = leadIds.join(',');
				var leadsQS = '&lid=' + leadIds;
				var notesQS = jQuery('#gform_print_notes').is(':checked') ? '&notes=1' : '';
				var pageBreakQS = jQuery('#gform_print_page_break').is(':checked') ? '&page_break=1' : '';
				var filterQS = '&filter=' + <?php echo json_encode( rgget( 'filter' ) ) ?>;
				var searchQS = '&s=' + <?php echo json_encode( rgget( 's' ) ) ?>;
				var searchFieldIdQS = '&field_id=' + <?php echo json_encode( rgget( 'field_id' ) ) ?>;
				var searchOperatorQS = '&operator=' + <?php echo json_encode( rgget( 'operator' ) ) ?>;

				var url = '<?php echo trailingslashit( site_url() ) ?>?gf_page=print-entry&fid=<?php echo absint( $form['id'] ) ?>' + leadsQS + notesQS + pageBreakQS + filterQS + searchQS + searchFieldIdQS + searchOperatorQS;
				window.open(url, 'printwindow');

				closeModal(true);
				hideMessage('#entry_list_form', false);
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
				"allEntriesOnPageAreSelected": <?php echo json_encode( sprintf( esc_html__( 'All %s{0}%s entries on this page are selected.', 'gravityforms' ), '<strong>', '</strong>' ) ); ?>,
				"selectAll"                  : <?php echo json_encode( sprintf( esc_html__( 'Select all %s{0}%s entries.', 'gravityforms' ), '<strong>', '</strong>' ) ); ?>,
				"allEntriesSelected"         : <?php echo json_encode( sprintf( esc_html__( 'All %s{0}%s entries have been selected.', 'gravityforms' ), '<strong>', '</strong>' ) ); ?>,
				"clearSelection"             : <?php echo json_encode( __( 'Clear selection', 'gravityforms' ) ); ?>
			};

			var gformVars = {
				"countAllEntries": <?php echo intval( $this->get_pagination_arg( 'total_items' ) ); ?>,
				"perPage"        : <?php echo intval( $this->get_pagination_arg( 'per_page' ) ); ?>
			};

			function setSelectAllText() {
				var tr = getSelectAllText();
				jQuery("#gform-select-all-message td").html(tr);
			}

			function getSelectAllText() {
				var count;
				count = jQuery("#the-list tr.entry_row:visible:not('#gform-select-all-message')").length;
				return gformStrings.allEntriesOnPageAreSelected.format(count) + " <a href='javascript:void(0)' onclick='selectAllEntriesOnAllPages();'>" + gformStrings.selectAll.format(gformVars.countAllEntries) + "</a>";
			}

			function getSelectAllTr() {
				var t = getSelectAllText();
				var colspan = jQuery("#the-list").find("tr:first td").length + 2;
				return "<tr id='gform-select-all-message' class='no-items' style='display:none;background-color:lightyellow;text-align:center;'><td colspan='{0}'>{1}</td></tr>".format(colspan, t);
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
					jQuery("#the-list").prepend(tr);
					jQuery(".column-cb input").click(function () {
						toggleSelectAll(jQuery(this).prop('checked'));
					});
					jQuery("#the-list .check-column input[type=checkbox]").click(function () {
						clearSelectAllMessage();
					})
				}
			}

			function afterAjaxDelete ( r, settings ) {
				var counts = settings.parsed.responses[0].supplemental;
				jQuery.each( counts, function( id, count ) {
					jQuery('#' + id).text(count);
				});
			}

			String.prototype.format = function () {
				var args = arguments;
				return this.replace(/{(\d+)}/g, function (match, number) {
					return typeof args[number] != 'undefined' ? args[number] : match;
				});
			};

			// end Select All

			jQuery(document).ready(function () {

				var list = jQuery("#the-list").wpList({ delAfter: afterAjaxDelete, alt: <?php echo json_encode( esc_html__( 'Entry List', 'gravityforms' ) ) ?>});
				list.bind('wpListDelEnd', function (e, s, list) {
					var currentStatus = <?php echo json_encode( $filter == 'trash' || $filter == 'spam' ? $filter : 'active' ); ?>;
					var filter = <?php echo json_encode( $filter ); ?>;
					var movingTo = "active";
					if (s.data.status == "trash")
						movingTo = "trash";
					else if (s.data.status == "spam")
						movingTo = "spam";
					else if (s.data.status == "delete")
						movingTo = "delete";

					// Updating Paging counts
					if (currentStatus == "spam" || movingTo == "spam") {
						var spamCount = movingTo == "spam" ? 1 : -1;
						//adjust paging counts
						if (filter == "spam") {
							UpdatePagingCounts(1);
						}
						else {
							UpdatePagingCounts(spamCount);
						}
					}
					if (currentStatus == "trash" || movingTo == "trash") {
						var trashCount = movingTo == "trash" ? 1 : -1;
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
						Search(<?php echo json_encode( $orderby ); ?>, <?php echo json_encode( $order ); ?>, <?php echo absint( $form_id ) ?>, jQuery('.gform-filter-value').val(), <?php echo json_encode( $filter ); ?>, jQuery('.gform-filter-field').val(), jQuery('.gform-filter-operator').val());
						event.preventDefault();
					}
				}));

				jQuery( '#current-page-selector').keyup( function( event ) {
					if (event.keyCode == 13) {
						var url = <?php echo json_encode( esc_url_raw( remove_query_arg( 'paged' ) ) ); ?>;
						var page = parseInt( this.value );
						document.location = url + '&paged=' + page;
						event.preventDefault();
					}
				});

				jQuery('#doaction, #doaction2').click(function(){
					var action = jQuery(this).siblings('select').val();

					if ( action == -1 ) {
						return;
					}

					var defaultModalOptions = '';
					var entryIds = getLeadIds();

					if ( entryIds.length == 0 ) {
						alert(<?php echo json_encode( __( 'Please select at least one entry...', 'gravityforms' ) ); ?>);
						return false;
					}

					switch (action) {

						case 'resend_notifications':
							resetResendNotificationsUI();
							tb_show(<?php echo json_encode( esc_html__( 'Resend Notifications', 'gravityforms' ) ); ?>, '#TB_inline?width=350&amp;inlineId=notifications_modal_container', '');
							return false;
							break;

						case 'print':
							resetPrintUI();
							tb_show(<?php echo json_encode( esc_html__( 'Print Entries', 'gravityforms' ) ); ?>, '#TB_inline?width=350&amp;height=250&amp;inlineId=print_modal_container', '');
							return false;
							break;

					}

				});


			});

		</script>
		<?php

	}

	/**
	 * Output modals.
	 */
	public function modals() {
		$form = $this->get_form();
		?>
		<div id="notifications_modal_container" style="display:none;">
			<div id="notifications_container">

				<div id="post_tag" class="tagsdiv">
					<div id="resend_notifications_options">

						<?php

						$notifications = GFCommon::get_notifications( 'resend_notifications', $form );

						if ( ! is_array( $notifications ) || count( $form['notifications'] ) <= 0 ) {
							?>
							<p class="description"><?php esc_html_e( 'You cannot resend notifications for these entries because this form does not currently have any notifications configured.', 'gravityforms' ); ?></p>

							<a href="<?php echo esc_url( admin_url( "admin.php?page=gf_edit_forms&view=settings&subview=notification&id={$form['id']}" ) ); ?>" class="button"><?php esc_html_e( 'Configure Notifications', 'gravityforms' ) ?></a>
							<?php
						} else {
							?>
							<p class="description"><?php esc_html_e( 'Specify which notifications you would like to resend for the selected entries.', 'gravityforms' ); ?></p>
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

								<p class="description" style="padding-top:0; margin-top:0;">
									<?php esc_html_e( 'You may override the default notification settings by entering a comma delimited list of emails to which the selected notifications should be sent.', 'gravityforms' ); ?>
								</p>
								<label for="notification_override_email"><?php esc_html_e( 'Send To', 'gravityforms' ); ?> <?php gform_tooltip( 'notification_override_email' ) ?></label><br />
								<input type="text" name="notification_override_email" id="notification_override_email" style="width:99%;" /><br /><br />

							</div>

							<input type="button" name="notification_resend" id="notification_resend" value="<?php esc_attr_e( 'Resend Notifications', 'gravityforms' ) ?>" class="button" style="" onclick="BulkResendNotifications();" />
							<span id="please_wait_container" style="display:none; margin-left: 5px;">
                                                <i class='gficon-gravityforms-spinner-icon gficon-spin'></i> <?php esc_html_e( 'Resending...', 'gravityforms' ); ?>
                                            </span>
							<?php
						}
						?>

					</div>

					<div id="resend_notifications_close" style="display:none;margin:10px 0 0;">
						<input type="button" name="resend_notifications_close_button" value="<?php esc_attr_e( 'Close Window', 'gravityforms' ) ?>" class="button" style="" onclick="closeModal(true);" />
					</div>

				</div>

			</div>
		</div>
		<!-- / Resend Notifications -->

		<div id="print_modal_container" style="display:none;">
			<div id="print_container">

				<div class="tagsdiv">
					<div id="print_options">

						<p class="description"><?php esc_html_e( 'Print all of the selected entries at once.', 'gravityforms' ); ?></p>

						<?php if ( GFCommon::current_user_can_any( 'gravityforms_view_entry_notes' ) ) { ?>
							<input type="checkbox" name="gform_print_notes" value="print_notes" checked="checked" id="gform_print_notes" />
							<label for="gform_print_notes"><?php esc_html_e( 'Include notes', 'gravityforms' ); ?></label>
							<br /><br />
						<?php } ?>

						<input type="checkbox" name="gform_print_page_break" value="print_notes" checked="checked" id="gform_print_page_break" />
						<label for="gform_print_page_break"><?php esc_html_e( 'Add page break between entries', 'gravityforms' ); ?></label>
						<br /><br />

						<input type="button" value="<?php esc_attr_e( 'Print', 'gravityforms' ); ?>" class="button" onclick="BulkPrint();" />

					</div>
				</div>

			</div>
		</div>
		<!-- / Print -->
		<?php
	}
}
