<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Downloadable\ResourceModel;

/**
 * Downloadable product link resource plugin
 */
class Link extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Store base currency
     * 
     * @var \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency
     */
    protected $storeBaseCurrency;
    
    /**
     * Store manager
     * 
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @param \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency $storeBaseCurrency
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope,
        \Ambros\StoreCatalogPrice\Model\Store\StoreBaseCurrency $storeBaseCurrency,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceScope = $priceScope;
        $this->storeBaseCurrency = $storeBaseCurrency;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Save item title
     * 
     * @param \Magento\Downloadable\Model\Link $linkObject
     * @return $this
     */
    protected function saveItemTitle($linkObject)
    {
        $subject = $this->getSubject();
        $connection = $subject->getConnection();
        $table = $subject->getTable('downloadable_link_title');
        $linkId = (int) $linkObject->getId();
        $storeId = (int) $linkObject->getStoreId();
        $title = $linkObject->getTitle();
        $useDefault = $linkObject->getUseDefaultTitle();
        if (
            $connection->fetchOne(
                $connection->select()->from($table)->where('link_id = :link_id AND store_id = :store_id'),
                [':link_id' => $linkId, ':store_id' => $storeId]
            )
        ) {
            $where = ['link_id = ?' => $linkId, 'store_id = ?' => $storeId];
            if ($useDefault) {
                $connection->delete($table, $where);
            } else {
                $connection->update($table, ['title' => $title], $where);
            }
        } else if (!$useDefault) {
            $connection->insert($table, ['link_id' => $linkId, 'store_id' => $storeId, 'title' => $title]);
        }
        return $this;
    }
    
    /**
     * Save item price
     * 
     * @param \Magento\Downloadable\Model\Link $linkObject
     * @return $this
     */
    protected function saveItemPrice($linkObject)
    {
        $subject = $this->getSubject();
        $connection = $subject->getConnection();
        $table = $subject->getTable('ambros_store__downloadable_link_price');
        $linkId = (int) $linkObject->getId();
        $price = (double) $linkObject->getPrice();
        $useDefault = $linkObject->getUseDefaultPrice();
        if ($this->priceScope->isGlobal() || !$linkObject->getStoreId()) {
            $storeIds = [0];
        } else {
            $storeIds = $this->priceScope->getStoreIds((int) $linkObject->getStoreId());
        }
        foreach ($storeIds as $storeId) {
            if (
                $connection->fetchOne(
                    $connection->select()->from($table)->where('link_id = :link_id AND store_id = :store_id'),
                    [':link_id' => $linkId, ':store_id' => $storeId]
                )
            ) {
                $where = ['link_id = ?' => $linkObject->getId(), 'store_id = ?' => $storeId];
                if ($useDefault) {
                    $connection->delete($table, $where);
                } else {
                    $connection->update($table, ['price' => $price], $where);
                }
            } else if (!$useDefault) {
                $connection->insert($table, ['link_id' => $linkId, 'store_id' => $storeId, 'price' => $price]);
            }
        }
        return $this;
    }
    
    /**
     * Around save item title and price
     * 
     * @param \Magento\Downloadable\Model\ResourceModel\Link $subject
     * @param \Closure $proceed
     * @param \Magento\Downloadable\Model\Link $linkObject
     * @return \Magento\Downloadable\Model\ResourceModel\Link
     */
    public function aroundSaveItemTitleAndPrice(
        \Magento\Downloadable\Model\ResourceModel\Link $subject,
        \Closure $proceed,
        $linkObject
    )
    {
        $this->setSubject($subject);
        $this->saveItemTitle($linkObject);
        $this->saveItemPrice($linkObject);
        return $subject;
    }
    
    /**
     * Around delete items
     * 
     * @param \Magento\Downloadable\Model\ResourceModel\Link\Collection $subject
     * @param \Closure $proceed
     * @param \Magento\Downloadable\Model\Link|array|int $items
     * @return $this
     */
    public function aroundDeleteItems(
        \Magento\Downloadable\Model\ResourceModel\Link\Collection $subject,
        \Closure $proceed,
        $items
    )
    {
        $this->setSubject($subject);
        $connection = $subject->getConnection();
        if ($items instanceof \Magento\Downloadable\Model\Link) {
            $where = ['link_id = ?' => $items->getId()];
        } elseif (is_array($items)) {
            $where = ['link_id in (?)' => $items];
        } else {
            $where = ['sample_id = ?' => $items];
        }
        $connection->delete($subject->getMainTable(), $where);
        $connection->delete($subject->getTable('downloadable_link_title'), $where);
        $connection->delete($subject->getTable('ambros_store__downloadable_link_price'), $where);
        return $subject;
    }
    
}