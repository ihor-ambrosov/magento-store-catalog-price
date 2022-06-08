<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Plugin\Block\Backend\Widget;

/**
 * Container plugin
 */
class Container extends \Ambros\Common\Plugin\View\Framework\Element\AbstractBlock
{
    
    /**
     * Prepare layout
     *
     * @return $this
     */
    protected function prepareLayout()
    {
        $subject = $this->getSubject();
        $toolbar = $this->getSubjectPropertyValue('toolbar');
        $buttonList = $this->getSubjectPropertyValue('buttonList');
        $toolbar->pushButtons($subject, $buttonList);
        $this->invokeSubjectParentMethod(\Magento\Framework\View\Element\AbstractBlock::class, '_prepareLayout');
        return $this;
    }
    
}