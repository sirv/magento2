<?php

namespace MagicToolbox\Sirv\Helper;

/**
 * Sirv Media Viewer helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class MediaViewer extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Viewer contents
     *
     * 1 Magento images/videos
     * 2 Magento images/videos + Sirv assets
     * 3 Sirv assets + Magento images/videos
     * 4 Sirv assets only
     */
    const MAGENTO_ASSETS = 1;
    const MAGENTO_AND_SIRV_ASSETS = 2;
    const SIRV_AND_MAGENTO_ASSETS = 3;
    const SIRV_ASSETS = 4;

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
     * Assets model factory
     *
     * @var \MagicToolbox\Sirv\Model\AssetsFactory
     */
    protected $assetsModelFactory = null;

    /**
     * Assets data
     *
     * @var array
     */
    static protected $assetsData = [];

    /**
     * View config
     *
     * @var \Magento\Framework\View\ConfigInterface
     */
    protected $presentationConfig;

    /**
     * Image helper
     *
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    /**
     * Gallery block
     *
     * @var \Magento\Catalog\Block\Product\View\Gallery
     */
    protected $galleryBlock = null;

    /**
     * Viewer slides
     *
     * @var array
     */
    protected $viewerSlides;

    /**
     * Active slide index
     *
     * @var integer
     */
    protected $activeSlideIndex;

    /**
     * cURL resource
     *
     * @var resource
     */
    protected static $curlHandle = null;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @param \MagicToolbox\Sirv\Helper\Sync $syncHelper
     * @param \MagicToolbox\Sirv\Model\AssetsFactory $assetsModelFactory
     * @param \Magento\Framework\View\ConfigInterface $presentationConfig
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \MagicToolbox\Sirv\Helper\Data $dataHelper,
        \MagicToolbox\Sirv\Helper\Sync $syncHelper,
        \MagicToolbox\Sirv\Model\AssetsFactory $assetsModelFactory,
        \Magento\Framework\View\ConfigInterface $presentationConfig,
        \Magento\Catalog\Helper\Image $imageHelper
    ) {
        parent::__construct($context);
        $this->dataHelper = $dataHelper;
        $this->syncHelper = $syncHelper;
        $this->assetsModelFactory = $assetsModelFactory;
        $this->presentationConfig = $presentationConfig;
        $this->imageHelper = $imageHelper;
    }

    /**
     * Set gallery block
     *
     * @param \Magento\Catalog\Block\Product\View\Gallery $galleryBlock
     * @return $this
     */
    public function setGalleryBlock($galleryBlock)
    {
        $this->galleryBlock = $galleryBlock;
        return $this;
    }

    /**
     * Get gallery block
     *
     * @return \Magento\Catalog\Block\Product\View\Gallery
     */
    public function getGalleryBlock()
    {
        return $this->galleryBlock;
    }

    /**
     * Get styles
     *
     * @return string
     */
    public function getViewerStyles()
    {
        $styles = '';

        $imageDisplayArea = 'product_page_image_medium';

        $attributes = $this->presentationConfig->getViewConfig()->getMediaAttributes(
            'Magento_Catalog',
            \Magento\Catalog\Helper\Image::MEDIA_TYPE_CONFIG_NODE,
            $imageDisplayArea
        );

        $width = $attributes['width'] ?? 0;
        $height = $attributes['height'] ?? 0;

        if ($width) {
            $styles .= "width: {$width}px;";
        }

        if ($height) {
            $styles .= "height: {$height}px;";
        }

        return $styles;
    }

    /**
     * Get options
     *
     * @return string
     */
    public function getViewerOptions()
    {
        if (!isset($this->viewerSlides)) {
            $this->setViewerSlides();
        }

        $options = 'slide.first: ' . $this->activeSlideIndex . ';';

        return $options;
    }

    /**
     * Get viewer slides
     *
     * @return array
     */
    public function getViewerSlides()
    {
        if (!isset($this->viewerSlides)) {
            $this->setViewerSlides();
        }

        return $this->viewerSlides;
    }

    /**
     * Get viewer contents source option
     *
     * @return integer
     */
    public function getViewerContentsSource()
    {
        return (int)($this->dataHelper->getConfig('viewer_contents') ?: self::MAGENTO_ASSETS);
    }

    /**
     * Set viewer slides
     *
     * @return void
     */
    protected function setViewerSlides()
    {
        $this->viewerSlides = [];
        $this->activeSlideIndex = 0;

        $viewerContents = $this->getViewerContentsSource();

        if ($viewerContents != self::SIRV_ASSETS) {
            $data = $this->getMagentoAssetsData();
            $this->viewerSlides = $data['slides'];
            $this->activeSlideIndex = $data['active-slide'];
        }

        if ($viewerContents != self::MAGENTO_ASSETS) {
            $data = $this->getSirvAssetsData($this->galleryBlock->getProduct());
        }

        switch ($viewerContents) {
            case self::MAGENTO_AND_SIRV_ASSETS:
                if (empty($this->viewerSlides)) {
                    $this->viewerSlides = $data['slides'];
                    $this->activeSlideIndex = $data['active-slide'];
                } else {
                    $this->viewerSlides = array_merge($this->viewerSlides, $data['slides']);
                }
                break;
            case self::SIRV_AND_MAGENTO_ASSETS:
                if (!empty($data['slides'])) {
                    $this->viewerSlides = array_merge($data['slides'], $this->viewerSlides);
                    $this->activeSlideIndex = $data['active-slide'];
                }
                break;
            case self::SIRV_ASSETS:
                $this->viewerSlides = $data['slides'];
                $this->activeSlideIndex = $data['active-slide'];
                break;
        }

        if (empty($this->viewerSlides)) {
            $this->activeSlideIndex = 0;
            $product = $this->galleryBlock->getProduct();
            $slideId = 'item-' . $product->getId() . '-0';
            $url = $this->imageHelper->getDefaultPlaceholderUrl('image');
            $this->viewerSlides[$slideId] = '<img data-id="' . $slideId . '" data-src="' . $url . '" data-type="static" />';
        }
    }

    /**
     * Get Magento assets data
     *
     * @return array
     */
    protected function getMagentoAssetsData()
    {
        $slides = [];
        $activeSlideIndex = 0;
        $galleryImages = $this->galleryBlock->getGalleryImages();
        $product = $this->galleryBlock->getProduct();
        $productId = $product->getId();
        $baseImage = $product->getImage();
        $idPrefix = 'item-' . $productId . '-';
        $index = 0;

        foreach ($galleryImages as $image) {
            if ($baseImage == $image->getData('file')) {
                $activeSlideIndex = $index;
            }

            $slideId = $idPrefix . $index;
            switch ($image->getData('media_type')) {
                case 'external-video':
                    $url = $image->getData('video_url');
                    $slides[$slideId] = '<div data-id="' . $slideId . '" data-src="' . $url . '"></div>';
                    $index++;
                    break;
                case 'image':
                    $absPath = $image->getData('path');
                    $relPath = $this->syncHelper->getRelativePath($absPath, \MagicToolbox\Sirv\Helper\Sync::MAGENTO_MEDIA_PATH);
                    $url = $image->getData('large_image_url');
                    if ($this->syncHelper->isSynced($relPath)) {
                        $parts = explode('?', $url, 2);
                        if (isset($parts[1])) {
                            $parts[1] = str_replace('+', '%20', $parts[1]);
                        }
                        $url = implode('?', $parts);
                        $slides[$slideId] = '<div data-id="' . $slideId . '" data-src="' . $url . '" data-type="zoom"></div>';
                    } else {
                        $slides[$slideId] = '<img data-id="' . $slideId . '" data-src="' . $url . '" data-type="static" />';
                    }
                    $index++;
                    break;
            }
        }

        return [
            'active-slide' => $activeSlideIndex,
            'slides' => $slides,
        ];
    }

    /**
     * Get Sirv assets data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function getSirvAssetsData($product)
    {
        $productId = $product->getId();

        if (isset(self::$assetsData[$productId])) {
            return self::$assetsData[$productId];
        }

        $assetsFolder = $this->dataHelper->getConfig('product_assets_folder') ?: '';
        if (empty($assetsFolder)) {
            return [
                'active-slide' => 0,
                'slides' => [],
            ];
        }

        $productSku = $product->getSku();
        if (strpos($assetsFolder, '{product-id}') !== false) {
            $assetsFolder = str_replace('{product-id}', $productId, $assetsFolder);
        } else if (strpos($assetsFolder, '{product-sku}') !== false) {
            $assetsFolder = str_replace('{product-sku}', $productSku, $assetsFolder);
        } else {
            $assetsFolder = $assetsFolder . '/' . $productSku;
        }
        $folderUrl = $this->syncHelper->getBaseUrl() . '/' . $assetsFolder;

        $assetsModel = $this->assetsModelFactory->create();
        $assetsModel->load($productId, 'product_id');
        $contents = $assetsModel->getData('contents');
        if ($contents === null) {
            $url = $folderUrl . '.view?info';
            $contents = $this->downloadViewContents($url);
            $assetsModel->setData('product_id', $productId);
            $assetsModel->setData('contents', $contents);
            $assetsModel->save();
        }

        $contents = json_decode($contents);
        $assets = is_object($contents) && isset($contents->assets) && is_array($contents->assets) ? $contents->assets : [];

        $slides = [];
        $activeSlideIndex = 0;
        $idPrefix = 'sirv-item-' . $productId . '-';
        $index = 0;
        foreach ($assets as $asset) {
            $slideId = $idPrefix . $index;
            switch ($asset->type) {
                case 'image':
                    $url = $folderUrl . '/' . $asset->name;
                    $slides[$slideId] = '<div data-id="' . $slideId . '" data-src="' . $url . '" data-type="zoom"></div>';
                    $index++;
                    break;
                case 'spin':
                    $url = $folderUrl . '/' . $asset->name;
                    $slides[$slideId] = '<div data-id="' . $slideId . '" data-src="' . $url . '"></div>';
                    $index++;
                    break;
            }
        }

        self::$assetsData[$productId] = [
            'active-slide' => $activeSlideIndex,
            'slides' => $slides,
        ];

        return self::$assetsData[$productId];
    }

    /**
     * Download view contents
     *
     * @param string $url
     * @return string
     */
    protected function downloadViewContents($url)
    {
        if (!isset(self::$curlHandle)) {
            self::$curlHandle = curl_init();
        }

        curl_setopt_array(
            self::$curlHandle,
            [
                CURLOPT_URL => $url,
                CURLOPT_HEADER => false,
                CURLOPT_NOBODY => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]
        );

        $contents = curl_exec(self::$curlHandle);

        $error = curl_errno(self::$curlHandle);
        $code = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);

        if ($error || $code != 200) {
            $contents = [
                'curl' => [
                    'code' => $code,
                    'error' => $error
                ]
            ];
            $contents = json_encode($contents);
        }

        return $contents;
    }

    /**
     * Get Sirv base URL
     *
     * @return integer
     */
    public function getBaseUrl()
    {
        return $this->syncHelper->getBaseUrl();
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        if (isset(self::$curlHandle)) {
            curl_close(self::$curlHandle);
            self::$curlHandle = null;
        }
        if (method_exists(get_parent_class($this) , '__destruct')) {
            parent::__destruct();
        }
    }
}
