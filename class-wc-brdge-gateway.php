<?php
/**
 * Class WC_BRDge_Gateway
 *
 * WooCommerce BR-DGE Payment Gateway integration.
 *
 * @package WooCommerce_BR-DGE
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_BRDge_Gateway
 */
class WC_BRDge_Gateway extends WC_Payment_Gateway {

	/**
	 * Whether gateway is in test mode.
	 *
	 * @var bool
	 */
	protected bool $testmode;

	/**
	 * Server API key for BR-DGE.
	 *
	 * @var string
	 */
	protected string $server_api_key;

	/**
	 * Client API key for BR-DGE.
	 *
	 * @var string
	 */
	protected string $client_api_key;

	/**
	 * Webhook secret for validating incoming requests.
	 *
	 * @var string
	 */
	protected string $webhook_secret;

	/**
	 * API endpoint to use.
	 *
	 * @var string
	 */
	protected string $api_endpoint;

	/**
	 * WC_BRDge_Gateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'brdge';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'BR-DGE Payment Gateway', 'wc-brdge-gateway' );
		$this->method_description = __( 'Accept payments through BR-DGE payment orchestration platform', 'wc-brdge-gateway' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->enabled        = $this->get_option( 'enabled' );
		$this->testmode       = ( 'yes' === $this->get_option( 'testmode' ) );
		$this->server_api_key = $this->testmode ? $this->get_option( 'test_server_api_key' ) : $this->get_option( 'live_server_api_key' );
		$this->client_api_key = $this->testmode ? $this->get_option( 'test_client_api_key' ) : $this->get_option( 'live_client_api_key' );
		$this->webhook_secret = $this->get_option( 'webhook_secret' );

		$this->api_endpoint = $this->testmode ? 'https://sandbox.comcarde.com/v1' : 'https://secure.comcarde.com/v1';

		$this->supports = array(
			'products',
			'refunds',
		);

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
	}

	/**
	 * Initialise gateway settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'             => array(
				'title'   => __( 'Enable/Disable', 'wc-brdge-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable BR-DGE Payment Gateway', 'wc-brdge-gateway' ),
				'default' => 'no',
			),
			'title'               => array(
				'title'       => __( 'Title', 'wc-brdge-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-brdge-gateway' ),
				'default'     => __( 'BR-DGE Payment Gateway', 'wc-brdge-gateway' ),
				'desc_tip'    => true,
			),
			'description'         => array(
				'title'       => __( 'Description', 'wc-brdge-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-brdge-gateway' ),
				'default'     => __( 'Pay securely using your credit/debit card or digital wallet.', 'wc-brdge-gateway' ),
				'desc_tip'    => true,
			),
			'testmode'            => array(
				'title'       => __( 'Test mode', 'wc-brdge-gateway' ),
				'label'       => __( 'Enable Test Mode', 'wc-brdge-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'wc-brdge-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_server_api_key' => array(
				'title'       => __( 'Test Server API Key', 'wc-brdge-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your BR-DGE account.', 'wc-brdge-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_client_api_key' => array(
				'title'       => __( 'Test Client API Key', 'wc-brdge-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your BR-DGE account.', 'wc-brdge-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_server_api_key' => array(
				'title'       => __( 'Live Server API Key', 'wc-brdge-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your BR-DGE account.', 'wc-brdge-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_client_api_key' => array(
				'title'       => __( 'Live Client API Key', 'wc-brdge-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your API keys from your BR-DGE account.', 'wc-brdge-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_secret'      => array(
				'title'       => __( 'Webhook Secret', 'wc-brdge-gateway' ),
				'type'        => 'password',
				'description' => __( 'Optional webhook secret for validating webhook signatures.', 'wc-brdge-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Enqueue payment scripts on relevant pages.
	 *
	 * @return void
	 */
	public function payment_scripts() {
		if (
			is_admin()
			|| ( ! is_cart() && ! is_checkout() )
			|| 'no' === $this->enabled
			|| empty( $this->client_api_key )
		) {
			return;
		}

		wp_enqueue_style(
			'wc-brdge-gateway-style',
			WC_BRDGE_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			WC_BRDGE_VERSION
		);

		$sdk_url = $this->testmode
			? 'https://sandbox-assets.comcarde.com/web/v2/js/comcarde.min.js'
			: 'https://assets.comcarde.com/web/v2/js/comcarde.min.js';

		wp_enqueue_script( 'brdge-sdk', $sdk_url, array(), WC_BRDGE_VERSION, true );
		wp_enqueue_script(
			'wc-brdge-gateway',
			WC_BRDGE_PLUGIN_URL . 'assets/js/checkout.js',
			array( 'jquery', 'brdge-sdk' ),
			WC_BRDGE_VERSION,
			true
		);

		wp_localize_script(
			'wc-brdge-gateway',
			'wc_brdge_params',
			array(
				'client_api_key' => $this->client_api_key,
				'testmode'       => $this->testmode,
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wc_brdge_nonce' ),
			)
		);
	}

