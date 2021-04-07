<?php
/**
 * Thawani Payment Gateway v1
 *   Error fixes for post payment redirect
 */
defined('ABSPATH') OR exit('Direct access not allowed');
if (!class_exists("WC_Thawani")) {

    class WC_Thawani extends WC_Payment_Gateway
    {
        public static $log_enabled = false;
        public static $log = false;
        protected $templateFields = array();
        private $domainName = "";
        private $test_mode = false;
        private $thawani_secret_key = "";
        private $thawani_publishable_key = "";
        private $api_return_url = "";
        private $after_payment_status = "";
        private $thawani_session = "";
        private $thawani_reference = "";
        private $thawani_receipt = "";
        private $thawani_pay_url = "";
        private $current_order_session_id = "";
        private $current_customer_card_available = "";

        private $current_customer_card_masked_card = "";
        private $current_customer_card_nickname = "";
        private $current_customer_card_brand = "";

        private $current_customer_card_intent = "";
        private $current_customer_card_authorize_url = "";

        private $thawani_customer = "";
        private $thawani_payment_method = "";
        private $thawani_payment_intent = "";

        private $msg = array();
        private $order_status_messege = array(
            "wc-processing" => "Awaiting admin confirmation.",
            "wc-on-hold" => "Awaiting admin confirmation.",
            "wc-cancelled" => "Order Cancelled",
            "wc-completed" => "Successful",
            "wc-pending" => "Awaiting admin confirmation.",
            "wc-failed" => "Order Failed",
            "wc-refunded" => "Payment Refunded."
        );

        public function __construct()
        {
            $this->id = 'wc_thawani';
            $this->icon = plugins_url('wc_thawani/images/logo.png', WC_THAWANI_PATH);
            $this->method_title = __('Thawani', 'thawani');
            $this->method_description = __('Thawani is most popular payment gateway for online shopping in Oman.', 'thawani');
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->test_mode = 'yes' === $this->get_option('environment', 'no');
            $this->thawani_secret_key = $this->get_option('thawani_secret_key');
            $this->thawani_publishable_key = $this->get_option('thawani_publishable_key');
            
            $this->after_payment_status = $this->get_option('after_payment_status');

            if ($this->test_mode) {
                self::$log_enabled = true;
                $this->description .= ' ' . sprintf(__('TEST MODE ENABLED. You can use test credentials. See the <a href="%s">Testing Guide</a> for more details.', 'thawani'), 'https://developer.thawani.om/#test_cards');
                $this->description = trim($this->description);
                $this->domainName = "https://uatcheckout.thawani.om/api/v1/checkout/";
                $this->thawani_pay_url = "http://uatcheckout.thawani.om/pay/";
                $this->thawani_customer = "https://uatcheckout.thawani.om/api/v1/customers/";
                $this->thawani_payment_method = "https://uatcheckout.thawani.om/api/v1/payment_methods/";
                $this->thawani_payment_intent = "https://uatcheckout.thawani.om/api/v1/payment_intents/";
            } else {
                $this->domainName = "https://checkout.thawani.om/api/v1/checkout/";
                $this->thawani_pay_url = "http://checkout.thawani.om/pay/";
                $this->thawani_customer = "https://checkout.thawani.om/api/v1/customers/";
                $this->thawani_payment_method = "https://checkout.thawani.om/api/v1/payment_methods/";
                $this->thawani_payment_intent = "https://checkout.thawani.om/api/v1/payment_intents/";
                
                
            }

            $this->thawani_session = $this->domainName . "session/";
            $this->thawani_reference = $this->domainName . "reference/";
            $this->thawani_receipt = $this->domainName . "receipt/";
            

            

            if ($this->is_valid_for_use()) {
                //IPN actions
                $this->notify_url = str_replace('https:', 'http:', home_url('/wc-api/WC_Thawani'));
                add_action('woocommerce_api_wc_thawani', array($this, 'check_thawani_response'));
                add_action('valid-thawani-request', array($this, 'successful_request'));
            } else {
                $this->enabled = 'no';
            }

            //save admin settings
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        }

        /**
         * Admin Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable / Disable', 'thawani'),
                    'label' => __('Enable this payment gateway', 'thawani'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'thawani'),
                    'type' => 'text',
                    'desc_tip' => __('Payment title the customer will see during the checkout process.', 'thawani'),
                    'default' => __('thawani', 'thawani'),
                ),
                'description' => array(
                    'title' => __('Description', 'thawani'),
                    'type' => 'textarea',
                    'desc_tip' => __('Payment description the customer will see during the checkout process.', 'thawani'),
                    'default' => __('Pay securely using Thawani', 'thawani'),
                    'css' => 'max-width:350px;'
                ),
                'thawani_secret_key' => array(
                    'title' => __('Secret Key', 'thawani'),
                    'type' => 'text',
                    'desc_tip' => __('Secret Key provided by thawani to use in the Thawani Checkout.', 'thawani'),
                ),
                'thawani_publishable_key' => array(
                    'title' => __('Publishable Key', 'thawani'),
                    'type' => 'text',
                    'desc_tip' => __('Publishable Key provided by thawani to use in the Thawani Checkout.', 'thawani'),
                ),
                
                'after_payment_status' => array(
                    'title' => __('After Successful Payment Order Status', 'thawani'),
                    'type' => 'select',
                    'description' => __('Change Order Status', 'thawani'),
                    'options' => array(
                        "wc-processing" => "Processing",
                        "wc-on-hold" => "On-Hold",
                        "wc-cancelled" => "Cancelled",
                        "wc-completed" => "Completed",
                        "wc-pending" => "Pending",
                        "wc-failed" => "Failed",
                        "wc-refunded" => "Refunded"
                    ),
                    'default' => 'wc-completed',
                ),
                'environment' => array(
                    'title' => __('Test Mode', 'thawani'),
                    'label' => __('Enable Test Mode', 'thawani'),
                    'type' => 'checkbox',
                    'description' => __('Place the payment gateway in test mode.', 'thawani'),
                    'default' => 'no',
                )
            );
        }

        /**
         * Only allowed for only OMR currency
         */
        public function is_valid_for_use()
        {
            return in_array(get_woocommerce_currency(), array('OMR'), true);
        }

        /**
         * Logger for Thawani
         * @param $message
         * @param string $level
         */
        public static function log($message, $level = 'info')
        {
            if (self::$log_enabled) {
                if (empty(self::$log)) {
                    self::$log = wc_get_logger();
                }
                self::$log->log($level, $message, array('source' => 'thawani'));
            }
        }

        /**
         * Processes and saves options.
         * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
         *
         * @return bool was anything saved?
         */
        public function process_admin_options()
        {
            $saved = parent::process_admin_options();
            return $saved;
        }

        /**
         * Admin Panel Options.
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options()
        {
            if ($this->is_valid_for_use()) {
                parent::admin_options();
            } else {
                ?>
                <div class="thawani_error">
                    <p>
                        <strong><?php esc_html_e('Gateway disabled', 'thawani'); ?></strong>: <?php esc_html_e('Thawani does not support your store currency.', 'thawani'); ?>
                    </p>
                </div>
                <?php
            }
        }

        /**
         *  There are no payment fields for Thawani, but we want to show the description if set.
         **/
        public function payment_fields()
        {
            if ($this->description) echo wpautop(wptexturize($this->description));
        }

        /**
         * Receipt Page.
         * @param $order
         */
        public function receipt_page($order)
        {
            $this->create_session_key($order);

            
            if($this->current_order_session_id != '' && $this->current_customer_card_available == '')
            {
                //echo '<p>' . __('Thank you for your order, please click the button below to pay with Thawani.', 'thawani') . '</p>';
                $this->thawani_pay_url = $this->thawani_pay_url.$this->current_order_session_id.'?key='.$this->thawani_publishable_key;
                wp_redirect($this->thawani_pay_url);
                exit;
                
                
            }
            else if($this->current_order_session_id != '' && $this->current_customer_card_available == 'yes')
            {
                echo '<p>' . __('Thank you for your order, We have found a card which was used before by you on our website. Do you want to use that again.', 'thawani') . '</p><br>';

                ?>
                <table>
                    <tbody>
                        <tr>
                            <td>
                                <?php echo '<p><strong>' . __('Masked Card', 'thawani') . '</strong></p>'; ?>
                            </td>
                            <td>
                                <?php echo '<p>' . $this->current_customer_card_masked_card . '</p>'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?php echo '<p><strong>' . __('Nick Name', 'thawani') . '</strong></p>'; ?>
                            </td>
                            <td>
                                <?php echo '<p>' . $this->current_customer_card_nickname . '</p>'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?php echo '<p><strong>' . __('Brand', 'thawani') . '</strong></p>'; ?>
                            </td>
                            <td>
                                <?php echo '<p>' . $this->current_customer_card_brand . '</p>'; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <br>
                <?php


                $this->thawani_pay_url = $this->thawani_pay_url.$this->current_order_session_id.'?key='.$this->thawani_publishable_key;

                echo '<a href="'.$this->thawani_pay_url.'" style="background:#fff;padding:10px 20px; display: inline-block;border:1px solid #73b734" >'. __('No', 'thawani') .'</a>';

                echo '<a href="'.$this->current_customer_card_authorize_url.'" style="background:#fff;padding:10px 20px;margin:0 20px; display: inline-block;border:1px solid #73b734" >'. __('Yes', 'thawani') .'</a>';
            }
            else
            {
                ?>
                <div class="error" style="background: red; color: #fff;">
                    Some Error Occur please try again later.
                </div>
                <?php
            }
            
        }
        public function create_session_key($order)
        {
            if (empty($order)) return null;
            if (!is_object($order)) {
                $order = wc_get_order( $order );
            }
            $site_title = get_bloginfo( 'name' );
            $order_total = (int)($order->get_total()*1000);
            $cancel_url = wc_get_checkout_url();
            $order_new_key = $order->get_id();
            $success_url = home_url('/wc-api/WC_Thawani').'/?thawani_key='.$order_new_key;


            $rand_val = rand(1,100000);

            $client_reference_id = $order->get_id();
            

            $user_email = $order->get_billing_email();
            $user_phone = $order->get_billing_phone();
            $user_full_name = $order->get_billing_first_name().' '.$order->get_billing_last_name();

            $customer_token = $card_token = "";

            if(is_user_logged_in())
            {
                $user = wp_get_current_user();
                $user_token = get_the_author_meta( 'thawani_customer_token', $user->ID );
                if(empty($user_token) || $user_token=="")
                {
                    $curl = curl_init();
                    $user_email = $user->user_email;
                    curl_setopt_array($curl, [
                      CURLOPT_URL => $this->thawani_customer,
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 30,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => "POST",
                      CURLOPT_POSTFIELDS => "{\n\t\"client_customer_id\": \"".$user_email."\"\n}",
                      CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "thawani-api-key: ".$this->thawani_secret_key
                      ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);

                    if ($err) {
                      
                    } else {
                      
                        $response = json_decode($response);
                        $customer_token = $response->data->id;
                        update_user_meta($user->ID,'thawani_customer_token',$customer_token);
                        
                    }
                    
                }
                else
                {
                    $customer_token = $user_token;
                }

                $user_card = get_the_author_meta('thawani_customer_card',$user->ID);

                if(empty($user_card) || $user_card=="")
                {
                    $curl = curl_init();

                    curl_setopt_array($curl, [
                      CURLOPT_URL => $this->thawani_payment_method ."?customerId=".$customer_token,
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 30,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => "GET",
                      CURLOPT_POSTFIELDS => "",
                      CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "thawani-api-key: ".$this->thawani_secret_key
                      ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);

                    if ($err) {
                      
                    } else {
                            $response = json_decode($response);
                            
                            $card_token = $response->data[0]->id;
                            $masked_card = $response->data[0]->masked_card;
                            $nickname = $response->data[0]->nickname;
                            $brand = $response->data[0]->brand;
                            update_user_meta($user->ID,'thawani_customer_card',$card_token);
                            update_user_meta($user->ID,'thawani_customer_masked_card',$masked_card);
                            update_user_meta($user->ID,'thawani_customer_card_nickname',$nickname);
                            update_user_meta($user->ID,'thawani_customer_card_brand',$brand);
                    }
                    
                }
                else
                {
                    $card_token = $user_card;
                    
                }

            }
            
            if(is_user_logged_in() && $card_token != "")
            {
                $this->current_customer_card_available = 'yes';
                $this->current_customer_card_masked_card = get_the_author_meta('thawani_customer_masked_card',$user->ID);
                $this->current_customer_card_nickname = get_the_author_meta('thawani_customer_card_nickname', $user->ID);
                $this->current_customer_card_brand = get_the_author_meta('thawani_customer_card_brand',$user->ID);

                $curl = curl_init();

                curl_setopt_array($curl, [
                CURLOPT_URL => $this->thawani_payment_intent,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => "",
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 30,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => "POST",
                  CURLOPT_POSTFIELDS => '{"payment_method_id": "'.$card_token.'","amount": "'.$order_total.'","client_reference_id":"'.$client_reference_id.'","return_url": "'.$success_url.'","metadata": {"Customer Name": "'.$user_full_name.'","Customer Email": "'.$user_email.'","Customer Phone Number": "'.$user_phone.'","order_id": '.$order->get_id().'}}',
                  CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "thawani-api-key: ".$this->thawani_secret_key
                  ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);

                curl_close($curl);

                if ($err) {
                  $this->current_customer_card_intent = '';
                } else {
                  $response = json_decode($response);
                  $this->current_customer_card_intent = $response->data->id;

                  $curl = curl_init();

                    curl_setopt_array($curl, [
                    CURLOPT_URL => $this->thawani_payment_intent.$this->current_customer_card_intent.'/confirm/',
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 30,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => "POST",
                      CURLOPT_POSTFIELDS => '',
                      CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "thawani-api-key: ".$this->thawani_secret_key
                      ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);

                    if ($err) {
                      $this->current_customer_card_authorize_url = '';
                    } else {
                      $response = json_decode($response);
                      $this->current_customer_card_authorize_url = $response->data->next_action->url;
                      
                    }


                }

            }
            else
            {
                $this->current_customer_card_available = '';
            }
            $curl = curl_init();
            curl_setopt_array($curl, [
              CURLOPT_URL => $this->thawani_session,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => '{"client_reference_id": "'.$client_reference_id.'","customer_id" : "'.$customer_token.'","products": [{"name": "For Order at '.$site_title.'","unit_amount": '.$order_total.',"quantity": 1}],"success_url": "'.$success_url.'","cancel_url": "'.$cancel_url.'","metadata": {"Customer Name": "'.$user_full_name.'","Customer Email": "'.$user_email.'","Customer Phone Number": "'.$user_phone.'","order_id": '.$order->get_id().'}}',

              CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "thawani-api-key: ".$this->thawani_secret_key
              ],
            ]);
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                $this->current_order_session_id = '';
            } else {
                $response = json_decode($response);
                
                $this->current_order_session_id = $response->data->session_id;
            }

            
        }
        /**
         * Process the payment and return the result.
         * @param $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
        }

        /**
         * Check for valid Thawani server callback
         **/
        public function check_thawani_response()
        {
            global $woocommerce;
            
            
            if(!isset($_REQUEST['thawani_key']) && empty($_REQUEST['thawani_key']))
            {
                $redirect = wc_get_checkout_url();
                return wp_redirect($redirect);
            }
            else
            {
                $order_new_key = $_REQUEST['thawani_key'];
                $order = wc_get_order($order_new_key);
                
                $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                $this->msg['class'] = 'success';
                $woocommerce->cart->empty_cart();
                $order->add_order_note("Thawani payment successful.");
                $order->update_status($this->after_payment_status, $this->order_status_messege[$this->after_payment_status]);
                do_action( 'woocommerce_reduce_order_stock', $order );
                return $this->redirect_with_msg($order);
            }
            

            
            
        }

        private function redirect_with_msg($order)
        {
            global $woocommerce;
            // $redirect = home_url('checkout/order-received/');
            $woocommerce->session->set( 'wc_notices', array() );
            if (function_exists('wc_add_notice')) {
                wc_add_notice($this->msg['message'], $this->msg['class']);
            } else {
                if ($this->msg['class'] == 'success') {
                    $woocommerce->add_message($this->msg['message']);
                } else {
                    $woocommerce->add_error($this->msg['message']);
                }
                $woocommerce->set_messages();
            }

            if ($order) 
            {
                if($order->status == 'completed' )
                {
                    $redirect = home_url('checkout/order-received/' . $order->get_id() . '/?key=' . $order->get_order_key());
                }

                //if($order->status == 'processing')
                if($order->status == 'processing')
                {
                    $redirect = home_url('checkout/order-received/' . $order->get_id() . '/?key=' . $order->get_order_key());
                }
                elseif($order->status == 'cancelled')
                {
                    // $redirect = $order->get_cancel_order_url_raw(); 
                    $redirect = wc_get_checkout_url();                   
                }
                elseif($order->status == 'fail' or $order->status == 'pending')
                {
                    $redirect = wc_get_checkout_url();
                    // $redirect = home_url('checkout/order-pay/' . $order->get_id() . '/?key=' . $order->get_order_key());
                }
                else
                {
                    // $redirect = home_url('checkout/order-pay/' . $order->get_id() . '/?key=' . $order->get_order_key());
                    $redirect = wc_get_checkout_url();
                }
          }



            wp_redirect($redirect);
        }

        
    }
}
