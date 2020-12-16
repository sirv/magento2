<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element\AdvancedButton;

/**
 * Flush Magento images cache button
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class FlushMagentoImagesCache extends \MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element\AdvancedButton
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
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-magento-images-cache',
                        'actionUrl' => $formWidget->getUrl('*/*/flushmagentoimagescache', []),
                    ]
                ]
            ]
        ];

        return $config;
    }
}
