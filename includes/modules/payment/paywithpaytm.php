<?php
require(dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../PaytmChecksum.php');
require(dirname(__FILE__) . DIRECTORY_SEPARATOR . '/paytmlib/PaytmHelper.php');
	
  class paywithpaytm{
    var $code, $title, $description, $enabled;

// class constructor
    function __construct() {
      global $order;

      $this->code = 'paywithpaytm';
      $this->title = MODULE_PAYMENT_PAYTM_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_PAYTM_TEXT_DESCRIPTION;

	  $my_file = dirname(__FILE__) . DIRECTORY_SEPARATOR.'/paytm_version.txt';
	  if(file_exists($my_file)){
	  	  $handle = fopen($my_file, 'r');
		  if($handle){
		  	$data = fread($handle,filesize($my_file));
		  	$this->description.=" plugin updated on ".date("d F Y", strtotime($data));
		  }
	  }

      $this->sort_order = MODULE_PAYMENT_PAYTM_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_PAYTM_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID;
      }

      if (is_object($order)) $this->update_status();
      $mod = MODULE_PAYMENT_PAYTM_ENV;    
			if($mod == "Test"){
				$this->paytmurl = PaytmConstants::STAGING_HOST;
				$this->init_txn_url = PaytmConstants::TRANSACTION_INIT_URL_STAGING;
				//$this->init_txn_status = "https://securegw-stage.paytm.in/"; PRODUCTION_HOST   STAGING_HOST
			}else{
				$this->paytmurl = PaytmConstants::PRODUCTION_HOST;
				$this->init_txn_url = PaytmConstants::TRANSACTION_INIT_URL_PRODUCTION;
			}

			
    }

// class methods
    function update_status() {
      global $order, $db;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_PAYTM_ZONE > 0) ) {
        $check_flag = false;
        $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYTM_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while (!$check->EOF) {
          if ($check->fields['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
          $check->MoveNext();
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }

      // other status checks?
      if ($this->enabled) {
        // other checks here
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      return array('id' => $this->code,
                   'module' => $this->title);
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
      return false;
    }

    function process_button() {		
		global $db, $order, $insert_id;
		
		$merchant_mid = MODULE_PAYMENT_PAYTM_MERCHANT_ID;
		$merchant_key =html_entity_decode(MODULE_PAYMENT_PAYTM_MERCHANT_KEY);
		$website = MODULE_PAYMENT_PAYTM_WEBSITE;
		$industry_type_id = MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID;
		$customCallbackUrl= MODULE_PAYMENT_CUSTOM_CALLBACK_URL;
		
			$amount = $order->info['total']; 
			//$orderId = $cart->cartID;	
			$order_id = uniqid("ORDR").time();
			$_SESSION['sorderid']=$order_id;
			$post_variables = array(		
				"MID" => $merchant_mid,
				"ORDER_ID" => $order_id,
				"CUST_ID" => ! empty($customer_id)?$customer_id:$order->customer['email_address'],
				"WEBSITE" => $website,
				"INDUSTRY_TYPE_ID" => $industry_type_id,
				"EMAIL" => $order->customer['email_address'],
				"MOBILE_NO" => $order->customer['telephone'],
				"CHANNEL_ID" => "WEB",
				"TXN_AMOUNT" => $amount
			);
			$post_variables['CALLBACK_URL'] = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
			if(trim($customCallbackUrl)!=''){
				if(filter_var($customCallbackUrl, FILTER_VALIDATE_URL)){
					$post_variables['CALLBACK_URL']=$customCallbackUrl;
				}
			}
			

		$paytmParams["body"] = array(
            "requestType" => "Payment",
            "mid" => $post_variables["MID"],
            "websiteName" => $post_variables["WEBSITE"],
            "orderId" => $post_variables["ORDER_ID"],
            "callbackUrl" => $post_variables["CALLBACK_URL"],
            "txnAmount" => array(
                "value" => $post_variables["TXN_AMOUNT"],
                "currency" => "INR",
            ),
            "userInfo" => array(
                "custId" => $post_variables["CUST_ID"],
                "mobile" => $post_variables["MOBILE_NO"],
                "email" =>  $post_variables["EMAIL"],
            ),
        );

        $generateSignature = PaytmChecksum::generateSignature(json_encode($paytmParams['body'], JSON_UNESCAPED_SLASHES), $merchant_key);
        $paytmParams["head"] = array(
            "signature" => $generateSignature
        );
        $apiURL = $this->init_txn_url.$post_variables["MID"] . "&orderId=" . $post_variables["ORDER_ID"];

        $post_data_string = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
        $response_array = PaytmHelper::executecUrl($apiURL, $post_data_string);

        if(!empty($response_array['body']['txnToken'])){
        $txnToken = $response_array['body']['txnToken'];
        $paytm_msg = PaytmConstants::TNX_TOKEN_GENERATED;
        }else{
         $txnToken = '';
         $paytm_msg = PaytmConstants::RESPONSE_ERROR;

        }
        $post_variables['TXN_TOKEN'] = $txnToken;
        $post_variables['PAYTM_MSG'] = $paytm_msg;

        $spinner = '<div id="paytm-pg-spinner" class="paytm-pg-loader"><div class="bounce1"></div><div class="bounce2"></div><div class="bounce3"></div><div class="bounce4"></div><div class="bounce5"></div></div>';
        $loader = '$("html").find("#mainWrapper").append("<div></div>");';

			$process_button_string = $spinner.'<script type="application/javascript" crossorigin="anonymous" src="'.$this->paytmurl.'/merchantpgpui/checkoutjs/merchants/'.$post_variables['MID'].'.js"></script>
				
			        <style type="text/css">
            #paytm-pg-spinner {margin: 0% auto 0;width: 70px;text-align: center;z-index: 999999;position: relative;display: }

            #paytm-pg-spinner > div {width: 10px;height: 10px;background-color: #012b71;border-radius: 100%;display: inline-block;-webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;animation: sk-bouncedelay 1.4s infinite ease-in-out both;}

            #paytm-pg-spinner .bounce1 {-webkit-animation-delay: -0.64s;animation-delay: -0.64s;}

            #paytm-pg-spinner .bounce2 {-webkit-animation-delay: -0.48s;animation-delay: -0.48s;}
            #paytm-pg-spinner .bounce3 {-webkit-animation-delay: -0.32s;animation-delay: -0.32s;}

            #paytm-pg-spinner .bounce4 {-webkit-animation-delay: -0.16s;animation-delay: -0.16s;}
            #paytm-pg-spinner .bounce4, #paytm-pg-spinner .bounce5{background-color: #48baf5;} 
            @-webkit-keyframes sk-bouncedelay {0%, 80%, 100% { -webkit-transform: scale(0) }40% { -webkit-transform: scale(1.0) }}

            @keyframes sk-bouncedelay { 0%, 80%, 100% { -webkit-transform: scale(0);transform: scale(0); } 40% { 
                                            -webkit-transform: scale(1.0); transform: scale(1.0);}}
            .paytm-overlay{width: 100%;position: fixed;top: 0px;opacity: .4;height: 100%;background: #000;display: ;z-index: 99999999;}

        </style>	

		
		<script type="text/javascript">
							 
		$(document).ready(function(){

            var p = $("#checkoutConfirmDefaultHeadingCart");
            var offset = p.offset();
            window.scrollBy(offset.left, offset.top);
             
          '.$loader.'
            $("#mainWrapper div").last().addClass("paytm-overlay paytm-pg-loader");
            $(".paytm-overlay").css("display","block");
            $("#paytm-pg-spinner").css("display","block");


              function openJsCheckout(){
                 var config = {
                        "root": "",
                        "flow": "DEFAULT",
                        "data": {
                            "orderId": "'.$post_variables['ORDER_ID'].'",
                            "token": "'.$post_variables['TXN_TOKEN'].'",
                            "tokenType": "TXN_TOKEN",
                            "amount": "'.$post_variables['TXN_AMOUNT'].'"
                        },
                        "merchant": {
                            "redirect": true
                        },
                        "handler": {
                            
                            "notifyMerchant": function (eventName, data) {
                                
                                if(eventName == "SESSION_EXPIRED"){
                                location.reload(); 
                               }
                            }
                        }
                    };
                    if (window.Paytm && window.Paytm.CheckoutJS) {
                        // initialze configuration using init method 
                        window.Paytm.CheckoutJS.init(config).then(function onSuccess() {
                            // after successfully updating configuration, invoke checkoutjs
                            window.Paytm.CheckoutJS.invoke();

                        jQuery(".paytm-overlay").css("display","none");
                        jQuery("#paytm-pg-spinner").css("display","none");

                        }).catch(function onError(error) {
                            //console.log("error => ", error);
                        });
                    }

              }
              var txnToken = "'.$post_variables['TXN_TOKEN'].'";
              if(txnToken){

              setTimeout(function(){openJsCheckout()},2000);

              }else{

              	jQuery("#btn_submit").after("<div>'.$post_variables['PAYTM_MSG'].'</div>");
              	jQuery("#btn_submit").next("div").css("color","red");
              	jQuery(".paytm-overlay").css("display","none");
                jQuery("#paytm-pg-spinner").css("display","none");

              }

             
            });
							</script>
						';
		
			return $process_button_string;
    }

    function before_process() {
		
			$merchant_key =html_entity_decode(MODULE_PAYMENT_PAYTM_MERCHANT_KEY);			
			$paramList = $_POST;			
			$paytmChecksum = isset($_POST["CHECKSUMHASH"]) ? $_POST["CHECKSUMHASH"] : ""; ///
			$isValidChecksum = PaytmChecksum::verifySignature($paramList, $merchant_key, $paytmChecksum);
			//$isValidChecksum = verifychecksum_e($paramList, $merchant_key, $paytmChecksum);
			$resp_code = isset($_POST["RESPCODE"]) ? $_POST["RESPCODE"] : ""; 
			if($isValidChecksum == "TRUE" || $isValidChecksum == "true" || $isValidChecksum == "1"){
				if( $resp_code != "01"){	
					zen_redirect(zen_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode("Your payment was not processed. Please try again...!"), 'SSL', true, false));
				}
			}else{	
				zen_redirect(zen_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode("Security error...!"), 'SSL', true, false));
			}
    }

    function after_process() {
		
		// Create an array having all required parameters for status query.
		     $requestParamList = array("MID" => MODULE_PAYMENT_PAYTM_MERCHANT_ID , "ORDERID" => $_POST['ORDERID']);

		     $paytmParamsStatus = array();
                /* body parameters */
             $paytmParamsStatus["body"] = array(
                    /* Find your MID in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys */
                    "mid" => $requestParamList['MID'],
                    /* Enter your order id which needs to be check status for */
                    "orderId" => $requestParamList['ORDERID'],
                );
              $checksumStatus = PaytmChecksum::generateSignature(json_encode($paytmParamsStatus["body"], JSON_UNESCAPED_SLASHES), MODULE_PAYMENT_PAYTM_MERCHANT_KEY);
                /* head parameters */
               $paytmParamsStatus["head"] = array(
                    /* put generated checksum value here */
               "signature" => $checksumStatus
               );
                /* prepare JSON string for request */
                $post_data_status = json_encode($paytmParamsStatus, JSON_UNESCAPED_SLASHES);
                $paytstsusmurl = $this->paytmurl.PaytmConstants::ORDER_STATUS_URL; 
                $ch = curl_init($paytstsusmurl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_status);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                $responseJson = curl_exec($ch);
                $responseStatusArray = json_decode($responseJson, true);
		            if($responseStatusArray['body']['resultInfo']['resultStatus']=='TXN_SUCCESS' && $responseStatusArray['body']['txnAmount']==$_POST['TXNAMOUNT'])
		           {
		          	global $insert_id;
			           $status_comment=array();
			          if(isset($_POST)){
				        if(isset($_POST['ORDERID'])){
				      	$status_comment[]="Order Id: " . $_POST['ORDERID'];
				       }
				
				       if(isset($_POST['TXNID'])){
					     $status_comment[]="Paytm TXNID: " . $_POST['TXNID'];
				       }
				
			}
			
			$sql_data_array = array('orders_id' => $insert_id,
                              'orders_status_id' => MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => implode("\n", $status_comment));

			zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		}
		else{
			zen_redirect(zen_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode("It seems some issue in server to server communication. Kindly connect with administrator."), 'SSL', true, false));
		}
    }

    function get_error() {
      return false;
    }

    function check() {
      global $db;
      if (!isset($this->_check)) {
        $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYTM_STATUS'");
        $this->_check = $check_query->RecordCount();
      }
      return $this->_check;
    }

    function install() {
      global $db, $messageStack;
      
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Paytm Order Module', 'MODULE_PAYMENT_PAYTM_STATUS', 'True', 'Do you want to accept Paytm Order payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");

      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Paytm Environment', 'MODULE_PAYMENT_PAYTM_ENV', 'Test', 'Paytm Environment Test or Live ?', '6', '1', 'zen_cfg_select_option(array(\'Test\', \'Live\'), ', now());");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('MerchantID', 'MODULE_PAYMENT_PAYTM_MERCHANT_ID', '', 'The Merchant Id given by Paytm', '6', '2', now())");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Key', 'MODULE_PAYMENT_PAYTM_MERCHANT_KEY', '', 'Merchant key.Please note that get this key ,login to your Paytm merchant account', '6', '2', now())");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Website', 'MODULE_PAYMENT_PAYTM_WEBSITE', '', 'The Website given by Paytm', '6', '2', now())");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Industry Type', 'MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID', '', 'The merchant industry type', '6', '2', now())");

	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Custom Callback Url(If you want)', 'MODULE_PAYMENT_CUSTOM_CALLBACK_URL', '".zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL')."', 'if you want then edit this otherwise skip empty', '6', '2', now())");

	  //$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Transaction URL', 'MODULE_PAYMENT_PAYTM_TRANSACTION_URL', '', 'The merchant transaction url', '6', '2', now())");

	  //$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Transaction Status URL', 'MODULE_PAYMENT_PAYTM_TRANSACTION_STATUS_URL', '', 'The merchant transaction status url', '6', '2', now())");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('PAYTM Payment Zone', 'MODULE_PAYMENT_PAYTM_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
      
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('PAYTM Sort order of  display.', 'MODULE_PAYMENT_PAYTM_SORT_ORDER', '0', 'Sort order of PAYTM display. Lowest is displayed first.', '6', '0', now())");
      
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('PAYTM Set Order Status', 'MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
	  
	 }

    function remove() {
      global $db;
      $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_PAYTM_STATUS','MODULE_PAYMENT_PAYTM_ENV','MODULE_PAYMENT_PAYTM_MERCHANT_ID', 'MODULE_PAYMENT_PAYTM_MERCHANT_KEY', 'MODULE_PAYMENT_PAYTM_WEBSITE', 'MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID', 'MODULE_PAYMENT_CUSTOM_CALLBACK_URL', 'MODULE_PAYMENT_PAYTM_ZONE','MODULE_PAYMENT_PAYTM_SORT_ORDER','MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID');
    }
  }
