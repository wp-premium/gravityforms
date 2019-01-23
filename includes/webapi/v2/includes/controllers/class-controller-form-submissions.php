<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_REST_Form_Submissions_Controller extends GF_REST_Controller {

	/**
	 * @since 2.4-beta-1
	 *
	 * @var string
	 */
	public $rest_base = 'forms/(?P<form_id>[\d]+)/submissions';

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
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
		) );

	}

	/**
	 * Create one item from the collection.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		$form_id = $request['form_id'];

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$input_values = $request->get_body_params();
			$field_values = isset( $input_values['field_values'] ) ? $input_values['field_values'] : array();
			$target_page  = isset( $input_values['target_page'] ) ? $input_values['target_page'] : 0;
			$source_page  = isset( $input_values['source_page'] ) ? $input_values['source_page'] : 1;
			$input_values = array(); // The input values are already in $_POST
		} else {
			$input_values = $params;
			$field_values = isset( $params['field_values'] ) ? $params['field_values'] : array();
			$target_page  = isset( $params['target_page'] ) ? $params['target_page'] : 0;
			$source_page  = isset( $params['source_page'] ) ? $params['source_page'] : 1;
		}

		$result = GFAPI::submit_form( $form_id, $input_values, $field_values, $target_page, $source_page );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		if ( ! current_user_can( 'gravityforms_view_entries' ) && ! current_user_can( 'gravityforms_edit_entries' ) ) {
			unset( $result['entry_id'] );
		}

		$response = $this->prepare_item_for_response( $result, $request );

		if ( isset( $result['confirmation_type'] ) && $result['confirmation_type'] == 'redirect' ) {
			$response->header( 'Location', $result['confirmation_redirect'] );
		}

		return $response;
	}

	/**
	 * Check if a given request has access to create items.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|boolean
	 */
	public function create_item_permissions_check( $request ) {
		return true;
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

		$status = $item['is_valid'] ? 200 : 400;

		$response = new WP_REST_Response( $item, $status );

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
		return array();
	}

	/**
	 * Get the Entry schema, conforming to JSON Schema.
	 *
	 * @since 2.4-beta-1
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'form-submission',
			'type'       => 'object',
			'properties' => array(
				'input_[Field ID]' => array(
					'description' => __( 'The input values.', 'gravityforms' ),
					'type'        => 'string',
				),
				'field_values' => array(
					'description' => __( 'The field values.', 'gravityforms' ),
					'type'        => 'string',
				),
				'target_page'  => array(
					'description' => 'The target page number.',
					'type'        => 'integer',
				),
				'source_page'  => array(
					'description' => 'The source page number.',
					'type'        => 'integer',
				),
			),
		);

		return $schema;
	}
}

