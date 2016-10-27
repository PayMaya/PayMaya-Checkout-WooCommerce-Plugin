<?php
/*
Plugin Name: PayMaya Checkout WooCommerce Gateway
Plugin URI: https://developers.paymaya.com/
Description: PayMaya Checkout payment page extension for WooCommerce.
Version: 1.0
Author: Diwa del Mundo, Voyager Innovations
Author URI: https://developers.paymaya.com/
*/

require_once __DIR__ . '/woocommerce-custom-order-data/woocommerce-custom-order-data.php';
register_activation_hook( __FILE__,'woocommerce_custom_order_data_activate');

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'paymaya_checkout_init', 0 );
function paymaya_checkout_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	// If we made it this far, then include our Gateway Class
	include_once( 'paymaya-checkout.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'paymaya_checkout_add_gateway' );
	function paymaya_checkout_add_gateway($methods ) {
		$methods[] = 'PayMaya_Checkout';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'paymaya_checkout_action_links' );
function paymaya_checkout_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'paymaya_checkout' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}

function paymaya_checkout_handler_webhook() {
    global $woocommerce;

    $checkoutGateway = new PayMaya_Checkout();
    $order = new WC_Order($_GET['amp;cid']);

    if(!empty($order->post)) {
        WC_CustomOrderData::extend($order);

        if(strcmp($_GET['amp;n'], $order->custom->nonce) == 0) {
            $checkout_id = $order->custom->checkout_id;

            \PayMaya\PayMayaSDK::getInstance()->initCheckout($checkoutGateway->public_facing_api_key, $checkoutGateway->secret_api_key, "sandbox");
            $checkout = new PayMaya\API\Checkout();
            $checkout->id = $checkout_id;
            $checkout->retrieve();

            if($checkout->status == "COMPLETED" && $checkout->paymentStatus == "PAYMENT_SUCCESS") {
                // Empty cart.
                $order->add_order_note( __( 'PayMaya Checkout payment completed.', 'paymaya-checkout' ) );
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
            }

            else {
                wc_add_notice( "Payment failed.", 'error' );
                $order->add_order_note( 'PayMaya Checkout payment failed. Status: ' .  $checkout->status . " Payment Status: " . $checkout->paymentStatus);
            }

            wp_redirect($checkoutGateway->get_return_url($order));
            exit(0);
        }
    }
}
add_action( 'woocommerce_api_paymaya_checkout_handler', 'paymaya_checkout_handler_webhook' );
