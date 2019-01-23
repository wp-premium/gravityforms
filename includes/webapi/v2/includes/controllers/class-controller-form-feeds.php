<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_REST_Form_Feeds_Controller extends GF_REST_Controller {

	/**
	 * @since 2.4
	 *
	 * @var string
	 */
	public $rest_base = 'forms/(?P<form_id>[\d]+)/feeds';

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 2.4
	 */
	public function register_routes() {

		$namespace = $this->namespace;

		$base = $this->rest_base;

		register_rest_route( $namespace, '/' . $base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
		) );
	}

	/**
	 * Get a collection of feeds for the form.
	 *
	 * @since 2.4
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$form_id = $request['form_id'];

		$addon_slug = $request['addon'];

		$feed_ids = $request['include'];

		if ( ! empty( $feed_ids ) ) {
			if ( ! is_array( $feed_ids ) ) {
				$feed_ids = array( $feed_ids );
			}
			$feed_ids = array_map( 'absint', $feed_ids );
		}

		$feeds = GFAPI::get_feeds( $feed_ids, $form_id, $addon_slug );

		if ( is_wp_error( $feeds ) ) {
			return $feeds;
		}

		return new WP_REST_Response( $feeds, 200 );
	}

	/**
	 * Create one feed for the form.
	 *
	 * @since 2.4
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {

		$feed = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		$form_id = $feed['form_id'];

		$feed_id = GFAPI::add_feed( $form_id, $feed['meta'], $feed['addon_slug'] );
		if ( is_wp_error( $feed_id ) ) {
			return $feed_id;
		}

		$feed['id'] = $feed_id;

		$response = $this->prepare_item_for_response( $feed, $request );

		$response->set_status( 201 );

		$base = sprintf( 'forms/%d/feeds', $form_id );

		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $base, $feed_id ) ) );

		return $response;
	}

	/**
	 * Prepare the item for the REST response.
	 *
	 * @since 2.4
	 *
	 *
	 * @param mixed $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response $response
	 */
	public function prepare_item_for_response( $item, $request ) {
			return rest_ensure_response( $item );
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @since 2.4
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {

		/**
		 * Filters the capability required to get feeds via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string          $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_get_feeds', 'gravityforms_edit_forms', $request );

		return GFAPI::current_user_can_any( $capability );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @since 2.4
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {

		/**
		 * Filters the capability required to create feeds via the REST API.
		 *
		 * @since 2.0-beta-2
		 *
		 * @param string          $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_post_feeds', 'gravityforms_edit_forms', $request );

		return GFAPI::current_user_can_any( $capability );
	}

	/**
	 * Prepare the item for create or update operation
	 *
	 * @since 2.4
	 *
	 * @param WP_REST_Request $request Request object
	 *
	 * @return WP_Error|array $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {

		$feed = $request->get_json_params();

		if ( empty( $feed ) ) {
			return new WP_Error( 'missing_feed', __( 'Missing feed JSON', 'gravityforms' ) );
		}

		$url_params = $request->get_url_params();

		// Check the URL params first
		$form_id = rgar( $url_params, 'form_id' );

		if ( empty( $form_id ) ) {
			$form_id = rgar( $feed, 'form_id' );
		}

		if ( $form_id ) {
			$feed['form_id'] = absint( $form_id );
		} else {
			return new WP_Error( 'missing_form_id', __( 'Missing form id', 'gravityforms' ) );
		}

		$addon_slug = isset( $feed['addon_slug'] ) ? $feed['addon_slug'] : $request['addon'];
		if ( empty( $addon_slug ) ) {
			return new WP_Error( 'missing_addon_slug', __( 'Missing add-on slug', 'gravityforms' ) );
		}


		if ( empty( $feed['meta'] ) ) {
			return new WP_Error( 'missing_feed_meta', __( 'Missing feed meta', 'gravityforms' ) );
		}

		return $feed;
	}

	/**
	 * Get the query params for collections
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'include' => array(
				'description'        => __( 'Limit result set to specific IDs.' ),
				'type'               => 'array',
				'items'              => array(
					'type'           => 'integer',
				),
				'default'            => array(),
			),
		);
	}

	/**
	 * Get the Feed schema, conforming to JSON Schema.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'feed',
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'description' => __( 'Unique identifier for the feed.', 'gravityforms' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'form_id' => array(
					'description' => __( 'The Form ID for the feed.', 'gravityforms' ),
					'type'        => 'integer',
					'required'    => true,
					'readonly'    => true,
				),
				'meta' => array(
					'description' => __( 'The JSON string containing the feed meta.', 'gravityforms' ),
					'type'        => 'object',
					'readonly'    => false,
				),
				'addon_slug' => array(
					'description' => __( 'The add-on the feed belongs to.', 'gravityforms' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
			),
		);
		return $schema;
	}
}
