<?php

/**
 * Handles download requests for files stored by File Upload fields.
 *
 * Class GF_Download
 */
class GF_Download {

	/**
	 * If the request is for a Gravity Forms file download then validate and deliver.
	 *
	 * @since 2.0
	 */
	public static function maybe_process() {
		if ( isset( $_GET['gf-download'] ) ) {

			$file = $_GET['gf-download'];
			$form_id = rgget( 'form-id' );
			$field_id = rgget( 'field-id' );

			if ( empty( $file ) || empty( $form_id ) ) {
				return;
			}

			$hash = rgget( 'hash' );
			GFCommon::log_debug( "start file download process. file: {$file}, hash: {$hash}" );
		    if ( self::validate_download( $form_id, $field_id, $file, $hash ) ) {
				GFCommon::log_debug('dowload validated. start delivery');
			    self::deliver( $form_id, $file );
		    } else {
				GFCommon::log_debug('download validation failed. abort.');
			    self::die_401();
		    }
		}
	}

	/**
	 * Verifies the hash for the download.
	 *
	 * @param int $form_id
	 * @param int $field_id
	 * @param string $file
	 * @param string $hash
	 *
	 * @return bool
	 */
	private static function validate_download( $form_id, $field_id, $file, $hash ) {

		if ( empty( $hash ) ) {
			return false;
		}

		$hash_check = GFCommon::generate_download_hash( $form_id, $field_id, $file );

		$valid = hash_equals( $hash, $hash_check );

		return $valid;
	}

	/**
	 * Send the file.
	 *
	 * @param $form_id
	 * @param $file
	 */
	private static function deliver( $form_id, $file ) {
		$path = GFFormsModel::get_upload_path( $form_id );

		$file_path = trailingslashit( $path ) . $file;

		GFCommon::log_debug( "delivering file: {$file_path}" );

		if ( file_exists( $file_path ) ) {
			GFCommon::log_debug( "file exists - starting delivery" );
			$content_type = self::get_content_type( $file_path );

			$content_disposition = rgget( 'dl' ) ? 'attachment' : 'inline';

			nocache_headers();
			header( 'Robots: none' );
			header( 'Content-Type: ' . $content_type );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: ' . $content_disposition . '; filename="' . basename( $file ) . '"' );
			header( 'Content-Transfer-Encoding: binary' );
			self::readfile_chunked( $file_path );
			die();
		} else {
			GFCommon::log_debug( "file does not exist. abort with 404" );
			self::die_404();
		}
	}

	/**
	 * Returns the appropriate mime type for the file extension.
	 *
	 * @param $file_path
	 *
	 * @return mixed|null|string
	 */
	private static function get_content_type( $file_path ) {
		$info = wp_check_filetype( $file_path );
		$type = rgar( $info, 'type' );
		return $type;
	}

	/**
	 * Reads file in chunks so big downloads are possible without changing PHP.INI
	 * See https://github.com/bcit-ci/CodeIgniter/wiki/Download-helper-for-large-files
	 *
	 * @access   public
	 * @param    string  $file      The file
	 * @param    boolean $retbytes  Return the bytes of file
	 * @return   bool|string        If string, $status || $cnt
	 */
	private static function readfile_chunked( $file, $retbytes = true ) {

		$chunksize = 1024 * 1024;
		$buffer    = '';
		$cnt       = 0;
		$handle    = @fopen( $file, 'r' );

		if ( $size = @filesize( $file ) ) {
			header("Content-Length: " . $size );
		}

		if ( false === $handle ) {
			return false;
		}

		while ( ! @feof( $handle ) ) {
			$buffer = @fread( $handle, $chunksize );
			echo $buffer;

			if ( $retbytes ) {
				$cnt += strlen( $buffer );
			}
		}

		$status = @fclose( $handle );

		if ( $retbytes && $status ) {
			return $cnt;
		}

		return $status;
	}

	/**
	 * Ends the request with a 404 (Not Found) HTTP status code. Loads the 404 template if it exists.
	 */
	private static function die_404() {
		global $wp_query;
		status_header( 404 );
		$wp_query->set_404();
		$template_path = get_404_template();
		if ( file_exists( $template_path ) ) {
			require_once( $template_path );
		}
		die();
	}

	/**
	 * Ends the request with a 401 (Unauthorized) HTTP status code.
	 */
	private static function die_401() {
		status_header( 401 );
		die();
	}
}
