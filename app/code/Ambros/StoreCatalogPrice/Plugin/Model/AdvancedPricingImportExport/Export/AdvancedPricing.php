<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\AdvancedPricingImportExport\Export;

/**
 * Advanced pricing export plugin
 */
class AdvancedPricing extends \Ambros\Common\Plugin\Plugin
{
    
    const TABLE_TIER_PRICE = 'ambros_store__catalog_product_entity_tier_price';
    
    const COL_SKU = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_SKU;
    const COL_TIER_PRICE_STORE = 'tier_price_store';
    const COL_TIER_PRICE_CUSTOMER_GROUP = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE_CUSTOMER_GROUP;
    const COL_TIER_PRICE_QTY = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE_QTY;
    const COL_TIER_PRICE = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE;
    const COL_TIER_PRICE_TYPE = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE_TYPE;
    const COL_TIER_PRICE_PERCENTAGE_VALUE = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE_PERCENTAGE_VALUE;
    const COL_PRODUCT_ID = 'product_link_id';
    
    const VALUE_ALL_STORES = 'All Stores';
    const VALUE_ALL_GROUPS = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::VALUE_ALL_GROUPS;
    
    /**
     * Price keys
     *
     * @var array
     */
    protected $priceKeys = [
        self::COL_SKU,
        self::COL_TIER_PRICE_STORE,
        self::COL_TIER_PRICE_CUSTOMER_GROUP,
        self::COL_TIER_PRICE_QTY,
        self::COL_TIER_PRICE,
        self::COL_TIER_PRICE_TYPE
    ];
    
    /**
     * Store codes
     * 
     * @var string[]
     */
    protected $storeCodes = [];
    
    /**
     * Get store code
     *
     * @param int $storeId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getStoreCode(int $storeId): string
    {
        if (array_key_exists($storeId, $this->storeCodes)) {
            return $this->storeCodes[$storeId];
        }
        $storeManager = $this->getSubjectPropertyValue('_storeManager');
        $store = $storeManager->getStore($storeId);
        if ($storeId) {
            $storeCode = $store->getCode();
        } else {
            $storeCode = self::VALUE_ALL_STORES.' ['.$store->getBaseCurrencyCode().']';
        }
        return $this->storeCodes[$storeId] = $storeCode;
    }
    
    /**
     * Get products data
     * 
     * @return array
     */
    protected function getProductsData(): array
    {
        $productsDataByStores = $this->invokeSubjectMethod('loadCollection');
        if (empty($productsDataByStores)) {
            return [];
        }
        $data = [];
        foreach ($productsDataByStores as $productDataByStores) {
            $data[] = $productDataByStores[\Magento\Store\Model\Store::DEFAULT_STORE_ID];
        }
        return $data;
    }
    
    /**
     * Get filter
     * 
     * @return array|null
     */
    protected function getFilter(): ?array
    {
        $parameters = $this->getSubjectPropertyValue('_parameters');
        if (!empty($parameters[\Magento\ImportExport\Model\Export::FILTER_ELEMENT_GROUP])) {
            return $parameters[\Magento\ImportExport\Model\Export::FILTER_ELEMENT_GROUP];
        }
        return null;
    }
    
    /**
     * Get tier price filter
     * 
     * @return array|null
     */
    protected function getTierPriceFilter(): ?array
    {
        $filter = $this->getFilter();
        if ($filter !== null && !empty($filter['tier_price'])) {
            return $filter['tier_price'];
        }
        return null;   
    }
    
    /**
     * Get tier price from filter
     * 
     * @return float|null
     */
    protected function getTierPriceFromFilter(): ?float
    {
        $filter = $this->getTierPriceFilter();
        if ($filter !== null && !empty($filter[0])) {
            return (float) $filter[0];
        }
        return null;
    }
    
    /**
     * Get tier price to filter
     * 
     * @return float|null
     */
    protected function getTierPriceToFilter(): ?float
    {
        $filter = $this->getTierPriceFilter();
        if ($filter !== null && !empty($filter[1])) {
            return (float) $filter[1];
        }
        return null;
    }
    
