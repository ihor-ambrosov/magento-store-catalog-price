<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Model\Store\Indexer;

/**
 * Store indexer dimension provider
 */
class StoreDimensionProvider implements \Magento\Framework\Indexer\DimensionProviderInterface
{
    
    /**
     * Dimension name
     */
    const DIMENSION_NAME = 'ws';
    
    /**
     * Collection factory
     * 
     * @var \Magento\Store\Model\ResourceModel\Store\CollectionFactory
     */
    protected $collectionFactory;
    
    /**
     * Dimension factory
     * 
     * @var \Magento\Framework\Indexer\DimensionFactory
     */
    protected $dimensionFactory;
    
    /**
     * Store IDs
     * 
     * @var array
     */
    protected $storeIds;
    
    /**
     * Constructor
     * 
     * @param \Magento\Store\Model\ResourceModel\Store\CollectionFactory $collectionFactory
     * @param \Magento\Framework\Indexer\DimensionFactory $dimensionFactory
     * @return void
     */
    public function __construct(
        \Magento\Store\Model\ResourceModel\Store\CollectionFactory $collectionFactory,
        \Magento\Framework\Indexer\DimensionFactory $dimensionFactory
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->dimensionFactory = $dimensionFactory;
    }
    
    /**
     * Get store IDs
     * 
     * @return array
     */
    protected function getStoreIds(): array
    {
        if ($this->storeIds !== null) {
            return $this->storeIds;
        }
        $storeIds = $this->collectionFactory->create()
            ->addFieldToFilter('code', ['neq' => \Magento\Store\Model\Store::ADMIN_CODE])
            ->getAllIds();
        return $this->storeIds = is_array($storeIds) ? $storeIds : [];
    }
    
    /**
     * Get iterator
     * 
     * @return \Magento\Framework\Indexer\Dimension[]|\Traversable
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->getStoreIds() as $storeId) {
            yield $this->dimensionFactory->create(static::DIMENSION_NAME, (string) $storeId);
        }
    }
    
}