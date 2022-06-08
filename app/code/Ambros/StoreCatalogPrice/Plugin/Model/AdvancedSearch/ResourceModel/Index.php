<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\AdvancedSearch\ResourceModel;

/**
 * Advanced search index resource plugin
 */
class Index extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Get product price index data
     *
     * @param array $productIds
     * @param int $storeId
     * @return array
     */
    protected function getProductPriceIndexData(array $productIds, int $storeId): array
    {
        $subject = $this->getSubject();
        $connection = $subject->getConnection();
        $dimensionCollectionFactory = $this->getSubjectPropertyValue('dimensionCollectionFactory');
        $tableResolver = $this->getSubjectPropertyValue('tableResolver');
        $selects = [];
        foreach ($dimensionCollectionFactory->create() as $dimensions) {
            $storeDimension = $dimensions[\Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME] ?? null;
            if ($storeDimension === null || $storeId === null || $storeDimension->getValue() === $storeId) {
                $select = $connection->select()->from(
                    $tableResolver->resolve('ambros_store__catalog_product_index_price', $dimensions),
                    ['entity_id', 'customer_group_id', 'store_id', 'min_price']
                );
                if ($productIds) {
                    $select->where('entity_id IN (?)', $productIds);
                }
                $selects[] = $select;
            }
        }
        $data = [];
        foreach ($connection->fetchAll($connection->select()->union($selects)) as $row) {
            $data[$row['store_id']][$row['entity_id']][$row['customer_group_id']] = round($row['min_price'], 2);
        }
        return $data;
    }
    
    /**
     * Around get price index data
     * 
     * @param \Magento\AdvancedSearch\Model\ResourceModel\Index $subject
     * @param \Closure $proceed
     * @param array $productIds
     * @param int $storeId
     * @return array
     */
    public function aroundGetPriceIndexData(
        \Magento\AdvancedSearch\Model\ResourceModel\Index $subject,
        \Closure $proceed,
        $productIds,
        $storeId
    )
    {
        $this->setSubject($subject);
        $storeManager = $this->getSubjectPropertyValue('storeManager');
        $data = $this->getProductPriceIndexData((array) $productIds, (int) $storeManager->getStore($storeId)->getId());
        if (empty($data[$storeId])) {
            return [];
        }
        return $data[$storeId];
    }
    
}