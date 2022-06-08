<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Setup\Operation;

/**
 * Create copy table setup operation
 */
class CreateCopyTable extends \Ambros\Common\Setup\Operation\CreateTable
{
    
    /**
     * Subject table name
     * 
     * @var string
     */
    protected $subjectTableName;
    
    /**
     * Columns map
     * 
     * @var array
     */
    protected $columnsMap;
    
    /**
     * Constructor
     * 
     * @param string $subjectTableName
     * @param string $tableName
     * @param array $columnsMap
     * @param string|null $tableComment
     * @return void
     */
    public function __construct(
        string $subjectTableName,
        string $tableName,
        array $columnsMap = [],
        string $tableComment = null
    )
    {
        parent::__construct($tableName, $tableComment);
        $this->subjectTableName = $subjectTableName;
        $this->columnsMap = $columnsMap;
    }
    
    /**
     * Get comment
     * 
     * @return string
     */
    protected function getTableComment(): string
    {
        if ($this->tableComment !== null) {
            return $this->tableComment;
        }
        return $this->tableComment = $this->getTableStatusByTableName($this->subjectTableName)['Comment'];
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
        $this->columns = [];
        $connection = $this->getConnection();
        $columns = $connection->describeTable($this->getTable($this->subjectTableName));
        foreach ($columns as $column) {
            $column = $connection->getColumnCreateByDescribe($column);
            if (!empty($this->columnsMap[$column['name']])) {
                $column = array_merge(
                    $column,
                    array_filter(
                        $this->columnsMap[$column['name']],
                        function($key) {
                            return in_array($key, ['name', 'type', 'length', 'options', 'comment']);
                        },
                        ARRAY_FILTER_USE_KEY
                    )
                );
            }
            $this->columns[] = $column;
        }
        return $this->columns;
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
        $this->indexes = [];
        $indexes = $this->getConnection()->getIndexList($this->getTable($this->subjectTableName));
        foreach ($indexes as $index) {
            foreach ($index['COLUMNS_LIST'] as &$indexColumn) {
                if (!empty($this->columnsMap[$indexColumn])) {
                    $indexColumn = $this->columnsMap[$indexColumn]['name'];
                }
            }
            $this->indexes[] = $index;
        }
        return $this->indexes;
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
        $this->foreignKeys = [];
        $foreignKeys = $this->getConnection()->getForeignKeys($this->getTable($this->subjectTableName));
        foreach ($foreignKeys as &$foreignKey) {
            if (!empty($this->columnsMap[$foreignKey['COLUMN_NAME']])) {
                $column = $this->columnsMap[$foreignKey['COLUMN_NAME']];
                $foreignKey['COLUMN_NAME'] = $column['name'];
                $foreignKey['REF_TABLE_NAME'] = $column['ref_table_name'];
                $foreignKey['REF_COLUMN_NAME'] = $column['ref_name'];
            }
            $this->foreignKeys[] = $foreignKey;
        }
        return $this->foreignKeys;
    }
    
    /**
     * Create
     * 
     * @return \Magento\Framework\DB\Ddl\Table
     */
    protected function createTableDdl(): \Magento\Framework\DB\Ddl\Table
    {
        $tableDdl = parent::createTableDdl();
        $tableDdl->setOption('type', $this->getTableStatusByTableName($this->subjectTableName)['Engine']);
        return $tableDdl;
    }
    
}