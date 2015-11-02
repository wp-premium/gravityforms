<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_FileUpload extends GF_Field {

	public $type = 'fileupload';

	public function get_form_editor_field_title() {
		return esc_attr__( 'File Upload', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'rules_setting',
			'file_extensions_setting',
			'file_size_setting',
			'multiple_files_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function validate( $value, $form ) {
		$input_name = 'input_' . $this->id;

		$allowed_extensions = ! empty( $this->allowedExtensions ) ? GFCommon::clean_extensions( explode( ',', strtolower( $this->allowedExtensions ) ) ) : array();

		if ( $this->multipleFiles ) {
			$file_names = isset( GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ] ) ? GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ] : array();
		} else {
			$max_upload_size_in_bytes = isset( $this->maxFileSize ) && $this->maxFileSize > 0 ? $this->maxFileSize * 1048576 : wp_max_upload_size();
			$max_upload_size_in_mb    = $max_upload_size_in_bytes / 1048576;
			if ( ! empty( $_FILES[ $input_name ]['name'] ) && $_FILES[ $input_name ]['error'] > 0 ) {
				$uploaded_file_name = isset( GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ] ) ? GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ] : '';
				if ( empty( $uploaded_file_name ) ) {
					$this->failed_validation = true;
					switch ( $_FILES[ $input_name ]['error'] ) {
						case UPLOAD_ERR_INI_SIZE :
						case UPLOAD_ERR_FORM_SIZE :
							$fileupload_validation_message = sprintf( esc_html__( 'File exceeds size limit. Maximum file size: %dMB', 'gravityforms' ), $max_upload_size_in_mb );
							break;
						default :
							$fileupload_validation_message = sprintf( esc_html__( 'There was an error while uploading the file. Error code: %d', 'gravityforms' ), $_FILES[ $input_name ]['error'] );
					}
					$this->validation_message = empty( $this->errorMessage ) ? $fileupload_validation_message : $this->errorMessage;
					return;
				}
			} elseif ( $_FILES[ $input_name ]['size'] > 0 && $_FILES[ $input_name ]['size'] > $max_upload_size_in_bytes ) {
				$this->failed_validation = true;
				$this->validation_message = sprintf( esc_html__( 'File exceeds size limit. Maximum file size: %dMB', 'gravityforms' ), $max_upload_size_in_mb );
				return;
			}

			/**
			 * A filter to allow or disallow whitelisting when uploading a file
			 *
			 * @param bool To set upload whitelisting to true or false (default is false, which means it is enabled)
			 */
			$whitelisting_disabled = apply_filters( 'gform_file_upload_whitelisting_disabled', false );

			if ( ! empty( $_FILES[ $input_name ]['name'] ) && empty( $allowed_extensions ) && ! $whitelisting_disabled ) {
				$check_result = GFCommon::check_type_and_ext( $_FILES[ $input_name ] );
				if ( is_wp_error( $check_result ) ) {
					$this->failed_validation = true;
					$this->validation_message = esc_html__( 'The uploaded file type is not allowed.', 'gravityforms' );
					return;
				}
			}
			$single_file_name = $_FILES[ $input_name ]['name'];
			$file_names = array( array( 'uploaded_filename' => $single_file_name ) );
		}

		foreach ( $file_names as $file_name ) {
			$info = pathinfo( rgar( $file_name, 'uploaded_filename' ) );

			if ( empty( $allowed_extensions ) ) {
				if ( GFCommon::file_name_has_disallowed_extension( rgar( $file_name, 'uploaded_filename' ) ) ) {
					$this->failed_validation  = true;
					$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'The uploaded file type is not allowed.', 'gravityforms' ) : $this->errorMessage;
				}
			} else {
				if ( ! empty( $info['basename'] ) && ! GFCommon::match_file_extension( rgar( $file_name, 'uploaded_filename' ), $allowed_extensions ) ) {
					$this->failed_validation  = true;
					$this->validation_message = empty( $this->errorMessage ) ? sprintf( esc_html__( 'The uploaded file type is not allowed. Must be one of the following: %s', 'gravityforms' ), strtolower( $this->allowedExtensions ) ) : $this->errorMessage;
				}
			}
		}
	}

	public function get_first_input_id( $form ) {

		return $this->multipleFiles ? '' : 'input_' . $form['id'] . '_' . $this->id;
	}


	public function get_field_input( $form, $value = '', $entry = null ) {

		$lead_id = absint( rgar( $entry, 'id' ) );

		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = absint( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$size         = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class        = $size . $class_suffix;

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$tabindex        = $this->get_tabindex();
		$multiple_files  = $this->multipleFiles;
		$file_list_id    = 'gform_preview_' . $form_id . '_' . $id;

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin = $is_entry_detail || $is_form_editor;

		$max_upload_size = ! $is_admin && $this->maxFileSize > 0 ? $this->maxFileSize * 1048576 : wp_max_upload_size();
		$allowed_extensions = ! empty( $this->allowedExtensions ) ? join( ',', GFCommon::clean_extensions( explode( ',', strtolower( $this->allowedExtensions ) ) ) ) : array();
		if ( ! empty( $allowed_extensions ) ) {
			$extensions_message = esc_attr( sprintf( __( 'Accepted file types: %s.', 'gravityforms' ), str_replace( ',', ', ', $allowed_extensions ) ) );
		} else {
			$extensions_message = '';
		}

		if ( $multiple_files ) {
			$upload_action_url = trailingslashit( site_url() ) . '?gf_page=' . GFCommon::get_upload_page_slug();
			$max_files         = $this->maxFiles > 0 ? $this->maxFiles : 0;
			$browse_button_id  = 'gform_browse_button_' . $form_id . '_' . $id;
			$container_id      = 'gform_multifile_upload_' . $form_id . '_' . $id;
			$drag_drop_id      = 'gform_drag_drop_area_' . $form_id . '_' . $id;

			$messages_id        = "gform_multifile_messages_{$form_id}_{$id}";
			if ( empty( $allowed_extensions ) ) {
				$allowed_extensions = '*';
			}
			$disallowed_extensions = GFCommon::get_disallowed_file_extensions();
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX && 'rg_change_input_type' === rgpost( 'action' ) ) {
				$plupload_init = array();
			} else {
				$plupload_init = array(
					'runtimes'            => 'html5,flash,html4',
					'browse_button'       => $browse_button_id,
					'container'           => $container_id,
					'drop_element'        => $drag_drop_id,
					'filelist'            => $file_list_id,
					'unique_names'        => true,
					'file_data_name'      => 'file',
					/*'chunk_size' => '10mb',*/ // chunking doesn't currently have very good cross-browser support
					'url'                 => $upload_action_url,
					'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
					'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
					'filters'             => array(
						'mime_types'    => array( array( 'title' => __( 'Allowed Files', 'gravityforms' ), 'extensions' => $allowed_extensions ) ),
						'max_file_size' => $max_upload_size . 'b',
					),
					'multipart'           => true,
					'urlstream_upload'    => false,
					'multipart_params'    => array(
						'form_id'  => $form_id,
						'field_id' => $id,
					),
					'gf_vars'             => array(
						'max_files'             => $max_files,
						'message_id'            => $messages_id,
						'disallowed_extensions' => $disallowed_extensions,
					)
				);

				if ( rgar( $form, 'requireLogin' ) ) {
					$plupload_init['multipart_params'][ '_gform_file_upload_nonce_' . $form_id ] = wp_create_nonce( 'gform_file_upload_' . $form_id, '_gform_file_upload_nonce_' . $form_id );
				}

				// plupload 2 was introduced in WordPress 3.9. Plupload 1 accepts a slightly different init array.
				if ( version_compare( get_bloginfo( 'version' ), '3.9-RC1', '<' ) ) {
					$plupload_init['max_file_size'] = $max_upload_size . 'b';
					$plupload_init['filters']       = array( array( 'title' => __( 'Allowed Files', 'gravityforms' ), 'extensions' => $allowed_extensions ) );
				}
			}

			$plupload_init = gf_apply_filters( 'gform_plupload_settings', $form_id, $plupload_init, $form_id, $this );

			$drop_files_here_text = esc_html__( 'Drop files here or', 'gravityforms' );
			$select_files_text    = esc_attr__( 'Select files', 'gravityforms' );

			$plupload_init_json = htmlspecialchars( json_encode( $plupload_init ), ENT_QUOTES, 'UTF-8' );
			$upload             = "<div id='{$container_id}' data-settings='{$plupload_init_json}' class='gform_fileupload_multifile'>
										<div id='{$drag_drop_id}' class='gform_drop_area'>
											<span class='gform_drop_instructions'>{$drop_files_here_text} </span>
											<input id='{$browse_button_id}' type='button' value='{$select_files_text}' class='button gform_button_select_files' aria-describedby='extensions_message' {$tabindex} />
										</div>
									</div>";
			if ( ! $is_admin ) {
				$upload .= "<span id='extensions_message' class='screen-reader-text'>{$extensions_message}</span>";
				$upload .= "<div class='validation_message'>
								<ul id='{$messages_id}'>
								</ul>
							</div>";
			}

			if ( $is_entry_detail ) {
				$upload .= sprintf( '<input type="hidden" name="input_%d" value=\'%s\' />', $id, esc_attr( $value ) );
			}
		} else {
			$upload = '';
			if ( $max_upload_size <= 2047 * 1048576 ) {
				//  MAX_FILE_SIZE > 2048MB fails. The file size is checked anyway once uploaded, so it's not necessary.
				$upload = sprintf( "<input type='hidden' name='MAX_FILE_SIZE' value='%d' />", $max_upload_size );
			}
			$upload .= sprintf( "<input name='input_%d' id='%s' type='file' class='%s' aria-describedby='extensions_message' {$tabindex} %s/>", $id, $field_id, esc_attr( $class ), $disabled_text );

			if ( ! $is_admin ) {
				$upload .= "<span id='extensions_message' class='screen-reader-text'>{$extensions_message}</span>";
			}
		}

		if ( $is_entry_detail && ! empty( $value ) ) { // edit entry
			$file_urls      = $multiple_files ? json_decode( $value ) : array( $value );
			$upload_display = $multiple_files ? '' : "style='display:none'";
			$preview        = "<div id='upload_$id' {$upload_display}>$upload</div>";
			$preview .= sprintf( "<div id='%s'></div>", $file_list_id );
			$preview .= sprintf( "<div id='preview_existing_files_%d'>", $id );

			foreach ( $file_urls as $file_index => $file_url ) {
				if ( GFCommon::is_ssl() && strpos( $file_url, 'http:' ) !== false ) {
					$file_url = str_replace( 'http:', 'https:', $file_url );
				}
				$download_file_text  = esc_attr__( 'Download file', 'gravityforms' );
				$delete_file_text    = esc_attr__( 'Delete file', 'gravityforms' );
				$file_index          = intval( $file_index );
				$file_url            = esc_attr( $file_url );
				$display_file_url    = GFCommon::truncate_url( $file_url );
				$download_button_url = GFCommon::get_base_url() . '/images/download.png';
				$delete_button_url   = GFCommon::get_base_url() . '/images/delete.png';
				$preview .= "<div id='preview_file_{$file_index}' class='ginput_preview'>
								<a href='{$file_url}' target='_blank' alt='{$file_url}' title='{$file_url}'>{$display_file_url}</a>
								<a href='{$file_url}' target='_blank' alt='{$download_file_text}' title='{$download_file_text}'>
								<img src='{$download_button_url}' style='margin-left:10px;'/></a><a href='javascript:void(0);' alt='{$delete_file_text}' title='{$delete_file_text}' onclick='DeleteFile({$lead_id},{$id},this);' ><img src='{$delete_button_url}' style='margin-left:10px;'/></a>
							</div>";
			}

			$preview .= '</div>';

			return $preview;
		} else {
			$input_name     = "input_{$id}";
			$uploaded_files = isset( GFFormsModel::$uploaded_files[ $form_id ][ $input_name ] ) ? GFFormsModel::$uploaded_files[ $form_id ][ $input_name ] : array();
			$file_infos     = $multiple_files ? $uploaded_files : RGFormsModel::get_temp_filename( $form_id, $input_name );

			if ( ! empty( $file_infos ) ) {
				$preview    = sprintf( "<div id='%s'>", $file_list_id );
				$file_infos = $multiple_files ? $uploaded_files : array( $file_infos );
				foreach ( $file_infos as $file_info ) {
					$file_upload_markup = apply_filters( 'gform_file_upload_markup', "<img alt='" . esc_attr__( 'Delete file', 'gravityforms' ) . "' title='" . esc_attr__( 'Delete file', 'gravityforms' ) . "' class='gform_delete' src='" . GFCommon::get_base_url() . "/images/delete.png' onclick='gformDeleteUploadedFile({$form_id}, {$id}, this);' /> <strong>" . esc_html( $file_info['uploaded_filename'] ) . '</strong>', $file_info, $form_id, $id );
					$preview .= "<div class='ginput_preview'>{$file_upload_markup}</div>";
				}
				$preview .= '</div>';
				if ( ! $multiple_files ) {
					$upload = str_replace( " class='", " class='gform_hidden ", $upload );
				}

				return "<div class='ginput_container ginput_container_fileupload'>" . $upload . " {$preview}</div>";
			} else {

				$preview = $multiple_files ? sprintf( "<div id='%s'></div>", $file_list_id ) : '';

				return "<div class='ginput_container ginput_container_fileupload'>$upload</div>" . $preview;
			}
		}
	}

	public function is_value_submission_empty( $form_id ) {
		$input_name = 'input_' . $this->id;

		if ( $this->multipleFiles ) {
			$uploaded_files = GFFormsModel::$uploaded_files[ $form_id ];
			$file_info      = rgar( $uploaded_files, $input_name );

			return empty( $file_info );
		} else {
			$file_info = GFFormsModel::get_temp_filename( $form_id, $input_name );

			return ! $file_info && empty( $_FILES[ $input_name ]['name'] );
		}
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		return $this->multipleFiles ? $this->get_multifile_value( $form['id'], $input_name, $value ) : $this->get_single_file_value( $form['id'], $input_name );
	}

	public function get_multifile_value( $form_id, $input_name, $value ) {
		global $_gf_uploaded_files;

		GFCommon::log_debug( __METHOD__ . '(): Starting.' );

		if ( isset( $_gf_uploaded_files[ $input_name ] ) ) {
			$value = $_gf_uploaded_files[ $input_name ];
		} else {
			if ( isset( GFFormsModel::$uploaded_files[ $form_id ][ $input_name ] ) ) {
				$uploaded_temp_files = GFFormsModel::$uploaded_files[ $form_id ][ $input_name ];
				$uploaded_files      = array();
				foreach ( $uploaded_temp_files as $i => $file_info ) {
					$temp_filepath = GFFormsModel::get_upload_path( $form_id ) . '/tmp/' . $file_info['temp_filename'];
					if ( $file_info && file_exists( $temp_filepath ) ) {
						$uploaded_files[ $i ] = $this->move_temp_file( $form_id, $file_info );
					}
				}

				if ( ! empty( $value ) ) { // merge with existing files (admin edit entry)
					$value = json_decode( $value, true );
					$value = array_merge( $value, $uploaded_files );
					$value = json_encode( $value );
				} else {
					$value = json_encode( $uploaded_files );
				}
			} else {
				GFCommon::log_debug( __METHOD__ . '(): No files uploaded. Exiting.' );

				$value = '';
			}
			$_gf_uploaded_files[ $input_name ] = $value;
		}

		return $value;
	}

	public function get_single_file_value( $form_id, $input_name ) {
		global $_gf_uploaded_files;

		GFCommon::log_debug( __METHOD__ . '(): Starting.' );

		if ( empty( $_gf_uploaded_files ) ) {
			$_gf_uploaded_files = array();
		}

		if ( ! isset( $_gf_uploaded_files[ $input_name ] ) ) {

			//check if file has already been uploaded by previous step
			$file_info     = GFFormsModel::get_temp_filename( $form_id, $input_name );
			$temp_filepath = GFFormsModel::get_upload_path( $form_id ) . '/tmp/' . $file_info['temp_filename'];

			if ( $file_info && file_exists( $temp_filepath ) ) {
				$_gf_uploaded_files[ $input_name ] = $this->move_temp_file( $form_id, $file_info );
			} else if ( ! empty( $_FILES[ $input_name ]['name'] ) ) {
				$_gf_uploaded_files[ $input_name ] = $this->upload_file( $form_id, $_FILES[ $input_name ] );
			} else {
				GFCommon::log_debug( __METHOD__ . '(): No file uploaded. Exiting.' );
			}
		}

		return rgget( $input_name, $_gf_uploaded_files );
	}

	public function upload_file( $form_id, $file ) {
		GFCommon::log_debug( __METHOD__ . '(): Uploading file: ' . $file['name'] );

		$target = GFFormsModel::get_file_upload_path( $form_id, $file['name'] );
		if ( ! $target ) {
			GFCommon::log_debug( __METHOD__ . '(): FAILED (Upload folder could not be created.)' );

			return 'FAILED (Upload folder could not be created.)';
		}

		if ( move_uploaded_file( $file['tmp_name'], $target['path'] ) ) {
			GFCommon::log_debug( __METHOD__ . '(): File successfully moved.' );
			$this->set_permissions( $target['path'] );

			return $target['url'];
		} else {
			GFCommon::log_debug( __METHOD__ . '(): FAILED (Temporary file could not be copied.)' );

			return 'FAILED (Temporary file could not be copied.)';
		}
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		if ( $this->multipleFiles ) {
			$uploaded_files_arr = empty( $value ) ? array() : json_decode( $value, true );
			$file_count         = count( $uploaded_files_arr );
			if ( $file_count > 1 ) {
				$value = empty( $uploaded_files_arr ) ? '' : sprintf( esc_html__( '%d files', 'gravityforms' ), count( $uploaded_files_arr ) );
				return $value;
			} elseif ( $file_count == 1 ) {
				$value = $uploaded_files_arr[0];
			} elseif ( $file_count == 0 ) {
				return;
			}
		}

		$file_path = $value;
		if ( ! empty( $file_path ) ) {
			//displaying thumbnail (if file is an image) or an icon based on the extension
			$thumb     = GFEntryList::get_icon_url( $file_path );
			$file_path = esc_attr( $file_path );
			$value     = "<a href='$file_path' target='_blank' title='" . esc_attr__( 'Click to view', 'gravityforms' ) . "'><img src='$thumb'/></a>";
		}
		return $value;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		$output = '';
		if ( ! empty( $value ) ) {
			$output_arr = array();
			$file_paths = $this->multipleFiles ? json_decode( $value ) : array( $value );
			foreach ( $file_paths as $file_path ) {
				$info = pathinfo( $file_path );
				if ( GFCommon::is_ssl() && strpos( $file_path, 'http:' ) !== false ) {
					$file_path = str_replace( 'http:', 'https:', $file_path );
				}
				$file_path    = esc_attr( str_replace( ' ', '%20', $file_path ) );
				$base_name = $info['basename'];
				$click_to_view_text = esc_attr__( 'Click to view', 'gravityforms' );
				$output_arr[] = $format == 'text' ? $file_path . PHP_EOL : "<li><a href='{$file_path}' target='_blank' title='{$click_to_view_text}'>{$base_name}</a></li>";
			}
			$output = join( PHP_EOL, $output_arr );
		}
		$output = empty( $output ) || $format == 'text' ? $output : sprintf( '<ul>%s</ul>', $output );

		return $output;
	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {

		if ( $this->multipleFiles ) {

			$files = empty( $raw_value ) ? array() : json_decode( $raw_value, true );
			foreach ( $files as &$file ) {

				$file = str_replace( ' ', '%20', $file );

				if ( $esc_html ) {
					$value = esc_html( $value );
				}
			}
			$value = $format == 'html' ? join( '<br />', $files ) : join( ', ', $files );

		} else {
			$value = str_replace( ' ', '%20', $value );
		}

		if ( $url_encode ) {
			$value = urlencode( $value );
		}


		return $value;
	}


	public function move_temp_file( $form_id, $tempfile_info ) {

		$target = GFFormsModel::get_file_upload_path( $form_id, $tempfile_info['uploaded_filename'] );
		$source = GFFormsModel::get_upload_path( $form_id ) . '/tmp/' . $tempfile_info['temp_filename'];

		GFCommon::log_debug( __METHOD__ . '(): Moving temp file from: ' . $source );

		if ( rename( $source, $target['path'] ) ) {
			GFCommon::log_debug( __METHOD__ . '(): File successfully moved.' );
			$this->set_permissions( $target['path'] );

			return $target['url'];
		} else {
			GFCommon::log_debug( __METHOD__ . '(): FAILED (Temporary file could not be moved.)' );

			return 'FAILED (Temporary file could not be moved.)';
		}
	}

	function set_permissions( $path ) {
		GFCommon::log_debug( __METHOD__ . '(): Setting permissions on: ' . $path );

		GFFormsModel::set_permissions( $path );
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		if ( $this->maxFileSize ) {
			$this->maxFileSize = absint( $this->maxFileSize );
		}

		if ( $this->maxFiles ) {
			$this->maxFiles = preg_replace( '/[^0-9,.]/', '', $this->maxFiles );
		}

		$this->multipleFiles = (bool) $this->multipleFiles;

		$this->allowedExtensions = sanitize_text_field( $this->allowedExtensions );

	}

	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		$value = rgar( $entry, $input_id );
		if ( $this->multipleFiles && ! empty( $value ) ) {
			return implode( ' , ', json_decode( $value, true ) );
		}

		return $value;
	}

}

GF_Fields::register( new GF_Field_FileUpload() );