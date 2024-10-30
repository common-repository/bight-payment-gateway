<?php

/*
    Plugin Name: Bight Payment Gateway
    Plugin URI: https://bight.io
    description: Accept Crypto currency as a form of payment for your products
    Version: 1.0.6
*/

add_action( 'plugins_loaded', 'init_bight_gateway_class' );

function init_bight_gateway_class() {

    // If the WooCommerce payment gateway class is not available nothing will return
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    require_once( plugin_basename( 'bight-woocommerce/bight-plugin.php' ) );

    add_filter( 'woocommerce_payment_gateways', 'add_bight_gateway_class' );

    function add_bight_gateway_class( $methods ) {
        $methods[] = 'WC_Gateway_Bight_Gateway'; 
        return $methods;
    }
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bight_authorize_action_links' );
function bight_authorize_action_links( $links ) {
    $mylinks = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bight' ) . '"><b>Settings</b></a>',
        );
    return array_merge($mylinks, $links);
}