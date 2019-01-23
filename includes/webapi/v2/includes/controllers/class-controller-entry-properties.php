<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_REST_Entry_Properties_Controller extends GF_REST_Form_Entries_Controller {

	/**
	 * @since 2.4-beta-1
	 *
	 *
	 * @var string
	 */
	public $rest_base = 'entries/(?P<entry_id>[\d]+)/properties';

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 2.4-beta-1
	 *
	 */
	public function register_routes() {

		$namespace = $this->namespace;

		$base = $this->rest_base;

		register_rest_route( $namespace, '/' . $base, array(
			array(
				'methods'         => 'PUT',
				'callback'        => array( $this, 'update_items' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( true ),
			),
		) );
	}

	/**
	 * Update one item from the collection
	 *
	 * @since 2.4-beta-1
	 *
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_items( $request ) {
		$entry_id = $request['entry_id'];
		$key_value_pairs = $this->prepare_item_for_database( $request );

		if ( empty( $key_value_pairs ) ) {
			$message = __( 'No property values were found in the request body', 'gravityforms' );
			return new WP_REST_Response( $message, 400 );
		} elseif ( ! is_array( $key_value_pairs ) ) {
			$message = __( 'Property values should be sent as an array', 'gravityforms' );
			return new WP_REST_Response( $message, 400 );
		}

		$result = false;
		foreach ( $key_value_pairs as $key => $property_value ) {
			$result = GFAPI::update_entry_property( $entry_id, $key, $property_value );
			if ( is_wp_error( $result ) ) {
				break;
			}
		}

		if ( is_wp_error( $result ) ) {
			$status = $this->get_error_status( $result );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
		}

		$message = __( 'Entry updated successfully', 'gravityforms' );

		return new WP_REST_Response( $message, 200 );
	}

	/**
	 * Check if a given request has access to update a specific item
	 *
	 * @since 2.4
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		/**
		 * Filters the capability required to update entries via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string          $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_put_entries', 'gravityforms_edit_entries', $request );
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
		$properties = $request->get_json_params();
		if ( empty( $properties ) ) {
			return new WP_Error( 'missing_properties', __( 'Missing Key Value Pairs JSON', 'gravityforms' ) );
		}

		return $properties;
	}

	/**
	 * Get the query params for collections
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array();
	}

	/**
	 * Get the Entry Property schema, conforming to JSON Schema.
	 *
	 * @since 2.4-beta-1
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array();
	}
}
