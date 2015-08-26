<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Post_Image extends GF_Field_Fileupload {

	public $type = 'post_image';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Post Image', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'post_image_setting',
			'rules_setting',
			'description_setting',
			'css_class_setting',
			'post_image_featured_image',
		);
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin = $is_entry_detail || $is_form_editor;

		$id       = (int) $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$value        = esc_attr( $value );
		$size         = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class        = $size . $class_suffix;

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$title       = esc_attr( rgget( $this->id . '.1', $value ) );
		$caption     = esc_attr( rgget( $this->id . '.4', $value ) );
		$description = esc_attr( rgget( $this->id . '.7', $value ) );

		//hidding meta fields for admin
		$hidden_style      = "style='display:none;'";
		$title_style       = ! $this->displayTitle && $is_admin ? $hidden_style : '';
		$caption_style     = ! $this->displayCaption && $is_admin ? $hidden_style : '';
		$description_style = ! $this->displayDescription && $is_admin ? $hidden_style : '';
		$file_label_style  = $is_admin && ! ( $this->displayTitle || $this->displayCaption || $this->displayDescription ) ? $hidden_style : '';

		$hidden_class = $preview = '';
		$file_info    = RGFormsModel::get_temp_filename( $form_id, "input_{$id}" );
		if ( $file_info ) {
			$hidden_class     = ' gform_hidden';
			$file_label_style = $hidden_style;
			$preview          = "<span class='ginput_preview'><strong>" . esc_html( $file_info['uploaded_filename'] ) . "</strong> | <a href='javascript:;' onclick='gformDeleteUploadedFile({$form_id}, {$id});'>" . __( 'delete', 'gravityforms' ) . '</a></span>';
		}

		//in admin, render all meta fields to allow for immediate feedback, but hide the ones not selected
		$file_label = ( $is_admin || $this->displayTitle || $this->displayCaption || $this->displayDescription ) ? "<label for='$field_id' class='ginput_post_image_file' $file_label_style>" . gf_apply_filters( 'gform_postimage_file', $form_id, __( 'File', 'gravityforms' ), $form_id ) . '</label>' : '';

		$tabindex = $this->get_tabindex();

		$upload = sprintf( "<span class='ginput_full$class_suffix'>{$preview}<input name='input_%d' id='%s' type='file' value='%s' class='%s' $tabindex %s/>$file_label</span>", $id, $field_id, esc_attr( $value ), esc_attr( $class . $hidden_class ), $disabled_text );

		$tabindex = $this->get_tabindex();

		$title_field = $this->displayTitle || $is_admin ? sprintf( "<span class='ginput_full$class_suffix ginput_post_image_title' $title_style><input type='text' name='input_%d.1' id='%s_1' value='%s' $tabindex %s/><label for='%s_1'>" . gf_apply_filters( 'gform_postimage_title', $form_id, __( 'Title', 'gravityforms' ), $form_id ) . '</label></span>', $id, $field_id, $title, $disabled_text, $field_id ) : '';

		$tabindex = $this->get_tabindex();

		$caption_field = $this->displayCaption || $is_admin ? sprintf( "<span class='ginput_full$class_suffix ginput_post_image_caption' $caption_style><input type='text' name='input_%d.4' id='%s_4' value='%s' $tabindex %s/><label for='%s_4'>" . gf_apply_filters( 'gform_postimage_caption', $form_id, __( 'Caption', 'gravityforms' ), $form_id ) . '</label></span>', $id, $field_id, $caption, $disabled_text, $field_id ) : '';

		$tabindex = $this->get_tabindex();

		$description_field = $this->displayDescription || $is_admin ? sprintf( "<span class='ginput_full$class_suffix ginput_post_image_description' $description_style><input type='text' name='input_%d.7' id='%s_7' value='%s' $tabindex %s/><label for='%s_7'>" . gf_apply_filters( 'gform_postimage_description', $form_id, __( 'Description', 'gravityforms' ), $form_id ) . '</label></span>', $id, $field_id, $description, $disabled_text, $field_id ) : '';

		return "<div class='ginput_complex$class_suffix ginput_container'>" . $upload . $title_field . $caption_field . $description_field . '</div>';
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		$form_id           = $form['id'];
		$url               = $this->get_single_file_value( $form_id, $input_name );
		$image_title       = isset( $_POST["{$input_name}_1"] ) ? strip_tags( $_POST["{$input_name}_1"] ) : '';
		$image_caption     = isset( $_POST["{$input_name}_4"] ) ? strip_tags( $_POST["{$input_name}_4"] ) : '';
		$image_description = isset( $_POST["{$input_name}_7"] ) ? strip_tags( $_POST["{$input_name}_7"] ) : '';

		$value = ! empty( $url ) ? $url . "|:|" . $image_title . "|:|" . $image_caption . "|:|" . $image_description : '';

		Return $value;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		list( $url, $title, $caption, $description ) = rgexplode( '|:|', $value, 4 );
		if ( ! empty( $url ) ) {
			//displaying thumbnail (if file is an image) or an icon based on the extension
			$thumb = GFEntryList::get_icon_url( $url );
			$value = "<a href='" . esc_attr( $url ) . "' target='_blank' title='" . __( 'Click to view', 'gravityforms' ) . "'><img src='$thumb'/></a>";
		}
		return $value;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		$ary         = explode( "|:|", $value );
		$url         = count( $ary ) > 0 ? $ary[0] : '';
		$title       = count( $ary ) > 1 ? $ary[1] : '';
		$caption     = count( $ary ) > 2 ? $ary[2] : '';
		$description = count( $ary ) > 3 ? $ary[3] : '';

		if ( ! empty( $url ) ) {
			$url = str_replace( ' ', '%20', $url );

			switch ( $format ) {
				case 'text' :
					$value = $url;
					$value .= ! empty( $title ) ? "\n\n" . $this->label . ' (' . __( 'Title', 'gravityforms' ) . '): ' . $title : '';
					$value .= ! empty( $caption ) ? "\n\n" . $this->label . ' (' . __( 'Caption', 'gravityforms' ) . '): ' . $caption : '';
					$value .= ! empty( $description ) ? "\n\n" . $this->label . ' (' . __( 'Description', 'gravityforms' ) . '): ' . $description : '';
					break;

				default :
					$value = "<a href='$url' target='_blank' title='" . __( 'Click to view', 'gravityforms' ) . "'><img src='$url' width='100' /></a>";
					$value .= ! empty( $title ) ? "<div>Title: $title</div>" : '';
					$value .= ! empty( $caption ) ? "<div>Caption: $caption</div>" : '';
					$value .= ! empty( $description ) ? "<div>Description: $description</div>" : '';

					break;
			}
		}

		return $value;
	}

	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$value[$this->id . '.1'] = $this->get_input_value_submission( 'input_' . $this->id . '_1', $get_from_post_global_var );
		$value[$this->id . '.4'] = $this->get_input_value_submission( 'input_' . $this->id . '_4', $get_from_post_global_var );
		$value[$this->id . '.7'] = $this->get_input_value_submission( 'input_' . $this->id . '_7', $get_from_post_global_var );

		return $value;
	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		list( $url, $title, $caption, $description ) = array_pad( explode( '|:|', $value ), 4, false );
		switch ( $modifier ) {
			case 'title' :
				return $title;

			case 'caption' :
				return $caption;

			case 'description' :
				return $description;

			default :
				return str_replace( ' ', '%20', $url );
		}
	}

}

GF_Fields::register( new GF_Field_Post_Image() );