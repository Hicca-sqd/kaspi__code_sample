<?php 


	class BitrixHelper {

		private $configs;
		private $rest;
		private  $KASPI_ORDER_ID;
		private  $KASPI_DELIVERY_ADDRESS;
		private  $KASPI_DELIVERY_METHOD;
		private  $KASPI_PAYMENT_METHOD;
		private  $KASPI_STATUS;
		private  $KASPI_ID;
		private  $KASPI_DELIVERY_DATE;
		private  $KASPI_MERCHANT_NAME;


		function __construct() {
			$this->configs = parse_ini_file(__DIR__."/configs.ini", true);
			$this->rest = $this->configs["Bitrix"]["bitrixRestUrl"];
			$this->KASPI_ORDER_ID = $this->configs["UF_CRM"]["KASPI_ORDER_ID"];
			$this->KASPI_DELIVERY_ADDRESS = $this->configs["UF_CRM"]["KASPI_DELIVERY_ADDRESS"];
			$this->KASPI_DELIVERY_METHOD = $this->configs["UF_CRM"]["KASPI_DELIVERY_METHOD"];
			$this->KASPI_PAYMENT_METHOD = $this->configs["UF_CRM"]["KASPI_PAYMENT_METHOD"];
			$this->KASPI_STATUS = $this->configs["UF_CRM"]["KASPI_STATUS"];
			$this->KASPI_ID = $this->configs["UF_CRM"]["KASPI_ID"];
			$this->KASPI_DELIVERY_DATE = $this->configs["UF_CRM"]["KASPI_DELIVERY_DATE"];
			$this->KASPI_MERCHANT_NAME = $this->configs["UF_CRM"]["KASPI_MERCHANT_NAME"];
		}

		function restExecuteURL($url) {
			$curl = curl_init();
			curl_setopt_array($curl, array(
			    CURLOPT_SSL_VERIFYPEER => 0,
			    CURLOPT_POST => 1,
			    CURLOPT_HEADER => 0,
			    CURLOPT_RETURNTRANSFER => 1,
			    CURLOPT_URL => $url
			));
			$result = curl_exec($curl);
			$decoded_data = json_decode($result, true);
			return $decoded_data;
		}

		function restExecuteURLquery($url, $queryData) {
			$queryData = http_build_query($queryData);
			$curl = curl_init();
			curl_setopt_array($curl, array(
			    CURLOPT_SSL_VERIFYPEER => 0,
			    CURLOPT_POST => 1,
			    CURLOPT_HEADER => 0,
			    CURLOPT_RETURNTRANSFER => 1,
			    CURLOPT_URL => $url,
			    CURLOPT_POSTFIELDS => $queryData,
			));
			$result = curl_exec($curl);
			$decoded_data = json_decode($result, true);
			return $decoded_data;
		}

		function checkOrder($orderId) {
			$filter = array('filter' => array("$this->KASPI_ORDER_ID" => $orderId));
			$url = $this->rest.'/crm.deal.list.json';
			$result = $this->restExecuteURLquery($url, $filter);
			return $result['total'];
		}

		function checkContact($phone) {
			$url = $this->rest.'/crm.duplicate.findbycomm?ENTITY_TYPE=CONTACT&TYPE=PHONE&VALUES[]=+7'.$phone.'';
			$result = $this->restExecuteURL($url);
			$checkcontact = $result['result']['CONTACT']['0'];
			return $checkcontact;
		}

		function addContact(KaspiOrder $order) {
		    $addcontact = array('fields' => array(
		        'NAME' => $order->firstName,
		        'LAST_NAME' => $order->lastName,
		        'OPENED' => 'Y',
		        'TYPE_ID' => 'CLIENT',
		        'PHONE' => ['0' => [
		            'VALUE' => '+7'.''.$order->phone,
		            'VALUE_TYPE' => 'WORK',
		        ]]  
				));
			$url = $this->rest.'/crm.contact.add.json';
			$result = $this->restExecuteURLquery($url, $addcontact);
			return $result['result'];
		}

		function addDeal(KaspiOrder $order, $category){
		    $preorder = $this->configs['UF_CRM']['KASPI_IS_PREORDER'];
			$orderparams = array(
			    "fields" => array(
			    "$this->KASPI_ORDER_ID" => $order->orderId,
			    'TITLE' => 'Заказ от kaspi.kz №' . $order->orderId . '',
			    'CATEGORY_ID' => $category,
			    'TYPE_ID' => 'GOODS',
			    'SOURCE_DESCRIPTION' => $this->KASPI_MERCHANT_NAME,
			    'CONTACT_ID' => $order->contact_id,
			    "$this->KASPI_DELIVERY_ADDRESS" => $order->formattedAddress,
			    "$this->KASPI_DELIVERY_METHOD" => $order->deliverymode,
			    "$this->KASPI_PAYMENT_METHOD" => $order->paymentMode,
			    "$this->KASPI_STATUS" => 'Принят на обработку продавцом',
			    "$this->KASPI_ID" => $order->id,
			    "$preorder" => $order->is_preorder,
			    "$this->KASPI_DELIVERY_DATE" => date('d.m.Y', $order->plannedDeliveryDate/1000),

               )
            );

			$url = $this->rest.'/crm.deal.add.json';
            $result = $this->restExecuteURLquery($url, $orderparams);
            $dealId = $result['result'];
            return $dealId; 
		}

	 	function setProducts($products, $dealId) {
			foreach ($products as $key => $value) {				
				$addProduct = array(
				    'id' => $dealId,
				    'rows' => array(
				        array(
				        'PRODUCT_NAME' => $value['name'],
				        'PRICE' => $value['basePrice'],
				        'QUANTITY' => $value['quantity'],
				        )
				       )
				    );
				$url = $this->rest.'/crm.deal.productrows.set.json';
				$this->restExecuteURLquery($url, $addProduct);
			}
		}
	}
