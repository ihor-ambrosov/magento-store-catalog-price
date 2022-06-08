<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Indexer;

/**
 * Linked product select builder by index price plugin
 */
class LinkedProductSelectBuilderByIndexPrice extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\Common\Model\ResourceModel\ConnectionProvider
     */
    protected $connectionProvider;
    
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
     * @param \Ambros\Common\Model\ResourceModel\ConnectionProvider $connectionProvider
     * @param \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\Common\Model\ResourceModel\ConnectionProvider $connectionProvider,
        \Ambros\StoreCatalogPrice\Model\Catalog\Indexer\Product\Price\PriceIndexTableResolver $priceIndexTableResolver,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        parent::__construct($wrapperFactory);
        $this->connectionProvider = $connectionProvider;
        $this->priceIndexTableResolver = $priceIndexTableResolver;
        $this->priceScope = $priceScope;
    }
    
    /**
     * Around build
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\LinkedProductSelectBuilderByIndexPrice $subject
     * @param \Closure $proceed
     * @param int $productId
     * @param int|null $storeId
     * @return \Magento\Framework\DB\Select[]
     */
    public function aroundBuild(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\LinkedProductSelectBuilderByIndexPrice $subject,
        \Closure $proceed,
        $productId,
        $storeId = null
    )
    {
        $this->setSubject($subject);
        $baseSelectProcessor = $this->getSubjectPropertyValue('baseSelectProcessor');
        $customerSession = $this->getSubjectPropertyValue('customerSession');
        $productTable = $this->connectionProvider->getTable('catalog_product_entity');
        $productTableAlias = \Magento\Catalog\Model\ResourceModel\Product\BaseSelectProcessorInterface::PRODUCT_TABLE_ALIAS;
        $priceStoreId = $this->priceScope->getStoreId($storeId);
        $customerGroupId = (int) $customerSession->getCustomerGroupId();
        return [
            $baseSelectProcessor->process(
                $this->connectionProvider->getSelect()
                    ->from(['parent' => $productTable], '')
                    ->joinInner(
                        ['link' => $this->connectionProvider->getTable('catalog_product_relation')],
                        'link.parent_id = parent.entity_id',
                        []
                    )
                    ->joinInner(
                        [$productTableAlias => $productTable],
                        $productTableAlias.'.entity_id = link.child_id',
                        ['entity_id']
                    )
                    ->joinInner(
                        ['t' => $this->priceIndexTableResolver->resolve($priceStoreId, $customerGroupId)],
                        't.entity_id = '.$productTableAlias.'.entity_id',
                        []
                    )
                    ->where('parent.entity_id = ?', $productId)
                    ->where('t.store_id = ?', $priceStoreId)
                    ->where('t.customer_group_id = ?', $customerGroupId)
                    ->order('t.min_price '.\Magento\Framework\DB\Select::SQL_ASC)
                    ->order($productTableAlias.'.entity_id '.\Magento\Framework\DB\Select::SQL_ASC)
                    ->limit(1)
            )
        ];
    }
    
}