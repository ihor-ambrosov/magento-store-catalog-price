<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Option;

/**
 * Product option value resource plugin
 */
class Value extends \Ambros\Common\Plugin\Model\Framework\ResourceModel\Db\AbstractDb
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
     * Delete price by store
     * 
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param int $storeId
     * @return $this
     */
    protected function deletePriceByStore(\Magento\Framework\Model\AbstractModel $object, int $storeId)
    {
        $subject = $this->getSubject();
        $subject->getConnection()->delete(
            $subject->getTable('catalog_product_option_type_price'),
            ['option_type_id = ?' => (int) $object->getId(), 'store_id = ?' => $storeId]
        );
        return $this;
    }
    
    /**
     * Save price by store
     * 
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param int $storeId
     * @param float|null $newPrice
     * @return $this
     */
    protected function savePriceByStore(\Magento\Framework\Model\AbstractModel $object, int $storeId, float $newPrice = null)
    {
        $subject = $this->getSubject();
        $connection = $subject->getConnection();
        $priceTable = $subject->getTable('catalog_product_option_type_price');
        $price = $newPrice === null ? (float) $object->getPrice() : $newPrice;
        $optionTypeId = $connection->fetchOne(
            $connection->select()->from($priceTable, 'option_type_id')
                ->where('option_type_id = ?', (int) $object->getId())
                ->where('store_id = ?', $storeId)
        );
        if (!$optionTypeId) {
            $data = $this->invokeSubjectMethod(
                '_prepareDataForTable',
                new \Magento\Framework\DataObject([
                    'option_type_id' => (int) $object->getId(),
                    'store_id' => $storeId,
                    'price' => $price,
                    'price_type' => $object->getPriceType(),
                ]),
                $priceTable
            );
            $connection->insert($priceTable, $data);
        } else {
            if (!$storeId && $object->getStoreId()) {
                return $this;
            }
            $data = $this->invokeSubjectMethod(
                '_prepareDataForTable',
                new \Magento\Framework\DataObject([
                    'price' => $price,
                    'price_type' => $object->getPriceType()
                ]),
                $priceTable
            );
            $connection->update($priceTable, $data, ['option_type_id = ?' => $optionTypeId, 'store_id  = ?' => $storeId]);
        }
        return $this;
    }
    
    /**
     * Save value prices
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    protected function saveValuePrices(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($object->getPrice() !== null && $object->getPriceType()) {
            $this->savePriceByStore($object, \Magento\Store\Model\Store::DEFAULT_STORE_ID);
        }
        if ($this->priceScope->isGlobal()) {
            return $this;
        }
        $storeIds = $this->priceScope->getStoreIds((int) $object->getStoreId());
        if ($object->getPrice() !== null && $object->getPriceType() && $object->getStoreId()) {
            foreach ($storeIds as $storeId) {
                $this->savePriceByStore($object, (int) $storeId, (float) $object->getPrice());
            }
        } else if ($object->getPrice() === null && !$object->getPriceType()) {
            foreach ($storeIds as $storeId) {
                $this->deletePriceByStore($object, (int) $storeId);
            }
        }
        return $this;
    }
    
    /**
     * After save
     *
     * @param \Magento\Framework\Model\AbstractModel|\Magento\Framework\DataObject $object
     * @return $this
     */
    protected function afterSave(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->saveValuePrices($object);
        $this->invokeSubjectMethod('_saveValueTitles', $object);
        $this->invokeSubjectParentMethod(\Magento\Framework\Model\ResourceModel\Db\AbstractDb::class, '_afterSave', $object);
        return $this;
    }
    
}