<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Bundle\ResourceModel\Selection;

/**
 * Bundle selection collection plugin
 */
class Collection extends \Ambros\Common\Plugin\Plugin
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
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\Common\Model\ResourceModel\ConnectionProvider $connectionProvider
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\Common\Model\ResourceModel\ConnectionProvider $connectionProvider,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        parent::__construct($wrapperFactory);
        $this->connectionProvider = $connectionProvider;
        $this->priceScope = $priceScope;
    }
    
    /**
     * Around join prices
     * 
     * @param \Magento\Bundle\Model\ResourceModel\Selection\Collection $subject
     * @param \Closure $proceed
     * @param int $storeId
     * @return \Magento\Bundle\Model\ResourceModel\Selection\Collection
     */
    public function aroundJoinPrices(
        \Magento\Bundle\Model\ResourceModel\Selection\Collection $subject,
        \Closure $proceed,
        $storeId
    )
    {
        $this->setSubject($subject);
        $select = $subject->getSelect();
        $columns = [
            'selection_price_type' => 'selection.selection_price_type',
            'selection_price_value' => 'selection.selection_price_value',
            'parent_product_id' => 'price.parent_product_id',
            'price_scope' => 'price.store_id'
        ];
        if (!$this->priceScope->isGlobal()) {
            $select->joinLeft(
                ['price' => $subject->getTable('ambros_store__catalog_product_bundle_selection_price')],
                $this->connectionProvider->getCondition([
                    'selection.selection_id = price.selection_id',
                    'price.store_id = '.$this->priceScope->getStoreId((int) $storeId),
                    'selection.parent_product_id = price.parent_product_id',
                ], 'AND'),
                []
            );
            $columns['selection_price_type'] = $this->connectionProvider->getCheckSql(
                'price.selection_price_type IS NOT NULL',
                'price.selection_price_type',
                'selection.selection_price_type'
            );
            $columns['selection_price_value'] = $this->connectionProvider->getCheckSql(
                'price.selection_price_value IS NOT NULL',
                'price.selection_price_value',
                'selection.selection_price_value'
            );
        }
        $select->columns($columns);
        $this->setSubjectPropertyValue('websiteScopePriceJoined', true);
        return $subject;
    }
    
    /**
     * Around add price filter
     * 
     * @param \Magento\Bundle\Model\ResourceModel\Selection\Collection $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $product
     * @param bool $searchMin
     * @param bool $useRegularPrice
     * @return \Magento\Bundle\Model\ResourceModel\Selection\Collection
     */
    public function aroundAddPriceFilter(
        \Magento\Bundle\Model\ResourceModel\Selection\Collection $subject,
        \Closure $proceed,
        $product,
        $searchMin,
        $useRegularPrice = false
    )
    {
        $this->setSubject($subject);
        $select = $subject->getSelect();
        if ($product->getPriceType() == \Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC) {
            $subject->addPriceData();
            if ($useRegularPrice) {
                $minPrice = \Magento\Catalog\Model\ResourceModel\Product\Collection::INDEX_TABLE_ALIAS.'.price';
            } else {
                $this->invokeSubjectMethod('getCatalogRuleProcessor')->addPriceData($subject, 'selection.product_id');
                $minPrice = 'LEAST(minimal_price, IFNULL(catalog_rule_price, minimal_price))';
            }
            $orderByValue = $this->connectionProvider->getSql('('.$minPrice.' * selection.selection_qty)');
        } else {
            if (!$this->priceScope->isGlobal()) {
                $priceType = $this->connectionProvider->getIfNullSql('price.selection_price_type', 'selection.selection_price_type');
                $priceValue = $this->connectionProvider->getIfNullSql('price.selection_price_value', 'selection.selection_price_value');
            } else {
                $priceType = 'selection.selection_price_type';
                $priceValue = 'selection.selection_price_value';
            }
            if (!$this->getSubjectPropertyValue('websiteScopePriceJoined') && !$this->priceScope->isGlobal()) {
                $select->joinLeft(
                    ['price' => $subject->getTable('ambros_store__catalog_product_bundle_selection_price')],
                    'selection.selection_id = price.selection_id AND price.store_id = '.$this->priceScope->getStoreId(),
                    []
                );
            }
            $price = $this->connectionProvider->getCheckSql($priceType.' = 1', ((string) (float) $product->getPrice()).' * '.$priceValue.' / 100', (string) $priceValue);
            $orderByValue = $this->connectionProvider->getSql('('.$price.' * selection.selection_qty)');
        }
        $select->reset(\Magento\Framework\DB\Select::ORDER);
        $select->order($this->connectionProvider->getSql(
            $orderByValue.($searchMin ? \Magento\Framework\DB\Select::SQL_ASC : \Magento\Framework\DB\Select::SQL_DESC)
        ));
        $select->limit(1);
        return $subject;
    }
    
    /**
     * Around add store filter
     *
     * @param \Magento\Bundle\Model\ResourceModel\Selection\Collection $subject
     * @param \Closure $proceed
     * @param null|string|bool|int|Store $store
     * @return \Magento\Bundle\Model\ResourceModel\Selection\Collection
     */
    public function aroundAddStoreFilter(
        \Magento\Bundle\Model\ResourceModel\Selection\Collection $subject,
        \Closure $proceed,
        $store = null
    )
    {
        return $subject;
    }
    
}