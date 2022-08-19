<?php

namespace Sirv\Magento2\Block\Product;

/**
 * Create image block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ImageFactory extends \Magento\Catalog\Block\Product\ImageFactory
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\View\ConfigInterface
     */
    protected $presentationConfig;

    /**
     * @var \Magento\Catalog\Model\View\Asset\PlaceholderFactory
     */
    protected $viewAssetPlaceholderFactory;

    /**
     * @var \Magento\Catalog\Model\View\Asset\ImageFactory
     */
    protected $viewAssetImageFactory;

    /**
     * @var \Magento\Catalog\Model\Product\Image\ParamsBuilder
     */
    protected $imageParamsBuilder;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\View\ConfigInterface $presentationConfig
     * @param \Magento\Catalog\Model\View\Asset\ImageFactory $viewAssetImageFactory
     * @param \Magento\Catalog\Model\View\Asset\PlaceholderFactory $viewAssetPlaceholderFactory
     * @param \Magento\Catalog\Model\Product\Image\ParamsBuilder $imageParamsBuilder
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\View\ConfigInterface $presentationConfig,
        \Magento\Catalog\Model\View\Asset\ImageFactory $viewAssetImageFactory,
        \Magento\Catalog\Model\View\Asset\PlaceholderFactory $viewAssetPlaceholderFactory,
        \Magento\Catalog\Model\Product\Image\ParamsBuilder $imageParamsBuilder
    ) {
        $this->objectManager = $objectManager;
        $this->presentationConfig = $presentationConfig;
        $this->viewAssetPlaceholderFactory = $viewAssetPlaceholderFactory;
        $this->viewAssetImageFactory = $viewAssetImageFactory;
        $this->imageParamsBuilder = $imageParamsBuilder;
    }

    /**
     * Remove class from custom attributes
     *
     * @param array $attributes
     * @return array
     */
    protected function filterCustomAttributes(array $attributes)
    {
        if (isset($attributes['class'])) {
            unset($attributes['class']);
        }

        return $attributes;
    }

    /**
     * Retrieve image class for HTML element
     *
     * @param array $attributes
     * @return string
     */
    protected function getClass(array $attributes)
    {
        return $attributes['class'] ?? 'product-image-photo';
    }

    /**
     * Calculate image ratio
     *
     * @param int $width
     * @param int $height
     * @return float
     */
    protected function getRatio(int $width, int $height)
    {
        if ($width && $height) {
            return $height / $width;
        }

        return 1.0;
    }

    /**
     * Get image label
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $imageType
     * @return string
     */
    protected function getLabel(\Magento\Catalog\Model\Product $product, string $imageType)
    {
        $label = $product->getData($imageType . '_' . 'label');
        if (empty($label)) {
            $label = $product->getName();
        }

        return (string)$label;
    }

    /**
     * Create image block from product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $imageId
     * @param array|null $attributes
     * @return \Magento\Catalog\Block\Product\Image
     */
    public function create(\Magento\Catalog\Model\Product $product, string $imageId, array $attributes = null): \Magento\Catalog\Block\Product\Image
    {
        $viewImageConfig = $this->presentationConfig->getViewConfig()->getMediaAttributes(
            'Magento_Catalog',
            \Magento\Catalog\Helper\Image::MEDIA_TYPE_CONFIG_NODE,
            $imageId
        );

        $imageMiscParams = $this->imageParamsBuilder->build($viewImageConfig);
        $originalFilePath = $product->getData($imageMiscParams['image_type']);

        if ($originalFilePath === null || $originalFilePath === 'no_selection') {
            $imageUrl = $this->getSirvAssetsUrl($product);
            if (!$imageUrl) {
                $imageAsset = $this->viewAssetPlaceholderFactory->create(
                    [
                        'type' => $imageMiscParams['image_type']
                    ]
                );
                $imageUrl = $imageAsset->getUrl();
            }
        } else {
            $imageAsset = $this->viewAssetImageFactory->create(
                [
                    'miscParams' => $imageMiscParams,
                    'filePath' => $originalFilePath,
                ]
            );
            $imageUrl = $imageAsset->getUrl();
        }

        $attributes = $attributes === null ? [] : $attributes;

        $data = [
            'data' => [
                'template' => 'Magento_Catalog::product/image_with_borders.phtml',
                'image_url' => $imageUrl,
                'width' => $imageMiscParams['image_width'],
                'height' => $imageMiscParams['image_height'],
                'label' => $this->getLabel($product, $imageMiscParams['image_type'] ?? ''),
                'ratio' => $this->getRatio($imageMiscParams['image_width'] ?? 0, $imageMiscParams['image_height'] ?? 0),
                'custom_attributes' => $this->filterCustomAttributes($attributes),
                'class' => $this->getClass($attributes),
                'product_id' => $product->getId()
            ],
        ];

        return $this->objectManager->create(\Magento\Catalog\Block\Product\Image::class, $data);
    }

    /**
     * Get Sirv asset URL
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string | false
     */
    protected function getSirvAssetsUrl($product)
    {
        $dataHelper = $this->objectManager->get(\Sirv\Magento2\Helper\Data::class);
        $assetsData = $dataHelper->getAssetsData($product);
        if (empty($assetsData) || empty($assetsData['assets']) || empty($assetsData['featured_image'])) {
            return false;
        }

        return 'https://' . $dataHelper->getSirvDomain(false) . '/' . $assetsData['featured_image'];
    }
}
