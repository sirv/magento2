<?php

namespace Sirv\Magento2\Block\Adminhtml\Product\Edit\SirvAssets\AutomaticallyAdded;

/**
 * Sirv automatically added content
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Content extends \Magento\Backend\Block\Widget
{
    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'Sirv_Magento2::product/edit/sirv_assets/automatically_added_content.phtml';

    /**
     * Json encoder
     *
     * @var \Magento\Framework\Json\EncoderInterface
     */
    protected $_jsonEncoder;

    /**
     * If content is loaded with AJAX
     *
     * @var boolean
     */
    protected $isAjaxLoaded = true;

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        array $data = []
    ) {
        $this->_jsonEncoder = $jsonEncoder;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve assets JSON
     *
     * @return string
     */
    public function getAssetsJson()
    {
        if ($this->isAjaxLoaded) {
            return '[]';
        }

        $assetsData = $this->getElement()->getAssetsData();

        if (is_array($assetsData) && count($assetsData)) {
            return $this->_jsonEncoder->encode($assetsData);
        }

        return '[]';
    }

    /**
     * Retrieve URL for AJAX call
     *
     * @return string
     */
    public function getAssetsGalleryControllerUrl()
    {
        return $this->_urlBuilder->getUrl('sirv/ajax/assetsGallery');
    }

    /**
     * Retrieve URL for refreshing cache
     *
     * @return string
     */
    public function getRefreshCacheUrl()
    {
        return $this->_urlBuilder->getUrl('sirv/ajax/assetsCache');
    }
}
