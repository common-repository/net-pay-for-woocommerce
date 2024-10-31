<?php

/**
 * Net Pay Helper to use ATOL interface for online-cashbox
 * ATOL REST_API 04.04.2018
 *
 * @author km@net2pay.ru
 *  v3.4
 * 
 * Example of use:

	$netpayAtol = new NetpayAtol('123456789012', 'km@net2pay.ru', '100.00');
	$netpayAtol->addItem('Product 1', '30.00', 2, '50.00', 'vat 00.1800%');
	$netpayAtol->addPreparedTax('MyTax', '110');
	$netpayAtol->addItem('Product 1', '30.00', 2, '50.00', 'MyTax', '7.645');
	$netpayAtol->addShipping('Pochta Rossii', '50.00');
	$netpayAtol->addShipping('EMS', '50.00', 'NULL');
	$netpayAtol->getJSON();
	var_export($netpayAtol->getErrors());
 */

class NetpayAtol {
	
	const VERSION = '3.4';
	
	private $inn, $host, $email, $phone, $total, $is_testmode;
	private $items = array();
	private $payments = array();
	private $discounts = array();
	private $shippings = array();
	private $taxes_prepared = array(); // prepared taxes history
	private $errors = array();
	
	// Valid taxes
	private $taxes = array(
    	'NULL' => 'none',
    	'0' => 'vat0',
    	'10' => 'vat10',
	    '18' => 'vat18',
	    '20' => 'vat20',
	    '110' => 'vat110',
   		'118' => 'vat118',
   		'120' => 'vat120',
    	);
		
	// Test values for test mode
	const TEST_INN = '7717586110';
	const TEST_HOST = 'test2.atol.ru';
	
	function __construct($inn, $email, $total, $host = '', $phone = '', $is_testmode = false) {
		$this->is_testmode = $is_testmode;		
		$this->setInn($this->is_testmode ? self::TEST_INN : $inn);
		$this->setEmail($email);
		$this->setTotal($total);
		$this->setHost($this->is_testmode ? self::TEST_HOST : $host);
		$this->setPhone($phone);		
	}
	
	/**
	 * Atol payment_address param
	 *
	 * @param string $host
	 * @return boolean
	 */
	function setHost($host) {
		if ($host == '') {
			$this->host = $_SERVER['HTTP_HOST'];
			return true;
		}
		if (strlen($host) > 256) {
			$this->error('100: '."Bad Host '$host'");
			
		}
		$host = preg_replace('/^(.{256}).*$/u', '$1', $host);
		if (@parse_url('http://'.$host, PHP_URL_HOST) === false) {
			$this->error('100: '."Bad Host '$host'");			
		}
		$this->host = $host;
		return true;
	}
	
	/**
	 * INN
	 *
	 * @param integer $inn
	 * @return boolean
	 */
	function setInn($inn) {
		if (!preg_match('/^[0-9]{10,12}$/', $inn)) {
			$this->error('102: '."Bad INN '$inn'");
		}

		//prepare
		if (strlen($inn) > 12) $inn = substr($inn, 0, 12);
		
		$this->inn = $inn;
		return true;
	}
	
	/**
	 * Set Email
	 *
	 * @param string $email
	 * @return boolean
	 */
	function setEmail($email) {
		$regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*$/';
		if (strlen($email) <= 64) $email = preg_replace('/^(.{64}).*$/u', '$1', $email);
		if (	(function_exists('filter_var') // php 5.2+
				&&	filter_var($email, FILTER_VALIDATE_EMAIL)
				)
			||	preg_match($regex, $email)
			) {
			//todo
		}
		else {
			$this->error('103: '."Bad Email '$email'");
		}
		$this->email = $email;
		return true;
	}
	
	/**
	 * Set Phone
	 *
	 * @param string $phone
	 * @return boolean
	 */
	function setPhone($phone) {
		// not used now
		$this->phone = '';
		return true;
	}
	
