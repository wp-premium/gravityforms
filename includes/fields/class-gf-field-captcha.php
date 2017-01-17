<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_CAPTCHA extends GF_Field {

	public $type = 'captcha';

	public function get_form_editor_field_title() {
		return esc_attr__( 'CAPTCHA', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'captcha_type_setting',
			'captcha_size_setting',
			'captcha_fg_setting',
			'captcha_bg_setting',
			'captcha_language_setting',
			'captcha_theme_setting',
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function validate( $value, $form ) {
		switch ( $this->captchaType ) {
			case 'simple_captcha' :
				if ( class_exists( 'ReallySimpleCaptcha' ) ) {
					$prefix      = $_POST[ "input_captcha_prefix_{$this->id}" ];
					$captcha_obj = $this->get_simple_captcha();

					if ( ! $captcha_obj->check( $prefix, str_replace( ' ', '', $value ) ) ) {
						$this->failed_validation  = true;
						$this->validation_message = empty( $this->errorMessage ) ? esc_html__( "The CAPTCHA wasn't entered correctly. Go back and try it again.", 'gravityforms' ) : $this->errorMessage;
					}

					//removes old files in captcha folder (older than 1 hour);
					$captcha_obj->cleanup();
				}
				break;

			case 'math' :
				$prefixes    = explode( ',', $_POST[ "input_captcha_prefix_{$this->id}" ] );
				$captcha_obj = $this->get_simple_captcha();

				//finding first number
				for ( $first = 0; $first < 10; $first ++ ) {
					if ( $captcha_obj->check( $prefixes[0], $first ) ) {
						break;
					}
				}

				//finding second number
				for ( $second = 0; $second < 10; $second ++ ) {
					if ( $captcha_obj->check( $prefixes[2], $second ) ) {
						break;
					}
				}

				//if it is a +, perform the sum
				if ( $captcha_obj->check( $prefixes[1], '+' ) ) {
					$result = $first + $second;
				} else {
					$result = $first - $second;
				}



				if ( intval( $result ) != intval( $value ) ) {
					$this->failed_validation  = true;
					$this->validation_message = empty( $this->errorMessage ) ? esc_html__( "The CAPTCHA wasn't entered correctly. Go back and try it again.", 'gravityforms' ) : $this->errorMessage;
				}

				//removes old files in captcha folder (older than 1 hour);
				$captcha_obj->cleanup();

				break;

			default:
				$this->validate_recaptcha();
		}

	}

	public function validate_recaptcha() {

		// when user clicks on the "I'm not a robot" box, the response token is populated into a hidden field by Google, get token from POST
		$response_token = rgpost( 'g-recaptcha-response' );
		$is_valid       = $this->verify_recaptcha_response( $response_token );

		if ( ! $is_valid ) {

			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage ) ? __( 'The reCAPTCHA was invalid. Go back and try it again.', 'gravityforms' ) : $this->errorMessage;

		}

	}

	public function verify_recaptcha_response( $response, $secret_key = null ) {

		$verify_url = 'https://www.google.com/recaptcha/api/siteverify';

		if ( $secret_key == null ) {
			$secret_key = get_option( 'rg_gforms_captcha_private_key' );
		}

		// pass secret key and token for verification of whether the response was valid
		$response = wp_remote_post( $verify_url, array(
			'method' => 'POST',
			'body'   => array(
				'secret'   => $secret_key,
				'response' => $response
			),
		) );

		if ( ! is_wp_error( $response ) ) {
			$result = json_decode( wp_remote_retrieve_body( $response ) );

			return $result->success == true;
		} else {
			GFCommon::log_debug( __METHOD__ . '(): Validating the reCAPTCHA response has failed due to the following: ' . $response->get_error_message() );
		}

		return false;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = (int) $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		switch ( $this->captchaType ) {
			case 'simple_captcha' :
				$size    = empty($this->simpleCaptchaSize) ? 'medium' : esc_attr( $this->simpleCaptchaSize );
				$captcha = $this->get_captcha();

				$tabindex = $this->get_tabindex();

				$dimensions = $is_entry_detail || $is_form_editor ? '' : "width='" . esc_attr( rgar( $captcha, 'width' ) ) . "' height='" . esc_attr( rgar( $captcha, 'height' ) ) . "'";

				return "<div class='gfield_captcha_container'><img class='gfield_captcha' src='" . esc_url( rgar( $captcha, 'url' ) ) . "' alt='' {$dimensions} /><div class='gfield_captcha_input_container simple_captcha_{$size}'><input type='text' name='input_{$id}' id='{$field_id}' {$tabindex}/><input type='hidden' name='input_captcha_prefix_{$id}' value='" . esc_attr( rgar( $captcha, 'prefix' ) ) . "' /></div></div>";
				break;

			case 'math' :
				$size      = empty( $this->simpleCaptchaSize ) ? 'medium' : esc_attr( $this->simpleCaptchaSize );
				$captcha_1 = $this->get_math_captcha( 1 );
				$captcha_2 = $this->get_math_captcha( 2 );
				$captcha_3 = $this->get_math_captcha( 3 );

				$tabindex = $this->get_tabindex();

				$dimensions   = $is_entry_detail || $is_form_editor ? '' : "width='" . esc_attr( rgar( $captcha_1, 'width' ) ) . "' height='" . esc_attr( rgar( $captcha_1, 'height' ) ) . "'";
				$prefix_value = rgar( $captcha_1, 'prefix' ) . ',' . rgar( $captcha_2, 'prefix' ) . ',' . rgar( $captcha_3, 'prefix' );

				return "<div class='gfield_captcha_container'><img class='gfield_captcha' src='" . esc_url( rgar( $captcha_1, 'url' ) ) . "' alt='' {$dimensions} /><img class='gfield_captcha' src='" . esc_url( rgar( $captcha_2, 'url' ) ) . "' alt='' {$dimensions} /><img class='gfield_captcha' src='" . esc_url( rgar( $captcha_3, 'url' ) ) . "' alt='' {$dimensions} /><div class='gfield_captcha_input_container math_{$size}'><input type='text' name='input_{$id}' id='{$field_id}' {$tabindex}/><input type='hidden' name='input_captcha_prefix_{$id}' value='" . esc_attr( $prefix_value ) . "' /></div></div>";
				break;

			default:

				$site_key   = get_option( 'rg_gforms_captcha_public_key' );
				$secret_key = get_option( 'rg_gforms_captcha_private_key' );
				$theme      = in_array( $this->captchaTheme, array( 'blackglass', 'dark' ) ) ? 'dark' : 'light';

				if ( $is_entry_detail || $is_form_editor ){

					//for admin, show a thumbnail depending on chosen theme
					if ( empty( $site_key ) || empty( $secret_key ) ) {

						return "<div class='captcha_message'>" . __( 'To use the reCAPTCHA field you must do the following:', 'gravityforms' ) . "</div><div class='captcha_message'>1 - <a href='https://www.google.com/recaptcha/admin' target='_blank'>" . sprintf( __( 'Sign up%s for an API key pair for your site.', 'gravityforms' ), '</a>' ) . "</div><div class='captcha_message'>2 - " . sprintf( __( 'Enter your reCAPTCHA site and secret keys in the reCAPTCHA Settings section of the %sSettings page%s', 'gravityforms' ), "<a href='?page=gf_settings' target='_blank'>", '</a>' ) . '</div>';

					} else {

						return "<div class='ginput_container'><img class='gfield_captcha' src='" . GFCommon::get_base_url() . "/images/captcha_$theme.jpg' alt='reCAPTCHA' title='reCAPTCHA'/></div>";
					}
				}
				else {

					$secure_token = self::create_recaptcha_secure_token( $secret_key );
					$language     = empty( $this->captchaLanguage ) ? 'en' : $this->captchaLanguage;

					// script is queued for the footer with the language property specified
					wp_enqueue_script( 'gform_recaptcha', 'https://www.google.com/recaptcha/api.js?hl=' . $language . '&render=explicit', array(), false, true );

					add_action( 'wp_footer', array( $this, 'ensure_recaptcha_js' ) );
					add_action( 'gform_preview_footer', array( $this, 'ensure_recaptcha_js' ) );

					$stoken = $this->use_stoken() ? sprintf( 'data-stoken=\'%s\'', esc_attr( $secure_token ) ) : '';
					$output = "<div id='" . esc_attr( $field_id ) ."' class='ginput_container ginput_recaptcha' data-sitekey='" . esc_attr( $site_key ) . "' {$stoken} data-theme='" . esc_attr( $theme ) . "' ></div>";

					return $output;
				}
		}
	}

	public function ensure_recaptcha_js(){
		?>
		<script type="text/javascript">
			var gfRecaptchaPoller = setInterval( function() {
				if( ! window.grecaptcha ) {
					return;
				}
				renderRecaptcha();
				clearInterval( gfRecaptchaPoller );
			}, 100 );
		</script>

		<?php
	}

	public function get_captcha() {
		if ( ! class_exists( 'ReallySimpleCaptcha' ) ) {
			return array();
		}

		$captcha = $this->get_simple_captcha();

		//If captcha folder does not exist and can't be created, return an empty captcha
		if ( ! wp_mkdir_p( $captcha->tmp_dir ) ) {
			return array();
		}

		$captcha->char_length = 5;
		switch ( $this->simpleCaptchaSize ) {
			case 'small' :
				$captcha->img_size        = array( 100, 28 );
				$captcha->font_size       = 18;
				$captcha->base            = array( 8, 20 );
				$captcha->font_char_width = 17;

				break;

			case 'large' :
				$captcha->img_size        = array( 200, 56 );
				$captcha->font_size       = 32;
				$captcha->base            = array( 18, 42 );
				$captcha->font_char_width = 35;
				break;

			default :
				$captcha->img_size        = array( 150, 42 );
				$captcha->font_size       = 26;
				$captcha->base            = array( 15, 32 );
				$captcha->font_char_width = 25;
				break;
		}

		if ( ! empty( $this->simpleCaptchaFontColor ) ) {
			$captcha->fg = $this->hex2rgb( $this->simpleCaptchaFontColor );
		}
		if ( ! empty( $this->simpleCaptchaBackgroundColor ) ) {
			$captcha->bg = $this->hex2rgb( $this->simpleCaptchaBackgroundColor );
		}

		$word     = $captcha->generate_random_word();
		$prefix   = mt_rand();
		$filename = $captcha->generate_image( $prefix, $word );
		$url      = RGFormsModel::get_upload_url( 'captcha' ) . '/' . $filename;
		$path     = $captcha->tmp_dir . $filename;

		if ( GFCommon::is_ssl() && strpos( $url, 'http:' ) !== false ) {
			$url = str_replace( 'http:', 'https:', $url );
		}

		return array( 'path' => $path, 'url' => $url, 'height' => $captcha->img_size[1], 'width' => $captcha->img_size[0], 'prefix' => $prefix );
	}

	public function get_simple_captcha() {
		$captcha          = new ReallySimpleCaptcha();
		$captcha->tmp_dir = RGFormsModel::get_upload_path( 'captcha' ) . '/';

		return $captcha;
	}

	public function get_math_captcha( $pos ) {
		if ( ! class_exists( 'ReallySimpleCaptcha' ) ) {
			return array();
		}

		$captcha = $this->get_simple_captcha();

		//If captcha folder does not exist and can't be created, return an empty captcha
		if ( ! wp_mkdir_p( $captcha->tmp_dir ) ) {
			return array();
		}

		$captcha->char_length = 1;
		if ( $pos == 1 || $pos == 3 ) {
			$captcha->chars = '0123456789';
		} else {
			$captcha->chars = '+';
		}

		switch ( $this->simpleCaptchaSize ) {
			case 'small' :
				$captcha->img_size        = array( 23, 28 );
				$captcha->font_size       = 18;
				$captcha->base            = array( 6, 20 );
				$captcha->font_char_width = 17;

				break;

			case 'large' :
				$captcha->img_size        = array( 36, 56 );
				$captcha->font_size       = 32;
				$captcha->base            = array( 10, 42 );
				$captcha->font_char_width = 35;
				break;

			default :
				$captcha->img_size        = array( 30, 42 );
				$captcha->font_size       = 26;
				$captcha->base            = array( 9, 32 );
				$captcha->font_char_width = 25;
				break;
		}

		if ( ! empty( $this->simpleCaptchaFontColor ) ) {
			$captcha->fg = $this->hex2rgb( $this->simpleCaptchaFontColor );
		}
		if ( ! empty( $this->simpleCaptchaBackgroundColor ) ) {
			$captcha->bg = $this->hex2rgb( $this->simpleCaptchaBackgroundColor );
		}

		$word     = $captcha->generate_random_word();
		$prefix   = mt_rand();
		$filename = $captcha->generate_image( $prefix, $word );
		$url      = RGFormsModel::get_upload_url( 'captcha' ) . '/' . $filename;
		$path     = $captcha->tmp_dir . $filename;

		if ( GFCommon::is_ssl() && strpos( $url, 'http:' ) !== false ) {
			$url = str_replace( 'http:', 'https:', $url );
		}

		return array( 'path' => $path, 'url' => $url, 'height' => $captcha->img_size[1], 'width' => $captcha->img_size[0], 'prefix' => $prefix );
	}

	private function hex2rgb( $color ) {
		if ( $color[0] == '#' ) {
			$color = substr( $color, 1 );
		}

		if ( strlen( $color ) == 6 ) {
			list( $r, $g, $b ) = array(
				$color[0] . $color[1],
				$color[2] . $color[3],
				$color[4] . $color[5],
			);
		} elseif ( strlen( $color ) == 3 ) {
			list( $r, $g, $b ) = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
		} else {
			return false;
		}

		$r = hexdec( $r );
		$g = hexdec( $g );
		$b = hexdec( $b );

		return array( $r, $g, $b );
	}

	public function create_recaptcha_secure_token( $secret_key ) {

		$secret_key = substr( hash( 'sha1', $secret_key, true ), 0, 16 );
		$session_id = uniqid( 'recaptcha' );
		$ts_ms      = round( ( microtime( true ) - 1 ) * 1000 );

		//create json string
		$params    = array( 'session_id' => $session_id, 'ts_ms' => $ts_ms );
		$plaintext = json_encode( $params );
		GFCommon::log_debug( 'recaptcha token parameters: ' . $plaintext );

		//pad json string
		$pad    = 16 - ( strlen( $plaintext ) % 16 );
		$padded = $plaintext . str_repeat( chr( $pad ), $pad );

		//encrypt as 128
		$cypher = defined( 'MCRYPT_RIJNDAEL_128' ) ? MCRYPT_RIJNDAEL_128 : false;
		$encrypted = GFCommon::encrypt( $padded, $secret_key, $cypher );

		$token = str_replace( array( '+', '/', '=' ), array( '-', '_', '' ), $encrypted );
		GFCommon::log_debug( ' token being used is: ' . $token );

		return $token;
	}

	public function use_stoken() {
		// 'gform_recaptcha_keys_status' will be set to true if new keys have been entered
		return ! get_option( 'gform_recaptcha_keys_status', false );
	}

}

GF_Fields::register( new GF_Field_CAPTCHA() );