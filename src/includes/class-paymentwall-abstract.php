<?php

/**
 * Paymentwall for Easy Digital Downloads
 * Plugin URI: http://www.paymentwall.com/en/documentation/Easy-Digital-Downloads/1741?source=edd
 * Description: Allows to use Paymentwall as a payment gateway for Easy Digital Downloads
 * Author: Paymentwall Integration Team
 */
abstract class edd_paymentwall_abstract
{
    protected $gateway_id;
    protected $edd_options;

    public function __construct()
    {
        global $edd_options;
        $this->edd_options = $edd_options;
    }

    /**
     * Register payment gateway
     */
    abstract protected function init_paymentwall_config();

    /**
     * @return array
     */
    abstract protected function gateway_options();

    /**
     * @param $purchase_data
     * @return mixed
     */
    abstract public function process_purchase($purchase_data);

    /**
     * Initial and register payment gateway to edd
     */
    public function init()
    {
        add_filter('edd_payment_gateways', array($this, 'register_gateway'));
        add_filter('edd_settings_gateways', array($this, 'add_settings'));
        add_action("edd_{$this->gateway_id}_cc_form", array($this, 'gateway_cc_form'));
        add_action("edd_gateway_{$this->gateway_id}", array($this, 'process_purchase'));
    }

    /**
     * @param $gateways
     * @return mixed
     */
    public function register_gateway($gateways)
    {
        $gateways[$this->gateway_id] = $this->gateway_options();
        return $gateways;
    }

    /**
     * @param $templateFileName
     * @param $data
     * @return bool|mixed|string
     */
    protected function get_template($templateFileName, $data)
    {
        if (file_exists(PW_EDD_PLUGIN_PATH . 'templates/' . $templateFileName)) {
            $content = file_get_contents(PW_EDD_PLUGIN_PATH . 'templates/' . $templateFileName);
            foreach ($data as $key => $var) {
                $content = str_replace('{{' . $key . '}}', $var, $content);
            }
            return $content;
        }
        return false;
    }

    /**
     * @param $settings
     * @return array
     */
    public function add_settings($settings)
    {
        if (file_exists(PW_EDD_PLUGIN_PATH . 'includes/settings/' . $this->gateway_id . '.php')) {
            return array_merge($settings, include(PW_EDD_PLUGIN_PATH . 'includes/settings/' . $this->gateway_id . '.php'));
        }
    }

    /**
     * print cc form, default hide
     */
    public function gateway_cc_form()
    {
        // register the action to remove default CC form
        return;
    }
}