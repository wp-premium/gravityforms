<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Specialist Add-On class designed for use by Add-Ons that collect payment
 *
 * @package GFPaymentAddOn
 *
 * NOTE: This class is still undergoing development and is not ready to be used on live sites.
 */

require_once( 'class-gf-feed-addon.php' );

abstract class GFPaymentAddOn extends GFFeedAddOn {

	private $_payment_version = '1.2';

	/**
	 * If set to true, user will not be able to create feeds for a form until a credit card field has been added.
	 * @var bool
	 */
	protected $_requires_credit_card = false;

	/**
	 * If set to true, callbacks/webhooks/IPN will be enabled and the appropriate database table will be created.
	 * @var bool
	 */
	protected $_supports_callbacks = false;

	protected $authorization = array();
	protected $redirect_url = '';

	protected $current_feed = false;
	protected $current_submission_data = false;
	protected $is_payment_gateway = false;

	protected $_single_feed_submission = true;

	/**
	 * Indicates if the payment gateway requires monetary amounts to be formatted as the smallest unit for the currency being used e.g. cents.
	 *
	 * @var bool
	 */
	protected $_requires_smallest_unit = false;

	//--------- Initialization ----------
	public function pre_init() {
		parent::pre_init();

		// Intercepting callback requests
		add_action( 'parse_request', array( $this, 'maybe_process_callback' ) );

		if ( $this->payment_method_is_overridden( 'check_status' ) ) {
			$this->setup_cron();
		}

	}

	public function init() {

		parent::init();

		add_filter( 'gform_confirmation', array( $this, 'confirmation' ), 20, 4 );

		add_filter( 'gform_validation', array( $this, 'maybe_validate' ), 20 );
		add_filter( 'gform_entry_post_save', array( $this, 'entry_post_save' ), 10, 2 );

	}

	public function init_admin() {

		parent::init_admin();

		//enables credit card field
		add_filter( 'gform_enable_credit_card_field', '__return_true' );

		add_filter( 'gform_currencies', array( $this, 'supported_currencies' ) );

		add_filter( 'gform_delete_lead', array( $this, 'entry_deleted' ) );


		if ( rgget( 'page' ) == 'gf_entries' ) {
			add_action( 'gform_payment_details', array( $this, 'entry_info' ), 10, 2 );
			add_filter( 'gform_enable_entry_info_payment_details', array( $this, 'disable_entry_info_payment' ), 10, 2 );
		}
	}

	public function init_frontend() {

		parent::init_frontend();

		add_filter( 'gform_register_init_scripts', array( $this, 'register_creditcard_token_script' ), 10, 3 );
		add_filter( 'gform_field_content', array( $this, 'add_creditcard_token_input' ), 10, 5 );
		add_filter( 'gform_form_args', array( $this, 'force_ajax_for_creditcard_tokens' ), 10, 1 );

	}

	public function init_ajax() {
		parent::init_ajax();

		add_action( 'wp_ajax_gaddon_cancel_subscription', array( $this, 'ajax_cancel_subscription' ) );
		add_action( 'gform_before_delete_field', array( $this, 'before_delete_field' ), 10, 2 );
	}

	protected function setup() {
		parent::setup();

		$installed_version = get_option( 'gravityformsaddon_payment_version' );

		$installed_addons = get_option( 'gravityformsaddon_payment_addons' );
		if ( ! is_array( $installed_addons ) ) {
			$installed_addons = array();
		}

		if ( $installed_version != $this->_payment_version ) {
			$this->upgrade_payment( $installed_version );

			$installed_addons = array( $this->_slug );
			update_option( 'gravityformsaddon_payment_addons', $installed_addons );
		} elseif ( ! in_array( $this->_slug, $installed_addons ) ) {
			$this->upgrade_payment( $installed_version );

			$installed_addons[] = $this->_slug;
			update_option( 'gravityformsaddon_payment_addons', $installed_addons );
		}

		update_option( 'gravityformsaddon_payment_version', $this->_payment_version );

	}

	private function upgrade_payment( $previous_versions ) {
		global $wpdb;

		$charset_collate = GFFormsModel::get_db_charset();

		$sql = "CREATE TABLE {$wpdb->prefix}gf_addon_payment_transaction (
                  id int(10) unsigned not null auto_increment,
                  lead_id int(10) unsigned not null,
                  transaction_type varchar(30) not null,
                  transaction_id varchar(50),
                  is_recurring tinyint(1) not null default 0,
                  amount decimal(19,2),
                  date_created datetime,
                  PRIMARY KEY  (id),
                  KEY lead_id (lead_id),
                  KEY transaction_type (transaction_type),
                  KEY type_lead (lead_id,transaction_type)
                ) $charset_collate;";

		GFFormsModel::dbDelta( $sql );


		if ( $this->_supports_callbacks ) {
			$sql = "CREATE TABLE {$wpdb->prefix}gf_addon_payment_callback (
                      id int(10) unsigned not null auto_increment,
                      lead_id int(10) unsigned not null,
                      addon_slug varchar(250) not null,
                      callback_id varchar(250),
                      date_created datetime,
                      PRIMARY KEY  (id),
                      KEY addon_slug_callback_id (addon_slug(50),callback_id(100))
                    ) $charset_collate;";

			GFFormsModel::dbDelta( $sql );

