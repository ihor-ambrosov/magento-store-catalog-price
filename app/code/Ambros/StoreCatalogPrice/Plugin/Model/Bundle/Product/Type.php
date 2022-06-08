<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Bundle\Product;

/**
 * Bundle product type plugin
 */
class Type extends \Ambros\Common\Plugin\Plugin
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
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceScope = $priceScope;
    }
    
    /**
     * Around get selections collection
     * 
     * @param \Magento\Bundle\Model\Product\Type $subject
     * @param \Closure $proceed
     * @param array $optionIds
     * @param \Magento\Catalog\Model\Product $product
     * @return \Magento\Bundle\Model\ResourceModel\Selection\Collection
     */
    public function aroundGetSelectionsCollection(
        \Magento\Bundle\Model\Product\Type $subject,
        \Closure $proceed,
        $optionIds,
        $product
    )
    {
        $this->setSubject($subject);
        $storeId = (int) $product->getStoreId();
        $collection = $this->getSubjectPropertyValue('_bundleCollection')->create();
        $collection
            ->addAttributeToSelect($this->getSubjectPropertyValue('_config')->getProductAttributes())
            ->addAttributeToSelect('tax_class_id')
            ->setFlag('product_children', true)
            ->setPositionOrder()
            ->addStoreFilter($subject->getStoreFilter($product))
            ->setStoreId($storeId)
            ->addFilterByRequiredOptions()
            ->setOptionIdsFilter($optionIds);
        $this->getSubjectPropertyValue('selectionCollectionFilterApplier')
            ->apply($collection, 'parent_product_id', $product->getData('entity_id'));
        if (!$this->priceScope->isGlobal() && $storeId) {
            $collection->joinPrices($storeId);
        }
        return $collection;
    }
    
    /**
     * Around get selections by IDs
     * 
     * @param \Magento\Bundle\Model\Product\Type $subject
     * @param \Closure $proceed
     * @param array $selectionIds
     * @param \Magento\Catalog\Model\Product $product
     * @return \Magento\Bundle\Model\ResourceModel\Selection\Collection
     */
    public function aroundGetSelectionsByIds(
        \Magento\Bundle\Model\Product\Type $subject,
        \Closure $proceed,
        $selectionIds,
        $product
    )
    {
        $this->setSubject($subject);
        sort($selectionIds);
        $keyUsedSelections = $this->getSubjectPropertyValue('_keyUsedSelections');
        $keyUsedSelectionsIds = $this->getSubjectPropertyValue('_keyUsedSelectionsIds');
        $usedSelections = $product->getData($keyUsedSelections);
        $usedSelectionsIds = $product->getData($keyUsedSelectionsIds);
        if (!$usedSelections || $usedSelectionsIds !== $selectionIds) {
            $storeId = (int) $product->getStoreId();
            $usedSelections = $this->getSubjectPropertyValue('_bundleCollection')->create();
            $usedSelections
                ->addAttributeToSelect('*')
                ->setFlag('product_children', true)
                ->addStoreFilter($subject->getStoreFilter($product))
                ->setStoreId($storeId)
                ->setPositionOrder()
                ->addFilterByRequiredOptions()
                ->setSelectionIdsFilter($selectionIds);
            $this->getSubjectPropertyValue('selectionCollectionFilterApplier')
                ->apply($usedSelections, 'parent_product_id', $product->getData('entity_id'));
            if (!$this->priceScope->isGlobal() && $storeId) {
                $usedSelections->joinPrices($storeId);
            }
            $product->setData($keyUsedSelections, $usedSelections);
            $product->setData($keyUsedSelectionsIds, $selectionIds);
        }
        return $usedSelections;
    }
    
}