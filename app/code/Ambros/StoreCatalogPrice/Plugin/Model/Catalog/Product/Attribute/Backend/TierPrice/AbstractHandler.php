<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Attribute\Backend\TierPrice;

/**
 * Product tier price attribute back-end abstract handler plugin
 */
class AbstractHandler extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope 
     */
    protected $priceScope;
    
    /**
     * Data object factory
     * 
     * @var \Magento\Framework\DataObjectFactory
     */
    protected $dataObjectFactory;
    
    /**
     * Attribute
     * 
     * @var \Magento\Catalog\Api\Data\ProductAttributeInterface
     */
    protected $attribute;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Magento\Framework\DataObjectFactory $dataObjectFactory
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Magento\Framework\DataObjectFactory $dataObjectFactory
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceScope = $priceScope;
        $this->dataObjectFactory = $dataObjectFactory;
    }
    
    /**
     * Get attribute
     * 
     * @return \Magento\Catalog\Api\Data\ProductAttributeInterface
     */
    protected function getAttribute(): \Magento\Catalog\Api\Data\ProductAttributeInterface
    {
        if ($this->attribute !== null) {
            return $this->attribute;
        }
        return $this->attribute = $this->getSubjectPropertyValue('attributeRepository')
            ->get(\Magento\Catalog\Api\Data\ProductAttributeInterface::CODE_TIER_PRICE);
    }
    
    /**
     * Get prices data
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $entity
     * @return array|null
     */
    protected function getPricesData(\Magento\Catalog\Api\Data\ProductInterface $entity): ?array
    {
        return $entity->getData($this->getAttribute()->getName());
    }
    
    /**
     * Get product ID
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $entity
     * @return int
     */
    protected function getProductId(\Magento\Catalog\Api\Data\ProductInterface $entity): int
    {
        return (int) $entity->getData('entity_id');
    }
    
    /**
     * Get store ID
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $entity
     * @return int
     */
    protected function getStoreId(\Magento\Catalog\Api\Data\ProductInterface $entity): int
    {
        return (int) $this->getSubjectPropertyValue('storeManager')->getStore($entity->getStoreId())->getId();
    }
    
    /**
     * Validate prices data
     * 
     * @param mixed $pricesData
     * @return $this
     * @throws \Magento\Framework\Exception\InputException
     */
    protected function validatePricesData($pricesData)
    {
        if (!is_array($pricesData)) {
            throw new \Magento\Framework\Exception\InputException(
                __('Tier prices data should be array, but actually other type is received')
            );
        }
        return $this;
    }
    
    /**
     * Prepare price data
     * 
     * @param array $priceData
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function preparePriceData(array $priceData): array
    {
        $allCustomerGroups = (int) $priceData['cust_group'] === $this->getSubjectPropertyValue('groupManagement')
            ->getAllCustomersGroup()->getId();
        $customerGroupId = $allCustomerGroups ? 0 : $priceData['cust_group'];
        return array_merge($this->invokeSubjectMethod('getAdditionalFields', $priceData), [
            'store_id' => $priceData['store_id'],
            'all_groups' => $allCustomerGroups,
            'customer_group_id' => $customerGroupId,
            'value' => $priceData['price'] ?? null,
            'qty' => $this->invokeSubjectMethod('parseQty', $priceData['price_qty']),
        ]);
    }
    
    /**
     * Filter prices data
     *
     * @param array $pricesData
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function filterPricesData(array $pricesData): array
    {
        $filteredPricesData = [];
        foreach (array_filter($pricesData) as $priceData) {
            $priceDataStoreId = (int) $priceData['store_id'];
            if ($priceDataStoreId) {
                foreach ($this->priceScope->getStoreIds($priceDataStoreId) as $storeId) {
                    $filteredPricesData[] = array_merge($priceData, ['store_id' => $storeId]);
                }
            } else {
                $filteredPricesData[] = $priceData;
            }
        }
        return $filteredPricesData;
    }
    
}