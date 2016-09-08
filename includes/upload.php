<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFAsyncUpload {

	public static function upload() {

		GFCommon::log_debug( 'GFAsyncUpload::upload(): Starting.' );

		if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
			status_header( 404 );
			die();
		}

		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		send_nosniff_header();
		nocache_headers();

		status_header( 200 );

		// If the file is bigger than the server can accept then the form_id might not arrive.
		// This might happen if the file is bigger than the max post size ini setting.
		// Validation in the browser reduces the risk of this happening.
		if ( ! isset( $_REQUEST['form_id'] ) ) {
			GFCommon::log_debug( 'GFAsyncUpload::upload(): File upload aborted because the form_id was not found. The file may have been bigger than the max post size ini setting.' );
			self::die_error( 500, __( 'Failed to upload file.', 'gravityforms' ) );
		}


		$form_id        = absint( $_REQUEST['form_id'] );
		$form_unique_id = rgpost( 'gform_unique_id' );
		$form           = GFAPI::get_form( $form_id );

		if ( empty( $form ) || ! $form['is_active'] ) {
			die();
		}

		if ( rgar( $form, 'requireLogin' ) ) {
			if ( ! is_user_logged_in() ) {
				die();
			}
			check_admin_referer( 'gform_file_upload_' . $form_id, '_gform_file_upload_nonce_' . $form_id );
		}

		if ( ! ctype_alnum( $form_unique_id ) ) {
			die();
		}

		$target_dir = GFFormsModel::get_upload_path( $form_id ) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;

		if ( ! is_dir( $target_dir ) ) {
			if ( ! wp_mkdir_p( $target_dir ) ) {
				GFCommon::log_debug( "GFAsyncUpload::upload(): Couldn't create the tmp folder: " . $target_dir );
				self::die_error( 500, __( 'Failed to upload file.', 'gravityforms' ) );
			}
		}

		$time = current_time( 'mysql' );
		$y    = substr( $time, 0, 4 );
		$m    = substr( $time, 5, 2 );

		//adding index.html files to all subfolders
		if ( ! file_exists( GFFormsModel::get_upload_root() . '/index.html' ) ) {
			GFForms::add_security_files();
		} else if ( ! file_exists( GFFormsModel::get_upload_path( $form_id ) . '/index.html' ) ) {
			GFCommon::recursive_add_index_file( GFFormsModel::get_upload_path( $form_id ) );
		} else if ( ! file_exists( GFFormsModel::get_upload_path( $form_id ) . "/$y/index.html" ) ) {
			GFCommon::recursive_add_index_file( GFFormsModel::get_upload_path( $form_id ) . "/$y" );
		} else {
			GFCommon::recursive_add_index_file( GFFormsModel::get_upload_path( $form_id ) . "/$y/$m" );
		}

		if ( ! file_exists( $target_dir . '/index.html' ) ) {
			GFCommon::recursive_add_index_file( $target_dir );
		}

		$uploaded_filename = $_FILES['file']['name'];
		$file_name = isset( $_REQUEST['name'] ) ? $_REQUEST['name'] : '';
		$field_id  = rgpost( 'field_id' );
		$field_id = absint( $field_id );
		$field     = GFFormsModel::get_field( $form, $field_id );

		if ( empty( $field ) || GFFormsModel::get_input_type( $field ) != 'fileupload' ) {
			die();
		}

		$file_name = sanitize_file_name( $file_name );
		$uploaded_filename = sanitize_file_name( $uploaded_filename );

		$allowed_extensions = ! empty( $field->allowedExtensions ) ? GFCommon::clean_extensions( explode( ',', strtolower( $field->allowedExtensions ) ) ) : array();

		$max_upload_size_in_bytes = $field->maxFileSize > 0 ? $field->maxFileSize * 1048576 : wp_max_upload_size();
		$max_upload_size_in_mb    = $max_upload_size_in_bytes / 1048576;

		if ( $_FILES['file']['size'] > 0 && $_FILES['file']['size'] > $max_upload_size_in_bytes ) {
			self::die_error( 104,sprintf( __( 'File exceeds size limit. Maximum file size: %dMB', 'gravityforms' ), $max_upload_size_in_mb ) );
		}

		if ( GFCommon::file_name_has_disallowed_extension( $file_name ) || GFCommon::file_name_has_disallowed_extension( $uploaded_filename ) ) {
			GFCommon::log_debug( "GFAsyncUpload::upload(): Illegal file extension: {$file_name}" );
			self::die_error( 104, __( 'The uploaded file type is not allowed.', 'gravityforms' ) );
		}

		if ( ! empty( $allowed_extensions ) ) {
			if ( ! GFCommon::match_file_extension( $file_name, $allowed_extensions ) || ! GFCommon::match_file_extension( $uploaded_filename, $allowed_extensions ) ) {
				GFCommon::log_debug( "GFAsyncUpload::upload(): The uploaded file type is not allowed: {$file_name}" );
				self::die_error( 104, sprintf( __( 'The uploaded file type is not allowed. Must be one of the following: %s', 'gravityforms' ), strtolower( $field['allowedExtensions'] ) ) );
			}
		}

		/**
		 * Allows the disabling of file upload whitelisting
		 *
		 * @param bool false Set to 'true' to disable whitelisting.  Defaults to 'false'.
		 */
		$whitelisting_disabled = apply_filters( 'gform_file_upload_whitelisting_disabled', false );

		if ( empty( $allowed_extensions ) && ! $whitelisting_disabled ) {
			// Whitelist the file type
			$valid_uploaded_filename = GFCommon::check_type_and_ext( $_FILES['file'], $uploaded_filename );

			if ( is_wp_error( $valid_uploaded_filename ) ) {
				self::die_error( $valid_uploaded_filename->get_error_code(), $valid_uploaded_filename->get_error_message() );
			}

			$valid_file_name = GFCommon::check_type_and_ext( $_FILES['file'], $file_name );

			if ( is_wp_error( $valid_file_name ) ) {
				self::die_error( $valid_file_name->get_error_code(), $valid_file_name->get_error_message() );
			}
		}

		$tmp_file_name = $form_unique_id . '_input_' . $field_id . '_' . $file_name;

		$tmp_file_name = sanitize_file_name( $tmp_file_name );

		$file_path = $target_dir . $tmp_file_name;

		$cleanup_target_dir = true; // Remove old files
		$max_file_age = 5 * 3600; // Temp file age in seconds

		// Remove old temp files
		if ( $cleanup_target_dir ) {
			if ( is_dir( $target_dir ) && ( $dir = opendir( $target_dir ) ) ) {
				while ( ( $file = readdir( $dir ) ) !== false ) {
					$tmp_file_path = $target_dir . $file;

					// Remove temp file if it is older than the max age and is not the current file
					if ( preg_match( '/\.part$/', $file ) && ( filemtime( $tmp_file_path ) < time() - $max_file_age ) && ( $tmp_file_path != "{$file_path}.part" ) ) {
						GFCommon::log_debug( 'GFAsyncUpload::upload(): Deleting file: ' . $tmp_file_path );
						@unlink( $tmp_file_path );
					}
				}
				closedir( $dir );
			} else {
				GFCommon::log_debug( 'GFAsyncUpload::upload(): Failed to open temp directory: ' . $target_dir );
				self::die_error( 100, __( 'Failed to open temp directory.', 'gravityforms' ) );
			}
		}

		if ( isset( $_SERVER['HTTP_CONTENT_TYPE'] ) ) {
			$contentType = $_SERVER['HTTP_CONTENT_TYPE'];
		}

		if ( isset( $_SERVER['CONTENT_TYPE'] ) ) {
			$contentType = $_SERVER['CONTENT_TYPE'];
		}

		$chunk  = isset( $_REQUEST['chunk'] ) ? intval( $_REQUEST['chunk'] ) : 0;
		$chunks = isset( $_REQUEST['chunks'] ) ? intval( $_REQUEST['chunks'] ) : 0;

		// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
		if ( strpos( $contentType, 'multipart' ) !== false ) {
			if ( isset( $_FILES['file']['tmp_name'] ) && is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
				// Open temp file
				$out = @fopen( "{$file_path}.part", $chunk == 0 ? 'wb' : 'ab' );
				if ( $out ) {
					// Read binary input stream and append it to temp file
					$in = @fopen( $_FILES['file']['tmp_name'], 'rb' );

					if ( $in ) {
						while ( $buff = fread( $in, 4096 ) ) {
							fwrite( $out, $buff );
						}
					} else {
						self::die_error( 101, __( 'Failed to open input stream.', 'gravityforms' ) );
					}

					@fclose( $in );
					@fclose( $out );
					@unlink( $_FILES['file']['tmp_name'] );
				} else {
					self::die_error( 102, __( 'Failed to open output stream.', 'gravityforms' ) );
				}
			} else {
				self::die_error( 103, __( 'Failed to move uploaded file.', 'gravityforms' ) );
			}
		} else {
			// Open temp file
			$out = @fopen( "{$file_path}.part", $chunk == 0 ? 'wb' : 'ab' );
			if ( $out ) {
				// Read binary input stream and append it to temp file
				$in = @fopen( 'php://input', 'rb' );

				if ( $in ) {
					while ( $buff = fread( $in, 4096 ) ) {
						fwrite( $out, $buff );
					}
				} else {
					self::die_error( 101, __( 'Failed to open input stream.', 'gravityforms' ) );
				}

				@fclose( $in );
				@fclose( $out );
			} else {
				self::die_error( 102, __( 'Failed to open output stream.', 'gravityforms' ) );
			}
		}

		// Check if file has been uploaded
		if ( ! $chunks || $chunk == $chunks - 1 ) {
			// Strip the temp .part suffix off
			rename( "{$file_path}.part", $file_path );
		}


		if ( file_exists( $file_path ) ) {
			GFFormsModel::set_permissions( $file_path );
		} else {
			self::die_error( 105, __( 'Upload unsuccessful', 'gravityforms' ) . ' '. $uploaded_filename );
		}

		$output = array(
			'status' => 'ok',
			'data'   => array(
				'temp_filename'     => $tmp_file_name,
				'uploaded_filename' => str_replace( "\\'", "'", urldecode( $uploaded_filename ) ) //Decoding filename to prevent file name mismatch.
			)
		);

		$output = json_encode( $output );

		GFCommon::log_debug( sprintf( 'GFAsyncUpload::upload(): File upload complete. temp_filename: %s  uploaded_filename: %s ', $tmp_file_name, $uploaded_filename ) );

		gf_do_action( array( 'gform_post_multifile_upload', $form['id'] ), $form, $field, $uploaded_filename, $tmp_file_name, $file_path );

		die( $output );
	}

	public static function die_error( $status_code, $message ) {
		$response = array();

		$response['status'] = 'error';
		$response['error'] = array(
			'code' => $status_code,
			'message' => $message,
		);
		$response_json = json_encode( $response );
		die( $response_json );
	}
}



GFAsyncUpload::upload();