    /**
     * Get tier prices data
     *
     * @param string[] $productIds
     * @return array
     */
    protected function getTierPricesData(array $productIds): array
    {
        $connection = $this->getSubjectPropertyValue('_connection');
        $resource = $this->getSubjectPropertyValue('_resource');
        if (empty($productIds)) {
            throw new \InvalidArgumentException('Can only load tier prices for specific products');
        }
        $fromFilter = $this->getTierPriceFromFilter();
        $toFilter = $this->getTierPriceToFilter();
        $select = $connection->select()
            ->from(['ap' => $resource->getTableName(self::TABLE_TIER_PRICE)], [
                self::COL_TIER_PRICE_STORE => 'ap.store_id',
                self::VALUE_ALL_GROUPS => 'ap.all_groups',
                self::COL_TIER_PRICE_CUSTOMER_GROUP => 'ap.customer_group_id',
                self::COL_TIER_PRICE_QTY => 'ap.qty',
                self::COL_TIER_PRICE => 'ap.value',
                self::COL_TIER_PRICE_PERCENTAGE_VALUE => 'ap.percentage_value',
                self::COL_PRODUCT_ID => 'ap.entity_id',
            ])
            ->where('ap.entity_id IN (?)', $productIds);
        if ($fromFilter !== null) {
            $select->where('ap.value >= ?', $fromFilter);
        }
        if ($toFilter !== null) {
            $select->where('ap.value <= ?', $toFilter);
        }
        if ($fromFilter || $toFilter) {
            $select->orWhere('ap.percentage_value IS NOT NULL');
        }
        return (array) $connection->fetchAll($select);
    }
    
    /**
     * Prepare price data
     *
     * @param array $tierPriceData
     * @return array
     */
    protected function preparePriceData(array $tierPriceData): array
    {
        $data = [];
        foreach ($this->priceKeys as $key) {
            if (!array_key_exists($key, $tierPriceData)) {
                continue;
            }
            if ($key === self::COL_TIER_PRICE_STORE) {
                $data[$key] = $this->getStoreCode((int) $tierPriceData[$key]);
            } elseif ($key === self::COL_TIER_PRICE_CUSTOMER_GROUP) {
                $data[$key] = $this->invokeSubjectMethod(
                    '_getCustomerGroupById',
                    $tierPriceData[$key],
                    $tierPriceData[self::VALUE_ALL_GROUPS]
                );
            } elseif ($key === self::COL_TIER_PRICE) {
                $tierPricePercentageValue = $tierPriceData[self::COL_TIER_PRICE_PERCENTAGE_VALUE];
                $tierPrice = $tierPriceData[self::COL_TIER_PRICE];
                $data[$key] =  $tierPricePercentageValue ? $tierPricePercentageValue : $tierPrice;
                $data[self::COL_TIER_PRICE_TYPE] = $this->invokeSubjectMethod('tierPriceTypeValue', $tierPriceData);
            } else {
                $data[$key] = $tierPriceData[$key];
            }
        }
        unset($data[self::VALUE_ALL_GROUPS]);
        return $data;
    }
    
    /**
     * Prepare prices data
     *
     * @param array $skus
     * @param array $tierPricesData
     * @return array
     */
    protected function preparePricesData(array $skus, array $tierPricesData): array
    {
        $data = [];
        foreach ($tierPricesData as $tierPriceData) {
            $productId = $tierPriceData[self::COL_PRODUCT_ID];
            $tierPriceData[self::COL_SKU] = $skus[$productId];
            $data[] = $this->preparePriceData($tierPriceData);
        }
        return $data;
    }
    
    /**
     * Get prices data
     *
     * @return array|mixed
     */
    protected function getPricesData()
    {
        $passTierPrice = $this->getSubjectPropertyValue('_passTierPrice');
        $logger = $this->getSubjectPropertyValue('_logger');
        if ($passTierPrice) {
            return [];
        }
        try {
            $productsData = $this->getProductsData();
            if (empty($productsData)) {
                return [];
            }
            $productIds = [];
            $skus = [];
            foreach ($productsData as $productData) {
                $productId = $productData['entity_id'];
                $sku = $productData['sku'];
                $productIds[$productId] = $productId;
                $skus[$productId] = $sku;
            }
            $data = $this->preparePricesData($skus, $this->getTierPricesData($productIds));
            if (!empty($data)) {
                asort($data);
                return $data;
            }
        } catch (\Throwable $exception) {
            $logger->critical($exception);
        }
        return [];
    }
    
    /**
     * Around export
     * 
     * @param \Magento\AdvancedPricingImportExport\Model\Export\AdvancedPricing $subject
     * @param \Closure $proceed
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundExport(
        \Magento\AdvancedPricingImportExport\Model\Export\AdvancedPricing $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        set_time_limit(0);
        $writer = $subject->getWriter();
        $page = 0;
        $itemsPerPage = $this->invokeSubjectMethod('getItemsPerPage');
        while (true) {
            ++$page;
            $entityCollection = $this->invokeSubjectMethod('_getEntityCollection', true);
            $entityCollection->setOrder('has_options', 'asc');
            $entityCollection->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
            $this->invokeSubjectMethod('_prepareEntityCollection', $entityCollection);
            $this->invokeSubjectMethod('paginateCollection', $page, $itemsPerPage);
            if ($entityCollection->count() == 0) {
                break;
            }
            $entityCollection->clear();
            $pricesData = $this->getPricesData();
            foreach ($pricesData as $priceData) {
                $writer->writeRow($priceData);
            }
            if ($entityCollection->getCurPage() >= $entityCollection->getLastPageNumber()) {
                break;
            }
        }
        return $writer->getContents();
    }
    
}