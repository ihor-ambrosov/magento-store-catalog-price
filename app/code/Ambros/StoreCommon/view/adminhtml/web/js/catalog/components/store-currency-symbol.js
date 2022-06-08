/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
define([
    'Magento_Ui/js/form/element/select'
], function (Select) {
    'use strict';

    return Select.extend({
        defaults: {
            currenciesForStores: {},
            tracks: {
                currency: true
            }
        },

        /**
         * Set differed from default
         *
         * @param {String} value
         */
        setDifferedFromDefault: function (value) {
            this.currency = this.currenciesForStores[value];
            return this._super();
        }
    });
});
