<?php

class WC_Gateway_Bight_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                = 'bight';
        $this->has_fields        = true;
        $this->method_title      = __( 'Bight', 'woocommerce' );
        $this->method_description = __( 'Allow users to pay using Bight.io', 'woocommerce' );
        
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // define('BIGHT_CHECKOUT_URL', 'http://localhost:8000');
        $this->hash_alg     = 'sha256';
        $this->icon 		= $this->bight_plugin_url() . '/images/bight-checkout-logo.png';        // $this->title        = $this->settings['title'];
        // $this->description  = $this->settings['description'];
        $this->title        = 'Bight';
        $this->description  = 'Enjoy the privacy and security of cryptocurrencies when using Bight to pay';
        $this->currency  	= $this->settings['seller_currency'];
        $this->seller_id  	= $this->settings['store_name'];
        $this->api_key      = $this->settings['api_key'];
        $this->secret_key   = $this->settings['secret_key'];
        $this->bight_url    = $this->settings['dev_mode'];
        // $this->bight_url    = 'http://localhost:8000/';

        if(isset($_GET['wpl_paylabs_ap_callback']) && esc_attr($_GET['wpl_paylabs_ap_callback'])==1) {
            add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'bight_thankyou') );
        }

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'bight_checkout_receipt' ) );
        add_action( 'woocommerce_api_wc_gateway_bight_gateway', array($this, 'bight_receipt_callback') );
    }

    function bight_plugin_url() {
        if(isset($this->bight_plugin_url)) 
            return $this->bight_plugin_url;

        if(is_ssl()) 
        {
            return $this->bight_plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . '/' . plugin_basename( dirname( dirname( __FILE__ )));
        }
        else 
        {
            return $this->bight_plugin_url = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ )));
        }
	}

    function bight_thankyou() {
        $etherum_network = $this->bight_url === 'https://wallet.bight.io/purchase' ? 'www' : 'rinkeby' ;
        $added_text = '<p>Thank You for paying with Bight.io</p>';
        $added_text .= '<p>Here is the transaction hash for this purchase: <a href="https://' . $etherum_network . '.etherscan.io/tx/' . $_GET['txHash'] . '">' . $_GET['txHash'] . '</a></p>';
        return $added_text ;
    }

    function bight_receipt_callback() {
        global $woocommerce;
		global $wpdb;
            
        if (!is_null($_GET['data'])) {

            error_log('Undecoded Data: ' . $_GET['data']);

            $decodedData = base64_decode($_GET['data']);

            error_log('$decodedData: ' . $decodedData);

            $dataObj = json_decode($decodedData, TRUE);

            error_log('$dataObj: ' . print_r($dataObj, true));

            $order_id = absint( WC()->session->get('order_awaiting_payment'));
            if($order_id < 1)
            {
                $post_id_arr = $wpdb->get_results( "select post_id from ".$wpdb->postmeta." where meta_key='_transaction_id' and meta_value = '".$dataObj->txnId."'", ARRAY_A );
                if(!empty($post_id_arr))
                    $order_id = $post_id_arr[0]['post_id'];
            }
            $order = new WC_Order($order_id);

            if ($_GET['resultCode'] == 'Authorized') {

                $secretKey = $this->secret_key;
                $hash = $this->hash_alg;
                $signature = base64_encode(hash_hmac($hash, $decodedData, $secretKey, true));
                // error_log('$secretKey: ' . $secretKey);
                // error_log('Incoming $signature: ' . $_GET['signature']);
                // error_log('Generated $signature: ' . $signature);

                if ($signature == $_GET['signature']) {

                    // Reduce stock levels
                    $order->reduce_order_stock();
                
                    // Remove cart
                    $woocommerce->cart->empty_cart();

                    $order->payment_complete();
                    
                    $results = urlencode(base64_encode(json_encode($_GET)));
					$return_url = add_query_arg(array('wpl_paylabs_ap_callback'=>1,'txHash'=>$_GET['txHash']), $this->get_return_url($order));
			        wp_redirect($return_url); 
                } 

            } else {
                $order->update_status($_GET['resultCode'], __( 'Unable to Authorize Payment', 'woocommerce' ));
            }

        }


    }

    public function init_form_fields() {
        $this->form_fields = array(
            // 'title' => array(
            //     'title' => __( 'Title', 'woocommerce' ),
            //     'type' => 'text',
            //     'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            //     'default' => __( 'Bight', 'woocommerce' ),
            //     'desc_tip'      => true,
            // ),
            // 'description' => array(
            //     'title' 		=> __( 'Description:', 'woocommerce' ),
            //     'type' 			=> 'textarea',
            //     'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            //     'default' 		=> __( 'Feel secure purchasing using crypto currencies with Bight', 'woocommerce' ),
            // ),
            'store_name' => array(
                'title' 		=> __( 'Store Name:', 'woocommerce' ),
                'type'	 		=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> __( 'The name of your store', 'woocommerce' ),
                'default' 		=> ''
            ),
            'api_key' => array(
                'title' 		=> __( 'Access Key:', 'woocommerce' ),
                'type'	 		=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> __( 'Access Key provided by Bight.io', 'woocommerce' ),
                'default' 		=> 'Insert Access Key Here'
            ),
            'secret_key' => array(
                'title' 		=> __( 'Secret Key:', 'woocommerce' ),
                'type'	 		=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> __( 'Secret Key provided by Bight.io', 'woocommerce' ),
                'default' 		=> 'Insert Secret Key Here'
            ),
            'seller_currency' => array(
                'title' 		=> __('Seller currency:', 'woocommerce'),
                'type' 			=> 'select',
                'label' 		=> __('Seller currency', 'woocommerce'),
                'options' 		=> array('USD'=>'USD'),
                'description' 	=> __( 'Currently only supporting USD', 'woocommerce' ),
                'default' 		=> 'USD',
            ),
            'dev_mode' => array(
                'title' 		=> __( 'Developer Mode:', 'woocommerce' ),
                'type' 			=> 'select',
                'options' 		=> array('https://checkout.bight.io/purchase'=>'MAINNET', 
                                        'https://checkout-qa.bight.io/purchase' => 'RINKEBY'),  
                'default' 		=> 'MAINNET'          
            )
        );
    }

    function bight_checkout_receipt($order_id) {
        global $woocommerce;
        $order = new WC_Order($order_id);
        $returnURL = $woocommerce->api_request_url(strtolower(get_class( $this )));
        $otherURL = $order->get_checkout_payment_url();
        $cancelURL = $order->get_cancel_order_url_raw();
        $aplKey= $this->api_key;
        $cart = new WC_Cart();

        $resp = $this->generate_bight_receipt_data($order_id);

        echo '<form id="paymentForm" method="get" action="' . $this->bight_url .'">
            <input type="hidden" name="data" value="' . $resp->data . '" />
            <input type="hidden" name="signature" value="' . $resp->signature . '" />
            <input type="submit" id="submitButton" value="Pay With Bight"/>
            </form>';
    }

    function generate_bight_receipt_data($order_id) {
        global $woocommerce;
        $order = new WC_Order($order_id);
        $returnURL = $woocommerce->api_request_url(strtolower(get_class( $this )));
        $cancelReturnURL = esc_url_raw($order->get_cancel_order_url_raw());
        $secretKey = $this->secret_key;

        $hash = $this->hash_alg;
        $txn_id = substr(hash($hash, mt_rand() . microtime()), 0, 20);
        update_post_meta( $order_id, '_transaction_id', $txn_id );

        $parameters["currencyCode"]           = $this->currency;
        $parameters["totalAmount"]            = $order->get_total();
        $parameters["sellerId"]               = $this->seller_id;
        $parameters["apiKey"]                 = $this->api_key;
        $parameters["returnURL"]              = $returnURL;
        $parameters["cancelReturnURL"]        = $cancelReturnURL;
        $parameters["txnId"]                  = $txn_id;
        $parameters["hashAlg"]                = $hash;
        // $parameters["shippingAddressRequired"]= "false";

        // Create some signature of transaction
        if (!isset($resp)) $resp = new stdClass();

        // Generate JSON
        $resp->data = base64_encode(stripslashes(json_encode($parameters)));
        
        // Encode json data     
        $hashData = stripslashes(json_encode($parameters));

        $resp->signature = base64_encode(hash_hmac($hash, $hashData, $secretKey, true));

        return $resp;
    }

    function process_payment( $order_id ) {
        global $woocommerce;
        $order = new WC_Order( $order_id );
    
        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }
}
