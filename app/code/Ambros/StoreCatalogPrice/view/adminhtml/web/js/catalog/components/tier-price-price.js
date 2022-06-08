/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
define([
    'Magento_Ui/js/form/element/abstract'
], function (AbstractElement) {
    'use strict';

    return AbstractElement.extend({
        defaults: {
            imports: {
                priceValue: '${ $.provider }:data.product.price',
                addbefore: '${ $.parentName }:currency'
            },
            tracks: {
                addbefore: true
            }
        }
    });
});