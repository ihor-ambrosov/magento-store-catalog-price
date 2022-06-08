<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Elasticsearch\Adapter\BatchDataMapper;

/**
 * Price fields provider plugin
 */
class PriceFieldsProvider extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Around get fields
     * 
     * @param \Magento\Elasticsearch\Model\Adapter\BatchDataMapper\PriceFieldsProvider $subject
     * @param \Closure $proceed
     * @param array $productIds
     * @param int $storeId
     * @return array
     */
    public function aroundGetFields(
        \Magento\Elasticsearch\Model\Adapter\BatchDataMapper\PriceFieldsProvider $subject,
        \Closure $proceed,
        array $productIds,
        $storeId
    )
    {
        $this->setSubject($subject);
        $dataProvider = $this->getSubjectPropertyValue('dataProvider');
        $resourceIndex = $this->getSubjectPropertyValue('resourceIndex');
        $priceData = $dataProvider->getSearchableAttribute('price') ? $resourceIndex->getPriceIndexData($productIds, $storeId) : [];
        $fields = [];
        foreach ($productIds as $productId) {
            $fields[$productId] = $this->invokeSubjectMethod('getProductPriceData', $productId, $storeId, $priceData);
        }
        return $fields;
    }
    
}