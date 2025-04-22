<?php

namespace Sirv\Magento2\Helper;

/**
 * Sirv Media Viewer helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
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
     * Alt text cache model factory
     *
     * @var \Sirv\Magento2\Model\AltTextCacheFactory
     */
    protected $altTextCacheModelFactory = null;

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
     * Active slide ids
     *
     * @var array
     */
    protected $activeSlideIds = [];

    /**
     * Use placeholder
     *
     * @var bool
     */
    protected $usePlaceholder = false;

    /**
     * SMV layout
     *
     * @var stirng
     */
    protected $smvLayout = 'slider';

    /**
     * Items order
     *
     * @var array
     */
    protected $itemsOrder = [];

    /**
     * Default placeholder width
     *
     * @var integer
     */
    protected $defaultPlaceholderWidth = 100;

    /**
     * Default placeholder height
     *
     * @var integer
     */
    protected $defaultPlaceholderHeight = 100;

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
        'models' => '',
        'images' => '',
        'mask' => ''
    ];

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Sirv\Magento2\Helper\Data $dataHelper
     * @param \Sirv\Magento2\Helper\Sync $syncHelper
     * @param \Sirv\Magento2\Model\AltTextCacheFactory $altTextCacheModelFactory
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Sirv\Magento2\Helper\Data $dataHelper,
        \Sirv\Magento2\Helper\Sync $syncHelper,
        \Sirv\Magento2\Model\AltTextCacheFactory $altTextCacheModelFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder
    ) {
        parent::__construct($context);
        $this->dataHelper = $dataHelper;
        $this->syncHelper = $syncHelper;
        $this->altTextCacheModelFactory = $altTextCacheModelFactory;
        $this->imageHelper = $imageHelper;
        $this->jsonEncoder = $jsonEncoder;
        $this->slideSources = (int)($this->dataHelper->getConfig('viewer_contents') ?: self::MAGENTO_ASSETS);
        $this->assetsCacheData['ttl'] = ($this->dataHelper->getConfig('assets_cache_ttl') ?: 0);
        $this->assetsCacheData['currentTime'] = time();
        $this->assetsCacheData['url'] = $this->_getUrl('sirv/ajax/assetsCache');
        $pinnedItems = $this->dataHelper->getConfig('pinned_items') ?: '{}';
        $pinnedItems = json_decode($pinnedItems, true);
        foreach (['videos', 'spins', 'models', 'images'] as $key) {
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
        $this->smvLayout = $this->dataHelper->getConfig('smv_layout') ?: 'slider';
        $this->usePlaceholder = $this->dataHelper->getConfig('use_placeholder_with_smv') == 'true';
        if ($this->smvLayout != 'slider') {
            $this->usePlaceholder = false;
        }

        $itemsOrder = $this->dataHelper->getConfig('slides_order') ?: '';
        if (!empty($itemsOrder)) {
            $this->itemsOrder = explode(',', $itemsOrder);
        }
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
        $css = $this->dataHelper->getConfig('smv_custom_css') ?: '';

        switch ($this->smvLayout) {
            case 'grid_columns_1_2':
                $css = '.smv-pg-container .Sirv .smv-slide:nth-child(1) { grid-column: span 2; }' . $css;
                break;
            case 'grid_columns_1_3':
                $css = '.smv-pg-container .Sirv .smv-slide:nth-child(1) { grid-column: span 3; }' . $css;
                break;
        }

        return $css;
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
        if (!empty($this->itemsOrder)) {
            $options .= 'itemsOrder:[\'' . implode('\',\'', $this->itemsOrder) . '\'];';
        }

        if ($this->usePlaceholder) {
            $options .= 'thumbnails.target: .pdp-gallery-thumbnails;';
        }

        if ($this->smvLayout == 'slider') {
            $options .= 'layout.type: slider;';
        } else {
            $options .= 'layout.type: grid;';
            switch ($this->smvLayout) {
                case 'grid_columns_1':
                    $options .= 'layout.grid.columns: 1;';
                    break;
                case 'grid_columns_2':
                case 'grid_columns_1_2':
                    $options .= 'layout.grid.columns: 2;';
                    break;
                case 'grid_columns_3':
                case 'grid_columns_1_3':
                    $options .= 'layout.grid.columns: 3;';
                    break;
            }
            $smvGridGap = $this->dataHelper->getConfig('smv_grid_gap') ?: '20';
            $options .= 'layout.grid.gap: ' . $smvGridGap . ';';
            $smvAspectRatio = $this->dataHelper->getConfig('smv_aspect_ratio') ?: 'auto';
            $options .= 'layout.aspectRatio: ' . $smvAspectRatio . ';';
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

        if (empty($this->placeholder) || empty($this->placeholder['url'])) {
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

        $orderedSlides = [];
        foreach ($this->getAssociatedProducts($product) as $associatedProduct) {
            $data = $this->getProductAssetsData($associatedProduct);
            $id = $associatedProduct->getId();
            $orderedSlides[$id] = [];
            if (empty($data)) {
                $this->configurableData['slides'][$id] = [];
            } else {
                $this->configurableData['slides'][$id] = array_keys($data);
                $activeSlideIndex = isset($this->activeSlideIds[$id]) ? $data[$this->activeSlideIds[$id]]['index'] : 0;
                $viewerSlidesCount = count($this->viewerSlides);
                $this->configurableData['active-slides'][$id] = $viewerSlidesCount + $activeSlideIndex;
                foreach ($data as $slideId => $slideData) {
                    $this->viewerSlides[] = $slideData['html'];
                }
                foreach ($this->itemsOrder as $i => $itemType) {
                    foreach ($data as $slideId => $slideData) {
                        if (isset($data[$slideId]['ordered'])) {
                            continue;
                        }
                        if ($slideData['type'] == $itemType) {
                            $orderedSlides[$id][$i] = [
                                'index' => $viewerSlidesCount + $slideData['index'],
                                'id' => $slideId
                            ];
                            $data[$slideId]['ordered'] = true;
                            break;
                        }
                    }
                }
            }
        }

        $data = $this->getProductAssetsData($product);
        $this->configurableData['slides'][$this->productId] = array_keys($data);
        $viewerSlidesCount = count($this->viewerSlides);
        $this->configurableData['active-slides'][$this->productId] = $viewerSlidesCount + $data[$this->activeSlideIds[$this->productId]]['index'];
        foreach ($data as $slideId => $slideData) {
            $this->viewerSlides[] = $slideData['html'];
        }
        $orderedSlides[$this->productId] = [];
        $mainProductOrderedSlides = [];
        foreach ($this->itemsOrder as $i => $itemType) {
            foreach ($data as $slideId => $slideData) {
                if (isset($data[$slideId]['ordered'])) {
                    continue;
                }
                if ($slideData['type'] == $itemType) {
                    $mainProductOrderedSlides[$i] = [
                        'index' => $viewerSlidesCount + $slideData['index'],
                        'id' => $slideId
                    ];
                    $data[$slideId]['ordered'] = true;
                    break;
                }
            }
        }

        foreach ($orderedSlides as $productId => $slideData) {
            if (!isset($this->configurableData['active-slides'][$productId])) {
                continue;
            }
            $mergedData = [];
            foreach ($this->itemsOrder as $i => $itemType) {
                if (isset($slideData[$i])) {
                    $mergedData[$i] = $slideData[$i];
                } elseif (isset($mainProductOrderedSlides[$i])) {
                    $mergedData[$i] = $mainProductOrderedSlides[$i];
                }
            }
            $orderedSlidesCount = count($mergedData);
            if ($orderedSlidesCount) {
                $pos = 0;
                $activeSlideId = $this->activeSlideIds[$productId];
                $activeSlideIndex = $this->configurableData['active-slides'][$productId];
                foreach ($mergedData as $i => $slideData) {
                    if ($slideData['id'] == $activeSlideId) {
                        $this->configurableData['active-slides'][$productId] = $pos;
                        break;
                    } elseif ($slideData['index'] > $activeSlideIndex) {
                        $this->configurableData['active-slides'][$productId]++;
                    }
                    $pos++;
                }
            }
        }

        $this->activeSlideIndex = $this->configurableData['active-slides'][$this->productId];
        $this->placeholder = $data[$this->activeSlideIds[$this->productId]]['placeholder'];
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

        switch ($this->slideSources) {
            case self::MAGENTO_ASSETS:
                $data = $this->getMagentoAssetsData($product);
                break;
            case self::MAGENTO_AND_SIRV_ASSETS:
                $data = $this->getMagentoAssetsData($product);
                if (empty($data)) {
                    $data = $this->getSirvAssetsData($product);
                } else {
                    $data = $data + $this->getSirvAssetsData($product);
                    $fileNames = [];
                    $index = 0;
                    foreach ($data as $slideId => $slideData) {
                        if (isset($fileNames[$data[$slideId]['basename']])) {
                            unset($data[$slideId]);
                        } else {
                            $fileNames[$data[$slideId]['basename']] = true;
                            $data[$slideId]['index'] = $index;
                            $index++;
                        }
                    }
                }
                break;
            case self::SIRV_AND_MAGENTO_ASSETS:
                $data = $this->getSirvAssetsData($product);
                if (empty($data)) {
                    $data = $this->getMagentoAssetsData($product);
                } else {
                    $firstSlideId = array_key_first($data);
                    $this->activeSlideIds[$productId] = $firstSlideId;
                    $data = $data + $this->getMagentoAssetsData($product);
                    $fileNames = [];
                    $index = 0;
                    foreach ($data as $slideId => $slideData) {
                        if (isset($fileNames[$data[$slideId]['basename']])) {
                            if ($this->activeSlideIds[$productId] == $slideId) {
                                //$this->activeSlideIds[$productId] = $firstSlideId;
                                $this->activeSlideIds[$productId] = $fileNames[$data[$slideId]['basename']];
                            }
                            unset($data[$slideId]);
                        } else {
                            $fileNames[$data[$slideId]['basename']] = $slideId;
                            $data[$slideId]['index'] = $index;
                            $index++;
                        }
                    }
                }
                break;
            case self::SIRV_ASSETS:
                $data = $this->getSirvAssetsData($product);
                //NOTE: display Megento assets if product has no Sirv assets
                if (empty($data)) {
                    $data = $this->getMagentoAssetsData($product);
                }
                break;
        }

        if (empty($data)) {
            if ($this->productId == $productId) {
                $slideId = 'item-' . $productId . '-0';
                $url = $this->imageHelper->getDefaultPlaceholderUrl('image');
                $data[$slideId] = [];
                $data[$slideId]['index'] = 0;
                $data[$slideId]['type'] = 'image';
                $data[$slideId]['html'] = '<img data-group="group-' . $productId . '" data-id="' . $slideId . '" data-src="' . $url . '" data-type="static" />';
                $data[$slideId]['placeholder'] = [];
                $data[$slideId]['placeholder']['url'] = $url;
                $data[$slideId]['placeholder']['width'] = $this->defaultPlaceholderWidth;
                $data[$slideId]['placeholder']['height'] = $this->defaultPlaceholderHeight;
            }
        }

        //NOTE: fix active slide id
        if (!isset($this->activeSlideIds[$productId])) {
            if (!empty($data)) {
                $this->activeSlideIds[$productId] = array_key_first($data);
            }
        }

        self::$assetsData[$productId] = $data;

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
        $items = $product->getMediaGalleryImages();
        if (!($items instanceof \Magento\Framework\Data\Collection)) {
            return [];
        }

        //NOTE: to sort by position for associated products
        $iterator = $items->getIterator();
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

        $productId = $product->getId();
        $baseImage = $product->getImage();
        $productName = $product->getName();
        $group = 'group-' . $productId;
        $idPrefix = 'item-' . $productId . '-';
        $zoom = $this->dataHelper->getConfig('image_zoom') ?: 'enabled';
        $dataTypeAttr = $zoom == 'enabled' ? ' data-type="zoom"' : '';
        $disabledAttr = ($this->productId == $productId ? '' : ' data-disabled');
        $profile = $this->dataHelper->getConfig('profile');
        if (!empty($profile) && !in_array($profile, ['-', 'Default'])) {
            $profile = '?profile=' . $profile;
        } else {
            $profile = '';
        }
        $getPlaceholder = $this->usePlaceholder && ($this->productId == $productId);

        $data = [];
        $index = 0;

        /** @var \Sirv\Magento2\Model\AltTextCache $altTextCacheModel */
        $altTextCacheModel = $this->altTextCacheModelFactory->create();

        while ($iterator->valid()) {
            $item = $iterator->current();
            $imageFile = $item->getData('file');

            $slideId = $idPrefix . $index;
            $data[$slideId] = [];
            $data[$slideId]['index'] = $index;
            $data[$slideId]['basename'] = basename($imageFile);

            switch ($item->getData('media_type')) {
                case 'external-video':
                    $url = $item->getData('video_url');
                    if (empty($url)) {
                        unset($data[$slideId]);
                        break;
                    }

                    $data[$slideId]['type'] = 'video';
                    $data[$slideId]['html'] = '<div data-group="' . $group . '" data-id="' . $slideId . '"' . $disabledAttr . ' data-src="' . $url . '"' . $this->pinnedItems['videos'] . '></div>';

                    $data[$slideId]['placeholder'] = [];
                    if ($getPlaceholder) {
                        if ($imageUrlBuilder) {
                            $data[$slideId]['placeholder']['url'] = $imageUrlBuilder->getUrl($imageFile, 'product_page_image_large');
                        } else {
                            $data[$slideId]['placeholder']['url'] = $this->imageHelper->init($product, 'product_page_image_large')
                                ->setImageFile($imageFile)
                                ->getUrl();
                        }
                        $absPath = $item->getData('path');
                        if (is_file($absPath)) {
                            list($fileWidth, $fileHeight,) = getimagesize($absPath);
                            $data[$slideId]['placeholder']['width'] = $fileWidth;
                            $data[$slideId]['placeholder']['height'] = $fileHeight;
                        } else {
                            $data[$slideId]['placeholder']['width'] = $this->defaultPlaceholderWidth;
                            $data[$slideId]['placeholder']['height'] = $this->defaultPlaceholderHeight;
                        }
                    }

                    if ($baseImage == $imageFile) {
                        $this->activeSlideIds[$productId] = $slideId;
                    }

                    $index++;
                    break;
                case 'image':
                    $data[$slideId]['type'] = 'zoom';
                    $absPath = $item->getData('path');
                    $relPath = $this->syncHelper->getRelativePath($absPath, \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH);

                    $url = $item->getData('large_image_url');
                    if (empty($url)) {
                        if ($imageUrlBuilder) {
                            $url = $imageUrlBuilder->getUrl($imageFile, 'product_page_image_large');
                        } else {
                            $url = $this->imageHelper->init($product, 'product_page_image_large')
                                ->setImageFile($imageFile)
                                ->getUrl();
                        }
                    }

                    $altTextCacheModel->clearInstance()->load($relPath, 'path');
                    $alt = $altTextCacheModel->getValue();
                    if (empty($alt)) {
                        $alt = $item->getData('label') ?: $productName;
                    }
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
                        $data[$slideId]['html'] = '<div data-group="' . $group . '" data-id="' . $slideId . '"' . $dataTypeAttr . $disabledAttr . ' data-src="' . $url . '"' . $pinnedAttr . ' data-alt="' . $alt . '"></div>';
                    } else {
                        $data[$slideId]['type'] = 'image';
                        $data[$slideId]['html'] = '<img data-group="' . $group . '" data-id="' . $slideId . '" data-type="static"' . $disabledAttr . ' data-src="' . $url . '"' . $pinnedAttr . ' data-alt="' . $alt . '" />';
                    }

                    $data[$slideId]['placeholder'] = [];
                    if ($getPlaceholder) {
                        $data[$slideId]['placeholder']['url'] = $url;
                        $absPath = $item->getData('path');
                        if (is_file($absPath)) {
                            list($fileWidth, $fileHeight,) = getimagesize($absPath);
                            $data[$slideId]['placeholder']['width'] = $fileWidth;
                            $data[$slideId]['placeholder']['height'] = $fileHeight;
                        } else {
                            $data[$slideId]['placeholder']['width'] = $this->defaultPlaceholderWidth;
                            $data[$slideId]['placeholder']['height'] = $this->defaultPlaceholderHeight;
                        }
                    }

                    if ($baseImage == $imageFile) {
                        $this->activeSlideIds[$productId] = $slideId;
                    }

                    $index++;
                    break;
            }

            $iterator->next();
        }

        return $data;
    }

    /**
     * Get Sirv assets data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    protected function getSirvAssetsData($product)
    {
        $assetsData = $this->dataHelper->getAssetsData($product);
        if (empty($assetsData)) {
            return [];
        }

        $assets = $assetsData['assets'] ?? [];
        $productId = $product->getId();
        $this->assetsCacheData['timestamps'][$productId] = $assetsData['timestamp'];
        $idPrefix = 'sirv-item-' . $productId . '-';
        $group = 'group-' . $productId;
        $folderUrl = $this->syncHelper->getBaseUrl() . $assetsData['dirname'];
        $zoom = $this->dataHelper->getConfig('image_zoom') ?: 'enabled';
        $dataTypeAttr = $zoom == 'enabled' ? ' data-type="zoom"' : '';
        $disabledAttr = ($this->productId == $productId ? '' : ' data-disabled');
        $profile = $this->dataHelper->getConfig('profile');
        if (!empty($profile) && !in_array($profile, ['-', 'Default'])) {
            $profile = '?profile=' . $profile;
        } else {
            $profile = '';
        }
        $getPlaceholder = $this->usePlaceholder && ($this->productId == $productId);

        $data = [];
        $index = 0;

        foreach ($assets as $asset) {
            $slideId = $idPrefix . $index;
            $data[$slideId] = [];
            $data[$slideId]['index'] = $index;
            switch ($asset['type']) {
                case 'image':
                    $data[$slideId]['type'] = 'zoom';
                    $url = $folderUrl . '/' . $asset['name'];
                    $data[$slideId]['basename'] = basename($asset['name']);
                    $pinnedAttr = $this->pinnedItems['images'];
                    if (!(empty($pinnedAttr) || empty($this->pinnedItems['mask']) || preg_match($this->pinnedItems['mask'], $url))) {
                        $pinnedAttr = '';
                    }
                    $url .= $profile;
                    $data[$slideId]['placeholder'] = [];
                    if ($getPlaceholder) {
                        $data[$slideId]['placeholder']['url'] = $url;
                        $data[$slideId]['placeholder']['width'] = $asset['width'] ?? $this->defaultPlaceholderWidth;
                        $data[$slideId]['placeholder']['height'] = $asset['height'] ?? $this->defaultPlaceholderHeight;
                    }
                    $data[$slideId]['html'] = '<div data-group="' . $group . '" data-id="' . $slideId . '"' . $dataTypeAttr . $disabledAttr . ' data-src="' . $url . '"' . $pinnedAttr . '></div>';
                    $index++;
                    break;
                case 'video':
                case 'spin':
                case 'model':
                    $data[$slideId]['type'] = $asset['type'];
                    $url = $folderUrl . '/' . $asset['name'] . $profile;
                    $data[$slideId]['basename'] = basename($asset['name']);
                    $data[$slideId]['placeholder'] = [];
                    if ($getPlaceholder) {
                        if ($asset['type'] == 'video') {
                            $data[$slideId]['placeholder']['url'] = $url . (strpos($url, '?') === false ? '?' : '&') . 'thumbnail=' . ($asset['width'] ?? $this->defaultPlaceholderWidth);
                        } elseif ($asset['type'] == 'spin') {
                            $data[$slideId]['placeholder']['url'] = $url . (strpos($url, '?') === false ? '?' : '&') .'thumb=spin&image.frames=1';
                        } else {
                            $data[$slideId]['placeholder']['url'] = '';
                        }
                        $data[$slideId]['placeholder']['width'] = $asset['width'] ?? $this->defaultPlaceholderWidth;
                        $data[$slideId]['placeholder']['height'] = $asset['height'] ?? $this->defaultPlaceholderHeight;
                    }
                    $data[$slideId]['html'] = '<div data-group="' . $group . '" data-id="' . $slideId . '"' . $disabledAttr . ' data-src="' . $url . '"' . $this->pinnedItems[$asset['type'] . 's'] . '></div>';
                    $index++;
                    break;
            }
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $assetsModel = $objectManager->get(\Sirv\Magento2\Model\ManuallyAddedAssets::class);
        $collection = $assetsModel->getCollection();
        $collection->addFieldToFilter('product_id', $productId);
        $collection->setOrder('position', \Magento\Framework\Data\Collection::SORT_ORDER_ASC);
        $assets = $collection->getData() ?: [];
        $idPrefix = 'sirv-custom-item-' . $productId . '-';
        $group = 'group-' . $productId;
        $baseUrl = $this->syncHelper->getBaseUrl();
        foreach ($assets as $asset) {
            $slideId = $idPrefix . $index;
            $data[$slideId] = [];
            $data[$slideId]['index'] = $index;
            $data[$slideId]['basename'] = basename($asset['path']);
            $url = $baseUrl . $asset['path'];
            switch ($asset['type']) {
                case \Sirv\Magento2\Model\ManuallyAddedAssets::IMAGE_ASSET:
                    $data[$slideId]['type'] = 'zoom';
                    $pinnedAttr = $this->pinnedItems['images'];
                    if (!(empty($pinnedAttr) || empty($this->pinnedItems['mask']) || preg_match($this->pinnedItems['mask'], $url))) {
                        $pinnedAttr = '';
                    }
                    $url .= $profile;
                    $data[$slideId]['placeholder'] = [];
                    if ($getPlaceholder) {
                        $data[$slideId]['placeholder']['url'] = $url;
                        $data[$slideId]['placeholder']['width'] = $asset['width'] ?? $this->defaultPlaceholderWidth;
                        $data[$slideId]['placeholder']['height'] = $asset['height'] ?? $this->defaultPlaceholderHeight;
                    }
                    $data[$slideId]['html'] = '<div data-group="' . $group . '" data-id="' . $slideId . '"' . $dataTypeAttr . $disabledAttr . ' data-src="' . $url . '"' . $pinnedAttr . '></div>';
                    $index++;
                    break;
                case \Sirv\Magento2\Model\ManuallyAddedAssets::VIDEO_ASSET:
                    $data[$slideId]['type'] = 'video';
                case \Sirv\Magento2\Model\ManuallyAddedAssets::SPIN_ASSET:
                    isset($data[$slideId]['type']) || $data[$slideId]['type'] = 'spin';
                case \Sirv\Magento2\Model\ManuallyAddedAssets::MODEL_ASSET:
                    isset($data[$slideId]['type']) || $data[$slideId]['type'] = 'model';
                    $url .= $profile;
                    $data[$slideId]['placeholder'] = [];
                    if ($getPlaceholder) {
                        if ($asset['type'] == \Sirv\Magento2\Model\ManuallyAddedAssets::VIDEO_ASSET) {
                            $data[$slideId]['placeholder']['url'] = $url . (strpos($url, '?') === false ? '?' : '&') . 'thumbnail=' . ($asset['width'] ?? $this->defaultPlaceholderWidth);
                        } elseif ($asset['type'] == \Sirv\Magento2\Model\ManuallyAddedAssets::SPIN_ASSET) {
                            $data[$slideId]['placeholder']['url'] = $url . (strpos($url, '?') === false ? '?' : '&') .'thumb=spin&image.frames=1';
                        } else {
                            $data[$slideId]['placeholder']['url'] = '';
                        }
                        $data[$slideId]['placeholder']['width'] = $asset['width'] ?? $this->defaultPlaceholderWidth;
                        $data[$slideId]['placeholder']['height'] = $asset['height'] ?? $this->defaultPlaceholderHeight;
                    }
                    $data[$slideId]['html'] = '<div data-group="' . $group . '" data-id="' . $slideId . '"' . $disabledAttr . ' data-src="' . $url . '"' . $this->pinnedItems[$data[$slideId]['type'] . 's'] . '></div>';
                    $index++;
                    break;
            }
        }

        $extraAssets = $product->getData('extra_sirv_assets');
        if (!empty($extraAssets)) {
            $extraAssets = explode("\n", $extraAssets);
            if (!is_array($extraAssets)) {
                $extraAssets = [];
            }
            $idPrefix = 'sirv-extra-item-' . $productId . '-';
            foreach($extraAssets as $url) {
                $url = trim($url);
                if (empty($url)) {
                    continue;
                }

                $assetBasename = basename($url);
                if (preg_match('#\.(jpg|jpeg|png|gif|webp|tif|tiff|svg|bmp)#', $assetBasename)) {
                    $assetType = 'zoom';
                } elseif (preg_match('#\.(mpg|mpeg|m4v|mp4|avi|mov|ogv)#', $assetBasename)) {
                    $assetType = 'video';
                } elseif (preg_match('#\.(usdz|glb|dwg)#', $assetBasename)) {
                    $assetType = 'model';
                } elseif (preg_match('#\.spin#', $assetBasename)) {
                    $assetType = 'spin';
                } else {
                    continue;
                }

                $slideId = $idPrefix . $index;
                $data[$slideId] = [];
                $data[$slideId]['index'] = $index;
                $data[$slideId]['basename'] = $assetBasename;
                $data[$slideId]['type'] = $assetType;
                switch ($assetType) {
                    case 'zoom':
                        $pinnedAttr = $this->pinnedItems['images'];
                        if (!(empty($pinnedAttr) || empty($this->pinnedItems['mask']) || preg_match($this->pinnedItems['mask'], $url))) {
                            $pinnedAttr = '';
                        }
                        $url .= $profile;
                        $data[$slideId]['placeholder'] = [];
                        if ($getPlaceholder) {
                            $data[$slideId]['placeholder']['url'] = $url;
                            $data[$slideId]['placeholder']['width'] = $this->defaultPlaceholderWidth;
                            $data[$slideId]['placeholder']['height'] = $this->defaultPlaceholderHeight;
                        }
                        $data[$slideId]['html'] = '<div data-group="' . $group . '" data-id="' . $slideId . '"' . $dataTypeAttr . $disabledAttr . ' data-src="' . $url . '"' . $pinnedAttr . '></div>';
                        $index++;
                        break;
                    case 'video':
                    case 'spin':
                    case 'model':
                        $data[$slideId]['placeholder'] = [];
                        if ($getPlaceholder) {
                            if ($assetType == 'video') {
                                $data[$slideId]['placeholder']['url'] = $url . (strpos($url, '?') === false ? '?' : '&') . 'thumbnail=' . $this->defaultPlaceholderWidth;
                            } elseif ($assetType == 'spin') {
                                $data[$slideId]['placeholder']['url'] = $url . (strpos($url, '?') === false ? '?' : '&') .'thumb=spin&image.frames=1';
                            } else {
                                $data[$slideId]['placeholder']['url'] = '';
                            }
                            $data[$slideId]['placeholder']['width'] = $this->defaultPlaceholderWidth;
                            $data[$slideId]['placeholder']['height'] = $this->defaultPlaceholderHeight;
                        }
                        $data[$slideId]['html'] = '<div data-group="' . $group . '" data-id="' . $slideId . '"' . $disabledAttr . ' data-src="' . $url . '"' . $this->pinnedItems[$assetType . 's'] . '></div>';
                        $index++;
                        break;
                }
            }
        }

        return $data;
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
