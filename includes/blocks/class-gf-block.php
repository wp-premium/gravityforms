<?php

// If Gravity Forms Block Manager is not available, do not run.
if ( ! class_exists( 'GF_Blocks' ) || ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base Gravity Forms Block class.
 *
 * @since 2.4.10
 *
 * Class GF_Block
 */
class GF_Block {

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
	public $type = '';

	/**
	 * Handle of primary block script.
	 *
	 * @since 2.4.10
	 * @var   string
	 */
	public $script_handle = '';

	/**
	 * Block attributes.
	 *
	 * @since 2.4.10
	 * @var   array
	 */
	public $attributes = array();

	/**
	 * Register block type.
	 * Enqueue editor assets.
	 *
	 * @since  2.4.10
	 *
	 * @uses   GF_Block::register_block_type()
	 */
	public function init() {

		$this->register_block_type();

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_styles' ) );

	}





	// # BLOCK REGISTRATION --------------------------------------------------------------------------------------------

	/**
	 * Get block type.
	 *
	 * @since  2.4.10
	 *
	 * @return string
	 */
	public function get_type() {

		return $this->type;

	}

	/**
	 * Register block with WordPress.
	 *
	 * @since  2.4.10
	 */
	public function register_block_type() {

		register_block_type( $this->get_type(), array(
			'render_callback' => array( $this, 'render_block' ),
			'editor_script'   => $this->script_handle,
			'attributes'      => $this->attributes,
		) );

	}





	// # SCRIPT ENQUEUEING ---------------------------------------------------------------------------------------------

	/**
	 * Enqueue block scripts.
	 *
	 * @since  2.4.10
	 *
	 * @uses   GF_Block::scripts()
	 */
	public function enqueue_scripts() {

		// Get registered scripts.
		$scripts = $this->scripts();

		// If no scripts are registered, return.
		if ( empty( $scripts ) ) {
			return;
		}

		// Loop through scripts.
		foreach ( $scripts as $script ) {

			// Prepare parameters.
			$src       = isset( $script['src'] ) ? $script['src'] : false;
			$deps      = isset( $script['deps'] ) ? $script['deps'] : array();
			$version   = isset( $script['version'] ) ? $script['version'] : false;
			$in_footer = isset( $script['in_footer'] ) ? $script['in_footer'] : false;

			// Enqueue script.
			wp_enqueue_script( $script['handle'], $src, $deps, $version, $in_footer );

			// Localize script.
			if ( rgar( $script, 'strings' ) ) {
				wp_localize_script( $script['handle'], $script['handle'] . '_strings', $script['strings'] );
			}

			// Run script callback.
			if ( rgar( $script, 'callback' ) && is_callable( $script['callback'] ) ) {
				call_user_func( $script['callback'], $script );
			}

		}

	}

	/**
	 * Override this function to provide a list of scripts to be enqueued.
	 * Following is an example of the array that is expected to be returned by this function:
	 * <pre>
	 * <code>
	 *
	 *    array(
	 *        array(
	 *            'handle'   => 'super_signature_script',
	 *            'src'      => $this->get_base_url() . '/super_signature/ss.js',
	 *            'version'  => $this->_version,
	 *            'deps'     => array( 'jquery'),
	 *            'callback' => array( $this, 'localize_scripts' ),
	 *            'strings'  => array(
	 *                // Accessible in JavaScript using the global variable "[script handle]_strings"
	 *                'stringKey1' => __( 'The string', 'gravityforms' ),
	 *                'stringKey2' => __( 'Another string.', 'gravityforms' )
	 *            )
	 *        )
	 *    );
	 *
	 * </code>
	 * </pre>
	 *
	 * @since  2.4.10
	 *
	 * @return array
	 */
	public function scripts() {

		return array();

	}





	// # STYLE ENQUEUEING ----------------------------------------------------------------------------------------------

	/**
	 * Enqueue block styles.
	 *
	 * @since  2.4.10
	 */
	public function enqueue_styles() {

		// Get registered styles.
		$styles = $this->styles();

		// If no styles are registered, return.
		if ( empty( $styles ) ) {
			return;
		}

		// Loop through styles.
		foreach ( $styles as $style ) {

			// Prepare parameters.
			$src     = isset( $style['src'] ) ? $style['src'] : false;
			$deps    = isset( $style['deps'] ) ? $style['deps'] : array();
			$version = isset( $style['version'] ) ? $style['version'] : false;
			$media   = isset( $style['media'] ) ? $style['media'] : 'all';

			// Enqueue style.
			wp_enqueue_style( $style['handle'], $src, $deps, $version, $media );

		}

	}

	/**
	 * Override this function to provide a list of styles to be enqueued.
	 * See scripts() for an example of the format expected to be returned.
	 *
	 * @since  2.4.10
	 *
	 * @return array
	 */
	public function styles() {

		return array();

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

		return '';

	}

}
