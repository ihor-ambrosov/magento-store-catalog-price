<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\DownloadableImportExport\Import\Product\Type;

/**
 * Downloadable product import plugin
 */
class Downloadable extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Fill data title link
     *
     * @param array $options
     * @return array
     */
    protected function fillDataTitleLink(array $options)
    {
        $result = [];
        $connection = $this->getSubjectPropertyValue('connection');
        $resource = $this->getSubjectPropertyValue('_resource');
        $downloadableHelper = $this->getSubjectPropertyValue('downloadableHelper');
        $dataLinkTitle = $this->getSubjectPropertyValue('dataLinkTitle');
        $dataLinkPrice = $this->getSubjectPropertyValue('dataLinkPrice');
        $productIds = $this->getSubjectPropertyValue('productIds');
        $existingOptions = $connection->fetchAll(
            $connection->select()->from(['dl' => $resource->getTableName('downloadable_link')], [
                    'link_id',
                    'product_id',
                    'sort_order',
                    'number_of_downloads',
                    'is_shareable',
                    'link_url',
                    'link_file',
                    'link_type',
                    'sample_url',
                    'sample_file',
                    'sample_type'
            ])
            ->joinLeft(
                ['dlp' => $resource->getTableName('ambros_store__downloadable_link_price')],
                'dl.link_id = dlp.link_id AND dlp.store_id='.\Magento\Store\Model\Store::DEFAULT_STORE_ID,
                ['price_id', 'store_id']
            )
            ->where('product_id in (?)', $productIds)
        );
        foreach ($options as $option) {
            $existingOption = $downloadableHelper>fillExistOptions($dataLinkTitle, $option, $existingOptions);
            if (!empty($existingOption)) {
                $result['title'][] = $existingOption;
            }
            $existingOption = $downloadableHelper->fillExistOptions($dataLinkPrice, $option, $existingOptions);
            if (!empty($existingOption)) {
                $result['price'][] = $existingOption;
            }
        }
        return $result;
    }
    
    /**
     * Save link options
     *
     * @return $this
     */
    protected function saveLinkOptions()
    {
        $connection = $this->getSubjectPropertyValue('connection');
        $resource = $this->getSubjectPropertyValue('_resource');
        $downloadableHelper = $this->getSubjectPropertyValue('downloadableHelper');
        $options = $this->getSubjectPropertyValue('cachedOptions');
        $dataLink = $this->getSubjectPropertyValue('dataLink');
        $dataLinkTitle = $this->getSubjectPropertyValue('dataLinkTitle');
        $dataLinkPrice = $this->getSubjectPropertyValue('dataLinkPrice');
        $options['link'] = $this->invokeSubjectMethod('uploadLinkFiles', $options['link']);
        $filledDataLink = $this->invokeSubjectMethod('fillDataLink', $dataLink, $options['link']);
        $connection->insertOnDuplicate(
            $resource->getTableName('downloadable_link'),
            $downloadableHelper->prepareDataForSave($dataLink, $filledDataLink)
        );
        $filledDataLink = $this->fillDataTitleLink($options['link']);
        $connection->insertOnDuplicate(
            $resource->getTableName('downloadable_link_title'),
            $downloadableHelper->prepareDataForSave($dataLinkTitle, $filledDataLink['title'])
        );
        if (count($filledDataLink['price'])) {
            $connection->insertOnDuplicate(
                $resource->getTableName('ambros_store__downloadable_link_price'),
                $downloadableHelper->prepareDataForSave($dataLinkPrice, $filledDataLink['price'])
            );
        }
        return $this;
    }
    
    /**
     * Save options
     *
     * @return $this
     */
    protected function saveOptions()
    {
        $options = $this->getSubjectPropertyValue('cachedOptions');
        if (!empty($options['sample'])) {
            $this->invokeSubjectMethod('saveSampleOptions');
        }
        if (!empty($options['link'])) {
            $this->saveLinkOptions();
        }
        return $this;
    }
    
    /**
     * Around save data
     * 
     * @param \Magento\DownloadableImportExport\Model\Import\Product\Type\Downloadable $subject
     * @param \Closure $proceed
     * @return \Magento\CatalogImportExport\Model\Import\Product\Type\AbstractType
     */
    public function aroundSaveData(
        \Magento\DownloadableImportExport\Model\Import\Product\Type\Downloadable $subject,
        \Closure $proceed
    )
    {
        $this->setSubject($subject);
        $entityModel = $this->getSubjectPropertyValue('_entityModel');
        $type = $this->getSubjectPropertyValue('_type');
        $options = $this->getSubjectPropertyValue('cachedOptions');
        $newSku = $entityModel->getNewSku();
        while ($bunch = $entityModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$entityModel->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }
                $rowSku = strtolower($rowData[\Magento\CatalogImportExport\Model\Import\Product::COL_SKU]);
                $productData = $newSku[$rowSku];
                if ($type != $productData['type_id']) {
                    continue;
                }
                $this->invokeSubjectMethod('parseOptions', $rowData, $productData[$this->invokeSubjectMethod('getProductEntityLinkField')]);
            }
            if (!empty($options) || !empty($options['link'])) {
                $this->saveOptions();
                $this->invokeSubjectMethod('clear');
            }
        }
        return $subject;
    }
    
}