<?php

namespace Sirv\Magento2\Block\Adminhtml;

/**
 * Synchronizer block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Synchronizer extends \Magento\Backend\Block\Template
{
    /**
     * Path to template file
     *
     * @var string
     */
    protected $_template = 'Sirv_Magento2::synchronizer.phtml';

    /**
     * Sync helper factory
     *
     * @var \Sirv\Magento2\Helper\Sync\BackendFactory
     */
    protected $syncHelperFactory = null;

    /**
     * Sync data
     *
     * @var array
     */
    protected $syncData = [];

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->syncHelperFactory = $syncHelperFactory;
    }

    /**
     * Get sync data
     *
     * @return array
     */
    public function getSyncData()
    {
        if (empty($this->syncData)) {
            $this->syncData = [
                'total' => 0,
                'synced' => 0,
                'queued' => 0,
                'failed' => 0,
                'cached' => 0,
                'synced-percent' => 0,
                'queued-percent' => 0,
                'failed-percent' => 0,
            ];

            $syncHelper = $this->syncHelperFactory->create();
            $data = $syncHelper->getSyncData(true);

            $total = $data['total'];
            if ($total) {
                $synced = $data['synced'];
                $queued = $data['queued'];
                $failed = $data['failed'];
                $cached = $synced + $queued + $failed;

                $scale = 100;

                $syncedPercent = floor($synced * 100 * $scale / $total);
                $queuedPercent = floor($queued * 100 * $scale / $total);
                $failedPercent = floor($failed * 100 * $scale / $total);

                if ($total == $cached) {
                    $cachedPercent = $syncedPercent + $queuedPercent + $failedPercent;
                    $rest = 100 * $scale - $cachedPercent;
                    if ($rest > 0) {
                        if ($syncedPercent) {
                            $syncedPercent += $rest;
                        } elseif ($queuedPercent) {
                            $queuedPercent += $rest;
                        } else {
                            $failedPercent += $rest;
                        }
                    }
                }
                $syncedPercent = $syncedPercent / $scale;
                $queuedPercent = $queuedPercent / $scale;
                $failedPercent = $failedPercent / $scale;

                $this->syncData = [
                    'total' => $total,
                    'synced' => $synced,
                    'queued' => $queued,
                    'failed' => $failed,
                    'cached' => $cached,
                    'synced-percent' => $syncedPercent,
                    'queued-percent' => $queuedPercent,
                    'failed-percent' => $failedPercent,
                ];
            }
        }

        return $this->syncData;
    }

    /**
     * Get AJAX URL
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('sirv/ajax/synchronize');
    }

    /**
     * Get buttons html
     *
     * @param integer $failed
     * @param integer $cached
     * @return string
     */
    public function getButtonsHtml()
    {
        $buttonConfig = [
            'id' => 'sirv-sync-media-button',
            'label' => __('Sync Media Gallery'),
            'title' => __('Sync Media Gallery'),
            'class' => 'sirv-button action-secondary',
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
        ];
        $block = $this->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class, 'mt-sirv-sync');
        $block->setData($buttonConfig);

        return $block->toHtml();
    }
}
