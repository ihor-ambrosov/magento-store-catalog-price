<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Attribute\Backend;

/**
 * Product tier price attribute back-end plugin
 */
class Tierprice extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope 
     */
    protected $priceScope;
    
    /**
     * Base currency
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency
     */
    protected $baseCurrency;
    
    /**
     * Store base currency
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency
     */
    protected $storeBaseCurrency;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency $baseCurrency
     * @param \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency $storeBaseCurrency
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Ambros\StoreCatalogPrice\Model\Directory\BaseCurrency $baseCurrency,
        \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency $storeBaseCurrency
    )
    {
        $this->priceScope = $priceScope;
        $this->baseCurrency = $baseCurrency;
        $this->storeBaseCurrency = $storeBaseCurrency;
        parent::__construct($wrapperFactory);
    }
    
    /**
     * Get store ID
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $object
     * @return int|null
     */
    protected function getStoreId(\Magento\Catalog\Api\Data\ProductInterface $object): ?int
    {
        if ($this->priceScope->isGlobal()) {
            return 0;
        }
        if (!$object->getStoreId()) {
            return null;
        }
        return $this->priceScope->getStoreIdByAttribute($this->getSubject()->getAttribute(), (int) $object->getStoreId());
    }
    
    /**
     * Get duplicate error message
     *
     * @return \Magento\Framework\Phrase
     */
    protected function getDuplicateErrorMessage(): \Magento\Framework\Phrase
    {
        return __('We found a duplicate store, tier price, customer group and quantity.');
    }
    
    /**
     * Get price data key
     * 
     * @param array $priceData
     * @param int $storeId
     * @return string
     */
    protected function getPriceDataKey(array $priceData, int $storeId = null): string
    {
        $customerGroupId = $priceData['cust_group'];
        return implode(
            '-',
            array_merge(
                ($storeId !== null) ? [$storeId, $customerGroupId] : [$customerGroupId],
                $this->invokeSubjectMethod('_getAdditionalUniqueFields', $priceData)
            )
        );
    }
    
    /**
     * Around validate
     * 
     * @param \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $object
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\Phrase|bool
     */
    public function aroundValidate(
        \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice $subject,
        \Closure $proceed,
        $object
    )
    {
        $this->setSubject($subject);
        $attribute = $subject->getAttribute();
        $pricesData = array_filter((array) $object->getData($attribute->getName()));
        if (empty($pricesData)) {
            return true;
        }
        $duplicates = [];
        foreach ($pricesData as $priceData) {
            $percentage = $this->invokeSubjectMethod('getPercentage', $priceData);
            if ($percentage !== null && (!$this->invokeSubjectMethod('isPositiveOrZero', $percentage) || $percentage > 100)) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Percentage value must be a number between 0 and 100.'));
            }
            if (!empty($priceData['delete'])) {
                continue;
            }
            $priceDataKey = $this->getPriceDataKey($priceData, (int) $priceData['store_id']);
            if (isset($duplicates[$priceDataKey])) {
                throw new \Magento\Framework\Exception\LocalizedException(__($this->getDuplicateErrorMessage()));
            }
            $this->invokeSubjectMethod('validatePrice', $priceData);
            $duplicates[$priceDataKey] = true;
        }
        if (!$attribute->isScopeGlobal() && $object->getStoreId()) {
            $origPricesData = $object->getOrigData($attribute->getName());
            if ($origPricesData) {
                foreach ($origPricesData as $origPriceData) {
                    if ($origPriceData['store_id'] != 0) {
                        continue;
                    }
                    $priceDataKey = $this->getPriceDataKey($origPriceData, (int) $origPriceData['store_id']);
                    $duplicates[$priceDataKey] = true;
                }
            }
        }
        $baseCurrencyCode = $this->baseCurrency->getCode();
        foreach ($pricesData as $priceData) {
            $storeId = (int) $priceData['store_id'];
            if (!empty($priceData['delete']) || $storeId == 0) {
                continue;
            }
            $globalPriceDataKey = $this->getPriceDataKey($priceData, 0);
            $storeBaseCurrencyCode = $this->storeBaseCurrency->getCode($storeId);
            if ($baseCurrencyCode === $storeBaseCurrencyCode && isset($duplicates[$globalPriceDataKey])) {
                throw new \Magento\Framework\Exception\LocalizedException(__($this->getDuplicateErrorMessage()));
            }
        }
        return true;
    }
    
    /**
     * Around prepare price data
     * 
     * @param \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice $subject
     * @param \Closure $proceed
     * @param array $pricesData
     * @param string $productTypeId
     * @param int $storeId
     * @return array
     */
    public function aroundPreparePriceData(
        \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice $subject,
        \Closure $proceed,
        array $pricesData,
        $productTypeId,
        $storeId
    )
    {
        $this->setSubject($subject);
        $preparedPricesData = [];
        $storeRate = $this->storeBaseCurrency->getRate((int) $storeId);
        $isPriceFixed = $this->invokeSubjectMethod('_isPriceFixed', $this->getSubjectPropertyValue('_catalogProductType')->priceFactory($productTypeId));
        foreach ($pricesData as $priceData) {
            if (!array_filter($priceData)) {
                continue;
            }
            $priceDataStoreId = $priceData['store_id'];
            $priceDataPrice = $priceData['price'];
            $priceDataKey = $this->getPriceDataKey($priceData);
            if ($priceDataStoreId == $storeId) {
                $preparedPricesData[$priceDataKey] = $priceData;
                $preparedPricesData[$priceDataKey]['website_price'] = $priceDataPrice;
            } elseif ($priceDataStoreId == 0 && !isset($preparedPricesData[$priceDataKey])) {
                $preparedPricesData[$priceDataKey] = $priceData;
                $preparedPricesData[$priceDataKey]['store_id'] = $storeId;
                if ($isPriceFixed) {
                    $preparedPricesData[$priceDataKey]['price'] = $priceDataPrice * $storeRate;
                    $preparedPricesData[$priceDataKey]['website_price'] = $priceDataPrice * $storeRate;
                }
            }
        }
        return $preparedPricesData;
    }
    
    /**
     * Around after load
     * 
     * @param \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $object
     * @return \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice
     */
    public function aroundAfterLoad(
        \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice $subject,
        \Closure $proceed,
        $object
    )
    {
        $this->setSubject($subject);
        $resource = $this->invokeSubjectMethod('_getResource');
        $pricesData = $resource->loadPriceData($object->getData('entity_id'), $this->getStoreId($object));
        $subject->setPriceData($object, $pricesData);
        return $subject;
    }
    
    /**
     * Around set price data
     * 
     * @param \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $object
     * @param array $pricesData
     * @return void
     */
    public function aroundSetPriceData(
        \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice $subject,
        \Closure $proceed,
        $object,
        $pricesData
    )
    {
        $this->setSubject($subject);
        $attributeName = $subject->getAttribute()->getName();
        $modifiedPricesData = $this->invokeSubjectMethod('modifyPriceData', $object, $pricesData);
        $storeId = $this->getStoreId($object);
        if (!$object->getData('_edit_mode') && $storeId) {
            $preparedPricesData = $subject->preparePriceData($modifiedPricesData, $object->getTypeId(), $storeId);
        } else {
            $preparedPricesData = $modifiedPricesData;
        }
        $object->setData($attributeName, $preparedPricesData);
        $object->setOrigData($attributeName, $preparedPricesData);
        $valueChangedKey = $attributeName.'_changed';
        $object->setData($valueChangedKey, 0);
        $object->setOrigData($valueChangedKey, 0);
    }
    
}