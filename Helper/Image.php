<?php

namespace MagicToolbox\Sirv\Helper;

/**
 * Image helper
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
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
