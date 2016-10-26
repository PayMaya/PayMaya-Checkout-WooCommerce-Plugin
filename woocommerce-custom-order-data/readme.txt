=== WooCommerce Custom Order Data ===

Contributors: mdjekic
Donate link: http://milos.djekic.net/my-software/woocommerce-custom-order-data
Tags: woocommerce,ecommerce,custom,order,data
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 2.7
Tested up to: 3.5.1
Stable tag: 1.0.1

Extend WC orders with practically any custom data by writing just one line of code and use it wherever you need.

== Description ==

If you are extending WooCommerce by creating themes or developing plugins you will find this plugin to be priceless!

WooCommerce uses WordPress logic and database tables to store data on store items, orders, checkouts…
If you are integrating a payment gateway or any 3rd party app with your store, you sometimes need to
store some extra data for specific orders. It tends to be very hard or even impossible to extend orders
with custom data.

= Extend WC Orders =

Here’s how easy it is to extend a WooCommerce order in your code:

| WC_CustomOrderData::extend($order);

To set a custom property to an order, just type:

| $order->custom->your_custom_property = 'some value';

After that you can use your custom order property by referring the “custom” order property:

| $custom_property = $order->custom->your_custom_property;

You can allways check if a custom order property exists using the standard PHP way:

| if(isset($order->custom->your_custom_property)) { doStuff(); }

All custom order properties are saved in a separate database table. When you finish working with the order,
make sure you save custom data by typing:

| $order->custom->save();

Note: Storred objects and arrays will be restored as arrays.

= Author =

[Miloš Đekić](http://milos.djekic.net) is a software enthusiast from Belgrade,
Serbia. He loves to create useful software.

== Installation ==

Just follow this simple guidelines.

= Installation steps =

1. Upload 'woocommerce-custom-order-data' to the '/wp-content/plugins/' directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Use custom order data in your WooCommerce plugins and themes
1. That's it. Enjoy!

== Changelog ==

= 1.0.1 =
* Added 'unset' support

= 1.0 =
* First version - WooCommerce orders can now be extended with custom data.