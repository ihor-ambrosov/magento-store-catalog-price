<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Ui\Catalog\DataProvider\Product;

/**
 * Product collection data provider plugin
 */
class ProductCollection extends \Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Collection
{
    
    /**
     * Product limitation join price
     * 
     * @return $this
     */
    protected function productLimitationJoinPrice()
    {
        $this->getSubjectPropertyValue('_productLimitationFilters')->setUsePriceIndex(false);
        parent::productLimitationPrice(true);
        return $this;
    }
    
}