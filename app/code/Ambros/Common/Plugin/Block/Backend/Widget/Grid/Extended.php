<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Plugin\Block\Backend\Widget\Grid;

/**
 * Extended grid plugin
 */
class Extended extends \Ambros\Common\Plugin\Block\Backend\Widget\Grid
{
    
    /**
     * Prepare columns
     *
     * @return $this
     */
    protected function prepareColumns()
    {
        $this->invokeSubjectMethod('_prepareColumns');
        return $this;
    }
    
    /**
     * After load collection
     * 
     * @return $this
     */
    protected function afterLoadCollection()
    {
        $this->invokeSubjectMethod('_afterLoadCollection');
        return $this;
    }
    
    /**
     * Prepare collection
     *
     * @return $this
     */
    protected function prepareCollection()
    {
        $subject = $this->getSubject();
        $collection = $subject->getCollection();
        if ($collection) {
            if ($collection->isLoaded()) {
                $collection->clear();
            }
            $this->invokeSubjectParentMethod(\Magento\Backend\Block\Widget\Grid::class, '_prepareCollection');
            if (!$this->getSubjectPropertyValue('_isExport')) {
                $collection->load();
                $this->afterLoadCollection();
            }
        }
        return $this;
    }
    
    /**
     * Prepare grid
     *
     * @return $this
     */
    protected function prepareGrid()
    {
        $this->prepareColumns();
        $this->invokeSubjectMethod('_prepareMassactionBlock');
        parent::prepareGrid();
        return $this;
    }
    
    /**
     * Get row field export
     * 
     * @param \Magento\Backend\Block\Widget\Grid\Column $column
     * @param \Magento\Framework\DataObject $row
     * @return string
     */
    protected function getRowFieldExport(
        \Magento\Backend\Block\Widget\Grid\Column $column,
        \Magento\Framework\DataObject $row
    ): string
    {
        return '"'.str_replace(['"', '\\'], ['""', '\\\\'], $column->getRowFieldExport($row)).'"';
    }
    
    /**
     * Get CSV line
     * 
     * @param array $data
     * @return string
     */
    protected function getCsvLine(array $data): string
    {
        return implode(',', $data)."\n";
    }
    
    /**
     * Around get CSV file
     * 
     * @param \Magento\Backend\Block\Widget\Grid\Extended $subject
     * @param \Closure $proceed
     * @return array
     */
    public function aroundGetCsvFile(
        \Magento\Backend\Block\Widget\Grid\Extended $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $path = $this->getSubjectPropertyValue('_path');
        $directory = $this->getSubjectPropertyValue('_directory');
        $this->setSubjectPropertyValue('_isExport', true);
        $this->prepareGrid();
        $name = hash('sha256', microtime());
        $file = $path.'/'.$name.'.csv';
        $directory->create($path);
        $stream = $directory->openFile($file, 'w+');
        $stream->lock();
        $stream->writeCsv($this->invokeSubjectMethod('_getExportHeaders'));
        $this->invokeSubjectMethod('_exportIterateCollection', '_exportCsvItem', [$stream]);
        if ($subject->getCountTotals()) {
            $stream->writeCsv($this->invokeSubjectMethod('_getExportTotals'));
        }
        $stream->unlock();
        $stream->close();
        return ['type' => 'filename', 'value' => $file, 'rm' => true];
    }
    
    /**
     * Around get CSV
     * 
     * @param \Magento\Backend\Block\Widget\Grid\Extended $subject
     * @param \Closure $proceed
     * @return string
     */
    public function aroundGetCsv(
        \Magento\Backend\Block\Widget\Grid\Extended $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $csv = '';
        $this->setSubjectPropertyValue('_isExport', true);
        $this->prepareGrid();
        $collection = $subject->getCollection();
        $collection->getSelect()->limit();
        $collection->setPageSize(0);
        $collection->load();
        $this->afterLoadCollection();
        $data = [];
        foreach ($subject->getColumns() as $column) {
            if (!$column->getIsSystem()) {
                $data[] = '"'.$column->getExportHeader().'"';
            }
        }
        $csv .= $this->getCsvLine($data);
        foreach ($collection as $item) {
            $data = [];
            foreach ($subject->getColumns() as $column) {
                if (!$column->getIsSystem()) {
                    $data[] = $this->getRowFieldExport($column, $item);
                }
            }
            $csv .= $this->getCsvLine($data);
        }
        if ($subject->getCountTotals()) {
            $data = [];
            foreach ($subject->getColumns() as $column) {
                if (!$column->getIsSystem()) {
                    $data[] = $this->getRowFieldExport($column, $subject->getTotals());
                }
            }
            $csv .= $this->getCsvLine($data);
        }
        return $csv;
    }
    
