<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product;

/**
 * Product option resource plugin
 */
class Option extends \Ambros\Common\Plugin\Model\Framework\ResourceModel\Db\AbstractDb
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
            $subject->getTable('catalog_product_option_price'),
            ['option_id = ?' => (int) $object->getId(), 'store_id = ?' => $storeId]
        );
        return $this;
    }
    
    /**
     * Save price by store
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param int $storeId
     * @param float|null $newPrice
     */
    protected function savePriceByStore(\Magento\Framework\Model\AbstractModel $object, int $storeId, float $newPrice = null): void
    {
        $subject = $this->getSubject();
        $connection = $subject->getConnection();
        $priceTable = $subject->getTable('catalog_product_option_price');
        $price = $newPrice === null ? $object->getPrice() : $newPrice;
        $optionId = $connection->fetchOne(
            $connection->select()->from($priceTable, 'option_id')
            ->where('option_id = ?', $object->getId())
            ->where('store_id = ?', $storeId)
        );
        if (!$optionId) {
            $data = $this->invokeSubjectMethod(
                '_prepareDataForTable',
                new \Magento\Framework\DataObject([
                    'option_id' => $object->getId(),
                    'store_id' => $storeId,
                    'price' => $price,
                    'price_type' => $object->getPriceType(),
                ]),
                $priceTable
            );
            $connection->insert($priceTable, $data);
        } else {
            if ($storeId === \Magento\Store\Model\Store::DEFAULT_STORE_ID && (int) $object->getStoreId() !== $storeId) {
                return;
            }
            $data = $this->invokeSubjectMethod(
                '_prepareDataForTable',
                new \Magento\Framework\DataObject(['price' => $price, 'price_type' => $object->getPriceType()]),
                $priceTable
            );
            $connection->update($priceTable, $data, ['option_id = ?' => $object->getId(), 'store_id = ?' => $storeId]);
        }
    }
    
    /**
     * Save value prices
     * 
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    protected function saveValuePrices(\Magento\Framework\Model\AbstractModel $object)
    {
        if (!in_array($object->getType(), $this->getSubject()->getPriceTypes())) {
            return $this;
        }
        if (!$object->getData('scope', 'price')) {
            $this->savePriceByStore($object, \Magento\Store\Model\Store::DEFAULT_STORE_ID);
        }
        if ($this->priceScope->isGlobal()) {
            return $this;
        }
        $storeIds = $this->priceScope->getStoreIds((int) $object->getStoreId());
        if ($object->getStoreId()) {
            foreach ($storeIds as $storeId) {
                $this->savePriceByStore($object, (int) $storeId, (float) $object->getPrice());
            }
        } elseif ($object->getData('scope', 'price')) {
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