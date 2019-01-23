<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Rest Controller Class
 *
 * @author   Rocketgenius
 * @category API
 * @package  Rocketgenius/Abstracts
 * @extends  WP_REST_Controller
 */
abstract class GF_REST_Controller extends WP_REST_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @since 2.4-beta-1
	 *
	 * @var string
	 */
	protected $namespace = 'gf/v2';

	/**
	 * Route base.
	 *
	 * @since 2.4-beta-1
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Parses the entry search, sort and paging parameters from the request
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return array Returns an associative array with the "search_criteria", "paging" and "sorting" keys appropriately populated.
	 */
	public function parse_entry_search_params( $request ) {

		// Sorting parameters
		$sorting_param = $request->get_param( 'sorting' );
		$sort_key = isset( $sorting_param['key'] ) && ! empty( $sorting_param['key'] ) ? $sorting_param['key'] : 'id';
		$sort_dir = isset( $sorting_param['direction'] ) && ! empty( $sorting_param['direction'] ) ? $sorting_param['direction'] : 'DESC';
		$sorting  = array( 'key' => $sort_key, 'direction' => $sort_dir );
		if ( isset( $sorting_param['is_numeric'] ) ) {
			$sorting['is_numeric'] = $sorting_param['is_numeric'];
		}

		// paging parameters
		$paging_param = $request->get_param( 'paging' );
		$page_size = isset( $paging_param['page_size'] ) ? intval( $paging_param['page_size'] ) : 10;
		if ( isset( $paging_param['current_page'] ) ) {
			$current_page = intval( $paging_param['current_page'] );
			$offset       = $page_size * ( $current_page - 1 );
		} else {
			$offset = isset( $paging_param['offset'] ) ? intval( $paging_param['offset'] ) : 0;
		}

		$paging = array( 'offset' => $offset, 'page_size' => $page_size );

		$search = $request->get_param( 'search' );
		if ( isset( $search ) ) {
			if ( ! is_array( $search ) ) {
				$search = urldecode( ( stripslashes( $search ) ) );
				$search = json_decode( $search, true );
			}
		} else {
			$search = array();
		}

		if ( ! isset( $search['status'] ) ) {
			$search['status'] = 'active';
		}

		$params = array(
			'search_criteria' => $search,
			'paging'          => $paging,
			'sorting'         => $sorting,
		);

		$form_ids = $request->get_param( 'form_ids' );

		if ( isset( $form_ids ) ) {
			$params['form_ids'] = $form_ids;
		}

		return $params;
	}

	/**
	 * JSON encodes list fields in the specified $entry and returns the new $entry
	 *
	 * @since 2.4-beta-1
	 *
	 * @param array $entry The entry object
	 *
	 * @return array Returns the $entry array with the list fields json encoded
	 */
	public function maybe_json_encode_list_fields( $entry ) {
		$form_id = $entry['form_id'];
		$form    = GFAPI::get_form( $form_id );
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				if ( $field->get_input_type() == 'list' ) {
					$new_value = maybe_unserialize( $entry[ $field->id ] );

					if ( ! $this->is_json( $new_value ) ) {
						$new_value = json_encode( $new_value );
					}

					$entry[ $field->id ] = $new_value;
				}
			}
		}

		return $entry;
	}

	/**
	 * Determines if the specified values is a JSON encoded string
	 *
	 * @since 2.4-beta-1
	 *
	 * @param mixed $value The value to be checked
	 *
	 * @return bool True if the speficied value is JSON encoded. False otherwise
	 */
	public static function is_json( $value ) {
		if ( is_string( $value ) && in_array( substr( $value, 0, 1 ), array( '{', '[' ) ) && is_array( json_decode( $value, ARRAY_A ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Filters an entry, removing fields that aren't in the list of specified $field_ids
	 *
	 * @since 2.4-beta-1
	 *
	 * @param array $entry The entry to be filtered
	 * @param array $field_ids The field IDs to be kept in the entry
	 *
	 * @return array Returns the entry array, containing only the field_ids specified in the $field_ids array.
	 */
	public static function filter_entry_fields( $entry, $field_ids ) {

		if ( ! is_array( $field_ids ) ) {
			$field_ids = array( $field_ids );
		}
		$new_entry = array();
		foreach ( $entry as $key => $val ) {
			if ( in_array( $key, $field_ids ) || ( is_numeric( $key ) && in_array( intval( $key ), $field_ids ) ) ) {
				$new_entry[ $key ] = $val;
			}
		}

		return $new_entry;
	}

	/***
	 * Prepares entry for REST API response, decoding or unserializing appropriate fields
	 *
	 * @since 2.4-beta-1
	 *
	 * @param array $entry The entry array
	 *
	 * @return bool|array Returns the entry array ready to be send in the REST API response.
	 */
	public function prepare_entry_for_response( $entry ) {

		if ( is_wp_error( $entry ) || ! isset( $entry['form_id'] ) ) {
			return $entry;
		}

		$form = GFAPI::get_form( $entry['form_id'] );
		foreach ( $form['fields'] as $field ) {
			if ( $this->is_field_value_json( $field ) ) {

				$value = $entry[ $field->id ];

				if ( $field->get_input_type() == 'list' ) {
					$new_value = maybe_unserialize( $value );
				} else {
					$new_value = json_decode( $value );
				}

				$entry[ $field->id ] = $new_value;

			}
		}

		return $entry;
	}

	/***
	 * Determines if the value of the specified field is stored in JSON format
	 *
	 * @since 2.4-beta-1
	 *
	 * @param GF_Field $field The field to be checked
	 *
	 * @return bool Returns true if the specified field's value is stored in JSON format. Retruns false otherwise.
	 */
	public function is_field_value_json( $field ) {

		$input_type = $field->get_input_type();

		if ( in_array( $input_type, array( 'multiselect', 'list' ) ) ) {
			return true;
		}

		if ( $input_type == 'fileupload' && $field->multipleFiles ) {
			return true;
		}

		return false;
	}

	/**
	 * Serializes list fields in the specified $entry array.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param  array $entry   The entry array
	 * @param null   $form_id The current form id
	 *
	 * @return array Returns the $entry array with all it's list fields serialized.
	 */
	public function maybe_serialize_list_fields( $entry, $form_id = null ) {
		if ( empty( $form_id ) ) {
			$form_id = $entry['form_id'];
		}
		$form = GFAPI::get_form( $form_id );
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				if ( $field->get_input_type() == 'list' && isset( $entry[ $field->id ] ) ) {
					$new_list_value = self::maybe_decode_json( $entry[ $field->id ] );
					if ( ! is_serialized( $new_list_value ) ) {
						$new_list_value = serialize( $new_list_value );
					}
					$entry[ $field->id ] = $new_list_value;
				}
			}
		}

		return $entry;
	}

	/**
	 * JSON encodes appropriate fields in the specified $entry array
	 *
	 * @since 2.4-beta-1
	 *
	 * @param array $entry The entry array.
	 *
	 * @return array Returns the $entry array with all appropriate fields JSON encoded.
	 */
	public function maybe_json_encode_applicable_fields( $entry ) {

		$form = GFAPI::get_form( $entry['form_id'] );

		foreach ( $form['fields'] as $field ) {
			if ( $this->is_field_value_json( $field ) && $field->get_input_type() != 'list' && isset( $entry[ $field->id ] ) ) {
				$entry[ $field->id ] = json_encode( $entry[ $field->id ] );
			}
		}

		return $entry;
	}

	/**
	 * Decodes JSON encoded strings.
	 *
	 * @since 2.4-beta-1
	 *
	 * @param string $value String to be decoded
	 *
	 * @return array|mixed Returns the decoded JSON array. If the specified $value isn't a JSON encoded string, returns
	 *                     $value.
	 */
	public static function maybe_decode_json( $value ) {
		if ( self::is_json( $value ) ) {
			return json_decode( $value, ARRAY_A );
		}

		return $value;
	}

	/**
	 * Returns the http error status
	 *
	 * @since 2.4-beta-1
	 *
	 * @param WP_Error $wp_error
	 *
	 * @return int Returns the http status recored in the specified $wp_error
	 */
	public function get_error_status( $wp_error ) {
		$error_code = $wp_error->get_error_code();
		$mappings   = array(
			'not_found'   => 404,
			'not_allowed' => 401,
		);
		$http_code  = isset( $mappings[ $error_code ] ) ? $mappings[ $error_code ] : 400;

		return $http_code;
	}

	/**
	 * Writes a message to the log
	 *
	 * @since 2.4-beta-1
	 *
	 * @param string $message
	 */
	public function log_debug( $message ) {
		GFCommon::log_debug( $message );
	}
}
