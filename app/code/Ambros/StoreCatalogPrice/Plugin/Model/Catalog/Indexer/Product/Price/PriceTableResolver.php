<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price;

/**
 * Product price indexer price table resolver plugin
 */
class PriceTableResolver extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Around resolve
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver $subject
     * @param \Closure $proceed
     * @param string $index
     * @param array $dimensions
     * @return string
     */
    public function aroundResolve(
        \Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver $subject,
        \Closure $proceed,
        $index,
        array $dimensions
    )
    {
        $this->setSubject($subject);
        if ($index === 'ambros_store__catalog_product_index_price') {
            $dimensions = $this->invokeSubjectMethod('filterDimensions', $dimensions);
        }
        return $this->getSubjectPropertyValue('indexScopeResolver')->resolve($index, $dimensions);
    }
    
}