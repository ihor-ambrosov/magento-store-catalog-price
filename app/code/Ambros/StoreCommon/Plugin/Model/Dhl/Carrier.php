<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Plugin\Model\Dhl;

/**
 * DHL carrier plugin
 */
class Carrier extends \Ambros\Common\Plugin\Plugin
{
    
    const SERVICE_PREFIX_QUOTE = 'QUOT';
    const SERVICE_PREFIX_SHIPVAL = 'SHIP';
    
    /**
     * Product metadata
     * 
     * @var \Magento\Framework\App\ProductMetadata 
     */
    protected $productMetadata;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        parent::__construct($wrapperFactory);
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Add error
     * 
     * @param string $error
     * @param mixed $code
     * @return $this
     */
    protected function addError(string $error, $code = null)
    {
        $errors = $this->getSubjectPropertyValue('_errors');
        if ($code !== null) {
            $errors[$code] = $error;
        } else {
            $errors[] = $error;
        }
        $this->setSubjectPropertyValue('_errors', $errors);
        return $this;
    }
    
    /**
     * Add exception error
     * 
     * @param \Exception $exception
     * @return $this
     */
    protected function addExceptionError(\Exception $exception)
    {
        $code = $exception->getCode();
        $this->addError($exception->getMessage(), ($code > 0) ? $code : null);
        return $this;
    }
    
    /**
     * Get gateway URL
     *
     * @return string
     */
    protected function getGatewayURL(): string
    {
        $subject = $this->getSubject();
        if ($subject->getConfigData('sandbox_mode')) {
            return (string) $subject->getConfigData('sandbox_url');
        } else {
            return (string) $subject->getConfigData('gateway_url');
        }
    }
    
    /**
     * Get base currency code
     * @return string
     */
    protected function getBaseCurrencyCode(): string
    {
        return $this->getSubjectPropertyValue('_storeManager')
            ->getStore($this->getSubjectPropertyValue('_request')->getStoreId())
            ->getBaseCurrencyCode();
    }
    
    /**
     * Add rate
     *
     * @param \SimpleXMLElement $shipmentDetails
     * @return $this
     */
    protected function addRate(\SimpleXMLElement $shipmentDetails)
    {
        $subject = $this->getSubject();
        $currencyFactory = $this->getSubjectPropertyValue('_currencyFactory');
        $errors = $this->getSubjectPropertyValue('_errors');
        if (
            isset($shipmentDetails->ProductShortName) && isset($shipmentDetails->ShippingCharge) && 
            isset($shipmentDetails->GlobalProductCode) && isset($shipmentDetails->CurrencyCode) && 
            array_key_exists((string) $shipmentDetails->GlobalProductCode, $subject->getAllowedMethods())
        ) {
            $dhlProduct = (string) $shipmentDetails->GlobalProductCode;
            $totalEstimate = (float) (string) $shipmentDetails->ShippingCharge;
            $currencyCode = (string) $shipmentDetails->CurrencyCode;
            $baseCurrencyCode = $this->getBaseCurrencyCode();
            $dhlProductDescription = $subject->getDhlProductTitle($dhlProduct);
            if ($currencyCode != $baseCurrencyCode) {
                $currency = $currencyFactory->create();
                $currencyRates = $currency->getCurrencyRates($currencyCode, [$baseCurrencyCode]);
                if (!empty($currencyRates) && isset($currencyRates[$baseCurrencyCode])) {
                    $totalEstimate = $totalEstimate * $currencyRates[$baseCurrencyCode];
                } else {
                    $currencyRates = $currency->getCurrencyRates($baseCurrencyCode, [$currencyCode]);
                    if (!empty($currencyRates) && isset($currencyRates[$currencyCode])) {
                        $totalEstimate = $totalEstimate / $currencyRates[$currencyCode];
                    }
                    if (!isset($currencyRates[$currencyCode]) || !$totalEstimate) {
                        $totalEstimate = false;
                        $this->addError(__(
                            'We had to skip DHL method %1 because we couldn\'t find exchange rate %2 (Base Currency).',
                            $currencyCode,
                            $baseCurrencyCode
                        ));
                    }
                }
            }
            if ($totalEstimate) {
                $data = [
                    'term' => $dhlProductDescription,
                    'price_total' => $subject->getMethodPrice($totalEstimate, $dhlProduct),
                ];
                $rates = $this->getSubjectPropertyValue('_rates');
                if (!empty($rates)) {
                    foreach ($rates as $product) {
                        if ($product['data']['term'] == $data['term'] && $product['data']['price_total'] == $data['price_total']) {
                            return $this;
                        }
                    }
                }
                $rates[] = ['service' => $dhlProduct, 'data' => $data];
                $this->setSubjectPropertyValue('_rates', $rates);
            } else {
                $this->addError(__("Zero shipping charge for '%1'", $dhlProductDescription));
            }
        } else {
            $dhlProductDescription = false;
            if (isset($shipmentDetails->GlobalProductCode)) {
                $dhlProductDescription = $subject->getDhlProductTitle((string)$shipmentDetails->GlobalProductCode);
            }
            $this->addError(__("Zero shipping charge for '%1'", $dhlProductDescription ? $dhlProductDescription : __('DHL')));
        }
        return $this;
    }
    
