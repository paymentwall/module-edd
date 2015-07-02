<?php
/**
 * Plugin Name: Easy Digital Downloads - Paymentwall Checkout
 * Plugin URI: http://www.paymentwall.com/en/documentation/Easy-Digital-Downloads/1741?source=edd
 * Description: Allows to use Paymentwall as a payment gateway for Easy Digital Downloads
 * Version: 1.0
 * Author: Paymentwall Integration Team
 * Author URI: http://paymentwall.com/?source=edd
 */

/**
Installation part
*/

// registers the gateway
function edd_register_paymentwall_gateway($gateways) {
	// Format: ID => Name
	$gateways['paymentwall'] = array('admin_label' => __('Paymentwall', 'paymentwall'), 'checkout_label' => __('Paymentwall', 'paymentwall'));
	return $gateways;
}
add_filter('edd_payment_gateways', 'edd_register_paymentwall_gateway');

// adds the settings to the Payment Gateways section
function paymentwall_add_settings($settings) {
  
  $paymentwall_settings = array(
		array(
			'id' => 'paymentwall_settings',
			'name' => '<strong>' . __('Paymentwall Settings', 'paymentwall') . '</strong>',
			'desc' => __('Configure your Paymentwall Settings', 'paymentwall'),
			'type' => 'header'
		),
		array(
			'id' => 'paymentwall_application_key',
			'name' => __('Paymentwall Project Key', 'paymentwall'),
			'desc' => __('Enter your Paymentwall Project Key that can be found in Project Settings inside of your Paymentwall Merchant Account', 'paymentwall'),
			'type' => 'text',
			'size' => '32'
		),
		array(
			'id' => 'paymentwall_secret_key',
			'name' => __('Paymentwall Secret Key', 'paymentwall'),
			'desc' => __('Enter your Paymentwall Secret Key that can be found in Project Settings inside of your Paymentwall Merchant Account', 'paymentwall'),
			'type' => 'text',
			'size' => '32'
		),
		array(
			'id' => 'paymentwall_widget_code',
			'name' => __('Paymentwall Widget Code', 'paymentwall'),
			'desc' => __('Enter your Paymentwall Widget Code that can be found in Widgets section inside of your Paymentwall Merchant Account, e.g. p1 or p1_1', 'paymentwall'),
			'type' => 'text',
			'size' => '10'
		),
		array(
			'id' => 'paymentwall_widget_mode',
			'name' => __('Paymentwall Widget Mode', 'paymentwall'),
			'desc' => __('Payment happens on an external page or in-page'),
			'type' => 'select',
			'options' => array(
				'iframe' => 'In-page',
				'redirect' => 'External page'
			)
		)
	);
	
	return array_merge($settings, $paymentwall_settings);
}
add_filter('edd_settings_gateways', 'paymentwall_add_settings');

function edd_paymentwall_cc_form() {
	global $edd_options;
	if (!empty($edd_options['paymentwall_widget_mode']) && $edd_options['paymentwall_widget_mode'] == 'iframe') {
		echo edd_paymentwall_get_widget_wrapper();
	}
}

function edd_paymentwall_get_widget_wrapper() {
	$h = 
		"<script tyle=\"text/javscript\">" .
			"jQuery(document).ready(function($){" .
				"var pwForm = $('#edd_purchase_form');" .
				"pwForm.submit(function() {" .
					"jQuery.ajax({" .
				    	"url: pwForm.attr('action')," .
				    	"method: pwForm.attr('method')," .
				    	"data: pwForm.serialize()" .
					"}).done(function(response) {".
						"$('#edd_pw_wrapper').html(response);" .
					"});" .
					"pwHtml = '<div style=\"background:url(https://api.paymentwall.com/images/preloader.gif) center 50px no-repeat;min-height:200px;\" id=\"edd_pw_wrapper\"></div>';" .
					"pwForm.html(pwHtml);" .
					"$('html, body').animate({scrollTop: $('#edd_checkout_wrap').offset().top}, '2000');" .
					"return false;" .
				"});" .
			"});".
		"</script>";
	return $h;
}

