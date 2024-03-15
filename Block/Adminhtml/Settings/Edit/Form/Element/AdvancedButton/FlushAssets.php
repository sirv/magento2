<?php

namespace Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\AdvancedButton;

/**
 * Flush assets button
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class FlushAssets extends \Sirv\Magento2\Block\Adminhtml\Settings\Edit\Form\Element\AdvancedButton
{
    /**
     * Assets model factory
     *
     * @var \Sirv\Magento2\Model\AssetsFactory
     */
    protected $assetsModelFactory = null;

    /**
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param \Sirv\Magento2\Model\AssetsFactory $assetsModelFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        \Sirv\Magento2\Model\AssetsFactory $assetsModelFactory,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->assetsModelFactory = $assetsModelFactory;
    }

    /**
     * Get button config
     *
     * @return array
     */
    protected function getButtonConfig()
    {
        $assetsModel = $this->assetsModelFactory->create();
        $collection = $assetsModel->getCollection();
        $collection->setPageSize(1000);
        $pageCount = $collection->getLastPageNumber();
        $currentPage = 1;
        $total = 0;
        $empty = 0;
        while ($currentPage <= $pageCount) {
            $collection->setCurPage($currentPage);
            foreach ($collection as $item) {
                $total++;
                $contents = $item->getData('contents');
                $contents = json_decode($contents);
                if (is_object($contents) && isset($contents->assets) && is_array($contents->assets) && !empty($contents->assets)) {
                    continue;
                }
                $empty++;
            }
            $currentPage++;
        }

        $formWidget = $this->getForm()->getParent();
        $config = [
            'label' => 'Clear cache',
            'title' => 'Flush product galleries',
            'options' => [
                'empty' => [
                    'label' => __('Products without assets') . ' (' . $empty . ')',
                    'title' => __('Clear data for products that do not have assets'),
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-empty-assets',
                        'actionUrl' => $formWidget->getUrl('*/*/flush', ['flush-action' => 'empty']),
                    ]
                ],
                'notempty' => [
                    'label' => __('Products with assets') . ' (' . ($total - $empty) . ')',
                    'title' => __('Clear data for products that have assets'),
                    'showLoader' => true,
                    'event' => 'sirv-sync',
                    'target' => '[data-role=sirv-synchronizer]',
                    'eventData' => [
                        'action' => 'flush-notempty-assets',
                        'actionUrl' => $formWidget->getUrl('*/*/flush', ['flush-action' => 'notempty']),
                    ]
                ],
                'all' => [
                    'label' => __('All products') . ' (' . $total . ')',
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
