<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price;

/**
 * Product price indexer table maintainer plugin
 */
class TableMaintainer extends \Ambros\Common\Plugin\Plugin
{
    
    const MAIN_INDEX_TABLE = 'ambros_store__catalog_product_index_price';
    
    /**
     * Product metadata
     * 
     * @var \Magento\Framework\App\ProductMetadata 
     */
    protected $productMetadata;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        parent::__construct($wrapperFactory);
        $this->productMetadata = $productMetadata;
    }
    
    /**
     * Before get main table
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject
     * @return void
     */
    public function beforeGetMainTable(\Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject)
    {
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $this->setSubject($subject);
            $this->invokeSubjectMethod('_setMainTable', self::MAIN_INDEX_TABLE, 'entity_id');
        }
    }
    
    /**
     * Get main table by dimensions
     * 
     * @param array $dimensions
     * @return string
     */
    protected function getMainTableByDimensions(array $dimensions): string
    {
        return $this->getSubjectPropertyValue('tableResolver')->resolve(self::MAIN_INDEX_TABLE, $dimensions);
    }
    
    /**
     * Around get main table
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Search\Request\Dimension[] $dimensions
     * @return string
     */
    public function aroundGetMainTable(
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject,
        \Closure $proceed,
        $dimensions = null
    ): string
    {
        $this->setSubject($subject);
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            return $proceed();
        } else {
            return $this->getMainTableByDimensions($dimensions);
        }
    }
    
    /**
     * Around get main table by dimensions
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Search\Request\Dimension[] $dimensions
     * @return string
     */
    public function aroundGetMainTableByDimensions(
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject,
        \Closure $proceed,
        array $dimensions
    ): string
    {
        $this->setSubject($subject);
        return $this->getMainTableByDimensions($dimensions);
    }
    
    /**
     * Around create tables for dimensions
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Search\Request\Dimension[] $dimensions
     * @return void
     */
    public function aroundCreateTablesForDimensions(
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject,
        \Closure $proceed,
        array $dimensions
    )
    {
        $this->setSubject($subject);
        $additionalTableSuffix = $this->getSubjectPropertyValue('additionalTableSuffix');
        $mainIndexTable = $this->invokeSubjectMethod('getTable', self::MAIN_INDEX_TABLE.$additionalTableSuffix);
        $this->invokeSubjectMethod('createTable', $mainIndexTable, $this->getMainTableByDimensions($dimensions));
        $this->invokeSubjectMethod('createTable', $mainIndexTable, $this->getMainTableByDimensions($dimensions).$additionalTableSuffix);
    }
    
    /**
     * Around create main temporary table
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Search\Request\Dimension[] $dimensions
     * @return void
     */
    public function aroundCreateMainTmpTable(
        \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer $subject,
        \Closure $proceed,
        array $dimensions
    )
    {
        $this->setSubject($subject);
        $tmpTableSuffix = $this->getSubjectPropertyValue('tmpTableSuffix');
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            $templateTableName = $this->getSubjectPropertyValue('_resources')->getTableName(self::MAIN_INDEX_TABLE.'_tmp');
        } else {
            $templateTableName = $this->getSubjectPropertyValue('resource')->getTableName(self::MAIN_INDEX_TABLE.'_tmp');
        }
        $temporaryTableName = $this->getMainTableByDimensions($dimensions).$tmpTableSuffix;
        $subject->getConnection()->createTemporaryTableLike($temporaryTableName, $templateTableName, true);
        $mainTmpTable = $this->getSubjectPropertyValue('mainTmpTable');
        $mainTmpTable[$this->invokeSubjectMethod('getArrayKeyForTmpTable', $dimensions)] = $temporaryTableName;
        $this->setSubjectPropertyValue('mainTmpTable', $mainTmpTable);
    }
    
}