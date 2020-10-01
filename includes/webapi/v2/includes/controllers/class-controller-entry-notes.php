<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_REST_Entry_Notes_Controller extends GF_REST_Controller {

	/**
	 * @var string Base for the REST request.
	 */
	public $rest_base = 'entries/(?P<entry_id>[\d]+)/notes';

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$namespace = $this->namespace;

		$base = $this->rest_base;

		register_rest_route(
			$namespace,
			'/' . $base,
			array(
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
			)
		);
	}

	/**
	 * Get all notes for one entry.
	 *
	 * @since 2.4.18
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$entry_id = $request->get_param( 'entry_id' );

		if ( ! GFAPI::entry_exists( $entry_id ) ) {
			return new WP_Error( 'gf_entry_invalid_id', __( 'Invalid entry id.', 'gravityforms' ), array( 'status' => 404 ) );
		}

		$criteria = $request->get_params();

		$allowed_criteria = array(
			'user_id',
			'note_type',
			'sub_type',
			'user_name'
		);

		foreach ( $criteria as $key => $value ) {
			if ( in_array( $key, $allowed_criteria ) ) {
				$criteria[$key] = $value;
			}
		}

		$criteria['entry_id'] = $entry_id;

		$sorting = '';
		if ( isset( $criteria['sorting'] ) ) {
			$sorting = $criteria['sorting'];
			unset( $criteria['sorting'] );
		}

		$notes = GFAPI::get_notes( $criteria, $sorting );

		if ( is_wp_error( $notes ) ) {
			return new WP_Error( 'gf_entry_invalid_notes', __( 'Error retrieving notes.', 'gravityforms' ), array( 'status' => 404 ) );
		}

		if ( ! is_array( $notes ) || empty( $notes ) ) {
			return array();
		}

		$data = array();

		foreach ( $notes as $note ) {
			$data[ $note->id ] = $note;
		}

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Create one note.
	 *
	 * @since 2.4.18
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request
	 */
	public function create_item( $request ) {

		$note     = $this->prepare_item_for_database( $request );
		$entry_id = $request->get_param( 'entry_id' );

		if ( is_wp_error( $note ) ) {
			return $note;
		}

		$note_id = GFAPI::add_note( $entry_id, $note['user_id'], $note['user_name'], $note['note'] );

		if ( is_wp_error( $note_id ) ) {
			$status = $this->get_error_status( $note_id );
			return new WP_Error( $note_id->get_error_code(), $note_id->get_error_message(), array( 'status' => $status ) );
		}

		$note['id'] = $note_id;

		$note     = $this->prepare_note_for_response( $note_id );
		$response = rest_ensure_response( $note );
		$response->set_status( 201 );
		$base = sprintf( 'entries/%d/notes/', $note_id );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $base, $note_id ) ) );

		return $response;
	}

	/**
	 * Check if a given request has access to get items.
	 *
	 * @since 2.4.18
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {

		/**
		 * Filters the capability required to get entries via the REST API.
		 *
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_get_notes', 'gravityforms_view_entry_notes', $request );

		return $this->current_user_can_any( $capability, $request );
	}

	/**
	 * Check if a given request has access to get a specific item.
	 *
	 * @since 2.4.18
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to create items.
	 *
	 * @since 2.4.18
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {

		/**
		 * Filters the capability required to create entries via the REST API.
		 *
		 * @since 2.4.18
		 *
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_post_notes', 'gravityforms_edit_entry_notes', $request );

		return $this->current_user_can_any( $capability, $request );
	}

	/**
	 * Prepare the item for create or update operation.
	 *
	 * @since 2.4.18
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|array $prepared_item.
	 */
	protected function prepare_item_for_database( $request ) {

		$note = $request->get_json_params();

		if ( empty( $note ) ) {
			return new WP_Error( 'missing_entry', __( 'Missing entry JSON', 'gravityforms' ) );
		}

		$note['user_id'] = intval( $note['user_id'] );
		$note['note']    = wp_kses_post( $note['value'] );

		return $note;
	}

	/**
	 * Prepare the item for the REST response.
	 *
	 * @since 2.4.18
	 *
	 * @param mixed           $item    WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Returns the item wrapped in a WP_REST_Response object
	 */
	public function prepare_item_for_response( $item, $request ) {

		$item = $this->prepare_note_for_response( $item->id );

		$response = new WP_REST_Response( $item, 200 );
		return $response;
	}

	/***
	 * Prepares note for REST API response, decoding or unserializing appropriate fields.
	 *
	 * @since 2.4.18
	 *
	 * @param int $note_id The note id.
	 *
	 * @return bool|array Returns the entry array ready to be send in the REST API response.
	 */
	public function prepare_note_for_response( $note_id ) {

		$note = GFAPI::get_note( $note_id );

		if ( is_wp_error( $note ) || ! isset( $note->ID ) ) {
			return $note;
		}

		return $note;
	}

	/**
	 * Check if a given request has access to update a specific item.
	 *
	 * @since 2.4.18
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
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_put_notes', 'gravityforms_edit_entries', $request );

		return $this->current_user_can_any( $capability, $request );
	}

	/**
	 * Check if a given request has access to delete a specific item.
	 *
	 * @since 2.4.18
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		/**
		 * Filters the capability required to delete entries via the REST API.
		 *
		 * @since 2.4.18
		 *
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_delete_notes', 'gravityforms_edit_entry_notes', $request );

		return $this->current_user_can_any( $capability, $request );
	}

}
