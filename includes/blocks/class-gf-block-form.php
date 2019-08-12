<?php

// If Gravity Forms Block Manager is not available, do not run.
if ( ! class_exists( 'GF_Blocks' ) || ! defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Block_Form extends GF_Block {

	/**
	 * Contains an instance of this block, if available.
	 *
	 * @since  2.4.10
	 * @var    GF_Block $_instance If available, contains an instance of this block.
	 */
	private static $_instance = null;

	/**
	 * Block type.
	 *
	 * @since 2.4.10
	 * @var   string
	 */
	public $type = 'gravityforms/form';

	/**
	 * Handle of primary block script.
	 *
	 * @since 2.4.10
	 * @var   string
	 */
	public $script_handle = 'gform_editor_block_form';

	/**
	 * Block attributes.
	 *
	 * @since 2.4.10
	 * @var   array
	 */
	public $attributes = array(
		'formId'      => array( 'type' => 'integer' ),
		'title'       => array( 'type' => 'boolean' ),
		'description' => array( 'type' => 'boolean' ),
		'ajax'        => array( 'type' => 'boolean' ),
		'tabindex'    => array( 'type' => 'string' ),
		'formPreview' => array( 'type' => 'boolean' ),
	);

	/**
	 * Get instance of this class.
	 *
	 * @since  2.4.10
	 *
	 * @return GF_Block_Form
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;

	}

	/**
	 * Register needed hooks.
	 *
	 * @since 2.4.10
	 */
	public function init() {

		parent::init();

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_form_scripts' ) );

	}





	// # SCRIPT / STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Register scripts for block.
	 *
	 * @since  2.4.10
	 *
	 * @return array
	 */
	public function scripts() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		return array(
			array(
				'handle'   => $this->script_handle,
				'src'      => GFCommon::get_base_url() . "/js/blocks{$min}.js",
				'deps'     => array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-editor' ),
				'version'  => $min ? GFForms::$version : filemtime( GFCommon::get_base_path() . '/js/blocks.js' ),
				'callback' => array( $this, 'localize_script' ),
			),
		);

	}

	/**
	 * Localize Form block script.
	 *
	 * @since  2.4.10
	 *
	 * @param array $script Script arguments.
	 */
	public function localize_script( $script = array() ) {

		wp_localize_script(
			$script['handle'],
			'gform_block_form',
			array(
				'forms' => $this->get_forms(),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $script['handle'], 'gravityforms', GFCommon::get_base_path() . '/languages' );
		}

	}

	/**
	 * Register styles for block.
	 *
	 * @since  2.4.10
	 *
	 * @return array
	 */
	public function styles() {

		// Prepare styling dependencies.
		$deps = array( 'wp-edit-blocks' );

		// Add Gravity Forms styling if CSS is enabled.
		if ( '1' !== get_option( 'rg_gforms_disable_css', false ) ) {
			$deps = array_merge( $deps, array( 'gforms_formsmain_css', 'gforms_ready_class_css', 'gforms_browsers_css' ) );
		}

		return array(
			array(
				'handle'  => 'gform_editor_block_form',
				'src'     => GFCommon::get_base_url() . '/css/blocks.min.css',
				'deps'    => $deps,
				'version' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? filemtime( GFCommon::get_base_path() . '/css/blocks.min.css' ) : GFForms::$version,
			),
		);

	}

	/**
	 * Parse current post's blocks for Gravity Forms block and enqueue required form scripts.
	 *
	 * @since  2.4.10
	 */
	public function maybe_enqueue_form_scripts() {

		global $wp_query;

		if ( ! isset( $wp_query->posts ) || ! is_array( $wp_query->posts ) ) {
			return;
		}

		foreach ( $wp_query->posts as $post ) {

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( ! has_block( $this->type, $post->post_content ) ) {
				continue;
			}

			$blocks = parse_blocks( $post->post_content );

			// Get form IDs for blocks.
			$form_ids = $this->get_block_form_ids( $blocks );

			// If no form IDs were found, skip.
			if ( empty( $form_ids ) ) {
				continue;
			}

			// Load GFFormDisplay.
			if ( ! class_exists( 'GFFormDisplay' ) ) {
				require_once( GFCommon::get_base_path() . '/form_display.php' );
			}

			// Enqueue scripts for found forms.
			foreach ( $form_ids as $form_id => $ajax ) {
				$form = GFAPI::get_form( $form_id );
				GFFormDisplay::enqueue_form_scripts( $form, $ajax );
			}

		}

	}

	/**
	 * Check current blocks and inner blocks for Gravity Forms block and return their form IDs.
	 *
	 * @since 2.4.11
	 *
	 * @param array $blocks Array of blocks.
	 *
	 * @return array
	 */
	private function get_block_form_ids( $blocks ) {

		$form_ids = array();

		foreach ( $blocks as $block ) {

			// If block has inner blocks, add to form IDs array.
			if ( rgar( $block, 'innerBlocks' ) ) {

				// Get nested form IDs.
				$nested_form_ids = $this->get_block_form_ids( $block['innerBlocks'] );

				// Merge arrays.
				if ( ! empty( $nested_form_ids ) ) {
					$form_ids = array_replace( $form_ids, $nested_form_ids );
				}

			}

			// If this is not a Form block or the form ID is not defined, skip.
			if ( $this->type !== rgar( $block, 'blockName' ) || ( $this->type === rgar( $block, 'blockName' ) && ! rgars( $block, 'attrs/formId' ) ) ) {
				continue;
			}

			// Get the form ID and AJAX attributes.
			$form_id = (int) $block['attrs']['formId'];
			$ajax    = rgars( $block, 'attrs/ajax' ) ? (bool) $block['attrs']['ajax'] : false;

			// Add form ID to return array.
			if ( ! in_array( $form_id, $form_ids ) || ( in_array( $form_id, $form_ids ) && true === $ajax && false === $form_ids[ $form_id ] ) ) {
				$form_ids[ $form_id ] = $ajax;
			}

		}

		return $form_ids;

	}




	// # BLOCK RENDER -------------------------------------------------------------------------------------------------

	/**
	 * Display block contents on frontend.
	 *
	 * @since  2.4.10
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public function render_block( $attributes = array() ) {

		// Prepare variables.
		$form_id     = rgar( $attributes, 'formId' ) ? $attributes['formId'] : false;
		$title       = isset( $attributes['title'] ) ? $attributes['title'] : true;
		$description = isset( $attributes['description'] ) ? $attributes['description'] : true;
		$ajax        = isset( $attributes['ajax'] ) ? $attributes['ajax'] : false;
		$tabindex    = isset( $attributes['tabindex'] ) ? intval( $attributes['tabindex'] ) : 0;

		// If form ID was not provided or form does not exist, return.
		if ( ! $form_id || ( $form_id && ! GFAPI::get_form( $form_id ) ) ) {
			return '';
		}

		// Use Gravity Forms function for REST API requests.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {

			// Start output buffering.
			ob_start();

			// Get form output string.
			$form_string = gravity_form( $form_id, $title, $description, false, null, $ajax, $tabindex, false );

			// Get output buffer contents.
			$buffer_contents = ob_get_contents();
			ob_end_clean();

			// Return buffer contents with form string.
			return $buffer_contents . $form_string;

		}

		return sprintf( '[gravityforms id="%d" title="%s" description="%s" ajax="%s" tabindex="%d"]', $form_id, ( $title ? 'true' : 'false' ), ( $description ? 'true' : 'false' ), ( $ajax ? 'true' : 'false' ), $tabindex );

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Get list of forms for Block control.
	 *
	 * @since 2.4.10
	 *
	 * @return array
	 */
	public function get_forms() {

		// Initialize forms array.
		$forms = array();

		// Load GFFormDisplay class.
		if ( ! class_exists( 'GFFormDisplay' ) ) {
			require_once GFCommon::get_base_path() . '/form_display.php';
		}

		// Get form objects.
		$form_objects = GFAPI::get_forms();

		// Loop through forms, add conditional logic check.
		foreach ( $form_objects as $form ) {
			$forms[] = array(
				'id'                  => $form['id'],
				'title'               => $form['title'],
				'hasConditionalLogic' => GFFormDisplay::has_conditional_logic( $form ),
			);
		}

		return $forms;

	}

}

// Register block.
if ( true !== ( $registered = GF_Blocks::register( GF_Block_Form::get_instance() ) ) && is_wp_error( $registered ) ) {

	// Log that block could not be registered.
	GFCommon::log_error( 'Unable to register block; ' . $registered->get_error_message() );

}