    /**
     * Parse response
     *
     * @param string $response
     * @return bool|\Magento\Framework\DataObject|Result|Error
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function parseResponse($response)
    {
        $subject = $this->getSubject();
        $xmlValidator = $this->getSubjectPropertyValue('xmlValidator');
        $rateFactory = $this->getSubjectPropertyValue('_rateFactory');
        $responseError = __('The response is in wrong format.');
        try {
            $xmlValidator->validate($response);
            $xml = simplexml_load_string($response);
            if (isset($xml->GetQuoteResponse->BkgDetails->QtdShp)) {
                foreach ($xml->GetQuoteResponse->BkgDetails->QtdShp as $quotedShipment) {
                    $this->addRate($quotedShipment);
                }
            } elseif (isset($xml->AirwayBillNumber)) {
                return $this->invokeSubjectMethod('_prepareShippingLabelContent', $xml);
            } else {
                $this->addError($responseError);
            }
        } catch (\Magento\Sales\Exception\DocumentValidationException $exception) {
            $this->addExceptionError($exception);
        }
        $result = $rateFactory->create();
        $rates = $this->getSubjectPropertyValue('_rates');
        if ($rates) {
            foreach ($rates as $rate) {
                $method = $rate['service'];
                $data = $rate['data'];
                $rate = $rateFactory->create();
                $rate->setCarrier(\Magento\Dhl\Model\Carrier::CODE);
                $rate->setCarrierTitle($subject->getConfigData('title'));
                $rate->setMethod($method);
                $rate->setMethodTitle($data['term']);
                $rate->setCost($data['price_total']);
                $rate->setPrice($data['price_total']);
                $result->append($rate);
            }
        } else {
            $errors = $this->getSubjectPropertyValue('_errors');
            if (!empty($errors)) {
                if ($this->getSubjectPropertyValue('_isShippingLabelFlag')) {
                    throw new \Magento\Framework\Exception\LocalizedException($responseError);
                }
                $this->invokeSubjectMethod('debugErrors', $errors);
            }
            $result->append($this->invokeSubjectMethod('getErrorMessage'));
        }
        return $result;
    }
    
    /**
     * Process quotes responses
     *
     * @param array $responsesData
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Error|\Magento\Shipping\Model\Rate\Result|null
     */
    protected function processQuotesResponses(array $responsesData)
    {
        if (version_compare($this->productMetadata->getVersion(), '2.3.3', '>=')) {
            $xmlElFactory = $this->getSubjectPropertyValue('_xmlElFactory');
            usort($responsesData, function (array $a, array $b): int {
                return $a['date'] <=> $b['date'];
            });
            $lastResponse = '';
            foreach ($responsesData as $responseData) {
                $debugPoint = [];
                $debugPoint['request'] = $this->invokeSubjectMethod('filterDebugData', $responseData['request']);
                $debugPoint['response'] = $this->invokeSubjectMethod('filterDebugData', $responseData['body']);
                $debugPoint['from_cache'] = $responseData['from_cache'];
                $unavailable = false;
                try {
                    $bodyXml = $xmlElFactory->create(['data' => $responseData['body']]);
                    $code = $bodyXml->xpath('//GetQuoteResponse/Note/Condition/ConditionCode');
                    if (isset($code[0]) && (int) $code[0] == \Magento\Dhl\Model\Carrier::CONDITION_CODE_SERVICE_DATE_UNAVAILABLE) {
                        $debugPoint['info'] = sprintf(__('DHL service is not available at %s date'), $responseData['date']);
                        $unavailable = true;
                    }
                } catch (\Throwable $exception) {
                    $unavailable = true;
                    $this->addExceptionError($exception);
                }
                if ($unavailable) {
                    $this->invokeSubjectMethod('_debug', $debugPoint);
                    break;
                }
                $this->invokeSubjectMethod('_setCachedQuotes', $responseData['request'], $responseData['body']);
                $this->invokeSubjectMethod('_debug', $debugPoint);
                $lastResponse = $responseData['body'];
            }
            return $this->parseResponse($lastResponse);
        } else {
            return null;
        }
    }
    