	/**
	 * Set Total
	 *
	 * @param string $total
	 * @return boolean
	 */
	function setTotal($total) {
		$this->total = $this->preparePrice($total, '105: '."Bad Total '$total'");
		return true;
	}
	
	/**
	 * Add new Item with validation
	 *
	 * @param string $name - <=64 will be cutted
	 * @param number $price - (format 8.2)
	 * @param number $quantity - (format 8.3)
	 * @param number $sum - (format 8.2)
	 * @param string $tax - any string with russian juridical consts
	 * @param number $tax_sum - (format 8.2) (not use if discounts in the bill!!!)
	 * @return boolean
	 */
	function addItem($name, $price, $quantity, $sum, $tax, $tax_sum = '', $payment_method = '', $payment_object = '') {
		$orig_sum = $sum;
		
		$item = array();
		$item_id = count($this->items) + 1;
		
		if (empty($name)) {
			$this->error('106: '."Empty Name '$name' for Item #".$item_id);
			$name = 'Unknown position'; // safe value to make check
		}		
		// make valid name
		$item['name'] = $name = preg_replace(
			'/^(.{62}).{3,}$/u', 
			'$1'.'..', 
			strip_tags($name)
			);
		$item['payment_method'] = $payment_method ? $payment_method : 'full_prepayment';
		$item['payment_object'] = $payment_object ? $payment_object : 'commodity';
		$item_id .= " name: \"$name\"";
    	
    	$item['price'] = $price = $this->preparePrice($price, '107: '."Bad Price '$price' for Item #".$item_id);
		
		$quantity = floatval($quantity);
		if ($quantity > floatval('99999.999')) $quantity = 99999;
		if (	empty($quantity) 
			|| 	!preg_match('/^[0-9]{1,8}(\.[0-9]{1,3})?$/', $quantity)
			) {
			$this->error('108: '."Bad Quantity '$quantity' for Item #".$item_id);
			$quantity = 1; // safe value to make check
		}
		$item['quantity'] = $quantity = number_format($quantity, 3, '.', '');
		
		$item['sum'] = $sum = $this->preparePrice($sum, '109: '."Bad Sum '$sum' for Item #".$item_id);
		
		$tax = $this->prepareTax($tax);
		$item['tax'] = $tax = $this->searchTax($tax);
		if ($tax === false) {
			$this->error('110: '."Bad Tax '$tax' for Item #".$item_id);
		}		
		
		if (empty($tax_sum)) {
			$tax_sum = null;
		}
		else {
			$item['tax_sum'] = $tax_sum = $this->preparePrice($tax_sum, '111: '."Bad Tax_sum '$tax_sum' for Item #".$item_id);
		}
		
		// Check Maximum count
		if (count($this->items) >= 100) {
			$this->error('112: '."Maximum count of Items ".count($this->items));			
		}
		
		// check for discout item
		if (floatval(preg_replace('/[^0-9\.\-]/', '', $orig_sum)) < 0) {
			// make discount item
			$item['sum'] = 0;
			//$item['quantity'] = 1;
			$item['tax'] = 'none';
			$item['name'] = preg_replace('/^(.{62}).{3,}$/u', '$1'.'..', '-'.$sum.' : '.$name);
		}	
		// add new Item
		$this->items[] = $item;
		return true;
	}
	
	/**
	 * Add shipping item to Items
	 *
	 * @param string $name - <=60 or will be cutted
	 * @param number $sum - (format 8.2)
	 * @param string $tax - any string with russian juridical consts
	 * @param number $tax_sum - (format 8.2)
	 * @return boolean
	 */
	function addShipping($name, $sum, $tax = 'NULL', $tax_sum = '') {
		$this->shippings[] = array(
			'name' => $name, 
			'sum' => $sum, 
			'tax' => $tax, 			
			'tax_sum' => $tax_sum, 			
			);
	}
	