	/**
	 * Output custom fields at checkout.
	 *
	 * @return void
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( $this->description ) );
		}

		echo '<div id="brdge-card-element" style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></div>';
		echo '<div id="brdge-card-errors" style="color: #e74c3c; margin-top: 10px;"></div>';
		echo '<input type="hidden" id="brdge-payment-token" name="brdge_payment_token" />';
		wp_nonce_field( 'wc_brdge_process_payment', 'wc_brdge_nonce' );
	}

	/**
	 * Validate fields before processing payment.
	 *
	 * @return bool
	 */
	public function validate_fields() {

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce safe.
		if ( ! isset( $_POST['wc_brdge_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wc_brdge_nonce'] ), 'wc_brdge_process_payment' ) ) {
			wc_add_notice( __( 'Security check failed. Please refresh and try again.', 'wc-brdge-gateway' ), 'error' );
			return false;
		}

		if ( empty( $_POST['brdge_payment_token'] ) ) {
			wc_add_notice(
				__( 'Payment error: Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'wc-brdge-gateway' ),
				'error'
			);
			return false;
		}

		return true;
	}
	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array|void
	 */
	public function process_payment( $order_id ) {

		$this->log( "Starting payment processing for order ID: $order_id" );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce safe.
		if ( ! isset( $_POST['wc_brdge_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wc_brdge_nonce'] ), 'wc_brdge_process_payment' ) ) {
			wc_add_notice( __( 'Security check failed. Please refresh and try again.', 'wc-brdge-gateway' ), 'error' );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( isset( $_POST['brdge_payment_token'] ) ) {
			$payment_token = sanitize_text_field( wp_unslash( $_POST['brdge_payment_token'] ) );
		} else {
			wc_add_notice( __( 'Payment error: Missing payment token.', 'wc-brdge-gateway' ), 'error' );
			return;
		}
		$payment_data = array(
			'amount'            => array(
				'value'    => intval( $order->get_total() * 100 ),
				'currency' => $order->get_currency(),
			),
			'paymentInstrument' => array(
				'type'  => 'paymentToken',
				'token' => $payment_token,
			),
			'reference'         => $order->get_order_number(),
			// Translators: order number, site name.
			'description'       => sprintf( __( 'Order %1$s from %2$s', 'wc-brdge-gateway' ), $order->get_order_number(), get_bloginfo( 'name' ) ),
			'billingAddress'    => $this->get_billing_address( $order ),
			'shippingAddress'   => $this->get_shipping_address( $order ),
			'customer'          => array(
				'email'     => $order->get_billing_email(),
				'firstName' => $order->get_billing_first_name(),
				'lastName'  => $order->get_billing_last_name(),
				'phone'     => $order->get_billing_phone(),
			),
			'metadata'          => array(
				'order_id'    => $order_id,
				'order_key'   => $order->get_order_key(),
				'woocommerce' => true,
			),
		);

		$response = $this->make_api_request( 'payments', $payment_data, 'POST' );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Payment failed with error: ' . $response->get_error_message(), 'error' );
			wc_add_notice( __( 'Payment error: ', 'wc-brdge-gateway' ) . $response->get_error_message(), 'error' );
			return;
		}

