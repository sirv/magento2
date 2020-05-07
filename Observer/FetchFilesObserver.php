<?php

namespace MagicToolbox\Sirv\Observer;

/**
 * Observer that call files fetching
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
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
