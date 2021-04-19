<?php


class KaspiOrder
{
    protected $config;

    public $orderId;
    public $totalPrice;
    public $formattedAddress;
    public $state;
    public $firstName;
    public $lastName;
    public $phone;
    public $deliverymode;
    public $paymentMode;
    public $id;
    public $plannedDeliveryDate;
    public $contact_id;
    public $is_preorder;

    public $ordersUrl = 'https://kaspi.kz/shop/api/v2/orders';


    function __construct($args)
    {
        $this->config = parse_ini_file(__DIR__."/configs.ini", true);

        $this->orderId = $args['attributes']['code'];
        $this->totalPrice = $args['attributes']['totalPrice'];
        $this->formattedAddress = $args['attributes']['deliveryAddress']['formattedAddress'];
        $this->state = $args['attributes']['state'];
        $this->firstName = $args['attributes']['customer']['firstName'];
        $this->lastName = $args['attributes']['customer']['lastName'];
        $this->phone = $args['attributes']['customer']['cellPhone'];
        $this->deliverymode = $this->setDeliveryMode($args['attributes']['deliveryMode']);
        $this->paymentMode = $this->setPaymentMode($args['attributes']['paymentMode']);
        $this->id = $args['id'];
        $this->plannedDeliveryDate = $args['attributes']['plannedDeliveryDate'];
        if ($args['attributes']['preOrder']) {
            $this->is_preorder = 1;
        }
        else{
            $this->is_preorder = 0;
        }
    }

    function execCurl($url)
    {
        $headers = array(
            'Content-Type: application/vnd.api+json',
            "X-Auth-Token: ".$this->config['kaspiToken']['Token'],
        );
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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


    function checkOrder(BitrixHelper $bxhelper)
    {
        return $bxhelper->checkOrder($this->orderId);
    }

    function checkOrderContact(BitrixHelper $bxhelper)
    {
        return $bxhelper->checkContact($this->phone);
    }

    function addOrderContact(BitrixHelper $bxhelper)
    {
        return $bxhelper->addContact($this);
    }

    function setDeliveryMode($mode)
    {
        if ($mode == 'DELIVERY_LOCAL') {
            return 'Доставка в пределах населённого пункта';
        } elseif ($mode == 'DELIVERY_PICKUP') {
            return 'Самовывоз';
        } elseif ($mode == 'DELIVERY_REGIONAL_PICKUP') {
            return 'Региональная доставка до точки самовывоза';
        } elseif ($mode == 'DELIVERY_REGIONAL_TODOOR') {
            return 'Региональная доставка до дверей';
        }
    }

    function setPaymentMode($mode)
    {
        if ($mode == 'PAY_WITH_CREDIT') {
            return 'Покупка в кредит';
        } elseif ($mode == 'PREPAID') {
            return 'Безналичная оплата';
        }
    }

    function getOrderGoods()
    {
        $url = $this->ordersUrl . '/' . $this->id . '/entries';
        return $this->getKaspiGoodsInfo($this->execCurl($url));
    }

    function getKaspiGoodsInfo($goods)
    {
        $kaspiOrderGoods = array();
        foreach ($goods['data'] as $key => $value) {
            $entryid = $value['id'];
            $kaspiOrderGoods[$entryid] = array(
                "quantity" => $value['attributes']['quantity'],
                "basePrice" => $value['attributes']['basePrice'],
                "deliveryCost" => $value['attributes']['deliveryCost'],
            );
            $url = 'https://kaspi.kz/shop/api/v2/orderentries/' . $entryid . '/product';

            $kaspiGood = $this->execCurl($url);
            $goodId = $kaspiGood['data']['id'];

            $result = $this->getMasterproductInfo($goodId);
            $kaspiOrderGoods[$entryid]['name'] = $result[0];
            $kaspiOrderGoods[$entryid]['sku'] = $result[1];
        }
        return $kaspiOrderGoods;
    }

    function getMasterproductInfo($goodId)
    {
        $url = 'https://kaspi.kz/shop/api/v2/masterproducts/' . $goodId . '/merchantProduct';
        $kaspiGetGoodName = $this->execCurl($url);
        $goodname = $kaspiGetGoodName['data']['attributes']['name'];
        $goodUTcode = $kaspiGetGoodName['data']['attributes']['code'];

        $goodname = html_entity_decode($goodname);
        $goodname = str_replace("   ", " ", $goodname);
        $arr = array($goodname, $goodUTcode);
        return $arr;
    }
}
