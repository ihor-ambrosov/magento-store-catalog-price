<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\ResourceModel\Product\Indexer\Price;

/**
 * Custom option price modifier plugin
 */
class CustomOptionPriceModifier extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Connection provider
     * 
     * @var \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider
     */
    protected $connectionProvider;
    
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
     * @param \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Store\ResourceModel\IndexerConnectionProvider $connectionProvider,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        $this->connectionProvider = $connectionProvider;
        $this->priceScope = $priceScope;
        parent::__construct($wrapperFactory);
    }
    
    /**
     * Get option aggregated table
     *
     * @return string
     */
    protected function getOptionAggregatedTable(): string
    {
        return $this->getSubjectPropertyValue('tableStrategy')->getTableName('ambros_store__catalog_product_index_price_opt_agr');
    }

    /**
     * Get option price table
     *
     * @return string
     */
    protected function getOptionPriceTable(): string
    {
        return $this->getSubjectPropertyValue('tableStrategy')->getTableName('ambros_store__catalog_product_index_price_opt');
    }
    
    /**
     * Clear option aggregated table
     * 
     * @return $this
     */
    protected function clearOptionAggregatedTable()
    {
        $this->connectionProvider->getConnection()->delete($this->getOptionAggregatedTable());
        return $this;
    }

    /**
     * Clear option price table
     *
     * @return $this
     */
    protected function clearOptionPriceTable()
    {
        $this->connectionProvider->getConnection()->delete($this->getOptionPriceTable());
        return $this;
    }
    
    /**
     * Clear option tables
     * 
     * @return $this
     */
    protected function clearOptionTables()
    {
        $this->clearOptionAggregatedTable();
        $this->clearOptionPriceTable();
        return $this;
    }
    
    /**
     * Get option select
     * 
     * @param string $finalPriceTable
     * @return \Magento\Framework\DB\Select
     */
    protected function getOptionSelect(string $finalPriceTable): \Magento\Framework\DB\Select
    {
        $connection = $this->connectionProvider->getConnection();
        return $connection->select()
            ->from(
                ['i' => $finalPriceTable],
                ['entity_id', 'customer_group_id', 'store_id']
            )
            ->join(
                ['e' => $this->connectionProvider->getTable('catalog_product_entity')],
                'e.entity_id = i.entity_id',
                []
            )
            ->join(
                ['csd' => $this->connectionProvider->getTable('ambros_store__catalog_product_index_store')],
                'i.store_id = csd.store_id',
                []
            )
            ->join(
                ['o' => $this->connectionProvider->getTable('catalog_product_option')],
                'o.product_id = e.entity_id',
                ['option_id']
            );
    }
    
    /**
     * Get fixed option price SQL
     * 
     * @param string $optionPriceType
     * @param string $optionPrice
     * @return \Zend_Db_Expr
     */
    protected function getFixedOptionPriceSql(string $optionPriceType, string $optionPrice): \Zend_Db_Expr
    {
        return $this->connectionProvider->getConnection()->getCheckSql(
            $optionPriceType.' = \'fixed\'',
            $optionPrice,
            (string) $this->connectionProvider->getPercentValueRoundSql('i.final_price', $optionPrice)
        );
    }
    
    /**
     * Get fixed option tier price SQL
     * 
     * @param string $optionPriceType
     * @param string $optionPrice
     * @return \Zend_Db_Expr
     */
    protected function getFixedOptionTierPriceSql(string $optionPriceType, string $optionPrice): \Zend_Db_Expr
    {
        return $this->connectionProvider->getConnection()->getCheckSql(
            $optionPriceType.' = \'fixed\'',
            $optionPrice,
            (string) $this->connectionProvider->getPercentValueRoundSql('i.tier_price', $optionPrice)
        );
    }
    
    /**
     * Get multiple option min price aggregated SQL
     * 
     * @param string $optionPriceType
     * @param string $optionPrice
     * @return \Zend_Db_Expr
     */
    protected function getMultipleOptionMinPriceAggregatedSql(string $optionPriceType, string $optionPrice): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $fixedOptionPrice = (string) $this->getFixedOptionPriceSql($optionPriceType, $optionPrice);
        return $connection->getCheckSql('MIN(o.is_require) = 1', 'MIN('.$fixedOptionPrice.')', '0');
    }
    
    /**
     * Get multiple option max price aggregated SQL
     * 
     * @param string $optionPriceType
     * @param string $optionPrice
     * @return \Zend_Db_Expr
     */
    protected function getMultipleOptionMaxPriceAggregatedSql(string $optionPriceType, string $optionPrice): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $fixedOptionPrice = (string) $this->getFixedOptionPriceSql($optionPriceType, $optionPrice);
        return $connection->getCheckSql(
            $this->connectionProvider->getCondition(['MIN(o.type)=\'radio\'', 'MIN(o.type)=\'drop_down\''], 'OR'),
            'MAX('.$fixedOptionPrice.')',
            'SUM('.$fixedOptionPrice.')'
        );
    }
    
    /**
     * Get multiple option tier price aggregated SQL
     * 
     * @param string $optionPriceType
     * @param string $optionPrice
     * @return \Zend_Db_Expr
     */
    protected function getMultipleOptionTierPriceAggregatedSql(string $optionPriceType, string $optionPrice): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $fixedOptionTierPrice = (string) $this->getFixedOptionTierPriceSql($optionPriceType, $optionPrice);
        return $connection->getCheckSql(
            'MIN(i.tier_price) IS NOT NULL',
            (string) $connection->getCheckSql('MIN(o.is_require) > 0', 'MIN('.$fixedOptionTierPrice.')', '0'),
            'NULL'
        );
    }
    
    /**
     * Get multiple option aggregated select
     *
     * @param string $finalPriceTable
     * @return \Magento\Framework\DB\Select
     * @throws \Exception
     */
    protected function getMultipleOptionAggregatedSelect(string $finalPriceTable): \Magento\Framework\DB\Select
    {
        $connection = $this->connectionProvider->getConnection();
        $select = $this->getOptionSelect($finalPriceTable);
        $select
            ->join(
                ['ot' => $this->connectionProvider->getTable('catalog_product_option_type_value')],
                'ot.option_id = o.option_id',
                []
            )
            ->join(
                ['otpd' => $this->connectionProvider->getTable('catalog_product_option_type_price')],
                $this->connectionProvider->getCondition(['otpd.option_type_id = ot.option_type_id', 'otpd.store_id = 0'], 'AND'),
                []
            )
            ->group(['i.entity_id', 'i.customer_group_id', 'i.store_id', 'o.option_id']);
        if ($this->priceScope->isGlobal()) {
            $optionPriceType = 'otpd.price_type';
            $optionPrice = 'otpd.price';
        } else {
            $select->joinLeft(
                ['otps' => $this->connectionProvider->getTable('catalog_product_option_type_price')],
                $this->connectionProvider->getCondition(['otps.option_type_id = otpd.option_type_id', 'otps.store_id = csd.store_id'], 'AND'),
                []
            );
            $optionPriceType = (string) $connection->getCheckSql('otps.option_type_price_id > 0', 'otps.price_type', 'otpd.price_type');
            $optionPrice = (string) $connection->getCheckSql('otps.option_type_price_id > 0', 'otps.price', 'otpd.price');
        }
        $select->columns([
            'min_price' => $this->getMultipleOptionMinPriceAggregatedSql($optionPriceType, $optionPrice),
            'max_price' => $this->getMultipleOptionMaxPriceAggregatedSql($optionPriceType, $optionPrice),
            'tier_price' => $this->getMultipleOptionTierPriceAggregatedSql($optionPriceType, $optionPrice),
        ]);
        return $select;
    }
    
    /**
     * Get single option min price aggregated SQL
     * 
     * @param string $optionPriceType
     * @param string $optionPrice
     * @return \Zend_Db_Expr
     */
    protected function getSingleOptionMinPriceAggregatedSql(string $optionPriceType, string $optionPrice): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $fixedOptionPrice = (string) $this->getFixedOptionPriceSql($optionPriceType, $optionPrice);
        return $connection->getCheckSql(
            $this->connectionProvider->getCondition([$fixedOptionPrice.' > 0', 'o.is_require = 1'], 'AND'),
            $fixedOptionPrice,
            '0'
        );
    }
    
    /**
     * Get single option max price aggregated SQL
     * 
     * @param string $optionPriceType
     * @param string $optionPrice
     * @return \Zend_Db_Expr
     */
    protected function getSingleOptionMaxPriceAggregatedSql(string $optionPriceType, string $optionPrice): \Zend_Db_Expr
    {
        return $this->getFixedOptionPriceSql($optionPriceType, $optionPrice);
    }
    
    /**
     * Get single option tier price aggregated SQL
     * 
     * @param string $optionPriceType
     * @param string $optionPrice
     * @return \Zend_Db_Expr
     */
    protected function getSingleOptionTierPriceAggregatedSql(string $optionPriceType, string $optionPrice): \Zend_Db_Expr
    {
        $connection = $this->connectionProvider->getConnection();
        $fixedOptionTierPrice = (string) $this->getFixedOptionTierPriceSql($optionPriceType, $optionPrice);
        return $connection->getCheckSql(
            'i.tier_price IS NOT NULL',
            (string) $connection->getCheckSql(
                $this->connectionProvider->getCondition([$fixedOptionTierPrice.' > 0', 'o.is_require = 1'], 'AND'),
                $fixedOptionTierPrice,
                '0'
            ),
            'NULL'
        );
    }
    
    /**
     * Get single option aggregated select
     * 
     * @param string $finalPriceTable
     * @return \Magento\Framework\DB\Select
     * @throws \Exception
     */
    protected function getSingleOptionAggregatedSelect(string $finalPriceTable): \Magento\Framework\DB\Select
    {
        $connection = $this->connectionProvider->getConnection();
        $select = $this->getOptionSelect($finalPriceTable);
        $select->join(
            ['opd' => $this->connectionProvider->getTable('catalog_product_option_price')],
            $this->connectionProvider->getCondition(['opd.option_id = o.option_id', 'opd.store_id = 0'], 'AND'),
            []
        );
        if ($this->priceScope->isGlobal()) {
            $optionPriceType = 'opd.price_type';
            $optionPrice = 'opd.price';
        } else {
            $select->joinLeft(
                ['ops' => $this->connectionProvider->getTable('catalog_product_option_price')],
                $this->connectionProvider->getCondition(['ops.option_id = opd.option_id', 'ops.store_id = csd.store_id'], 'AND'),
                []
            );
            $optionPriceType = (string) $connection->getCheckSql('ops.option_price_id > 0', 'ops.price_type', 'opd.price_type');
            $optionPrice = (string) $connection->getCheckSql('ops.option_price_id > 0', 'ops.price', 'opd.price');
        }
        $select->columns([
            'min_price' => $this->getSingleOptionMinPriceAggregatedSql($optionPriceType, $optionPrice),
            'max_price' => $this->getSingleOptionMaxPriceAggregatedSql($optionPriceType, $optionPrice),
            'tier_price' => $this->getSingleOptionTierPriceAggregatedSql($optionPriceType, $optionPrice),
        ]);
        return $select;
    }
    
    /**
     * Get option price select
     *
     * @return \Magento\Framework\DB\Select
     */
    protected function getOptionPriceSelect(): \Magento\Framework\DB\Select
    {
        return $this->connectionProvider->getConnection()->select()
            ->from([$this->getOptionAggregatedTable()], [
                'entity_id',
                'customer_group_id',
                'store_id',
                'min_price' => 'SUM(min_price)',
                'max_price' => 'SUM(max_price)',
                'tier_price' => 'SUM(tier_price)',
            ])
            ->group(['entity_id', 'customer_group_id', 'store_id']);
    }

    /**
     * Get price select
     *
     * @return \Magento\Framework\DB\Select
     */
    protected function getPriceSelect(): \Magento\Framework\DB\Select
    {
        $connection = $this->connectionProvider->getConnection();
        return $connection->select()
            ->join(
                ['io' => $this->getOptionPriceTable()],
                $this->connectionProvider->getCondition([
                    'i.entity_id = io.entity_id',
                    'i.customer_group_id = io.customer_group_id',
                    'i.store_id = io.store_id',
                ], 'AND'),
                []
            )
            ->columns([
                'min_price' => $this->connectionProvider->getSql('i.min_price + io.min_price'),
                'max_price' => $this->connectionProvider->getSql('i.max_price + io.max_price'),
                'tier_price' => $connection->getCheckSql(
                    'i.tier_price IS NOT NULL',
                    'i.tier_price + io.tier_price',
                    'NULL'
                ),
            ]);
    }
    
    /**
     * Around modify price
     * 
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\CustomOptionPriceModifier $subject
     * @param \Closure $proceed
     * @param \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTable
     * @param array $entityIds
     * @return void
     * @throws \Exception
     */
    public function aroundModifyPrice(
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\CustomOptionPriceModifier $subject,
        \Closure $proceed,
        \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure $priceTable,
        array $entityIds = []
    ): void
    {
        $this->setSubject($subject);
        if (!$this->invokeSubjectMethod('checkIfCustomOptionsExist', $priceTable)) {
            return;
        }
        $connection = $this->connectionProvider->getConnection();
        $this->clearOptionTables();
        $finalPriceTable = $priceTable->getTableName();
        $connection->query(
            $this->getMultipleOptionAggregatedSelect($finalPriceTable)
                ->insertFromSelect($this->getOptionAggregatedTable())
        );
        $connection->query(
            $this->getSingleOptionAggregatedSelect($finalPriceTable)
                ->insertFromSelect($this->getOptionAggregatedTable())
        );
        $connection->query(
            $this->getOptionPriceSelect()
                ->insertFromSelect($this->getOptionPriceTable())
        );
        $connection->query(
            $this->getPriceSelect()
                ->crossUpdateFromSelect(['i' => $finalPriceTable])
        );
        $this->clearOptionTables();
    }
    
}