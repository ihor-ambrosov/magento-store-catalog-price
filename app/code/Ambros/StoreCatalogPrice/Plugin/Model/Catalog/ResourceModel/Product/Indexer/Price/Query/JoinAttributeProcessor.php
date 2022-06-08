<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Indexer\Price\Query;

/**
 * Join attribute processor plugin
 */
class JoinAttributeProcessor
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;
    
    /**
     * EAV configuration
     * 
     * @var \Magento\Eav\Model\Config
     */
    protected $eavConfig;
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
     * @param \Magento\Eav\Model\Config $eavConfig
     * @return void
     */
    public function __construct(
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Magento\Eav\Model\Config $eavConfig
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->eavConfig = $eavConfig;
    }
    
    /**
     * Get condition
     * 
     * @param string $tableAlias
     * @param string $attributeId
     * @param string $storeId
     * @return string
     */
    protected function getCondition(string $tableAlias, string $attributeId, string $storeId): string
    {
        return $this->connectionProvider->getCondition([
            $tableAlias.'.entity_id = e.entity_id',
            $tableAlias.'.attribute_id = '.$attributeId,
            $tableAlias.'.store_id = '.$storeId,
        ], 'AND');
    }
    
    /**
     * Around process
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\DB\Select $select
     * @param string $attributeCode
     * @param string|null $attributeValue
     * @return \Zend_Db_Expr
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     */
    public function aroundProcess(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor $subject,
        \Closure $proceed,
        \Magento\Framework\DB\Select $select,
        $attributeCode,
        $attributeValue = null
    ): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        $attributeId = (string) $attribute->getAttributeId();
        $attributeTable = $attribute->getBackend()->getTable();
        $joinType = $attributeValue !== null ? 'join' : 'joinLeft';
        if ($attribute->isScopeGlobal()) {
            $alias = 'ta_'.$attributeCode;
            $select->$joinType([$alias => $attributeTable], $this->getCondition($alias, $attributeId, '0'), []);
            $sql = $this->connectionProvider->getSql($alias.'.value');
        } else {
            $dAlias = 'tad_'.$attributeCode;
            $sAlias = 'tas_'.$attributeCode;
            $select->$joinType([$dAlias => $attributeTable], $this->getCondition($dAlias, $attributeId, '0'), []);
            if ($attribute->isScopeWebsite()) {
                $select->joinLeft([$sAlias => $attributeTable], $this->getCondition($sAlias, $attributeId, 'csd.default_store_id'), []);
            } else {
                $select->joinLeft([$sAlias => $attributeTable], $this->getCondition($sAlias, $attributeId, 'csd.store_id'), []);
            }
            $sql = $this->connectionProvider->getCheckSql($connection->getIfNullSql($sAlias.'.value_id', -1).' > 0', $sAlias.'.value', $dAlias.'.value');
        }
        if ($attributeValue !== null) {
            $select->where($sql.' = ?', $attributeValue);
        }
        return $sql;
    }
    
}