		$payment_response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$error_message = $payment_response['message'] ?? __( 'Payment failed', 'wc-brdge-gateway' );
			wc_add_notice( __( 'Payment error: ', 'wc-brdge-gateway' ) . $error_message, 'error' );
			return;
		}

		if ( isset( $payment_response['id'] ) ) {
			$order->update_meta_data( '_brdge_payment_id', $payment_response['id'] );
		}

		if ( isset( $payment_response['status'] ) ) {
			switch ( $payment_response['status'] ) {
				case 'COMPLETED':
				case 'CAPTURED':
					$order->payment_complete( $payment_response['id'] );
					// Translators: Payment ID.
					$order->add_order_note( sprintf( __( 'BR-DGE payment completed. Payment ID: %s', 'wc-brdge-gateway' ), $payment_response['id'] ) );
					break;

				case 'PENDING':
				case 'AUTHORIZED':
					// Translators: Payment ID.
					$order->update_status( 'on-hold', sprintf( __( 'BR-DGE payment pending. Payment ID: %s', 'wc-brdge-gateway' ), $payment_response['id'] ) );
					break;

				case 'REQUIRES_ACTION':
					if ( isset( $payment_response['action'] ) ) {
						return $this->handle_payment_action( $order, $payment_response );
					}
					break;

				default:
					wc_add_notice( __( 'Payment status unknown. Please contact support.', 'wc-brdge-gateway' ), 'error' );
					return;
			}
		}

		$order->save();

		$this->log( "Payment processing completed for order ID: $order_id with status: " . $payment_response['status'] );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Handle 3D Secure or redirect payment actions.
	 *
	 * @param WC_Order $order           WooCommerce order.
	 * @param array    $payment_response Payment response data.
	 * @return array|void
	 */
	private function handle_payment_action( $order, $payment_response ) {
		$action = $payment_response['action'];

		if ( 'redirect' === $action['type'] ) {
			$order->update_meta_data( '_brdge_payment_action', $action );
			$order->update_meta_data( '_brdge_payment_id', $payment_response['id'] );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $action['url'],
			);
		}

		wc_add_notice( __( 'Payment requires additional verification. Please try again.', 'wc-brdge-gateway' ), 'error' );
	}

	/**
	 * Process a refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Refund amount.
	 * @param string $reason   Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$this->log( "Starting refund for order ID: $order_id, amount: $amount" );

		$order      = wc_get_order( $order_id );
		$payment_id = $order->get_meta( '_brdge_payment_id' );

		if ( empty( $payment_id ) ) {
			return new WP_Error( 'refund_error', __( 'Payment ID not found. Cannot process refund.', 'wc-brdge-gateway' ) );
		}

		$refund_data = array(
			'amount' => array(
				'value'    => $amount ? intval( $amount * 100 ) : intval( $order->get_total() * 100 ),
				'currency' => $order->get_currency(),
			),
			'reason' => ! empty( $reason ) ? $reason : __( 'Refund via WooCommerce', 'wc-brdge-gateway' ),
		);

		$response = $this->make_api_request( "payments/$payment_id/refund", $refund_data, 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$refund_response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === wp_remote_retrieve_response_code( $response ) && isset( $refund_response['status'] ) ) {

			$this->log( "Refund successful for order ID: $order_id, refund ID: " . $refund_response['id'] );

			// Translators: Refund ID.
			$order->add_order_note( sprintf( __( 'BR-DGE refund completed. Refund ID: %s', 'wc-brdge-gateway' ), $refund_response['id'] ) );
			return true;
		} else {
			$this->log( "Refund failed for order ID: $order_id", 'error' );
		}

		return new WP_Error( 'refund_error', __( 'Refund failed. Please check your BR-DGE account.', 'wc-brdge-gateway' ) );
	}

	/**
	 * Make an API request to BR-DGE.
	 *
	 * @param string $endpoint Endpoint path.
	 * @param array  $data     Request payload.
	 * @param string $method   HTTP method.
	 * @return array|WP_Error
	 */
	private function make_api_request( $endpoint, $data = array(), $method = 'GET' ) {
		$url = $this->api_endpoint . '/' . ltrim( $endpoint, '/' );

		$this->log( sprintf( 'Making %s request to %s with data: %s', $method, $url, wp_json_encode( $data ) ) );

		$args = array(
			'method'  => $method,
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->server_api_key,
				'Content-Type'  => 'application/json',
			),
		);

		if ( 'GET' !== $method && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		// Log the response.
		if ( is_wp_error( $response ) ) {
			$this->log( 'API Request Error: ' . $response->get_error_message(), 'error' );
		} else {
			$this->log( sprintf( 'API Response (%d): %s', wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) ) );
		}

		return $response;
	}

	/**
	 * Get the billing address.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array
	 */
	private function get_billing_address( $order ) {
		return array(
			'firstName'  => $order->get_billing_first_name(),
			'lastName'   => $order->get_billing_last_name(),
			'line1'      => $order->get_billing_address_1(),
			'line2'      => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'postalCode' => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
		);
	}

	/**
	 * Get the shipping address or fall back to billing address.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array
	 */
	private function get_shipping_address( $order ) {
		if ( ! $order->needs_shipping_address() ) {
			return $this->get_billing_address( $order );
		}

		return array(
			'firstName'  => '' !== $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name(),
			'lastName'   => '' !== $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name(),
			'line1'      => '' !== $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
			'line2'      => '' !== $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
			'city'       => '' !== $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city(),
			'state'      => '' !== $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state(),
			'postalCode' => '' !== $order->get_shipping_postcode() ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
			'country'    => '' !== $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country(),
		);
	}

	/**
	 * Output thank you message.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		$order      = wc_get_order( $order_id );
		$payment_id = $order->get_meta( '_brdge_payment_id' );

		if ( $payment_id ) {
			echo '<p><strong>' . esc_html__( 'Payment ID:', 'wc-brdge-gateway' ) . '</strong> ' . esc_html( $payment_id ) . '</p>';
		}
	}

	/**
	 * Output receipt message.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order ) {
			$payment_id = $order->get_meta( '_brdge_payment_id' );
			if ( $payment_id ) {
				echo '<p><strong>' . esc_html__( 'Payment ID:', 'wc-brdge-gateway' ) . '</strong> ' . esc_html( $payment_id ) . '</p>';
			}
		}

		echo '<p>' . esc_html__( 'Thank you for your order, please click the button below to pay with BR-DGE.', 'wc-brdge-gateway' ) . '</p>';
	}

	/**
	 * Logging function for WC logger.
	 *
	 * @param string $message The message to be logged.
	 * @param string $level Message level (emergency|alert|critical|error|warning|notice|info|debug).
	 *
	 * @return void
	 */
	private function log( $message, $level = 'info' ) {
		// Only log in test mode or if WP_DEBUG is enabled.
		if ( $this->testmode || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			$logger = wc_get_logger();
			$logger->$level( $message, array( 'source' => 'brdge-gateway' ) );
		}
	}
}
