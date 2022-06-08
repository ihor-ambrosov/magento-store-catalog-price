<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Type;

/**
 * Product type price plugin
 */
class Price extends \Ambros\Common\Plugin\Plugin
{
    
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
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        $this->priceScope = $priceScope;
        parent::__construct($wrapperFactory);
    }
    
    /**
     * Around get tier prices
     * 
     * @param \Magento\Catalog\Model\Product\Type\Price $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $product
     * @return \Magento\Catalog\Api\Data\ProductTierPriceInterface[]
     */
    public function aroundGetTierPrices(
        \Magento\Catalog\Model\Product\Type\Price $subject,
        \Closure $proceed,
        $product
    )
    {
        $this->setSubject($subject);
        $prices = [];
        $pricesData = $this->invokeSubjectMethod('getExistingPrices', $product, \Magento\Catalog\Api\Data\ProductInterface::TIER_PRICE);
        $defaultStoreId = !$this->priceScope->isGlobal() ? $this->priceScope->getStoreId() : 0;
        $tierPriceExtensionFactory = $this->getSubjectPropertyValue('tierPriceExtensionFactory');
        $tierPriceFactory = $this->getSubjectPropertyValue('tierPriceFactory');
        foreach ($pricesData as $priceData) {
            $priceExtension = $tierPriceExtensionFactory->create();
            $price = $tierPriceFactory->create();
            $price->setCustomerGroupId($priceData['cust_group']);
            if (array_key_exists('website_price', $priceData)) {
                $value = $priceData['website_price'];
            } else {
                $value = $priceData['price'];
            }
            $price->setValue($value);
            $price->setQty($priceData['price_qty']);
            if (isset($priceData['percentage_value'])) {
                $priceExtension->setPercentageValue($priceData['percentage_value']);
            }
            $storeId = isset($priceData['store_id']) ? $priceData['store_id'] : $defaultStoreId;
            $priceExtension->setStoreId($storeId);
            $price->setExtensionAttributes($priceExtension);
            $prices[] = $price;
        }
        return $prices;
    }
    
    /**
     * Around set tier prices
     * 
     * @param \Magento\Catalog\Model\Product\Type\Price $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Api\Data\ProductTierPriceInterface[] $prices
     * @return \Magento\Catalog\Model\Product\Type\Price
     */
    public function aroundSetTierPrices(
        \Magento\Catalog\Model\Product\Type\Price $subject,
        \Closure $proceed,
        $product,
        array $prices = null
    )
    {
        $this->setSubject($subject);
        if ($prices === null) {
            return $subject;
        }
        $allCustomersGroupId = $this->invokeSubjectMethod('getAllCustomerGroupsId');
        $defaultStoreId = !$this->priceScope->isGlobal() ? $this->priceScope->getStoreId() : 0;
        $pricesData = [];
        foreach ($prices as $price) {
            $priceExtension = $price->getExtensionAttributes();
            $storeId = $defaultStoreId;
            if ($priceExtension && is_numeric($priceExtension->getStoreId())) {
                $storeId = (string) $priceExtension->getStoreId();
            }
            $customerGroupId = $price->getCustomerGroupId();
            $pricesData[] = [
                'store_id' => $storeId,
                'cust_group' => $customerGroupId,
                'website_price' => $price->getValue(),
                'price' => $price->getValue(),
                'all_groups' => $customerGroupId == $allCustomersGroupId,
                'price_qty' => $price->getQty(),
                'percentage_value' => $priceExtension ? $priceExtension->getPercentageValue() : null
            ];
        }
        $product->setData(\Magento\Catalog\Api\Data\ProductInterface::TIER_PRICE, $pricesData);
        return $subject;
    }
    
}