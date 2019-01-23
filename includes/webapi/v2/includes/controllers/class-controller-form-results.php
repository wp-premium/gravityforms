<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_REST_Form_Results_Controller extends GF_REST_Controller {

	/**
	 * @since 2.4-beta-1
	 *
	 * @var string
	 */
	public $rest_base = 'forms/(?P<form_id>[\d]+)/results';

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 2.4-beta-1
	 */
	public function register_routes() {

		$namespace = $this->namespace;

		$base = $this->rest_base;

		register_rest_route( $namespace, '/' . $base, array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'            => $this->get_collection_params(),
			),
		) );

		register_rest_route( $namespace, '/' . $base . '/schema', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get a collection of results.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$form_id = $request['form_id'];
		$search_params = $this->parse_entry_search_params( $request );
		$search_criteria = rgar( $search_params, 'search_criteria' );
		$args = array(
			'page_size' => 100,
			'time_limit' => 5,
			'wait' => 5,
		);
		$data = gf_results_cache()->get_results( $form_id, $search_criteria, $args );
		$response = $this->prepare_item_for_response( $data, $request );
		return $response;
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		/**
		 * Filters the capability required to get form results via the web API.
		 *
		 * @since 2.0-beta-2
		 *
		 * @param string          $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_get_results', 'gravityforms_view_entries', $request );
		return GFAPI::current_user_can_any( $capability );
	}


	/**
	 * Prepare the item for the REST response
	 *
	 * @since 2.4-beta-1
	 *
	 * @param mixed           $item    WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {

		$response = new WP_REST_Response( $item, 200 );
		return $response;
	}

	/**
	 * Get the query params for collections
	 *
	 * @since 2.4-beta-1
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'search'                 => array(
				'description'        => 'The search criteria.',
				'type'               => 'string',
				'sanitize_callback'  => 'sanitize_text_field',
			),
		);
	}
}

