<?php
/*
Plugin Name: Woocommerce Lamdaprocessing Payment Gateway
Plugin URI: http://www.lamdaprocessing.com/
Description: Woocommerce payment module for use with https://www.lamdaprocessing.com merchant accounts
Author: David Barnes
Version: 1.2
Author URI: http://www.advancedstyle.com/
*/
add_action('plugins_loaded', 'lamdaprocessing_woocommerce_gateway_class', 0);
function lamdaprocessing_woocommerce_gateway_class(){
	if(!class_exists('WC_Payment_Gateway')) return;
	
	class WC_Lamdaprocessing extends WC_Payment_Gateway{
		public function __construct(){
			global $woocommerce;
			$this->id = 'lamdaprocessing';
			$this->medthod_title = __( 'LAMDA Processing', 'woocommerce' );
			$this->has_fields = true;
			
			$this->init_form_fields();
			
			$this->init_settings();
			
			$this->testmode = $this->settings['testmode'];
			
			if($this->testmode){
				$this->process_url = 'https://test.lamdaprocessing.com/securePayments/direct/v1/processor.php';
				$this->api_url = 'https://test.lamdaprocessing.com/api/direct/v1/processor';
			}else{
				$this->process_url = 'https://www.lamdaprocessing.com/securePayments/direct/v1/processor.php';
				$this->api_url = 'https://www.lamdaprocessing.com/api/direct/v1/processor';
			}
			
			
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->merchant_id = $this->settings['merchant_id'];
			$this->terminal_id = $this->settings['terminal_id'];
			$this->private_key = $this->settings['private_key'];
			$this->api_password = $this->settings['api_password'];
			$this->rebilling = $this->settings['rebilling'];
			
			$this->curl = ($this->settings['usecurl'] == 'yes');
			
			if($this->rebilling == 'yes'){
				if($this->testmode){
					$this->process_url = 'https://test.lamdaprocessing.com/securePayments/rebilling/v1/processor.php';
					$this->api_url = 'https://test.lamdaprocessing.com/api/rebilling/v1/processor';
				}else{
					$this->process_url = 'https://www.lamdaprocessing.com/securePayments/rebilling/v1/processor.php';
					$this->api_url = 'https://www.lamdaprocessing.com/api/rebilling/v1/processor';
				}
			}
			
			$this->msg['message'] = "";
			$this->msg['class'] = "";
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_receipt_lamdaprocessing', array(&$this, 'finalize_order'),0);
			add_action('woocommerce_receipt_lamdaprocessing', array(&$this, 'receipt_page'));
			
			if($_GET['lamda_error'] != ''){
				$woocommerce->add_error(__( urldecode($_GET['lamda_error']), 'woocommerce' ));
			}
			
		}
		
	   
	   
		function admin_options() {
			?>
			<h3><?php _e('LAMDA Processing','woocommerce'); ?></h3>
			<p><?php _e('Process credit cards via LAMDA Processing', 'woocommerce' ); ?></p>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table> <?php
		}


		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		function init_form_fields() {
			global $woocommerce;
	
			$shipping_methods = array();
	
			if ( is_admin() )
				foreach ( $woocommerce->shipping->load_shipping_methods() as $method ) {
					$shipping_methods[ $method->id ] = $method->get_title();
				}
	
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable LAMDA Processing', 'woocommerce' ),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'testmode' => array(
					'title' => __( 'Test Mode', 'woocommerce' ),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Payment method title that the customer will see on your website.', 'woocommerce' ),
					'default' => __( 'Credit & Debit Cards', 'woocommerce' ),
					'desc_tip'      => true,
				),
				'merchant_id' => array(
					'title' => __( 'Merchant ID', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Your merchant ID', 'woocommerce' )
				),
				'terminal_id' => array(
					'title' => __( 'Terminal ID', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Your terminal ID', 'woocommerce' ),
					'default' => '10000225'
				),
				'api_password' => array(
					'title' => __( 'API Password', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Your API password', 'woocommerce' )
				),
				'private_key' => array(
					'title' => __( 'Private Key', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Your Private Key', 'woocommerce' )
				),
				'usecurl' => array(
					'title' => __( 'Use CURL', 'woocommerce' ),
					'type' => 'checkbox',
					'description' => 'Depending on your account settings you may have to turn this off, with CURL turned off customers will be redirected through the LAMDA website.',
					'default' => 'yes'
				),
				'rebilling' => array(
					'title' => __( 'Rebilling', 'woocommerce' ),
					'type' => 'checkbox',
					'description' => 'Rebill the customer the same amount every month',
					'default' => 'no'
				)
		   );
		}
		
		function is_available() {
			return true;
		}
		
    function payment_fields() {
		?>
		<?php if ($this->testmode=='yes') : ?><p><strong><?php _e('TEST MODE ENABLED', 'woocommerce'); ?></strong></p><?php endif; ?>
		<?php if ($this->description) : ?><p><?php echo $this->description; ?></p><?php endif; ?>
		<fieldset>

		<p class="form-row form-row-first">
			<label for="lamda_ccnum"><?php echo __("Credit Card number", 'woocommerce') ?> <span class="required">*</span></label>
			<input type="text" class="input-text" id="lamda_ccnum" name="lamda_ccnum" />
		</p>
		<div class="clear"></div>

		<p class="form-row form-row-first">
			<label for="cc-expire-month"><?php echo __("Expiration date", 'woocommerce') ?> <span class="required">*</span></label>
			<select name="lamda_expmonth" id="lamda_expmonth" class="woocommerce-select woocommerce-cc-month">
				<option value=""><?php _e('Month', 'woocommerce') ?></option>
				<?php
					$months = array();
					for ($i = 1; $i <= 12; $i++) {
					    $timestamp = mktime(0, 0, 0, $i, 1);
					    $months[date('n', $timestamp)] = date('F', $timestamp);
					}
					foreach ($months as $num => $name) {
			            printf('<option value="%u">%s</option>', $num, $name);
			        }
				?>
			</select>
			<select name="lamda_expyear" id="lamda_expyear" class="woocommerce-select woocommerce-cc-year">
				<option value=""><?php _e('Year', 'woocommerce') ?></option>
				<?php for($y=date('Y');$y<=date('Y')+10;$y++){?>
		          <option value="<?php echo $y;?>"><?php echo $y;?></option>
		        <?php }?>
			</select>
		</p>
		<p class="form-row form-row-last">
			<label for="lamda_cvv"><?php _e("Card security code", 'woocommerce') ?> <span class="required">*</span></label>
			<input type="text" class="input-text" id="lamda_cvv" name="lamda_cvv" maxlength="4" style="width:45px" />
			<span class="help"><?php _e('3 or 4 digits.', 'woocommerce') ?></span>
		</p>
		<div class="clear"></div>
	</fieldset>
		
		<?php
    
    }
		
		
		
		function process_payment ($order_id) {
			global $woocommerce;
			

			include('includes/cc_validation.php');
			
			$cc_validation = new cc_validation();
			$result = $cc_validation->validate($_POST['lamda_ccnum'], $_POST['lamda_expmonth'], substr($_POST['lamda_expyear'],-2), $_POST['lamda_cvv']);

			$error = '';
			switch ($result) {
			case -1:
			  $error = sprintf('Unknown credit card type', substr($cc_validation->cc_number, 0, 4));
			  break;
			case -2:
			case -3:
			case -4:
			  $error = __('Invalid Expiration Date', 'woothemes');
			  break;
			case -5:
			  $error = __('Invalid Card Security Code', 'woothemes');
			  break;
			case -8:
			  $error = __('Invalid Card Type', 'woothemes');
			  break;
			case false:
			  $error = __('Invalid Card Number', 'woothemes');
			  break;
			}
			
			if ( ($result == false) || ($result < 1) ) {
				$woocommerce->add_error(__('Payment error:', 'woothemes'). ' ' . $error);
				return;
			}
	
			$order = new WC_Order( $order_id );
			
			//$TransactionId = intval( date("Yms"). rand(1,9) . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9). rand(0,9) );
			if($this->rebilling == 'yes'){
				$TransactionId = intval( "55" . rand(1,9) . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9). rand(0,9) );
			}else{
				$TransactionId = intval( "11" . rand(1,9) . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9). rand(0,9) );
			}
			

			$ApiPassword_encrypt=hash('sha256',$this->api_password);	
			
			$cardtype = 'VI';
			if($cc_validation->cc_type == 'Master Card'){
				$cardtype = 'MC';
			}elseif($cc_validation->cc_type == 'American Express'){
				$cardtype = 'AM';
			}
			
			//$return_url = $this->get_return_url($order);
			$return_url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))));
			list($return_url,$query) = explode('?',$return_url);
			$vars = explode('&',$query);
			
			$xmlReq='<?xml version="1.0" encoding="UTF-8" ?>
			<TransactionRequest xmlns="'.$this->api_url.'">
				<Language>ENG</Language>
				<Credentials>
					<MerchantId>'.$this->merchant_id.'</MerchantId>
					<TerminalId>'.$this->terminal_id.'</TerminalId>
					<TerminalPassword>'.$ApiPassword_encrypt.'</TerminalPassword>
				</Credentials>
				<TransactionType>'.($this->rebilling == 'yes' ? 'LP201' : 'LP001').'</TransactionType>
				<TransactionId>'.$TransactionId.'</TransactionId>
			<ReturnUrl page="'.$return_url .'">
					<Param>
						<Key>lamda_trans</Key>
						<Value>'.$TransactionId.'</Value>
					</Param>';
			if(!empty($vars)){
				foreach($vars as $var){
					$v = explode('=',$var);
					$xmlReq .= '<Param>
						<Key>'.$v[0].'</Key>
						<Value>'.$v[1].'</Value>
					</Param>';
				}
			}
			$xmlReq .= '
				</ReturnUrl>
				<CurrencyCode>'.get_woocommerce_currency().'</CurrencyCode>
				<TotalAmount>'.number_format($order->order_total,2,'','').'</TotalAmount>
			<ProductDescription>'.get_bloginfo('name').'</ProductDescription >
				<CustomerDetails>
					<FirstName>'.$order->billing_first_name.'</FirstName>
					<LastName>'.$order->billing_last_name.'</LastName>
					<CustomerIP>'.$_SERVER['REMOTE_ADDR'].'</CustomerIP>
					<Phone>'.$order->billing_phone.'</Phone>
					<Email>'.$order->billing_email.'</Email>
				</CustomerDetails>
			<BillingDetails>
			<CardPayMethod>2</CardPayMethod>
			<FirstName>'.$order->billing_first_name.'</FirstName>
			<LastName>'.$order->billing_last_name.'</LastName>
			<Street>'.$order->billing_address_1.'</Street>
			<City>'.$order->billing_city.'</City>
			<Region>'.$order->billing_state.'</Region>
			<Country>'.$order->shipping_country.'</Country>
			<Zip>'.$order->billing_postcode.'</Zip>
			</BillingDetails>
			<CardDetails>
			<CardHolderName>'.$order->billing_first_name.' '.$order->billing_last_name.'</CardHolderName>
			<CardNumber>'.$_POST['lamda_ccnum'].'</CardNumber>
			<CardExpireMonth>'.$this->leadingZero($_POST['lamda_expmonth']).'</CardExpireMonth>
			<CardExpireYear>'.substr($_POST['lamda_expyear'],-2).'</CardExpireYear>
			<CardType>'.$cardtype.'</CardType>
			<CardSecurityCode>'.$_POST['lamda_cvv'].'</CardSecurityCode>
			<CardIssuingBank>UNKNOWN</CardIssuingBank>
			<CardIssueNumber></CardIssueNumber>
			</CardDetails>
			</TransactionRequest>';
			
			$signature_key=trim($this->private_key.$this->api_password.$TransactionId);
			$signature=base64_encode(hash_hmac("sha256", trim($xmlReq), $signature_key, True));
			$encodedMessage=base64_encode($xmlReq);
			
			$params = array('version' => '1.0',
						    'encodedMessage' => $encodedMessage,
							'signature' => $signature);
			
				
			if($this->curl){
				if($ch = curl_init ()){
					curl_setopt ($ch, CURLOPT_URL, $this->process_url);
					curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, false );
					curl_setopt ($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
					curl_setopt ($ch, CURLOPT_REFERER, get_bloginfo('url'));
					curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1 );
					curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5 );
					curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false);
					curl_setopt ($ch, CURLOPT_POST, true);
					curl_setopt ($ch, CURLOPT_POSTFIELDS,http_build_query($params));
					
					$str = curl_exec ( $ch );
					
					$formstr = '<form action="'.$this->process_url.'" method="post">'."\n";
					foreach($params as $k => $v){
						$formstr .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
					}
					$formstr .= '<input type="submit" value="Do Payment"></form>';
					
					file_put_contents(dirname(__FILE__).'/data.log',$xmlReq."\n\n".'====================================='."\n\n", FILE_APPEND);
					file_put_contents(dirname(__FILE__).'/data.log',$formstr."\n\n".'====================================='."\n\n", FILE_APPEND);
					file_put_contents(dirname(__FILE__).'/data.log',$str, FILE_APPEND);
					curl_close ( $ch );
					$result = $this->response($str);
	
				}else{
					$woocommerce->add_error(__('CURL not installed', 'woothemes'));
					return false;
				}
				
				if(empty($result)){
					$woocommerce->add_error(__('Unknown Payment Error, please try again', 'woothemes'));
					return false;
				}elseif((string)$result['data']->Code > 1001){
					$woocommerce->add_error(__('Payment Error: '.(string)$result['data']->Description.' ('.(string)$result['data']->Code.')', 'woothemes'));
					return false;
				}
				
				update_post_meta( $order->id, 'Transaction ID', (string)$result['data']->TransactionId );
				
				// Make Order Completed
				$order->payment_complete();
		
				// Remove cart
				$woocommerce->cart->empty_cart();
		
				// Return thankyou redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))))
				);
			}else{
				file_put_contents(dirname(__FILE__).'/data.log',$xmlReq."\n\n".'====================================='."\n\n", FILE_APPEND);
				
				update_post_meta( $order->id, 'LAMDA_PARAMS', $params);
				update_post_meta( $order->id, 'LAMDA_TRANS_ID',$TransactionId);
				
				return array('result' => 'success', 'redirect' => add_query_arg('order',
					$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
				);
			}
		}
		
		function receipt_page($order){
			$form = '<form action="'.$this->process_url.'" method="POST" id="lamdaform">';
			echo '<p>'.__('Your payment will now be processed', 'woothemes').'</p>';
			$data = get_post_meta($order,'LAMDA_PARAMS',true);
			foreach($data as $k => $v){
				$form .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
			}
			$form .= '<input type="submit" value="If page does not reload in 5 seconds, click here">';
			$form .= '</form>';
			file_put_contents(dirname(__FILE__).'/data.log',$form."\n\n".'====================================='."\n\n", FILE_APPEND);
			
			echo $form;
			?>
            <script type="text/javascript">
				document.getElementById('lamdaform').submit();
			</script>
            <?php
		}
		
		function leadingZero($number, $length=2){
			return sprintf("%0".$length."d",$number); 
		}
		
		function response($str){
			$response = array();
			
			preg_match('#response.php\?inv=([\d]*)">#',$str, $inv);
			if($inv[1] != ''){
				$response['invoice'] = $inv[1];
			}
			
			preg_match('#name="encodedMessage" value="(.*)"#Usi',$str,$data);
			if($data[1] != ''){
				$xml = base64_decode($data[1]);
				$xmlobj = simplexml_load_string($xml);
				$response['data'] = $xmlobj;
			}
			
			return $response;
		}
		function finalize_order(){
			global $woocommerce;
			if($_GET['lamda_trans'] != '' && $_GET['order'] != ''){
				$error = '';
				$order = new WC_Order((int)$_GET['order']);
				
				$result = simplexml_load_string(base64_decode($_POST['encodedMessage']));
				
				if(empty($result)){
					$error = __('Unknown Payment Error, please try again', 'woothemes');
				}elseif((string)$result->Code > 1001){
					$error = __('Payment Error: '.(string)$result->Description.' ('.(string)$result->Code.')', 'woothemes');
				}
				
				if($error != ''){
					wp_redirect(add_query_arg('lamda_error', urlencode($error), get_permalink(woocommerce_get_page_id('checkout'))));
					exit();
				}
				
				
				$order->add_order_note( __( 'LAMDA Payment successful', 'woocommerce' ) );
				
				update_post_meta( $order->id, 'Transaction ID', (string)$result->TransactionId );
				update_post_meta( $order->id, 'CustomerId', (string)$result->CustomerId );
				
				
				// Make Order Completed
				$order->payment_complete();
		
				// Remove cart
				$woocommerce->cart->empty_cart();
				
				wp_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('thanks')))));
				exit();
				
			}
			return true;
		}
		
	} // End Class
	/**
	 * Add the Gateway to WooCommerce
	 **/
	function woocommerce_add_lamdaprocessing_gateway($methods) {
		$methods[] = 'WC_Lamdaprocessing';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_lamdaprocessing_gateway' );
}
?>
