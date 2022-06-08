<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Bundle\ResourceModel;

/**
 * Bundle selection resource plugin
 */
class Selection
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
     * Around save
     * 
     * @param \Magento\Bundle\Model\ResourceModel\Selection $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param \Magento\Bundle\Model\ResourceModel\Selection
     */
    public function aroundSave(
        \Magento\Bundle\Model\ResourceModel\Selection $subject,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $object
    )
    {
        if ($this->priceScope->isGlobal() || !$object->getStoreId()) {
            return $proceed($object);
        }
        $object->setStoreSelectionPriceValue($object->getSelectionPriceValue());
        $object->setStoreSelectionPriceType($object->getSelectionPriceType());
        if ($object->getSelectionId()) {
            $object->unsSelectionPriceValue();
            $object->unsSelectionPriceType();
        }
        return $proceed($object);
    }
    
    /**
     * Around save selection price
     * 
     * @param \Magento\Bundle\Model\ResourceModel\Selection $subject
     * @param \Closure $proceed
     * @param \Magento\Bundle\Model\Selection $item
     * @return void
     */
    public function aroundSaveSelectionPrice(
        \Magento\Bundle\Model\ResourceModel\Selection $subject,
        \Closure $proceed,
        $item
    )
    {
        $connection = $subject->getConnection();
        $storeId = $this->priceScope->getStoreId($item->getStoreId());
        if ($item->getDefaultPriceScope()) {
            $connection->delete(
                $subject->getTable('ambros_store__catalog_product_bundle_selection_price'),
                [
                    'selection_id = ?' => $item->getSelectionId(),
                    'store_id = ?' => $storeId,
                    'parent_product_id = ?' => $item->getParentProductId(),
                ]
            );
        } else {
            $connection->insertOnDuplicate(
                $subject->getTable('ambros_store__catalog_product_bundle_selection_price'),
                [
                    'selection_id' => $item->getSelectionId(),
                    'store_id' => $storeId,
                    'selection_price_type' => $item->getStoreSelectionPriceType(),
                    'selection_price_value' => $item->getStoreSelectionPriceValue(),
                    'parent_product_id' => $item->getParentProductId(),
                ],
                ['selection_price_type', 'selection_price_value']
            );
        }
    }
    
}