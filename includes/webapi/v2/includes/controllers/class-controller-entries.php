<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_REST_Entries_Controller extends GF_REST_Form_Entries_Controller {

	/**
	 * @since 2.4-beta-1
	 *
	 * @var string
	 */
	public $rest_base = 'entries';

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

		register_rest_route( $namespace, '/' . $base . '/(?P<entry_id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(),
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( false ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(),
			),
		) );

	}

	/**
	 * Get a collection of entries
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		return parent::get_items( $request );
	}

	/**
	 * Get one item from the collection
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {

		$entry_id = $request->get_param( 'entry_id' );
		$entry    = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) ) {
			return new WP_Error( 'gf_entry_invalid_id', __( 'Invalid entry id.', 'gravityforms' ), array( 'status' => 404 ) );
		}

		// Get form id here, it could be removed when _field_ids are specified.
		$form_id = $entry['form_id'];

		$field_ids = $request['_field_ids'];
		if ( ! empty( $field_ids ) ) {
			$field_ids = (array) explode( ',', $request['_field_ids'] );
			$field_ids = array_map( 'trim', $field_ids );
			if ( ! empty( $field_ids ) ) {
				$entry = $this->filter_entry_fields( $entry, $field_ids );
			}
		}

		$labels = $request['_labels'];

		if ( $labels ) {

			$form = GFAPI::get_form( $form_id );

			$entry['_labels'] = $this->get_entry_labels( $form, compact( 'field_ids' ) );
		}

		$data = $this->prepare_item_for_response( $entry, $request );

		return $data;
	}

	/**
	 * Create one item from the collection
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request
	 */
	public function create_item( $request ) {
		return parent::create_item( $request );
	}

	/**
	 * Update one item from the collection
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$entry = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		$result = GFAPI::update_entry( $entry );

		if ( is_wp_error( $result ) ) {
			$status = $this->get_error_status( $result );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
		}


		$updated_entry = GFAPI::get_entry( $entry['id'] );

		$response = $this->prepare_item_for_response( $updated_entry, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Delete one item from the collection
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$entry_id = $request['entry_id'];

		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			return new WP_Error( 'gf_entry_invalid_id', __( 'Invalid entry id.', 'gravityforms' ), array( 'status' => 404 ) );
		}

		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		if ( $force ) {
			$result = GFAPI::delete_entry( $entry_id );

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
				return new WP_Error( 'gf_cannot_delete', $message, array( 'status' => 500 ) );
			}

			$previous = $this->prepare_item_for_response( $entry, $request );
			$response = new WP_REST_Response();
			$response->set_data( array( 'deleted' => true, 'previous' => $previous->get_data() ) );
		} else {
			if ( rgar( $entry, 'status' ) == 'trash' ) {
				$message = __( 'The entry has already been deleted.', 'gravityforms' );
				return new WP_Error( 'gf_already_trashed', $message, array( 'status' => 410 ) );
			}

			// Trash the entry
			GFAPI::update_entry_property( $entry_id, 'status', 'trash' );

			$entry = GFAPI::get_entry( $entry_id );
			$response = rest_ensure_response( $entry );
		}

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
		 * Filters the capability required to get entries via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string          $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_get_entries', 'gravityforms_view_entries', $request );

		return GFAPI::current_user_can_any( $capability );
	}

	/**
	 * Check if a given request has access to get a specific item
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {

		/**
		 * Filters the capability required to create entries via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string          $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_post_entries', 'gravityforms_edit_entries', $request );

		return GFAPI::current_user_can_any( $capability );
	}

	/**
	 * Check if a given request has access to update a specific item
	 *
	 * @since 2.4-beta-1
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
	 * Check if a given request has access to delete a specific item
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {

		/**
		 * Filters the capability required to delete entries via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string          $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_delete_entries', 'gravityforms_delete_entries', $request );

		return GFAPI::current_user_can_any( $capability );
	}

	/**
	 * Prepare the item for create or update operation
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Request object
	 *
	 * @return WP_Error|array $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {

		$entry = $request->get_json_params();

		if ( empty( $entry ) ) {
			return new WP_Error( 'missing_entry', __( 'Missing entry JSON', 'gravityforms' ) );
		}

		$entry_id = $request['entry_id'];

		if ( ! empty( $entry_id ) ) {
			$entry['id'] = $entry_id;
		}

		$entry = $this->maybe_json_encode_applicable_fields( $entry );
		$entry = $this->maybe_serialize_list_fields( $entry );

		return $entry;
	}

	/**
	 * Prepare the item for the REST response
	 *
	 * @since 2.4-beta-1
	 *
	 * @param mixed           $item    WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Returns the item wrapped in a WP_REST_Response object
	 */
	public function prepare_item_for_response( $item, $request ) {

		$item = $this->prepare_entry_for_response( $item );

		$response = new WP_REST_Response( $item, 200 );
		return $response;
	}

}
