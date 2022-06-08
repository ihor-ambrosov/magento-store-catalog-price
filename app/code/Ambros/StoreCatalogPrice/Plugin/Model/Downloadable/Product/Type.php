<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Downloadable\Product;

/**
 * Downloadable product type plugin
 */
class Type
{
    
    /**
     * Link collection factory
     * 
     * @var \Magento\Downloadable\Model\ResourceModel\Link\CollectionFactory
     */
    protected $linkCollectionFactory;
    
    /**
     * Extension attribute join processor
     * 
     * @var \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface 
     */
    protected $extensionAttributeJoinProcessor;
    
    /**
     * Constructor
     * 
     * @param \Magento\Downloadable\Model\ResourceModel\Link\CollectionFactory $linkCollectionFactory
     * @param \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface $extensionAttributeJoinProcessor
     * @return void
     */
    public function __construct(
        \Magento\Downloadable\Model\ResourceModel\Link\CollectionFactory $linkCollectionFactory,
        \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface $extensionAttributeJoinProcessor
    )
    {
        $this->linkCollectionFactory = $linkCollectionFactory;
        $this->extensionAttributeJoinProcessor = $extensionAttributeJoinProcessor;
    }
    
    /**
     * Around get links
     * 
     * @param \Magento\Downloadable\Model\Product\Type $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\Product $product
     * @return \Magento\Downloadable\Model\Link[]
     */
    public function aroundGetLinks(
        \Magento\Downloadable\Model\Product\Type $subject,
        \Closure $proceed,
        $product
    )
    {
        $links = $product->getDownloadableLinks();
        if ($links !== null) {
            return $links;
        }
        $storeId = (int) $product->getStoreId();
        $linkCollection = $this->linkCollectionFactory->create()
            ->addProductToFilter($product->getEntityId())
            ->addTitleToResult($storeId)
            ->addPriceToResult($storeId);
        $this->extensionAttributeJoinProcessor->process($linkCollection);
        $links = [];
        foreach ($linkCollection as $link) {
            $link->setProduct($product);
            $links[(int) $link->getId()] = $link;
        }
        $product->setDownloadableLinks($links);
        return $links;
    }
    
}