<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element\MultiSwitchButton;

/**
 * Flush assets button
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class FlushAssets extends \MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element\MultiSwitchButton
{
    /**
     * Get button config
     *
     * @return array
     */
    protected function getButtonConfig()
    {
        $formWidget = $this->getForm()->getParent();
        $config = [
            'label' => 'Flush cache',
            'title' => 'Flush cache',
            'options' => [
                'empty' => [
                    'label' => __('Products without assets'),
                    'title' => __('Clear data for products that do not have assets'),
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-empty-assets',
                        'actionUrl' => $formWidget->getUrl('*/*/flush', ['flush-action' => 'empty']),
                    ]
                ],
                'all' => [
                    'label' => __('All products'),
                    'title' => __('Clear all asset\'s data from the Sirv extension database cache'),
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-all-assets',
                        'actionUrl' => $formWidget->getUrl('*/*/flush', ['flush-action' => 'all']),
                    ]
                ]
            ]
        ];

        return $config;
    }
}
