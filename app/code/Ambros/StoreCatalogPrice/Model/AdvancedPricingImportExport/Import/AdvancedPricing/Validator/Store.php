<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\AdvancedPricingImportExport\Import\AdvancedPricing\Validator;

/**
 * Advanced pricing store validator
 */
class Store extends \Magento\CatalogImportExport\Model\Import\Product\Validator\AbstractImportValidator 
    implements \Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface 
{
    
    const COL_TIER_PRICE_STORE = 'tier_price_store';
    const COL_TIER_PRICE = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE;
    
    const VALUE_ALL_STORES = 'All Stores';
    
    /**
     * Base currency
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency
     */
    protected $baseCurrency;
    
    /**
     * Store resolver
     * 
     * @var \Magento\CatalogImportExport\Model\Import\Product\StoreResolver
     */
    protected $storeResolver;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency $baseCurrency
     * @param \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver
     * @return void
     */
    public function __construct(
        \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency $baseCurrency,
        \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver
    )
    {
        $this->baseCurrency = $baseCurrency;
        $this->storeResolver = $storeResolver;
    }
    
    /**
     * Get all stores value
     *
     * @return string
     */
    public function getAllStoresValue()
    {
        return self::VALUE_ALL_STORES.' ['.$this->baseCurrency->getCode().']';
    }
    
    /**
     * Check if store column is valid
     * 
     * @param array $value
     * @return false
     */
    protected function isStoreColumnValid($value)
    {
        $store = $value[self::COL_TIER_PRICE_STORE] ?? null;
        if (empty($store)) {
            return true;
        }
        if ($store != $this->getAllStoresValue() && !$this->storeResolver->getStoreCodeToId($store)) {
            return false;
        }
        return true;
    }
    
    /**
     * Check if is valid
     * 
     * @param mixed $value
     * @return bool
     */
    public function isValid($value)
    {
        $this->_clearMessages();
        if (!empty($value[self::COL_TIER_PRICE]) && !$this->isStoreColumnValid($value)) {
            $this->_addMessages([self::ERROR_INVALID_STORE]);
            return false;
        }
        return true;
    }
    
}