<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_REST_Forms_Controller extends GF_REST_Controller {

	/**
	 * @since 2.4-beta-1
	 *
	 * @var string
	 */
	public $rest_base = 'forms';

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
				'args'            => array(),
			),
			array(
				'methods'         => WP_REST_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( true ),
			),
		) );
		register_rest_route( $namespace, '/' . $base . '/(?P<id>[\d]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => array(
						'default'      => 'view',
					),
				),
			),
			array(
				'methods'         => 'PUT',
				'callback'        => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( false ),
			),
			array(
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'     => array(
					'force'    => array(
						'default'      => false,
					),
				),
			),
		) );

		register_rest_route( $namespace, '/' . $base . '/schema', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get a collection of items.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$form_ids = $request['include'];

		if ( ! empty( $form_ids ) ) {
			if ( ! is_array( $form_ids ) ) {
				$form_ids = array( $form_ids );
			}
			$form_ids = array_map( 'absint', $form_ids );
		}

		$data = array();
		if ( $form_ids && is_array( $form_ids ) ) {
			foreach ( $form_ids as $id ) {
				$form = GFAPI::get_form( $id );
				$data[ $id ] = $form;
			}
		} else {
			$forms = GFFormsModel::get_forms( true );
			foreach ( $forms as $form ) {
				$form_id              = $form->id;
				$totals               = GFFormsModel::get_form_counts( $form_id );
				$form_info            = array(
					'id'      => $form_id,
					'title'   => $form->title,
					'entries' => rgar( $totals, 'total' ),
				);
				$data[ $form_id ] = $form_info;
			}
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get one item from the collection.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$form_id = $request['id'];
		$form = GFAPI::get_form( $form_id );

		if ( $form ) {
			return new WP_REST_Response( $form, 200 );
		} else {
			return new WP_Error( 'gf_not_found', __( 'Form not found', 'gravityforms' ) );
		}
	}

	/**
	 * Create one item from the collection.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request
	 */
	public function create_item( $request ) {

		$form = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $form ) ) {
			return new WP_Error( $form->get_error_code(), $form->get_error_message(), array( 'status' => 400 ) );
		}

		$form_id = GFAPI::add_form( $form );

		if ( is_wp_error( $form_id ) ) {
			$status = $this->get_error_status( $form_id );
			return new WP_Error( $form_id->get_error_code(), $form_id->get_error_message(), array( 'status' => $status ) );
		}

		$form = GFAPI::get_form( $form_id );

		$response = $this->prepare_item_for_response( $form, $request );

		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $form_id ) ) );

		return $response;
	}

	/**
	 * Update one item from the collection
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request
	 */
	public function update_item( $request ) {
		$form_id = $request['id'];
		$form = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $form ) ) {
			return $form;
		}

		$result = GFAPI::update_form( $form, $form_id );

		if ( is_wp_error( $result ) ) {
			$status = $this->get_error_status( $result );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
		}

		$form = GFAPI::get_form( $form_id );

		$response = $this->prepare_item_for_response( $form, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Delete one item from the collection
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request
	 */
	public function delete_item( $request ) {

		$form_id = $request['id'];

		$form = GFAPI::get_form( $form_id );
		if ( empty( $form ) ) {
			return new WP_Error( 'gf_form_invalid_id', __( 'Invalid form id.', 'gravityforms' ), array( 'status' => 404 ) );
		}

		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		if ( $force ) {
			$result = GFAPI::delete_form( $form_id );

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
				return new WP_Error( 'gf_cannot_delete', $message, array( 'status' => 500 ) );
			}

			$previous = $this->prepare_item_for_response( $form, $request );
			$response = new WP_REST_Response();
			$response->set_data( array( 'deleted' => true, 'previous' => $previous->get_data() ) );

		} else {
			if ( rgar( $form, 'is_trash' ) ) {
				$message = __( 'The form has already been deleted.', 'gravityforms' );
				return new WP_Error( 'gf_already_trashed', $message, array( 'status' => 410 ) );
			}

			// Trash the form
			GFAPI::update_form_property( $form_id, 'is_trash', 1 );

			$form = GFAPI::get_form( $form_id );
			$response = rest_ensure_response( $form );
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
		 * Filters the capability required to get forms via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_get_forms', 'gravityforms_edit_forms', $request );
		return $this->current_user_can_any( $capability, $request );
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
		/**
		 * Filters the capability required to get forms via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_get_forms', 'gravityforms_edit_forms', $request );
		return $this->current_user_can_any( $capability, $request );
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
		 * Filters the capability required to create forms via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_post_forms', 'gravityforms_create_form', $request );
		return $this->current_user_can_any( $capability, $request );
	}

	/**
	 * Check if a given request has access to update a specific item.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		/**
		 * Filters the capability required to update forms via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_put_forms', 'gravityforms_create_form', $request );
		return $this->current_user_can_any( $capability, $request );
	}

	/**
	 * Check if a given request has access to delete a specific item.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		/**
		 * Filters the capability required to delete forms via the REST API.
		 *
		 * @since 2.4
		 *
		 * @param string|array    $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_delete_forms', 'gravityforms_delete_forms', $request );
		return $this->current_user_can_any( $capability, $request );
	}

	/**
	 * Prepare the item for create or update operation.
	 *
	 * The Form object must be sent as a JSON string in order to preserve boolean values.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Request object
	 *
	 * @return WP_Error|array $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {
		$form_json = $request->get_json_params();
		if ( ! $form_json ) {

			$form_json = $request->get_body_params();

			if ( empty( $form_json ) || is_array( $form_json ) ) {
				return new WP_Error( 'missing_form', __( 'The Form object must be sent as a JSON string in the request body with the content-type header set to application/json.', 'gravityforms' ) );
			}
		}
		$form = ( is_string( $form_json ) ) ? json_decode( $form_json, true ) : $form_json;

		$form = GFFormsModel::convert_field_objects( $form );

		$form = GFFormsModel::sanitize_settings( $form );

		return $form;
	}

	/**
	 * Prepare the item for the REST response
	 *
	 * @since 2.4-beta-1
	 *
	 * @param mixed $item WordPress representation of the item.
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
			'page'                   => array(
				'description'        => 'Current page of the collection.',
				'type'               => 'integer',
				'default'            => 1,
				'sanitize_callback'  => 'absint',
			),
			'per_page'               => array(
				'description'        => 'Maximum number of items to be returned in result set.',
				'type'               => 'integer',
				'default'            => 10,
				'sanitize_callback'  => 'absint',
			),
			'search'                 => array(
				'description'        => 'The search criteria.',
				'type'               => 'array',
				'sanitize_callback'  => 'sanitize_text_field',
			),
		);
	}
}