	/**
	 * Make Tax prepared from any formats to validation
	 *
	 * @param string $tax
	 */
	function prepareTax($tax) {
		// search const
		if ($i = array_search($tax, $this->taxes)) {
			return $i;
		}
		
		// search prepared before
		if (key_exists($tax, $this->taxes_prepared)) {
			return $this->taxes_prepared[$tax];
		}
		
		$new_tax = $tax;
		if (is_null($tax) || ($tax === false) || ($tax === 'NULL')) {
			$tax = 'NULL';
		}
		elseif (preg_match('/^[^0-9]*[0\.]+[^0-9]*$/', $tax)) {	// only zero, point and not digits
			$tax = '0';
		}
		else {	// prepare to ineger number
			$tax = preg_replace('/\D+/', '', $tax);	// delete non-digit characters
			$tax = preg_replace('/^0+/', '', $tax);	// delete first zero
			$tax = preg_replace('/0{2,}/', '0', $tax);	// replace more than one zero
			$tax = preg_replace('/80/', '8', $tax);	// replace zero after 8
		}
		
		// save prepared to history
		$this->taxes_prepared[$new_tax] = $tax;
		
		return $tax;
	}
		
	/**
	 * Check for valid taxes types
	 *
	 * @param string $tax
	 * @return boolean
	 */
	function searchTax($tax) {		
		if (key_exists($tax, $this->taxes)) {
			return $this->taxes[$tax];
		}
		else {
			$this->error('112: '."Tax $tax not valid ");
			return $this->taxes['NULL'];
		}
	}
	
	/**
	 * Add payment
	 *
	 * @param number $sum - (format 8.2)
	 * @param int $type - 1 for enternet payments
	 */
	function addPayment($sum, $type = 1) {
		$payment_id = count($this->payments) + 1;
		
		if (empty($sum)) {
			$this->error('113: '."Empty Sum '$sum' for Payment #".$payment_id);
		}
		
		$sum = $this->preparePrice($sum, '114: '."Bad Sum '$sum' for Payment #".$payment_id);
				
		// Check Maximum count
		if (count($this->payments) >= 10) {
			$this->error('115: '."Maximum count of Payments ".count($this->payments));
		}
				
		// add new Payment
		$this->payments[] = array(
			'sum' => $sum,
        	'type' => $type
        	);
		return true;
	}
	
	/**
	 * Validation for count of elements
	 *
	 * @return boolean
	 */
	function isValid() {
		$items_cnt = count($this->items);
		if ($items_cnt < 1) {
			$this->error('116: '."No Items added".count($this->payments));
		}
		if ($items_cnt > 100) {
			$this->error('117: '."Maximum Items count = 100, you added ".$items_cnt);
		}
				
		$payments_cnt = count($this->payments);
		if ($payments_cnt < 1) {
			$this->addPayment($this->total); // default
		}
		if ($payments_cnt > 10) {
			$this->error('119: '."Maximum Payments count = 10, you added ".$payments_cnt);
		}
		
		return true;
	}
	
	/**
	 * To add and fix your own text to use like choosed tax
	 *
	 * @param string $text
	 * @param string $tax
	 * @return boolean
	 */
	function addPreparedTax($text, $tax) {
		if (key_exists($tax, $this->taxes)) {
			$this->taxes_prepared[$text] = $tax;
			return true;
		}
		else {
			$this->error('120: '."Tax '$tax' not valid");
		}
	}
	
