<?php
/**
 * Plugin Name: WooCommerce Custom Order Data
 * Plugin URI: http://milos.djekic.net/my-software/wc-custom-order-data-plugin
 * Description: Plugin enables you to store and use custom data for your orders.
 * Author: Miloš Đekić
 * Author URI: http://milos.djekic.net
 * Version: 1.0.1
 * License: GPL v2
 */

// include custom order data class
include(__DIR__ . '/WC_CustomOrderData.php');

/**
 * Adds database tables used for storing custom order data
 *
 * @global $wpdb;
 */
function woocommerce_custom_order_data_activate() {
    global $wpdb;

    // create custom order data table
    $wpdb->query('
      CREATE TABLE IF NOT EXISTS wp_woocommerce_custom_order_data (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        custom_key VARCHAR(32) NOT NULL,
        custom_value TEXT
      ) ENGINE = MYISAM;
    ');
}

// register activation hook
register_activation_hook( __FILE__,'woocommerce_custom_order_data_activate');

/**
 * Make sure plugin is loaded first
 */
function woocommerce_custom_order_data_loadFirst() {
    // init path
    $path = str_replace( WP_PLUGIN_DIR . '/', '', __FILE__ );

    // get active plugins
    $plugins = get_option('active_plugins');

    // make sure active plugins are loaded
    if(!$plugins) return;

    // search for plugin key
    $key = array_search($path,$plugins);

    // check if plugin key is found
    if(!$key) return;

    // shift the key to the first position
    array_splice($plugins,$key,1);
    array_unshift($plugins,$path);

    // update active plugins
    update_option('active_plugins',$plugins);
}

// register activation action
add_action('activated_plugin', 'woocommerce_custom_order_data_loadFirst');