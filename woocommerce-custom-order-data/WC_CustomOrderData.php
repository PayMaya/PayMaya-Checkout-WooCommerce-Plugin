<?php

/**
 * Custom Order Data for WooCommerce
 *
 * @author Milos Djekic (milos.djekic.net)
 */
class WC_CustomOrderData
{
    /**
     * ID of the order extended with custom data
     *
     * @var int
     */
    private $order_id;

    /**
     * Custom fields
     *
     * @var array
     */
    private $fields;

    /**
     * Extends an order with custom data
     *
     * @param WC_Order $order
     */
    public static function extend(&$order) {
        $order->custom = new WC_CustomOrderData($order->id);
    }

    /**
     * Creates custom data for an order
     *
     * @param $order_id
     */
    private function __construct($order_id) {
        // save order id
        $this->order_id = $order_id;

        // init fields
        $this->fields = array();

        // get data from the database
        $this->populate();
    }

    /**
     * Populates data from the database
     *
     * @global $wpdb
     */
    private function populate() {
        global $wpdb;

        // search for custom fields
        $fields = $wpdb->get_results(
            'SELECT * FROM wp_woocommerce_custom_order_data WHERE order_id = ' . $this->order_id,
            OBJECT
        );

        // go trough results
        foreach($fields as $field) {
            // try to decode value
            $value = json_decode($field->custom_value,true);

            // check if value decoded
            if($value != null) $this->fields[$field->custom_key] = $value;
            else $this->fields[$field->custom_key] = $field->custom_value;
        }
    }

    /**
     * Saves custom fields
     *
     * @global $wpdb
     */
    public function save() {
        global $wpdb;

        // delete all options
        $wpdb->query('DELETE FROM wp_woocommerce_custom_order_data WHERE order_id = ' . $this->order_id);

        // go trough all fields an insert them
        foreach($this->fields as $key => $value) {
            // skip if field is null or empty string
            if(empty($value)) continue;

            // encode if object or array
            if(is_object($value) || is_array($value)) $value = json_encode($value);

            // insert into the database
            $wpdb->insert('wp_woocommerce_custom_order_data',array(
                'order_id' => $this->order_id,
                'custom_key' => $key,
                'custom_value' => $value,
            ),array('%d','%s','%s'));
        }
    }

    /**
     * Checks if a custom param has been set
     *
     * @param string $key
     *
     * @return bool true if param is set
     */
    public function __isset($key) {
        return isset($this->fields[$key]);
    }

    /**
     * Removes a custom param
     *
     * @param string $key
     */
    public function __unset($key) {
        unset($this->fields[$key]);
    }

    /**
     * Returns a param by its name
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key) {
        return isset($this->fields[$key]) ? $this->fields[$key] : null;
    }

    /**
     * Sets a param value
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key,$value) {
        $this->fields[$key] = $value;
    }
}
