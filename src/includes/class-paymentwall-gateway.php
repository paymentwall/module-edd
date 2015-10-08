<?php

/**
 * Paymentwall for Easy Digital Downloads
 * Plugin URI: http://www.paymentwall.com/en/documentation/Easy-Digital-Downloads/1741?source=edd
 * Description: Allows to use Paymentwall as a payment gateway for Easy Digital Downloads
 * Author: Paymentwall Integration Team
 */
class edd_paymentwall_gateway extends edd_paymentwall_abstract
{
    protected $gateway_id = 'paymentwall';

    public function __construct()
    {
        parent::__construct();
    }

    public function init()
    {
        add_action('process_pingback', array($this, 'process_pingback'));
        add_action('init', array($this, 'listen_pingback'));
        parent::init();
    }

    /**
     * @return array
     */
    protected function gateway_options()
    {
        return array(
            'admin_label' => 'Paymentwall',
            'checkout_label' => $this->edd_options['paymentwall_name'] ? $this->edd_options['paymentwall_name'] : __('Paymentwall', PW_EDD_TEXT_DOMAIN)
        );
    }

    /**
     * Register payment gateway
     */
    protected function init_paymentwall_config()
    {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $this->edd_options['paymentwall_project_key'],
            'private_key' => $this->edd_options['paymentwall_secret_key']
        ));
    }

    /**
     * @param $purchase_data
     * @return mixed|void
     */
    public function process_purchase($purchase_data)
    {
        // Collect payment data
        $payment_data = array(
            'price' => $purchase_data['price'],
            'date' => $purchase_data['date'],
            'user_email' => $purchase_data['user_email'],
            'purchase_key' => $purchase_data['purchase_key'],
            'currency' => edd_get_currency(),
            'downloads' => $purchase_data['downloads'],
            'user_info' => $purchase_data['user_info'],
            'cart_details' => $purchase_data['cart_details'],
            'gateway' => $this->gateway_id,
            'status' => 'pending'
        );

        // Record the pending payment
        $payment = edd_insert_payment($payment_data);

        // Check payment
        if (!$payment) {
            // Record the error
            edd_record_gateway_error(__('Payment Error', PW_EDD_TEXT_DOMAIN), sprintf(__('Payment creation failed before sending buyer to Paymentwall. Payment data: %s', PW_EDD_TEXT_DOMAIN), json_encode($payment_data)), $payment);
            // Problems? send back
            edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
        } else {

            $this->init_paymentwall_config();
            $widget = new Paymentwall_Widget(
                $purchase_data['user_email'],
                $this->edd_options['paymentwall_widget_code'],
                array(
                    new Paymentwall_Product(
                        $payment,
                        $purchase_data['price'],
                        edd_get_currency(),
                        stripslashes(html_entity_decode(wp_strip_all_tags(edd_get_purchase_summary($purchase_data, false)), ENT_COMPAT, 'UTF-8'))
                    )
                ),
                array(
                    'success_url' => add_query_arg('payment-confirmation', $this->gateway_id, get_permalink($this->edd_options['success_page'])),
                    'email' => $purchase_data['user_email'],
                    'integration_module' => 'easy_digital_downloads',
                    'test_mode' => edd_is_test_mode() ? 1 : 0
                )
            );

            // Get rid of cart contents
            edd_empty_cart();

            if (!empty($this->edd_options['paymentwall_widget_mode']) && $this->edd_options['paymentwall_widget_mode'] == 'iframe') {
                do_action('edd_paymentwall_widget_before');
                echo $widget->getHtmlCode(array('width' => '100%', 'allowtransparency' => 'true'));
                do_action('edd_paymentwall_widget_after');
            } else {
                wp_redirect($widget->getUrl());
            }
            exit;
        }
    }

    /**
     * Process pingback request
     */
    public function process_pingback()
    {
        $this->init_paymentwall_config();

        $pingback = new Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);
        if ($pingback->validate()) {
            $payment_id = $pingback->getProduct()->getId();
            if ($pingback->isDeliverable()) {
                edd_update_payment_status($payment_id, 'publish');
                edd_insert_payment_note($payment_id, 'Payment approved!, Transaction Id #' . $pingback->getReferenceId());
            } else if ($pingback->isCancelable()) {
                edd_update_payment_status($payment_id, 'refunded');
            } else {
                echo $pingback->getErrorSummary();
            }
            echo 'OK';
        } else {
            echo $pingback->getErrorSummary();
        }
        die();
    }

    /**
     * Listen a wp action to process pingback
     */
    function listen_pingback()
    {
        if (isset($_GET['edd-listener']) && $_GET['edd-listener'] == 'paymentwall') {
            do_action('process_pingback');
        }
    }

}