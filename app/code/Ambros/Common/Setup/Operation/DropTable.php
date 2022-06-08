<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Setup\Operation;

/**
 * Drop table setup operation
 */
class DropTable extends \Ambros\Common\Setup\Operation\AbstractOperation
{
    
    /**
     * Name
     * 
     * @var string
     */
    protected $tableName;
    
    /**
     * Constructor
     * 
     * @param string $tableName
     * @return void
     */
    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }
    
    /**
     * Execute
     * 
     * @return \Ambros\Common\Setup\Operation\OperationInterface
     */
    public function execute(): \Ambros\Common\Setup\Operation\OperationInterface
    {
        $connection = $this->getConnection();
        $table = $this->getTable($this->tableName);
        if (!$connection->isTableExists($table)) {
            return $this;
        }
        $connection->dropTable($table);
        return $this;
    }
    
}