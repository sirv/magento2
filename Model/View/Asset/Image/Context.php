<?php

namespace Sirv\Magento2\Model\View\Asset\Image;

/**
 * Context for image file asset
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Context extends \Magento\Catalog\Model\View\Asset\Image\Context
{
    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Constructor
     *
     * @param \Magento\Catalog\Model\Product\Media\ConfigInterface $mediaConfig
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Catalog\Model\Product\Media\ConfigInterface $mediaConfig,
        \Magento\Framework\Filesystem $filesystem,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($mediaConfig, $filesystem);
        $this->_logger = $logger;
    }

    /**
     * Get logger
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->_logger;
    }
}
