<?php

namespace MagicToolbox\Sirv\Block\Html;

/**
 * Head block
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Head extends \Magento\Framework\View\Element\Template
{
    /**
     * Sync helper
     *
     * @var \MagicToolbox\Sirv\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Block visibility
     *
     * @var bool
     */
    protected $visibility = false;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \MagicToolbox\Sirv\Helper\Sync $syncHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \MagicToolbox\Sirv\Helper\Sync $syncHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->syncHelper = $syncHelper;
    }

    /**
     * Preparing layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $this->visibility = $this->syncHelper->isAuth();
        return parent::_prepareLayout();
    }

    /**
     * Get Sirv base URL
     *
     * @return string
     */
    public function getSirvUrl()
    {
        return $this->syncHelper->getBaseUrl();
    }

    /**
     * Check block visibility
     *
     * @return bool
     */
    public function isVisibile()
    {
        return $this->visibility;
    }
}
