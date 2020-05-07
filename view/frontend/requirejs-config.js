/**
 * Config for RequireJS
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

var config = {
    config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'MagicToolbox_Sirv/js/configurable': true
            },
            'Magento_Swatches/js/swatch-renderer': {
                'MagicToolbox_Sirv/js/swatch-renderer': true
            }
        }
    }
};
