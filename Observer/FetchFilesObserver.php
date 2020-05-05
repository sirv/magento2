<?php

namespace MagicToolbox\Sirv\Observer;

/**
 * Observer that call files fetching
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class FetchFilesObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Sync helper
     *
     * @var \MagicToolbox\Sirv\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Constructor
     *
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @param \MagicToolbox\Sirv\Helper\Sync $syncHelper
     * @return void
     */
    public function __construct(
        \MagicToolbox\Sirv\Helper\Data $dataHelper,
        \MagicToolbox\Sirv\Helper\Sync $syncHelper
    ) {
        $this->syncHelper = $syncHelper;
        $this->isSirvEnabled = $dataHelper->isSirvEnabled();
    }

    /**
     * Execute method
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        // $request = $observer->getRequest();
        /** @var \Magento\Framework\App\Response\Http\Interceptor $response */
        // $response = $observer->getResponse();
        if ($this->isSirvEnabled) {
            $this->syncHelper->doFetch();
        }
    }
}
