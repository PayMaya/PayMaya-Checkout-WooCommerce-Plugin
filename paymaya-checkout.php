<?php

require_once __DIR__ . '/PayMaya-PHP-SDK-master/sample/autoload.php';
require_once __DIR__ . '/PayMaya-PHP-SDK-master/sample/Checkout/User.php';

class PayMaya_Checkout extends WC_Payment_Gateway {

  function __construct() {
    $this->id = "paymaya_checkout";

    $this->method_title = __("PayMaya Checkout", 'paymaya-checkout');
    $this->method_description = __("PayMaya Checkout Payment Gateway Plug-in for WooCommerce", 'paymaya-checkout');

    $this->title = __("PayMaya Checkout", 'paymaya-checkout');
    $this->icon = null;

    $this->has_fields = true;
    $this->supports = array();

    $this->init_form_fields();
    $this->init_settings();

    foreach($this->settings as $setting_key => $value) {
      $this->$setting_key = $value;
    }

    add_action('admin_notices', array($this, 'do_ssl_check'));

    if(is_admin()) {
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options'));
    }
  }

  public function init_form_fields() {
    $this->form_fields = array(
      'enabled'               => array(
        'title'   => __('Enable / Disable', 'paymaya-checkout'),
        'label'   => __('Enable this payment gateway', 'paymaya-checkout'),
        'type'    => 'checkbox',
        'default' => 'no',
      ),
      'title'                 => array(
        'title'    => __('Title', 'paymaya-checkout'),
        'type'     => 'text',
        'desc_tip' => __('Payment title the customer will see during the checkout process.', 'paymaya-checkout'),
        'default'  => __('Credit card', 'paymaya-checkout'),
      ),
      'description'           => array(
        'title'    => __('Description', 'paymaya-checkout'),
        'type'     => 'textarea',
        'desc_tip' => __('Payment description the customer will see during the checkout process.', 'paymaya-checkout'),
        'default'  => __('Pay securely using your VISA and MasterCard credit, debit, or prepaid card.', 'paymaya-checkout'),
        'css'      => 'max-width:350px;'
      ),
      'public_facing_api_key' => array(
        'title'    => __('Public-facing API Key', 'paymaya-checkout'),
        'type'     => 'text',
        'desc_tip' => __('Used to authenticate yourself to PayMaya Checkout.', 'paymaya-checkout'),
      ),
      'secret_api_key' => array(
        'title'    => __('Secret API Key', 'paymaya-checkout'),
        'type'     => 'text',
        'desc_tip' => __('Used to authenticate yourself to PayMaya Checkout.', 'paymaya-checkout'),
      ),
      'environment'           => array(
        'title'       => __('Sandbox Mode', 'paymaya-checkout'),
        'label'       => __('Enable Sandbox Mode', 'paymaya-checkout'),
        'type'        => 'checkbox',
        'description' => __('Perform transactions in sandbox (test) mode. <br>Test card numbers available <a target="_blank" href="https://developers.paymaya.com/blog/entry/checkout-api-test-credit-card-account-numbers">here</a>.', 'paymaya-checkout'),
        'default'     => 'no',
      ),
      'webhook_success'                 => array(
        'title'    => __('Webhook Successful', 'paymaya-checkout'),
        'type'     => 'text',
        'desc_tip' => __('URL that gets notified by PayMaya Checkout after a successful transaction.', 'paymaya-checkout'),
        'default'  => __(get_home_url() . "?wc-api=paymaya_checkout_handler", 'paymaya-checkout'),
      ),
      'webhook_failure'                 => array(
          'title'    => __('Webhook Failure', 'paymaya-checkout'),
          'type'     => 'text',
          'desc_tip' => __('URL that gets notified by PayMaya Checkout after a failed transaction.', 'paymaya-checkout'),
          'default'  => __(get_home_url() . "?wc-api=paymaya_checkout_handler", 'paymaya-checkout'),
      ),
      'webhook_token'                 => array(
          'title'    => __('Webhook Token', 'paymaya-checkout'),
          'type'     => 'text',
          'desc_tip' => __('Authenticates the webhook request.', 'paymaya-checkout'),
          'default'  => __(uniqid("pgwh-", true) . uniqid() . uniqid(), 'paymaya-checkout'),
      )
    );
  }

