<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price\Action;

/**
 * Product price indexer full plugin
 */
class Full extends \Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price\AbstractAction
{
    
    /**
     * Prepare tables
     * 
     * @return $this
     * @throws \Exception
     */
    protected function prepareTables()
    {
        $defaultIndexerResource = $this->getSubjectPropertyValue('_defaultIndexerResource');
        $defaultIndexerResource->getTableStrategy()->setUseIdxTable(false);
        $this->prepareStoreDateTable();
        $this->invokeSubjectMethod('truncateReplicaTables');
        return $this;
    }
    
    /**
     * Move data from replica table to replica tables
     *
     * @param array $dimensions
     * @return $this
     * @throws \Zend_Db_Statement_Exception
     */
    protected function moveDataFromReplicaTableToReplicaTables(array $dimensions)
    {
        if (!$dimensions) {
            return $this;
        }
        $dimensionTableMaintainer = $this->getSubjectPropertyValue('dimensionTableMaintainer');
        $connection = $dimensionTableMaintainer->getConnection();
        $select = $connection->select()->from($dimensionTableMaintainer->getMainReplicaTable([]));
        $check = clone $select;
        $check->reset('columns')->columns('count(*)');
        if (!$connection->query($check)->fetchColumn()) {
            return $this;
        }
        $replicaTablesByDimension = $dimensionTableMaintainer->getMainReplicaTable($dimensions);
        foreach ($dimensions as $dimension) {
            $this->applyDimensionCondition($select, $dimension);
        }
        $connection->query($connection->insertFromSelect(
            $select,
            $replicaTablesByDimension,
            [],
            \Magento\Framework\DB\Adapter\AdapterInterface::INSERT_ON_DUPLICATE
        ));
        return $this;
    }
    
    /**
     * Switch tables
     * 
     * @return $this
     * @throws \Zend_Db_Statement_Exception
     */
    protected function switchTables()
    {
        $mainTablesByDimension = [];
        $defaultIndexerResource = $this->getSubjectPropertyValue('_defaultIndexerResource');
        $dimensionCollectionFactory = $this->getSubjectPropertyValue('dimensionCollectionFactory');
        $connection = $defaultIndexerResource->getConnection();
        foreach ($dimensionCollectionFactory->create() as $dimensions) {
            $mainTablesByDimension[] = $this->getMainTableByDimensions($dimensions);
            $this->moveDataFromReplicaTableToReplicaTables($dimensions);
        }
        if (count($mainTablesByDimension) > 0) {
            $this->getSubjectPropertyValue('activeTableSwitcher')->switchTable($connection, $mainTablesByDimension);
        }
        return $this;
    }
    
    /**
     * Around execute
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\Action\Full $subject
     * @param \Closure $proceed
     * @param array|int|null $ids
     * @return void
     * @throws \Exception
     */
    public function aroundExecute(
        \Magento\Catalog\Model\Indexer\Product\Price\Action\Full $subject,
        \Closure $proceed,
        $ids = null
    ): void
    {
        $this->setSubject($subject);
        try {
            $this->prepareTables();
            foreach ($subject->getTypeIndexers(true) as $typeId => $priceIndexer) {
                if ($priceIndexer instanceof \Magento\Framework\Indexer\DimensionalIndexerInterface) {
                    $this->invokeSubjectMethod('reindexProductTypeWithDimensions', $priceIndexer, $typeId);
                    continue;
                }
                $priceIndexer->getTableStrategy()->setUseIdxTable(false);
                $this->invokeSubjectMethod('reindexProductType', $priceIndexer, $typeId);
            }
            $this->switchTables();
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\LocalizedException(__($exception->getMessage()), $exception);
        }
    }
    
}