<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Option;

/**
 * Product option collection plugin
 */
class Collection
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\Common\Model\ResourceModel\ConnectionProvider
     */
    protected $connectionProvider;
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\Model\ResourceModel\ConnectionProvider $connectionProvider
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\Model\ResourceModel\ConnectionProvider $connectionProvider,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->priceScope = $priceScope;
    }
    
    /**
     * Around add price to result
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Option\Collection $subject
     * @param \Closure $proceed
     * @param int $storeId
     * @return \Magento\Catalog\Model\ResourceModel\Product\Option\Collection
     */
    public function aroundAddPriceToResult(
        \Magento\Catalog\Model\ResourceModel\Product\Option\Collection $subject,
        \Closure $proceed,
        $storeId
    )
    {
        $select = $subject->getSelect();
        $connection = $this->connectionProvider->getConnection();
        $priceTable = $subject->getTable('catalog_product_option_price');
        $defaultPriceTableAlias = 'default_option_price';
        $defaultPrice = $defaultPriceTableAlias.'.price';
        $defaultPriceType = $defaultPriceTableAlias.'.price_type';
        $columns = ['default_price' => $defaultPrice, 'default_price_type' => $defaultPriceType, 'price' => $defaultPrice, 'price_type' => $defaultPriceType];
        $select->joinLeft(
            [$defaultPriceTableAlias => $priceTable],
            $this->connectionProvider->getCondition([
                $defaultPriceTableAlias.'.option_id = main_table.option_id',
                $connection->quoteInto($defaultPriceTableAlias.'.store_id = ?', \Magento\Store\Model\Store::DEFAULT_STORE_ID),
            ], 'AND'),
            []
        );
        if (!$this->priceScope->isGlobal()) {
            $storePriceTableAlias = 'store_option_price';
            $storePrice = $storePriceTableAlias.'.price';
            $storePriceType = $storePriceTableAlias.'.price_type';
            $price = $this->connectionProvider->getIfNullSql($storePrice, $defaultPrice);
            $priceType = $this->connectionProvider->getIfNullSql($storePriceType, $defaultPriceType);
            $select->joinLeft(
                [$storePriceTableAlias => $priceTable],
                $this->connectionProvider->getCondition([
                    $storePriceTableAlias.'.option_id = main_table.option_id',
                    $connection->quoteInto($storePriceTableAlias.'.store_id = ?', $this->priceScope->getStoreId((int) $storeId)),
                ], 'AND'),
                []
            );
            $columns = array_merge($columns, ['store_price' => $storePrice, 'store_price_type' => $storePriceType, 'price' => $price, 'price_type' => $priceType]);
        }
        $select->columns($columns);
        return $subject;
    }
    
}