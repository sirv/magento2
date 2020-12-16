/**
 * Config for RequireJS
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */

var config = {
    paths: {
        'sirv/template': 'MagicToolbox_Sirv/templates'
    },
    map: {
        '*': {
            sirvButton: 'MagicToolbox_Sirv/js/button',
            sirvAdvancedButton: 'MagicToolbox_Sirv/js/advanced-button',
            sirvEditFolderOption: 'MagicToolbox_Sirv/js/edit-folder-option',
            sirvTooltip: 'MagicToolbox_Sirv/js/tooltip',
            sirvChangelog: 'MagicToolbox_Sirv/js/changelog',
            sirvSynchronizer: 'MagicToolbox_Sirv/js/synchronizer',
            sirvUsage: 'MagicToolbox_Sirv/js/usage'
        }
    }
};
