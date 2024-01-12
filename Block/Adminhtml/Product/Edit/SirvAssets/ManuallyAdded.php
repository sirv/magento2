<?php

namespace Sirv\Magento2\Block\Adminhtml\Product\Edit\SirvAssets;

/**
 * Sirv manually added assets
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ManuallyAdded extends \Magento\Framework\View\Element\AbstractBlock
{
    /**
     * Block html id
     *
     * @var string
     */
    protected $htmlId = 'sirv_manually_added_assets';

    /**
     * Content block name
     *
     * @var string
     */
    protected $contentBlockName = 'sirv_manually_added_content';

    /**
     * Gallery name
     *
     * @var string
     */
    protected $name = 'sirv_assets_gallery[manually_added]';

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
        $assetsModel = $objectManager->get(\Sirv\Magento2\Model\ManuallyAddedAssets::class);
        $collection = $assetsModel->getCollection();
        $productId = $product->getId();
        $collection->addFieldToFilter('product_id', $productId);
        $collection->setOrder('position', \Magento\Framework\Data\Collection::SORT_ORDER_ASC);

        $assets = $collection->getData() ?: [];
        if (empty($assets)) {
            return [];
        }

        $syncHelper = $objectManager->get(\Sirv\Magento2\Helper\Sync::class);
        $baseUrl = $syncHelper->getBaseUrl();
        $assetRepository = $objectManager->get(\Magento\Framework\View\Asset\Repository::class);
        $modelThumbUrl = $assetRepository->createAsset('Sirv_Magento2::images/icon.3d.3.svg')->getUrl();

        $data = [];
        foreach ($assets as $asset) {
            $_asset = [];
            $_asset['id'] = $_asset['value_id'] = (int)$asset['id'];
            $_asset['file'] = $asset['path'];
            $_asset['name'] = basename($asset['path']);
            $_asset['url'] = $_asset['viewUrl'] = $baseUrl . $_asset['file'];
            if ($asset['type'] == \Sirv\Magento2\Model\ManuallyAddedAssets::IMAGE_ASSET) {
                $_asset['type'] = 'image';
            } else if ($asset['type'] == \Sirv\Magento2\Model\ManuallyAddedAssets::VIDEO_ASSET) {
                $_asset['type'] = 'video';
                $_asset['url'] .= '?thumb';
            } else if ($asset['type'] == \Sirv\Magento2\Model\ManuallyAddedAssets::SPIN_ASSET) {
                $_asset['type'] = 'spin';
                $_asset['url'] .= '?thumb';
            } else if ($asset['type'] == \Sirv\Magento2\Model\ManuallyAddedAssets::MODEL_ASSET) {
                $_asset['type'] = 'model';
                $_asset['url'] = $modelThumbUrl;
                $_asset['viewUrl'] .= '?embed';
            } else {
                continue;
            }
            $_asset['size'] = (int)($asset['size'] ?? 0);
            $_asset['width'] = (int)($asset['width'] ?? 0);
            $_asset['height'] = (int)($asset['height'] ?? 0);
            $_asset['position'] = (int)($asset['position'] ?? 0);

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
        /* @var $content \Sirv\Magento2\Block\Adminhtml\Product\Edit\SirvAssets\ManuallyAdded\Content */
        $content = $this->getChildBlock($this->contentBlockName);

        $content->setId($this->getHtmlId() . '_content');
        $content->setElement($this);
        $content->setFormName($this->formName);

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
