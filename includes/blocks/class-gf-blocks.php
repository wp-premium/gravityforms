<?php

defined( 'ABSPATH' ) or die();

// Load core Block class.
require_once( plugin_dir_path( __FILE__ ) . 'class-gf-block.php' );

/**
 * Handles management of Gravity Forms editor blocks.
 *
 * @since 2.4.10
 *
 * Class GF_Blocks
 */
class GF_Blocks {

	/**
	 * Registered Gravity Forms editor blocks.
	 *
	 * @since 2.4.10
	 * @var   GF_Block[]
	 */
	private static $_blocks = array();

	/**
	 * Register a block type.
	 *
	 * @since  2.4.10
	 *
	 * @param GF_Block $block Block class.
	 *
	 * @return bool|WP_Error
	 */
	public static function register( $block ) {

		if ( ! is_subclass_of( $block, 'GF_Block' ) ) {
			return new WP_Error( 'block_not_subclass', 'Must be a subclass of GF_Block' );
		}

		// Get block type.
		$block_type = $block->get_type();

		if ( empty( $block_type ) ) {
			return new WP_Error( 'block_type_undefined', 'The type must be set' );
		}

		if ( isset( self::$_blocks[ $block_type ] ) ) {
			return new WP_Error( 'block_already_registered', 'Block type already registered: ' . $block_type );
		}

		// Register block.
		self::$_blocks[ $block_type ] = $block;

		// Initialize block.
		call_user_func( array( $block, 'init' ) );

		return true;

	}

	/**
	 * Get instance of block.
	 *
	 * @since  2.4.10
	 *
	 * @param string $block_type Block type.
	 *
	 * @return GF_Block|bool
	 */
	public static function get( $block_type ) {

		return isset( self::$_blocks[ $block_type ] ) ? self::$_blocks[ $block_type ] : false;

	}

}

new GF_Blocks();