add_action( 'edd_paymentwall_cc_form', 'edd_paymentwall_cc_form' );

/**
Payment processing part
*/

// initialize paymentwall library object
function edd_initialize_paymentwall_lib() {
	global $edd_options;

	require_once plugin_dir_path(__FILE__) . 'paymentwall-php/lib/paymentwall.php';

    Paymentwall_Config::getInstance()->set(array(
        'api_type' => Paymentwall_Config::API_GOODS,
        'public_key' => $edd_options['paymentwall_application_key'],
        'private_key' => $edd_options['paymentwall_secret_key']
    ));
}

// redirect user to the payment page
function edd_process_paymentwall_purchase( $purchase_data ) {

	global $edd_options;

    // Collect payment data
    $payment_data = array(
        'price'         => $purchase_data['price'],
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'      => edd_get_currency(),
        'downloads'     => $purchase_data['downloads'],
        'user_info'     => $purchase_data['user_info'],
        'cart_details'  => $purchase_data['cart_details'],
        'gateway'       => 'paymentwall',
        'status'        => 'pending'
     );

    // Record the pending payment
    $payment = edd_insert_payment( $payment_data );

    // Check payment
    if ( ! $payment ) {
    	// Record the error
        edd_record_gateway_error( __( 'Payment Error', 'edd' ), sprintf( __( 'Payment creation failed before sending buyer to Paymentwall. Payment data: %s', 'edd' ), json_encode( $payment_data ) ), $payment );
        // Problems? send back
        edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
    } else {

    	edd_initialize_paymentwall_lib();
    	$widget = new Paymentwall_Widget(
        	$purchase_data['user_email'],
        	$edd_options['paymentwall_widget_code'],
        	array(
        		new Paymentwall_Product(
        			$payment,
        			$purchase_data['price'],
        			edd_get_currency(),
        			stripslashes( html_entity_decode( wp_strip_all_tags( edd_get_purchase_summary( $purchase_data, false ) ), ENT_COMPAT, 'UTF-8' ) )
    			)
        	),
        	array(
        		'success_url' => add_query_arg( 'payment-confirmation', 'paymentwall', get_permalink( $edd_options['success_page'] ) ),
        		'email' => $purchase_data['user_email'],
        		'sign_version' => Paymentwall_Signature_Abstract::VERSION_THREE,
				'integration_module' => 'easy_digital_downloads'
    		)
    	);

    	
    	// Get rid of cart contents
		edd_empty_cart();

    	if (!empty($edd_options['paymentwall_widget_mode']) && $edd_options['paymentwall_widget_mode'] == 'iframe') {
    		echo $htmlCode = $widget->getHtmlCode(array('width' => '100%', 'allowtransparency' => 'true'));
    	} else {
			wp_redirect( $widget->getUrl() );
    	}

		exit;
	}

}
add_action( 'edd_gateway_paymentwall', 'edd_process_paymentwall_purchase' );

// process pingback
function edd_paymentwall_pingback() {
	edd_initialize_paymentwall_lib();
    if (isset($_GET['edd-listener']) && $_GET['edd-listener'] != '') {
        unset($_GET['edd-listener']);
    }
	$pingback = new Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);
	if ($pingback->validate()) {
		$payment_id = $pingback->getProduct()->getId();
		if ($pingback->isDeliverable()) {
			edd_update_payment_status($payment_id, 'publish');
		} else if ($pingback->isCancelable()) {
			edd_update_payment_status( $payment_id, 'refunded' );
		} else {
			echo $pingback->getErrorSummary();
		}
		echo 'OK';
	} else {
		echo $pingback->getErrorSummary();
	}
	die();
}

add_action( 'edd_paymentwall_pingback', 'edd_paymentwall_pingback' );

// listen for pingback
function edd_listen_for_paymentwall_pingback() {
	global $edd_options;

	// Regular PayPal IPN
	if ( isset( $_GET['edd-listener'] ) && $_GET['edd-listener'] == 'paymentwall' ) {
		do_action( 'edd_paymentwall_pingback' );
	}

}
add_action( 'init', 'edd_listen_for_paymentwall_pingback' );

