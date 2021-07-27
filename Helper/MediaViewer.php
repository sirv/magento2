<?php

namespace Sirv\Magento2\Helper;

/**
 * Sirv Media Viewer helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
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
     * Assets model factory
     *
     * @var \Sirv\Magento2\Model\AssetsFactory
     */
    protected $assetsModelFactory = null;

    /**
     * Image helper
     *
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    /**
     * Json encoder
     *
     * @var \Magento\Framework\Json\EncoderInterface
     */
    protected $jsonEncoder;

    /**
     * Gallery block
     *
     * @var \Magento\Catalog\Block\Product\View\Gallery
     */
    protected $galleryBlock = null;

    /**
     * Slide sources
     *
     * @var integer
     */
    protected $slideSources;

    /**
     * cURL resource
     *
     * @var resource
     */
    protected static $curlHandle = null;

    /**
     * Assets data
     *
     * @var array
     */
    protected static $assetsData = [];

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
     * Current product id
     *
     * @var integer
     */
    protected $productId = 0;

    /**
     * Configurable data
     *
     * @var array
     */
    protected $configurableData = [];

    /**
     * Sirv content cache data
     *
     * @var array
     */
    protected $assetsCacheData = [
        'ttl' => 0,
        'currentTime' => 0,
        'url' => '',
        'timestamps' => []
    ];

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Sirv\Magento2\Helper\Data $dataHelper
     * @param \Sirv\Magento2\Helper\Sync $syncHelper
     * @param \Sirv\Magento2\Model\AssetsFactory $assetsModelFactory
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Sirv\Magento2\Helper\Data $dataHelper,
        \Sirv\Magento2\Helper\Sync $syncHelper,
        \Sirv\Magento2\Model\AssetsFactory $assetsModelFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder
    ) {
        parent::__construct($context);
        $this->dataHelper = $dataHelper;
        $this->syncHelper = $syncHelper;
        $this->assetsModelFactory = $assetsModelFactory;
        $this->imageHelper = $imageHelper;
        $this->jsonEncoder = $jsonEncoder;
        $this->slideSources = (int)($this->dataHelper->getConfig('viewer_contents') ?: self::MAGENTO_ASSETS);
        $this->assetsCacheData['ttl'] = ($this->dataHelper->getConfig('assets_cache_ttl') ?: 0);
        $this->assetsCacheData['currentTime'] = time();
        $this->assetsCacheData['url'] = $this->_getUrl('sirv/ajax/assetsCache');
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
     * Get max height
     *
     * @return integer
     */
    public function getMaxHeight()
    {
        return (int)($this->dataHelper->getConfig('smv_max_height') ?: 0);
    }

    /**
     * Get configured options
     *
     * @return string
     */
    public function getViewerJsOptions()
    {
        $options = $this->dataHelper->getConfig('smv_js_options') ?: '';
        if ($options) {
            $options = "\n<script type=\"text/javascript\">\n{$options}\n</script>\n";
        }

        return $options;
    }

    /**
     * Get configured CSS
     *
     * @return string
     */
    public function getViewerCss()
    {
        return $this->dataHelper->getConfig('smv_custom_css') ?: '';
    }

    /**
     * Get runtime options
     *
     * @return string
     */
    public function getViewerDataOptions()
    {
        if (!isset($this->viewerSlides)) {
            $this->initAssetsData();
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
            $this->initAssetsData();
        }

        return $this->viewerSlides;
    }

    /**
     * Get configuration for js
     *
     * @return string
     */
    public function getJsonConfig()
    {
        if (!isset($this->viewerSlides)) {
            $this->initAssetsData();
        }

        return $this->jsonEncoder->encode($this->configurableData);
    }

    /**
     * Get assets cache data
     *
     * @return string
     */
    public function getAssetsCacheData()
    {
        if (!isset($this->viewerSlides)) {
            $this->initAssetsData();
        }

        return $this->jsonEncoder->encode($this->assetsCacheData);
    }

    /**
     * Init assets data
     *
     * @return void
     */
    protected function initAssetsData()
    {
        if (!$this->galleryBlock) {
            return;
        }

        $this->viewerSlides = [];
        $this->activeSlideIndex = 0;

        $product = $this->galleryBlock->getProduct();
        $this->productId = $product->getId();

        $this->configurableData['current-id'] = $this->productId;
        $this->configurableData['slides'] = [];
        $this->configurableData['active-slides'] = [];

        foreach ($this->getAssociatedProducts($product) as $associatedProduct) {
            $id = $associatedProduct->getId();
            $data = $this->getProductAssetsData($associatedProduct);
            $this->configurableData['slides'][$id] = array_keys($data['slides']);
            $this->configurableData['active-slides'][$id] = count($this->viewerSlides) + $data['active-slide'];
            $this->viewerSlides = array_merge($this->viewerSlides, $data['slides']);
        }

        $data = $this->getProductAssetsData($product);
        $this->configurableData['slides'][$this->productId] = array_keys($data['slides']);
        $this->configurableData['active-slides'][$this->productId] = count($this->viewerSlides) + $data['active-slide'];
        $this->viewerSlides = array_merge($this->viewerSlides, $data['slides']);

        $this->activeSlideIndex = $this->configurableData['active-slides'][$this->productId];
    }

    /**
     * Get associated products
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return \Magento\Catalog\Model\Product[]
     */
    protected function getAssociatedProducts($product)
    {
        $products = [];

        $isConfigurable = $product->getTypeId() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE;
        if ($isConfigurable) {
            $block = $this->getConfigurableBlock();
            if ($block) {
                $products = $block->getAllowProducts();
            }
        }

        return $products;
    }

    /**
     * Get configurable block
     *
     * @return \Magento\ConfigurableProduct\Block\Product\View\Type\Configurable
     */
    protected function getConfigurableBlock()
    {
        static $configurableBlock = null;

        if ($configurableBlock === null) {
            /** @var \Magento\Framework\View\Layout $layout */
            $layout = $this->galleryBlock->getLayout();

            /** @var \Magento\Swatches\Block\Product\Renderer\Configurable $block */
            $block = $layout->getBlock('product.info.options.swatches');
            if (!$block) {
                /** @var \Magento\Catalog\Block\Product\View $wrapperBlock */
                $wrapperBlock = $layout->getBlock('product.info.options.wrapper');
                if ($wrapperBlock) {
                    $block = $wrapperBlock->getChildBlock('swatch_options');
                }

                if (!$block) {
                    /** @var \Magento\ConfigurableProduct\Block\Product\View\Type\Configurable $block */
                    $block = $layout->getBlock('product.info.options.configurable');
                    if (!$block) {
                        if ($wrapperBlock) {
                            $block = $wrapperBlock->getChildBlock('options_configurable');
                        }
                    }
                }
            }

            $configurableBlock = $block ?: false;
        }

        return $configurableBlock;
    }

    /**
     * Get product assets data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    protected function getProductAssetsData($product)
    {
        $productId = $product->getId();

        if (isset(self::$assetsData[$productId])) {
            return self::$assetsData[$productId];
        }

        $slides = [];
        $activeSlideIndex = 0;

        if ($this->slideSources != self::SIRV_ASSETS) {
            $data = $this->getMagentoAssetsData($product);
            $slides = $data['slides'];
            $activeSlideIndex = $data['active-slide'];
        }

        if ($this->slideSources != self::MAGENTO_ASSETS) {
            $data = $this->getSirvAssetsData($product);
        }

        switch ($this->slideSources) {
            case self::MAGENTO_AND_SIRV_ASSETS:
                if (empty($slides)) {
                    $slides = $data['slides'];
                } else {
                    $slides = array_merge($slides, $data['slides']);
                }
                break;
            case self::SIRV_AND_MAGENTO_ASSETS:
                if (!empty($data['slides'])) {
                    $slides = array_merge($data['slides'], $slides);
                    //$activeSlideIndex += count($data['slides']);
                    $activeSlideIndex = 0;
                }
                break;
            case self::SIRV_ASSETS:
                $slides = $data['slides'];
                break;
        }

        if (empty($slides) && $this->productId == $productId) {
            $slideId = 'item-' . $productId . '-0';
            $url = $this->imageHelper->getDefaultPlaceholderUrl('image');
            $slides[$slideId] = '<img data-id="' . $slideId . '" data-src="' . $url . '" data-type="static" />';
        }

        self::$assetsData[$productId] = [
            'active-slide' => $activeSlideIndex,
            'slides' => $slides
        ];

        return self::$assetsData[$productId];
    }

    /**
     * Get Magento assets data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    protected function getMagentoAssetsData($product)
    {
        $slides = [];
        $activeSlideIndex = 0;

        $images = $product->getMediaGalleryImages();
        if ($images instanceof \Magento\Framework\Data\Collection) {
            $productId = $product->getId();
            $baseImage = $product->getImage();
            $productName = $product->getName();
            $idPrefix = 'item-' . $productId . '-';
            $index = 0;
            $disabled = ($this->productId == $productId ? 'false' : 'true');
            $zoom = $this->dataHelper->getConfig('image_zoom') ?: 'enabled';
            $dataType = $zoom == 'enabled' ? ' data-type="zoom"' : '';

            //NOTE: to sort by position for associated products
            $iterator = $images->getIterator();
            $iterator->uasort(function ($a, $b) {
                $aPos = (int)$a->getPosition();
                $bPos = (int)$b->getPosition();
                if ($aPos > $bPos) {
                    return 1;
                } elseif ($aPos < $bPos) {
                    return -1;
                }
                return 0;
            });
            $iterator->rewind();

            $imageUrlBuilder = $this->getImageUrlBuilder();

            while ($iterator->valid()) {
                $image = $iterator->current();

                if ($baseImage == $image->getData('file')) {
                    $activeSlideIndex = $index;
                }

                $slideId = $idPrefix . $index;
                switch ($image->getData('media_type')) {
                    case 'external-video':
                        $url = $image->getData('video_url');
                        $slides[$slideId] = '<div data-id="' . $slideId . '" data-src="' . $url . '" data-disabled="' . $disabled . '"></div>';
                        $index++;
                        break;
                    case 'image':
                        $absPath = $image->getData('path');
                        $relPath = $this->syncHelper->getRelativePath($absPath, \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH);
                        $url = $image->getData('large_image_url');
                        if (empty($url)) {
                            if ($imageUrlBuilder) {
                                $url = $imageUrlBuilder->getUrl($image->getData('file'), 'product_page_image_large');
                            } else {
                                $url = $this->imageHelper->init($product, 'product_page_image_large')
                                    ->setImageFile($image->getData('file'))
                                    ->getUrl();
                            }
                        }

                        $alt = $image->getData('label') ?: $productName;
                        $alt = $this->galleryBlock->escapeHtmlAttr($alt, false);

                        if ($this->syncHelper->isNotExcluded($absPath) && $this->syncHelper->isSynced($relPath)) {
                            $parts = explode('?', $url, 2);
                            if (isset($parts[1])) {
                                $parts[1] = str_replace('+', '%20', $parts[1]);
                            }
                            $url = implode('?', $parts);
                            $slides[$slideId] = '<div data-id="' . $slideId . '" data-src="' . $url . '"' . $dataType . ' data-disabled="' . $disabled . '" data-alt="' . $alt . '"></div>';
                        } else {
                            $slides[$slideId] = '<img data-id="' . $slideId . '" data-src="' . $url . '" data-type="static" data-disabled="' . $disabled . '" data-alt="' . $alt . '" />';
                        }
                        $index++;
                        break;
                }
                $iterator->next();
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
        $assetsFolder = $this->dataHelper->getConfig('product_assets_folder') ?: '';
        $assetsFolder = trim($assetsFolder);
        $assetsFolder = trim($assetsFolder, '/');
        if (empty($assetsFolder)) {
            return [
                'active-slide' => 0,
                'slides' => []
            ];
        }

        $productId = $product->getId();
        $productSku = $product->getSku();

        if (strpos($assetsFolder, '{product-id}') !== false) {
            $assetsFolder = str_replace('{product-id}', $productId, $assetsFolder);
        } elseif (strpos($assetsFolder, '{product-sku}') !== false) {
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
            $assetsModel->setData(
                'timestamp',
                $this->assetsCacheData['timestamps'][$productId] = time()
            );
            $assetsModel->save();
        } else {
            $this->assetsCacheData['timestamps'][$productId] = $assetsModel->getData('timestamp');
        }

        $contents = json_decode($contents);
        $assets = is_object($contents) && isset($contents->assets) && is_array($contents->assets) ? $contents->assets : [];

        $zoom = $this->dataHelper->getConfig('image_zoom') ?: 'enabled';
        $dataType = $zoom == 'enabled' ? ' data-type="zoom"' : '';

        $slides = [];
        $idPrefix = 'sirv-item-' . $productId . '-';
        $index = 0;
        $disabled = ($this->productId == $productId ? 'false' : 'true');
        foreach ($assets as $asset) {
            $slideId = $idPrefix . $index;
            switch ($asset->type) {
                case 'image':
                    $url = $folderUrl . '/' . $asset->name;
                    $slides[$slideId] = '<div data-id="' . $slideId . '" data-src="' . $url . '"' . $dataType . ' data-disabled="' . $disabled . '"></div>';
                    $index++;
                    break;
                case 'spin':
                case 'video':
                    $url = $folderUrl . '/' . $asset->name;
                    $slides[$slideId] = '<div data-id="' . $slideId . '" data-src="' . $url . '" data-disabled="' . $disabled . '"></div>';
                    $index++;
                    break;
            }
        }

        return [
            'active-slide' => 0,
            'slides' => $slides
        ];
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
     * Get image URL builder
     *
     * @return \Magento\Catalog\Model\Product\Image\UrlBuilder|null
     */
    public function getImageUrlBuilder()
    {
        static $imageUrlBuilder = null;

        if ($imageUrlBuilder === null) {
            if (class_exists('\Magento\Catalog\Model\Product\Image\UrlBuilder', false)) {
                $imageUrlBuilder = \Magento\Framework\App\ObjectManager::getInstance()->get(
                    \Magento\Catalog\Model\Product\Image\UrlBuilder::class
                );
            }
        }

        return $imageUrlBuilder;
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
        if (method_exists(get_parent_class(__CLASS__), '__destruct')) {
            parent::__destruct();
        }
    }
}
