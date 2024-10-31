<?php 
/*
  Plugin Name: Net Pay for WooCommerce
  Description: Платёжная система Net Pay 
  Version: 2.4
  Author: Net Pay
  Author URI: http://www.net2pay.ru
  License: GPL2
 */

// To change order's status using notification URL (/?wc-api=wc_netpay) 

if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

include_once(dirname(__FILE__).'/netpay_atol.php');
 
// Add roubles in currencies
add_filter('woocommerce_currency_symbol', 'netpay_rub_currency_symbol', 10, 2);
function netpay_rub_currency_symbol($currency_symbol, $currency) {
    if($currency == "RUB") {
        $currency_symbol = 'р.';
    }
    return $currency_symbol;
}

add_filter('woocommerce_currencies', 'netpay_rub_currency', 10, 1);
function netpay_rub_currency($currencies) {
    $currencies["RUB"] = 'Russian Roubles';
    return $currencies;
}

// Add paypage filter
add_filter('template_include', 'netpay_show_paypage');
function netpay_show_paypage( $original_template ) {
	$slug = '/pay-by-netpay/';
	if (substr($_SERVER['REQUEST_URI'], 0, strlen($slug)) == $slug) {
		$assets = dirname(__FILE__).'/templates/assets/';
		if (	strlen($af = substr($_SERVER['REQUEST_URI'], strlen($slug)))
			&&	in_array($af, array_diff(scandir($assets), array('.','..')))
			) {
			wp_redirect(plugins_url('templates/assets/'.$af, __FILE__ ));
			exit;
		}
		define('NETPAY_ACCESS', 1);
		status_header(200);
		return dirname(__FILE__).'/templates/paypage.php';
	}
	else
		return $original_template;
}

