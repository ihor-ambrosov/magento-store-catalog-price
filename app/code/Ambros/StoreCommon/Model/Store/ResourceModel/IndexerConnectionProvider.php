<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCommon\Model\Store\ResourceModel;

/**
 * Indexer resource connection provider
 */
class IndexerConnectionProvider extends \Ambros\Common\Model\ResourceModel\ConnectionProvider
{
    
    /**
     * Constructor
     * 
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Framework\DB\Sql\ExpressionFactory $expressionFactory
     * @param string $resourceName
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\DB\Sql\ExpressionFactory $expressionFactory,
        string $resourceName = 'indexer'
    )
    {
        parent::__construct(
            $resourceConnection,
            $expressionFactory,
            $resourceName
        );
    }
    
}