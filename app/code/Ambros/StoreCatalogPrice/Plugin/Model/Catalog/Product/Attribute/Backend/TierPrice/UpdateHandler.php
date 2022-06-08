<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Attribute\Backend\TierPrice;

/**
 * Product tier price attribute backend update handler plugin
 */
class UpdateHandler extends \Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Product\Attribute\Backend\TierPrice\AbstractHandler
{
    
    /**
     * Get price data key
     * 
     * @param array $priceData
     * @return string
     */
    protected function getPriceDataKey(array $priceData): string
    {
        return implode(
            '-',
            array_merge(
                [$priceData['store_id'], $priceData['cust_group']],
                [$this->invokeSubjectMethod('parseQty', $priceData['price_qty'])]
            )
        );
    }
    
    /**
     * Prepare orig prices data
     * 
     * @param array|null $pricesData
     * @return array
     */
    protected function prepareOrigPricesData($pricesData): array
    {
        $preparedPricesData = [];
        if (!is_array($pricesData)) {
            return $preparedPricesData;
        }
        foreach ($pricesData as $priceData) {
            $preparedPricesData[$this->getPriceDataKey($priceData)] = $priceData;
        }
        return $preparedPricesData;
    }
    
    /**
     * Prepare prices data
     *
     * @param array $pricesData
     * @param bool $isGlobal
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function preparePricesData(array $pricesData, bool $isGlobal = true): array
    {
        $preparedPricesData = [];
        foreach ($this->filterPricesData($pricesData) as $priceData) {
            if (
                empty($priceData['delete']) && 
                (
                    !empty($priceData['price_qty']) || 
                    isset($priceData['cust_group']) || 
                    $isGlobal === ((int) $priceData['store_id'] === 0)
                )
            ) {
                $preparedPricesData[$this->getPriceDataKey($priceData)] = $this->preparePriceData($priceData);
            }
        }
        return $preparedPricesData;
    }
    
    /**
     * Around execute
     * 
     * @param \Magento\Catalog\Model\Product\Attribute\Backend\TierPrice\UpdateHandler $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Api\Data\ProductInterface|object $entity
     * @param array $arguments
     * @return \Magento\Catalog\Api\Data\ProductInterface|object
     * @throws \Magento\Framework\Exception\InputException
     */
    public function aroundExecute(
        \Magento\Catalog\Model\Product\Attribute\Backend\TierPrice\UpdateHandler $subject,
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
        $preparedPricesData = $this->preparePricesData($pricesData, $isGlobal);
        $preparedOrigPricesData = $this->prepareOrigPricesData($entity->getOrigData($attribute->getName()));
        $pricesDataToDelete = array_diff_key($preparedOrigPricesData, $preparedPricesData);
        $pricesDataToInsert = array_diff_key($preparedPricesData, $preparedOrigPricesData);
        $pricesDataToUpdate = array_intersect_key($preparedPricesData, $preparedOrigPricesData);
        $isChanged = $this->invokeSubjectMethod('deleteValues', $productId, $pricesDataToDelete);
        $isChanged |= $this->invokeSubjectMethod('insertValues', $productId, $pricesDataToInsert);
        $isChanged |= $this->invokeSubjectMethod('updateValues', $pricesDataToUpdate, $preparedOrigPricesData);
        if ($isChanged) {
            $entity->setData($attribute->getName().'_changed', 1);
        }
        return $entity;
    }
    
}