<?php

namespace MagicToolbox\Sirv\Observer;

/**
 * Observer to replace product media gallery view
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ReplaceGalleryObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Data helper
     *
     * @var \MagicToolbox\Sirv\Helper\Data
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
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @return void
     */
    public function __construct(
        \MagicToolbox\Sirv\Helper\Data $dataHelper
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
                $block->setTemplate('MagicToolbox_Sirv::product/view/gallery.phtml');
            }
        }
    }
}