    /**
     * Shipment details
     * 
     * @param \Magento\Shipping\Model\Simplexml\Element $xml
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $rawRequest
     * @param string $originRegion
     * @return $this
     */
    protected function shipmentDetails($xml, $rawRequest, $originRegion = '')
    {
        $coreDate = $this->getSubjectPropertyValue('_coreDate');
        $nodeShipmentDetails = $xml->addChild('ShipmentDetails', '', '');
        $nodeShipmentDetails->addChild('NumberOfPieces', count($rawRequest->getPackages()));
        $nodePieces = $nodeShipmentDetails->addChild('Pieces', '', '');
        $i = 0;
        foreach ($rawRequest->getPackages() as $package) {
            $nodePiece = $nodePieces->addChild('Piece', '', '');
            $packageType = 'EE';
            if ($package['params']['container'] == \Magento\Dhl\Model\Carrier::DHL_CONTENT_TYPE_NON_DOC) {
                $packageType = 'CP';
            }
            $nodePiece->addChild('PieceID', ++$i);
            $nodePiece->addChild('PackageType', $packageType);
            $nodePiece->addChild('Weight', sprintf('%.3f', $package['params']['weight']));
            $params = $package['params'];
            if ($params['width'] && $params['length'] && $params['height']) {
                $nodePiece->addChild('Width', round($params['width']));
                $nodePiece->addChild('Height', round($params['height']));
                $nodePiece->addChild('Depth', round($params['length']));
            }
            $content = [];
            foreach ($package['items'] as $item) {
                $content[] = $item['name'];
            }
            $nodePiece->addChild('PieceContents', substr(implode(',', $content), 0, 34));
        }
        $nodeShipmentDetails->addChild('Weight', sprintf('%.3f', $rawRequest->getPackageWeight()));
        $nodeShipmentDetails->addChild('WeightUnit', substr($this->invokeSubjectMethod('_getWeightUnit'), 0, 1));
        $nodeShipmentDetails->addChild('GlobalProductCode', $rawRequest->getShippingMethod());
        $nodeShipmentDetails->addChild('LocalProductCode', $rawRequest->getShippingMethod());
        $nodeShipmentDetails->addChild('Date', $coreDate->date('Y-m-d', strtotime('now + 1day')));
        $nodeShipmentDetails->addChild('Contents', 'DHL Parcel');
        $nodeShipmentDetails->addChild('DoorTo', 'DD');
        $nodeShipmentDetails->addChild('DimensionUnit', substr($this->invokeSubjectMethod('_getDimensionUnit'), 0, 1));
        $contentType = isset($package['params']['container']) ? $package['params']['container'] : '';
        $packageType = $contentType === \Magento\Dhl\Model\Carrier::DHL_CONTENT_TYPE_NON_DOC ? 'CP' : '';
        $nodeShipmentDetails->addChild('PackageType', $packageType);
        if ($this->invokeSubjectMethod('isDutiable', $rawRequest->getOrigCountryId(), $rawRequest->getDestCountryId())) {
            $nodeShipmentDetails->addChild('IsDutiable', 'Y');
        }
        $nodeShipmentDetails->addChild('CurrencyCode', $this->getBaseCurrencyCode());
        return $this;
    }
    
