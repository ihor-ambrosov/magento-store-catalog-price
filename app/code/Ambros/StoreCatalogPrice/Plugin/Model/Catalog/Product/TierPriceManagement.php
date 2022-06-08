<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product;

/**
 * Tier price management plugin
 */
class TierPriceManagement extends \Ambros\Common\Plugin\Plugin
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
     * Check if is price customer group
     * 
     * @param array $priceData
     * @param mixed $customerGroupId
     * @return bool
     */
    protected function isPriceCustomerGroup(array $priceData, $customerGroupId): bool
    {
        return (is_numeric($customerGroupId) && (int) $priceData['cust_group'] === (int) $customerGroupId) || 
                ($customerGroupId === 'all' && $priceData['all_groups']);
    }
    
    /**
     * Cast customer group ID
     * 
     * @param mixed $customerGroupId
     * @return int
     */
    protected function castCustomerGroupId($customerGroupId): int
    {
        return 'all' == $customerGroupId ? 
            $this->getSubjectPropertyValue('groupManagement')->getAllCustomersGroup()->getId() : 
            $this->getSubjectPropertyValue('groupRepository')->getById($customerGroupId)->getId();
    }
    
    /**
     * Around add
     * 
     * @param \Magento\Catalog\Model\Product\TierPriceManagement $subject
     * @param \Closure $proceed
     * @param string $sku
     * @param string $customerGroupId
     * @param float $price
     * @param float $qty
     * @return boolean
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function aroundAdd(
        \Magento\Catalog\Model\Product\TierPriceManagement $subject,
        \Closure $proceed,
        $sku,
        $customerGroupId,
        $price,
        $qty
    )
    {
        $this->setSubject($subject);
        $productRepository = $this->getSubjectPropertyValue('productRepository');
        $this->validatePriceAndQty($price, $qty);
        $product = $this->getProduct($sku);
        $storeId = !$this->priceScope->isGlobal() ? $this->priceScope->getStoreId() : 0;
        $pricesData = $product->getData(\Magento\Catalog\Api\Data\ProductInterface::TIER_PRICE);
        $isPriceFound = false;
        foreach ($pricesData as &$priceData) {
            if (
                $this->isPriceCustomerGroup($priceData, $customerGroupId) && 
                $priceData['store_id'] == $storeId && 
                $priceData['price_qty'] == $qty
            ) {
                $priceData['price'] = $price;
                $isPriceFound = true;
                break;
            }
        }
        if (!$isPriceFound) {
            $pricesData[] = [
                'cust_group' => $this->castCustomerGroupId($customerGroupId),
                'price' => $price,
                'website_price' => $price,
                'store_id' => $storeId,
                'price_qty' => $qty,
            ];
        }
        $product->setData(\Magento\Catalog\Api\Data\ProductInterface::TIER_PRICE, $pricesData);
        $errors = $product->validate();
        if (is_array($errors) && count($errors)) {
            $errorAttributeCodes = implode(', ', array_keys($errors));
            throw new \Magento\Framework\Exception\InputException(
                __('Values in the %1 attributes are invalid. Verify the values and try again.', $errorAttributeCodes)
            );
        }
        try {
            $productRepository->save($product);
        } catch (\Exception $exception) {
            if ($exception instanceof \Magento\Framework\Exception\TemporaryStateExceptionInterface) {
                throw $exception;
            }
            throw new \Magento\Framework\Exception\CouldNotSaveException(__('The group price couldn\'t be saved.'));
        }
        return true;
    }
    
    /**
     * Around remove
     * 
     * @param \Magento\Catalog\Model\Product\TierPriceManagement $subject
     * @param \Closure $proceed
     * @param string $sku
     * @param string $customerGroupId
     * @param float $qty
     * @return boolean
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function aroundRemove(
        \Magento\Catalog\Model\Product\TierPriceManagement $subject,
        \Closure $proceed,
        $sku,
        $customerGroupId,
        $qty
    )
    {
        $this->setSubject($subject);
        $productRepository = $this->getSubjectPropertyValue('productRepository');
        $product = $this->getProduct($sku);
        $storeId = !$this->priceScope->isGlobal() ? $this->priceScope->getStoreId() : 0;
        $pricesData = $product->getData(\Magento\Catalog\Api\Data\ProductInterface::TIER_PRICE);
        if ($pricesData === null) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(__('Tier price is unavailable for this product.'));
        }
        $isPriceFound = false;
        foreach ($pricesData as $key => $priceData) {
            if (
                $this->isPriceCustomerGroup($priceData, $customerGroupId) && 
                $priceData['store_id'] == $storeId && 
                $priceData['price_qty'] == $qty
            ) {
                $isPriceFound = true;
                unset($pricesData[$key]);
            }
        }
        if (!$isPriceFound) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(
                __(
                    'Product hasn\'t group price with such data: customerGroupId = \'%1\', store = %2, qty = %3',
                    [$customerGroupId, $storeId, $qty]
                )
            );
        }
        $product->setData(\Magento\Catalog\Api\Data\ProductInterface::TIER_PRICE, $pricesData);
        try {
            $productRepository->save($product);
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(__('The tier_price data is invalid. Verify the data and try again.'));
        }
        return true;
    }
    
    /**
     * Around get list
     * 
     * @param \Magento\Catalog\Model\Product\TierPriceManagement $subject
     * @param \Closure $proceed
     * @param string $sku
     * @param string $customerGroupId
     * @return \Magento\Catalog\Api\Data\ProductTierPriceInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function aroundGetList(
        \Magento\Catalog\Model\Product\TierPriceManagement $subject,
        \Closure $proceed,
        $sku,
        $customerGroupId
    )
    {
        $this->setSubject($subject);
        $priceFactory = $this->getSubjectPropertyValue('priceFactory');
        $product = $this->getProduct($sku);
        if (!$this->priceScope->isGlobal()) {
            $priceKey = 'website_price';
        } else {
            $priceKey = 'price';
        }
        $castedCustomerGroupId = $this->castCustomerGroupId($customerGroupId);
        $prices = [];
        foreach ($product->getData(\Magento\Catalog\Api\Data\ProductInterface::TIER_PRICE) as $priceData) {
            if ($this->isPriceCustomerGroup($priceData, $customerGroupId)) {
                $prices[] = $priceFactory->create()
                    ->setValue($priceData[$priceKey])
                    ->setQty($priceData['price_qty'])
                    ->setCustomerGroupId($castedCustomerGroupId);
            }
        }
        return $prices;
    }
    
}