    /**
     * Around get XML
     * 
     * @param \Magento\Backend\Block\Widget\Grid\Extended $subject
     * @param \Closure $proceed
     * @return string
     */
    public function aroundGetXml(
        \Magento\Backend\Block\Widget\Grid\Extended $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $this->setSubjectPropertyValue('_isExport', true);
        $this->prepareGrid();
        $collection = $subject->getCollection();
        $collection->getSelect()->limit();
        $collection->setPageSize(0);
        $collection->load();
        $this->afterLoadCollection();
        $indexes = [];
        foreach ($subject->getColumns() as $column) {
            if (!$column->getIsSystem()) {
                $indexes[] = $column->getIndex();
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<items>';
        foreach ($collection as $item) {
            $xml .= $item->toXml($indexes);
        }
        if ($subject->getCountTotals()) {
            $xml .= $subject->getTotals()->toXml($indexes);
        }
        $xml .= '</items>';
        return $xml;
    }
    
    /**
     * Around get excel file
     * 
     * @param \Magento\Backend\Block\Widget\Grid\Extended $subject
     * @param \Closure $proceed
     * @param string $sheetName
     * @return array
     */
    public function aroundGetExcelFile(
        \Magento\Backend\Block\Widget\Grid\Extended $subject,
        \Closure $proceed,
        $sheetName = ''
    )
    {
        $this->setSubject($subject);
        $path = $this->getSubjectPropertyValue('_path');
        $directory = $this->getSubjectPropertyValue('_directory');
        $this->setSubjectPropertyValue('_isExport', true);
        $this->prepareGrid();
        $convert = new \Magento\Framework\Convert\Excel(
            $subject->getCollection()->getIterator(),
            [$this, 'getRowRecord']
        );
        $name = hash('sha256', microtime());
        $file = $path.'/'.$name.'.xml';
        $directory->create($path);
        $stream = $directory->openFile($file, 'w+');
        $stream->lock();
        $convert->setDataHeader($this->invokeSubjectMethod('_getExportHeaders'));
        if ($subject->getCountTotals()) {
            $convert->setDataFooter($this->invokeSubjectMethod('_getExportTotals'));
        }
        $convert->write($stream, $sheetName);
        $stream->unlock();
        $stream->close();
        return ['type' => 'filename', 'value' => $file, 'rm' => true];
    }
    
    /**
     * Around get excel
     * 
     * @param \Magento\Backend\Block\Widget\Grid\Extended $subject
     * @param \Closure $proceed
     * @return string
     */
    public function aroundGetExcel(
        \Magento\Backend\Block\Widget\Grid\Extended $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $this->setSubjectPropertyValue('_isExport', true);
        $this->prepareGrid();
        $collection = $subject->getCollection();
        $collection->getSelect()->limit();
        $collection->setPageSize(0);
        $collection->load();
        $this->afterLoadCollection();
        $headers = [];
        $data = [];
        foreach ($subject->getColumns() as $column) {
            if (!$column->getIsSystem()) {
                $headers[] = $column->getHeader();
            }
        }
        $data[] = $headers;
        foreach ($collection as $item) {
            $row = [];
            foreach ($subject->getColumns() as $column) {
                if (!$column->getIsSystem()) {
                    $row[] = $column->getRowField($item);
                }
            }
            $data[] = $row;
        }
        if ($subject->getCountTotals()) {
            $row = [];
            foreach ($subject->getColumns() as $column) {
                if (!$column->getIsSystem()) {
                    $row[] = $column->getRowField($subject->getTotals());
                }
            }
            $data[] = $row;
        }
        $convert = new \Magento\Framework\Convert\Excel(new \ArrayIterator($data));
        return $convert->convert('single_sheet');
    }
    
}