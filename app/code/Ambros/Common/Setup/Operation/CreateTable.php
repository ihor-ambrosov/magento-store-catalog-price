<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Setup\Operation;

/**
 * Create table setup operation
 */
class CreateTable extends \Ambros\Common\Setup\Operation\AbstractOperation
{
    
    /**
     * Table name
     * 
     * @var string
     */
    protected $tableName;
    
    /**
     * Table comment
     * 
     * @var string
     */
    protected $tableComment;
    
    /**
     * Columns
     * 
     * @var array
     */
    protected $columns;
    
    /**
     * Indexes
     * 
     * @var array
     */
    protected $indexes;
    
    /**
     * Foreign keys
     * 
     * @var array
     */
    protected $foreignKeys;
    
    /**
     * Constructor
     * 
     * @param string $tableName
     * @param string|null $tableComment
     * @param array|null $columns
     * @param array|null $indexes
     * @param array|null $foreignKeys
     * @return void
     */
    public function __construct(
        string $tableName,
        string $tableComment = null,
        array $columns = null,
        array $indexes = null,
        array $foreignKeys = null
    )
    {
        $this->tableName = $tableName;
        $this->tableComment = $tableComment;
        $this->columns = $columns;
        $this->indexes = $indexes;
        $this->foreignKeys = $foreignKeys;
    }
    
    /**
     * Get columns
     * 
     * @param string $tableName
     * @return array
     */
    protected function getColumnsByTableName(string $tableName): array
    {
        $connection = $this->getConnection();
        $columns = [];
        foreach ($connection->describeTable($this->getTable($tableName)) as $columnDdl) {
            $columns[] = $connection->getColumnCreateByDescribe($columnDdl);
        }
        return $columns;
    }
    
    /**
     * Get table status by table name
     * 
     * @param string $tableName
     * @return array
     */
    protected function getTableStatusByTableName(string $tableName): array
    {
        return $this->getConnection()->showTableStatus($this->getTable($tableName));
    }
    
    /**
     * Get table comment
     * 
     * @return string
     */
    protected function getTableComment(): string
    {
        if ($this->tableComment !== null) {
            return $this->tableComment;
        }
        return $this->tableComment = $this->tableName;
    }
    
