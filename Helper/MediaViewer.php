<?php

namespace Sirv\Magento2\Helper;

/**
 * Sirv Media Viewer helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
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
     * Use placeholder
     *
     * @var bool
     */
    protected $usePlaceholder = false;

    /**
     * Placeholder data
     *
     * @var array
     */
    protected $placeholder = [];

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
     * Sirv pinned items
     *
     * @var array
     */
    protected $pinnedItems = [
        'videos' => '',
        'spins' => '',
        'images' => '',
        'mask' => ''
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
        $pinnedItems = $this->dataHelper->getConfig('pinned_items') ?: '{}';
        $pinnedItems = json_decode($pinnedItems, true);
        foreach (['videos', 'spins', 'images'] as $key) {
            if (isset($pinnedItems[$key])) {
                if ($pinnedItems[$key] == 'left') {
                    $this->pinnedItems[$key] = ' data-pinned="start"';
                } elseif ($pinnedItems[$key] == 'right') {
                    $this->pinnedItems[$key] = ' data-pinned="end"';
                }
            }
        }
        if (isset($pinnedItems['mask']) && !empty($pinnedItems['mask'])) {
            $this->pinnedItems['mask'] = '#' .
                str_replace(
                    '__ASTERISK__',
                    '.*',
                    preg_quote(
                        str_replace('*', '__ASTERISK__', $pinnedItems['mask']),
                        '#'
                    )
                ) . '#';
        }
        $this->usePlaceholder = $this->dataHelper->getConfig('use_placeholder_with_smv') == 'true';
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
        $itemsOrder = $this->dataHelper->getConfig('slides_order') ?: '';
        if (!empty($itemsOrder)) {
            $options .= 'itemsOrder:[\'' . implode('\',\'', explode(',', $itemsOrder)) . '\'];';
        }

        if ($this->usePlaceholder) {
            $options .= 'thumbnails.target: .pdp-gallery-thumbnails;';
        }

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
     * Use placeholder
     *
     * @return bool
     */
    public function usePlaceholder()
    {
        return $this->usePlaceholder;
    }

    /**
     * Get placeholder data
     *
     * @return array
     */
    public function getPlaceholder()
    {
        if (!isset($this->viewerSlides)) {
            $this->initAssetsData();
        }

        if (empty($this->placeholder)) {
            return [
                'url' => '',
                'width' => 0,
                'height' => 0,
            ];
        }

        $url = $this->placeholder['url'];
        if (preg_match('#(\?|&)q=\d++#', $url)) {
            $url = preg_replace('#(\?|&)q=\d++#', '$1q=30', $url);
        } elseif (preg_match('#\?.#', $url)) {
            $url = $url . '&q=30';
        } else {
            $url = $url . '?q=30';
        }

        $width = empty($this->placeholder['width']) ? 100 : (int)$this->placeholder['width'];
        $height = empty($this->placeholder['height']) ? 100 : (int)$this->placeholder['height'];
        if ($width > $height) {
            $s = $width / 100;
        }
        if ($height >= $width) {
            $s = $height / 100;
        }
        $width = floor($width / $s);
        $height = floor($height / $s);

        return [
            'url' => $url,
            'width' => $width,
            'height' => $height,
        ];
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
        $this->placeholder = $data['placeholder'];
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
        $placeholder = [];

        if ($this->slideSources != self::SIRV_ASSETS) {
            $data = $this->getMagentoAssetsData($product);
            $slides = $data['slides'];
            $activeSlideIndex = $data['active-slide'];
            $placeholder = $data['placeholder'];
        }

        if ($this->slideSources != self::MAGENTO_ASSETS) {
            $data = $this->getSirvAssetsData($product);
        }

        switch ($this->slideSources) {
            case self::MAGENTO_AND_SIRV_ASSETS:
                if (empty($slides)) {
                    $slides = $data['slides'];
                    $placeholder = $data['placeholder'];
                } else {
                    $slides = array_merge($slides, $data['slides']);
                }
                break;
            case self::SIRV_AND_MAGENTO_ASSETS:
                if (!empty($data['slides'])) {
                    $slides = array_merge($data['slides'], $slides);
                    //$activeSlideIndex += count($data['slides']);
                    $activeSlideIndex = 0;
                    $placeholder = $data['placeholder'];
                }
                break;
            case self::SIRV_ASSETS:
                $slides = $data['slides'];
                $placeholder = $data['placeholder'];
                break;
        }

        if (empty($slides) && $this->productId == $productId) {
            $slideId = 'item-' . $productId . '-0';
            $url = $this->imageHelper->getDefaultPlaceholderUrl('image');
            $placeholder['url'] = $url;
            $placeholder['width'] = 100;
            $placeholder['height'] = 100;
            $slides[$slideId] = '<img data-id="' . $slideId . '" data-src="' . $url . '" data-type="static" />';
        }

        self::$assetsData[$productId] = [
            'active-slide' => $activeSlideIndex,
            'placeholder' => $placeholder,
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
        $placeholder = [];

        $images = $product->getMediaGalleryImages();
        if ($images instanceof \Magento\Framework\Data\Collection) {
            $productId = $product->getId();
            $baseImage = $product->getImage();
            $productName = $product->getName();
            $idPrefix = 'item-' . $productId . '-';
            $index = 0;
            $disabled = ($this->productId == $productId ? '' : ' data-disabled');
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
                    if ($this->usePlaceholder) {
                        $absPath = $image->getData('path');
                        if (is_file($absPath)) {
                            list($fileWidth, $fileHeight,) = getimagesize($absPath);
                            $placeholder['width'] = $fileWidth;
                            $placeholder['height'] = $fileHeight;
                        }
                        if ($imageUrlBuilder) {
                            $placeholder['url'] = $imageUrlBuilder->getUrl($image->getData('file'), 'product_page_image_large');
                        } else {
                            $placeholder['url'] = $this->imageHelper->init($product, 'product_page_image_large')
                                ->setImageFile($image->getData('file'))
                                ->getUrl();
                        }
                    }
                }

                $slideId = $idPrefix . $index;
                switch ($image->getData('media_type')) {
                    case 'external-video':
                        $url = $image->getData('video_url');
                        $slides[$slideId] = '<div data-id="' . $slideId . '"' . $disabled . ' data-src="' . $url . '"' . $this->pinnedItems['videos'] . '></div>';
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

                        $pinnedAttr = $this->pinnedItems['images'];
                        if (!(empty($pinnedAttr) || empty($this->pinnedItems['mask']) || preg_match($this->pinnedItems['mask'], $url))) {
                            $pinnedAttr = '';
                        }

                        if ($this->syncHelper->isNotExcluded($absPath) && $this->syncHelper->isSynced($relPath)) {
                            $parts = explode('?', $url, 2);
                            if (isset($parts[1])) {
                                $parts[1] = str_replace('+', '%20', $parts[1]);
                            }
                            $url = implode('?', $parts);
                            $slides[$slideId] = '<div data-id="' . $slideId . '"' . $dataType . $disabled . ' data-src="' . $url . '"' . $pinnedAttr . ' data-alt="' . $alt . '"></div>';
                        } else {
                            $slides[$slideId] = '<img data-id="' . $slideId . '" data-type="static"' . $disabled . ' data-src="' . $url . '"' . $pinnedAttr . ' data-alt="' . $alt . '" />';
                        }
                        $index++;
                        break;
                }
                $iterator->next();
            }
        }

        return [
            'active-slide' => $activeSlideIndex,
            'placeholder' => $placeholder,
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
        $assetsData = $this->dataHelper->getAssetsData($product);
        if (empty($assetsData)) {
            return [
                'active-slide' => 0,
                'placeholder' => [],
                'slides' => []
            ];
        }

        $assets = $assetsData['assets'] ?? [];
        $productId = $product->getId();
        $this->assetsCacheData['timestamps'][$productId] = $assetsData['timestamp'];
        $folderUrl = $this->syncHelper->getBaseUrl() . $assetsData['dirname'];

        $zoom = $this->dataHelper->getConfig('image_zoom') ?: 'enabled';
        $dataType = $zoom == 'enabled' ? ' data-type="zoom"' : '';

        $slides = [];
        $idPrefix = 'sirv-item-' . $productId . '-';
        $index = 0;
        $disabled = ($this->productId == $productId ? '' : ' data-disabled');

        $profile = $this->dataHelper->getConfig('profile');
        if (!empty($profile) && !in_array($profile, ['-', 'Default'])) {
            $profile = '?profile=' . $profile;
        } else {
            $profile = '';
        }

        $placeholder = [];
        foreach ($assets as $asset) {
            $slideId = $idPrefix . $index;
            switch ($asset['type']) {
                case 'image':
                    $url = $folderUrl . '/' . $asset['name'];
                    $pinnedAttr = $this->pinnedItems['images'];
                    if (!(empty($pinnedAttr) || empty($this->pinnedItems['mask']) || preg_match($this->pinnedItems['mask'], $url))) {
                        $pinnedAttr = '';
                    }
                    $url .= $profile;
                    if (empty($placeholder) && $this->usePlaceholder) {
                        $placeholder['url'] = $url;
                        $placeholder['width'] = $asset['width'];
                        $placeholder['height'] = $asset['height'];
                    }
                    $slides[$slideId] = '<div data-id="' . $slideId . '"' . $dataType . $disabled . ' data-src="' . $url . '"' . $pinnedAttr . '></div>';
                    $index++;
                    break;
                case 'spin':
                case 'video':
                    $url = $folderUrl . '/' . $asset['name'] . $profile;
                    if (empty($placeholder) && $this->usePlaceholder) {
                        if ($asset['type'] == 'video') {
                            $placeholder['url'] = $url . (strpos($url, '?') === false ? '?' : '&') . 'thumbnail=' . $asset['width']/*height*/;
                        } else {
                            $placeholder['url'] = $url . (strpos($url, '?') === false ? '?' : '&') .'thumb=spin&image.frames=1';
                        }
                        $placeholder['width'] = $asset['width'];
                        $placeholder['height'] = $asset['height'];
                    }
                    $slides[$slideId] = '<div data-id="' . $slideId . '"' . $disabled . ' data-src="' . $url . '"' . $this->pinnedItems[$asset['type'] . 's'] . '></div>';
                    $index++;
                    break;
            }
        }

        return [
            'active-slide' => 0,
            'placeholder' => $placeholder,
            'slides' => $slides
        ];
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
}
