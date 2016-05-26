<?php
session_start();

/**
 * Paymentwall for Easy Digital Downloads
 * Plugin URI: http://www.paymentwall.com/en/documentation/Easy-Digital-Downloads/1741?source=edd
 * Description: Allows to use Paymentwall as a payment gateway for Easy Digital Downloads
 * Author: Paymentwall Integration Team
 */
class edd_paymentwall_brick extends edd_paymentwall_abstract
{
    protected $gateway_id = 'brick';

    const ORDER_COMPLETE = 'publish';
    const ORDER_PENDING = 'pending';

    public function __construct()
    {
        parent::__construct();
    }

    public function init()
    {
        parent::init();
        $this->init_paymentwall_config();
        if (!empty($_GET['secure'])) {
            $this->confirm_3ds($_POST);
        }
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
            $charge = new Paymentwall_Charge();
            $cardInfo = $this->prepare_card_info($purchase_data, $payment_data);

            $charge->create(array_merge($cardInfo, $this->getExtraData($payment_id)));
            $response = $charge->getPublicData();
            $rawResponse = json_decode($charge->getRawResponseData(), true);

            if ($charge->isSuccessful() && empty($rawResponse['secure'])) {
                $this->payment_success($charge, $payment_id);
            } elseif (!empty($rawResponse['secure'])) {
                $_SESSION['3dsecure'] = array(
                    'cardInfo' => $cardInfo,
                    'paymentData' => $payment_data,
                    'paymentId' => $payment_id
                );
                echo $this->get_template('3ds.html', array(
                    '3ds' => $rawResponse['secure']['formHTML']
                ));
            } else {
                $this->payment_error($response, $payment_id);
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

    private function prepare_card_info($purchase_data, $payment_data)
    {
        return array(
            'email' => $purchase_data['post_data']['edd_email'],
            'amount' => $purchase_data['price'],
            'currency' => edd_get_currency(),
            'token' => $purchase_data['post_data']['brick_token'],
            'fingerprint' => $purchase_data['post_data']['brick_fingerprint'],
            'description' => edd_get_purchase_summary($payment_data, false)
        );
    }

    public function confirm_3ds($postData)
    {
        require_once(ABSPATH . WPINC . "/pluggable.php");

        global $wp_rewrite;
        $wp_rewrite = new WP_Rewrite();
        $charge = new Paymentwall_Charge();

        $secureData = $_SESSION['3dsecure'];
        EDD()->session->set('edd_purchase', $secureData['paymentData']);
        $payment_id = $secureData['paymentId'];
        $cardInfo = $secureData['cardInfo'];
        $cardInfo['charge_id'] = $postData['brick_charge_id'];
        $cardInfo['secure_token'] = $postData['brick_secure_token'];

        $charge->create($cardInfo);
        $response = $charge->getPublicData();

        if ($charge->isSuccessful()) {
            $this->payment_success($charge, $payment_id);
        } else {
            $this->payment_error($response, $payment_id, 'confirm3ds');
        }
    }

    private function payment_success($charge, $paymentId)
    {
        $paymentStatus = $paymentNote = '';
        $transactionId = $charge->getId();
        if ($charge->isCaptured()) {
            $paymentStatus = self::ORDER_COMPLETE;
            $paymentNote = 'Payment approved !, Transaction Id #' . $transactionId;
        } elseif ($charge->isUnderReview()) {
            $paymentStatus = self::ORDER_PENDING;
            $paymentNote = 'Payment under review !, Transaction Id #' . $transactionId;
        }
        edd_update_payment_status($paymentId, $paymentStatus);
        edd_insert_payment_note($paymentId, $paymentNote);
        edd_set_payment_transaction_id( $paymentId, $transactionId);
        edd_empty_cart();
        unset($_SESSION['3dsecure']);
        edd_send_to_success_page();
    }

    private function payment_error($response, $payment_id, $payment_mode = '')
    {
        $errors = json_decode($response, true);
        $error = __($errors['error']['message']);
        if (empty($_GET['secure'])) {
            edd_set_error('brick_error_' . $errors['error']['code'], __($errors['error']['message'], PW_EDD_TEXT_DOMAIN));
        }
        edd_insert_payment_note($payment_id, 'Error: ' . $error);
        edd_record_gateway_error(__('Payment Error', PW_EDD_TEXT_DOMAIN), $errors['error']['message'], $payment_id);
        if ('confirm3ds' == $payment_mode) {
            header("Location:" . edd_get_failed_transaction_uri());
            die();
        } else {
            edd_send_back_to_checkout('?payment-mode=' . $this->gateway_id);
        }
    }

    private function getExtraData($payment_id)
    {
        $customerId = edd_get_payment_customer_id($payment_id);
        $customerId = !empty($customerId) ? $customerId : $_SERVER['REMOTE_ADDR'];
        return array(
            'integration_module' => 'edd',
            'uid' => $customerId,
            'secure_redirect_url' => edd_get_current_page_url() . 'checkout/?payment-mode=brick&secure=1'
        );
    }
}