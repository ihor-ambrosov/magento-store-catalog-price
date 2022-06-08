<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Model\DownloadableGraphQl\Resolver\Product;

/**
 * Downloadable product options resolver plugin
 */
class DownloadableOptions extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Around resolve
     * 
     * @param \Magento\DownloadableGraphQl\Model\Resolver\Product\DownloadableOptions $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param \Magento\Framework\GraphQl\Schema\Type\ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return null|array
     */
    public function aroundResolve(
        $subject,
        \Closure $proceed,
        \Magento\Framework\GraphQl\Config\Element\Field $field,
        $context,
        \Magento\Framework\GraphQl\Schema\Type\ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        $this->setSubject($subject);
        if (!isset($value['model'])) {
            throw new \Magento\Framework\Exception\LocalizedException(__('"model" value should be specified'));
        }
        $product = $value['model'];
        if ($product->getTypeId() !== \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE) {
            return null;
        }
        $productId = (int) $product->getId();
        $storeId = (int) $product->getStoreId();
        if ($field->getName() === 'downloadable_product_links') {
            return $this->invokeSubjectMethod(
                'formatLinks',
                $this->getSubjectPropertyValue('linkCollection')
                    ->addTitleToResult($storeId)
                    ->addPriceToResult($storeId)
                    ->addProductToFilter($productId)
            );
        } elseif ($field->getName() === 'downloadable_product_samples') {
            return $this->invokeSubjectMethod(
                'formatSamples',
                $this->getSubjectPropertyValue('sampleCollection')
                    ->addTitleToResult($storeId)
                    ->addProductToFilter($productId)
            );
        }
    }
    
}