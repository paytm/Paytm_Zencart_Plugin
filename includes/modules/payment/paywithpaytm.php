<?php
require(dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../encdec_paytm.php');
	
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

      // $mod = MODULE_PAYMENT_PAYTM_MODE;
      $transaction_url = MODULE_PAYMENT_PAYTM_TRANSACTION_URL;
    	/*	19751/17Jan2018	*/
			/*if($mod == "Test"){
				$this->form_action_url = "https://pguat.paytm.com/oltp-web/processTransaction";
			}else{
				$this->form_action_url ="https://secure.paytm.in/oltp-web/processTransaction";
			}*/

			/*if($mod == "Test"){
				$this->form_action_url = "https://securegw-stage.paytm.in/theia/processTransaction";
			}else{
				$this->form_action_url ="https://securegw.paytm.in/theia/processTransaction";
			}*/
			$this->form_action_url =$transaction_url;
    	/*	19751/17Jan2018 end	*/
			
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
			$order_id = uniqid("ORDR_");
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
			
			
		
			$checksum = getChecksumFromArray($post_variables,$merchant_key);
			$post_variables['CHECKSUMHASH']=$checksum;
      
			$process_button_string = '';
			
			foreach($post_variables as $key=>$value){
				$process_button_string .= zen_draw_hidden_field($key, $value);
			}

			
			return $process_button_string;
    }

    function before_process() {
		
			$merchant_key =html_entity_decode(MODULE_PAYMENT_PAYTM_MERCHANT_KEY);			
			$paramList = $_POST;			
			$paytmChecksum = isset($_POST["CHECKSUMHASH"]) ? $_POST["CHECKSUMHASH"] : ""; 
			$isValidChecksum = verifychecksum_e($paramList, $merchant_key, $paytmChecksum);
			$resp_code = isset($_POST["RESPCODE"]) ? $_POST["RESPCODE"] : ""; 
			if($isValidChecksum){
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
		
		$StatusCheckSum = getChecksumFromArray($requestParamList, $merchant_key);
							
		$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
		
		// $mod = MODULE_PAYMENT_PAYTM_MODE;
		$transaction_status_url=MODULE_PAYMENT_PAYTM_TRANSACTION_STATUS_URL;
		/*	19751/17Jan2018	*/
			/*if($mod == "Test"){
				$check_status_url = 'https://pguat.paytm.com/oltp/HANDLER_INTERNAL/getTxnStatus';
			}else{
				$check_status_url = 'https://secure.paytm.in/oltp/HANDLER_INTERNAL/getTxnStatus';
			}*/

			/*if($mod == "Test"){
				$check_status_url = 'https://securegw-stage.paytm.in/merchant-status/getTxnStatus';
			}else{
				$check_status_url = 'https://securegw.paytm.in/merchant-status/getTxnStatus';
			}*/
			$check_status_url = $transaction_status_url;
		/*	19751/17Jan2018 end	*/

		$responseParamList = callNewAPI($check_status_url, $requestParamList);
		if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$_POST['TXNAMOUNT'])
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
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('MerchantID', 'MODULE_PAYMENT_PAYTM_MERCHANT_ID', '', 'The Merchant Id given by Paytm', '6', '2', now())");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Key', 'MODULE_PAYMENT_PAYTM_MERCHANT_KEY', '', 'Merchant key.Please note that get this key ,login to your Paytm merchant account', '6', '2', now())");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Website', 'MODULE_PAYMENT_PAYTM_WEBSITE', '', 'The Website given by Paytm', '6', '2', now())");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Industry Type', 'MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID', '', 'The merchant industry type', '6', '2', now())");

	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Custom Callback Url(If you want)', 'MODULE_PAYMENT_CUSTOM_CALLBACK_URL', '".zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL')."', 'if you want then edit this otherwise skip empty', '6', '2', now())");

	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Transaction URL', 'MODULE_PAYMENT_PAYTM_TRANSACTION_URL', '', 'The merchant transaction url', '6', '2', now())");

	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Transaction Status URL', 'MODULE_PAYMENT_PAYTM_TRANSACTION_STATUS_URL', '', 'The merchant transaction status url', '6', '2', now())");
	  
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('PAYTM Payment Zone', 'MODULE_PAYMENT_PAYTM_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
      
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('PAYTM Sort order of  display.', 'MODULE_PAYMENT_PAYTM_SORT_ORDER', '0', 'Sort order of PAYTM display. Lowest is displayed first.', '6', '0', now())");
      
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('PAYTM Set Order Status', 'MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
	  
	 }

    function remove() {
      global $db;
      $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_PAYTM_STATUS','MODULE_PAYMENT_PAYTM_MERCHANT_ID', 'MODULE_PAYMENT_PAYTM_MERCHANT_KEY', 'MODULE_PAYMENT_PAYTM_WEBSITE', 'MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID', 'MODULE_PAYMENT_CUSTOM_CALLBACK_URL', 'MODULE_PAYMENT_PAYTM_TRANSACTION_URL','MODULE_PAYMENT_PAYTM_TRANSACTION_STATUS_URL', 'MODULE_PAYMENT_PAYTM_ZONE','MODULE_PAYMENT_PAYTM_SORT_ORDER','MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID');
    }
  }
