<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price\Action;

/**
 * Product price indexer rows plugin
 */
class Rows extends \Ambros\StoreCatalogPrice\Plugin\Model\Catalog\Indexer\Product\Price\AbstractAction
{
    
    /**
     * Around execute
     * 
     * @param \Magento\Catalog\Model\Indexer\Product\Price\Action\Rows $subject
     * @param \Closure $proceed
     * @param array $ids
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundExecute(
        \Magento\Catalog\Model\Indexer\Product\Price\Action\Rows $subject,
        \Closure $proceed,
        $ids
    )
    {
        $this->setSubject($subject);
        if (empty($ids)) {
            throw new \Magento\Framework\Exception\InputException(__('Bad value was supplied.'));
        }
        
        if (version_compare($this->productMetadata->getVersion(), '2.4.2', '>=')) {
            $batchSize = $this->getSubjectPropertyValue('batchSize');
            $currentBatch = [];
            $i = 0;
            foreach ($ids as $id) {
                $currentBatch[] = $id;
                if (++$i === $batchSize) {
                    try {
                        $this->reindexRows($currentBatch);
                    } catch (\Exception $exception) {
                        throw new \Magento\Framework\Exception\LocalizedException(__($exception->getMessage()), $exception);
                    }
                    $i = 0;
                    $currentBatch = [];
                }
            }
            if (!empty($currentBatch)) {
                try {
                    $this->reindexRows($currentBatch);
                } catch (\Exception $exception) {
                    throw new \Magento\Framework\Exception\LocalizedException(__($exception->getMessage()), $exception);
                }
            }
        } else {
            try {
                $this->reindexRows($ids);
            } catch (\Exception $exception) {
                throw new \Magento\Framework\Exception\LocalizedException(__($exception->getMessage()), $exception);
            }
        }
    }
    
}