<?php

namespace Sirv\Magento2\Block\Html;

/**
 * Head block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Head extends \Magento\Framework\View\Element\Template
{
    /**
     * Data helper
     *
     * @var \Sirv\Magento2\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync
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
     * @param \Sirv\Magento2\Helper\Data $dataHelper
     * @param \Sirv\Magento2\Helper\Sync $syncHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Sirv\Magento2\Helper\Data $dataHelper,
        \Sirv\Magento2\Helper\Sync $syncHelper,
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
        $store = $this->dataHelper->getStoreManager()->getStore();
        $url = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_STATIC, false);
        //NOTE: protocol://host/path_to_magento/pub/static/version{id}/
        if (empty($url)) {
            $url = $this->getViewFileUrl('/');
            //NOTE: protocol://host/path_to_magento/pub/static/version{id}/frontend/Magento/luma/en_US
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
     * Check block visibility
     *
     * @return bool
     */
    public function isVisibile()
    {
        return $this->visibility;
    }
}
