<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\CatalogSearch\Adapter\Mysql\Dynamic;

/**
 * Catalog search dynamic data provider plugin
 */
class DataProvider extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price index table resolver
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver
     */
    protected $priceIndexTableResolver;
    
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
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceIndexTableResolver = $priceIndexTableResolver;
        $this->priceScope = $priceScope;
    }
    
    /**
     * Around get aggregations
     * 
     * @param \Magento\CatalogSearch\Model\Adapter\Mysql\Dynamic\DataProvider $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Search\Dynamic\EntityStorage $entityStorage
     * @return array
     */
    public function aroundGetAggregations(
        $subject,
        \Closure $proceed,
        \Magento\Framework\Search\Dynamic\EntityStorage $entityStorage
    )
    {
        $this->setSubject($subject);
        $connection = $this->getSubjectPropertyValue('connection');
        $customerSession = $this->getSubjectPropertyValue('customerSession');
        $storeId = $this->priceScope->getStoreId();
        $customerGroupId = (int) $customerSession->getCustomerGroupId();
        return $connection->fetchRow(
            $connection->select()
                ->from(['main_table' => $this->priceIndexTableResolver->resolve($storeId, $customerGroupId)], [])
                ->where('main_table.entity_id in (select entity_id from '.$entityStorage->getSource()->getName().')')
                ->columns([
                    'count' => 'count(main_table.entity_id)',
                    'max' => 'MAX(min_price)',
                    'min' => 'MIN(min_price)',
                    'std' => 'STDDEV_SAMP(min_price)',
                ])
                ->where('customer_group_id = ?', $customerGroupId)
                ->where('main_table.store_id = ?', $storeId)
        );
    }
    
}