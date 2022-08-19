/**
 * Config for RequireJS
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

var config = {
    paths: {
        mageText: 'mage/requirejs/text',
        text: 'Sirv_Magento2/js/text'
    },
    config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'Sirv_Magento2/js/configurable': true
            },
            'Magento_Swatches/js/swatch-renderer': {
                'Sirv_Magento2/js/swatch-renderer': true
            },
            'Firebear_ConfigurableProducts/js/swatch-renderer': {
                'Sirv_Magento2/js/swatch-renderer': true
            }
        },
        mageText: {
            'headers': {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }
    },
    map: {
        '*': {
            sirvAssetsCache: 'Sirv_Magento2/js/assets-cache'
        }
    }
};
