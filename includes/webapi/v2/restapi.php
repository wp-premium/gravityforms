<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Loads the Gravity Forms REST API add-on.
 *
 * Includes the main class, registers it with GFAddOn, and initialises.
 *
 * @since 2.4-beta-1
 */
class GF_REST_API_Bootstrap {

	/**
	 * Loads the required files.
	 *
	 * @since 2.4-beta-1
	 *
	 */
	public static function load_rest_api() {


		$dir = plugin_dir_path( __FILE__ );

		// Requires the class file
		require_once( $dir . '/class-gf-rest-api.php' );

		require_once( $dir . '/includes/class-results-cache.php' );


		if ( ! class_exists( 'WP_REST_Controller' ) ) {
			require_once( $dir . '/includes/controllers/class-wp-rest-controller.php' );
		}

		require_once( $dir . '/includes/controllers/class-gf-rest-controller.php' );

		require_once( $dir . '/includes/controllers/class-controller-form-entries.php' );
		require_once( $dir . '/includes/controllers/class-controller-form-results.php' );
		require_once( $dir . '/includes/controllers/class-controller-form-submissions.php' );
		require_once( $dir . '/includes/controllers/class-controller-form-feeds.php' );
		require_once( $dir . '/includes/controllers/class-controller-feeds.php' );
		require_once( $dir . '/includes/controllers/class-controller-entries.php' );
		require_once( $dir . '/includes/controllers/class-controller-entry-notes.php' );
		require_once( $dir . '/includes/controllers/class-controller-notes.php' );
		require_once( $dir . '/includes/controllers/class-controller-entry-notifications.php' );
		require_once( $dir . '/includes/controllers/class-controller-entry-properties.php' );
		require_once( $dir . '/includes/controllers/class-controller-forms.php' );

		return GF_REST_API::get_instance();
	}
}

GF_REST_API_Bootstrap::load_rest_api();
