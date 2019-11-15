<?php
defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );
class ithub_VivaWallet extends WC_Payment_Gateway {

	function __construct() {

		// global ID
		$this->id = "ithub_vivawallet";

		// Show Title
		$this->method_title = __( "VivaWallet", 'cwoa-authorizenet-aim' );

		// Show Description
		$this->method_description = __( "VivaWallet Payment Gateway Plug-in for WooCommerce", 'cwoa-authorizenet-aim' );

		// vertical tab title
		$this->title = __( "VivaWallet", 'ithub-vivawallet' );


		$this->icon = null;

		$this->has_fields = true;

		// support default form with credit card
		$this->supports = array( 'default_credit_card_form' );

		// setting defines
		$this->init_form_fields();

		// load time variable setting
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// further check of SSL if you want
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	} // Here is the  End __construct()

	// administration fields for specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'ithub-vivawallet' ),
				'label'		=> __( 'Enable this payment gateway', 'ithub-vivawallet' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'ithub-vivawallet' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title of checkout process.', 'ithub-vivawallet' ),
				'default'	=> __( 'Viva Wallet Credit Card', 'ithub-vivawallet' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'ithub-vivawallet' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment title of checkout process.', 'ithub-vivawallet' ),
				'default'	=> __( 'Payment through credit card.', 'ithub-vivawallet' ),
				'css'		=> 'max-width:450px;'
			),
			'merchant_id' => array(
				'title'		=> __( 'VivaWallet Merchant ID', 'ithub-vivawallet' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the Merchant ID provided by VivaWallet when you signed up for an account.', 'ithub-vivawallet' ),
			),
			'api_key' => array(
				'title'		=> __( 'VivaWallet API Key', 'ithub-vivawallet' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'This is the API Key provided by VivaWallet when you signed up for an account.', 'ithub-vivawallet' ),
			),
			'client_id' => array(
				'title'		=> __( 'VivaWallet Client ID', 'ithub-vivawallet' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the Client ID provided by VivaWallet when you signed up for an account.', 'ithub-vivawallet' ),
			),
			'client_secret' => array(
				'title'		=> __( 'VivaWallet Client Secret', 'ithub-vivawallet' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'This is the Client Secret provided by VivaWallet when you signed up for an account.', 'ithub-vivawallet' ),
			),
			'environment' => array(
				'title'		=> __( 'VivaWallet Test Mode', 'ithub-vivawallet' ),
				'label'		=> __( 'Enable Test Mode', 'ithub-vivawallet' ),
				'type'		=> 'checkbox',
				'description' => __( 'This is the test mode of gateway.', 'ithub-vivawallet' ),
				'default'	=> 'no',
			)
		);		
	}
	
	// Response handled for payment gateway
	public function process_payment( $order_id ) {
		global $woocommerce;

		$customer_order = new WC_Order( $order_id );
		
		// checking for transiction
		$environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$environment_url = ( "FALSE" == $environment ) ? 'https://api.vivapayments.com/' : 'https://demo-api.vivapayments.com/';

		$environment_url_token = ( "FALSE" == $environment ) ? 'https://accounts.vivapayments.com/' : 'https://demo-accounts.vivapayments.com/';
		
		$environment_url_payment = ( "FALSE" == $environment ) ? 'https://www.vivapayments.com/' : 'https://demo.vivapayments.com/';
	

		// This is where the fun stuff begins
		$postdata = array(
        	"grant_type"=>"client_credentials"
	    );

	    $curl = curl_init($environment_url_token."connect/token");
	    curl_setopt($curl, CURLOPT_USERPWD, $this->client_id.":".$this->client_secret);
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($curl, CURLOPT_POST, true);
	    curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
	    $jsonResponse = curl_exec($curl);
	    if(curl_errno($curl)){
	        throw new Exception( __( 'There is issue for connectin payment gateway. Sorry for the inconvenience. credentials curl', 'ithub-vivawallet' ) );
	    }
	    $response 			= json_decode($jsonResponse, true);
	    $access_token     	= $response['access_token'];
	    $access_token_type  = $response['token_type'];
	    if($access_token=="" || $access_token_type==""){
		   throw new Exception( __( 'There is issue for connectin payment gateway. Sorry for the inconvenience. token-issue', 'ithub-vivawallet' ) );
		}
		$header   = array();
		$header[] = 'Authorization:'.$access_token_type." ".$access_token;
		$header[] = 'Content-Type:application/json';

		/* Get Token to Process the Card*/
		if(isset($_POST["card_token"]) && trim($_POST["card_token"])!=""){
			$curl = curl_init($environment_url."acquiring/v1/cards/chargetokens?token=".trim(base64_decode($_POST["card_token"])));
		}else{
			if(trim($_POST["vivawallet_ccName"])==""){
				throw new Exception( __( 'Please enter card holder name.', 'ithub-vivawallet' ) );
			}elseif(trim($_POST["vivawallet_exp_year"])==""){
				throw new Exception( __( 'Please enter card number.', 'ithub-vivawallet' ) );
			}elseif(trim($_POST["vivawallet_exp_month"])==""){
				throw new Exception( __( 'Please enter card expiry month.', 'ithub-vivawallet' ) );
			}elseif(trim($_POST["vivawallet_exp_year"])==""){
				throw new Exception( __( 'Please enter card expiry year.', 'ithub-vivawallet' ) );
			}elseif(trim($_POST["vivawallet_exp_year"])==""){
				throw new Exception( __( 'Please enter card cvv.', 'ithub-vivawallet' ) );
			}
			$credit_card_postdata = array(
			    "cvc"=>trim($_POST["vivawallet_cvv"]),
				"expirationMonth"=>trim($_POST["vivawallet_exp_month"]),
				"expirationYear"=>trim($_POST["vivawallet_exp_year"]),
				"holderName"=>trim($_POST["vivawallet_ccName"]),
				"number"=>trim($_POST["vivawallet_ccNo"])
			);
			$curl = curl_init($environment_url."acquiring/v1/cards/chargetokens");
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode($credit_card_postdata));
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
		$jsonResponse = curl_exec($curl);
		if(curl_errno($curl)){
	   		throw new Exception( __( 'There is issue for connectin payment gateway. Please check you card details card curl.', 'ithub-vivawallet' ) );
		}
		$response = json_decode($jsonResponse, true);
		if($response["chargeToken"]!=""){
			$chargeToken = $response["chargeToken"];
		}else{
			throw new Exception( __('Please check card details.'. $response["message"], 'ithub-vivawallet' ) );
		}
		
		/* Save User Card If Customer Login and Check Save Card Option */
		$login_user_id = get_current_user_id();
		if($login_user_id>0 && empty($_POST["card_token"]) && $_POST["ithub_save_card"]=="1"){
			$curl = curl_init($environment_url."acquiring/v1/cards/tokens?chargeToken=".trim($chargeToken));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
			$jsonResponse = curl_exec($curl);
			if(curl_errno($curl)){
		   		throw new Exception( __( 'There is issue for connectin payment gateway. Sorry for the inconvenience.', 'ithub-vivawallet' ) );
			}
			$response = json_decode($jsonResponse, true);

			if($response["token"]!=""){
				$card_token = $response["token"];
				$vivawallet_save_card = get_user_meta($login_user_id, 'ithub_vivawallet_save_card',true);
				unset($credit_card_postdata["cvc"]);
				$credit_card_postdata["number"] = substr($credit_card_postdata["number"],12);

				$vivawallet_save_card_array[$card_token] = $credit_card_postdata;
				if($vivawallet_save_card!=""){
					$saved_card_array = json_decode($vivawallet_save_card,TRUE);
					if(is_array($saved_card_array) && count($saved_card_array)>0){
						$vivawallet_save_card_array = @array_merge($saved_card_array,$vivawallet_save_card_array);
					}

				}
				update_user_meta($login_user_id,'ithub_vivawallet_save_card',json_encode($vivawallet_save_card_array));

			}else{
				throw new Exception( __( 'VivaWallet\'s Response was not get any data.', 'ithub-vivawallet' ) );
			}
		}
		/*Create Order on Viva For Payment*/
		$orders_postdata = array(
		    "Email"=>$customer_order->billing_email,
			"Phone"=>$customer_order->billing_phone,
			"FullName"=>$customer_order->billing_first_name." ".$customer_order->billing_last_name,
			"PaymentTimeOut"=>86400,
			"RequestLang"=> "en-UK",
			"IsPreAuth"=> true,
			"Amount"=>(float)number_format(($customer_order->order_total*100), 2, '.', ''),
			"MerchantTrns"=>str_replace( "#", "", $customer_order->get_order_number() ),
			"CustomerTrns"=>"Payment of the Order ".str_replace( "#", "",$customer_order->get_order_number())
		);
		$header   = array();
		$header[] = 'Content-Type:application/json';
		$curl = curl_init($environment_url_payment."api/orders");
		curl_setopt($curl, CURLOPT_USERPWD, $this->merchant_id.":".$this->api_key);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode($orders_postdata));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
		$jsonResponse = curl_exec($curl);
		if(curl_errno($curl)){
	   		throw new Exception( __( 'There is issue for connectin payment gateway. Sorry for the inconvenience.', 'ithub-vivawallet' ) );
		}
		$response_order = json_decode($jsonResponse, true);
		if($response_order["OrderCode"]!=null && $response_order["ErrorCode"]==0){
		
		}else{
			throw new Exception( __("Error Checkout: ".$response_order["ErrorText"].base64_encode($this->merchant_id.":".$this->api_key), 'ithub-vivawallet' ) );
		}
		/*Process Order on Viva For Payment*/
		$transactions_postdata = array(
		    "OrderCode"=>$response_order["OrderCode"],
			"CreditCard"=>array("Token"=>$chargeToken)
		);
		$curl = curl_init($environment_url_payment."api/transactions");
		curl_setopt($curl, CURLOPT_USERPWD, $this->merchant_id.":".$this->api_key);
		curl_setopt($curl,CURLOPT_POST, true);
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($transactions_postdata));
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl,CURLOPT_HTTPHEADER,$header);
		$jsonResponse = curl_exec($curl);
		if(curl_errno($curl)){
	   		throw new Exception( __( 'There is issue for connectin payment gateway. Sorry for the inconvenience.', 'ithub-vivawallet' ) );
		}
		$response = json_decode($jsonResponse, true);
		if ( ( $response['StatusId'] == "F" ) && ( $response['ErrorCode'] == 0 ) && ( $response['Success'] == "true" ) ) {
			// Payment successful
			$customer_order->add_order_note( __( 'VivaWallet Complete Payment. </br> Transaction Details </br> TransactionId: '.$response['TransactionId'] ."</br>ReferenceNumber:".$response['ReferenceNumber']."</br>RetrievalReferenceNumber:".$response['RetrievalReferenceNumber'], 'ithub-vivawallet' ) );
												 
			// paid order marked
			$customer_order->payment_complete();

			// this is important part for empty cart
			$woocommerce->cart->empty_cart();

			// Redirect to thank you page
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $customer_order ),
			);
		} else {
			throw new Exception( __( 'There is issue for'.json_encode($transactions_postdata), 'ithub-vivawallet' ) );
			//transiction fail
			wc_add_notice( $response['ErrorText'], 'error' );
			$customer_order->add_order_note( 'Error: '.$response['ErrorText'] );
		}

	}
	public function payment_fields() {
 
		// ok, let's display some description before the payment form
		if ( $this->description ) {
			// you can instructions for test mode, I mean test card numbers etc.
			if ( $this->testmode ) {
				$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
				$this->description  = trim( $this->description );
			}
			// display the description with <p> tags etc.
			echo wpautop( wp_kses_post( $this->description ) );
		}
	 
		// I will echo() the form, but you can close PHP tags and print it directly in HTML
		echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
	 
		// Add this action hook if you want your custom payment gateway to support it
		do_action( 'woocommerce_credit_card_form_start', $this->id );
	 	$login_user_id = get_current_user_id();
	 	$display_customers_cards = '';
	 	$wp_get_upload_dir_ithub = wp_get_upload_dir();

	 	$card_logon_images = '<img style="position: absolute;right: -25px;top: -30px;
"scale="0" src="'.$wp_get_upload_dir_ithub["baseurl"].'/2019/09/card-icon-1.png">';
	 	if($login_user_id>0){
	 		global $woocommerce;
    		$lw_redirect_checkout = $woocommerce->cart->get_checkout_url();
	 		$ithub_vivawallet_save_card = get_user_meta($login_user_id, 'ithub_vivawallet_save_card',true);
	 		if($ithub_vivawallet_save_card!=""){
				$ithub_vivawallet_cards = json_decode($ithub_vivawallet_save_card,TRUE);
				if(is_array($ithub_vivawallet_cards) && count($ithub_vivawallet_cards)>0){
					foreach ($ithub_vivawallet_cards as $key_card => $value_card) {
						# code...
						$save_card_list .= '<div class="form-row form-row-wide" style="margin:0px; padding:0px; margin-top:5px;"><input onclick="jQuery(\'.ithub-card-class\').hide();jQuery(\'.ithub-card-add-button\').show();" type="radio" class="input-radio ithub-viva-card-selected" value="'.base64_encode($key_card).'" id="wc-'.base64_encode($key_card).'" name="card_token"><label for="wc-'.base64_encode($key_card).'">xxxx-xxxx-xxxx-'.$value_card["number"].'&nbsp;&nbsp;'.$value_card["expirationMonth"].'/'.$value_card["expirationYear"].'</label>&nbsp;&nbsp;<a href="'.$lw_redirect_checkout.'?card_action=remove&card_id_remove='.base64_encode($key_card).'" onclick="return confirm(\'Are you sure you want to delete this item?\');"><i class="default-icon ion ion-android-cancel"></i></a></div>';
					}
					$display_customers_cards = '<div class="form-row form-row-wide" style="margin:0px; padding:0px;">
				<label>Save Cards List</label>'.$save_card_list.'</div>';
				}

			}
	 	}
	 	
		// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
		echo $card_logon_images.$display_customers_cards.'<div class="form-row form-row-wide ithub-card-class" style="margin:0px; padding:0px;">
				<label>Card Holder Name <span class="required">*</span></label>
				<input style="margin:0px;padding: 10px;" id="vivawallet_ccName" type="text" name="vivawallet_ccName" required autocomplete="off">
			</div>
			<div class="form-row form-row-wide ithub-card-class" style="margin:0px; padding:0px;">
				<label>Card Number <span class="required">*</span></label>
				<input style="margin:0px;padding: 10px;width: 100%;" id="vivawallet_ccNo" name="vivawallet_ccNo" required type="number" autocomplete="off">
			</div>
			<div class="form-row form-row-wide ithub-card-class" style="margin:0px; padding:0px;">
				<label>Expiry<span class="required">*</span></label>
				<input style="max-width: 50%;float:left;width: auto;padding: 10px;margin: 0;" id="vivawallet_exp_month" name="vivawallet_exp_month" type="number" autocomplete="off" placeholder="MM" required>
				<input style="max-width:50%;float:left; margin:0px;padding: 10px;" id="vivawallet_exp_year" name="vivawallet_exp_year" type="number" autocomplete="off" required placeholder="YYYY">
			</div>
			<div class="form-row form-row-wide ithub-card-class" style="margin:0px; padding:0px;">
				<label>CVC <span class="required">*</span></label>
				<input style="margin:0px;padding: 10px;width: 100%;" id="vivawallet_cvv" name="vivawallet_cvv" required type="password" autocomplete="off" placeholder="CVC">
			</div>
			<div class="form-row form-row-wide ithub-card-class" style="margin:0px; padding:0px; margin-top:5px;">
				<label class=""><input type="checkbox" value="1" name="ithub_save_card"> Save Card</label>
			</div>
			<div class="form-row form-row-wide ithub-card-add-button" style="display:none; margin:0px; padding:0px; margin-top:5px;">
				<button type="button" onclick="jQuery(\'.ithub-card-class\').show();jQuery(\'.ithub-card-add-button\').hide();jQuery(\'.ithub-viva-card-selected\').attr(\'checked\', false);" class="button alt">Add New Card</button>
			</div>
			<div class="clear"></div>';
	 
		do_action( 'woocommerce_credit_card_form_end', $this->id );
	 
		echo '<div class="clear"></div></fieldset>';
	 
	}
	
	// Validate fields
	public function validate_fields() {
		return true;
	}

	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}

}
?>