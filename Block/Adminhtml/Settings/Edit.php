<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings;

/**
 * Adminhtml settings form container
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Edit extends \Magento\Backend\Block\Widget\Form\Container
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        //NOTE: used for delete button
        $this->_objectId = 'sirv_settings_id';
        $this->_controller = 'adminhtml_settings';
        $this->_blockGroup = 'MagicToolbox_Sirv';

        parent::_construct();

        $this->removeButton('back');
        $this->removeButton('reset');
        $this->removeButton('save');

        $dataHelper = $this->getDataHelper();
        $disabled = !(
            $dataHelper->getConfig('account') &&
            $dataHelper->getConfig('client_id') &&
            $dataHelper->getConfig('client_secret')
        );

        $config = [
            'id' => 'sirv-save-config-button',
            'label' => __('Save Settings'),
            'class' => 'sirv-button primary',
            'data_attribute' => [
                'mage-init' => [
                    'sirvButton' => [
                        'event' => 'save',
                        'target' => '#edit_form',
                        'showLoader' => true,
                    ]
                ]
            ]
        ];

        if ($disabled) {
            $config['disabled'] = 'disabled';
        }

        $this->addButton(
            'sirv-save',
            $config,
            0,
            1
        );
    }

    /**
     * Get data helper
     *
     * @return \MagicToolbox\Sirv\Helper\Data\Backend
     */
    protected function getDataHelper()
    {
        static $helper = null;

        if ($helper == null) {
            $helper = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\MagicToolbox\Sirv\Helper\Data\Backend::class);
        }

        return $helper;
    }
}
