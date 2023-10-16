<?php

namespace Sirv\Magento2\Observer;

/**
 * Observer to replace product media gallery view
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ReplaceGallery implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Data helper
     *
     * @var \Sirv\Magento2\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Whether to use Sirv Media Viewer
     *
     * @var bool
     */
    protected $useSirvMediaViewer = false;

    /**
     * Constructor
     *
     * @param \Sirv\Magento2\Helper\Data $dataHelper
     * @return void
     */
    public function __construct(
        \Sirv\Magento2\Helper\Data $dataHelper
    ) {
        $this->dataHelper = $dataHelper;
        $this->isSirvEnabled = $dataHelper->isSirvEnabled();
        $this->useSirvMediaViewer = $dataHelper->useSirvMediaViewer();
    }

    /**
     * Execute method
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->isSirvEnabled && $this->useSirvMediaViewer) {
            /** @var \Magento\Framework\View\Layout $layout */
            $layout = $observer->getLayout();
            $block = $layout->getBlock('product.info.media.image');
            if ($block) {
                $template = $block->getTemplate();
                $block->setOriginalTemplate($template);
                $block->setTemplate('Sirv_Magento2::product/view/gallery.phtml');
            }
        }
    }
}
