<?php
/**
 * Plugin Name: Intram WooCommerce Plugin
 * Plugin URI: http://intram.cf/intram-wc-plugin
 * Description: Use Intram to receive paiement by Mobile Money account, credit card or bank account
 * Version: 1.0
 * Author: Intram PayCfa
 * Author URI: http://intram.cf
 */

require_once "vendor/intram/php-sdk/src/Intram.php";

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

function wc_offline_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Intram_Gateway';
    return $gateways;
}

add_filter('woocommerce_payment_gateways', 'wc_offline_add_to_gateways');

add_action('plugins_loaded', 'wc_offline_gateway_init', 11);


/**
 * Adds plugin action links.
 */
function intram_action_links($links)
{

    $links[] = '<a href="admin.php?page=wc-settings&tab=checkout&section=intram_gateway">' . __('Settings', 'wc-intram-paycfa') . '</a>';
    return $links;
}

add_action('plugin_action_links_' . plugin_basename(__FILE__), 'intram_action_links');


function wc_offline_gateway_init()
{

    class WC_Intram_Gateway extends WC_Payment_Gateway
    {

        /**
         * Constructor for the gateway. https://intram.cf/images/logo-1.png
         */


        public function __construct()
        {

            $this->intram_config = array();
            $this->dataWidget = [];

            $this->id = 'intram_gateway';
            $this->icon = apply_filters('woocommerce_offline_icon', plugins_url('assets/img/logo_small.png', __FILE__));
            $this->has_fields = false;
            $this->title = array_key_exists('title', $this->settings) ? $this->settings['title'] : '';
            $this->method_title = "Intram PayCfa";
            $this->method_description = array_key_exists('description', $this->settings) ? $this->settings['description'] : '';

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            $this->intram_config = array();
            foreach ($this->settings as $setting_key => $value) {
                $this->$setting_key = $value;
                $this->intram_config[$setting_key] = $value;
            }

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions', $this->description);

            $this->import_intramjs();
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'on_intram_back'));


            // Customer Emails
            //add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }


        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields()
        {

            $this->form_fields = apply_filters('wc_offline_form_fields', array(

                'enabled' => array(
                    'title' => __('Enable/Disable', 'wc-intram-paycfa'),
                    'type' => 'checkbox',
                    'label' => __('Enable Intram Payment', 'wc-intram-paycfa'),
                    'default' => 'yes'
                ),

                'testmode' => array(
                    'title' => __('Test Mode', 'wc-intram-paycfa'),
                    'label' => __('Enable Test Mode', 'wc-intram-paycfa'),
                    'type' => 'checkbox',
                    'description' => __('Set intram in tested mode', 'wc-intram-paycfa'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),

                'title' => array(
                    'title' => __('Intram PayCfa Payment', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-intram-paycfa'),
                    'default' => __('Intram Payment', 'wc-intram-paycfa'),
                    'desc_tip' => true,
                ),

                'description' => array(
                    'title' => __('Description', 'wc-intram-paycfa'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'wc-intram-paycfa'),
                    'default' => __('Please remit payment to Store Name upon pickup or delivery.', 'wc-intram-paycfa'),
                    'desc_tip' => true,
                ),

                'public_key' => array(
                    'title' => __('Public KEY', 'wc-intram-paycfa'),
                    'type' => 'password',
                    'desc_tip' => true,
                    'description' => __('Get your API keys from your Intram dashboard', 'wc-intram-paycfa')
                ),
                'private_key' => array(
                    'title' => __('Private KEY', 'wc-intram-paycfa'),
                    'type' => 'password',
                    'desc_tip' => true,
                    'description' => __('Get your API keys from your Intram dashboard', 'wc-intram-paycfa')
                ),
                'secret' => array(
                    'title' => __('Secret', 'wc-intram-paycfa'),
                    'type' => 'password',
                    'desc_tip' => true,
                    'description' => __('Get your API keys from your Intram dashboard', 'wc-intram-paycfa')
                ),
                'marchand_key' => array(
                    'title' => __('Marchand', 'wc-intram-paycfa'),
                    'type' => 'password',
                    'desc_tip' => true,
                    'description' => __('Get your Marchand key from your Intram dashboard', 'wc-intram-paycfa')
                ),
                'color' => array(
                    'title' => __('Color (Optional)', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'class' => 'colorpick',
                    'description' => __('When Intram payment form is displayed directly on your site, you can choose the widget color (eg: #351111) according to your website colors. ', 'wc-intram-paycfa')
                ),
                'template' => array(
                    'title' => __('Template (Optional)', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __('When the Intram payment form is displayed directly on our site, you can use the theme (ex: XXXXCCCC)', 'wc-intram-paycfa')
                ),
                'currency' => array(
                    'title' => __('Currency', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __('ex: XOF', 'wc-intram-paycfa')
                ),

                'url' => array(
                    'title' => __('Add link to your shop logo (Optional)', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __("Enter the URL to a 128x128px image of your store. e.g. <code>https://intram.cf/images/logo-1.png</code>", 'wc-intram-paycfa')
                ),
                'nameStore' => array(
                    'title' => __('Name Store', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __("(required)", 'wc-intram-paycfa')
                ),
                'webSiteUrlStore' => array(
                    'title' => __('Web Site Url', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __("(required)", 'wc-intram-paycfa')
                ),
                'phoneStore' => array(
                    'title' => __('Phone store', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __("(required)", 'wc-intram-paycfa')
                ),
                'postalAdressStore' => array(
                    'title' => __('Postal Adress', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __("(required)", 'wc-intram-paycfa')
                ),
                'returnUrl' => array(
                    'title' => __('Return Url', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __("(required)", 'wc-intram-paycfa')
                ),
                'cancelUrl' => array(
                    'title' => __('Cancel Url', 'wc-intram-paycfa'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __("(required)", 'wc-intram-paycfa')
                )
            ));
        }


        public function import_intramjs()
        {

            $filename = 'intram-wc-plugin/intram-wc-plugin.php';
            $path = plugin_dir_path(__DIR__) . $filename;
            $plugin_information = get_plugin_data($path);

            wp_enqueue_script('setup-intram-script', "https://cdn.intram.org/sdk-javascript.js", [], $plugin_information['Version'], true);
            wp_register_script('init-intram-open-widget', plugins_url('assets/js/openWidgetIntram.js', __FILE__), ['setup-intram-script'], 'v1', true);
            if ($this->testmode == 'yes') {
                $sandbox = true;
            } else {
                $sandbox = false;
            }

            $this->paycfa = new Intram\Intram(
                $this->public_key, $this->private_key,
                $this->secret,
                $this->marchand_key, $sandbox);
        }


        public function process_payment($order_id)
        {


            $order = new WC_Order($order_id);

            $this->order_id = $order_id;

            $order->reduce_order_stock();

            $this->paycfa->setNameStore($this->nameStore);
            $this->paycfa->setLogoUrlStore($this->url);
            $this->paycfa->setWebSiteUrlStore($this->webSiteUrlStore);
            $this->paycfa->setPhoneStore($this->phoneStore);
            $this->paycfa->setPostalAdressStore($this->postalAdressStore);
            $items = $order->get_items();
            $tab_items = [];
            $i = 0;
            foreach ($items as $item) {

                $tab_items[$i] = ['name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'price' => ($item->get_subtotal() / $item->get_quantity()),
                    'total_amount' => $item->get_total()];
                $i++;
            }

            $this->paycfa->setItems($tab_items);
            $this->paycfa->setTva([["name" => "VAT (18%)", "amount" => 0]]);
            $this->paycfa->setCustomData([]);

            if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>=')) {
                $this->paycfa->setAmount($order->get_total());
            } else {
                $this->paycfa->setAmount($order->order_total);
            }

            $this->paycfa->setCurrency($this->currency);
            $this->paycfa->setDescription($this->description);
            $this->paycfa->setTemplate($this->template);
            $this->paycfa->setRedirectionUrl($this->get_callback_url($order_id));
            $this->paycfa->setReturnUrl($this->returnUrl);
            $this->paycfa->setCancelUrl($this->cancelUrl);
            $response = $this->paycfa->setRequestPayment();
            $receipt_url = $response->receipt_url;
            $transaction_id = $response->transaction_id;
            $error = $response->status != "PENDING";

            if (!$error) {
                $this->dataWidget = [
                    "url" => $receipt_url,
                    "transaction_id" => $transaction_id,
                    "public_key" => $this->public_key,
                    "nameStore" => $this->nameStore,
                    "color" => $this->color,
                    "urlLogo" => $this->url,
                    "callback_url" => $this->get_callback_url($order_id),
                    "sandbox" => $this->testmode == 'yes',
                    "response" => $response
                ];
                session_start();
                $_SESSION['dataWidget'] = $this->dataWidget;

                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

        }


        /**
         * Checkout receipt page
         *
         * @return void
         */
        public function receipt_page($order)
        {
            //TODO: add transaction reason

            $order = wc_get_order($order);
            echo '<p>' . __('Thank you for your order, please click the <b>Proceed to payment</b> button below to make payment.', 'wc-intram-paycfa') . '</p>';
            echo '<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">';
            echo __('Cancel order', 'wc-intram-paycfa') . '</a> ';
            echo '<button class="button alt  wc-forward"  id="btn-intram-open-widget">' . __('Proceed to payment', 'wc-intram-paycfa') . '</button> ';
            session_start();
            $this->request_intram_payment($_SESSION['dataWidget']);
        }

        public function get_callback_url($order_id)
        {
            return home_url('/') . '?wc-api=' . get_class($this) . '&state=' . $order_id;
        }

        public function request_intram_payment($data)
        {
            wp_enqueue_script('init-intram-open-widget');
            wp_localize_script('init-intram-open-widget', 'data', $data);
        }

        public function on_intram_back()
        {
            global $woocommerce;
            session_start();
            $pos = strpos($_GET["state"], "?");
            $order_id = substr($_GET["state"],0,$pos);
            $order = wc_get_order($order_id);

            if ($order) {
                $order->update_status('completed');
                $order->add_order_note(__('Intram Payment was successful', 'wc-intram-paycfa'));
                $order->add_order_note('Intram transaction: ' . $_SESSION['dataWidget']['transaction_id']);
                $customer_note = __('Thank you for your order.<br>', 'wc-intram-paycfa');
                $customer_note .= __('Your payment was successful, we are now <strong>processing</strong> your order.', 'wc-intram-paycfa');
                $order->add_order_note($customer_note, 1);
                wc_add_notice($customer_note, 'notice');
                $woocommerce->cart->empty_cart();
                $url = get_site_url().'/index.php/my-account/view-order/'.$order_id;
                wp_redirect($url);
            }else{
                $customer_note = __('Your payment <strong>failed</strong>. ', 'wc-intram-paycfa');
                $customer_note .= __('Please, try funding your account. : '.$this->get_return_url($order), 'wc-intram-paycfa');
                wc_add_notice($customer_note, 'notice');
                $url = wc_get_checkout_url();
                wp_redirect($url);
            }

        }

        public function handlePaymentFailed($order)
        {
            $order->add_order_note(__('The order payment failed on Intram', 'wc-intram-paycfa'));
            $customer_note = __('Your payment <strong>failed</strong>. ', 'wc-intram-paycfa');
            $customer_note .= __('Please, try funding your account.', 'wc-intram-paycfa');
            $order->add_order_note($customer_note, 1);
            wc_add_notice($customer_note, 'notice');

            $url = wc_get_checkout_url();
            wp_redirect($url);
        }

    }
}
