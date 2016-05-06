<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

if ( ! defined( 'GRAVITY_API_URL' ) ) {
	define( 'GRAVITY_API_URL', 'https://o4zq2dvjn6.execute-api.us-east-1.amazonaws.com/prod/' );
}

if( ! class_exists( 'Gravity_Api' ) )
{

/**
 * Client-side API wrapper for interacting with the Gravity APIs.
 *
 * @package    Gravity Forms
 * @subpackage Gravity_Api
 * @since      1.9
 * @access     public
 */

class Gravity_Api {

	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Retrieves site key and site secret key from remote API and stores them as WP options. Returns false if license key is invalid; otherwise, returns true.
	 *
	 * @since  1.9.?
	 * @access public
	 *
	 * @param string $license_key
	 *
	 * @return bool Success
	 */
	public function add_site( $license_key, $is_md5 = false ) {

		$body = array(
			'site_name' => get_bloginfo( 'name' ),
			'site_url'  => get_bloginfo( 'url' ),
		);
		if ( $is_md5 ) {
			$body['license_key_md5'] = $license_key;
		} else {
			$body['license_key'] = $license_key;
		}

		GFCommon::log_debug( __METHOD__ . '() - requesting new site key' );
		$result = $this->request( 'sites', $body );

		$response = $this->prepare_response_body( $result );

		if ( is_wp_error( $response ) ) {
			GFCommon::log_debug( 'Gravity_Api::add_site() - error:' . print_r( $response, true ) );
			return $response;
		} else {

			$site_details = $response_body->data;
			$site_key     = $site_details->key;
			$site_secret  = $site_details->secret;

			update_option( 'gf_site_key', $site_key );
			update_option( 'gf_site_secret', $site_secret );

			GFCommon::log_debug( 'Gravity_Api::add_site() - site created' );

		}

		return true;
	}

	public function update_site( $new_license_key_md5 ) {

		$site_key = $this->get_site_key();
		if ( empty( $site_key ) ) {
			return false;
		}

		$site_secret = $this->get_site_secret();

		GFCommon::log_debug( __METHOD__ . '() - refreshing license info' );

		$body = array(
			'site_secret' => $site_secret,
			'site_name' => get_bloginfo( 'name' ),
			'site_url'  => get_bloginfo( 'url' ),
			'license_key_md5' => $new_license_key_md5,
		);

		$response = $this->request( 'sites/' . $site_key, $body, 'PUT' );

		$result = $this->prepare_response_body( $response );

		return $result;
	}

	/***
	 * Retrieves API Keys for third party services. Requires a valid license
	 *
	 * @param $third_party_name string - Name or the third party service. "Dropbox" is currently the only supported service.
	 * @return WP_Error|object - If successful, returns the api key.
	 */
	public function get_api_key( $third_party_name ){

		$site_keys = $this->ensure_site_registered();
		if ( empty( $site_keys ) ) {
			return false;
		}

		switch ( $third_party_name ){

			case 'Dropbox' :

				GFCommon::log_debug( __METHOD__ . '() - retrieving dropbox api key' );

				$auth = base64_encode( $site_keys['site_key'] . ':' . $site_keys['site_secret'] );

				$headers = array( 'Authorization' => 'GravityAPI ' . $auth );

				$response = $this->request( 'credentials/dropbox', array(), 'GET', array( 'headers' => $headers ) );

				return $this->prepare_response_body( $response );

			default :

				return new WP_Error( 'unsupported_service_name', 'The provided third party service name: ' . $third_party_name . ' is not supported. ' );
		}

	}


	// # HELPERS

	public function prepare_response_body( $raw_response ){

		if ( is_wp_error( $raw_response ) ) {
			return $raw_response;
		}
		else if ( $raw_response['response']['code'] != 200 ) {
			return new WP_Error( 'server_error', 'Error from server: ' . $raw_response['response']['message'] );
		}

		$response_body = json_decode( $raw_response['body'] );

		if ( ! $response_body ){
			return new WP_Error( 'invalid_response', 'Invalid response from server: ' . $raw_response['body'] );
		}

		return $response_body;
	}

	public function purge_site_credentials() {

		delete_option( 'gf_site_key' );
		delete_option( 'gf_site_secret' );
	}

	public function request( $resource, $body, $method = 'POST', $options = array() ) {
		$body['timestamp'] = time();

		// set default options
		$options = wp_parse_args( $options, array(
			'method'  => $method,
			'timeout' => 10,
			'body'    => in_array( $method, array( 'GET', 'DELETE' ) ) ? null : json_encode( $body ),
			'headers' => array(),
			'sslverify' => false,
		) );

		// set default header options
		$options['headers'] = wp_parse_args( $options['headers'], array(
			'Content-Type'   => 'application/json; charset=' . get_option( 'blog_charset' ),
			'User-Agent'     => 'WordPress/' . get_bloginfo( 'version' ),
			'Referer'        => get_bloginfo( 'url' )
		) );

		// WP docs say method should be uppercase
		$options['method'] = strtoupper( $options['method'] );

		$request_url  = $this->get_gravity_api_url() . $resource;
		$raw_response = wp_remote_request( $request_url, $options );


		return $raw_response;
	}

	public function get_site_key(){

		if ( defined( 'GRAVITY_API_SITE_KEY' ) ) {
			return GRAVITY_API_SITE_KEY;
		}

		$site_key = get_option( 'gf_site_key' );
		if ( empty( $site_key ) ) {
			return false;
		}
		return $site_key;

	}

	public function get_site_secret(){
		if ( defined( 'GRAVITY_API_SITE_SECRET' ) ) {
			return GRAVITY_API_SITE_SECRET;
		}
		$site_secret = get_option( 'gf_site_secret' );
		if ( empty( $site_secret ) ) {
			return false;
		}
		return $site_secret;
	}

	public function get_gravity_api_url() {
		return trailingslashit( GRAVITY_API_URL );
	}

	public function ensure_site_registered(){

		if ( ! $this->is_site_registered() ){

			$license_key_md5 = GFCommon::get_key();
			if ( empty( $license_key_md5 ) ){
				return false;
			}

			$result = $this->add_site( $license_key_md5, true );

			if ( ! $result || is_wp_error( $result ) ) {
				return false;
			}
		}

		return array(
			'site_key' => $this->get_site_key(),
			'site_secret' => $this->get_site_secret(),
		);
	}

	public function is_site_registered(){
		return $this->get_site_key() && $this->get_site_secret();
	}

}

function gapi() {
	return Gravity_Api::get_instance();
}

gapi();

}
