<?php
/**
 * Plugin Name:       WooCommerce BR-DGE Payment Gateway
 * Plugin URI:        https://br-dge.io
 * Description:       Accept payments through BR-DGE payment orchestration platform.
 * Version:           1.0.0
 * Author:            Serious Systems
 * Text Domain:       wc-brdge-gateway
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.4
 * WC requires at least: 5.0
 * WC tested up to:      8.4
 *
 * @package WooCommerce_BRDGE_Gateway
 * @noinspection PhpDefineCanBeReplacedWithConstInspection
 */

defined( 'ABSPATH' ) || exit;

// Bail early if WooCommerce is not active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ), true ), true ) ) {
	return;
}

// Define plugin constants.
define( 'WC_BRDGE_VERSION', '1.0.0' );
define( 'WC_BRDGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_BRDGE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Initialise the BR-DGE gateway after plugins are loaded.
 *
 * @return void
 */
function wc_brdge_gateway_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once WC_BRDGE_PLUGIN_PATH . 'class-wc-brdge-gateway.php';

	add_filter( 'woocommerce_payment_gateways', 'wc_add_brdge_gateway' );
}
add_action( 'plugins_loaded', 'wc_brdge_gateway_init' );

/**
 * Register the BR-DGE gateway with WooCommerce.
 *
 * @param array $gateways Existing payment gateways.
 *
 * @return array
 */
function wc_add_brdge_gateway( $gateways ) {
	$gateways[] = 'WC_BRDge_Gateway';
	return $gateways;
}

/**
 * Register the REST API webhook endpoint for BR-DGE.
 *
 * @return void
 */
function wc_brdge_register_webhook_endpoint() {
	register_rest_route(
		'wc-brdge/v1',
		'/webhook',
		array(
			'methods'             => 'POST',
			'callback'            => 'wc_brdge_handle_webhook',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'wc_brdge_register_webhook_endpoint' );

/**
 * Handle incoming webhook requests from BR-DGE.
 *
 * @param WP_REST_Request $request The incoming REST request.
 *
 * @return WP_Error|WP_REST_Response
 */
function wc_brdge_handle_webhook( WP_REST_Request $request ) {
	// Ensure WooCommerce is loaded.
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return new WP_Error( 'woocommerce_not_loaded', 'WooCommerce not available', array( 'status' => 500 ) );
	}

	if ( get_option( 'woocommerce_brdge_settings' )['testmode'] ?? false ) {
		$logger = wc_get_logger();
		$logger->info( 'Webhook received: ' . $request->get_body(), array( 'source' => 'brdge-webhook' ) );
	}

	$payload = $request->get_json_params();
	$headers = $request->get_headers();

	// Get gateway settings.
	$gateway_settings = get_option( 'woocommerce_brdge_settings', array() );
	$webhook_secret   = $gateway_settings['webhook_secret'] ?? '';

	// Validate webhook signature if secret is set.
	if ( ! empty( $webhook_secret ) ) {
		$signature          = isset( $headers['x_brdge_validation'] ) ? $headers['x_brdge_validation'][0] : '';
		$expected_signature = hash_hmac( 'sha256', $request->get_body(), $webhook_secret );

		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return new WP_Error( 'invalid_signature', 'Invalid webhook signature', array( 'status' => 401 ) );
		}
	}

	// Process webhook based on event type.
	if ( isset( $payload['type'] ) && isset( $payload['data'] ) ) {
		switch ( $payload['type'] ) {
			case 'payment.completed':
			case 'payment.captured':
				wc_brdge_handle_payment_completed( $payload['data'] );
				break;

			case 'payment.failed':
				wc_brdge_handle_payment_failed( $payload['data'] );
				break;

			case 'refund.completed':
				wc_brdge_handle_refund_completed( $payload['data'] );
				break;
		}
	}

	return new WP_REST_Response( 'OK', 200 );
}

/**
 * Handle completed payment via webhook.
 *
 * @param array $payment_data Payment data.
 * @return void
 */
function wc_brdge_handle_payment_completed( $payment_data ) {

	if ( get_option( 'woocommerce_brdge_settings' )['testmode'] ?? false ) {
		$logger = wc_get_logger();
		$logger->info( 'Processing payment completed webhook for order: ' . ( $payment_data['metadata']['order_id'] ?? 'unknown' ), array( 'source' => 'brdge-webhook' ) );
	}

	if ( ! isset( $payment_data['metadata']['order_id'] ) ) {
		return;
	}

	$order_id = intval( $payment_data['metadata']['order_id'] );
	$order    = wc_get_order( $order_id );

	if ( ! $order || $order->has_status( array( 'completed', 'processing' ) ) ) {
		return;
	}

	$order->payment_complete( $payment_data['id'] );
	// Translators: Payment ID.
	$order->add_order_note( sprintf( __( 'BR-DGE payment completed via webhook. Payment ID: %s', 'wc-brdge-gateway' ), $payment_data['id'] ) );
}

/**
 * Handle failed payment via webhook.
 *
 * @param array $payment_data Payment data.
 * @return void
 */
function wc_brdge_handle_payment_failed( $payment_data ) {
	if ( ! isset( $payment_data['metadata']['order_id'] ) ) {
		return;
	}

	$order_id = intval( $payment_data['metadata']['order_id'] );
	$order    = wc_get_order( $order_id );

	if ( ! $order ) {
		return;
	}

	// Translators: Payment ID.
	$order->update_status( 'failed', sprintf( __( 'BR-DGE payment failed via webhook. Payment ID: %s', 'wc-brdge-gateway' ), $payment_data['id'] ) );
}

/**
 * Handle completed refund via webhook.
 *
 * @param array $refund_data Refund data.
 * @return void
 */
function wc_brdge_handle_refund_completed( $refund_data ) {
	// Handle refund completion if needed.
}

/**
 * Add settings link to the plugin actions list.
 *
 * @param array $links Plugin action links.
 *
 * @return array
 */
function wc_brdge_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=brdge' ) ) . '">' . esc_html__( 'Settings', 'wc-brdge-gateway' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_brdge_plugin_action_links' );

/**
 * Plugin activation hook.
 *
 * @return void
 */
function wc_brdge_activate() {
	update_option( 'wc_brdge_webhook_endpoint', get_rest_url( null, 'wc-brdge/v1/webhook' ) );

	if ( ! get_option( 'woocommerce_brdge_settings' ) ) {
		$default_settings = array(
			'enabled'     => 'no',
			'title'       => 'BR-DGE Payment Gateway',
			'description' => 'Pay securely using your credit/debit card or digital wallet.',
		);
		update_option( 'woocommerce_brdge_settings', $default_settings );
	}
}
register_activation_hook( __FILE__, 'wc_brdge_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function wc_brdge_deactivate() {
	delete_option( 'wc_brdge_webhook_endpoint' );
}
register_deactivation_hook( __FILE__, 'wc_brdge_deactivate' );