    /**
     * Get configuration shipping origin
     * @return string
     */
    protected function getConfigShippingOrigin(): string
    {
        return $this->getSubjectPropertyValue('_scopeConfig')->getValue(
            \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_COUNTRY_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getSubject()->getStore()
        );
    }
    
    /**
     * Do request
     *
     * @return \Magento\Shipping\Model\Rate\Result|\Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function doRequest()
    {
        $subject = $this->getSubject();
        $request = $this->getSubjectPropertyValue('_request');
        $xmlElFactory = $this->getSubjectPropertyValue('_xmlElFactory');
        $httpClient = $this->getSubjectPropertyValue('httpClient');
        $string = $this->getSubjectPropertyValue('string');
        $shipperAddressCountryCode = $request->getShipperAddressCountryCode();
        $recipientAddressCountryCode = $request->getRecipientAddressCountryCode();
        $xml = $xmlElFactory->create(['data' => '<?xml version="1.0" encoding="UTF-8"?>'.
            '<req:ShipmentRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'.
            ' xsi:schemaLocation="http://www.dhl.com ship-val-global-req-6.0.xsd" schemaVersion="6.0" />']);
        $nodeRequest = $xml->addChild('Request', '', '');
        $nodeServiceHeader = $nodeRequest->addChild('ServiceHeader');
        $nodeServiceHeader->addChild('MessageTime', $this->invokeSubjectMethod('buildMessageTimestamp'));
        $messageReference = $this->invokeSubjectMethod('buildMessageReference', self::SERVICE_PREFIX_SHIPVAL);
        $nodeServiceHeader->addChild('MessageReference', $messageReference);
        $nodeServiceHeader->addChild('SiteID', (string) $subject->getConfigData('id'));
        $nodeServiceHeader->addChild('Password', (string) $subject->getConfigData('password'));
        $originRegion = $this->invokeSubjectMethod('getCountryParams', $this->getConfigShippingOrigin())->getRegion();
        if ($originRegion) {
            $xml->addChild('RegionCode', $originRegion, '');
        }
        $xml->addChild('RequestedPickupTime', 'N', '');
        $xml->addChild('NewShipper', 'N', '');
        $xml->addChild('LanguageCode', 'EN', '');
        $xml->addChild('PiecesEnabled', 'Y', '');
        $nodeBilling = $xml->addChild('Billing', '', '');
        $nodeBilling->addChild('ShipperAccountNumber', (string) $subject->getConfigData('account'));
        $nodeBilling->addChild('ShippingPaymentType', 'S');
        $nodeBilling->addChild('BillingAccountNumber', (string) $subject->getConfigData('account'));
        $nodeBilling->addChild('DutyPaymentType', 'S');
        $nodeBilling->addChild('DutyAccountNumber', (string) $subject->getConfigData('account'));
        $nodeConsignee = $xml->addChild('Consignee', '', '');
        $companyName = $request->getRecipientContactCompanyName() ? $request->getRecipientContactCompanyName() : $request->getRecipientContactPersonName();
        $nodeConsignee->addChild('CompanyName', substr($companyName, 0, 35));
        $address = $string->split($request->getRecipientAddressStreet1().' '.$request->getRecipientAddressStreet2(), 35, false, true);
        if (is_array($address)) {
            foreach ($address as $addressLine) {
                $nodeConsignee->addChild('AddressLine', $addressLine);
            }
        } else {
            $nodeConsignee->addChild('AddressLine', $address);
        }
        $nodeConsignee->addChild('City', $request->getRecipientAddressCity());
        $recipientAddressStateOrProvinceCode = $request->getRecipientAddressStateOrProvinceCode();
        if ($recipientAddressStateOrProvinceCode) {
            $nodeConsignee->addChild('Division', $recipientAddressStateOrProvinceCode);
        }
        $nodeConsignee->addChild('PostalCode', $request->getRecipientAddressPostalCode());
        $nodeConsignee->addChild('CountryCode', $recipientAddressCountryCode);
        $nodeConsignee->addChild(
            'CountryName',
            $this->invokeSubjectMethod('getCountryParams', $recipientAddressCountryCode)->getName()
        );
        $nodeContact = $nodeConsignee->addChild('Contact');
        $nodeContact->addChild('PersonName', substr($request->getRecipientContactPersonName(), 0, 34));
        $nodeContact->addChild('PhoneNumber', substr($request->getRecipientContactPhoneNumber(), 0, 24));
        $nodeCommodity = $xml->addChild('Commodity', '', '');
        $nodeCommodity->addChild('CommodityCode', '1');
        if ($this->invokeSubjectMethod('isDutiable', $shipperAddressCountryCode, $recipientAddressCountryCode)) {
            $nodeDutiable = $xml->addChild('Dutiable', '', '');
            $nodeDutiable->addChild('DeclaredValue', sprintf("%.2F", $request->getOrderShipment()->getOrder()->getSubtotal()));
            $nodeDutiable->addChild('DeclaredCurrency', $this->getBaseCurrencyCode());
        }
        $nodeReference = $xml->addChild('Reference', '', '');
        $nodeReference->addChild('ReferenceID', 'shipment reference');
        $nodeReference->addChild('ReferenceType', 'St');
        $this->shipmentDetails($xml, $request);
        $nodeShipper = $xml->addChild('Shipper', '', '');
        $nodeShipper->addChild('ShipperID', (string) $subject->getConfigData('account'));
        $nodeShipper->addChild('CompanyName', $request->getShipperContactCompanyName());
        $nodeShipper->addChild('RegisteredAccount', (string) $subject->getConfigData('account'));
        $address = $string->split($request->getShipperAddressStreet1().' '.$request->getShipperAddressStreet2(), 35, false, true);
        if (is_array($address)) {
            foreach ($address as $addressLine) {
                $nodeShipper->addChild('AddressLine', $addressLine);
            }
        } else {
            $nodeShipper->addChild('AddressLine', $address);
        }
        $nodeShipper->addChild('City', $request->getShipperAddressCity());
        $shipperAddressStateOrProvinceCode = $request->getShipperAddressStateOrProvinceCode();
        if ($shipperAddressStateOrProvinceCode) {
            $nodeShipper->addChild('Division', $shipperAddressStateOrProvinceCode);
        }
        $nodeShipper->addChild('PostalCode', $request->getShipperAddressPostalCode());
        $nodeShipper->addChild('CountryCode', $shipperAddressCountryCode);
        $nodeShipper->addChild(
            'CountryName',
            $this->invokeSubjectMethod('getCountryParams', $shipperAddressCountryCode)->getName()
        );
        $nodeContact = $nodeShipper->addChild('Contact', '', '');
        $nodeContact->addChild('PersonName', substr($request->getShipperContactPersonName(), 0, 34));
        $nodeContact->addChild('PhoneNumber', substr($request->getShipperContactPhoneNumber(), 0, 24));
        $xml->addChild('LabelImageFormat', 'PDF', '');
        $requestXml = $xml->asXML();
        if ($requestXml && !(mb_detect_encoding($requestXml) == 'UTF-8')) {
            $requestXml = utf8_encode($requestXml);
        }
        $responseBody = $this->invokeSubjectMethod('_getCachedQuotes', $requestXml);
        if ($responseBody === null) {
            $debugData = ['request' => $this->invokeSubjectMethod('filterDebugData', $requestXml)];
            try {
                if (version_compare($this->productMetadata->getVersion(), '2.3.3', '>=')) {
                    $response = $httpClient->request(
                        new \Magento\Framework\HTTP\AsyncClient\Request(
                            $this->getGatewayURL(),
                            \Magento\Framework\HTTP\AsyncClient\Request::METHOD_POST,
                            ['Content-Type' => 'application/xml'],
                            $requestXml
                        )
                    );
                    $responseBody = utf8_decode($response->get()->getBody());
                } else {
                    $client = $this->getSubjectPropertyValue('_httpClientFactory')->create();
                    $client->setUri($this->getGatewayURL());
                    $client->setConfig(['maxredirects' => 0, 'timeout' => 30]);
                    $client->setRawData($request);
                    $responseBody = utf8_decode($client->request(\Magento\Framework\HTTP\ZendClient::POST)->getBody());
                }
                $debugData['result'] = $this->invokeSubjectMethod('filterDebugData', $responseBody);
                $this->invokeSubjectMethod('_setCachedQuotes', $requestXml, $responseBody);
            } catch (\Exception $exception) {
                $this->addExceptionError($exception);
                $responseBody = '';
            }
            $this->invokeSubjectMethod('_debug', $debugData);
        }
        $this->setSubjectPropertyValue('_isShippingLabelFlag', true);
        return $this->parseResponse($responseBody);
    }
    
    /**
     * Do shipment request
     *
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     */
    protected function doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        $subject = $this->getSubject();
        $this->invokeSubjectMethod('_prepareShipmentRequest', $request);
        $this->invokeSubjectMethod('_mapRequestToShipment', $request);
        $subject->setRequest($request);
        return $this->doRequest();
    }
    
    
    
    /**
     * Build quotes request XML
     *
     * @return \SimpleXMLElement
     */
    protected function buildQuotesRequestXml()
    {
        $subject = $this->getSubject();
        $rawRequest = $this->getSubjectPropertyValue('_rawRequest');
        $xmlElFactory = $this->getSubjectPropertyValue('_xmlElFactory');
        $countryId = $rawRequest->getOrigCountryId();
        $destCountryId = $rawRequest->getDestCountryId();
        $xml = $xmlElFactory->create(['data' => '<?xml version="1.0" encoding = "UTF-8"?><req:DCTRequest schemaVersion="2.0" '.
            'xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '.
            'xsi:schemaLocation="http://www.dhl.com DCT-req_global-2.0.xsd"/>']);
        $nodeGetQuote = $xml->addChild('GetQuote', '', '');
        $nodeRequest = $nodeGetQuote->addChild('Request');
        $nodeServiceHeader = $nodeRequest->addChild('ServiceHeader');
        $nodeServiceHeader->addChild('MessageTime', $this->invokeSubjectMethod('buildMessageTimestamp'));
        $nodeServiceHeader->addChild(
            'MessageReference',
            $this->invokeSubjectMethod('buildMessageReference', self::SERVICE_PREFIX_QUOTE)
        );
        $nodeServiceHeader->addChild('SiteID', (string) $subject->getConfigData('id'));
        $nodeServiceHeader->addChild('Password', (string) $subject->getConfigData('password'));
        $nodeMetaData = $nodeRequest->addChild('MetaData');
        $nodeMetaData->addChild('SoftwareName', $this->invokeSubjectMethod('buildSoftwareName'));
        $nodeMetaData->addChild('SoftwareVersion', $this->invokeSubjectMethod('buildSoftwareVersion'));
        $nodeFrom = $nodeGetQuote->addChild('From');
        $nodeFrom->addChild('CountryCode', $countryId);
        $nodeFrom->addChild('Postalcode', $rawRequest->getOrigPostal());
        $nodeFrom->addChild('City', $rawRequest->getOrigCity());
        $nodeBkgDetails = $nodeGetQuote->addChild('BkgDetails');
        $nodeBkgDetails->addChild('PaymentCountryCode', $countryId);
        $date = (new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT);
        $nodeBkgDetails->addChild('Date', $date);
        $nodeBkgDetails->addChild('ReadyTime', 'PT'.(int) (string) $subject->getConfigData('ready_time').'H00M');
        $nodeBkgDetails->addChild('DimensionUnit', $this->invokeSubjectMethod('_getDimensionUnit'));
        $nodeBkgDetails->addChild('WeightUnit', $this->invokeSubjectMethod('_getWeightUnit'));
        $this->invokeSubjectMethod('_makePieces', $nodeBkgDetails);
        $nodeBkgDetails->addChild('PaymentAccountNumber', (string) $subject->getConfigData('account'));
        $nodeTo = $nodeGetQuote->addChild('To');
        $nodeTo->addChild('CountryCode', $destCountryId);
        $nodeTo->addChild('Postalcode', $rawRequest->getDestPostal());
        $nodeTo->addChild('City', $rawRequest->getDestCity());
        if ($this->invokeSubjectMethod('isDutiable', $countryId, $destCountryId)) {
            $nodeBkgDetails->addChild('IsDutiable', 'Y');
            $nodeDutiable = $nodeGetQuote->addChild('Dutiable');
            $nodeDutiable->addChild('DeclaredCurrency', $this->getBaseCurrencyCode());
            $nodeDutiable->addChild('DeclaredValue', sprintf("%.2F", $rawRequest->getValue()));
        }
        return $xml;
    }
    
    /**
     * Get quotes
     *
     * @return \Magento\Framework\Model\AbstractModel|Result
     */
    protected function getQuotes()
    {
        $subject = $this->getSubject();
        if (version_compare($this->productMetadata->getVersion(), '2.3.3', '>=')) {
            $httpClient = $this->getSubjectPropertyValue('httpClient');
            $proxyDeferredFactory = $this->getSubjectPropertyValue('proxyDeferredFactory');
            $responseBodies = [];
            $deferredResponses = [];
            $requestXml = $this->buildQuotesRequestXml();
            for ($offset = 0; $offset <= \Magento\Dhl\Model\Carrier::UNAVAILABLE_DATE_LOOK_FORWARD; $offset++) {
                $date = date(\Magento\Dhl\Model\Carrier::REQUEST_DATE_FORMAT, strtotime($this->invokeSubjectMethod('_getShipDate').' +'.$offset.' days'));
                $this->invokeSubjectMethod('_setQuotesRequestXmlDate', $requestXml, $date);
                $request = $requestXml->asXML();
                $responseBody = $this->invokeSubjectMethod('_getCachedQuotes', $request);
                if ($responseBody === null) {
                    $deferredResponses[] = [
                        'deferred' => $httpClient->request(
                            new \Magento\Framework\HTTP\AsyncClient\Request(
                                (string) $subject->getConfigData('gateway_url'),
                                \Magento\Framework\HTTP\AsyncClient\Request::METHOD_POST,
                                ['Content-Type' => 'application/xml'],
                                utf8_encode($request)
                            )
                        ),
                        'date' => $date,
                        'request' => $request
                    ];
                } else {
                    $responseBodies[] = [
                        'body' => $responseBody,
                        'date' => $date,
                        'request' => $request,
                        'from_cache' => true
                    ];
                }
            }
            return $proxyDeferredFactory->create([
                'deferred' => new \Magento\Framework\Async\CallbackDeferred(
                    function () use ($deferredResponses, $responseBodies) {
                        foreach ($deferredResponses as $deferredResponseData) {
                            $responseResult = null;
                            try {
                                $responseResult = $deferredResponseData['deferred']->get();
                            } catch (\Magento\Framework\HTTP\AsyncClient\HttpException $exception) {
                                $this->getSubjectPropertyValue('_logger')->critical($exception);
                            }
                            $responseBody = $responseResult ? $responseResult->getBody() : '';
                            $responseBodies[] = [
                                'body' => $responseBody,
                                'date' => $deferredResponseData['date'],
                                'request' => $deferredResponseData['request'],
                                'from_cache' => false
                            ];
                        }
                        return $this->processQuotesResponses($responseBodies);
                    }
                )
            ]);
        } else {
            $responseBody = '';
            try {
                for ($offset = 0; $offset <= \Magento\Dhl\Model\Carrier::UNAVAILABLE_DATE_LOOK_FORWARD; $offset++) {
                    $requestXml = $this->buildQuotesRequestXml();
                    $date = date(\Magento\Dhl\Model\Carrier::REQUEST_DATE_FORMAT, strtotime($this->invokeSubjectMethod('_getShipDate').' +'.$offset.' days'));
                    $this->invokeSubjectMethod('_setQuotesRequestXmlDate', $requestXml, $date);
                    $request = $requestXml->asXML();
                    $responseBody = $this->invokeSubjectMethod('_getCachedQuotes', $request);
                    if ($responseBody === null) {
                        $responseBody = $this->invokeSubjectMethod('_getQuotesFromServer', $request);
                    }
                    $bodyXml = $this->getSubjectPropertyValue('_xmlElFactory')->create(['data' => $responseBody]);
                    $code = $bodyXml->xpath('//GetQuoteResponse/Note/Condition/ConditionCode');
                    if (!isset($code[0]) || (int) $code[0] != \Magento\Dhl\Model\Carrier::CONDITION_CODE_SERVICE_DATE_UNAVAILABLE) {
                        break;
                    }
                    $this->invokeSubjectMethod('_setCachedQuotes', $request, $responseBody);
                }
            } catch (\Exception $exception) {
                $this->addError($exception->getMessage(), $exception->getCode());
            }
            return $this->invokeSubjectMethod('_parseResponse', $responseBody);
        }
    }
    
    /**
     * Around request to shipment
     * 
     * @param \Magento\Dhl\Model\Carrier $subject
     * @param \Closure $proceed
     * @param \Magento\Shipping\Model\Shipment\Request $request
     * @return array|\Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundRequestToShipment(
        \Magento\Dhl\Model\Carrier $subject,
        \Closure $proceed,
        $request
    )
    {
        $this->setSubject($subject);
        $packages = $request->getPackages();
        if (!is_array($packages) || !$packages) {
            throw new \Magento\Framework\Exception\LocalizedException(__('No packages for request'));
        }
        $result = $this->doShipmentRequest($request);
        $response = new \Magento\Framework\DataObject(['info' => [[
            'tracking_number' => $result->getTrackingNumber(),
            'label_content' => $result->getShippingLabelContent(),
        ]]]);
        $request->setMasterTrackingId($result->getTrackingNumber());
        return $response;
    }
    
    /**
     * Get default value
     *
     * @param string|int $origValue
     * @param string $pathToValue
     * @return string|int|null
     */
    protected function getDefaultValue($origValue, $pathToValue)
    {
        return $this->invokeSubjectMethod('_getDefaultValue', $origValue, $pathToValue);
    }
    
    /**
     * Around collect rates
     * 
     * @param \Magento\Dhl\Model\Carrier $subject
     * @param \Closure $proceed
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return bool|\Magento\Shipping\Model\Rate\Result|\Magento\Quote\Model\Quote\Address\RateResult\Error
     */
    public function aroundCollectRates(
        \Magento\Dhl\Model\Carrier $subject,
        \Closure $proceed,
        \Magento\Quote\Model\Quote\Address\RateRequest $request
    )
    {
        $this->setSubject($subject);
        $proxyDeferredFactory = $this->getSubjectPropertyValue('proxyDeferredFactory');
        if (!$subject->canCollectRates()) {
            return $this->invokeSubjectMethod('getErrorMessage');
        }
        $requestClone = clone $request;
        $subject->setStore($requestClone->getStoreId());
        $requestClone
            ->setOrigCompanyName($this->getDefaultValue($requestClone->getOrigCompanyName(), \Magento\Store\Model\Information::XML_PATH_STORE_INFO_NAME))
            ->setCountryId($this->getDefaultValue($requestClone->getOrigCountryId(), \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_COUNTRY_ID))
            ->setOrigState($this->getDefaultValue($requestClone->getOrigState(), \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_REGION_ID))
            ->setOrigCity($this->getDefaultValue($requestClone->getOrigCity(), \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_CITY))
            ->setOrigPostal($this->getDefaultValue($requestClone->getOrigPostcode(), \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_ZIP));
        $subject->setRequest($requestClone);
        $result = $this->getQuotes();
        $this->setSubjectPropertyValue('_result', $result);
        if (version_compare($this->productMetadata->getVersion(), '2.3.3', '>=')) {
            return $proxyDeferredFactory->create([
                'deferred' => new \Magento\Framework\Async\CallbackDeferred(
                    function () use ($request, $result) {
                        $this->setSubjectPropertyValue('_result', $result);
                        $this->invokeSubjectMethod('_updateFreeMethodQuote', $request);
                        return $this->getSubjectPropertyValue('_result');
                    }
                )
            ]);
        } else {
            $this->invokeSubjectMethod('_updateFreeMethodQuote', $request);
            return $this->getSubjectPropertyValue('_result');
        }
    }
    
}