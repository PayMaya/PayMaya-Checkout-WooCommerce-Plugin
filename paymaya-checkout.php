<?php

require_once __DIR__ . '/PayMaya-PHP-SDK-master/sample/autoload.php';
require_once __DIR__ . '/PayMaya-PHP-SDK-master/sample/Checkout/User.php';

class PayMaya_Checkout extends WC_Payment_Gateway {

  function __construct() {
    $this->id = "paymaya_checkout";

    $this->method_title = __( "PayMaya Checkout", 'paymaya-checkout' );
    $this->method_description = __( "PayMaya Checkout Payment Gateway Plug-in for WooCommerce", 'paymaya-checkout' );

    $this->title = __( "PayMaya Checkout", 'paymaya-checkout' );
    $this->icon = null;

    $this->has_fields = true;
    $this->supports = array();

    $this->init_form_fields();
    $this->init_settings();

    foreach($this->settings as $setting_key => $value) {
      $this->$setting_key = $value;
    }

    add_action( 'admin_notices', array( $this, 'do_ssl_check' ) );

    if(is_admin()) {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }
  }

  public function init_form_fields() {
    $this->form_fields = array(
      'enabled'               => array(
        'title'   => __( 'Enable / Disable', 'paymaya-checkout' ),
        'label'   => __( 'Enable this payment gateway', 'paymaya-checkout' ),
        'type'    => 'checkbox',
        'default' => 'no',
      ),
      'title'                 => array(
        'title'    => __( 'Title', 'paymaya-checkout' ),
        'type'     => 'text',
        'desc_tip' => __( 'Payment title the customer will see during the checkout process.', 'paymaya-checkout' ),
        'default'  => __( 'Credit card', 'paymaya-checkout' ),
      ),
      'description'           => array(
        'title'    => __( 'Description', 'paymaya-checkout' ),
        'type'     => 'textarea',
        'desc_tip' => __( 'Payment description the customer will see during the checkout process.', 'paymaya-checkout' ),
        'default'  => __( 'Pay securely using your VISA and MasterCard credit, debit, or prepaid card.', 'paymaya-checkout' ),
        'css'      => 'max-width:350px;'
      ),
      'public_facing_api_key' => array(
        'title'    => __( 'Public-facing API Key', 'paymaya-checkout' ),
        'type'     => 'text',
        'desc_tip' => __( 'Used to authenticate yourself to PayMaya Checkout.', 'paymaya-checkout' ),
      ),
      'secret_api_key' => array(
        'title'    => __( 'Secret API Key', 'paymaya-checkout' ),
        'type'     => 'text',
        'desc_tip' => __( 'Used to authenticate yourself to PayMaya Checkout.', 'paymaya-checkout' ),
      ),
      'environment'           => array(
        'title'       => __( 'Sandbox Mode', 'paymaya-checkout' ),
        'label'       => __( 'Enable Sandbox Mode', 'paymaya-checkout' ),
        'type'        => 'checkbox',
        'description' => __( 'Perform transactions in sandbox mode. <br>Test card numbers available <a target="_blank" href="https://developers.paymaya.com/blog/entry/checkout-api-test-credit-card-account-numbers">here</a>.', 'paymaya-checkout' ),
        'default'     => 'no',
      )
    );
  }

  public function process_payment( $order_id ) {
    global $woocommerce;

    \PayMaya\PayMayaSDK::getInstance()->initCheckout($this->public_facing_api_key, $this->secret_api_key, "sandbox");

    $item_checkout = new PayMaya\API\Checkout();
    $wooCountries = new WC_Countries();
    $user = new User();
    $item_checkout->buyer = $user->buyerInfo();

    $customer_order = new WC_Order( $order_id );

    $states = $wooCountries->get_states($customer_order->billing_country);
    $billingState = $states[$customer_order->billing_state];

    $address = new PayMaya\Model\Checkout\Address();
    $address->line1 = $customer_order->billing_address_1;
    $address->line2 = $customer_order->billing_address_2;
    $address->city = $customer_order->billing_city;
    $address->state = $billingState;
    $address->zipCode = $customer_order->billing_postcode;
    $address->countryCode = "PH";

    $item_checkout->buyer->shippingAddress = $address;
    $item_checkout->buyer->billingAddress  = $address;

    foreach($customer_order->get_items() as $key => $cart_item) {
        $product = new WC_Product($cart_item['product_id']);

        $product_price = new PayMaya\Model\Checkout\ItemAmount();
        $product_price->currency = "PHP";
        $product_price->value = number_format($product->get_price(), 2, ".", "");
        $product_price->details = new PayMaya\Model\Checkout\ItemAmountDetails();

        $line_total = new PayMaya\Model\Checkout\ItemAmount();
        $line_total->currency = "PHP";
        $line_total->value = number_format($cart_item['line_total'], 2, ".", "");
        $line_total->details = new PayMaya\Model\Checkout\ItemAmountDetails();

        $item = new PayMaya\Model\Checkout\Item();
        $item->name = $product->get_title();
        $item->code = $product->get_sku();
        $item->description = "";
        $item->quantity = $cart_item['qty'];
        $item->totalAmount = $line_total;

        $item_checkout->items[] = $item;
    }

    $totalAmount = new PayMaya\Model\Checkout\ItemAmount();
    $totalAmount->currency = "PHP";
    $totalAmount->value = number_format($customer_order->get_total(), 2, ".", "");
    $totalAmount->details = new PayMaya\Model\Checkout\ItemAmountDetails();

    $random_token = uniqid("paymaya-pg-", true);

    $item_checkout->totalAmount = $totalAmount;
    $item_checkout->requestReferenceNumber = "$order_id";
    $item_checkout->redirectUrl = array(
      "success" => get_home_url() . "?wc-api=paymaya_checkout_handler&cid=$order_id&n=$random_token",
      "failure" => get_home_url() . "?wc-api=paymaya_checkout_handler&cid=$order_id&n=$random_token",
      "cancel"  => get_home_url() . "?wc-api=paymaya_checkout_handler&cid=$order_id&n=$random_token"
    );
    $item_checkout->execute();

    WC_CustomOrderData::extend($customer_order);
    $customer_order->custom->checkout_id = $item_checkout->id;
    $customer_order->custom->checkout_url = $item_checkout->url;
    $customer_order->custom->nonce = $random_token;
    $customer_order->custom->save();

    return array(
      'result'   => 'success',
      'redirect' => $item_checkout->url,
    );
  }

  // Validate fields
  public function validate_fields() {
    return true;
  }

  // Check if we are forcing SSL on checkout pages
  // Custom function not required by the Gateway
  public function do_ssl_check() {
    if ( $this->enabled == "yes" ) {
      if ( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
        echo "<div class=\"error\"><p>" . sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . "</p></div>";
      }
    }
  }

}
