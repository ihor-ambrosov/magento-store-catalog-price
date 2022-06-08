<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Setup\Operation;

/**
 * Create store copy table setup operation
 */
class CreateStoreCopyTable extends \Ambros\Common\Setup\Operation\CreateCopyTable
{
    
    /**
     * Constructor
     * 
     * @param string $subjectTableName
     * @param string $tableName
     * @param string|null $tableComment
     * @return void
     */
    public function __construct(
        string $subjectTableName,
        string $tableName,
        string $tableComment = null
    )
    {
        parent::__construct(
            $subjectTableName,
            $tableName,
            [
                'website_id' => [
                    'name' => 'store_id',
                    'comment' => 'Store ID',
                    'ref_table_name' => 'store',
                    'ref_name' => 'store_id',
                ],
            ],
            $tableComment
        );
    }
    
}