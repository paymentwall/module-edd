<?php
return array(
    array(
        'id' => 'paymentwall_settings',
        'name' => '<strong>' . __('Paymentwall Settings', PW_EDD_TEXT_DOMAIN) . '</strong>',
        'desc' => __('Configure your Paymentwall Settings', PW_EDD_TEXT_DOMAIN),
        'type' => 'header'
    ),
    array(
        'id' => 'paymentwall_name',
        'name' => __('Payment name', PW_EDD_TEXT_DOMAIN),
        'desc' => '',
        'type' => 'text',
    ),
    array(
        'id' => 'paymentwall_project_key',
        'name' => __('Project Key', PW_EDD_TEXT_DOMAIN),
        'desc' => __('Enter your Paymentwall Project Key that can be found in Project Settings inside of your Paymentwall Merchant Account', PW_EDD_TEXT_DOMAIN),
        'type' => 'text',
    ),
    array(
        'id' => 'paymentwall_secret_key',
        'name' => __('Secret Key', PW_EDD_TEXT_DOMAIN),
        'desc' => __('Enter your Paymentwall Secret Key that can be found in Project Settings inside of your Paymentwall Merchant Account', PW_EDD_TEXT_DOMAIN),
        'type' => 'text',
    ),
    array(
        'id' => 'paymentwall_widget_code',
        'name' => __('Widget Code', PW_EDD_TEXT_DOMAIN),
        'desc' => __('Enter your Paymentwall Widget Code that can be found in Widgets section inside of your Paymentwall Merchant Account, e.g. p1 or p1_1', PW_EDD_TEXT_DOMAIN),
        'type' => 'text',
        'size' => '10'
    ),
    array(
        'id' => 'paymentwall_widget_mode',
        'name' => __('Widget Mode', PW_EDD_TEXT_DOMAIN),
        'desc' => __('Payment happens on an external page or in-page', PW_EDD_TEXT_DOMAIN),
        'type' => 'select',
        'options' => array(
            'iframe' => __('In-page', PW_EDD_TEXT_DOMAIN),
            'redirect' => __('External page', PW_EDD_TEXT_DOMAIN)
        )
    )
);