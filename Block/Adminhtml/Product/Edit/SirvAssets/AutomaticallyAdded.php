<?php

namespace Sirv\Magento2\Block\Adminhtml\Product\Edit\SirvAssets;

/**
 * Sirv automatically added assets
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class AutomaticallyAdded extends \Magento\Framework\View\Element\AbstractBlock
{
    /**
     * Block html id
     *
     * @var string
     */
    protected $htmlId = 'sirv_automatically_added_assets';

    /**
     * Content block name
     *
     * @var string
     */
    protected $contentBlockName = 'sirv_automatically_added_content';

    /**
     * Gallery name
     *
     * @var string
     */
    protected $name = 'sirv_assets_gallery[automatically_added]';

    /**
     * Form name
     *
     * @var string
     */
    protected $formName = 'product_form';

    /**
     * Registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Framework\Registry $registry,
        $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Get element HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        return $this->getContentHtml();
    }

    /**
     * Get assets data
     *
     * @return array
     */
    public function getAssetsData()
    {
        $product = $this->registry->registry('current_product');
        if (!$product) {
            return [];
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->get(\Sirv\Magento2\Helper\Data::class);

        $assetsData = $dataHelper->getAssetsData($product);
        if (empty($assetsData)) {
            return [];
        }

        $assets = $assetsData['assets'] ?? [];
        if (empty($assets)) {
            return [];
        }

        $syncHelper = $objectManager->get(\Sirv\Magento2\Helper\Sync::class);
        $baseUrl = $syncHelper->getBaseUrl();
        $assetRepository = $objectManager->get(\Magento\Framework\View\Asset\Repository::class);
        $modelThumbUrl = $assetRepository->createAsset('Sirv_Magento2::images/icon.3d.3.svg')->getUrl();

        $position = 0;
        $data = [];
        foreach ($assets as $asset) {
            $_asset = [];
            $_asset['file'] = $assetsData['dirname'] . '/' . $asset['name'];
            $_asset['url'] = $_asset['viewUrl'] = $baseUrl . $_asset['file'];
            if ($asset['type'] == 'video') {
                $_asset['url'] .= '?thumb';
            } elseif ($asset['type'] == 'spin') {
                $_asset['url'] .= '?thumb';
            } elseif ($asset['type'] == 'model') {
                $_asset['url'] = $modelThumbUrl;
                $_asset['viewUrl'] .= '?embed';
            } elseif ($asset['type'] == 'image') {
                //
            } else {
                continue;
            }

            $_asset['size'] = $asset['size'] ?? 0;
            $_asset['width'] = $asset['width'] ?? 0;
            $_asset['height'] = $asset['height'] ?? 0;

            $_asset['position'] = $position;
            $position++;

            $data[] = $_asset;
        }

        return $data;
    }

    /**
     * Prepares content block
     *
     * @return string
     */
    public function getContentHtml()
    {
        /* @var $content \Sirv\Magento2\Block\Adminhtml\Product\Edit\SirvAssets\AutomaticallyAdded\Content */
        $content = $this->getChildBlock($this->contentBlockName);

        $content->setId($this->getHtmlId() . '_content');
        $content->setElement($this);
        $content->setFormName($this->formName);
        $this->setReadonly(true);

        return $content->toHtml();
    }

    /**
     * Get HTML id
     *
     * @return string
     */
    protected function getHtmlId()
    {
        return $this->htmlId;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get product ID
     *
     * @return string
     */
    public function getProductId()
    {
        $product = $this->registry->registry('current_product');
        if (!$product) {
            return '';
        }

        return $product->getId();
    }

    /**
     * To HTML
     *
     * @return string
     */
    public function toHtml()
    {
        return $this->getElementHtml();
    }
}
