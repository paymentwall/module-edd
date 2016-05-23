<?php
/**
 * Plugin Name: Paymentwall for Easy Digital Downloads
 * Plugin URI: http://www.paymentwall.com/en/documentation/Easy-Digital-Downloads/1741?source=edd
 * Description: Allows to use Paymentwall as a payment gateway for Easy Digital Downloads
 * Version: 1.1.1
 * Author: Paymentwall Integration Team
 * Author URI: https://www.paymentwall.com/?source=edd
 * License: The MIT License (MIT)
 */


define('PW_EDD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PW_EDD_PLUGIN_URL', plugins_url('', __FILE__));
define('PW_EDD_TEXT_DOMAIN', 'paymentwall-for-easy-digital-downloads');

require_once PW_EDD_PLUGIN_PATH . 'lib/paymentwall-php/lib/paymentwall.php';
require_once PW_EDD_PLUGIN_PATH . 'includes/class-paymentwall-abstract.php';
require_once PW_EDD_PLUGIN_PATH . 'includes/class-paymentwall-gateway.php';
require_once PW_EDD_PLUGIN_PATH . 'includes/class-paymentwall-brick.php';


/**
 * Check EDD plugin install
 */
add_action('admin_init', 'child_plugin_has_parent_plugin');
function child_plugin_has_parent_plugin()
{
    if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('easy-digital-downloads/easy-digital-downloads.php')) {
        add_action('admin_notices', 'child_plugin_notice');

        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

function child_plugin_notice()
{
    ?>
    <div class="error">
        <p>Sorry, but Paymentwall Plugin requires the EDD plugin to be installed and active.</p>
    </div>
<?php
}

/**
 * Registers the gateway
 * @param $gateways
 * @return mixed
 */
$paymentwall_gateway = new edd_paymentwall_gateway();
$paymentwall_gateway->init();


$paymentwall_gateway = new edd_paymentwall_brick();
$paymentwall_gateway->init();

