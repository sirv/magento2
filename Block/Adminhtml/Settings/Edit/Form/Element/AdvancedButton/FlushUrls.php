<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\AdvancedButton;

/**
 * Flush URLs button
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class FlushUrls extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\AdvancedButton
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
            'label' => 'Flush synchronized images',
            'title' => 'Flush synchronized images',
            'options' => [
                'empty' => [
                    'label' => __('Failed images'),
                    'title' => __('Clear failed images from the Sirv extension database cache'),
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-failed',
                    ]
                ],
                'queued' => [
                    'label' => __('Queued images'),
                    'title' => __('Clear queued images from the Sirv extension database cache'),
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-queued',
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
