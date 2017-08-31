<?php
/*
Plugin Name: PayMaya Checkout WooCommerce Gateway
Plugin URI: https://developers.paymaya.com/
Description: PayMaya Checkout payment page extension for WooCommerce.
Version: 1.5.5
Author: PayMaya Philippines Inc
Author URI: https://developers.paymaya.com/
*/

require_once __DIR__ . '/woocommerce-custom-order-data/woocommerce-custom-order-data.php';
register_activation_hook( __FILE__,'woocommerce_custom_order_data_activate');

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'paymaya_checkout_init', 0);
function paymaya_checkout_init() {
  // If the parent WC_Payment_Gateway class doesn't exist
  // it means WooCommerce is not installed on the site
  // so do nothing
  if (!class_exists('WC_Payment_Gateway')) return;

  // If we made it this far, then include our Gateway Class
  include_once('paymaya-checkout.php');

  // Now that we have successfully included our class,
  // Lets add it too WooCommerce
  add_filter('woocommerce_payment_gateways', 'paymaya_checkout_add_gateway');
  function paymaya_checkout_add_gateway($methods) {
    $methods[] = 'PayMaya_Checkout';
    return $methods;
  }
}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), 'paymaya_checkout_action_links');
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

  $raw_checkout_input = file_get_contents("php://input");
  $checkout = json_decode($raw_checkout_input);

  $checkoutGateway->logStart();
  $checkoutGateway->log("Checkout ID: " . $checkout->id);
  $checkoutGateway->log("Checkout RRN: " . $checkout->requestReferenceNumber);

  if(isset($checkout->requestReferenceNumber)) {
    try {
      $order = new WC_Order( $checkout->requestReferenceNumber );
      WC_CustomOrderData::extend( $order );

      $checkoutGateway->log( "Checkout Order: " . $order->custom->checkout_id );
      $checkoutGateway->log( "Checkout Nonce: " . $order->custom->nonce );
    } catch ( Exception $e ) {
      // TODO: Log invalid order.
      $checkoutGateway->log( "Order Exception: " . $e->getMessage(), "error" );
    }

    $checkoutGateway->log( "Webhook Token: " . $_GET['wht'] );

    if(strcmp( $_GET['wht'], $checkoutGateway->webhook_token ) == 0 && isset( $checkout->id )) {

      $checkoutGateway->log( "Checkout Status: " . $checkout->status );
      $checkoutGateway->log( "Checkout Payment Status: " . $checkout->paymentStatus );

      if($checkout->status == "COMPLETED" && $checkout->paymentStatus == "PAYMENT_SUCCESS") {
        // Empty cart.
        $order->payment_complete();
        $woocommerce->cart->empty_cart();

        $checkoutGateway->log("Order " . $checkout->requestReferenceNumber . " set to completed and emptied.");
      }

      else {
        $checkoutGateway->log( "** Failed to completed order. **" );
      }

      $checkoutGateway->log( "Webhook execution completed for " . $checkout->id );
    }
  }

  $checkoutGateway->logEnd();

  echo json_encode(array(
    'message' => 'nop'
  ));
}
add_action('woocommerce_api_paymaya_checkout_handler', 'paymaya_checkout_handler_webhook');
