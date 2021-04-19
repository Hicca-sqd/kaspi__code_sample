<?php


// require 'Kaspi.php';

class KaspiStatuses extends Kaspi
{
    private $kaspiOrderIdCustomField;
    private $kaspiOrderStatusCustomField;
    private $bitrixRestUrl;
    private $kaspiDeliveryDate;
    private $kaspiWaybill;
    private $kaspiOrderUrl = "https://kaspi.kz/shop/api/v2/orders";

    function __construct()
    {
        parent::__construct();
        $this->kaspiOrderIdCustomField = $this->config["UF_CRM"]["KASPI_ORDER_ID"];
        $this->bitrixRestUrl = $this->config["Bitrix"]["bitrixRestUrl"];
        $this->kaspiOrderStatusCustomField = $this->config["UF_CRM"]["KASPI_STATUS"];
        $this->kaspiDeliveryDate = $this->config["UF_CRM"]["KASPI_DELIVERY_DATE"];
        $this->kaspiWaybill = $this->config["UF_CRM"]["KASPI_DELIVERY_DATE"];
    }

    function UpdateStatuses() 
    {
        $this->documentPrepare();
        $this->updatePickup();
        $this->almatyOwnDelivery();
        $this->kaspiDelivery();
        $this->orderCancelled();
        $this->kaspiStatusCompleted();
        $this->kaspiDeliveryReturning();
    }

    function makeBitrixRequestUrl($method)
    {
        return $this->bitrixRestUrl . $method;
    }

    function dealsUpdate($ordersList, $status)
    {
        foreach ($ordersList as $key => $value) {
            if ($key == 'included' || $key == 'meta') {
                break;
            }
            foreach ($value as $data) {
                $query = array('filter' => array("$this->kaspiOrderIdCustomField" => $data['attributes']['code']));
                $url = $this->makeBitrixRequestUrl("crm.deal.list.json");

                $result = $this->BitrixHelper->restExecuteURLquery($url, $query);
                if (isset($result['result']) && count($result['result']) != 0) {

                    $dealIdFromBitrix = $result['result'][0]['ID'];

                    $plannedDeliveryDate = isset($data['attributes']['plannedDeliveryDate']) ? $data['attributes']['plannedDeliveryDate'] : null;
                    $waybill = isset($data['attributes']['kaspiDelivery']['waybill']) ? $data['attributes']['kaspiDelivery']['waybill'] : null;

                    $query = array(
                        'id' => $dealIdFromBitrix,
                        'fields' => array(
                            "$this->kaspiOrderStatusCustomField" => $status,
                            "$this->kaspiDeliveryDate" => date('d.m.Y', $plannedDeliveryDate / 1000),
                            "$this->kaspiWaybill" => $waybill,
                        )
                    );
                    $url = $this->makeBitrixRequestUrl("crm.deal.update.json");
                    $result = $this->BitrixHelper->restExecuteURLquery($url, $query);
                    return $result;
                }
            }
        }
    }

    function documentPrepare()
    {
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "1209600000";
        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=50&filter[orders][state]=SIGN_REQUIRED&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "На Подписании");
    }

    function updatePickup()
    {
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "604800000";
        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=100&filter[orders][state]=PICKUP&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "Самовывоз");
    }

    function almatyOwnDelivery()
    {
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "604800000";
        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=200&filter[orders][state]=DELIVERY&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "Своя доставка");
    }

    function kaspiDelivery()
    {
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "604800000";
        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=400&filter[orders][state]=KASPI_DELIVERY&filter[orders][status]=ACCEPTED_BY_MERCHANT&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "Kaspi доставка");
    }

    function orderCancelled()
    {
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "604800000";
        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=200&filter[orders][state]=ARCHIVE&filter[orders][status]=CANCELLED&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "Отменен");
    }

    function kaspiStatusCompleted()
    {
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "604800000";
        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=1000&filter[orders][state]=ARCHIVE&filter[orders][status]=COMPLETED&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "Выдан");
    }

    function kaspiDeliveryReturning()
    {
        $currenttimestamp = round(microtime(true) * 1000);
        $lasttimestamp = $currenttimestamp - "1209600000";
        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=1000&filter[orders][state]=KASPI_DELIVERY&filter[orders][status]=CANCELLING&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "Ожидает отмены");

        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=1000&filter[orders][state]=KASPI_DELIVERY&filter[orders][status]=KASPI_DELIVERY_RETURN_REQUESTED&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "Ожидает возврата");

        $url = $this->kaspiOrderUrl . '?page[number]=0&page[size]=1000&filter[orders][state]=ARCHIVE&filter[orders][status]=RETURNED&filter[orders][creationDate][$ge]=' . $lasttimestamp . '&filter[orders][creationDate][$le]=' . $currenttimestamp . '';
        $ordersList = parent::execCurl($url);
        $this->dealsUpdate($ordersList, "Возврат завершен");
    }
}