    /**
     * Get columns
     * 
     * @return array
     */
    protected function getColumns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }
        return $this->columns = [];
    }
    
    /**
     * Get indexes
     * 
     * @return array
     */
    protected function getIndexes(): array
    {
        if ($this->indexes !== null) {
            return $this->indexes;
        }
        return $this->indexes = [];
    }
    
    /**
     * Get foreign keys
     * 
     * @return array
     */
    protected function getForeignKeys(): array
    {
        if ($this->foreignKeys !== null) {
            return $this->foreignKeys;
        }
        return $this->foreignKeys = [];
    }
    
    /**
     * Create table DDL
     * 
     * @return \Magento\Framework\DB\Ddl\Table
     */
    protected function createTableDdl(): \Magento\Framework\DB\Ddl\Table
    {
        return $this->getConnection()->newTable($this->getTable($this->tableName))
            ->setComment($this->getTableComment());
    }
    
    /**
     * Add column
     * 
     * @param \Magento\Framework\DB\Ddl\Table $tableDdl
     * @param array $column
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function addColumn(\Magento\Framework\DB\Ddl\Table $tableDdl, array $column): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $tableDdl->addColumn(
            $column['name'],
            $column['type'],
            $column['length'] ?? null,
            $column['options'],
            $column['comment']
        );
        return $this;
    }
    
    /**
     * Add columns
     * 
     * @param \Magento\Framework\DB\Ddl\Table $tableDdl
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function addColumns(\Magento\Framework\DB\Ddl\Table $tableDdl): \Ambros\Common\Setup\Operation\OperationInterface
    {
        foreach ($this->getColumns() as $column) {
            $this->addColumn($tableDdl, $column);
        }
        return $this;
    }
    
    /**
     * Add index
     * 
     * @param \Magento\Framework\DB\Ddl\Table $tableDdl
     * @param array $index
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function addIndex(\Magento\Framework\DB\Ddl\Table $tableDdl, array $index): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $indexColumns = $index['COLUMNS_LIST'];
        $indexType = $index['INDEX_TYPE'];
        $tableDdl->addIndex(
            $this->getConnection()->getIndexName($tableDdl->getName(), $indexColumns, $indexType),
            $indexColumns,
            ['type' => $indexType]
        );
        return $this;
    }
    
    /**
     * Check if is primary index
     * 
     * @param array $index
     * @return bool
     */
    protected function isPrimaryIndex(array $index): bool
    {
        return (!empty($index['KEY_NAME']) && $index['KEY_NAME'] === 'PRIMARY') || 
            $index['INDEX_TYPE'] === \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY;
    }
    
    /**
     * Add indexes
     * 
     * @param \Magento\Framework\DB\Ddl\Table $tableDdl
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function addIndexes(\Magento\Framework\DB\Ddl\Table $tableDdl): \Ambros\Common\Setup\Operation\OperationInterface
    {
        foreach ($this->getIndexes() as $index) {
            if ($this->isPrimaryIndex($index)) {
                continue;
            }
            $this->addIndex($tableDdl, $index);
        }
        return $this;
    }
    
    /**
     * Get table DDL action
     *
     * @param string $action
     * @return string
     */
    protected function getTableDdlAction(string $action): string
    {
        switch ($action) {
            case \Magento\Framework\DB\Adapter\AdapterInterface::FK_ACTION_CASCADE:
                return \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE;
            case \Magento\Framework\DB\Adapter\AdapterInterface::FK_ACTION_SET_NULL:
                return \Magento\Framework\DB\Ddl\Table::ACTION_SET_NULL;
            case \Magento\Framework\DB\Adapter\AdapterInterface::FK_ACTION_RESTRICT:
                return \Magento\Framework\DB\Ddl\Table::ACTION_RESTRICT;
            default:
                return \Magento\Framework\DB\Ddl\Table::ACTION_NO_ACTION;
        }
    }
    
    /**
     * Add foreign key
     * 
     * @param \Magento\Framework\DB\Ddl\Table $tableDdl
     * @param array $foreignKey
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function addForeignKey(\Magento\Framework\DB\Ddl\Table $tableDdl, array $foreignKey): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $connection = $this->getConnection();
        $columnName = $foreignKey['COLUMN_NAME'];
        $referenceTableName = $foreignKey['REF_TABLE_NAME'];
        $referenceColumnName = $foreignKey['REF_COLUMN_NAME'];
        $tableDdl->addForeignKey(
            $connection->getForeignKeyName(
                $tableDdl->getName(),
                $columnName,
                $referenceTableName,
                $referenceColumnName
            ),
            $columnName,
            $this->getTable($referenceTableName),
            $referenceColumnName,
            $this->getTableDdlAction($foreignKey['ON_DELETE'])
        );
        return $this;
    }
    
    /**
     * Add foreign keys
     * 
     * @param \Magento\Framework\DB\Ddl\Table $tableDdl
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    protected function addForeignKeys(\Magento\Framework\DB\Ddl\Table $tableDdl): \Ambros\Common\Setup\Operation\OperationInterface
    {
        foreach ($this->getForeignKeys() as $foreignKey) {
            $this->addForeignKey($tableDdl, $foreignKey);
        }
        return $this;
    }
    
    /**
     * Execute
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function execute(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $connection = $this->getConnection();
        $tableDdl = $this->createTableDdl();
        if ($connection->isTableExists($tableDdl->getName())) {
            return $this;
        }
        $this->addColumns($tableDdl);
        $this->addIndexes($tableDdl);
        $this->addForeignKeys($tableDdl);
        $connection->createTable($tableDdl);
        return $this;
    }
    
}