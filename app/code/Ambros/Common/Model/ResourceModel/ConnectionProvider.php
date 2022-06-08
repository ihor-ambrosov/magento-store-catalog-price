<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Model\ResourceModel;

/**
 * Resource connection provider
 */
class ConnectionProvider
{
    
    /**
     * Resource connection
     * 
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceConnection;
    
    /**
     * Expression factory
     * 
     * @var \Magento\Framework\DB\Sql\ExpressionFactory
     */
    protected $expressionFactory;
    
    /**
     * Resource name
     * 
     * @var string
     */
    protected $resourceName;
    
    /**
     * Connection
     * 
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;
    
    /**
     * Constructor
     * 
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Framework\DB\Sql\ExpressionFactory $expressionFactory
     * @param string $resourceName
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\DB\Sql\ExpressionFactory $expressionFactory,
        string $resourceName = \Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->expressionFactory = $expressionFactory;
        $this->resourceName = $resourceName;
    }
    
    /**
     * Get connection
     * 
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    public function getConnection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        if ($this->connection === null) {
            $this->connection = $this->resourceConnection->getConnection($this->resourceName);
        }
        return $this->connection;
    }
    
    /**
     * Get select
     * 
     * @return \Magento\Framework\DB\Select
     */
    public function getSelect(): \Magento\Framework\DB\Select
    {
        return $this->getConnection()->select();
    }
    
    /**
     * Get table
     * 
     * @param string $tableName
     * @return string
     */
    public function getTable(string $tableName): string
    {
        return $this->resourceConnection->getTableName($tableName, $this->resourceName);
    }
    
    /**
     * Get condition
     * 
     * @param array $subConditions
     * @param string $joinOperator
     * @return string
     */
    public function getCondition(array $subConditions, string $joinOperator = 'AND'): string
    {
        return '('.implode(') '.$joinOperator.' (', $subConditions).')';
    }
    
    /**
     * Get SQL
     * 
     * @param string $expression
     * @return \Magento\Framework\DB\Sql\Expression
     */
    public function getSql(string $expression): \Magento\Framework\DB\Sql\Expression
    {
        return $this->expressionFactory->create(['expression' => $expression]);
    }
    
    /**
     * Get check SQL
     * 
     * @param string $expression
     * @param string $true
     * @param string $false
     * @return \Magento\Framework\DB\Sql\Expression
     */
    public function getCheckSql(string $expression, string $true, string $false): \Magento\Framework\DB\Sql\Expression
    {
        return $this->getSql((string) $this->getConnection()->getCheckSql($expression, $true, $false));
    }
    
    /**
     * Get if NULL SQL
     * 
     * @param string $expression
     * @param string $value
     * @return \Magento\Framework\DB\Sql\Expression
     */
    public function getIfNullSql(string $expression, string $value = null): \Magento\Framework\DB\Sql\Expression
    {
        return $this->getSql((string) $this->getConnection()->getIfNullSql($expression, $value));
    }
    
    /**
     * Get round SQL
     * 
     * @param string $value
     * @return \Magento\Framework\DB\Sql\Expression
     */
    public function getRoundSql(string $value): \Magento\Framework\DB\Sql\Expression
    {
        return $this->getSql(sprintf("ROUND(%s, 4)", $value));
    }
    
    /**
     * Get percent value SQL
     * 
     * @param string $value
     * @param string $percentage
     * @return \Magento\Framework\DB\Sql\Expression
     */
    public function getPercentValueSql(string $value, string $percentage): \Magento\Framework\DB\Sql\Expression
    {
        return $this->getSql(sprintf("%s * (%s / 100)", $value, $percentage));
    }
    
    /**
     * Get inverse percent value SQL
     * 
     * @param string $value
     * @param string $percentage
     * @return \Magento\Framework\DB\Sql\Expression
     */
    public function getInversePercentValueSql(string $value, string $percentage): \Magento\Framework\DB\Sql\Expression
    {
        return $this->getSql(sprintf("%s * (1 - %s / 100)", $value, $percentage));
    }
    
    /**
     * Get percent value round SQL
     * 
     * @param string $value
     * @param string $percentage
     * @return \Magento\Framework\DB\Sql\Expression
     */
    public function getPercentValueRoundSql(string $value, string $percentage): \Magento\Framework\DB\Sql\Expression
    {
        return $this->getRoundSql((string) $this->getPercentValueSql($value, $percentage));
    }
    
    /**
     * Get inverse percent value round SQL
     * 
     * @param string $value
     * @param string $percentage
     * @return \Magento\Framework\DB\Sql\Expression
     */
    public function getInversePercentValueRoundSql(string $value, string $percentage): \Magento\Framework\DB\Sql\Expression
    {
        return $this->getRoundSql((string) $this->getInversePercentValueSql($value, $percentage));
    }
    
}