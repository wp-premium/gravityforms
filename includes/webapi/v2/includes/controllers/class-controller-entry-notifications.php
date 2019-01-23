<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_REST_Entry_Notifications_Controller extends GF_REST_Controller {

	/**
	 * @since 2.4-beta-1
	 *
	 * @var string
	 */
	public $rest_base = 'entries/(?P<entry_id>[\d]+)/notifications';

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
				'methods'         => WP_REST_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $this->get_collection_params(),
			),
		) );
	}

	/**
	 * Re-sends notifications for an entry.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		$entry_id = $request['entry_id'];

		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		$form_id = $entry['form_id'];

		$form = GFAPI::get_form( $form_id );

		if ( empty( $form ) ) {
			return new WP_Error( __( 'Form not found.', 'gravityforms' ) );
		}

		$notification_ids = $request['_notifications'];

		if ( ! empty( $notification_ids ) ) {
			$notification_ids = (array) explode( ',', $request['_notifications'] );
			$notification_ids = array_map( 'trim', $notification_ids );
		}

		$event = isset( $request['_event'] ) ? $request['_event'] : 'form_submission';

		if ( empty( $notification_ids ) ) {
			$notification_ids = GFAPI::send_notifications( $form, $entry, $event );
		} else {
			GFCommon::send_notifications( $notification_ids, $form, $entry, true );
		}

		return new WP_REST_Response( $notification_ids, 200 );
	}

	/**
	 * Check if a given request has permission to send notifications.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		/**
		 * Filters the capability required to re-send notifications via the REST API.
		 *
		 * @since 2.4-beta-1
		 *
		 * @param string          $capability The capability required for this endpoint.
		 * @param WP_REST_Request $request    Full data about the request.
		 */
		$capability = apply_filters( 'gform_rest_api_capability_post_entries_notifications', 'gravityforms_edit_entries', $request );
		return GFAPI::current_user_can_any( $capability );
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
			'include' => array(
				'description' => 'Limit the notifications to specific IDs.',
			),
			'event'   => array(
				'description' => 'The event to trigger. Default: form_submission.',
			),
		);
	}
}
