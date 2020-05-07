<?php

namespace MagicToolbox\Sirv\Helper;

/**
 * Image helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Image extends \Magento\Catalog\Helper\Image
{
    /**
     * Determine if the data has been initialized or not
     *
     * @var bool
     */
    protected $isInitialized = false;

    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Initialize the data
     *
     * @return void
     */
    protected function initializeData()
    {
        $this->isInitialized = true;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->get(\MagicToolbox\Sirv\Helper\Data::class);
        $this->isSirvEnabled = $dataHelper->isSirvEnabled();
    }
}
