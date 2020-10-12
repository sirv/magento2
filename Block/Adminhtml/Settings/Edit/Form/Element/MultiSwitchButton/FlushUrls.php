<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element\MultiSwitchButton;

/**
 * Flush URLs button
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class FlushUrls extends \MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element\MultiSwitchButton
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
                    'label' => __('Failed'),
                    'title' => __('Clear failed images from the Sirv extension database cache'),
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-failed',
                    ]
                ],
                'all' => [
                    'label' => __('All'),
                    'title' => __('Clear all images from the Sirv extension database cache'),
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-all',
                    ]
                ]
            ]
        ];

        return $config;
    }
}
