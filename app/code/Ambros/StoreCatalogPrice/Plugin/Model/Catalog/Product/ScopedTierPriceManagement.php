<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product;

/**
 * Scoped tier price management plugin
 */
class ScopedTierPriceManagement extends \Ambros\Common\Plugin\Plugin
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
     * Get product
     * 
     * @param string $sku
     * @return \Magento\Catalog\Api\Data\ProductInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getProduct(string $sku): \Magento\Catalog\Api\Data\ProductInterface
    {
        return $this->getSubjectPropertyValue('productRepository')
            ->get($sku, ['edit_mode' => true]);
    }
    
    /**
     * Check if value is positive float
     * 
     * @param mixed $value
     * @return bool
     */
    protected function isPositiveFloat($value): bool
    {
        return (is_float($value) || is_int($value) || \Zend_Validate::is((string) $value, 'Float')) && $value > 0;
    }
    
    /**
     * Validate price and qty
     * 
     * @param mixed $price
     * @param mixed $qty
     * @return bool
     * @throws \Magento\Framework\Exception\InputException
     */
    protected function validatePriceAndQty($price, $qty): bool
    {
        if (!$this->isPositiveFloat($price) || !$this->isPositiveFloat($qty)) {
            throw new \Magento\Framework\Exception\InputException(__('The data was invalid. Verify the data and try again.'));
        }
        return true;
    }
    
    /**
     * Check if prices are equal
     * 
     * @param int $storeId
     * @param array $price1
     * @param array $price2
     * @return bool
     */
    protected function areEqualPrices(int $storeId, array $price1, array $price2): bool
    {
        return $price1->getCustomerGroupId() == $price2->getCustomerGroupId() && 
            $price1->getQty() == $price2->getQty() && 
            $storeId == ($price2->getExtensionAttributes() ? $price2->getExtensionAttributes()->getStoreId() : 0);
    }
    
    /**
     * Prepare prices
     * 
     * @param array $prices
     * @param \Magento\Catalog\Api\Data\ProductTierPriceInterface $newPrice
     * @return \Magento\Catalog\Api\Data\ProductTierPriceInterface[]|null
     */
    protected function preparePrices(array $prices, \Magento\Catalog\Api\Data\ProductTierPriceInterface $newPrice)
    {
        $this->validatePriceAndQty($newPrice->getValue(), $newPrice->getQty());
        $storeId = !$this->priceScope->isGlobal() ? $this->priceScope->getStoreId() : 0;
        foreach ($prices as $priceKey => $price) {
            if ($this->areEqualPrices($storeId, $price, $newPrice)) {
                unset($prices[$priceKey]);
                break;
            }
        }
        $prices[] = $newPrice;
        return $prices;
    }
    
    /**
     * Around add
     * 
     * @param \Magento\Catalog\Model\Product\ScopedTierPriceManagement $subject
     * @param \Closure $proceed
     * @param string $sku
     * @param \Magento\Catalog\Api\Data\ProductTierPriceInterface $tierPrice
     * @return boolean
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function aroundAdd(
        \Magento\Catalog\Model\Product\ScopedTierPriceManagement $subject,
        \Closure $proceed,
        $sku,
        \Magento\Catalog\Api\Data\ProductTierPriceInterface $tierPrice
    )
    {
        $this->setSubject($subject);
        $productRepository = $this->getSubjectPropertyValue('productRepository');
        $product = $this->getProduct($sku);
        $product->setTierPrices($this->preparePrices($product->getTierPrices(), $tierPrice));
        try {
            $productRepository->save($product);
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(__('The group price couldn\'t be saved.'));
        }
        return true;
    }
    
    /**
     * Around remove
     * 
     * @param \Magento\Catalog\Model\Product\ScopedTierPriceManagement $subject
     * @param \Closure $proceed
     * @param string $sku
     * @param \Magento\Catalog\Api\Data\ProductTierPriceInterface $tierPrice
     * @return boolean
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function aroundRemove(
        \Magento\Catalog\Model\Product\ScopedTierPriceManagement $subject,
        \Closure $proceed,
        $sku,
        \Magento\Catalog\Api\Data\ProductTierPriceInterface $tierPrice
    )
    {
        $this->getSubjectPropertyValue('tierPriceManagement')
            ->remove($sku, $tierPrice->getCustomerGroupId(), $tierPrice->getQty());
        return true;
    }
    
}