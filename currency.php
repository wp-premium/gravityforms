<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

if ( ! class_exists( 'RGCurrency' ) ) {

	class RGCurrency {
		private $currency;

		public function __construct( $currency ) {
			if ( is_array( $currency ) ) {
				$this->currency = $currency;
			} else {
				$this->currency = self::get_currency( $currency );
			}
		}

		public function to_number( $text ) {
			$text = strval( $text );

			if ( is_numeric( $text ) ) {
				return floatval( $text );
			}

			//Making sure symbol is in unicode format (i.e. &#4444;)
			$text = preg_replace( '/&.*?;/', '', $text );

			//Removing symbol from text
			$text = str_replace( $this->currency['symbol_right'], '', $text );
			$text = str_replace( $this->currency['symbol_left'], '', $text );
			if ( ! empty( $this->currency['symbol_old'] ) ) {
				$text = str_replace( $this->currency['symbol_old'], '', $text );
			}

			//Removing all non-numeric characters
			$array        = str_split( $text );
			$is_negative  = false;
			$clean_number = '';
			foreach ( $array as $char ) {

				if ( ( $char >= '0' && $char <= '9' ) || $char == $this->currency['decimal_separator'] ) {
					$clean_number .= $char;
				} else if ( $char == '-' ) {
					$is_negative = true;
				}
			}

			$decimal_separator = $this->currency && $this->currency['decimal_separator'] ? $this->currency['decimal_separator'] : '.';

			//Removing thousand separators but keeping decimal point
			$array        = str_split( $clean_number );
			$float_number = '';
			for ( $i = 0, $count = sizeof( $array ); $i < $count; $i ++ ) {
				$char = $array[ $i ];

				if ( $char >= '0' && $char <= '9' ) {
					$float_number .= $char;
				} else if ( $char == $decimal_separator ) {
					$float_number .= '.';
				}
			}

			if ( $is_negative ) {
				$float_number = '-' . $float_number;
			}

			return is_numeric( $float_number ) ? floatval( $float_number ) : false;
		}

		public function to_money( $number, $do_encode = false ) {

			if ( ! is_numeric( $number ) ) {
				$number = $this->to_number( $number );
			}

			if ( $number === false ) {
				return '';
			}

			$negative = '';
			if ( strpos( strval( $number ), '-' ) !== false ) {
				$negative = '-';
				$number   = floatval( substr( $number, 1 ) );
			}

			$money = number_format( $number, $this->currency['decimals'], $this->currency['decimal_separator'], $this->currency['thousand_separator'] );

			if ( $money == '0.00' ){
				$negative = '';
			}

			$symbol_left  = ! empty( $this->currency['symbol_left'] ) ? $this->currency['symbol_left'] . $this->currency['symbol_padding'] : '';
			$symbol_right = ! empty( $this->currency['symbol_right'] ) ? $this->currency['symbol_padding'] . $this->currency['symbol_right'] : '';

			if ( $do_encode ) {
				$symbol_left  = html_entity_decode( $symbol_left );
				$symbol_right = html_entity_decode( $symbol_right );
			}

			return $negative . $symbol_left . $money . $symbol_right;
		}

		public static function get_currency( $code ) {
			$currencies = self::get_currencies();

			return $currencies[ $code ];
		}

		public function is_zero_decimal() {

			return empty( $this->currency['decimals'] );
		}

		public static function get_currencies() {
			$currencies = array(
				'USD' => array( 'name' => esc_html__( 'U.S. Dollar', 'gravityforms' ), 'symbol_left' => '$', 'symbol_right' => '', 'symbol_padding' => '', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'GBP' => array( 'name' => esc_html__( 'Pound Sterling', 'gravityforms' ), 'symbol_left' => '&#163;', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'EUR' => array( 'name' => esc_html__( 'Euro', 'gravityforms' ), 'symbol_left' => '', 'symbol_right' => '&#8364;', 'symbol_padding' => ' ', 'thousand_separator' => '.', 'decimal_separator' => ',', 'decimals' => 2 ),
				'AUD' => array( 'name' => esc_html__( 'Australian Dollar', 'gravityforms' ), 'symbol_left' => '$', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'BRL' => array( 'name' => esc_html__( 'Brazilian Real', 'gravityforms' ), 'symbol_left' => 'R$', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => '.', 'decimal_separator' => ',', 'decimals' => 2 ),
				'CAD' => array( 'name' => esc_html__( 'Canadian Dollar', 'gravityforms' ), 'symbol_left' => '$', 'symbol_right' => 'CAD', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'CZK' => array( 'name' => esc_html__( 'Czech Koruna', 'gravityforms' ), 'symbol_left' => '', 'symbol_right' => '&#75;&#269;', 'symbol_padding' => ' ', 'thousand_separator' => ' ', 'decimal_separator' => ',', 'decimals' => 2 ),
				'DKK' => array( 'name' => esc_html__( 'Danish Krone', 'gravityforms' ), 'symbol_left' => '', 'symbol_right' => 'kr.', 'symbol_padding' => ' ', 'thousand_separator' => '.', 'decimal_separator' => ',', 'decimals' => 2 ),
				'HKD' => array( 'name' => esc_html__( 'Hong Kong Dollar', 'gravityforms' ), 'symbol_left' => 'HK$', 'symbol_right' => '', 'symbol_padding' => '', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'HUF' => array( 'name' => esc_html__( 'Hungarian Forint', 'gravityforms' ), 'symbol_left' => '', 'symbol_right' => 'Ft', 'symbol_padding' => ' ', 'thousand_separator' => '.', 'decimal_separator' => ',', 'decimals' => 2 ),
				'ILS' => array( 'name' => esc_html__( 'Israeli New Sheqel', 'gravityforms' ), 'symbol_left' => '&#8362;', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'JPY' => array( 'name' => esc_html__( 'Japanese Yen', 'gravityforms' ), 'symbol_left' => '&#165;', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '', 'decimals' => 0 ),
				'MYR' => array( 'name' => esc_html__( 'Malaysian Ringgit', 'gravityforms' ), 'symbol_left' => '&#82;&#77;', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'MXN' => array( 'name' => esc_html__( 'Mexican Peso', 'gravityforms' ), 'symbol_left' => '$', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'NOK' => array( 'name' => esc_html__( 'Norwegian Krone', 'gravityforms' ), 'symbol_left' => 'Kr', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => '.', 'decimal_separator' => ',', 'decimals' => 2 ),
				'NZD' => array( 'name' => esc_html__( 'New Zealand Dollar', 'gravityforms' ), 'symbol_left' => '$', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'PHP' => array( 'name' => esc_html__( 'Philippine Peso', 'gravityforms' ), 'symbol_left' => 'Php', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'PLN' => array( 'name' => esc_html__( 'Polish Zloty', 'gravityforms' ), 'symbol_left' => '&#122;&#322;', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => '.', 'decimal_separator' => ',', 'decimals' => 2 ),
				'RUB' => array( 'name' => esc_html__( 'Russian Ruble', 'gravityforms' ), 'symbol_left' => '', 'symbol_right' => 'pyб', 'symbol_padding' => ' ', 'thousand_separator' => ' ', 'decimal_separator' => '.', 'decimals' => 2 ),
				'SGD' => array( 'name' => esc_html__( 'Singapore Dollar', 'gravityforms' ), 'symbol_left' => '$', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'ZAR' => array( 'name' => esc_html__( 'South African Rand', 'gravityforms' ), 'symbol_left' => 'R', 'symbol_right' => '', 'symbol_padding' => '', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'SEK' => array( 'name' => esc_html__( 'Swedish Krona', 'gravityforms' ), 'symbol_left' => '', 'symbol_right' => 'Kr', 'symbol_padding' => ' ', 'thousand_separator' => ' ', 'decimal_separator' => ',', 'decimals' => 2 ),
				'CHF' => array( 'name' => esc_html__( 'Swiss Franc', 'gravityforms' ), 'symbol_left' => 'CHF', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => "'", 'decimal_separator' => '.', 'decimals' => 2, 'symbol_old' => 'Fr.' ),
				'TWD' => array( 'name' => esc_html__( 'Taiwan New Dollar', 'gravityforms' ), 'symbol_left' => '$', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
				'THB' => array( 'name' => esc_html__( 'Thai Baht', 'gravityforms' ), 'symbol_left' => '&#3647;', 'symbol_right' => '', 'symbol_padding' => ' ', 'thousand_separator' => ',', 'decimal_separator' => '.', 'decimals' => 2 ),
			);

			return apply_filters( 'gform_currencies', $currencies );
		}
	}

}
