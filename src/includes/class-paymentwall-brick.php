<?php

/**
 * Paymentwall for Easy Digital Downloads
 * Plugin URI: http://www.paymentwall.com/en/documentation/Easy-Digital-Downloads/1741?source=edd
 * Description: Allows to use Paymentwall as a payment gateway for Easy Digital Downloads
 * Author: Paymentwall Integration Team
 */
class edd_paymentwall_brick extends edd_paymentwall_abstract
{
    protected $gateway_id = 'brick';

    public function __construct()
    {
        parent::__construct();
    }

    public function init()
    {
        parent::init();
    }

    /**
     * @return array
     */
    protected function gateway_options()
    {
        return array(
            'admin_label' => 'Brick',
            'checkout_label' => $this->edd_options['brick_name'] ? $this->edd_options['brick_name'] : __('Brick - Credit Card Processing', PW_EDD_TEXT_DOMAIN)
        );
    }

    /**
     * Register payment gateway
     */
    protected function init_paymentwall_config()
    {
        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => edd_is_test_mode() ? $this->edd_options['brick_public_test_key'] : $this->edd_options['brick_public_key'],
            'private_key' => edd_is_test_mode() ? $this->edd_options['brick_private_test_key'] : $this->edd_options['brick_private_key']
        ));
    }

    /**
     * @param $purchase_data
     * @return mixed|void
     */
    public function process_purchase($purchase_data)
    {
        $this->init_paymentwall_config();

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
        $payment_id = edd_insert_payment($payment_data);

        // Check payment
        if (!$payment_id) {
            // Record the error
            edd_record_gateway_error(__('Payment Error', PW_EDD_TEXT_DOMAIN), sprintf(__('Payment creation failed before sending buyer to Paymentwall. Payment data: %s', PW_EDD_TEXT_DOMAIN), json_encode($payment_data)), $payment_id);
            // Problems? send back
            edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
        } else {

            $cardInfo = array(
                'email' => $purchase_data['post_data']['edd_email'],
                'amount' => $purchase_data['price'],
                'currency' => edd_get_currency(),
                'token' => $purchase_data['post_data']['brick_token'],
                'fingerprint' => $purchase_data['post_data']['brick_fingerprint'],
                'description' => edd_get_purchase_summary($payment_data, false)
            );

            $charge = new Paymentwall_Charge();
            $charge->create($cardInfo);
            $response = $charge->getPublicData();

            if ($charge->isSuccessful()) {
                if ($charge->isCaptured()) {
                    // deliver a product
                    edd_update_payment_status($payment_id, 'publish');
                    edd_insert_payment_note($payment_id, 'Payment approved!, Transaction Id #' . $charge->getId());
                } elseif ($charge->isUnderReview()) {
                    // decide on risk charge
                    edd_update_payment_status($payment_id, 'pending');
                    edd_insert_payment_note($payment_id, 'Payment under review!, Transaction Id #' . $charge->getId());
                }

                // Empty the shopping cart
                edd_empty_cart();
                edd_send_to_success_page();
            } else {
                $errors = json_decode($response, true);
                edd_set_error('brick_error_' . $errors['error']['code'], __($errors['error']['message'], PW_EDD_TEXT_DOMAIN));

                // Record the error
                edd_record_gateway_error(__('Payment Error', PW_EDD_TEXT_DOMAIN), $errors['error']['message'], $payment_id);
                edd_insert_payment_note($payment_id, 'Error: ' . __($errors['error']['message']));
                // Problems? send back
                edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
            }
        }
    }

    /**
     * Print cc form to checkout page
     */
    public function gateway_cc_form()
    {
        $this->init_paymentwall_config();
        $months = '';
        $years = '';
        for ($i = 1; $i <= 12; $i++) {
            $months .= '<option value="' . $i . '">' . sprintf('%02d', $i) . '</option>';
        }

        for ($i = date('Y'); $i <= date('Y') + 20; $i++) {
            $years .= '<option value="' . $i . '">' . substr($i, 2) . '</option>';
        }

        do_action('edd_before_cc_fields');
        // register the action to remove default CC form
        echo $this->get_template('cc_form.html', array(
            'months' => $months,
            'years' => $years,
            'public_key' => Paymentwall_Config::getInstance()->getPublicKey()
        ));
        do_action('edd_after_cc_fields');
    }

    private function prepare_card_info($purchase_data, $payment)
    {
        return array(
            'email' => $purchase_data['post_data']['edd_email'],
            'amount' => $purchase_data['price'],
            'currency' => edd_get_currency(),
            'token' => $purchase_data['post_data']['brick_token'],
            'fingerprint' => $purchase_data['post_data']['brick_fingerprint'],
            'description' => 'Order #' . $payment
        );
    }

}