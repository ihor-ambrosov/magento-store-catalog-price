<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Downloadable\ResourceModel\Link;

/**
 * Downloadable product link collection plugin
 */
class Collection
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
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        $this->priceScope = $priceScope;
    }
    
    /**
     * Around add price to result
     * 
     * @param \Magento\Downloadable\Model\ResourceModel\Link\Collection $subject
     * @param \Closure $proceed
     * @param int $storeId
     * @return \Magento\Downloadable\Model\ResourceModel\Link\Collection
     */
    public function aroundAddPriceToResult(
        \Magento\Downloadable\Model\ResourceModel\Link\Collection $subject,
        \Closure $proceed,
        $storeId
    )
    {
        $select = $subject->getSelect();
        $connection = $subject->getConnection();
        $columns = ['default_price' => 'dp.price', 'price' => 'dp.price'];
        $select->joinLeft(
            ['dp' => $subject->getTable('ambros_store__downloadable_link_price')],
            'dp.link_id = main_table.link_id AND dp.store_id = 0',
            []
        );
        if (!$this->priceScope->isGlobal()) {
            $select->joinLeft(
                ['stp' => $subject->getTable('ambros_store__downloadable_link_price')],
                'stp.link_id = main_table.link_id AND stp.store_id = '.$this->priceScope->getStoreId((int) $storeId),
                []
            );
            $columns = array_merge($columns, ['website_price' => 'stp.price', 'price' => $connection->getIfNullSql('stp.price', 'dp.price')]);
        }
        $select->columns($columns);
        return $subject;
    }
    
}