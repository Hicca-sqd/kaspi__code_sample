<?php

include 'BitrixHelper.php';
include 'DbConnection.php';
include 'KaspiStatuses.php';
include 'KaspiOrder.php';

$ka = new Kaspi();
$ka->start();
class Kaspi
{
	protected $config;
	private $kaspitoken;
	public $BitrixHelper;
	public $DbConnection;
    public $ordersUrl = 'https://kaspi.kz/shop/api/v2/orders';
	private $headers;

	function __construct()
	{

		$this->config = parse_ini_file(__DIR__."/configs.ini", true);
		$this->kaspitoken = $this->config["kaspiToken"]["Token"];

		$this->BitrixHelper = new BitrixHelper();
		$this->DbConnection = new DbConnection();

		$this->headers = array(
			'Content-Type: application/vnd.api+json',
			"X-Auth-Token: $this->kaspitoken",
		);
	}

	function start()
	{
		try {
			$this->fetchPickup();
			$this->fetchDelivery();
			$kaspiStatuses = new KaspiStatuses();
			$kaspiStatuses->UpdateStatuses();
		} catch (Exception $e) {
			var_dump($e->getMessage());
		}
	}

	function fetchPickup()
	{
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "1209600000";
		$url = $this->ordersUrl . '?page[number]=0&page[size]=20&filter[orders][state]=NEW&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '&filter[orders][deliveryType]=PICKUP';
		$orders = $this->execCurl($url);
		$this->saveOrder($orders, 8);
	}

	function fetchDelivery()
	{
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "1209600000";
		$url = $this->ordersUrl . '?page[number]=0&page[size]=20&filter[orders][state]=NEW&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '&filter[orders][deliveryType]=DELIVERY';
		$orders = $this->execCurl($url);
		$this->saveOrder($orders, 11);
	}

	function saveOrder($data, $category)
    {
        foreach ($data as $key => $value) {
            if ($key == 'included' || $key == 'meta' || count($value) == 0) {
                break;
            }

            foreach ($value as $data) {
                $kaspiOrder = new KaspiOrder($data);

                if ($kaspiOrder->checkOrder($this->BitrixHelper) != '0') {
                    continue;
                }

                $contact_id = $kaspiOrder->checkOrderContact($this->BitrixHelper);
                if (isset($contact_id)) {
                    $kaspiOrder->contact_id = $contact_id;
                } else {
                    $kaspiOrder->addOrderContact($this->BitrixHelper);
                }

                $orderGoods = $kaspiOrder->getOrderGoods();

                $dealId = $this->BitrixHelper->addDeal($kaspiOrder, $category);

                $this->BitrixHelper->setProducts($orderGoods, $dealId);

                $this->DbConnection->insertDeal($kaspiOrder, $dealId, $category);

                foreach ($orderGoods as $key => $value) {
                    $this->DbConnection->insertProducts($value, $dealId);
                }

                $this->acceptOrder($kaspiOrder);
            }
        }
    }

    function execCurl($url)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            $result = curl_exec($ch);
            $arr = json_decode($result, true);
            if ($result === false)
                throw new Exception(curl_error($ch), curl_errno($ch));
            return $arr;
        } catch (Exception $e) {
            $log = date('Y-m-d H:i:s') . $e->getMessage();
            file_put_contents(__DIR__ . '/Kaspi.txt', $log . PHP_EOL, FILE_APPEND);
            var_dump($e->getMessage());
        }
    }

    function acceptOrder(KaspiOrder $order)
    {
        $url = 'https://kaspi.kz/shop/api/v2/orders';
        $fields_string = array(
            'data' => array(
                'type'       => 'orders',
                'id'         => "$order->id",
                'attributes' => array(
                    "code" => "$order->orderId",
                    'status' => 'ACCEPTED_BY_MERCHANT',
                )

            )

        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields_string));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        //execute post

        $result = curl_exec($ch);
        $arr = json_decode($result, true);
    }
}
