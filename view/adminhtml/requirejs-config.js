/**
 * Config for RequireJS
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

var config = {
    paths: {
        'sirv/template': 'Sirv_Magento2/templates'
    },
    map: {
        '*': {
            sirvButton: 'Sirv_Magento2/js/button',
            sirvAdvancedButton: 'Sirv_Magento2/js/advanced-button',
            sirvEditFolderOption: 'Sirv_Magento2/js/edit-folder-option',
            sirvAssets: 'Sirv_Magento2/js/assets',
            sirvTooltip: 'Sirv_Magento2/js/tooltip',
            sirvChangelog: 'Sirv_Magento2/js/changelog',
            sirvSynchronizer: 'Sirv_Magento2/js/synchronizer',
            sirvUsage: 'Sirv_Magento2/js/usage'
        }
    }
};
