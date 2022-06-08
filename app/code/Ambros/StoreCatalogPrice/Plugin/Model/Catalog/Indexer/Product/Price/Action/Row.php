<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price\Action;

/**
 * Product price row indexer plugin
 */
class Row extends \Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price\AbstractAction
{
    
    /**
     * Around execute
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\Action\Row $subject
     * @param \Closure $proceed
     * @param int|null $id
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundExecute(
        \Magento\Catalog\Model\Indexer\Product\Price\Action\Row $subject,
        \Closure $proceed,
        $id = null
    )
    {
        $this->setSubject($subject);
        if (!isset($id) || empty($id)) {
            throw new \Magento\Framework\Exception\InputException(__('We can\'t rebuild the index for an undefined product.'));
        }
        try {
            $this->reindexRows([$id]);
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\LocalizedException(__($exception->getMessage()), $exception);
        }
    }
    
}