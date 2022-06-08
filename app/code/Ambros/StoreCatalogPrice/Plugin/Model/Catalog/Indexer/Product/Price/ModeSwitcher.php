<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price;

/**
 * Product price indexer mode switcher plugin
 */
class ModeSwitcher extends \Ambros\Common\Plugin\Plugin
{
    
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
     * Get main table by dimensions
     * 
     * @param array $dimensions
     * @return string
     */
    protected function getMainTableByDimensions(array $dimensions): string
    {
        $tableMaintainer = $this->getSubjectPropertyValue('tableMaintainer');
        if (
            (
                version_compare($this->productMetadata->getVersion(), '2.3.6', '>=') && 
                version_compare($this->productMetadata->getVersion(), '2.4.0', '<')
            ) || 
            version_compare($this->productMetadata->getVersion(), '2.4.1', '>=')
        ) {
            return $tableMaintainer->getMainTableByDimensions($dimensions);
        } else {
            return $tableMaintainer->getMainTable($dimensions);
        }
    }
    
    /**
     * Copy table data
     * 
     * @param string $newTable
     * @param string $oldTable
     * @param \Magento\Framework\Search\Request\Dimension[] $dimensions
     * @return void
     */
    protected function copyTableData(string $newTable, string $oldTable, array $dimensions = [])
    {
        $connection = $this->getSubjectPropertyValue('tableMaintainer')->getConnection();
        $select = $connection->select()->from($oldTable);
        foreach ($dimensions as $dimension) {
            if ($dimension->getName() === \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME) {
                $select->where('store_id = ?', $dimension->getValue());
            }
            if ($dimension->getName() === \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME) {
                $select->where('customer_group_id = ?', $dimension->getValue());
            }
        }
        $connection->query(
            $connection->insertFromSelect(
                $select,
                $newTable,
                [],
                \Magento\Framework\DB\Adapter\AdapterInterface::INSERT_ON_DUPLICATE
            )
        );
    }
    
    /**
     * Around move data
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\ModeSwitcher $subject
     * @param \Closure $proceed
     * @param string $currentMode
     * @param string $previousMode
     * @return void
     */
    public function aroundMoveData(
        \Magento\Catalog\Model\Indexer\Product\Price\ModeSwitcher $subject,
        \Closure $proceed,
        string $currentMode,
        string $previousMode
    )
    {
        $this->setSubject($subject);
        $multiDimensionsForCurrentMode = $this->invokeSubjectMethod('getDimensionsArray', $currentMode);
        $multiDimensionsForPreviousMode = $this->invokeSubjectMethod('getDimensionsArray', $previousMode);
        $tableMaintainer = $this->getSubjectPropertyValue('tableMaintainer');
        foreach ($multiDimensionsForCurrentMode as $dimensionsForCurrentMode) {
            $newTable = $this->getMainTableByDimensions($dimensionsForCurrentMode);
            if (empty($dimensionsForCurrentMode)) {
                foreach ($multiDimensionsForPreviousMode as $dimensionsForPreviousMode) {
                    $oldTable = $this->getMainTableByDimensions($dimensionsForPreviousMode);
                    $this->copyTableData($newTable, $oldTable);
                }
            } else {
                foreach ($multiDimensionsForPreviousMode as $dimensionsForPreviousMode) {
                    $oldTable = $this->getMainTableByDimensions($dimensionsForPreviousMode);
                    $this->copyTableData($newTable, $oldTable, $dimensionsForCurrentMode);
                }
            }
        }
    }
    
}