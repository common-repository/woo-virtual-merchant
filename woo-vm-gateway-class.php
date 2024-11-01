<?php

if (!class_exists('WC_Payment_Gateway')){
    //Woocommerce is not active.
    return;
}

class WC_VM_Gateway extends WC_Payment_Gateway {

    protected $VM_SANDBOX_URI = "https://api.demo.convergepay.com/VirtualMerchantDemo/processxml.do";
    protected $VM_LIVE_URI = "https://api.convergepay.com/VirtualMerchant/processxml.do";
    protected $VM_NVP_PAYMENTACTION = "Sale";
    protected $VM_NVP_METHOD = "DoDirectPayment";
    protected $order = null;
    protected $transactionId = null;
    protected $transactionErrorMessage = null;
    protected $usesandboxapi = true;
    protected $securitycodehint = true;
    protected $apivmmerchantid = '';
    protected $apivmuserid = '';
    protected $apivmpin = '';

    public function __construct() {
        $this->id = 'virtualmerchant';//ID needs to be ALL lowercase or it doens't work
        $this->GATEWAYNAME = 'Virtual-Merchant';
        $this->method_title = 'Virtual-Merchant';
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->description = '';
        $this->usesandboxapi = strcmp($this->settings['debug'], 'yes') == 0;
        $this->securitycodehint = strcmp($this->settings['securitycodehint'], 'yes') == 0;
        //If the field is populated, it will grab the value from there and will not be translated.  If it is empty, it will use the default and translate that value
        $this->title = strlen($this->settings['title']) > 0 ? $this->settings['title'] : __('Credit Card Payment', 'woocommerce');
        $this->apivmmerchantid = $this->settings['apivmmerchantid'];
        $this->apivmuserid = $this->settings['apivmuserid'];
        $this->apivmpin = $this->settings['apivmpin'];        
        
        add_filter('http_request_version', array(&$this, 'use_http_1_1'));                
        add_action('admin_notices', array(&$this, 'handle_admin_notice_msg'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
        
    }

    public function admin_options() {
        ?>
        <h3><?php _e('Virtual Merchant', 'woocommerce'); ?></h3>
        <p><?php _e('Allows Credit Card Payments via the Virtual Merchant gateway.', 'woocommerce'); ?></p>
        
        <table class="form-table">
            <?php 
            //Render the settings form according to what is specified in the init_form_fields() function
            $this->generate_settings_html(); 
            ?>
        </table>
        <?php
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Virtual Merchant Gateway', 'woocommerce'),
                'default' => 'yes'
            ),
            'debug' => array(
                'title' => __('Sandbox Mode', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Sandbox Mode', 'woocommerce'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('The title for this checkout option.', 'woocommerce'),
                'default' => __('Credit Card Payment', 'woocommerce')
            ),
            'securitycodehint' => array(
                'title' => __('Show CVV Hint', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable this option if you want to show a hint for the CVV field on the credit card checkout form', 'woocommerce'),
                'default' => 'no'
            ),
            'apivmmerchantid' => array(
                'title' => __('Virtual Merchant ID', 'woocommerce'),
                'type' => 'text',
                'description' => __('Your Virtual Merchant ID.', 'woocommerce'),
                'default' => __('', 'woocommerce')
            ),
            'apivmuserid' => array(
                'title' => __('Virtual Merchant User ID', 'woocommerce'),
                'type' => 'text',
                'description' => __('Your Virtual Merchant User ID.', 'woocommerce'),
                'default' => __('', 'woocommerce')
            ),
            'apivmpin' => array(
                'title' => __('Virtual Merchant Pin', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Your Virtual Merchant Pin.', 'woocommerce'),
                'default' => __('', 'woocommerce')
            )
        );
    }

    function handle_admin_notice_msg() {
        if (!$this->usesandboxapi && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes'){
            echo '<div class="error"><p>' . sprintf(__('%s gateway requires SSL certificate for better security. The <a href="%s">force SSL option</a> is disabled on your site. Please ensure your server has a valid SSL certificate so you can enable the SSL option on your checkout page.', 'woocommerce'), $this->GATEWAYNAME, admin_url('admin.php?page=woocommerce_settings&tab=general')) . '</p></div>';
        }
    }
    

    /*
     * Validates the fields specified in the payment_fields() function.
     */
    public function validate_fields() {
        global $woocommerce;

        if (!WC_VM_Utility::is_valid_card_number($_POST['billing_credircard'])){
            wc_add_notice(__('Credit card number you entered is invalid.', 'woocommerce'), 'error');
        }
        if (!WC_VM_Utility::is_valid_expiry($_POST['billing_expdatemonth'], $_POST['billing_expdateyear'])){
            wc_add_notice(__('Card expiration date is not valid.', 'woocommerce'), 'error');
        }
        if (!WC_VM_Utility::is_valid_cvv_number($_POST['billing_ccvnumber'])){
            wc_add_notice(__('Card verification number (CVV) is not valid. You can find this number on your credit card.', 'woocommerce'), 'error');
        }
    }
    
    /*
     * Render the credit card fields on the checkout page
     */
    public function payment_fields() {
        $billing_credircard = isset($_REQUEST['billing_credircard'])? esc_attr($_REQUEST['billing_credircard']) : '';
        ?>
        <p class="form-row validate-required">
            <label><?php _e('Card Number', 'woocommerce'); ?> <span class="required">*</span></label>
            <input class="input-text" type="text" size="19" maxlength="19" name="billing_credircard" value="<?php echo $billing_credircard; ?>" />
        </p>
        <div class="clear"></div>
        <p class="form-row form-row-first">
            <label><?php _e('Expiration Date', 'woocommerce'); ?> <span class="required">*</span></label>
            <select name="billing_expdatemonth">
                <option value=1>01</option>
                <option value=2>02</option>
                <option value=3>03</option>
                <option value=4>04</option>
                <option value=5>05</option>
                <option value=6>06</option>
                <option value=7>07</option>
                <option value=8>08</option>
                <option value=9>09</option>
                <option value=10>10</option>
                <option value=11>11</option>
                <option value=12>12</option>
            </select>
            <select name="billing_expdateyear">
            <?php
            $today = (int)date('Y', time());
            for($i = 0; $i < 8; $i++)
            {
            ?>
                <option value="<?php echo $today; ?>"><?php echo $today; ?></option>
            <?php
                $today++;
            }
            ?>
            </select>
        </p>
        <div class="clear"></div>
        <p class="form-row form-row-first validate-required">
            <label><?php _e('Card Verification Number (CVV)', 'woocommerce'); ?> <span class="required">*</span></label>
            <input class="input-text" type="text" size="4" maxlength="4" name="billing_ccvnumber" value="" />
        </p>
        <?php if ($this->securitycodehint){ 
        $cvv_hint_img = WC_VM_ADDON_URL.'/images/hint.png';
        $cvv_hint_img = apply_filters('wcpprog-cvv-image-hint-src', $cvv_hint_img);
        echo '<div class="vm-security-code-hint-section">';
        echo '<img src="'.$cvv_hint_img.'" />';
        echo '</div>';
        } 
        ?>
        <div class="clear"></div>
        
        <?php
    }

    public function process_payment($order_id) {
        global $woocommerce;
        $this->order = new WC_Order($order_id);
        $gatewayRequestData = $this->create_vm_request();

        if ($gatewayRequestData && $this->verify_vm_payment($gatewayRequestData)) {
            $this->do_order_complete_tasks();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
        } else {
            $this->mark_as_failed_payment();
            wc_add_notice(__('(Transaction Error) something is wrong.', 'woocommerce'),'error');
        }
    }

    /*
     * Set the HTTP version for the remote posts
     * https://developer.wordpress.org/reference/hooks/http_request_version/
     */
    public function use_http_1_1($httpversion) {
        return '1.1';
    }

    protected function mark_as_failed_payment() {
        $this->order->add_order_note(sprintf("Paypal Credit Card Payment Failed with message: '%s'", $this->transactionErrorMessage));
    }

    protected function do_order_complete_tasks() {
        global $woocommerce;

        if ($this->order->status == 'completed')
            return;

        $this->order->payment_complete();
        $woocommerce->cart->empty_cart();

        $this->order->add_order_note(
                sprintf("Virtual Merchant Card payment completed with Transaction Id of '%s'", $this->transactionId)
        );

        unset($_SESSION['order_awaiting_payment']);
    }

    protected function verify_vm_payment($gatewayRequestData) {
        global $woocommerce;

        $erroMessage = "";
        $api_url = $this->usesandboxapi ? $this->VM_SANDBOX_URI : $this->VM_LIVE_URI;
        $request = array(
            'method' => 'POST',
            'timeout' => 45,
            'blocking' => true,
            'sslverify' => $this->usesandboxapi ? false : true,
            'body' => $gatewayRequestData
        );

        $response = wp_remote_post($api_url, $request);
        if (!is_wp_error($response)) {
            $parsedResponse = $this->parse_vm_response($response);
            if (array_key_exists('ssl_result_message', $parsedResponse)) {
                switch ($parsedResponse['ssl_result_message']) {
                    case 'APPROVAL':
                        $this->transactionId = $parsedResponse['ssl_txn_id'];
                        return true;
                        break;

                    default:
                        $this->transactionErrorMessage = $erroMessage = $parsedResponse['L_LONGMESSAGE0'];
                        break;
                }
            } else{
                $erroMessage = 'Something went wrong while performing your request. Please contact website administrator to report this problem.';
            }
        } else {
            // Uncomment to view the http error
            //$erroMessage = print_r($response->errors, true);
            $erroMessage = $parsedResponse['errorCode'].': '.$parsedResponse['errorName'].' <br> '.$parsedResponse['errorMessage'];
            wc_add_notice($erroMessage,'error');
            return false;
        }

        wc_add_notice($erroMessage,'error');
        return false;
    }

    protected function parse_vm_response($response) {
        $result = array();
        $response = $response['body'];

        $xml = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $result = json_decode($json,TRUE);

        return $result;
    }

    protected function create_vm_request() {
        if ($this->order AND $this->order != null) {
            $req = array(
                'ssl_merchant_id' => $this->apivmmerchantid,
                'ssl_user_id' => $this->apivmuserid,
                'ssl_pin' => $this->apivmpin,
                'ssl_amount' => $this->order->get_total(),
                'ssl_first_name' => ( WC()->version < '2.7.0' ) ? $this->order->billing_first_name : $this->order->get_billing_first_name(),
                'ssl_last_name' => ( WC()->version < '2.7.0' ) ? $this->order->billing_last_name : $this->order->get_billing_last_name(),

                'ssl_city' => ( WC()->version < '2.7.0' ) ? $this->order->billing_city : $this->order->get_billing_city(),

                'ssl_state' => ( WC()->version < '2.7.0' ) ? $this->order->billing_state : $this->order->get_billing_state(),

                'ssl_avs_zip' => ( WC()->version < '2.7.0' ) ? $this->order->billing_postcode : $this->order->get_billing_postcode(),

                'ssl_card_number' => $_POST['billing_credircard'],
                'ssl_company' => $_POST['billing_company'],
                'ssl_email' => $_POST['billing_email'],
                'ssl_phone' => $_POST['billing_phone'],
                'ssl_cvv2cvc2' => $_POST['billing_ccvnumber'],
                'ssl_test_mode' => 'false',
                'ssl_transaction_type' => 'ccsale',
                'ssl_cvv2cvc2_indicator' => 1,
                'ssl_exp_date' => sprintf('%s%s', sprintf("%02d", $_POST['billing_expdatemonth']), substr($_POST['billing_expdateyear'], 2)),
                'ssl_avs_address' => sprintf('%s, %s', $_POST['billing_address_1'], $_POST['billing_address_2'])
            );
            $return_str = '<txn>';
            foreach ($req as $key => $value) {
                $return_str.="<{$key}>$value</{$key}>";
            }
            $return_str.='<ssl_transaction_type>ccsale</ssl_transaction_type>';
            $return_str.= '</txn>';
            return array('xmldata'=>$return_str);

        }
        return false;
    }
    
}//End of class