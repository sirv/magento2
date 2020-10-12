<?php

namespace MagicToolbox\Sirv\Block\Html;

/**
 * Head block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Head extends \Magento\Framework\View\Element\Template
{
    /**
     * Data helper
     *
     * @var \MagicToolbox\Sirv\Helper\Data
     */
    protected $dataHelper = null;

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
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @param \MagicToolbox\Sirv\Helper\Sync $syncHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \MagicToolbox\Sirv\Helper\Data $dataHelper,
        \MagicToolbox\Sirv\Helper\Sync $syncHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dataHelper = $dataHelper;
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
     * Get base static URL
     *
     * @return string
     */
    public function getBaseStaticUrl()
    {
        $url = $this->dataHelper->baseStaticUrl();
        if (empty($url)) {
            $url = $this->getViewFileUrl('/');
        }

        return $url;
    }

    /**
     * Get fetch mode
     *
     * @return string
     */
    public function getFetchMode()
    {
        return $this->dataHelper->getConfig('auto_fetch');
    }

    /**
     * Whether to use Sirv Media Viewer
     *
     * @return bool
     */
    public function useSirvMediaViewer()
    {
        return $this->dataHelper->useSirvMediaViewer();
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
