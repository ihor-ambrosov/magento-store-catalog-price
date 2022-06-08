<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Plugin\Ui\Downloadable\DataProvider\Product\Form\Modifier;

/**
 * Used default product form data provider modifier plugin
 */
class UsedDefault extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Price scope
     * 
     * @var \Ambros\StoreCommon\Model\Catalog\Product\PriceScope
     */
    protected $priceScope;
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\DataObject\WrapperFactory $wrapperFactory
     * @param \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
     * @return void
     */
    public function __construct(
        \Ambros\Common\DataObject\WrapperFactory $wrapperFactory,
        \Ambros\StoreCommon\Model\Catalog\Product\PriceScope $priceScope
    )
    {
        parent::__construct($wrapperFactory);
        $this->priceScope = $priceScope;
    }
    
    /**
     * Price used default
     *
     * @return $this
     */
    protected function priceUsedDefault()
    {
        $storeId = (int) $this->getSubjectPropertyValue('locator')->getProduct()->getStoreId();
        if ($this->priceScope->isGlobal() || !$storeId) {
            return $this;
        }
        $arrayManager = $this->getSubjectPropertyValue('arrayManager');
        $meta = $this->getSubjectPropertyValue('meta');
        $this->setSubjectPropertyValue(
            'meta',
            $arrayManager->set(
                $arrayManager->findPath('container_link_price', $meta, null, 'children').'/children/use_default_price/arguments/data/config',
                $meta,
                [
                    'componentType' => \Magento\Ui\Component\Form\Element\Checkbox::NAME,
                    'formElement' => \Magento\Ui\Component\Form\Field::NAME,
                    'component' => 'Magento_Downloadable/js/components/use-price-default-handler',
                    'description' => __('Use Default Value'),
                    'dataScope' => 'use_default_price',
                    'valueMap' => ['false' => '0', 'true' => '1'],
                    'imports' => [
                        'linksPurchasedSeparately' => '${$.provider}:data.product.links_purchased_separately',
                        '__disableTmpl' => ['linksPurchasedSeparately' => false],
                    ],
                ]
            )
        );
        return $this;
    }
    
    /**
     * Around modify meta
     * 
     * @param \Magento\Downloadable\Ui\DataProvider\Product\Form\Modifier\UsedDefault $subject
     * @param \Closure $proceed
     * @param array $meta
     * @return array
     */
    public function aroundModifyMeta(
        \Magento\Downloadable\Ui\DataProvider\Product\Form\Modifier\UsedDefault $subject,
        \Closure $proceed,
        array $meta
    )
    {
        $this->setSubject($subject);
        $this->setSubjectPropertyValue('meta', $meta);
        $this->invokeSubjectMethod('titleUsedDefault', 'links_title');
        $this->invokeSubjectMethod('titleUsedDefault', 'samples_title');
        $this->priceUsedDefault();
        $this->invokeSubjectMethod('titleUsedDefaultInGrid', 'link_title');
        $this->invokeSubjectMethod('titleUsedDefaultInGrid', 'sample_title');
        return $this->getSubjectPropertyValue('meta');
    }
    
}