/* 
	Add a custom payment class to WC
*/
add_action('plugins_loaded', 'woocommerce_netpay', 0);
function woocommerce_netpay() {	
	// if the WC payment gateway class is not available, do nothing
	if (!class_exists('WC_Payment_Gateway')) return;
		 		
	if (class_exists('WC_NETPAY')) return;
		
	class WC_NETPAY extends WC_Payment_Gateway {
		
		const TEST_AUTH = 1;
		const TEST_API_KEY = 'js4cucpn4kkc6jl1p95np054g2';
		
		public function __construct() {			
			$plugin_dir = plugin_dir_url(__FILE__);
	
			global $woocommerce;
	
			$this->id = 'netpay';
			$this->icon = apply_filters('woocommerce_netpay_icon', ''.$plugin_dir.'net_pay_logo.png');
			$this->has_fields = true;
	
			// Load the settings
			$this->init_form_fields();
			$this->init_settings();
	
			// Define user set variables
			$this->title = $this->get_option('title');
			$this->api_key = $this->get_option('api_key');
			$this->auth_sign = $this->get_option('auth_sign');
			$this->testmode = $this->get_option('testmode');
            $this->paymail = $this->get_option('paymail');
			$this->mailforpay = $this->get_option('mailforpay');
			$this->inn = $this->get_option('inn');
			$this->tax = $this->get_option('tax');
			$this->tax_shipping = $this->get_option('tax_shipping');
			$this->is_hold = $this->get_option('is_hold');
	
			// Logs
			if ($this->debug == 'yes'){
				$this->log = $woocommerce->logger();
			}
	
			// Actions
			add_action('valid-netpay-request', array($this, 'successful_request') );
			add_action('woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));
	
			// Save options
			add_action(
				'woocommerce_update_options_payment_gateways_'.$this->id, 
				array($this, 'process_admin_options') 
				);
	
			// Payment listener/API hook
			add_action('woocommerce_api_wc_netpay', array($this, 'change_status'));
	
			if (!$this->is_valid_for_use()){
				$this->enabled = false;
			}
		}		
		
		/**
		 * Check if this gateway is enabled and available in the user's country
		 */
		function is_valid_for_use(){
			if (!in_array(get_option('woocommerce_currency'), array('RUB'))){
				return false;
			}
			return true;
		}
		
		/**
		* Admin Panel Options 
		* - Options for bits like 'title' and availability on a country-by-country basis
		*
		* @since 0.1
		**/
		public function admin_options() {
			?>
			<h3><?php _e('NET PAY', 'woocommerce'); ?></h3>
			<p><?php _e('Настройка платёжной системы Net Pay.', 'woocommerce'); ?></p>
			<?php 
			if ( $this->is_valid_for_use() ) {
				?>	
				<table class="form-table">
					<?php    	
	    			// Generate the HTML For the settings form.
	    			$this->generate_settings_html();
	    			?>
	    		</table>
	    		<?php 
			}
			else {
				?>
}				<div class="inline error"><p><strong><?php _e('Шлюз отключен', 'woocommerce'); ?></strong>: <?php _e('NETPAY не поддерживает валюты Вашего магазина, напишите нам на e-mail support@net2pay.ru и мы внесём необходимые изменения в плагин.', 'woocommerce' ); ?></p></div>
				<?php
			}
	    } // End admin_options()
	
	  /**
	  * Initialise Gateway Settings Form Fields
	  *
	  * @access public
	  * @return void
	  */
		function init_form_fields(){
			$this->form_fields = array(
					'main' => array(
						'title'       => __( '<hr> Основные настройки', 'woocommerce' ),
						'type'        => 'title',
					),
					'enabled' => array(
						'title' => __('Включить/Выключить', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Включен', 'woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Название в корзине', 'woocommerce'),
						'type' => 'text', 
						'description' => __('Название платежной системы, которое покупатель видит во время оформления заказа.', 'woocommerce' ), 
						'default' => __('Net Pay (Оплата банковскими картами VISA, Visa Electron, Maestro, MasterCard, МИР) без комиссии', 'woocommerce')
					),					
					'testmode' => array(
						'title' => __('Тестовый режим', 'woocommerce'),
						'type' => 'checkbox', 
						'label' => __('Включен', 'woocommerce'),
						'description' => __('Снять галочку, для перехода на рабочий режим.', 'woocommerce'),
						'default' => 'yes'
					),  
					'workmode' => array(
						'title'       => __( '<hr> Обязательны только в рабочем режиме', 'woocommerce' ),
						'type'        => 'title',
					),
					'api_key' => array(
						'title' => __('Api key', 'woocommerce'),
						'type' => 'text',
						'description' => __('Секретный ключ выданный при регистрации <br><code>обязателен только в рабочем режиме</code>', 'woocommerce'),
						'default' => ''
					),				
					'auth_sign' => array(
						'title' => __('Auth signature', 'woocommerce'),
						'type' => 'text',
						'description' => __('Секретный ключ выданный при регистрации <br><code>обязателен только в рабочем режиме</code>', 'woocommerce'),
						'default' => ''
					),					
					'hold' => array(
						'title'       => __( '<hr> Режим холдирования', 'woocommerce' ),
						'type'        => 'title',
					),
					'is_hold' => array(
						'title' => __('Режим холдирования', 'woocommerce'),
						'type' => 'checkbox', 
						'label' => __('Включен', 'woocommerce'),
						'description' => __('Отметить галочку, для перехода на режим холдирования.', 'woocommerce'),
						'default' => 'no'
					), 
					'mailconf' => array(
						'title'       => __( '<hr> Заполняется только при использовании оплаты с подтверждением через Email', 'woocommerce' ),
						'type'        => 'title',
					),
					'paymail' => array(
						'title' => __('Оплата с подтверждением по email', 'woocommerce'),
						'type' => 'checkbox', 
						'label' => __('Включен', 'woocommerce'),
						'description' => __('Сообщение содержащее ссылку на оплату приходит на указанный ниже email. После подтверждения заказа менеджером магазина ссылку можно переслать покупателю.', 'woocommerce'),
						'default' => 'no'
					),  
					'mailforpay' => array(
						'title' => __('Email менеджера', 'woocommerce'),
						'type' => 'text',
						'description' => __('Email для отправки сообщения содержащего ссылку на оплату', 'woocommerce'),
						'default' => ''
					),
					'cashboxes' => array(
						'title'       => __( '<hr> Заполняется только при использовании онлайн-касс через партнёра Net Pay', 'woocommerce' ),
						'type'        => 'title',
					),
					'inn' => array(
						'title' => __( 'ИНН', 'woocommerce' ),
						'type' => 'text',
						'description' => __( 'ИНН юр. лица - 10 или 12 цифр', 'woocommerce' ),
						'default' => ''
					),
					'tax' => array(
						'title' => __( 'Ставка НДС', 'woocommerce' ),
						'type' => 'select',
		                'options' => array(
							'none' => __('без НДС', 'woocommerce'), 
							'vat0' => __('НДС по ставке 0%', 'woocommerce'), 
							'vat10' => __('НДС чека по ставке 10%', 'woocommerce'), 
							'vat18' => __('НДС чека по ставке 18%', 'woocommerce'), 
							'vat110' => __('НДС чека по расчетной ставке 10/110', 'woocommerce'), 
							'vat118' => __('НДС чека по расчетной ставке 18/118', 'woocommerce'), 		                    
		                ),
						'css' => 'height: 30px;',
						'description' => __( 'Ставка НДС для товаров в чеке', 'woocommerce' ),
						'default' => 'none'
					),
					'tax_shipping' => array(
						'title' => __( 'Ставка НДС для доставки', 'woocommerce' ),
						'type' => 'select',
		                'options' => array(
							'none' => __('без НДС', 'woocommerce'), 
							'vat0' => __('НДС по ставке 0%', 'woocommerce'), 
							'vat10' => __('НДС чека по ставке 10%', 'woocommerce'), 
							'vat18' => __('НДС чека по ставке 18%', 'woocommerce'), 
							'vat20' => __('НДС чека по ставке 20%', 'woocommerce'), 
							'vat110' => __('НДС чека по расчетной ставке 10/110', 'woocommerce'), 
							'vat118' => __('НДС чека по расчетной ставке 18/118', 'woocommerce'), 		                    
							'vat120' => __('НДС чека по расчетной ставке 20/120', 'woocommerce'), 		                    
		                ),
						'css' => 'height: 30px;',
						'description' => __( 'Ставка НДС для доставки в чеке', 'woocommerce' ),
						'default' => 'none'
					),
					'end' => array(
						'title'       => '<hr>',
						'type'        => 'title',
					),
				);
		}
	
		/**
		* There are no payment fields for sprypay, but we want to show the description if set.
		**/
		function payment_fields(){
			echo wpautop(wptexturize(__('Для проведения платежа вы будете перенаправлены на защищенную страницу процессинговой компании  Net Pay.', 'woocommerce')));
		}
		/**
		* Generate the dibs button link
		**/
		public function generate_form($order_id){
			global $woocommerce;
	
			$order = new WC_Order( $order_id );
	
			$url_net2pay_pay = 'https://'.($this->testmode == 'yes' ? 'demo':'my').
				'.net2pay.ru/billingService/paypage/';
			if ($this->testmode == 'yes'){
                $Api_key = self::TEST_API_KEY;
                $AuthSign = self::TEST_AUTH;
			}
			else{
                $Api_key = $this->api_key;
                $AuthSign = $this->auth_sign;
			}        
			$submitval = __('Оплатить', 'woocommerce');        
			$current_user = wp_get_current_user();
            $billing_email = get_user_meta( $current_user->ID, 'billing_email', true );
            $billing_phone = get_user_meta( $current_user->ID, 'billing_phone', true );
			$out_summ = number_format($order->order_total, 2, '.', '');
	        if (isset($_POST['paymail']) && ($_POST['paymail'] == 'true')) {
	        	$link = "$url_net2pay_pay?data=".$_POST['data']
					."&auth=".$_POST['auth']
					."&expire=".$_POST['expire']
					.($this->testmode == 'yes' ? "&demo=1":"");
                $link = str_replace("%", "%25", $link);
                $headers = 'From: '.$this->mailforpay . "\r\n" 
					.'Content-Type: text/html; charset=UTF-8; format=flowed Content-Transfer-Encoding: 8bit';
				$assets_dir = plugins_url('assets' , __FILE__ );
				$message = str_replace(
					array('{$order_id}', '{$amount}', '{$link}', '{$assests}'),
					array($order->id, $out_summ, $link, $assets_dir),
					file_get_contents(dirname(__FILE__).'/templates/netpay_mail.tpl')
				);
                if (!mail($this->mailforpay, "Order from ".$_SERVER['HTTP_HOST'], $message, $headers)) {
                	return 'Ошибка при отправке письма';
                }
				else {
					return 'Ваша заявка отправленна, мы свяжемся с Вами ближайшее время';
				}
			}
	                
            $md5_Api_key = base64_encode(md5($Api_key, true));
            $order_date = $order->order_date;
            $dateClass = new DateTime($order_date);
            $dateClass->modify('+1 day');
            $order_date = $dateClass->format('Y-m-dVH:i:s');
            $cryptoKey = substr(base64_encode(md5($md5_Api_key.$order_date, true)),0,16);
            
            $params = array();
            $params['description'] = 'Заказ № '.$order->get_order_number();
            $params['amount'] = $out_summ;
            $params['currency'] = 'RUB';
            $params['orderID'] = $order->id;
            $params['phone'] = $billing_phone;
            $params['email'] = $billing_email;
            $params['successUrl'] = $this->get_return_url( $order );
            $params['failUrl'] = $order->get_cancel_order_url();
            
            if ($this->is_hold == 'yes') {
            	$params['isHold'] = 'true';
            }
			
			if ($this->testmode == 'yes') {
				$params['orderID'] .= '_TEST_'.$_SERVER['HTTP_HOST'];
				$params['param1'] = 'callback';
				$params['value1'] = 'http'.(isset($_SERVER['HTTPS']) ? 's':'').'://'
						.$_SERVER['HTTP_HOST'].'/?wc-api=wc_netpay';
			}
            
            // add Atol data -------------------------------
            $inn = $this->inn;
            $amount = $order->order_total;
            
			if (preg_match('/^[0-9]{10,12}$/', $inn)) {
				$netpayAtol = new NetpayAtol(
					$inn, 
					$billing_email, 
					$amount,
					'', // host
					'', // phone
					($this->testmode == 'yes')
					);
				
				// список товаров
				$items = $order->get_items();
				foreach ($items as $product) {
					$netpayAtol->addItem($product['name'], round($product['line_subtotal']/$product['qty']), $product['qty'], $product['line_total'], $this->tax);
				}
								
				// Доставка 
				$shipping = array_shift($order->get_items( 'shipping' ));
				if (!empty($shipping) && isset($shipping['cost'])) {
					$netpayAtol->addShipping($shipping["name"], $shipping['cost'], $this->tax_shipping);
				}
								
				$params['cashbox_data'] = $netpayAtol->getJSON();
			}
			// end Atol data -------------------------------
            
            $params_crypted = array();
                        
            foreach ($params as $key=>$param) {
                    $cripter = $this->encrypt($key.'='.$param, $cryptoKey);
                    $params_crypted[] = $cripter;
            }
            $params_crypted_str = implode('&', $params_crypted);
	                	
			$args = array(
				// Merchant
				'data' => urlencode($params_crypted_str),
				'auth' => $AuthSign,
				'expire' => urlencode($order_date),
			);
	                
			// if paymail then button send post data to this page and send letter
            if ($this->paymail == 'yes'){
				$url_net2pay_pay = '';  
                $args['paymail'] = 'true';
                $submitval='Отправить заявку';
			}
				
			$args_array = array();
	
			foreach ($args as $key => $value){
				$args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
			}
	
			return 
				'<form action="'.esc_url($url_net2pay_pay).'" method="POST" id="netpay_payment_form">'.
				implode("\n", $args_array).
				'<input type="submit" class="button alt" value="'.__($submitval, 'woocommerce').'" /><br><br> '.
                '<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.
                	__('Отказаться от оплаты и вернуться в корзину', 'woocommerce').'</a>'."\n".
				'</form>'.
				'<style>#netpay_payment_form INPUT { padding: 10px 16px; margin: 10px; font-size: 18px; line-height: 1.33; border-radius: 6px; color: #fff; background-color: #5cb85c; font-weight: 400; text-align: center; vertical-align: middle; cursor: pointer; white-space: nowrap; border:  0px; } #netpay_payment_form INPUT:hover {background-color: #6cc86c;}</style>';
		}
		
		/**
		 * Process the payment and return the result
		 **/
		function process_payment($order_id){
			$order = new WC_Order($order_id);
	
			return array(
				'result' => 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
			);
		}
		
		/**
		* receipt_page
		**/
		function receipt_page($order){
	            if ($_POST['paymail'] != 'true')
			echo '<p>'.__('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce').'</p>';
			echo $this->generate_form($order);
		}
				
		/**
		* Check Response - Change status using notification URL (/?wc-api=wc_netpay) 
		**/
		function change_status(){
			global $woocommerce;
			
			if ($this->testmode == 'yes'){
                $Api_key = self::TEST_API_KEY;
                $AuthSign = self::TEST_AUTH;
			}
			else{
                $Api_key = $this->api_key;
                $AuthSign = $this->auth_sign;
			}
			
			parse_str($_SERVER['QUERY_STRING'], $query_arr);
			$query_arr = array_map('urldecode', $query_arr);
			$getData = $query_arr;
			
			//cleaning bad keys
			foreach ($query_arr as $k => $v) { 
				if ($k == 'orderID') break;
				unset($getData[$k]);
			}			
			$token = '';
			foreach ($getData as $k => $v) {
			    if ($k !== 'token') $token .= $v.';';
			}			
			$token = md5($token.base64_encode(md5($Api_key, true)).';');
			
			$curr_symbol = get_woocommerce_currency_symbol();
			
			if ($getData['auth'] == $AuthSign) {
			    if ($token === urldecode($getData['token'])) {
					$order_id = $getData['orderID'];
					if ($this->testmode == 'yes') {
						$order_id = str_replace('_TEST_'.$_SERVER['HTTP_HOST'], '', $getData['orderID']);
					}
					if (isset($getData['orderNumber'])) $order_id = $getData['orderNumber'];
					$order = new WC_Order($order_id);
					$status = $getData['status'];
					$trans_type = $getData['transactionType'];
			        if (in_array($getData['error'], array('000', '00', '0'))) { 
			        	if ($status === 'APPROVED') { 
							if (in_array(
								$trans_type, 
								array('Capture', 'Sale', 'Sale_Qiwi', 'Sale_YaMoney', 'Sale_WebMoney')
								)) {
								if (!$order->is_paid()) {
									$order->payment_complete();
									$order->add_order_note(__('Сумма '.$getData['amount'].$curr_symbol.' для оплаты заказа была успешно списана'));
								}
							}
							elseif (in_array($trans_type, array('Cancel'))) {
								$order->add_order_note(__('Сумма '.$getData['amount'].$curr_symbol.' из оплаты заказа возвращена покупателю', 'woocommerce'));
								$order->update_status('refunded');
							}
							elseif (in_array($trans_type, 
								array('Refund','Refund_Qiwi','Refund_WebMoney','Refund_YaMoney')
								)) {
								$refund = new WC_Order_Refund($order_id);
								$refund->set_amount($getData['amount']);
								$refund->set_parent_id($order_id);
								$refund->calculate_totals(false);
								$refund->set_total($order->get_total() - $getData['amount']);
								$refund->save();
								$order->add_order_note(__('Сумма '.$getData['amount'].$curr_symbol.' из оплаты заказа возвращена покупателю', 'woocommerce'));
							}
							$getData['status'] = '1';
						}
						elseif (($status == 'WAITING') && ($trans_type == 'Authorize')) {
							$order->add_order_note(__('Сумма '.$getData['amount'].$curr_symbol.' для оплаты заказа заморожена', 'woocommerce'));
				            $order->update_status('on-hold');
							$getData['status'] = '1';
						}						
						else {
							$getData['status'] = '0';
						}
			        }
			        else {
			            $getData['status'] = '0';
			        }
			    }
			    else {
			        $getData['status'] = '0';
			        $getData['error'] = 'Error: token does not match';
			    }
			}
			else {
			    $getData['status'] = 0;
			    $getData['error'] = 'Wrong auth value';
			}
			echo '<notification>
				<orderId>'.htmlentities($getData['orderID']).'</orderId>
				<transactionType>'.htmlentities($getData['transactionType']).'</transactionType>
				<status>'.htmlentities($getData['status']).'</status>
				<error>'.htmlentities($getData['error']).'</error>
			</notification>';
			exit;
		}
	
		/**
		* Successful Payment!
		**/
		function successful_request($posted) {
			global $woocommerce;
	
			$out_summ = $posted['OutSum'];
			$inv_id = $posted['InvId'];
	
			$order = new WC_Order($inv_id);
	
			// Check order not already completed
			if ($order->status == 'completed'){
				exit;
			}
	
			// Payment completed
			$order->add_order_note(__('Платеж успешно завершен.', 'woocommerce'));
			$order->payment_complete();
			exit;
		}
		
		function encrypt($plaintext, $key) {
			$this_w;
			$key_size = 16;
			$block_size = 16;
			$Nk = 4;
			$Nb = 4;
			$Nr = max($Nk, $Nb) + 6;
			$rcon = array(0,
				0x01000000, 0x02000000, 0x04000000, 0x08000000, 0x10000000,
				0x20000000, 0x40000000, 0x80000000, 0x1B000000, 0x36000000,
				0x6C000000, 0xD8000000, 0xAB000000, 0x4D000000, 0x9A000000,
				0x2F000000, 0x5E000000, 0xBC000000, 0x63000000, 0xC6000000,
				0x97000000, 0x35000000, 0x6A000000, 0xD4000000, 0xB3000000,
				0x7D000000, 0xFA000000, 0xEF000000, 0xC5000000, 0x91000000
			);
			$key = str_pad(substr($key, 0, $key_size), $key_size, chr(0));        
			$w = array_values(unpack('N*words', $key));

			$length = $Nb * ($Nr + 1);
			for ($i = $Nk; $i < $length; $i++) {
				$temp = $w[$i - 1];
				if ($i % $Nk == 0) {
					$temp = (($temp << 8) & 0xFFFFFF00) | (($temp >> 24) & 0x000000FF); // rotWord
					$temp = $this->sub_word($temp) ^ $rcon[$i / $Nk];
				} else if ($Nk > 6 && $i % $Nk == 4) {
					$temp = $this->sub_word($temp);
				}
				$w[$i] = $w[$i - $Nk] ^ $temp;
			}
			$temp = array();
			for ($i = $row = $col = 0; $i < $length; $i++, $col++) {
				if ($col == $Nb) {
					$col = 0;
					$row++;
				}
				$this_w[$row][$col] = $w[$i];
			}

			$pad = $block_size - (strlen($plaintext) % $block_size);
			$plaintext = str_pad($plaintext, strlen($plaintext) + $pad, chr($pad));

			$ciphertext = '';
			for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
				$ciphertext .= $this->encrypt_block(substr($plaintext, $i, $block_size), $Nb, $Nr, $this_w);
			}
			return base64_encode($ciphertext);
		}

		function encrypt_block($in, $Nb, $Nr, $w) {
			$state = array();
			$words = unpack('N*word', $in);
			$c = array(0, 1, 2, 3);
			$t0;$t1;$t2;
			$t3 = array(
				0x6363A5C6,0x7C7C84F8,0x777799EE,0x7B7B8DF6,0xF2F20DFF,0x6B6BBDD6,0x6F6FB1DE,0xC5C55491,
				0x30305060,0x01010302,0x6767A9CE,0x2B2B7D56,0xFEFE19E7,0xD7D762B5,0xABABE64D,0x76769AEC,
				0xCACA458F,0x82829D1F,0xC9C94089,0x7D7D87FA,0xFAFA15EF,0x5959EBB2,0x4747C98E,0xF0F00BFB,
				0xADADEC41,0xD4D467B3,0xA2A2FD5F,0xAFAFEA45,0x9C9CBF23,0xA4A4F753,0x727296E4,0xC0C05B9B,
				0xB7B7C275,0xFDFD1CE1,0x9393AE3D,0x26266A4C,0x36365A6C,0x3F3F417E,0xF7F702F5,0xCCCC4F83,
				0x34345C68,0xA5A5F451,0xE5E534D1,0xF1F108F9,0x717193E2,0xD8D873AB,0x31315362,0x15153F2A,
				0x04040C08,0xC7C75295,0x23236546,0xC3C35E9D,0x18182830,0x9696A137,0x05050F0A,0x9A9AB52F,
				0x0707090E,0x12123624,0x80809B1B,0xE2E23DDF,0xEBEB26CD,0x2727694E,0xB2B2CD7F,0x75759FEA,
				0x09091B12,0x83839E1D,0x2C2C7458,0x1A1A2E34,0x1B1B2D36,0x6E6EB2DC,0x5A5AEEB4,0xA0A0FB5B,
				0x5252F6A4,0x3B3B4D76,0xD6D661B7,0xB3B3CE7D,0x29297B52,0xE3E33EDD,0x2F2F715E,0x84849713,
				0x5353F5A6,0xD1D168B9,0x00000000,0xEDED2CC1,0x20206040,0xFCFC1FE3,0xB1B1C879,0x5B5BEDB6,
				0x6A6ABED4,0xCBCB468D,0xBEBED967,0x39394B72,0x4A4ADE94,0x4C4CD498,0x5858E8B0,0xCFCF4A85,
				0xD0D06BBB,0xEFEF2AC5,0xAAAAE54F,0xFBFB16ED,0x4343C586,0x4D4DD79A,0x33335566,0x85859411,
				0x4545CF8A,0xF9F910E9,0x02020604,0x7F7F81FE,0x5050F0A0,0x3C3C4478,0x9F9FBA25,0xA8A8E34B,
				0x5151F3A2,0xA3A3FE5D,0x4040C080,0x8F8F8A05,0x9292AD3F,0x9D9DBC21,0x38384870,0xF5F504F1,
				0xBCBCDF63,0xB6B6C177,0xDADA75AF,0x21216342,0x10103020,0xFFFF1AE5,0xF3F30EFD,0xD2D26DBF,
				0xCDCD4C81,0x0C0C1418,0x13133526,0xECEC2FC3,0x5F5FE1BE,0x9797A235,0x4444CC88,0x1717392E,
				0xC4C45793,0xA7A7F255,0x7E7E82FC,0x3D3D477A,0x6464ACC8,0x5D5DE7BA,0x19192B32,0x737395E6,
				0x6060A0C0,0x81819819,0x4F4FD19E,0xDCDC7FA3,0x22226644,0x2A2A7E54,0x9090AB3B,0x8888830B,
				0x4646CA8C,0xEEEE29C7,0xB8B8D36B,0x14143C28,0xDEDE79A7,0x5E5EE2BC,0x0B0B1D16,0xDBDB76AD,
				0xE0E03BDB,0x32325664,0x3A3A4E74,0x0A0A1E14,0x4949DB92,0x06060A0C,0x24246C48,0x5C5CE4B8,
				0xC2C25D9F,0xD3D36EBD,0xACACEF43,0x6262A6C4,0x9191A839,0x9595A431,0xE4E437D3,0x79798BF2,
				0xE7E732D5,0xC8C8438B,0x3737596E,0x6D6DB7DA,0x8D8D8C01,0xD5D564B1,0x4E4ED29C,0xA9A9E049,
				0x6C6CB4D8,0x5656FAAC,0xF4F407F3,0xEAEA25CF,0x6565AFCA,0x7A7A8EF4,0xAEAEE947,0x08081810,
				0xBABAD56F,0x787888F0,0x25256F4A,0x2E2E725C,0x1C1C2438,0xA6A6F157,0xB4B4C773,0xC6C65197,
				0xE8E823CB,0xDDDD7CA1,0x74749CE8,0x1F1F213E,0x4B4BDD96,0xBDBDDC61,0x8B8B860D,0x8A8A850F,
				0x707090E0,0x3E3E427C,0xB5B5C471,0x6666AACC,0x4848D890,0x03030506,0xF6F601F7,0x0E0E121C,
				0x6161A3C2,0x35355F6A,0x5757F9AE,0xB9B9D069,0x86869117,0xC1C15899,0x1D1D273A,0x9E9EB927,
				0xE1E138D9,0xF8F813EB,0x9898B32B,0x11113322,0x6969BBD2,0xD9D970A9,0x8E8E8907,0x9494A733,
				0x9B9BB62D,0x1E1E223C,0x87879215,0xE9E920C9,0xCECE4987,0x5555FFAA,0x28287850,0xDFDF7AA5,
				0x8C8C8F03,0xA1A1F859,0x89898009,0x0D0D171A,0xBFBFDA65,0xE6E631D7,0x4242C684,0x6868B8D0,
				0x4141C382,0x9999B029,0x2D2D775A,0x0F0F111E,0xB0B0CB7B,0x5454FCA8,0xBBBBD66D,0x16163A2C
			);
			for ($i = 0; $i < 256; $i++) {
				$t2[$i << 8] = (($t3[$i] << 8) & 0xFFFFFF00) | (($t3[$i] >> 24) & 0x000000FF);
				$t1[$i << 16] = (($t3[$i] << 16) & 0xFFFF0000) | (($t3[$i] >> 16) & 0x0000FFFF);
				$t0[$i << 24] = (($t3[$i] << 24) & 0xFF000000) | (($t3[$i] >> 8) & 0x00FFFFFF);
			}

			$i = 0;
			foreach ($words as $word) {
				$state[] = $word ^ $w[0][$i++];
			}
			$temp = array();
			for ($round = 1; $round < $Nr; $round++) {
				$i = 0;
				$j = $c[1];
				$k = $c[2];
				$l = $c[3];

				while ($i < $Nb) {
					$temp[$i] = $t0[$state[$i] & 0xFF000000] ^ $t1[$state[$j] & 0x00FF0000] ^
						$t2[$state[$k] & 0x0000FF00] ^ $t3[$state[$l] & 0x000000FF] ^ $w[$round][$i];
					$i++;
					$j = ($j + 1) % $Nb;
					$k = ($k + 1) % $Nb;
					$l = ($l + 1) % $Nb;
				}

				for ($i = 0; $i < $Nb; $i++) {
					$state[$i] = $temp[$i];
				}
			}

			for ($i = 0; $i < $Nb; $i++) {
				$state[$i] = $this->sub_word($state[$i]);
			}
			$i = 0; // $c[0] == 0
			$j = $c[1];
			$k = $c[2];
			$l = $c[3];
			while ($i < $Nb) {
				$temp[$i] = ($state[$i] & 0xFF000000) ^ ($state[$j] & 0x00FF0000) ^
					($state[$k] & 0x0000FF00) ^ ($state[$l] & 0x000000FF) ^ $w[$Nr][$i];
				$i++;
				$j = ($j + 1) % $Nb;
				$k = ($k + 1) % $Nb;
				$l = ($l + 1) % $Nb;
			}
			$state = $temp;

			array_unshift($state, 'N*');

			return call_user_func_array('pack', $state);
		}

		function sub_word($word) {
			static $sbox0, $sbox1, $sbox2, $sbox3;

			if (empty($sbox0)) {
				$sbox0 = array(
					0x63,0x7C,0x77,0x7B,0xF2,0x6B,0x6F,0xC5,0x30,0x01,0x67,0x2B,0xFE,0xD7,0xAB,0x76,
					0xCA,0x82,0xC9,0x7D,0xFA,0x59,0x47,0xF0,0xAD,0xD4,0xA2,0xAF,0x9C,0xA4,0x72,0xC0,
					0xB7,0xFD,0x93,0x26,0x36,0x3F,0xF7,0xCC,0x34,0xA5,0xE5,0xF1,0x71,0xD8,0x31,0x15,
					0x04,0xC7,0x23,0xC3,0x18,0x96,0x05,0x9A,0x07,0x12,0x80,0xE2,0xEB,0x27,0xB2,0x75,
					0x09,0x83,0x2C,0x1A,0x1B,0x6E,0x5A,0xA0,0x52,0x3B,0xD6,0xB3,0x29,0xE3,0x2F,0x84,
					0x53,0xD1,0x00,0xED,0x20,0xFC,0xB1,0x5B,0x6A,0xCB,0xBE,0x39,0x4A,0x4C,0x58,0xCF,
					0xD0,0xEF,0xAA,0xFB,0x43,0x4D,0x33,0x85,0x45,0xF9,0x02,0x7F,0x50,0x3C,0x9F,0xA8,
					0x51,0xA3,0x40,0x8F,0x92,0x9D,0x38,0xF5,0xBC,0xB6,0xDA,0x21,0x10,0xFF,0xF3,0xD2,
					0xCD,0x0C,0x13,0xEC,0x5F,0x97,0x44,0x17,0xC4,0xA7,0x7E,0x3D,0x64,0x5D,0x19,0x73,
					0x60,0x81,0x4F,0xDC,0x22,0x2A,0x90,0x88,0x46,0xEE,0xB8,0x14,0xDE,0x5E,0x0B,0xDB,
					0xE0,0x32,0x3A,0x0A,0x49,0x06,0x24,0x5C,0xC2,0xD3,0xAC,0x62,0x91,0x95,0xE4,0x79,
					0xE7,0xC8,0x37,0x6D,0x8D,0xD5,0x4E,0xA9,0x6C,0x56,0xF4,0xEA,0x65,0x7A,0xAE,0x08,
					0xBA,0x78,0x25,0x2E,0x1C,0xA6,0xB4,0xC6,0xE8,0xDD,0x74,0x1F,0x4B,0xBD,0x8B,0x8A,
					0x70,0x3E,0xB5,0x66,0x48,0x03,0xF6,0x0E,0x61,0x35,0x57,0xB9,0x86,0xC1,0x1D,0x9E,
					0xE1,0xF8,0x98,0x11,0x69,0xD9,0x8E,0x94,0x9B,0x1E,0x87,0xE9,0xCE,0x55,0x28,0xDF,
					0x8C,0xA1,0x89,0x0D,0xBF,0xE6,0x42,0x68,0x41,0x99,0x2D,0x0F,0xB0,0x54,0xBB,0x16
				);
				$sbox1 = array();
				$sbox2 = array();
				$sbox3 = array();
				for ($i = 0; $i < 256; $i++) {
					$sbox1[$i << 8] = $sbox0[$i] << 8;
					$sbox2[$i << 16] = $sbox0[$i] << 16;
					$sbox3[$i << 24] = $sbox0[$i] << 24;
				}
			}
			return $sbox0[$word & 0x000000FF] |
					$sbox1[$word & 0x0000FF00] |
					$sbox2[$word & 0x00FF0000] |
					$sbox3[$word & 0xFF000000];
		}
	}

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_netpay_gateway($methods){
		$methods[] = 'WC_NETPAY';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_netpay_gateway');
}