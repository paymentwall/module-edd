<?php
return array(
    array(
        'id' => 'brick_settings',
        'name' => '<strong>' . __('Brick Settings', PW_EDD_TEXT_DOMAIN) . '</strong>',
        'desc' => __('Configure your Brick Settings', PW_EDD_TEXT_DOMAIN),
        'type' => 'header'
    ),
    array(
        'id' => 'brick_name',
        'name' => __('Payment name', PW_EDD_TEXT_DOMAIN),
        'desc' => '',
        'type' => 'text',
    ),
    array(
        'id' => 'brick_public_key',
        'name' => __('Public Key', PW_EDD_TEXT_DOMAIN),
        'desc' => __('Enter your Paymentwall Public Key that can be found in Project Settings inside of your Paymentwall Merchant Account', PW_EDD_TEXT_DOMAIN),
        'type' => 'text',
    ),
    array(
        'id' => 'brick_private_key',
        'name' => __('Private Key', PW_EDD_TEXT_DOMAIN),
        'desc' => __('Enter your Paymentwall Private Key that can be found in Project Settings inside of your Paymentwall Merchant Account', PW_EDD_TEXT_DOMAIN),
        'type' => 'text',
    ),
    array(
        'id' => 'brick_public_test_key',
        'name' => __('Public Test Key', PW_EDD_TEXT_DOMAIN),
        'desc' => '',
        'type' => 'text',
    ),
    array(
        'id' => 'brick_private_test_key',
        'name' => __('Private Test Key', PW_EDD_TEXT_DOMAIN),
        'desc' => '',
        'type' => 'text',
    )
);