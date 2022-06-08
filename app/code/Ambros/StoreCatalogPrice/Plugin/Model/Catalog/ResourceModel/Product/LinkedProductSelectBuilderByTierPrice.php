<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product;

/**
 * Linked product select builder by tier price plugin
 */
class LinkedProductSelectBuilderByTierPrice
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\Common\Model\ResourceModel\ConnectionProvider
     */
    protected $connectionProvider;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * Customer session
     * 
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Price scope
     * 
     * @var \Magento\Catalog\Helper\Data
     */
    protected $priceScope;

    /**
     * Base select processor
     * 
     * @var \Magento\Catalog\Model\ResourceModel\Product\BaseSelectProcessorInterface
     */
    protected $baseSelectProcessor;

    /**
     * Constructor
     * 
     * @param \Ambros\Common\Model\ResourceModel\ConnectionProvider $connectionProvider
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Magento\Framework\EntityManager\MetadataPool $metadataPool
     * @param \Magento\Catalog\Model\ResourceModel\Product\BaseSelectProcessorInterface $baseSelectProcessor
     * @return void
     */
    public function __construct(
        \Ambros\Common\Model\ResourceModel\ConnectionProvider $connectionProvider,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Magento\Catalog\Model\ResourceModel\Product\BaseSelectProcessorInterface $baseSelectProcessor
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->priceScope = $priceScope;
        $this->baseSelectProcessor = $baseSelectProcessor;
    }
    
    /**
     * Around build
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\LinkedProductSelectBuilderByTierPrice $subject
     * @param \Closure $proceed
     * @param int $productId
     * @param int|null $storeId
     * @return \Magento\Framework\DB\Select[]
     */
    public function aroundBuild(
        \Magento\Catalog\Model\ResourceModel\Product\LinkedProductSelectBuilderByTierPrice $subject,
        \Closure $proceed,
        $productId,
        $storeId = null
    )
    {
        $productTable = $this->connectionProvider->getTable('catalog_product_entity');
        $productTableAlias = \Magento\Catalog\Model\ResourceModel\Product\BaseSelectProcessorInterface::PRODUCT_TABLE_ALIAS;
        $priceSelect = $this->baseSelectProcessor->process(
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
                    ['t' => $this->connectionProvider->getTable('ambros_store__catalog_product_entity_tier_price')],
                    't.entity_id = '.$productTableAlias.'.entity_id',
                    []
                )
                ->where('parent.entity_id = ?', $productId)
                ->where('t.all_groups = 1 OR customer_group_id = ?', $this->customerSession->getCustomerGroupId())
                ->where('t.qty = ?', 1)
                ->order('t.value '.\Magento\Framework\DB\Select::SQL_ASC)
                ->order($productTableAlias.'.entity_id '.\Magento\Framework\DB\Select::SQL_ASC)
                ->limit(1)
        );
        $selects = [];
        $priceStoreId = $this->priceScope->getStoreId($storeId);
        if ($priceStoreId) {
            $priceSelectStore = clone $priceSelect;
            $priceSelectStore->where('t.store_id = ?', $priceStoreId);
            $selects[] = $priceSelectStore;
        }
        $priceSelect->where('t.store_id = ?', \Magento\Store\Model\Store::DEFAULT_STORE_ID);
        $selects[] = $priceSelect;
        return $selects;
    }
    
}