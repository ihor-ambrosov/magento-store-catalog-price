<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Ui\Catalog\DataProvider\Product\Form\Modifier;

/**
 * Product form data provider advanced pricing modifier plugin
 */
class AdvancedPricing
{
    
    const DATA_SOURCE_DEFAULT = \Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier::DATA_SOURCE_DEFAULT;
    const CODE_TIER_PRICE = \Magento\Catalog\Api\Data\ProductAttributeInterface::CODE_TIER_PRICE;
    
    /**
     * Meta
     * 
     * @var \Ambros\Common\Ui\DataProvider\Modifier\Meta
     */
    protected $meta;
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Currency symbol provider
     * 
     * @var \Ambros\StoreCommon\Ui\Catalog\DataProvider\Product\Form\Modifier\CurrencySymbolProvider 
     */
    protected $currencySymbolProvider;
    
    /**
     * Store option source
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\OptionSource\Store 
     */
    protected $storeOptionSource;
    
    /**
     * Locator
     * 
     * @var \Magento\Catalog\Model\Locator\LocatorInterface
     */
    protected $locator;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * Tier price path
     * 
     * @var string
     */
    protected $tierPricePath;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\Ui\DataProvider\Modifier\Meta $meta
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Ambros\StoreCommon\Ui\Catalog\DataProvider\Product\Form\Modifier\CurrencySymbolProvider $currencySymbolProvider
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\OptionSource\Store $storeOptionSource
     * @param \Magento\Catalog\Model\Locator\LocatorInterface $locator
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Ambros\Common\Ui\DataProvider\Modifier\Meta $meta,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Ambros\StoreCommon\Ui\Catalog\DataProvider\Product\Form\Modifier\CurrencySymbolProvider $currencySymbolProvider,
        \Ambros\StoreCatalogPrice\Model\Catalog\OptionSource\Store $storeOptionSource,
        \Magento\Catalog\Model\Locator\LocatorInterface $locator,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->meta = $meta;
        $this->priceScope = $priceScope;
        $this->currencySymbolProvider = $currencySymbolProvider;
        $this->storeOptionSource = $storeOptionSource;
        $this->locator = $locator;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Get tier price path
     * 
     * @return string|null
     */
    protected function getTierPricePath(): ?string 
    {
        if ($this->tierPricePath !== null) {
            return $this->tierPricePath;
        }
        return $this->tierPricePath = $this->meta->findPath([self::CODE_TIER_PRICE], null, 'children');
    }
    
    /**
     * Get tier price field path
     * 
     * @param string $fieldName
     * @return string
     */
    protected function getTierPriceFieldPath(string $fieldName): string
    {
        return $this->getTierPricePath().'/children/record/children/'.$fieldName;
    }
    
    /**
     * Remove tier price website ID meta
     * 
     * @return $this
     */
    protected function removeTierPriceWebsiteIdMeta()
    {
        $this->meta->remove($this->getTierPriceFieldPath('website_id'));
        return $this;
    }
    
    /**
     * Check if in multiple stores mode
     *
     * @return bool
     */
    protected function isMultiStores()
    {
        return !$this->storeManager->isSingleStoreMode();
    }
    
    /**
     * Check if show store column
     *
     * @return bool
     */
    protected function isShowStoreColumn(): bool
    {
        if ($this->priceScope->isGlobal() || !$this->isMultiStores()) {
            return false;
        }
        return true;
    }
    
    /**
     * Check if is allow change store
     *
     * @return bool
     */
    protected function isAllowChangeStore(): bool
    {
        if (!$this->isShowStoreColumn() || $this->locator->getProduct()->getStoreId()) {
            return false;
        }
        return true;
    }
    
    /**
     * Add tier price store ID meta
     * 
     * @return $this
     */
    protected function addTierPriceStoreIdMeta()
    {
        $storeId = (int) $this->locator->getStore()->getId();
        $value = !$this->storeManager->isSingleStoreMode() && $storeId ? $this->priceScope->getStoreId($storeId) : 0;
        $this->meta->set(
            $this->meta->createField(
                [
                    'component' => 'Ambros_StoreCommon/js/catalog/components/store-currency-symbol',
                    'dataType' => \Magento\Ui\Component\Form\Element\DataType\Text::NAME,
                    'formElement' => \Magento\Ui\Component\Form\Element\Select::NAME,
                    'dataScope' => 'store_id',
                    'label' => __('Store'),
                    'options' => $this->storeOptionSource->getOptions(false, true),
                    'visible' => $this->isMultiStores(),
                    'value' => $value,
                    'disabled' => ($this->isShowStoreColumn() && !$this->isAllowChangeStore()),
                    'sortOrder' => 10,
                    'currenciesForStores' => $this->currencySymbolProvider->getCurrenciesPerStore(),
                    'currency' => $this->currencySymbolProvider->getDefaultCurrency(),
                ]
            ),
            $this->getTierPriceFieldPath('store_id')
        );
        return $this;
    }
    
    /**
     * Update tier price price meta
     * 
     * @return $this
     */
    protected function updateTierPricePriceMeta()
    {
        $tierPricePriceConfigPath = $this->getTierPriceFieldPath('price').'/arguments/data/config';
        $this->meta->merge(
            [
                'component' => 'Ambros_StoreCatalogPrice/js/catalog/components/tier-price-price',
                'imports' => [
                    'priceValue' => '${ $.provider }:data.product.price',
                    '__disableTmpl' => ['priceValue' => false, 'addbefore' => false],
                    'addbefore' => '${ $.parentName }:currency'
                ],
                'tracks' => [
                    'addbefore' => true
                ],
            ],
            $tierPricePriceConfigPath
        );
        return $this;
    }
    
    /**
     * After modify data
     * 
     * @param \Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AdvancedPricing $subject
     * @param array $result
     * @return array
     */
    public function afterModifyData(\Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AdvancedPricing $subject, $result)
    {
        $productId = $this->locator->getProduct()->getId();
        if (empty($result[$productId][self::DATA_SOURCE_DEFAULT][self::CODE_TIER_PRICE])) {
            return $result;
        }
        $tierPricesData = [];
        foreach ($result[$productId][self::DATA_SOURCE_DEFAULT][self::CODE_TIER_PRICE] as $tierPriceData) {
            $storeId = (int) $tierPriceData['store_id'];
            if ($storeId && $storeId != $this->priceScope->getStoreId($storeId)) {
                continue;
            }
            $tierPricesData[] = $tierPriceData;
        }
        $result[$productId][self::DATA_SOURCE_DEFAULT][self::CODE_TIER_PRICE] = $tierPricesData;
        return $result;
    }
    
    /**
     * After modify meta
     * 
     * @param \Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AdvancedPricing $subject
     * @param array $result
     * @return array
     */
    public function afterModifyMeta(\Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AdvancedPricing $subject, $result)
    {
        $this->meta->set($result);
        if ($this->getTierPricePath() === null) {
            return $this->meta->get();
        }
        $this->removeTierPriceWebsiteIdMeta();
        $this->addTierPriceStoreIdMeta();
        $this->updateTierPricePriceMeta();
        return $this->meta->get();
    }
}