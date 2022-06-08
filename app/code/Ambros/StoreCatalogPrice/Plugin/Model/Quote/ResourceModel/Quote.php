<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Quote\ResourceModel;

/**
 * Quote resource plugin
 */
class Quote
{
    
    /**
     * Around mark quotes recollect on catalog rules
     * 
     * @param \Magento\Quote\Model\ResourceModel\Quote $subject
     * @param \Closure $proceed
     * @return \Magento\Quote\Model\ResourceModel\Quote
     */
    public function aroundMarkQuotesRecollectOnCatalogRules(
        \Magento\Quote\Model\ResourceModel\Quote $subject,
        \Closure $proceed
    )
    {
        $connection = $subject->getConnection();
        $select = $connection->select()->join(
            [
                't2' => $connection->select()
                    ->from(['t2' => $subject->getTable('quote_item')], ['entity_id' => 'quote_id'])
                    ->from(['t3' => $subject->getTable('ambros_store__catalogrule_product_price')], [])
                    ->where('t2.product_id = t3.product_id')
                    ->group('quote_id')
            ],
            't1.entity_id = t2.entity_id',
            ['trigger_recollect' => new \Zend_Db_Expr('1')]
        );
        $connection->query($select->crossUpdateFromSelect(['t1' => $subject->getTable('quote')]));
        return $subject;
    }
    
}