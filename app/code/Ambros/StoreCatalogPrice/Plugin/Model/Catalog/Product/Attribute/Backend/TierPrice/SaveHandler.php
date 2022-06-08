<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Attribute\Backend\TierPrice;

/**
 * Product tier price attribute back-end save handler plugin
 */
class SaveHandler extends \Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Attribute\Backend\TierPrice\AbstractHandler
{
    
    /**
     * Around execute
     * 
     * @param \Magento\Catalog\Model\Product\Attribute\Backend\TierPrice\SaveHandler $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Api\Data\ProductInterface|object $entity
     * @param array $arguments
     * @return \Magento\Catalog\Api\Data\ProductInterface|object
     * @throws \Magento\Framework\Exception\InputException
     */
    public function aroundExecute(
        \Magento\Catalog\Model\Product\Attribute\Backend\TierPrice\SaveHandler $subject,
        \Closure $proceed,
        $entity,
        $arguments = []
    )
    {
        $this->setSubject($subject);
        $attribute = $this->getAttribute();
        $pricesData = $this->getPricesData($entity);
        if ($pricesData === null) {
            return $entity;
        }
        $this->validatePricesData($pricesData);
        $storeId = $this->getStoreId($entity);
        $isGlobal = $attribute->isScopeGlobal() || $storeId === 0;
        $productId = $this->getProductId($entity);
        $tierPriceResource = $this->getSubjectPropertyValue('tierPriceResource');
        foreach ($this->filterPricesData($pricesData) as $priceData) {
            $isPriceStoreGlobal = (int) $priceData['store_id'] === 0;
            if (
                $isGlobal === $isPriceStoreGlobal || 
                !empty($priceData['price_qty']) || 
                isset($priceData['cust_group'])
            ) {
                $price = $this->dataObjectFactory->create(['data' => $this->preparePriceData($priceData)]);
                $price->setData('entity_id', $productId);
                $tierPriceResource->savePriceData($price);
                $entity->setData($attribute->getName().'_changed', 1);
            }
        }
        return $entity;
    }
    
}