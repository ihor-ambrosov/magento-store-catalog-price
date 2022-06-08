<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\AdvancedPricingImportExport\Import;

/**
 * Advanced pricing import plugin
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
    
    const VALUE_ALL_GROUPS = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::VALUE_ALL_GROUPS;
    
    const TIER_PRICE_TYPE_FIXED = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::TIER_PRICE_TYPE_FIXED;
    const TIER_PRICE_TYPE_PERCENT = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::TIER_PRICE_TYPE_PERCENT;
    
    /**
     * Error aggregator
     * 
     * @var \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface 
     */
    protected $errorAggregator;
    
    /**
     * Store validator
     * 
     * @var \Ambros\StoreCatalogPrice\Model\AdvancedPricingImportExport\Import\AdvancedPricing\Validator\Store
     */
    protected $storeValidator;
    
    /**
     * Tier price validator
     * 
     * @var \Ambros\StoreCatalogPrice\Model\AdvancedPricingImportExport\Import\AdvancedPricing\Validator\TierPrice
     */
    protected $tierPriceValidator;
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface $errorAggregator
     * @param \Ambros\StoreCatalogPrice\Model\AdvancedPricingImportExport\Import\AdvancedPricing\Validator\Store $storeValidator
     * @param \Ambros\StoreCatalogPrice\Model\AdvancedPricingImportExport\Import\AdvancedPricing\Validator\TierPrice $tierPriceValidator
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface $errorAggregator,
        \Ambros\StoreCatalogPrice\Model\AdvancedPricingImportExport\Import\AdvancedPricing\Validator\Store $storeValidator,
        \Ambros\StoreCatalogPrice\Model\AdvancedPricingImportExport\Import\AdvancedPricing\Validator\TierPrice $tierPriceValidator,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        parent::__construct($wrapperFactory);
        $this->errorAggregator = $errorAggregator;
        $this->storeValidator = $storeValidator;
        $this->tierPriceValidator = $tierPriceValidator;
        $this->errorAggregator->addErrorMessageTemplate(
            \Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface::ERROR_INVALID_STORE,
            'Invalid value in Store column (store does not exists?)'
        );
        $this->errorAggregator->addErrorMessageTemplate('tierPriceStoreInvalid', 'Tier Price data store is invalid');
        $this->priceScope = $priceScope;
    }
    
    /**
     * Get store ID
     *
     * @param string $storeCode
     * @return array|int|string
     */
    protected function getStoreId($storeCode)
    {
        $storeResolver = $this->getSubjectPropertyValue('_storeResolver');
        return ($storeCode == $this->storeValidator->getAllStoresValue() || $this->priceScope->isGlobal()) ? 
            0 : $storeResolver->getStoreCodeToId($storeCode);
    }
    
    /**
     * Get customer group ID
     *
     * @param string $customerGroup
     * @return int
     */
    protected function getCustomerGroupId($customerGroup)
    {
        $customerGroups = $this->tierPriceValidator->getCustomerGroups();
        return $customerGroup == self::VALUE_ALL_GROUPS ? 0 : $customerGroups[$customerGroup];
    }
    
    /**
     * Save and replace prices
     * 
     * @return $this
     * @throws \Exception
     */
    protected function saveAndReplacePrices()
    {
        $subject = $this->getSubject();
        $behavior = $subject->getBehavior();
        $dataSourceModel = $this->getSubjectPropertyValue('_dataSourceModel');
        if (\Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE == $behavior) {
            $this->setSubjectPropertyValue('_cachedSkuToDelete', null);
        }
        $skus = [];
        $tierPricesData = [];
        $table = self::TABLE_TIER_PRICE;
        while ($bunch = $dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $data) {
                if (!$subject->validateRow($data, $rowNum)) {
                    $subject->addRowError(\Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface::ERROR_SKU_IS_EMPTY, $rowNum);
                    continue;
                }
                if ($this->errorAggregator->hasToBeTerminated()) {
                    $this->errorAggregator->addRowToSkip($rowNum);
                    continue;
                }
                $sku = $data[self::COL_SKU];
                $tierPrice = $data[self::COL_TIER_PRICE];
                $tierPriceType = $data[self::COL_TIER_PRICE_TYPE];
                $customerGroup = $data[self::COL_TIER_PRICE_CUSTOMER_GROUP];
                $skus[] = $sku;
                if (!empty($data[self::COL_TIER_PRICE_STORE])) {
                    $tierPricesData[$sku][] = [
                        'all_groups' => $customerGroup == self::VALUE_ALL_GROUPS,
                        'customer_group_id' => $this->getCustomerGroupId($customerGroup),
                        'qty' => $data[self::COL_TIER_PRICE_QTY],
                        'value' => $tierPriceType === self::TIER_PRICE_TYPE_FIXED ? $tierPrice : 0,
                        'percentage_value' => $tierPriceType === self::TIER_PRICE_TYPE_PERCENT ? $tierPrice : null,
                        'store_id' => $this->getStoreId($data[self::COL_TIER_PRICE_STORE])
                    ];
                }
            }
            if (\Magento\ImportExport\Model\Import::BEHAVIOR_APPEND == $behavior) {
                $this->invokeSubjectMethod('processCountExistingPrices', $tierPricesData, $table);
                $this->invokeSubjectMethod('processCountNewPrices', $tierPricesData);
                $this->invokeSubjectMethod('saveProductPrices', $tierPricesData, $table);
                if ($skus) {
                    $this->invokeSubjectMethod('setUpdatedAt', $skus);
                }
            }
        }
        if (\Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE == $behavior) {
            if ($skus) {
                $this->invokeSubjectMethod('processCountNewPrices', $tierPricesData);
                if ($this->invokeSubjectMethod('deleteProductTierPrices', array_unique($skus), $table)) {
                    $this->invokeSubjectMethod('saveProductPrices', $tierPricesData, $table);
                    $this->invokeSubjectMethod('setUpdatedAt', $skus);
                }
            }
        }
        return $this;
    }
    
    /**
     * Around get valid column names
     * 
     * @param \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing $subject
     * @param \Closure $proceed
     * @return array
     */
    public function aroundGetValidColumnNames(
        \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing $subject,
        \Closure $proceed
    )
    {
        return [
            self::COL_SKU,
            self::COL_TIER_PRICE_STORE,
            self::COL_TIER_PRICE_CUSTOMER_GROUP,
            self::COL_TIER_PRICE_QTY,
            self::COL_TIER_PRICE,
            self::COL_TIER_PRICE_TYPE
        ];
    }
    
    /**
     * Around save advanced pricing
     * 
     * @param \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing $subject
     * @param \Closure $proceed
     * @return \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing
     * @throws \Exception
     */
    public function aroundSaveAdvancedPricing(
        \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $this->saveAndReplacePrices();
        return $subject;
    }
    
    /**
     * Around replace advanced pricing
     * 
     * @param \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing $subject
     * @param \Closure $proceed
     * @return \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing
     * @throws \Exception
     */
    public function aroundReplaceAdvancedPricing(
        \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $this->saveAndReplacePrices();
        return $subject;
    }
    
    /**
     * Around delete advanced pricing
     * 
     * @param \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing $subject
     * @param \Closure $proceed
     * @return $this
     * @throws \Exception
     */
    public function aroundDeleteAdvancedPricing(
        \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $this->setSubjectPropertyValue('_cachedSkuToDelete', null);
        $skus = [];
        $dataSourceModel = $this->getSubjectPropertyValue('_dataSourceModel');
        $errorAggregator = $subject->getErrorAggregator();
        while ($bunch = $dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $data) {
                $subject->validateRow($data, $rowNum);
                if (!$errorAggregator->isRowInvalid($rowNum)) {
                    $skus[] = $data[self::COL_SKU];
                }
                if ($errorAggregator->hasToBeTerminated()) {
                    $errorAggregator->addRowToSkip($rowNum);
                }
            }
        }
        if ($skus) {
            $this->invokeSubjectMethod('deleteProductTierPrices', array_unique($skus), self::TABLE_TIER_PRICE);
            $this->invokeSubjectMethod('setUpdatedAt', $skus);
        }
        return $this;
    }
    
}