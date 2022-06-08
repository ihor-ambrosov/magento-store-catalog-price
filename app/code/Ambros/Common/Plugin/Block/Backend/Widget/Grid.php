<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Plugin\Block\Backend\Widget;

/**
 * Grid plugin
 */
class Grid extends \Ambros\Common\Plugin\View\Framework\Element\AbstractBlock
{
    
    /**
     * Prepare collection
     * 
     * @return $this
     */
    protected function prepareCollection()
    {
        $this->invokeSubjectMethod('_prepareCollection');
        return $this;
    }
    
    /**
     * Around get prepared collection
     * 
     * @param \Magento\Backend\Block\Widget\Grid $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Data\Collection
     */
    public function aroundGetPreparedCollection(
        \Magento\Backend\Block\Widget\Grid $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $this->prepareCollection();
        return $subject->getCollection();
    }
    
    /**
     * Prepare grid
     *
     * @return $this
     */
    protected function prepareGrid()
    {
        $subject = $this->getSubject();
        $eventManager = $this->getSubjectPropertyValue('_eventManager');
        $eventManager->dispatch(
            'backend_block_widget_grid_prepare_grid_before',
            ['grid' => $subject, 'collection' => $subject->getCollection()]
        );
        $massaction = $subject->getChildBlock('grid.massaction');
        if ($massaction && $massaction->isAvailable()) {
            $massaction->prepareMassactionColumn();
        }
        $this->prepareCollection();
        $columnSet = $subject->getColumnSet();
        if ($subject->hasColumnRenderers()) {
            foreach ($subject->getColumnRenderers() as $renderer => $rendererClass) {
                $columnSet->setRendererType($renderer, $rendererClass);
            }
        }
        if ($subject->hasColumnFilters()) {
            foreach ($subject->getColumnFilters() as $filter => $filterClass) {
                $columnSet->setFilterType($filter, $filterClass);
            }
        }
        $columnSet->setSortable($subject->getSortable());
        $this->invokeSubjectMethod('_prepareFilterButtons');
        return $this;
    }
    
    /**
     * Before to HTML
     *
     * @return $this
     */
    protected function beforeToHtml()
    {
        $this->prepareGrid();
        return $this;
    }
    
}