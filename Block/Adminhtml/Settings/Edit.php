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

        $buttonsConfig = [
            'sirv-flush-asset' => [
                'label' => __('Flush assets info cache'),
                'title' => __('Flush assets info cache'),
                'class_name' => \Magento\Backend\Block\Widget\Button\SplitButton::class,
                'button_class' => 'sirv-button sirv-flush-asset-cache-button',
                'class' => 'secondary',
                'options' => [
                    'empty' => [
                        'label' => __('For products without assets'),
                        'title' => __('Clear data without assets from the Sirv extension database cache'),
                        'onclick' => 'setLocation(\'' . $this->getUrl('*/*/flush', ['flush-action' => 'empty']) . '\')',
                        'default' => true
                    ],
                    'all' => [
                        'label' => __('For all products'),
                        'title' => __('Clear all asset\'s data from the Sirv extension database cache'),
                        'onclick' => 'setLocation(\'' . $this->getUrl('*/*/flush', ['flush-action' => 'all']) . '\')',
                        'default' => false
                    ],
                ]
            ],
            'sirv-flush-url' => [
                'label' => __('Flush image cache'),
                'title' => __('Flush image cache'),
                'class_name' => \Magento\Backend\Block\Widget\Button\SplitButton::class,
                'button_class' => 'sirv-button sirv-flush-url-cache-button',
                'class' => 'secondary',
                'options' => [
                    'failed' => [
                        'label' => __('Failed'),
                        'title' => __('Clear failed images from the Sirv extension database cache'),
                        'onclick' => 'return false',
                        'data_attribute' => [
                            'mage-init' => [
                                'button' => [
                                    'event' => 'sirv-sync',
                                    'target' => '[data-role=sirv-synchronizer]',
                                    'eventData' => [
                                        'action' => 'flush-failed'
                                    ]
                                ]
                            ]
                        ],
                        'default' => true
                    ],
                    'all' => [
                        'label' => __('All'),
                        'title' => __('Clear all images from the Sirv extension database cache'),
                        'onclick' => 'return false',
                        'data_attribute' => [
                            'mage-init' => [
                                'button' => [
                                    'event' => 'sirv-sync',
                                    'target' => '[data-role=sirv-synchronizer]',
                                    'eventData' => [
                                        'action' => 'flush-all'
                                    ]
                                ]
                            ]
                        ],
                        'default' => false
                    ],
                    /*
                    'master' => [
                        'label' => __('Master'),
                        'title' => __('Delete images from Sirv and clear database cache (not recommended)'),
                        'onclick' => 'return false',
                        'data_attribute' => [
                            'mage-init' => [
                                'button' => [
                                    'event' => 'sirv-sync',
                                    'target' => '[data-role=sirv-synchronizer]',
                                    'eventData' => [
                                        'action' => 'flush-master'
                                    ]
                                ]
                            ]
                        ],
                        'default' => false
                    ]
                    */
                ]
            ],
            'sirv-sync' => [
                'label' => __('Sync Media Gallery'),
                'class' => 'sirv-button sirv-sync-media-button action-secondary',
                'onclick' => 'return false',
                'data_attribute' => [
                    'mage-init' => [
                        'button' => [
                            'event' => 'sirv-sync',
                            'target' => '[data-role=sirv-synchronizer]',
                            'eventData' => [
                                'action' => 'start-sync'
                            ]
                        ]
                    ]
                ]
            ],
            'sirv-save' => [
                'label' => __('Save Settings'),
                'class' => 'sirv-button sirv-save-config-button primary',
                'data_attribute' => [
                    'mage-init' => [
                        'button' => [
                            'event' => 'save',
                            'target' => '#edit_form'
                        ]
                    ]
                ]
            ]
        ];

        if ($disabled) {
            $buttonsConfig['sirv-flush-asset']['disabled'] = 'disabled';
            $buttonsConfig['sirv-flush-asset']['button_class'] .= ' disabled';
            $buttonsConfig['sirv-flush-url']['disabled'] = 'disabled';
            $buttonsConfig['sirv-flush-url']['button_class'] .= ' disabled';
            $buttonsConfig['sirv-sync']['disabled'] = 'disabled';
            $buttonsConfig['sirv-save']['disabled'] = 'disabled';
        }

        $this->addButton(
            'sirv-flush-asset',
            $buttonsConfig['sirv-flush-asset'],
            0,
            4
        );

        $this->addButton(
            'sirv-flush-url',
            $buttonsConfig['sirv-flush-url'],
            0,
            3
        );

        $this->addButton(
            'sirv-sync',
            $buttonsConfig['sirv-sync'],
            0,
            2
        );

        $this->addButton(
            'sirv-save',
            $buttonsConfig['sirv-save'],
            0,
            1
        );
    }

    /**
     * Get data helper
     *
     * @return \MagicToolbox\Sirv\Helper\Data
     */
    protected function getDataHelper()
    {
        static $helper = null;

        if ($helper == null) {
            $helper = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\MagicToolbox\Sirv\Helper\Data::class);
        }

        return $helper;
    }
}
