<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Model\Catalog\ResourceModel\Product\Indexer\Price;

/**
 * Dimension condition applier
 */
class DimensionConditionApplier
{
    
    /**
     * Default field mapper
     * 
     * @var array
     */
    protected $defaultFieldMapper = [
        \Ambros\StoreCommon\Model\Store\Indexer\StoreDimensionProvider::DIMENSION_NAME => 'ps.store_id',
        \Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider::DIMENSION_NAME => 'cg.customer_group_id',
    ];
    
    /**
     * Get dimension field
     * 
     * @param string $dimensionName
     * @return string
     * @throws \LogicException
     */
    public function getDimensionField(string $dimensionName, array $fieldMapper = []): string
    {
        $fieldMapper = empty($fieldMapper) ? $this->defaultFieldMapper : $fieldMapper;
        if (!isset($fieldMapper[$dimensionName])) {
            throw new \LogicException('Provided dimension is not valid for Price indexer: '.$dimensionName);
        }
        return $fieldMapper[$dimensionName];
    }
    
    /**
     * Execute
     * 
     * @param \Magento\Framework\DB\Select $select
     * @param array $dimensions
     * @param array $fieldMapper
     * @return $this
     */
    public function execute(\Magento\Framework\DB\Select $select, array $dimensions, array $fieldMapper = [])
    {
        foreach ($dimensions as $dimension) {
            $select->where($this->getDimensionField($dimension->getName(), $fieldMapper).' = ?', $dimension->getValue());
        }
        return $this;
    }
    
}