	/**
	 * Check order discount and prepare data to have item discount instead
	 *
	 */
	function prepareDiscounts() {
		$sum_items = 0;
		foreach ($this->items as $item) {
			$sum_items += $item['sum'];
		}
		
		$sum_shippings = 0;
		foreach ($this->shippings as $num => $shipping) {
			$sum_shippings += $this->preparePrice($shipping['sum'], "Bad shipping #$num Price");
		}
		
		$discount = $sum_shippings + $sum_items - $this->total;
		if ($discount != 0) {
			$discount_portion = ($this->total - $sum_shippings) / $sum_items;
		}
		else {
			$discount_portion = 1;
		}
		
		// if order has discount
		if ($discount_portion != 1) {	
			//search last not empty item	
			$items_noempty_num = false;
			foreach ($this->items as $i => $item) {
				if ($item['sum'] > 0) {
					$items_noempty_num = $i;
				}
			}			
			
			// change sums with discount portion and quntity dividing
			$sum_items = 0;
			foreach ($this->items as $i => $item) {
				$sum = ceil(
						$item['sum'] * $discount_portion
						/ $item['quantity'] 
						* 100
					) / 100
					* $item['quantity'];
				$this->items[$i]['sum'] = $this->preparePrice($sum);
				$sum_items += $this->items[$i]['sum'];
			}
			
			// send difference to 1 quantity position
			$diff_placed = false;
			if ($this->total - $sum_items - $sum_shippings != 0) {
				foreach ($this->items as $i => $item) {
					if (($item['quantity'] == 1) && ($item['sum'] > 0)) {
						$this->items[$i]['sum'] = $this->preparePrice(
							$item['sum'] + $this->total - $sum_items - $sum_shippings
							);
						$diff_placed = true;
						break;
					}
				}
				if (!$diff_placed) {
					// place first with sum
					foreach ($this->items as $i => $item) {
						if ($item['sum'] > 0) {
							$this->items[$i]['sum'] = $this->preparePrice(
								$item['sum'] + $this->total - $sum_items - $sum_shippings
								);
							$diff_placed = true;
							break;
						}
					}
				}
			}	
			// difference placed
			
		}
	}
	
	/**
	 * Add shippings like product item
	 *
	 * @return boolean
	 */
	function prepareShippings() {
		foreach ($this->shippings as $shipping) {
			$shipping_sum = floatval($shipping['sum']);
			$this->addItem($shipping['name'], $shipping_sum, 1, $shipping_sum, $shipping['tax'], $shipping['tax_sum'], 'full_prepayment', 'service');
		}
		
		// clear added shippings
		$this->shippings = array();
		return true;
	}
	
	/**
	 * Get prepared cahbox string in JSON format
	 *
	 * @param int $options - for json_encode in php 5.4+ (like JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
	 * @return json string
	 */
	function getJSON($options = null) {		
		//check order discount & prepare items discount data
		$this->prepareDiscounts();	
		
		// include shippings
		$this->prepareShippings();	
				
		if ($this->isValid()) {
			return $this->getCurrentJSON();
		}
		else {
			return json_encode(array(            
	            'error' => 'items or payments have bad count',
	        ));
		}
	}
	
	/**
	 * Get current cahbox string in JSON format without any preparing
	 *
	 * @return json string
	 */
	function getCurrentJSON() {
		return json_encode(array(            
			'receipt' => array(
				'attributes' => array(
					'email' => $this->email,
					'phone' => $this->phone,
				),
				'items' => $this->items,                
				'payments' => $this->payments,
				'total' => $this->total,
			),
			'service' => array(
				'callback_url' => '',
				'inn' => $this->inn,
				'payment_address' => $this->host,
			),
			'timestamp' => '',
		));
	}
	
	/**
	 * Prepare price to ATOL format
	 *
	 * @param float $price
	 * @param string $error_text
	 */
	function preparePrice($price, $error_text = '') {
		$price = preg_replace('/\,/', '.', $price);	// replace , to .
		$price = preg_replace('/[^0-9\.]+/', '', $price);	// delete not digits
		$price = floatval($price);
		$price = number_format($price, 2, '.', '');
		if (	empty($price) 
			|| 	!preg_match('/^[0-9]{1,8}(\.[0-9]{1,2})?$/', $price)
			) {
			$this->error($error_text 
				? $error_text 
				:'121: '."Bad Price '$price' used"
				);
		}
		return $price;
	}
	
	/**
	 * Add error to errors stack
	 *
	 * @param string $text
	 */
	function error($text) {
		$this->errors[] = $text;
	}
	
	/**
	 * Return all errors of class using
	 *
	 * @return array of strings
	 */
	function getErrors() {
		return $this->errors;
	}
	
	/**
	 * Turn On error's reports to Net Pay
	 *
	 */
	function reportsOn() {
		$this->report_errors = 1;
	}
	
}
