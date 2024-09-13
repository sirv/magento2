<?php

namespace Sirv\Magento2\Block\Adminhtml\Product\Edit\SirvAssets\ManuallyAdded;

use Magento\Backend\Block\Media\Uploader;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;

/**
 * Sirv manually added content
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
    protected $_template = 'Sirv_Magento2::product/edit/sirv_assets/manually_added_content.phtml';

    /**
     * Json encoder
     *
     * @var \Magento\Framework\Json\EncoderInterface
     */
    protected $_jsonEncoder;

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
        $assetsData = $this->getElement()->getAssetsData();
        if (is_array($assetsData) && count($assetsData)) {
            return $this->_jsonEncoder->encode($assetsData);
        }

        return '[]';
    }

    /**
     * Retrieve config JSON for asset picker
     *
     * @return string
     */
    public function getAssetPickerConfigJson()
    {
        $config = [
            'id' => 'sirv-asset-picker-frame'
        ];

        $assetRepository = \Magento\Framework\App\ObjectManager::getInstance()->get(
            \Magento\Framework\View\Asset\Repository::class
        );
        $config['modelIconUrl'] = $assetRepository->createAsset('Sirv_Magento2::images/icon.3d.3.svg')->getUrl();

        $helper = \Magento\Framework\App\ObjectManager::getInstance()->get(
            \Sirv\Magento2\Helper\Data::class
        );
        $config['sirvBaseUrl'] = 'https://' . $helper->getSirvDomain() . '/';

        $config['folderContentUrl'] = $this->_urlBuilder->getUrl('sirv/ajax/assetsFolderContents');

        return $this->_jsonEncoder->encode($config);
    }

    /**
     * Prepare layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $this->addChild(
            'uploader',
            \Magento\Backend\Block\Media\Uploader::class
        );

        $uploader = $this->getUploader();
        $uploader->setTemplate('Sirv_Magento2::product/edit/sirv_assets/media/uploader.phtml');
        $uploader->getConfig()->setUrl(
            $this->_urlBuilder->getUrl('sirv/assets/upload')
        )->setFileField(
            'sirv-file-upload-input'
        )->setFilters(
            [
                'images' => [
                    'label' => __('Images (.jpg, .jpeg, .png, .gif, .webp, .tif, .tiff, .svg)'),
                    'files' => ['*.jpg', '*.jpeg', '*.png', '*.gif', '*.webp', '*.tif', '*.tiff', '*.svg'],
                ],
                'media' => [
                    'label' => __('Media (.mpg, .mpeg, .m4v, .mp4, .avi, .mov, .ogv)'),
                    'files' => ['*.mpg', '*.mpeg', '*.m4v', '*.mp4', '*.avi', '*.mov', '*.ogv'],
                ],
                'models' => [
                    'label' => __('Media (.usdz, .glb, .dwg)'),
                    'files' => ['*.usdz', '*.glb', '*.dwg'],
                ],
                'all' => ['label' => __('All Files'), 'files' => ['*.*']],
            ]
        );

        return parent::_prepareLayout();
    }

    /**
     * Retrieve uploader block
     *
     * @return \Magento\Backend\Block\Media\Uploader
     */
    public function getUploader()
    {
        return $this->getChildBlock('uploader');
    }

    /**
     * Retrieve uploader block html
     *
     * @return string
     */
    public function getUploaderHtml()
    {
        return $this->getChildHtml('uploader');
    }
}