			//droping legacy index
			GFForms::drop_index( "{$wpdb->prefix}gf_addon_payment_callback", 'slug_callback_id' );
		}


	}

	//--------- Submission Process ------
	public function confirmation( $confirmation, $form, $entry, $ajax ) {

		if ( empty( $this->redirect_url ) ) {
			return $confirmation;
		}

		$confirmation = array( 'redirect' => $this->redirect_url );

		return $confirmation;
	}

	/**
	 * Override this function to specify a URL to the third party payment processor. Useful when developing a payment gateway that processes the payment outside of the website (i.e. PayPal Standard).
	 *
	 * @param $feed - Active payment feed containing all the configuration data
	 * @param $submission_data - Contains form field data submitted by the user as well as payment information (i.e. payment amount, setup fee, line items, etc...)
	 * @param $form - Current form array containing all form settings
	 * @param $entry - Current entry array containing entry information (i.e data submitted by users)
	 *
	 * @return string - Return a full URL (inlucing http:// or https://) to the payment processor
	 */
	protected function redirect_url( $feed, $submission_data, $form, $entry ) {

	}

	/**
	 * Check if the rest of the form has passed validation, is the last page, and that the honeypot field has not been completed.
	 *
	 * @param array $validation_result Contains the validation result, the form object, and the failed validation page number.
	 *
	 * @return array $validation_result
	 */
	public function maybe_validate( $validation_result ) {

		$form            = $validation_result['form'];
		$is_last_page    = GFFormDisplay::is_last_page( $form );
		$failed_honeypot = false;

		if ( $is_last_page && rgar( $form, 'enableHoneypot' ) ) {
			$honeypot_id     = GFFormDisplay::get_max_field_id( $form ) + 1;
			$failed_honeypot = ! rgempty( "input_{$honeypot_id}" );
		}

		if ( ! $validation_result['is_valid'] || ! $is_last_page || $failed_honeypot ) {
			return $validation_result;
		}

		return $this->validation( $validation_result );
	}

	public function validation( $validation_result ) {

		if ( ! $validation_result['is_valid'] ) {
			return $validation_result;
		}

		$form  = $validation_result['form'];
		$entry = GFFormsModel::create_lead( $form );
		$feed  = $this->get_payment_feed( $entry, $form );

		if ( ! $feed ) {
			return $validation_result;
		}

		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		//Do not process payment if payment amount is 0
		if ( floatval( $submission_data['payment_amount'] ) <= 0 ) {

			$this->log_debug( __METHOD__ . '(): Payment amount is $0.00 or less. Not sending to payment gateway.' );

			return $validation_result;
		}


		$this->is_payment_gateway      = true;
		$this->current_feed            = $this->_single_submission_feed = $feed;
		$this->current_submission_data = $submission_data;

		$performed_authorization = false;
		$is_subscription         = $feed['meta']['transactionType'] == 'subscription';

		if ( $this->payment_method_is_overridden( 'authorize' ) && ! $is_subscription ) {

			//Running an authorization only transaction if function is implemented and this is a single payment
			$this->authorization = $this->authorize( $feed, $submission_data, $form, $entry );

			$performed_authorization = true;

		} elseif ( $this->payment_method_is_overridden( 'subscribe' ) && $is_subscription ) {

			$subscription = $this->subscribe( $feed, $submission_data, $form, $entry );

			$this->authorization['is_authorized'] = $subscription['is_success'];
			$this->authorization['error_message'] = rgar( $subscription, 'error_message' );
			$this->authorization['subscription']  = $subscription;

			$performed_authorization = true;
		}

		if ( $performed_authorization ) {
			$this->log_debug( __METHOD__ . "(): Authorization result for form #{$form['id']} submission => " . print_r( $this->authorization, 1 ) );
		}

		if ( $performed_authorization && ! rgar( $this->authorization, 'is_authorized' ) ) {
			$validation_result = $this->get_validation_result( $validation_result, $this->authorization );

			//Setting up current page to point to the credit card page since that will be the highlighted field
			GFFormDisplay::set_current_page( $validation_result['form']['id'], $validation_result['credit_card_page'] );
		}

		return $validation_result;
	}

	/**
	 * Override this method to add integration code to the payment processor in order to authorize a credit card with or without capturing payment. This method is executed during the form validation process and allows
	 * the form submission process to fail with a validation error if there is anything wrong with the payment/authorization. This method is only supported by single payments.
	 * For subscriptions or recurring payments, use the subscribe() method.
	 *
	 * @param $feed - Current configured payment feed
	 * @param $submission_data - Contains form field data submitted by the user as well as payment information (i.e. payment amount, setup fee, line items, etc...)
	 * @param $form - Current form array containing all form settings
	 * @param $entry - Current entry array containing entry information (i.e data submitted by users). NOTE: the entry hasn't been saved to the database at this point, so this $entry object does not have the 'ID' property and is only a memory representation of the entry.
	 *
	 * @return array - Return an $authorization array in the following format:
	 * [
	 *  'is_authorized' => true|false,
	 *  'error_message' => 'Error message',
	 *  'transaction_id' => 'XXX',
	 *
	 *  //If the payment is captured in this method, return a 'captured_payment' array with the following information about the payment
	 *  'captured_payment' => ['is_success'=>true|false, 'error_message' => 'error message', 'transaction_id' => 'xxx', 'amount' => 20]
	 * ]
	 */
	protected function authorize( $feed, $submission_data, $form, $entry ) {

	}

	/**
	 * Override this method to capture a single payment that has been authorized via the authorize() method. Use only with single payments. For subscriptions, use subscribe() instead.
	 *
	 * @param $authorization - Contains the result of the authorize() function
	 * @param $feed - Current configured payment feed
	 * @param $submission_data - Contains form field data submitted by the user as well as payment information (i.e. payment amount, setup fee, line items, etc...)
	 * @param $form - Current form array containing all form settings
	 * @param $entry - Current entry array containing entry information (i.e data submitted by users).
	 *
	 * @return array - Return an array with the information about the captured payment in the following format:
	 * [
	 *      'is_success'=>true|false,
	 *      'error_message' => 'error message',
	 *      'transaction_id' => 'xxx',
	 *      'amount' => 20,
	 *      'payment_method' => 'Visa'
	 *  ]
	 */
	protected function capture( $authorization, $feed, $submission_data, $form, $entry ) {

	}

	/**
	 * Override this method to add integration code to the payment processor in order to create a subscription. This method is executed during the form validation process and allows
	 * the form submission process to fail with a validation error if there is anything wrong when creating the subscription.
	 *
	 * @param $feed - Current configured payment feed
	 * @param $submission_data - Contains form field data submitted by the user as well as payment information (i.e. payment amount, setup fee, line items, etc...)
	 * @param $form - Current form array containing all form settings
	 * @param $entry - Current entry array containing entry information (i.e data submitted by users). NOTE: the entry hasn't been saved to the database at this point, so this $entry object does not have the 'ID' property and is only a memory representation of the entry.
	 *
	 * @return array - Return an $subscription array in the following format:
	 * [
	 *  'is_success'=>true|false,
	 *  'error_message' => 'error message',
	 *  'subscription_id' => 'xxx',
	 *  'amount' => 10
	 *
	 *  //To implement an initial/setup fee for gateways that don't support setup fees as part of subscriptions, manually capture the funds for the setup fee as a separate transaction and send that payment
	 *  //information in the following 'captured_payment' array
	 *  'captured_payment' => ['name' => 'Setup Fee', 'is_success'=>true|false, 'error_message' => 'error message', 'transaction_id' => 'xxx', 'amount' => 20]
	 * ]
	 */
	protected function subscribe( $feed, $submission_data, $form, $entry ) {

	}

	/**
	 * Override this method to add integration code to the payment processor in order to cancel a subscription. This method is executed when a subscription is canceled from the Payment Gateway (i.e. Stripe or PayPal)
	 *
	 * @param $entry - Current entry array containing entry information (i.e data submitted by users).
	 * @param $feed - Current configured payment feed
	 *
	 * @return bool - Returns true if the subscription was cancelled successfully and false otherwise.
	 *
	 */
	protected function cancel( $entry, $feed ) {
		return false;
	}

	protected function get_validation_result( $validation_result, $authorization_result ) {

		$credit_card_page = 0;
		foreach ( $validation_result['form']['fields'] as &$field ) {
			if ( $field->type == 'creditcard' ) {
				$field->failed_validation  = true;
				$field->validation_message = $authorization_result['error_message'];
				$credit_card_page          = $field->pageNumber;
				break;
			}
		}

		$validation_result['credit_card_page'] = $credit_card_page;
		$validation_result['is_valid']         = false;

		return $validation_result;

	}

	public function entry_post_save( $entry, $form ) {

		if ( ! $this->is_payment_gateway ) {
			return $entry;
		}

		$feed = $this->current_feed;

		if ( ! empty( $this->authorization ) ) {
			//If an authorization was done, capture it

			if ( $feed['meta']['transactionType'] == 'subscription' ) {

				$entry = $this->process_subscription( $this->authorization, $feed, $this->current_submission_data, $form, $entry );

			} else {

				if ( $this->payment_method_is_overridden( 'capture' ) && rgempty( 'captured_payment', $this->authorization ) ) {

					$this->authorization['captured_payment'] = $this->capture( $this->authorization, $feed, $this->current_submission_data, $form, $entry );

				}

				$entry = $this->process_capture( $this->authorization, $feed, $this->current_submission_data, $form, $entry );
			}
		} elseif ( $this->payment_method_is_overridden( 'redirect_url' ) ) {

			//If the url_redirect() function is overridden, call it.

			//Getting URL to redirect to ( saved to be used by the confirmation() function )
			$this->redirect_url = $this->redirect_url( $feed, $this->current_submission_data, $form, $entry );

			//Setting transaction_type to subscription or one time payment
			$entry['transaction_type'] = rgars( $feed, 'meta/transactionType' ) == 'subscription' ? 2 : 1;
			$entry['payment_status']   = 'Processing';

		}

		//Saving which gateway was used to process this entry
		gform_update_meta( $entry['id'], 'payment_gateway', $this->_slug );

		return $entry;
	}

	protected function process_capture( $authorization, $feed, $submission_data, $form, $entry ) {

		$payment = rgar( $authorization, 'captured_payment' );
		if ( empty( $payment ) ) {
			return $entry;
		}

		$this->log_debug( __METHOD__ . "(): Updating entry #{$entry['id']} with result => " . print_r( $payment, 1 ) );

		if ( $payment['is_success'] ) {

			$entry['is_fulfilled']     = '1';
			$payment['payment_status'] = 'Paid';
			$payment['payment_date']   = gmdate( 'Y-m-d H:i:s' );
			$payment['type']           = 'complete_payment';
			$this->complete_payment( $entry, $payment );

		} else {

			$entry['payment_status'] = 'Failed';
			$payment['type']         = 'fail_payment';
			$payment['note']         = sprintf( esc_html__( 'Payment failed to be captured. Reason: %s', 'gravityforms' ), $payment['error_message'] );
			$this->fail_payment( $entry, $payment );

		}

		return $entry;

	}

	protected function process_subscription( $authorization, $feed, $submission_data, $form, $entry ) {

		$subscription = rgar( $authorization, 'subscription' );
		if ( empty( $subscription ) ) {
			return $entry;
		}

		$this->log_debug( __METHOD__ . "(): Updating entry #{$entry['id']} with result => " . print_r( $subscription, 1 ) );

		// if setup fee / trial is captured as part of a separate transaction
		$payment      = rgar( $subscription, 'captured_payment' );
		$payment_name = rgempty( 'name', $payment ) ? esc_html__( 'Initial payment', 'gravityforms' ) : $payment['name'];

		if ( $payment && $payment['is_success'] ) {

			$this->insert_transaction( $entry['id'], 'payment', $payment['transaction_id'], $payment['amount'], false );

			$amount_formatted = GFCommon::to_money( $payment['amount'], $entry['currency'] );
			$note             = sprintf( esc_html__( '%s has been captured successfully. Amount: %s. Transaction Id: %s', 'gravityforms' ), $payment_name, $amount_formatted, $payment['transaction_id'] );
			$this->add_note( $entry['id'], $note, 'success' );

		} elseif ( $payment && ! $payment['is_success'] ) {

			$this->add_note( $entry['id'], sprintf( esc_html__( 'Failed to capture %s. Reason: %s.', 'gravityforms' ), $payment['error_message'], $payment_name ), 'error' );

		}

		// updating subscription information
		if ( $subscription['is_success'] ) {

			$entry = $this->start_subscription( $entry, $subscription );

		} else {

			$entry['payment_status'] = 'Failed';
			GFAPI::update_entry( $entry );

			$this->add_note( $entry['id'], sprintf( esc_html__( 'Subscription failed to be created. Reason: %s', 'gravityforms' ), $subscription['error_message'] ), 'error' );

			$subscription['type'] = 'fail_create_subscription';
			$this->post_payment_action( $entry, $subscription );

		}

		return $entry;

	}

	protected function insert_transaction( $entry_id, $transaction_type, $transaction_id, $amount, $is_recurring = null ) {
		global $wpdb;

		// @todo: make sure stats does not show setup fee as a recurring payment
		$payment_count = $wpdb->get_var( $wpdb->prepare( "SELECT count(id) FROM {$wpdb->prefix}gf_addon_payment_transaction WHERE lead_id=%d", $entry_id ) );
		$is_recurring  = $payment_count > 0 && $transaction_type == 'payment' ? 1 : 0;

		$sql = $wpdb->prepare(
			" INSERT INTO {$wpdb->prefix}gf_addon_payment_transaction (lead_id, transaction_type, transaction_id, amount, is_recurring, date_created)
                                values(%d, %s, %s, %f, %d, utc_timestamp())", $entry_id, $transaction_type, $transaction_id, $amount, $is_recurring
		);
		$wpdb->query( $sql );

		$txn_id = $wpdb->insert_id;

		/**
		 * Fires after a payment transaction is created in Gravity Forms
		 *
		 * @param int $txn_id The overall Transaction ID
		 * @param int $entry_id The new Entry ID
		 * @param string $transaction_type The Type of transaction that was made
		 * @param int $transaction_id The transaction ID
		 * @param string $amount The amount payed in the transaction
		 * @param bool $is_recurring True or false if this is an ongoing payment
		 */
		do_action( 'gform_post_payment_transaction', $txn_id, $entry_id, $transaction_type, $transaction_id, $amount, $is_recurring );
		if ( has_filter( 'gform_post_payment_transaction' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_transaction.' );
		}

		return $txn_id;
	}

	public function get_payment_feed( $entry, $form = false ) {
		$submission_feed = false;

		if ( $entry['id'] ) {
			$feeds           = $this->get_feeds_by_entry( $entry['id'] );
			$submission_feed = empty( $feeds ) ? false : $this->get_feed( $feeds[0] );
		} elseif ( $form ) {
			// getting all feeds
			$feeds = $this->get_feeds( $form['id'] );

			foreach ( $feeds as $feed ) {
				if ( $feed['is_active'] && $this->is_feed_condition_met( $feed, $form, $entry ) ) {
					$submission_feed = $feed;
					break;
				}
			}
		}


		return $submission_feed;
	}

	protected function is_payment_gateway( $entry_id ) {

		if ( $this->is_payment_gateway ) {
			return true;
		}

		$gateway = gform_get_meta( $entry_id, 'payment_gateway' );

		return $gateway == $this->_slug;
	}

	public function get_submission_data( $feed, $form, $entry ) {

		$submission_data = array();

		$submission_data['form_title'] = $form['title'];

		//getting mapped field data
		$billing_fields = $this->billing_info_fields();
		foreach ( $billing_fields as $billing_field ) {
			$field_name                     = $billing_field['name'];
			$input_id                       = rgar( $feed['meta'], "billingInformation_{$field_name}" );
			$submission_data[ $field_name ] = $this->get_field_value( $form, $entry, $input_id );
		}

		//getting credit card field data
		$card_field = $this->get_credit_card_field( $form );
		if ( $card_field ) {

			$submission_data['card_number']          = $this->remove_spaces_from_card_number( rgpost( "input_{$card_field->id}_1" ) );
			$submission_data['card_expiration_date'] = rgpost( "input_{$card_field->id}_2" );
			$submission_data['card_security_code']   = rgpost( "input_{$card_field->id}_3" );
			$submission_data['card_name']            = rgpost( "input_{$card_field->id}_5" );

		}

		//getting product field data
		$order_info      = $this->get_order_data( $feed, $form, $entry );
		$submission_data = array_merge( $submission_data, $order_info );

		/**
		 * Enables the Submission Data to be modified before it is used during feed processing by the payment add-on.
		 *
		 * @since 1.9.12.8
		 *
		 * @param array $submission_data The customer and transaction data.
		 * @param array $feed The Feed Object.
		 * @param array $form The Form Object.
		 * @param array $entry The Entry Object.
		 *
		 * @return array $submission_data
		 */

		return gf_apply_filters( array( 'gform_submission_data_pre_process_payment', $form['id'] ), $submission_data, $feed, $form, $entry );
	}

	protected function get_credit_card_field( $form ) {
		$fields = GFAPI::get_fields_by_type( $form, array( 'creditcard' ) );

		return empty( $fields ) ? false : $fields[0];
	}

	protected function has_credit_card_field( $form ) {
		return $this->get_credit_card_field( $form ) !== false;
	}

	protected function get_order_data( $feed, $form, $entry ) {

		$products = GFCommon::get_product_fields( $form, $entry );

		$payment_field   = $feed['meta']['transactionType'] == 'product' ? rgars( $feed, 'meta/paymentAmount' ) : rgars( $feed, 'meta/recurringAmount' );
		$setup_fee_field = rgar( $feed['meta'], 'setupFee_enabled' ) ? $feed['meta']['setupFee_product'] : false;
		$trial_field     = rgar( $feed['meta'], 'trial_enabled' ) ? rgars( $feed, 'meta/trial_product' ) : false;

		$amount       = 0;
		$line_items   = array();
		$discounts    = array();
		$fee_amount   = 0;
		$trial_amount = 0;
		foreach ( $products['products'] as $field_id => $product ) {

			$quantity      = $product['quantity'] ? $product['quantity'] : 1;
			$product_price = GFCommon::to_number( $product['price'], $entry['currency'] );

			$options = array();
			if ( is_array( rgar( $product, 'options' ) ) ) {
				foreach ( $product['options'] as $option ) {
					$options[] = $option['option_name'];
					$product_price += $option['price'];
				}
			}

			$is_trial_or_setup_fee = false;

			if ( ! empty( $trial_field ) && $trial_field == $field_id ) {

				$trial_amount          = $product_price * $quantity;
				$is_trial_or_setup_fee = true;

			} elseif ( ! empty( $setup_fee_field ) && $setup_fee_field == $field_id ) {

				$fee_amount            = $product_price * $quantity;
				$is_trial_or_setup_fee = true;
			}

			//Do not add to line items if the payment field selected in the feed is not the current field.
			if ( is_numeric( $payment_field ) && $payment_field != $field_id ) {
				continue;
			}

			//Do not add to line items if the payment field is set to "Form Total" and the current field was used for trial or setup fee.
			if ( $is_trial_or_setup_fee && ! is_numeric( $payment_field ) ) {
				continue;
			}

			$amount += $product_price * $quantity;

			$description = '';
			if ( ! empty( $options ) ) {
				$description = esc_html__( 'options: ', 'gravityforms' ) . ' ' . implode( ', ', $options );
			}

			if ( $product_price >= 0 ) {
				$line_items[] = array(
					'id'          => $field_id,
					'name'        => $product['name'],
					'description' => $description,
					'quantity'    => $quantity,
					'unit_price'  => GFCommon::to_number( $product_price, $entry['currency'] ),
					'options'     => rgar( $product, 'options' )
				);
			} else {
				$discounts[] = array(
					'id'          => $field_id,
					'name'        => $product['name'],
					'description' => $description,
					'quantity'    => $quantity,
					'unit_price'  => GFCommon::to_number( $product_price, $entry['currency'] ),
					'options'     => rgar( $product, 'options' )
				);
			}
		}

		if ( $trial_field == 'enter_amount' ) {
			$trial_amount = rgar( $feed['meta'], 'trial_amount' ) ? GFCommon::to_number( rgar( $feed['meta'], 'trial_amount' ), $entry['currency'] ) : 0;
		}

		if ( ! empty( $products['shipping']['name'] ) && ! is_numeric( $payment_field ) ) {
			$line_items[] = array(
				'id'          => $products['shipping']['id'],
				'name'        => $products['shipping']['name'],
				'description' => '',
				'quantity'    => 1,
				'unit_price'  => GFCommon::to_number( $products['shipping']['price'], $entry['currency'] ),
				'is_shipping' => 1
			);
			$amount += $products['shipping']['price'];
		}

		return array(
			'payment_amount' => $amount,
			'setup_fee'      => $fee_amount,
			'trial'          => $trial_amount,
			'line_items'     => $line_items,
			'discounts'      => $discounts
		);
	}

	protected function is_callback_valid() {
		if ( rgget( 'callback' ) != $this->_slug ) {
			return false;
		}

		return true;
	}


	//--------- Callback (aka Webhook)----------------

	public function maybe_process_callback() {

		// ignoring requests that are not this addon's callbacks
		if ( ! $this->is_callback_valid() ) {
			return;
		}

		// returns either false or an array of data about the callback request which payment add-on will then use
		// to generically process the callback data
		$this->log_debug( __METHOD__ . '(): Initializing callback processing for: ' . $this->_slug );

		$callback_action = $this->callback();

		$this->log_debug( __METHOD__ . '(): Result from gateway callback => ' . print_r( $callback_action, true ) );

		$result = false;
		if ( is_wp_error( $callback_action ) ) {
			$this->display_callback_error( $callback_action );
		} elseif ( $callback_action && is_array( $callback_action ) && rgar( $callback_action, 'type' ) && ! rgar( $callback_action, 'abort_callback' ) ) {

			$result = $this->process_callback_action( $callback_action );

			$this->log_debug( __METHOD__ . '(): Result of callback action => ' . print_r( $result, true ) );

			if ( is_wp_error( $result ) ) {
				$this->display_callback_error( $result );
			} elseif ( ! $result ) {
				status_header( 200 );
				echo 'Callback could not be processed.';
			} else {
				status_header( 200 );
				echo 'Callback processed successfully.';
			}
		} else {
			status_header( 200 );
			echo 'Callback bypassed';
		}

		$this->post_callback( $callback_action, $result );

		die();
	}

	private function display_callback_error( $error ) {

		$data   = $error->get_error_data();
		$status = ! rgempty( 'status_header', $data ) ? $data['status_header'] : 200;

		status_header( $status );
		echo $error->get_error_message();
	}

	/**
	 * Processes callback based on provided data.
	 *
	 * $action = array(
	 *     'type' => 'cancel_subscription',     // required
	 *     'transaction_id' => '',              // required (if payment)
	 *     'subscription_id' => '',             // required (if subscription)
	 *     'amount' => '0.00',                  // required (some exceptions)
	 *     'entry_id' => 1,                     // required (some exceptions)
	 *     'transaction_type' => '',
	 *     'payment_status' => '',
	 *     'note' => ''
	 * );
	 *
	 * @param array $action
	 *
	 * @return mixed
	 */
	private function process_callback_action( $action ) {
		$this->log_debug( __METHOD__ . '(): Processing callback action.' );
		$action = wp_parse_args(
			$action, array(
				'type'             => false,
				'amount'           => false,
				'transaction_type' => false,
				'transaction_id'   => false,
				'subscription_id'  => false,
				'entry_id'         => false,
				'payment_status'   => false,
				'note'             => false,
			)
		);

		$result = false;

		if ( rgar( $action, 'id' ) && $this->is_duplicate_callback( $action['id'] ) ) {
			return new WP_Error( 'duplicate', sprintf( esc_html__( 'This webhook has already been processed (Event Id: %s)', 'gravityforms' ), $action['id'] ) );
		}

		$entry = GFAPI::get_entry( $action['entry_id'] );
		if ( ! $entry || is_wp_error( $entry ) ) {
			return $result;
		}

		//$action = do_action('gform_action_pre_payment_callback', $action, $entry);
		do_action( 'gform_action_pre_payment_callback', $action, $entry );
		if ( has_filter( 'gform_action_pre_payment_callback' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_action_pre_payment_callback.' );
		}

		switch ( $action['type'] ) {
			case 'complete_payment':
				$result = $this->complete_payment( $entry, $action );
				break;
			case 'refund_payment':
				$result = $this->refund_payment( $entry, $action );
				break;
			case 'fail_payment':
				$result = $this->fail_payment( $entry, $action );
				break;
			case 'add_pending_payment':
				$result = $this->add_pending_payment( $entry, $action );
				break;
			case 'void_authorization':
				$result = $this->void_authorization( $entry, $action );
				break;
			case 'create_subscription':
				$result = $this->start_subscription( $entry, $action );
				$result = rgar( $result, 'payment_status' ) == 'Active' && rgar( $result, 'transaction_id' ) == rgar( $action, 'subscription_id' );
				break;
			case 'cancel_subscription':
				$feed   = $this->get_payment_feed( $entry );
				$result = $this->cancel_subscription( $entry, $feed, $action['note'] );
				break;
			case 'expire_subscription':
				$result = $this->expire_subscription( $entry, $action );
				break;
			case 'add_subscription_payment':
				$result = $this->add_subscription_payment( $entry, $action );
				break;
			case 'fail_subscription_payment':
				$result = $this->fail_subscription_payment( $entry, $action );
				break;
			default:
				// handle custom events
				if ( is_callable( array( $this, rgar( $action, 'callback' ) ) ) ) {
					$result = call_user_func_array( array( $this, $action['callback'] ), array( $entry, $action ) );
				}
				break;
		}

		if ( rgar( $action, 'id' ) && $result ) {
			$this->register_callback( $action['id'], $action['entry_id'] );
		}

		/**
		 * Fires right after the payment callback
		 *
		 * @param array $entry The Entry Object
		 * @param array $action The Action Object
		 * $action = array(
		 *     'type' => 'cancel_subscription',     // See Below
		 *     'transaction_id' => '',              // What is the ID of the transaction made?
		 *     'subscription_id' => '',             // What is the ID of the Subscription made?
		 *     'amount' => '0.00',                  // Amount to charge?
		 *     'entry_id' => 1,                     // What entry to check?
		 *     'transaction_type' => '',
		 *     'payment_status' => '',
		 *     'note' => ''
		 * );
		 *
		 * 'type' can be:
		 *
		 * - complete_payment
		 * - refund_payment
		 * - fail_payment
		 * - add_pending_payment
		 * - void_authorization
		 * - create_subscription
		 * - cancel_subscription
		 * - expire_subscription
		 * - add_subscription_payment
		 * - fail_subscription_payment
		 *
		 * @param mixed $result The Result Object
		 */
		do_action( 'gform_post_payment_callback', $entry, $action, $result );
		if ( has_filter( 'gform_post_payment_callback' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_callback.' );
		}

		return $result;
	}

	protected function register_callback( $callback_id, $entry_id ) {
		global $wpdb;

		$wpdb->insert( "{$wpdb->prefix}gf_addon_payment_callback", array(
			'addon_slug'   => $this->_slug,
			'callback_id'  => $callback_id,
			'lead_id'      => $entry_id,
			'date_created' => gmdate( 'Y-m-d H:i:s' )
		) );
	}

	protected function is_duplicate_callback( $callback_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}gf_addon_payment_callback WHERE addon_slug=%s AND callback_id=%s", $this->_slug, $callback_id );
		if ( $wpdb->get_var( $sql ) ) {
			return true;
		}

		return false;
	}

	protected function callback() {
	}

	protected function post_callback( $callback_action, $result ) {
	}


	// # PAYMENT INTERACTION FUNCTIONS

	public function add_pending_payment( $entry, $action ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $action['payment_status'] ) {
			$action['payment_status'] = 'Pending';
		}

		if ( ! $action['note'] ) {
			$amount_formatted = GFCommon::to_money( $action['amount'], $entry['currency'] );
			$action['note']   = sprintf( esc_html__( 'Payment is pending. Amount: %s. Transaction Id: %s.', 'gravityforms' ), $amount_formatted, $action['transaction_id'] );
		}

		GFAPI::update_entry_property( $entry['id'], 'payment_status', $action['payment_status'] );
		$this->add_note( $entry['id'], $action['note'] );
		$this->post_payment_action( $entry, $action );

		return true;
	}

	public function complete_payment( &$entry, $action ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! rgar( $action, 'payment_status' ) ) {
			$action['payment_status'] = 'Paid';
		}

		if ( ! rgar( $action, 'transaction_type' ) ) {
			$action['transaction_type'] = 'payment';
		}

		if ( ! rgar( $action, 'payment_date' ) ) {
			$action['payment_date'] = gmdate( 'y-m-d H:i:s' );
		}

		$entry['is_fulfilled']     = '1';
		$entry['transaction_id']   = rgar( $action, 'transaction_id' );
		$entry['transaction_type'] = '1';
		$entry['payment_status']   = $action['payment_status'];
		$entry['payment_amount']   = rgar( $action, 'amount' );
		$entry['payment_date']     = $action['payment_date'];
		$entry['payment_method']   = rgar( $action, 'payment_method' );

		if ( ! rgar( $action, 'note' ) ) {
			$amount_formatted = GFCommon::to_money( $action['amount'], $entry['currency'] );
			$action['note']   = sprintf( esc_html__( 'Payment has been completed. Amount: %s. Transaction Id: %s.', 'gravityforms' ), $amount_formatted, $action['transaction_id'] );
		}

		GFAPI::update_entry( $entry );
		$this->insert_transaction( $entry['id'], $action['transaction_type'], $action['transaction_id'], $action['amount'] );
		$this->add_note( $entry['id'], $action['note'], 'success' );

		/**
		 * Fires after a payment is completed through a form
		 *
		 * @param array $entry The Entry object
		 * @param array $action The Action Object
		 * $action = array(
		 *     'type' => 'cancel_subscription',     // See Below
		 *     'transaction_id' => '',              // What is the ID of the transaction made?
		 *     'subscription_id' => '',             // What is the ID of the Subscription made?
		 *     'amount' => '0.00',                  // Amount to charge?
		 *     'entry_id' => 1,                     // What entry to check?
		 *     'transaction_type' => '',
		 *     'payment_status' => '',
		 *     'note' => ''
		 * );
		 *
		 * 'type' can be:
		 *
		 * - complete_payment
		 * - refund_payment
		 * - fail_payment
		 * - add_pending_payment
		 * - void_authorization
		 * - create_subscription
		 * - cancel_subscription
		 * - expire_subscription
		 * - add_subscription_payment
		 * - fail_subscription_payment
		 */
		do_action( 'gform_post_payment_completed', $entry, $action );
		if ( has_filter( 'gform_post_payment_completed' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_completed.' );
		}
		$this->post_payment_action( $entry, $action );

		return true;
	}

	public function refund_payment( $entry, $action ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $action['payment_status'] ) {
			$action['payment_status'] = 'Refunded';
		}

		if ( ! $action['transaction_type'] ) {
			$action['transaction_type'] = 'refund';
		}

		if ( ! $action['note'] ) {
			$amount_formatted = GFCommon::to_money( $action['amount'], $entry['currency'] );
			$action['note']   = sprintf( esc_html__( 'Payment has been refunded. Amount: %s. Transaction Id: %s.', 'gravityforms' ), $amount_formatted, $action['transaction_id'] );
		}

		GFAPI::update_entry_property( $entry['id'], 'payment_status', $action['payment_status'] );
		$this->insert_transaction( $entry['id'], $action['transaction_type'], $action['transaction_id'], $action['amount'] );
		$this->add_note( $entry['id'], $action['note'] );

		/**
		 * Fires after a payment is refunded
		 *
		 * @param array $entry The Entry object
		 * @param array $action The Action Object
		 * $action = array(
		 *     'type' => 'cancel_subscription',     // See Below
		 *     'transaction_id' => '',              // What is the ID of the transaction made?
		 *     'subscription_id' => '',             // What is the ID of the Subscription made?
		 *     'amount' => '0.00',                  // Amount to charge?
		 *     'entry_id' => 1,                     // What entry to check?
		 *     'transaction_type' => '',
		 *     'payment_status' => '',
		 *     'note' => ''
		 * );
		 *
		 * 'type' can be:
		 *
		 * - complete_payment
		 * - refund_payment
		 * - fail_payment
		 * - add_pending_payment
		 * - void_authorization
		 * - create_subscription
		 * - cancel_subscription
		 * - expire_subscription
		 * - add_subscription_payment
		 * - fail_subscription_payment
		 */
		do_action( 'gform_post_payment_refunded', $entry, $action );
		if ( has_filter( 'gform_post_payment_refunded' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_refunded.' );
		}
		$this->post_payment_action( $entry, $action );

		return true;
	}

	public function fail_payment( $entry, $action ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $action['payment_status'] ) {
			$action['payment_status'] = 'Failed';
		}

		if ( ! $action['note'] ) {
			$amount_formatted = GFCommon::to_money( $action['amount'], $entry['currency'] );
			$action['note']   = sprintf( esc_html__( 'Payment has failed. Amount: %s.', 'gravityforms' ), $amount_formatted );
		}

		GFAPI::update_entry_property( $entry['id'], 'payment_status', $action['payment_status'] );
		$this->add_note( $entry['id'], $action['note'] );
		$this->post_payment_action( $entry, $action );

		return true;
	}

	public function void_authorization( $entry, $action ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $action['payment_status'] ) {
			$action['payment_status'] = 'Voided';
		}

		if ( ! $action['note'] ) {
			$action['note'] = sprintf( esc_html__( 'Authorization has been voided. Transaction Id: %s', 'gravityforms' ), $action['transaction_id'] );
		}

		GFAPI::update_entry_property( $entry['id'], 'payment_status', $action['payment_status'] );
		$this->add_note( $entry['id'], $action['note'] );
		$this->post_payment_action( $entry, $action );

		return true;
	}

	/**
	 * Used to start a new subscription. Updates the associcated entry with the payment and transaction details and adds an entry note.
	 *
	 * @param  [array]  $entry           Entry object
	 * @param  [string] $subscription_id ID of the subscription
	 * @param  [float]  $amount          Numeric amount of the initial subscription payment
	 *
	 * @return [array]  $entry           Entry Object
	 */

	public function start_subscription( $entry, $subscription ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $this->has_subscription( $entry ) ) {
			$entry['payment_status']   = 'Active';
			$entry['payment_amount']   = $subscription['amount'];
			$entry['payment_date']     = gmdate( 'y-m-d H:i:s' );
			$entry['transaction_id']   = $subscription['subscription_id'];
			$entry['transaction_type'] = '2'; // subscription
			$entry['is_fulfilled']     = '1';

			$result = GFAPI::update_entry( $entry );
			$this->add_note( $entry['id'], sprintf( esc_html__( 'Subscription has been created. Subscription Id: %s.', 'gravityforms' ), $subscription['subscription_id'] ), 'success' );


			/**
			 * Fires when someone starts a subscription
			 *
			 * @param array $entry Entry Object
			 * @param array $subscription The new Subscription object
			 */
			do_action( 'gform_post_subscription_started', $entry, $subscription );
			if ( has_filter( 'gform_post_subscription_started' ) ) {
				$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_subscription_started.' );
			}

			$subscription['type'] = 'create_subscription';
			$this->post_payment_action( $entry, $subscription );

		}

		return $entry;
	}

	/**
	 * A payment on an existing subscription.
	 *
	 * @param  [array] $data  Transaction data including 'amount' and 'subscriber_id'
	 * @param  [array] $entry Entry object
	 *
	 * @return true
	 */
	public function add_subscription_payment( $entry, $action ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $action['transaction_type'] ) {
			$action['transaction_type'] = 'payment';
		}

		// Set payment status back to active if a previous payment attempt failed.
		if ( strtolower( $entry['payment_status'] ) != 'active' ) {
			$entry['payment_status'] = 'Active';
			GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Active' );
		}

		if ( ! $action['note'] ) {
			$amount_formatted = GFCommon::to_money( $action['amount'], $entry['currency'] );
			$action['note']   = sprintf( esc_html__( 'Subscription has been paid. Amount: %s. Subscription Id: %s', 'gravityforms' ), $amount_formatted, $action['subscription_id'] );
		}

		$transaction_id = ! empty( $action['transaction_id'] ) ? $action['transaction_id'] : $action['subscription_id'];

		$this->insert_transaction( $entry['id'], $action['transaction_type'], $transaction_id, $action['amount'] );
		$this->add_note( $entry['id'], $action['note'], 'success' );

		/**
		 * Fires after a payment is made on an existing subscription.
		 *
		 * @param array $entry The Entry Object
		 * @param array $action The Action Object
		 * $action = array(
		 *     'type' => 'cancel_subscription',     // See Below
		 *     'transaction_id' => '',              // What is the ID of the transaction made?
		 *     'subscription_id' => '',             // What is the ID of the Subscription made?
		 *     'amount' => '0.00',                  // Amount to charge?
		 *     'entry_id' => 1,                     // What entry to check?
		 *     'transaction_type' => '',
		 *     'payment_status' => '',
		 *     'note' => ''
		 * );
		 *
		 * 'type' can be:
		 *
		 * - complete_payment
		 * - refund_payment
		 * - fail_payment
		 * - add_pending_payment
		 * - void_authorization
		 * - create_subscription
		 * - cancel_subscription
		 * - expire_subscription
		 * - add_subscription_payment
		 * - fail_subscription_payment
		 */
		do_action( 'gform_post_add_subscription_payment', $entry, $action );
		if ( has_filter( 'gform_post_add_subscription_payment' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_add_subscription_payment.' );
		}
		$this->post_payment_action( $entry, $action );

		return true;
	}

	public function fail_subscription_payment( $entry, $action ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $action['note'] ) {
			$amount_formatted = GFCommon::to_money( $action['amount'], $entry['currency'] );
			$action['note']   = sprintf( esc_html__( 'Subscription payment has failed. Amount: %s. Subscription Id: %s.', 'gravityforms' ), $amount_formatted, $action['subscription_id'] );
		}

		GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Failed' );
		$this->add_note( $entry['id'], $action['note'], 'error' );

		// keep 'gform_subscription_payment_failed' for backward compatability
		/**
		 * @deprecated Use gform_post_fail_subscription_payment now
		 */
		do_action( 'gform_subscription_payment_failed', $entry, $action['subscription_id'] );
		if ( has_filter( 'gform_subscription_payment_failed' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_subscription_payment_failed.' );
		}
		/**
		 * Fires after a subscription payment has failed
		 *
		 * @param array $entry The Entry Object
		 * @param array $action The Action Object
		 * $action = array(
		 *     'type' => 'cancel_subscription',     // See Below
		 *     'transaction_id' => '',              // What is the ID of the transaction made?
		 *     'subscription_id' => '',             // What is the ID of the Subscription made?
		 *     'amount' => '0.00',                  // Amount to charge?
		 *     'entry_id' => 1,                     // What entry to check?
		 *     'transaction_type' => '',
		 *     'payment_status' => '',
		 *     'note' => ''
		 * );
		 *
		 * 'type' can be:
		 *
		 * - complete_payment
		 * - refund_payment
		 * - fail_payment
		 * - add_pending_payment
		 * - void_authorization
		 * - create_subscription
		 * - cancel_subscription
		 * - expire_subscription
		 * - add_subscription_payment
		 * - fail_subscription_payment
		 */
		do_action( 'gform_post_fail_subscription_payment', $entry, $action );
		if ( has_filter( 'gform_post_fail_subscription_payment' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_fail_subscription_payment.' );
		}
		$this->post_payment_action( $entry, $action );

		return true;
	}

	public function cancel_subscription( $entry, $feed, $note = null ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $note ) {
			$note = sprintf( esc_html__( 'Subscription has been cancelled. Subscription Id: %s.', 'gravityforms' ), $entry['transaction_id'] );
		}

		if ( strtolower( $entry['payment_status'] ) == 'cancelled' ) {
			$this->log_debug( __METHOD__ . '(): Subscription is already canceled.' );

			return false;
		}

		GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Cancelled' );
		$this->add_note( $entry['id'], $note );

		// include $subscriber_id as 3rd parameter for backwards compatibility
		do_action( 'gform_subscription_canceled', $entry, $feed, $entry['transaction_id'] );
		if ( has_filter( 'gform_subscription_canceled' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_subscription_canceled.' );
		}

		$action = array(
			'type'            => 'cancel_subscription',
			'subscription_id' => $entry['transaction_id'],
			'entry_id'        => $entry['id'],
			'payment_status'  => 'Cancelled',
			'note'            => $note,
		);
		$this->post_payment_action( $entry, $action );

		return true;
	}

	public function expire_subscription( $entry, $action ) {
		$this->log_debug( __METHOD__ . '(): Processing request.' );
		if ( ! $action['note'] ) {
			$action['note'] = sprintf( esc_html__( 'Subscription has expired. Subscriber Id: %s', 'gravityforms' ), $action['subscription_id'] );
		}

		GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Expired' );
		$this->add_note( $entry['id'], $action['note'] );
		$this->post_payment_action( $entry, $action );

		return true;
	}

	public function has_subscription( $entry ) {
		if ( rgar( $entry, 'transaction_type' ) == 2 && ! rgempty( 'transaction_id', $entry ) ) {
			return true;
		} else {
			return false;
		}
	}

	protected function get_entry_by_transaction_id( $transaction_id ) {
		global $wpdb;

		$sql      = $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rg_lead WHERE transaction_id = %s", $transaction_id );
		$entry_id = $wpdb->get_var( $sql );

		//        //Try transaction table
		//        if( empty($entry_id) ){
		//            $sql = $wpdb->prepare( "SELECT lead_id FROM {$wpdb->prefix}gf_addon_payment_transaction WHERE transaction_id = %s", $transaction_id );
		//            $entry_id = $wpdb->get_var( $sql );
		//        }

		return $entry_id ? $entry_id : false;
	}

	/**
	 * Helper for making the gform_post_payment_action hook available to the various payment interaction methods. Also handles sending notifications for payment events.
	 *
	 * @param array $entry
	 * @param array $action
	 */
	public function post_payment_action( $entry, $action ) {
		do_action( 'gform_post_payment_action', $entry, $action );
		if ( has_filter( 'gform_post_payment_action' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_action.' );
		}

		$form             = GFAPI::get_form( $entry['form_id'] );
		$supported_events = $this->supported_notification_events( $form );
		if ( ! empty( $supported_events ) ) {
			GFAPI::send_notifications( $form, $entry, rgar( $action, 'type' ) );
		}
	}


	// -------- Cron --------------------
	protected function setup_cron() {
		// Setting up cron
		$cron_name = "{$this->_slug}_cron";

		add_action( $cron_name, array( $this, 'check_status' ) );

		if ( ! wp_next_scheduled( $cron_name ) ) {
			wp_schedule_event( time(), 'hourly', $cron_name );
		}


	}

	public function check_status() {

	}

	//--------- List Columns ------------
	protected function feed_list_columns() {
		return array(
			'feedName'        => esc_html__( 'Name', 'gravityforms' ),
			'transactionType' => esc_html__( 'Transaction Type', 'gravityforms' ),
			'amount'          => esc_html__( 'Amount', 'gravityforms' )
		);
	}

	public function get_column_value_transactionType( $feed ) {
		switch ( rgar( $feed['meta'], 'transactionType' ) ) {
			case 'subscription' :
				return esc_html__( 'Subscription', 'gravityforms' );
				break;
			case 'product' :
				return esc_html__( 'Products and Services', 'gravityforms' );
				break;
			case 'donation' :
				return esc_html__( 'Donations', 'gravityforms' );
				break;

		}

		return esc_html__( 'Unsupported transaction type', 'gravityforms' );
	}

	public function get_column_value_amount( $feed ) {
		$form     = $this->get_current_form();
		$field_id = $feed['meta']['transactionType'] == 'subscription' ? rgars( $feed, 'meta/recurringAmount' ) : rgars( $feed, 'meta/paymentAmount' );
		if ( $field_id == 'form_total' ) {
			$label = esc_html__( 'Form Total', 'gravityforms' );
		} else {
			$field = GFFormsModel::get_field( $form, $field_id );
			$label = GFCommon::get_label( $field );
		}

		return $label;
	}


	//--------- Feed Settings ----------------

	public function feed_list_message() {

		if ( $this->_requires_credit_card && ! $this->has_credit_card_field( $this->get_current_form() ) ) {
			return $this->requires_credit_card_message();
		}

		return parent::feed_list_message();
	}

	public function requires_credit_card_message() {
		$url = add_query_arg( array( 'view' => null, 'subview' => null ) );

		return sprintf( esc_html__( "You must add a Credit Card field to your form before creating a feed. Let's go %sadd one%s!", 'gravityforms' ), "<a href='" . esc_url( $url ) . "'>", '</a>' );
	}

	public function feed_settings_fields() {

		return array(

			array(
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gravityforms' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . esc_html__( 'Name', 'gravityforms' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityforms' )
					),
					array(
						'name'     => 'transactionType',
						'label'    => esc_html__( 'Transaction Type', 'gravityforms' ),
						'type'     => 'select',
						'onchange' => "jQuery(this).parents('form').submit();",
						'choices'  => array(
							array(
								'label' => esc_html__( 'Select a transaction type', 'gravityforms' ),
								'value' => ''
							),
							array(
								'label' => esc_html__( 'Products and Services', 'gravityforms' ),
								'value' => 'product'
							),
							array( 'label' => esc_html__( 'Subscription', 'gravityforms' ), 'value' => 'subscription' ),
						),
						'tooltip'  => '<h6>' . esc_html__( 'Transaction Type', 'gravityforms' ) . '</h6>' . esc_html__( 'Select a transaction type.', 'gravityforms' )
					),
				)
			),
			array(
				'title'      => 'Subscription Settings',
				'dependency' => array(
					'field'  => 'transactionType',
					'values' => array( 'subscription' )
				),
				'fields'     => array(
					array(
						'name'     => 'recurringAmount',
						'label'    => esc_html__( 'Recurring Amount', 'gravityforms' ),
						'type'     => 'select',
						'choices'  => $this->recurring_amount_choices(),
						'required' => true,
						'tooltip'  => '<h6>' . esc_html__( 'Recurring Amount', 'gravityforms' ) . '</h6>' . esc_html__( "Select which field determines the recurring payment amount, or select 'Form Total' to use the total of all pricing fields as the recurring amount.", 'gravityforms' )
					),
					array(
						'name'    => 'billingCycle',
						'label'   => esc_html__( 'Billing Cycle', 'gravityforms' ),
						'type'    => 'billing_cycle',
						'tooltip' => '<h6>' . esc_html__( 'Billing Cycle', 'gravityforms' ) . '</h6>' . esc_html__( 'Select your billing cycle.  This determines how often the recurring payment should occur.', 'gravityforms' )
					),
					array(
						'name'    => 'recurringTimes',
						'label'   => esc_html__( 'Recurring Times', 'gravityforms' ),
						'type'    => 'select',
						'choices' => array(
							             array(
								             'label' => esc_html__( 'infinite', 'gravityforms' ),
								             'value' => '0'
							             )
						             ) + $this->get_numeric_choices( 1, 100 ),
						'tooltip' => '<h6>' . esc_html__( 'Recurring Times', 'gravityforms' ) . '</h6>' . esc_html__( 'Select how many times the recurring payment should be made.  The default is to bill the customer until the subscription is canceled.', 'gravityforms' )
					),
					array(
						'name'  => 'setupFee',
						'label' => esc_html__( 'Setup Fee', 'gravityforms' ),
						'type'  => 'setup_fee',
					),
					array(
						'name'    => 'trial',
						'label'   => esc_html__( 'Trial', 'gravityforms' ),
						'type'    => 'trial',
						'hidden'  => $this->get_setting( 'setupFee_enabled' ),
						'tooltip' => '<h6>' . esc_html__( 'Trial Period', 'gravityforms' ) . '</h6>' . esc_html__( 'Enable a trial period.  The user\'s recurring payment will not begin until after this trial period.', 'gravityforms' )
					),
				)
			),
			array(
				'title'      => 'Products &amp; Services Settings',
				'dependency' => array(
					'field'  => 'transactionType',
					'values' => array( 'product', 'donation' )
				),
				'fields'     => array(
					array(
						'name'          => 'paymentAmount',
						'label'         => esc_html__( 'Payment Amount', 'gravityforms' ),
						'type'          => 'select',
						'choices'       => $this->product_amount_choices(),
						'required'      => true,
						'default_value' => 'form_total',
						'tooltip'       => '<h6>' . esc_html__( 'Payment Amount', 'gravityforms' ) . '</h6>' . esc_html__( "Select which field determines the payment amount, or select 'Form Total' to use the total of all pricing fields as the payment amount.", 'gravityforms' )
					),
				)
			),
			array(
				'title'      => esc_html__( 'Other Settings', 'gravityforms' ),
				'dependency' => array(
					'field'  => 'transactionType',
					'values' => array( 'subscription', 'product', 'donation' )
				),
				'fields'     => $this->other_settings_fields()
			),

		);
	}

	public function other_settings_fields() {
		$other_settings = array(
			array(
				'name'      => 'billingInformation',
				'label'     => esc_html__( 'Billing Information', 'gravityforms' ),
				'type'      => 'field_map',
				'field_map' => $this->billing_info_fields(),
				'tooltip'   => '<h6>' . esc_html__( 'Billing Information', 'gravityforms' ) . '</h6>' . esc_html__( 'Map your Form Fields to the available listed fields.', 'gravityforms' )
			),
		);

		$option_choices = $this->option_choices();
		if ( ! empty( $option_choices ) ) {
			$other_settings[] = array(
				'name'    => 'options',
				'label'   => esc_html__( 'Options', 'gravityforms' ),
				'type'    => 'checkbox',
				'choices' => $option_choices,
			);
		}

		$other_settings[] = array(
			'name'    => 'conditionalLogic',
			'label'   => esc_html__( 'Conditional Logic', 'gravityforms' ),
			'type'    => 'feed_condition',
			'tooltip' => '<h6>' . esc_html__( 'Conditional Logic', 'gravityforms' ) . '</h6>' . esc_html__( 'When conditions are enabled, form submissions will only be sent to the payment gateway when the conditions are met. When disabled, all form submissions will be sent to the payment gateway.', 'gravityforms' )
		);

		return $other_settings;
	}

	public function settings_billing_cycle( $field, $echo = true ) {

		$intervals = $this->supported_billing_intervals();
		//get unit so the length drop down is populated with the appropriate numbers for initial load
		$unit = $this->get_setting( $field['name'] . '_unit' );
		//Length drop down
		$interval_keys = array_keys( $intervals );
		if ( ! $unit ) {
			$first_interval = $intervals[ $interval_keys[0] ];
		} else {
			$first_interval = $intervals[ $unit ];
		}
		$length_field = array(
			'name'    => $field['name'] . '_length',
			'type'    => 'select',
			'choices' => $this->get_numeric_choices( $first_interval['min'], $first_interval['max'] )
		);

		$html = $this->settings_select( $length_field, false );

		//Unit drop down
		$choices = array();
		foreach ( $intervals as $unit => $interval ) {
			if ( ! empty( $interval ) ) {
				$choices[] = array( 'value' => $unit, 'label' => $interval['label'] );
			}
		}

		$unit_field = array(
			'name'     => $field['name'] . '_unit',
			'type'     => 'select',
			'onchange' => "loadBillingLength('" . esc_attr( $field['name'] ) . "')",
			'choices'  => $choices,
		);

		$html .= '&nbsp' . $this->settings_select( $unit_field, false );

		$html .= "<script type='text/javascript'>var " . $field['name'] . '_intervals = ' . json_encode( $intervals ) . ';</script>';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function settings_setup_fee( $field, $echo = true ) {

		$enabled_field = array(
			'name'       => $field['name'] . '_checkbox',
			'type'       => 'checkbox',
			'horizontal' => true,
			'choices'    => array(
				array(
					'label'    => esc_html__( 'Enabled', 'gravityforms' ),
					'name'     => $field['name'] . '_enabled',
					'value'    => '1',
					'onchange' => "if(jQuery(this).prop('checked')){jQuery('#{$field['name']}_product').show('slow'); jQuery('#gaddon-setting-row-trial').hide('slow');} else {jQuery('#{$field['name']}_product').hide('slow'); jQuery('#gaddon-setting-row-trial').show('slow');}",
				),
			)
		);

		$html = $this->settings_checkbox( $enabled_field, false );

		$form = $this->get_current_form();

		$is_enabled = $this->get_setting( "{$field['name']}_enabled" );

		$product_field = array(
			'name'    => $field['name'] . '_product',
			'type'    => 'select',
			'class'   => $is_enabled ? '' : 'hidden',
			'choices' => $this->get_payment_choices( $form )
		);

		$html .= '&nbsp' . $this->settings_select( $product_field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function set_trial_onchange( $field ) {

		return "if(jQuery(this).prop('checked')){jQuery('#{$field['name']}_product').show('slow');if (jQuery('#{$field['name']}_product').val() == 'enter_amount'){jQuery('#{$field['name']}_amount').show();}} else {jQuery('#{$field['name']}_product').hide('slow');jQuery('#{$field['name']}_amount').hide();}";

	}

	public function settings_trial( $field, $echo = true ) {

		//--- Enabled field ---
		$enabled_field = array(
			'name'       => $field['name'] . '_checkbox',
			'type'       => 'checkbox',
			'horizontal' => true,
			'choices'    => array(
				array(
					'label'    => esc_html__( 'Enabled', 'gravityforms' ),
					'name'     => $field['name'] . '_enabled',
					'value'    => '1',
					'onchange' => $this->set_trial_onchange( $field )
				),
			)
		);

		$html = $this->settings_checkbox( $enabled_field, false );

		//--- Select Product field ---
		$form            = $this->get_current_form();
		$payment_choices = array_merge( $this->get_payment_choices( $form ), array(
			array(
				'label' => esc_html__( 'Enter an amount', 'gravityforms' ),
				'value' => 'enter_amount'
			)
		) );

		$product_field = array(
			'name'     => $field['name'] . '_product',
			'type'     => 'select',
			'class'    => $this->get_setting( "{$field['name']}_enabled" ) ? '' : 'hidden',
			'onchange' => "if(jQuery(this).val() == 'enter_amount'){ jQuery('#{$field['name']}_amount').show();} else { jQuery('#{$field['name']}_amount').hide(); }",
			'choices'  => $payment_choices,
		);

		$html .= '&nbsp' . $this->settings_select( $product_field, false );

		//--- Trial Amount field ----
		$amount_field = array(
			'type'  => 'text',
			'name'  => "{$field['name']}_amount",
			'class' => $this->get_setting( "{$field['name']}_enabled" ) && $this->get_setting( "{$field['name']}_product" ) == 'enter_amount' ? 'gform_currency' : 'hidden gform_currency',
		);

		$html .= '&nbsp;' . $this->settings_text( $amount_field, false );


		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	protected function recurring_amount_choices() {
		$form                = $this->get_current_form();
		$recurring_choices   = $this->get_payment_choices( $form );
		$recurring_choices[] = array( 'label' => esc_html__( 'Form Total', 'gravityforms' ), 'value' => 'form_total' );

		return $recurring_choices;
	}

	protected function product_amount_choices() {
		$form              = $this->get_current_form();
		$product_choices   = $this->get_payment_choices( $form );
		$product_choices[] = array( 'label' => esc_html__( 'Form Total', 'gravityforms' ), 'value' => 'form_total' );

		return $product_choices;
	}

	protected function option_choices() {

		$option_choices = array(
			array(
				'label' => esc_html__( 'Sample Option', 'gravityforms' ),
				'name'  => 'sample_option',
				'value' => 'sample_option'
			),
		);

		return $option_choices;
	}

	protected function billing_info_fields() {

		$fields = array(
			array( 'name' => 'email', 'label' => esc_html__( 'Email', 'gravityforms' ), 'required' => false ),
			array( 'name' => 'address', 'label' => esc_html__( 'Address', 'gravityforms' ), 'required' => false ),
			array( 'name' => 'address2', 'label' => esc_html__( 'Address 2', 'gravityforms' ), 'required' => false ),
			array( 'name' => 'city', 'label' => esc_html__( 'City', 'gravityforms' ), 'required' => false ),
			array( 'name' => 'state', 'label' => esc_html__( 'State', 'gravityforms' ), 'required' => false ),
			array( 'name' => 'zip', 'label' => esc_html__( 'Zip', 'gravityforms' ), 'required' => false ),
			array( 'name' => 'country', 'label' => esc_html__( 'Country', 'gravityforms' ), 'required' => false ),
		);

		return $fields;
	}

	public function get_numeric_choices( $min, $max ) {
		$choices = array();
		for ( $i = $min; $i <= $max; $i ++ ) {
			$choices[] = array( 'label' => $i, 'value' => $i );
		}

		return $choices;
	}

	protected function supported_billing_intervals() {

		$billing_cycles = array(
			'day'   => array( 'label' => esc_html__( 'day(s)', 'gravityforms' ), 'min' => 1, 'max' => 365 ),
			'week'  => array( 'label' => esc_html__( 'week(s)', 'gravityforms' ), 'min' => 1, 'max' => 52 ),
			'month' => array( 'label' => esc_html__( 'month(s)', 'gravityforms' ), 'min' => 1, 'max' => 12 ),
			'year'  => array( 'label' => esc_html__( 'year(s)', 'gravityforms' ), 'min' => 1, 'max' => 10 )
		);

		return $billing_cycles;
	}

	protected function get_payment_choices( $form ) {
		$fields  = GFAPI::get_fields_by_type( $form, array( 'product' ) );
		$choices = array(
			array( 'label' => esc_html__( 'Select a product field', 'gravityforms' ), 'value' => '' ),
		);

		foreach ( $fields as $field ) {
			$field_id    = $field['id'];
			$field_label = RGFormsModel::get_label( $field );
			$choices[]   = array( 'value' => $field_id, 'label' => $field_label );
		}

		return $choices;
	}

	//--------- Stats Page -------------------
	public function get_results_page_config() {

		return array(
			'title'        => _x( 'Sales', 'toolbar label', 'gravityforms' ),
			'search_title' => _x( 'Filter', 'metabox title', 'gravityforms' ),
			'capabilities' => array( 'gravityforms_view_entries' ),
			'callbacks'    => array(
				'fields'    => array( $this, 'results_fields' ),
				'data'      => array( $this, 'results_data' ),
				'markup'    => array( $this, 'results_markup' ),
				'filter_ui' => array( $this, 'results_filter_ui' )
			)
		);
	}

	public function results_fields( $form ) {

		if ( $this->has_feed( $form['id'] ) ) {
			return $form['fields'];
		} else {
			return false;
		}

	}


	public function results_markup( $html, $data, $form, $fields ) {

		$html = "<table width='100%' id='gaddon-results-summary'>
                    <tr>
                        <td class='gaddon-results-summary-label'>" . esc_html__( 'Today', 'gravityforms' ) . "</td>
                        <td class='gaddon-results-summary-label'>" . esc_html__( 'Yesterday', 'gravityforms' ) . "</td>
                        <td class='gaddon-results-summary-label'>" . esc_html__( 'Last 30 Days', 'gravityforms' ) . "</td>
                        <td class='gaddon-results-summary-label'>" . esc_html__( 'Total', 'gravityforms' ) . "</td>
                    </tr>
                    <tr>
                        <td class='gaddon-results-summary-data'>
                            <div class='gaddon-results-summary-data-box'>
                                <div class='gaddon-results-summary-primary'>{$data['summary']['today']['revenue']}</div>
                                <div class='gaddon-results-summary-secondary'>{$data['summary']['today']['subscriptions']} " . esc_html__( 'subscriptions', 'gravityforms' ) . "</div>
                                <div class='gaddon-results-summary-secondary'>{$data['summary']['today']['orders']} " . esc_html__( 'orders', 'gravityforms' ) . "</div>
                            </div>
                        </td>
                        <td class='gaddon-results-summary-data'>
                            <div class='gaddon-results-summary-data-box'>
                                <div class='gaddon-results-summary-primary'>{$data['summary']['yesterday']['revenue']}</div>
                                <div class='gaddon-results-summary-secondary'>{$data['summary']['yesterday']['subscriptions']} " . esc_html__( 'subscriptions', 'gravityforms' ) . "</div>
                                <div class='gaddon-results-summary-secondary'>{$data['summary']['yesterday']['orders']} " . esc_html__( 'orders', 'gravityforms' ) . "</div>
                            </div>
                        </td>

                        <td class='gaddon-results-summary-data'>
                            <div class='gaddon-results-summary-data-box'>
                                <div class='gaddon-results-summary-primary'>{$data['summary']['last30']['revenue']}</div>
                                <div class='gaddon-results-summary-secondary'>{$data['summary']['last30']['subscriptions']} " . esc_html__( 'subscriptions', 'gravityforms' ) . "</div>
                                <div class='gaddon-results-summary-secondary'>{$data['summary']['last30']['orders']} " . esc_html__( 'orders', 'gravityforms' ) . "</div>
                            </div>
                        </td>
                        <td class='gaddon-results-summary-data'>
                            <div class='gaddon-results-summary-data-box'>
                                <div class='gaddon-results-summary-primary'>{$data['summary']['total']['revenue']}</div>
                                <div class='gaddon-results-summary-secondary'>{$data['summary']['total']['subscriptions']} " . esc_html__( 'subscriptions', 'gravityforms' ) . "</div>
                                <div class='gaddon-results-summary-secondary'>{$data['summary']['total']['orders']} " . esc_html__( 'orders', 'gravityforms' ) . '</div>
                            </div>
                        </td>

                    </tr>
                 </table>';

		if ( $data['row_count'] == '0' ) {
			$html .= "<div class='updated' style='padding:20px; margin-top:40px;'>" . esc_html__( "There aren't any transactions that match your criteria.", 'gravityforms' ) . '</div>';
		} else {
			$chart_data = $this->get_chart_data( $data );
			$html .= $this->get_sales_chart( $chart_data );

			//Getting sales table markup
			$sales_table = new GFPaymentStatsTable( $data['table']['header'], $data['data'], $data['row_count'], $data['page_size'] );
			$sales_table->prepare_items();
			ob_start();
			$sales_table->display();
			$html .= ob_get_clean();
		}

		$html .= '</form>';

		return $html;
	}

	protected function get_chart_data( $data ) {
		$hAxis_column = $data['chart']['hAxis']['column'];
		$vAxis_column = $data['chart']['vAxis']['column'];

		$chart_data = array();
		foreach ( $data['data'] as $row ) {
			$hAxis_value                = $row[ $hAxis_column ];
			$chart_data[ $hAxis_value ] = $row[ $vAxis_column ];
		}

		return array(
			'hAxis_title' => $data['chart']['hAxis']['label'],
			'vAxis_title' => $data['chart']['vAxis']['label'],
			'data'        => $chart_data
		);
	}

	public static function get_sales_chart( $sales_data ) {
		$markup = '';

		$data_table   = array();
		$data_table[] = array( $sales_data['hAxis_title'], $sales_data['vAxis_title'] );

		foreach ( $sales_data['data'] as $key => $value ) {
			$data_table[] = array( (string) $key, $value );
		}

		$chart_options = array(
			'series' => array(
				'0' => array(
					'color'           => '#66CCFF',
					'visibleInLegend' => 'false',
				),
			),
			'hAxis'  => array(
				'title' => $sales_data['hAxis_title'],
			),
			'vAxis'  => array(
				'title' => $sales_data['vAxis_title'],
			)
		);

		$data_table_json = json_encode( $data_table );
		$options_json    = json_encode( $chart_options );
		$div_id          = 'gquiz-results-chart-field-score-frequencies';
		$markup .= "<div class='gresults-chart-wrapper' style='width:100%;height:250px' id='{$div_id}'></div>";
		$markup .= "<script>
                        jQuery('#{$div_id}')
                            .data('datatable',{$data_table_json})
                            .data('options', {$options_json})
                            .data('charttype', 'column');
                    </script>";

		return $markup;

	}

	public function results_data( $form, $fields, $search_criteria, $state_array ) {

		$summary = $this->get_sales_summary( $form['id'] );

		$data = $this->get_sales_data( $form['id'], $search_criteria, $state_array );

		return array(
			'entry_count' => $data['row_count'],
			'row_count'   => $data['row_count'],
			'page_size'   => $data['page_size'],
			'status'      => 'complete',
			'summary'     => $summary,
			'data'        => $data['rows'],
			'chart'       => $data['chart'],
			'table'       => $data['table'],
		);
	}

	private function get_mysql_tz_offset() {
		$tz_offset = get_option( 'gmt_offset' );

		//add + if offset starts with a number
		if ( is_numeric( substr( $tz_offset, 0, 1 ) ) ) {
			$tz_offset = '+' . $tz_offset;
		}

		return $tz_offset . ':00';
	}

	protected function get_sales_data( $form_id, $search, $state ) {
		global $wpdb;

		$data = array(
			'chart' => array(
				'hAxis' => array(),
				'vAxis' => array(
					'column' => 'revenue',
					'label'  => esc_html__( 'Revenue', 'gravityforms' )
				)
			),
			'table' => array(
				'header' => array(
					'orders'             => esc_html__( 'Orders', 'gravityforms' ),
					'subscriptions'      => esc_html__( 'Subscriptions', 'gravityforms' ),
					'recurring_payments' => esc_html__( 'Recurring Payments', 'gravityforms' ),
					'refunds'            => esc_html__( 'Refunds', 'gravityforms' ),
					'revenue'            => esc_html__( 'Revenue', 'gravityforms' )
				)
			),
			'rows'  => array()
		);

		$tz_offset = $this->get_mysql_tz_offset();

		$page_size = 10;
		$group     = strtolower( rgpost( 'group' ) );
		switch ( $group ) {

			case 'weekly' :
				$select        = "concat(left(lead.week,4), ' - ', right(lead.week,2)) as week";
				$select_inner1 = "yearweek(CONVERT_TZ(date_created, '+00:00', '" . $tz_offset . "')) week";
				$select_inner2 = "yearweek(CONVERT_TZ(t.date_created, '+00:00', '" . $tz_offset . "')) week";
				$group_by      = 'week';
				$order_by      = 'week desc';
				$join          = 'lead.week = transaction.week';

				$data['chart']['hAxis']['column'] = 'week';
				$data['chart']['hAxis']['label']  = esc_html__( 'Week', 'gravityforms' );
				$data['table']['header']          = array_merge( array( 'week' => esc_html__( 'Week', 'gravityforms' ) ), $data['table']['header'] );
				break;

			case 'monthly' :
				$select        = "date_format(lead.month, '%%Y') as year, date_format(lead.month, '%%c') as month, '' as month_abbrev, '' as month_year";
				$select_inner1 = "date_format(CONVERT_TZ(date_created, '+00:00', '" . $tz_offset . "'), '%%Y-%%m-01') month";
				$select_inner2 = "date_format(CONVERT_TZ(t.date_created, '+00:00', '" . $tz_offset . "'), '%%Y-%%m-01') month";
				$group_by      = 'month';
				$order_by      = 'year desc, month desc';
				$join          = 'lead.month = transaction.month';

				$data['chart']['hAxis']['column'] = 'month_abbrev';
				$data['chart']['hAxis']['label']  = esc_html__( 'Month', 'gravityforms' );
				$data['table']['header']          = array_merge( array( 'month_year' => esc_html__( 'Month', 'gravityforms' ) ), $data['table']['header'] );
				break;

			default : //daily
				$select        = "lead.date, date_format(lead.date, '%%c') as month, day(lead.date) as day, dayname(lead.date) as day_of_week, '' as month_day";
				$select_inner1 = "date(CONVERT_TZ(date_created, '+00:00', '" . $tz_offset . "')) as date";
				$select_inner2 = "date(CONVERT_TZ(t.date_created, '+00:00', '" . $tz_offset . "')) as date";
				$group_by      = 'date';
				$order_by      = 'date desc';
				$join          = 'lead.date = transaction.date';

				$data['chart']['hAxis']['column'] = 'month_day';
				$data['chart']['hAxis']['label']  = esc_html__( 'Day', 'gravityforms' );
				$data['table']['header']          = array_merge( array(
					'date'        => esc_html__( 'Date', 'gravityforms' ),
					'day_of_week' => esc_html__( 'Day', 'gravityforms' )
				), $data['table']['header'] );
				break;
		}

		$lead_date_filter        = '';
		$transaction_date_filter = '';
		if ( isset( $search['start_date'] ) ) {
			$lead_date_filter        = $wpdb->prepare( " AND timestampdiff(SECOND, %s, CONVERT_TZ(l.date_created, '+00:00', '" . $tz_offset . "')) >= 0", $search['start_date'] );
			$transaction_date_filter = $wpdb->prepare( " AND timestampdiff(SECOND, %s, CONVERT_TZ(t.date_created, '+00:00', '" . $tz_offset . "')) >= 0", $search['start_date'] );
		}

		if ( isset( $search['end_date'] ) ) {
			$lead_date_filter .= $wpdb->prepare( " AND timestampdiff(SECOND, %s, CONVERT_TZ(l.date_created, '+00:00', '" . $tz_offset . "')) <= 0", $search['end_date'] );
			$transaction_date_filter .= $wpdb->prepare( " AND timestampdiff(SECOND, %s, CONVERT_TZ(t.date_created, '+00:00', '" . $tz_offset . "')) <= 0", $search['end_date'] );
		}

		$payment_method        = rgpost( 'payment_method' );
		$payment_method_filter = '';
		if ( ! empty( $payment_method ) ) {
			$payment_method_filter = $wpdb->prepare( ' AND l.payment_method=%s', $payment_method );
		}

		$current_page = rgempty( 'paged' ) ? 1 : absint( rgpost( 'paged' ) );
		$offset       = $page_size * ( $current_page - 1 );

		$sql = $wpdb->prepare(
			" SELECT SQL_CALC_FOUND_ROWS {$select}, lead.orders, lead.subscriptions, transaction.refunds, transaction.recurring_payments, transaction.revenue
                                FROM (
                                  SELECT  {$select_inner1},
                                          sum( if(transaction_type = 1,1,0) ) as orders,
                                          sum( if(transaction_type = 2,1,0) ) as subscriptions
                                  FROM {$wpdb->prefix}rg_lead l
                                  WHERE l.status='active' AND form_id=%d {$lead_date_filter} {$payment_method_filter}
                                  GROUP BY {$group_by}
                                ) AS lead

                                LEFT OUTER JOIN(
                                  SELECT  {$select_inner2},
                                          sum( if(t.transaction_type = 'refund', abs(t.amount) * -1, t.amount) ) as revenue,
                                          sum( if(t.transaction_type = 'refund', 1, 0) ) as refunds,
                                          sum( if(t.transaction_type = 'payment' AND t.is_recurring = 1, 1, 0) ) as recurring_payments
                                  FROM {$wpdb->prefix}gf_addon_payment_transaction t
                                  INNER JOIN {$wpdb->prefix}rg_lead l ON l.id = t.lead_id
                                  WHERE l.status='active' AND l.form_id=%d {$lead_date_filter} {$transaction_date_filter} {$payment_method_filter}
                                  GROUP BY {$group_by}

                                ) AS transaction on {$join}
                                ORDER BY {$order_by}
                                LIMIT $page_size OFFSET $offset
                                ", $form_id, $form_id
		);


		$results = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $results as &$result ) {
			$result['orders']             = intval( $result['orders'] );
			$result['subscriptions']      = intval( $result['subscriptions'] );
			$result['refunds']            = intval( $result['refunds'] );
			$result['recurring_payments'] = intval( $result['recurring_payments'] );
			$result['revenue']            = floatval( $result['revenue'] );

			$result = $this->format_chart_h_axis( $result );

		}

		$data['row_count'] = $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		$data['page_size'] = $page_size;

		$data['rows'] = $results;

		return $data;

	}

	protected function format_chart_h_axis( $result ) {
		$months = array(
			esc_html__( 'Jan', 'gravityforms' ),
			esc_html__( 'Feb', 'gravityforms' ),
			esc_html__( 'Mar', 'gravityforms' ),
			esc_html__( 'Apr', 'gravityforms' ),
			esc_html__( 'May', 'gravityforms' ),
			esc_html__( 'Jun', 'gravityforms' ),
			esc_html__( 'Jul', 'gravityforms' ),
			esc_html__( 'Aug', 'gravityforms' ),
			esc_html__( 'Sep', 'gravityforms' ),
			esc_html__( 'Oct', 'gravityforms' ),
			esc_html__( 'Nov', 'gravityforms' ),
			esc_html__( 'Dec', 'gravityforms' )
		);

		if ( isset( $result['month_abbrev'] ) ) {
			$result['month_abbrev'] = $months[ intval( $result['month'] ) - 1 ];
			$result['month_year']   = $months[ intval( $result['month'] ) - 1 ] . ', ' . $result['year'];

			return $result;
		} elseif ( isset( $result['month_day'] ) ) {
			$result['month_day'] = $months[ intval( $result['month'] ) - 1 ] . ' ' . $result['day'];

			return $result;
		}

		return $result;
	}

	protected function get_sales_summary( $form_id ) {
		global $wpdb;

		$tz_offset = $this->get_mysql_tz_offset();

		$summary = $wpdb->get_results(
			$wpdb->prepare(
				"
                    SELECT lead.date, lead.orders, lead.subscriptions, transaction.revenue
                    FROM (
                       SELECT  date( CONVERT_TZ(date_created, '+00:00', '" . $tz_offset . "') ) as date,
                               sum( if(transaction_type = 1,1,0) ) as orders,
                               sum( if(transaction_type = 2,1,0) ) as subscriptions
                       FROM {$wpdb->prefix}rg_lead
                       WHERE status='active' AND form_id = %d AND datediff(now(), CONVERT_TZ(date_created, '+00:00', '" . $tz_offset . "') ) <= 30
                       GROUP BY date
                     ) AS lead

                     LEFT OUTER JOIN(
                       SELECT  date( CONVERT_TZ(t.date_created, '+00:00', '" . $tz_offset . "') ) as date,
                               sum( if(t.transaction_type = 'refund', abs(t.amount) * -1, t.amount) ) as revenue
                       FROM {$wpdb->prefix}gf_addon_payment_transaction t
                         INNER JOIN {$wpdb->prefix}rg_lead l ON l.id = t.lead_id
                       WHERE l.form_id=%d AND l.status='active'
                       GROUP BY date
                     ) AS transaction on lead.date = transaction.date
                    ORDER BY date desc", $form_id, $form_id
			), ARRAY_A
		);

		$total_summary = $wpdb->get_results(
			$wpdb->prepare(
				"
                    SELECT sum( if(transaction_type = 1,1,0) ) as orders,
                           sum( if(transaction_type = 2,1,0) ) as subscriptions
                    FROM {$wpdb->prefix}rg_lead
                    WHERE form_id=%d AND status='active'", $form_id
			), ARRAY_A
		);

		$total_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"
                    SELECT sum( if(t.transaction_type = 'refund', abs(t.amount) * -1, t.amount) ) as revenue
                    FROM {$wpdb->prefix}gf_addon_payment_transaction t
                    INNER JOIN {$wpdb->prefix}rg_lead l ON l.id = t.lead_id
                    WHERE l.form_id=%d AND status='active'", $form_id
			)
		);


		$result = array(
			'today'     => array( 'revenue' => GFCommon::to_money( 0 ), 'orders' => 0, 'subscriptions' => 0 ),
			'yesterday' => array( 'revenue' => GFCommon::to_money( 0 ), 'orders' => 0, 'subscriptions' => 0 ),
			'last30'    => array( 'revenue' => 0, 'orders' => 0, 'subscriptions' => 0 ),
			'total'     => array(
				'revenue'       => GFCommon::to_money( $total_revenue ),
				'orders'        => $total_summary[0]['orders'],
				'subscriptions' => $total_summary[0]['subscriptions']
			)
		);

		$local_time = GFCommon::get_local_timestamp();
		$today      = gmdate( 'Y-m-d', $local_time );
		$yesterday  = gmdate( 'Y-m-d', strtotime( '-1 day', $local_time ) );

		foreach ( $summary as $day ) {
			if ( $day['date'] == $today ) {
				$result['today']['revenue']       = GFCommon::to_money( $day['revenue'] );
				$result['today']['orders']        = $day['orders'];
				$result['today']['subscriptions'] = $day['subscriptions'];
			} elseif ( $day['date'] == $yesterday ) {
				$result['yesterday']['revenue']       = GFCommon::to_money( $day['revenue'] );
				$result['yesterday']['orders']        = $day['orders'];
				$result['yesterday']['subscriptions'] = $day['subscriptions'];
			}

			$is_within_30_days = strtotime( $day['date'] ) >= strtotime( '-30 days', $local_time );
			if ( $is_within_30_days ) {
				$result['last30']['revenue'] += floatval( $day['revenue'] );
				$result['last30']['orders'] += floatval( $day['orders'] );
				$result['last30']['subscriptions'] += floatval( $day['subscriptions'] );
			}
		}

		$result['last30']['revenue'] = GFCommon::to_money( $result['last30']['revenue'] );

		return $result;
	}

	public function results_filter_ui( $filter_ui, $form_id, $page_title, $gf_page, $gf_view ) {

		if ( $gf_view == "gf_results_{$this->_slug}" ) {
			unset( $filter_ui['fields'] );
		}

		$view_markup = "<div>
                    <select id='gaddon-sales-group' name='group'>
                        <option value='daily' " . selected( 'daily', rgget( 'group' ), false ) . '>' . esc_html__( 'Daily', 'gravityforms' ) . "</option>
                        <option value='weekly' " . selected( 'weekly', rgget( 'group' ), false ) . '>' . esc_html__( 'Weekly', 'gravityforms' ) . "</option>
                        <option value='monthly' " . selected( 'monthly', rgget( 'group' ), false ) . '>' . esc_html__( 'Monthly', 'gravityforms' ) . '</option>
                    </select>
                  </div>';
		$view_filter = array(
			'view' => array(
				'label'   => esc_html__( 'View', 'gravityforms' ),
				'tooltip' => '<h6>' . esc_html__( 'View', 'gravityforms' ) . '</h6>' . esc_html__( 'Select how you would like the sales data to be displayed.', 'gravityforms' ),
				'markup'  => $view_markup
			)
		);

		$payment_methods = $this->get_payment_methods( $form_id );

		$payment_method_markup = "
                <div>
                    <select id='gaddon-sales-group' name='payment_method'>
                        <option value=''>" . esc_html__( _x( 'Any', 'regarding a payment method', 'gravityforms' ) ) . '</option>';

		foreach ( $payment_methods as $payment_method ) {
			$payment_method_markup .= "<option value='" . esc_attr( $payment_method ) . "' " . selected( $payment_method, rgget( 'payment_method' ), false ) . '>' . $payment_method . '</option>';
		}
		$payment_method_markup .= '
                    </select>
                 </div>';

		$payment_method_filter = array(
			'payment_method' => array(
				'label'   => esc_html__( 'Payment Method', 'gravityforms' ),
				'tooltip' => '',
				'markup'  => $payment_method_markup
			)
		);


		$filter_ui = array_merge( $view_filter, $payment_method_filter, $filter_ui );

		return $filter_ui;

	}

	protected function get_payment_methods( $form_id ) {
		global $wpdb;

		$payment_methods = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT payment_method FROM {$wpdb->prefix}rg_lead WHERE form_id=%d", $form_id ) );

		return array_filter( $payment_methods, array( $this, 'array_filter_non_blank' ) );
	}

	protected function array_filter_non_blank( $value ) {
		if ( empty( $value ) || $value == 'null' ) {
			return false;
		}

		return true;
	}


	//-------- Uninstall ---------------------
	protected function uninstall() {
		global $wpdb;

		// deleting transactions
		$sql = $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}gf_addon_payment_transaction
                                WHERE lead_id IN
                                   (SELECT lead_id FROM {$wpdb->prefix}rg_lead_meta WHERE meta_key='payment_gateway' AND meta_value=%s)", $this->_slug
		);
		$wpdb->query( $sql );

		// deleting callback log
		$sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}gf_addon_payment_callback WHERE addon_slug=%s", $this->_slug );
		$wpdb->query( $sql );

		//clear cron
		wp_clear_scheduled_hook( $this->_slug . '_cron' );

		parent::uninstall();
	}

	//-------- Scripts -----------------------
	public function scripts() {
		$min     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		$scripts = array(
			array(
				'handle'  => 'gaddon_payment',
				'src'     => $this->get_gfaddon_base_url() . "/js/gaddon_payment{$min}.js",
				'version' => GFCommon::$version,
				'strings' => array(
					'subscriptionCancelWarning' => __( "Warning! This subscription will be canceled. This cannot be undone. 'OK' to cancel subscription, 'Cancel' to stop", 'gravityforms' ),
					'subscriptionCancelNonce'   => wp_create_nonce( 'gaddon_cancel_subscription' ),
					'subscriptionCanceled'      => __( 'Canceled', 'gravityforms' ),
					'subscriptionError'         => __( 'The subscription could not be canceled. Please try again later.', 'gravityforms' )
				),
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ), 'tab' => $this->_slug ),
					array( 'admin_page' => array( 'entry_view' ) ),
				)
			),
			array(
				'handle'    => 'gaddon_token',
				'src'       => $this->get_gfaddon_base_url() . "/js/gaddon_token{$min}.js",
				'version'   => GFCommon::$version,
				'deps'      => array( 'jquery' ),
				'in_footer' => false,
				'enqueue'   => array(
					array( $this, 'enqueue_creditcard_token_script' )
				)
			),
			array(
				'handle'  => 'gform_form_admin',
				'enqueue' => array(
					array( 'admin_page' => array( 'entry_edit' ) ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}


	//----- Javascript Credit Card Tokens ----
	/**
	 * Override to support creating credit card tokens via Javascript.
	 *
	 * @access public
	 *
	 * @param mixed $form
	 *
	 * @return array
	 */
	public function creditcard_token_info( $form ) {

		return array();

	}

	/**
	 * Add input field for credit card token response.
	 *
	 * @access public
	 *
	 * @param string $content
	 * @param array $field
	 * @param string $value
	 * @param string $entry_id
	 * @param string $form_id
	 *
	 * @return string
	 */
	public function add_creditcard_token_input( $content, $field, $value, $entry_id, $form_id ) {

		if ( ! $this->has_feed( $form_id ) || GFFormsModel::get_input_type( $field ) != 'creditcard' ) {
			return $content;
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! $this->creditcard_token_info( $form ) ) {
			return $content;
		}

		$slug = str_replace( 'gravityforms', '', $this->_slug );
		$content .= '<input type=\'hidden\' name=\'' . $slug . '_response\' id=\'gf_' . $slug . '_response\' value=\'' . rgpost( $slug . '_response' ) . '\' />';

		return $content;

	}

	/**
	 * Enables AJAX for forms that create credit card tokens via Javascript.
	 *
	 * @access public
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function force_ajax_for_creditcard_tokens( $args ) {

		$form = GFAPI::get_form( rgar( $args, 'form_id' ) );

		$args['ajax'] = $this->enqueue_creditcard_token_script( $form ) ? true : $args['ajax'];

		return $args;

	}

	/**
	 * Determines if GFToken script should be enqueued.
	 *
	 * @access public
	 *
	 * @param array $form
	 *
	 * @return bool
	 */
	public function enqueue_creditcard_token_script( $form ) {

		return $form && $this->has_feed( $form['id'] ) && $this->creditcard_token_info( $form );

	}

	/**
	 * Prepare Javascript for creating credit card tokens.
	 *
	 * @access public
	 *
	 * @param array $form
	 * @param array $field_values
	 * @param bool $is_ajax
	 *
	 * @return void
	 */
	public function register_creditcard_token_script( $form, $field_values, $is_ajax ) {

		if ( ! $this->enqueue_creditcard_token_script( $form ) ) {
			return;
		}

		/* Prepare GFToken object. */
		$gftoken = array(
			'callback'      => 'GF_' . str_replace( ' ', '', $this->_short_title ),
			'feeds'         => $this->creditcard_token_info( $form ),
			'formId'        => rgar( $form, 'id' ),
			'hasPages'      => GFCommon::has_pages( $form ),
			'pageCount'     => GFFormDisplay::get_max_page_number( $form ),
			'responseField' => '#gf_' . str_replace( 'gravityforms', '', $this->_slug ) . '_response'
		);

		/* Get needed fields. */
		$gftoken['fields'] = $this->get_creditcard_token_entry_fields( $gftoken['feeds'] );

		$script = 'new GFToken( ' . json_encode( $gftoken ) . ' );';
		GFFormDisplay::add_init_script( $form['id'], 'GFToken', GFFormDisplay::ON_PAGE_RENDER, $script );

	}

	/**
	 * Get needed fields for creating credit card tokens.
	 *
	 * @access public
	 *
	 * @param array $feeds
	 *
	 * @return array $fields
	 */
	public function get_creditcard_token_entry_fields( $feeds ) {

		$fields = array();

		foreach ( $feeds as $feed ) {
			foreach ( $feed['billing_fields'] as $billing_field ) {
				$fields[] = $billing_field;
			}
		}

		return array_unique( $fields );

	}

	//-------- Currency ----------------------
	/**
	 * Override this function to add or remove currencies from the list of supported currencies
	 *
	 * @param $currencies - Currently supported currencies
	 *
	 * @return mixed - A filtered list of supported currencies
	 */
	public function supported_currencies( $currencies ) {
		return $currencies;
	}

	/**
	 * Retrieve the currency object for the specified currency code.
	 *
	 * @param string $currency_code
	 *
	 * @return RGCurrency
	 */
	public function get_currency( $currency_code = '' ) {
		if ( ! class_exists( 'RGCurrency' ) ) {
			require_once( GFCommon::get_base_path() . '/currency.php' );
		}

		if ( empty( $currency_code ) ) {
			$currency_code = GFCommon::get_currency();
		}

		return new RGCurrency( $currency_code );
	}

	/**
	 * Format the amount for export to the payment gateway.
	 *
	 * Removes currency symbol and if required converts the amount to the smallest unit required by the gateway (e.g. dollars to cents).
	 *
	 * @param int|float $amount The value to be formatted.
	 * @param string $currency_code The currency code.
	 *
	 * @return int|float
	 */
	public function get_amount_export( $amount, $currency_code = '' ) {
		$currency = $this->get_currency( $currency_code );
		$amount   = $currency->to_number( $amount );

		if ( $this->_requires_smallest_unit && ! $currency->is_zero_decimal() ) {
			return $amount * 100;
		}

		return $amount;
	}

	/**
	 * If necessary convert the amount back from the smallest unit required by the gateway (e.g cents to dollars).
	 *
	 * @param int|float $amount The value to be formatted.
	 * @param string $currency_code The currency code.
	 *
	 * @return int|float
	 */
	public function get_amount_import( $amount, $currency_code = '' ) {
		$currency = $this->get_currency( $currency_code );

		if ( $this->_requires_smallest_unit && ! $currency->is_zero_decimal() ) {
			return $amount / 100;
		}

		return $amount;
	}


	//-------- Cancel Subscription -----------
	public function entry_info( $form_id, $entry ) {

		//abort if subscription cancellation isn't supported by the addon or if it has already been canceled
		if ( ! $this->payment_method_is_overridden( 'cancel' ) ) {
			return;
		}

		// adding cancel subscription button and script to entry info section
		$cancelsub_button = '';
		if ( $entry['transaction_type'] == '2' && $entry['payment_status'] <> 'Cancelled' && $this->is_payment_gateway( $entry['id'] ) ) {
			?>
			<input id="cancelsub" type="button" name="cancelsub"
			       value="<?php esc_html_e( 'Cancel Subscription', 'gravityforms' ) ?>" class="button"
			       onclick="cancel_subscription(<?php echo absint( $entry['id'] ); ?>);"/>
			<img src="<?php echo GFCommon::get_base_url() ?>/images/spinner.gif" id="subscription_cancel_spinner"
			     style="display: none;"/>

			<script type="text/javascript">

			</script>

			<?php
		}
	}

	/**
	 * Target of gform_delete_lead hook. Deletes all transactions and callbacks when an entry is deleted.
	 *
	 * @param $entry_id . ID of entry that is being deleted
	 */
	public function entry_deleted( $entry_id ) {
		global $wpdb;

		//deleting from transaction table
		$wpdb->delete( "{$wpdb->prefix}gf_addon_payment_transaction", array( 'lead_id' => $entry_id ), array( '%d' ) );

		//deleting from callback table
		$wpdb->delete( "{$wpdb->prefix}gf_addon_payment_callback", array( 'lead_id' => $entry_id ), array( '%d' ) );
	}

	public function disable_entry_info_payment( $is_enabled, $entry ) {

		$is_my_entry = $this->is_payment_gateway( $entry['id'] );

		return $is_my_entry ? false : $is_enabled;
	}

	public function ajax_cancel_subscription() {
		check_ajax_referer( 'gaddon_cancel_subscription', 'gaddon_cancel_subscription' );

		$entry_id = $_POST['entry_id'];
		$entry    = GFAPI::get_entry( $entry_id );

		$form = GFAPI::get_form( $entry['form_id'] );
		$feed = $this->get_payment_feed( $entry, $form );

		//This addon does not have a payment feed. Abort.
		if ( empty ( $feed ) ){
			return;
		}

		if ( $this->cancel( $entry, $feed ) ) {
			$this->cancel_subscription( $entry, $feed );
			die( '1' );
		} else {
			die( '0' );
		}

	}

	/**
	 * Target of gform_before_delete_field hook. Sets relevant payment feeds to inactive when the credit card field is deleted.
	 *
	 * @param $form_id . ID of the form being edited.
	 * @param $field_id . ID of the field being deleted.
	 */
	public function before_delete_field( $form_id, $field_id ) {
		if ( $this->_requires_credit_card ) {
			$form  = GFAPI::get_form( $form_id );
			$field = $this->get_credit_card_field( $form );

			if ( is_object( $field ) && $field->id == $field_id ) {
				$feeds = $this->get_feeds( $form_id );
				foreach ( $feeds as $feed ) {
					if ( $feed['is_active'] ) {
						$this->update_feed_active( $feed['id'], 0 );
					}
				}
			}
		}
	}


	// # HELPERS

	private function payment_method_is_overridden( $method_name, $base_class = 'GFPaymentAddOn' ) {
		return parent::method_is_overridden( $method_name, $base_class );
	}

	public function authorization_error( $error_message ) {
		return array( 'error_message' => $error_message, 'is_success' => false, 'is_authorized' => false );
	}

	public function remove_spaces_from_card_number( $card_number ) {
		$card_number = str_replace( array( "\t", "\n", "\r", ' ' ), '', $card_number );

		return $card_number;
	}
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


class GFPaymentStatsTable extends WP_List_Table {

	private $_rows = array();
	private $_page_size = 10;
	private $_total_items = 0;

	function __construct( $columns, $rows, $total_count, $page_size ) {
		$this->_rows        = $rows;
		$this->_total_items = $total_count;
		$this->_page_size   = $page_size;

		$this->_column_headers = array(
			$columns,
			array(),
			array(),
			rgar( array_values( $columns ), 2 ),
		);

		parent::__construct(
			array(
				'singular' => esc_html__( 'sale', 'gravityforms' ),
				'plural'   => esc_html__( 'sales', 'gravityforms' ),
				'ajax'     => false,
				'screen'   => 'gaddon_sales',
			)
		);
	}

	function prepare_items() {
		$this->items = $this->_rows;

		$this->set_pagination_args( array( 'total_items' => $this->_total_items, 'per_page' => $this->_page_size ) );
	}

	function no_items() {
		esc_html_e( "There hasn't been any sales in the specified date range.", 'gravityforms' );
	}

	function get_columns() {
		return $this->_column_headers[0];
	}

	function column_default( $item, $column ) {
		return rgar( $item, $column );
	}

	function column_revenue( $item ) {
		return GFCommon::to_money( $item['revenue'] );
	}

	function pagination( $which ) {
		if ( empty( $this->_pagination_args ) ) {
			return;
		}

		$total_items = $this->get_pagination_arg( 'total_items' );
		$total_pages = $this->get_pagination_arg( 'total_pages' );

		$output = '<span class="displaying-num">' . sprintf( _n( '1 item', '%s items', $total_items, 'gravityforms' ), number_format_i18n( $total_items ) ) . '</span>';

		$current = $this->get_pagenum();

		$page_links = array();

		$disable_first = $disable_last = '';
		if ( $current == 1 ) {
			$disable_first = ' disabled';
		}
		if ( $current == $total_pages ) {
			$disable_last = ' disabled';
		}

		$page_links[] = sprintf(
			"<a class='%s' title='%s' style='cursor:pointer;' onclick='gresults.setCustomFilter(\"paged\", \"1\"); gresults.getResults();'>%s</a>",
			'first-page' . $disable_first,
			esc_attr__( 'Go to the first page', 'gravityforms' ),
			'&laquo;'
		);

		$page_links[] = sprintf(
			"<a class='%s' title='%s' style='cursor:pointer;' onclick='gresults.setCustomFilter(\"paged\", \"%s\"); gresults.getResults(); gresults.setCustomFilter(\"paged\", \"1\");'>%s</a>",
			'prev-page' . $disable_first,
			esc_attr__( 'Go to the previous page', 'gravityforms' ),
			max( 1, $current - 1 ),
			'&lsaquo;'
		);


		$html_current_page = $current;

		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[]     = '<span class="paging-input">' . sprintf( esc_html_x( '%1$s of %2$s', 'paging', 'gravityforms' ), $html_current_page, $html_total_pages ) . '</span>';

		$page_links[] = sprintf(
			"<a class='%s' title='%s' style='cursor:pointer;' onclick='gresults.setCustomFilter(\"paged\", \"%s\"); gresults.getResults(); gresults.setCustomFilter(\"paged\", \"1\");'>%s</a>",
			'next-page' . $disable_last,
			esc_attr__( 'Go to the next page', 'gravityforms' ),
			min( $total_pages, $current + 1 ),
			'&rsaquo;'
		);

		$page_links[] = sprintf(
			"<a class='%s' title='%s' style='cursor:pointer;' onclick='gresults.setCustomFilter(\"paged\", \"%s\"); gresults.getResults(); gresults.setCustomFilter(\"paged\", \"1\");'>%s</a>",
			'last-page' . $disable_last,
			esc_attr__( 'Go to the last page', 'gravityforms' ),
			$total_pages,
			'&raquo;'
		);

		$pagination_links_class = 'pagination-links';
		if ( ! empty( $infinite_scroll ) ) {
			$pagination_links_class = ' hide-if-js';
		}
		$output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

		if ( $total_pages ) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		} else {
			$page_class = ' no-pages';
		}

		$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

		echo $this->_pagination;
	}

}