  public function process_payment($order_id) {
    global $woocommerce;

    \PayMaya\PayMayaSDK::getInstance()->initCheckout($this->public_facing_api_key, $this->secret_api_key, $this->environment());

    $item_checkout = new PayMaya\API\Checkout();
    $wooCountries = new WC_Countries();
    $user = new User();
    $item_checkout->buyer = $user->buyerInfo();

    $customer_order = new WC_Order($order_id);

    $states = $wooCountries->get_states($customer_order->get_billing_country());
    $billingState = $states[$customer_order->get_billing_state()];

    $address = new PayMaya\Model\Checkout\Address();
    $address->line1 = $customer_order->get_billing_address_1();
    $address->line2 = $customer_order->get_billing_address_2();
    $address->city = $customer_order->get_billing_city();
    $address->state = $billingState;
    $address->zipCode = $customer_order->get_billing_postcode();
    $address->countryCode = $customer_order->get_billing_country();

    $item_checkout->buyer->shippingAddress = $address;
    $item_checkout->buyer->billingAddress  = $address;

    foreach($customer_order->get_items() as $key => $cart_item) {
        $product = new WC_Product($cart_item->get_product_id());

        $product_price = new PayMaya\Model\Checkout\ItemAmount();
        $product_price->currency = get_woocommerce_currency();
        $product_price->value = number_format($product->get_price(), 2, ".", "");
        $product_price->details = new PayMaya\Model\Checkout\ItemAmountDetails();

        $line_total = new PayMaya\Model\Checkout\ItemAmount();
        $line_total->currency = get_woocommerce_currency();
        $line_total->value = number_format($cart_item->get_subtotal(), 2, ".", "");
        $line_total->details = new PayMaya\Model\Checkout\ItemAmountDetails();

        $item = new PayMaya\Model\Checkout\Item();
        $item->name = $product->get_title();
        $item->code = $product->get_sku();
        $item->description = "";
        $item->quantity = (string) $cart_item->get_quantity();
        $item->totalAmount = $line_total;

        $item_checkout->items[] = $item;
    }

    $totalAmount = new PayMaya\Model\Checkout\ItemAmount();
    $totalAmount->currency = get_woocommerce_currency();
    $totalAmount->value = number_format($customer_order->get_total(), 2, ".", "");
    $totalAmount->details = new PayMaya\Model\Checkout\ItemAmountDetails();

    $random_token = uniqid("paymaya-pg-", true);

    $item_checkout->totalAmount = $totalAmount;
    $item_checkout->requestReferenceNumber = "$order_id";
    $item_checkout->redirectUrl = array(
      "success" => $this->get_return_url($customer_order),
      "failure" => $this->get_return_url($customer_order),
      "cancel"  => $this->get_return_url($customer_order)
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
    if ($this->enabled == "yes") {
      if (get_option('woocommerce_force_ssl_checkout') == "no") {
        echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
      }
    }
  }

  public function is_sandbox() {
    return $this->environment == "yes";
  }

  public function environment() {
    return $this->is_sandbox() ? "SANDBOX" : "PRODUCTION";
  }

  public function process_admin_options() {
    global $woocommerce;

    $is_options_saved = parent::process_admin_options();

    if(isset($this->public_facing_api_key) && isset($this->secret_api_key)) {
      $this->delete_webhooks();

      $is_options_saved = $this->register_webhook("success");
      $is_options_saved = $this->register_webhook("failed");

      $this->display_errors();
    }

    return $is_options_saved;
  }

  private function register_webhook($type = "success") {
    \PayMaya\PayMayaSDK::getInstance()->initCheckout($this->public_facing_api_key, $this->secret_api_key, $this->environment());

    $webhook_name = $type== "success" ? PayMaya\API\Webhook::CHECKOUT_SUCCESS : PayMaya\API\Webhook::CHECKOUT_FAILURE;
    $webhook_url = $type == "success" ? $this->webhook_success : $this->webhook_failure;

    $success_webhook = new PayMaya\API\Webhook();
    $success_webhook->name = $webhook_name;
    $success_webhook->callbackUrl = $webhook_url . "&wht=" . $this->webhook_token;

    $success_webhook_result = json_decode($success_webhook->register());

    if(isset($success_webhook_result->error)) {
      $this->add_error("There was an error saving your webhook. (" . $success_webhook_result->error->code . " : " . $success_webhook_result->error->message .")");
      return false;
    }

    return true;
  }

  private function delete_webhooks() {
    \PayMaya\PayMayaSDK::getInstance()->initCheckout($this->public_facing_api_key, $this->secret_api_key, $this->environment());

    $webhooks = PayMaya\API\Webhook::retrieve();
    for($i = 0; $i < count($webhooks); $i++) {
      $webhook = new PayMaya\API\Webhook();
      $webhook->id = $webhooks[$i]->id;
      $webhook->delete();
    }
  }
}
