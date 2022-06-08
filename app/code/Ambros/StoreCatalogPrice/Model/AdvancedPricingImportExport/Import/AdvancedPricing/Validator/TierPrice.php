<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\AdvancedPricingImportExport\Import\AdvancedPricing\Validator;

/**
 * Tier price validator
 */
class TierPrice extends \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing\Validator\TierPrice
{
    
    const COL_TIER_PRICE_STORE = 'tier_price_store';
    const COL_TIER_PRICE_CUSTOMER_GROUP = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE_CUSTOMER_GROUP;
    const COL_TIER_PRICE_QTY = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE_QTY;
    const COL_TIER_PRICE = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE;
    const COL_TIER_PRICE_TYPE = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::COL_TIER_PRICE_TYPE;
    
    const VALUE_ALL_GROUPS = \Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing::VALUE_ALL_GROUPS;
    
    /**
     * Tier price columns
     * 
     * @var array
     */
    protected $tierPriceColumns = [
        self::COL_TIER_PRICE_STORE,
        self::COL_TIER_PRICE_CUSTOMER_GROUP,
        self::COL_TIER_PRICE_QTY,
        self::COL_TIER_PRICE,
        self::COL_TIER_PRICE_TYPE,
    ];
    
    /**
     * Check if is set
     *  
     * @param array $value
     * @return bool
     */
    protected function isSet(array $value)
    {
        $isSet = false;
        foreach ($this->tierPriceColumns as $column) {
            if (isset($value[$column]) && strlen($value[$column])) {
                $isSet = true;
                break;
            }
        }
        return $isSet;
    }
    
    /**
     * Check if has empty columns
     *
     * @param array $value
     * @return bool
     */
    protected function hasEmptyColumns(array $value)
    {
        $hasEmptyColumns = false;
        foreach ($this->tierPriceColumns as $column) {
            if (!isset($value[$column]) || !strlen($value[$column])) {
                $hasEmptyColumns = true;
                break;
            }
        }
        if ($hasEmptyColumns) {
            $this->_addMessages([self::ERROR_TIER_DATA_INCOMPLETE]);
        }
        return $hasEmptyColumns;
    }
    
    /**
     * Check if customer group column is valid
     * 
     * @param array $value
     * @return boolean
     */
    protected function isCustomerGroupColumnValid($value)
    {
        $customerGroup = $value[self::COL_TIER_PRICE_CUSTOMER_GROUP] ?? null;
        if ($customerGroup != self::VALUE_ALL_GROUPS && !isset($this->customerGroups[$customerGroup])) {
            $this->_addMessages([self::ERROR_INVALID_TIER_PRICE_GROUP]);
            return false;
        }
        return true;
    }
    
    /**
     * Check if decimal column is valid
     * 
     * @param array $value
     * @param string $columnName
     * @return boolean
     */
    protected function isDecimalColumnValid($value, $columnName)
    {
        if (!is_numeric($value[$columnName]) || $value[$columnName] < 0) {
            $this->addDecimalError($columnName);
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
        if (!$this->customerGroups) {
            $this->init($this->context);
        }
        if (!$this->isSet($value)) {
            return true;
        }
        if (
            $this->hasEmptyColumns($value) || !$this->isCustomerGroupColumnValid($value) || 
            !$this->isDecimalColumnValid($value, self::COL_TIER_PRICE_QTY) || !$this->isDecimalColumnValid($value, self::COL_TIER_PRICE)
        ) {
            return false;
        }
        return true;
    }
    
}