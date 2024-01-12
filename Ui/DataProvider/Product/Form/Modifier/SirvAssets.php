<?php

namespace Sirv\Magento2\Ui\DataProvider\Product\Form\Modifier;

/**
 * Sirv modifier for catalog product form
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class SirvAssets extends \Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier
{
    const CODE_SIRV_ASSETS_GROUP = 'sirv_assets';
    const SORT_ORDER = 24;

    /**
     * Locator
     *
     * @var \Magento\Catalog\Model\Locator\LocatorInterface
     */
    protected $locator;

    /**
     * Module manager
     *
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /**
     * Product metadata
     *
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * Constructor
     *
     * @param \Magento\Catalog\Model\Locator\LocatorInterface $locator
     */
    public function __construct(
        \Magento\Catalog\Model\Locator\LocatorInterface $locator
    ) {
        $this->locator = $locator;
    }

    /**
     * Method to modify meta
     *
     * @param array $meta
     */
    public function modifyMeta(array $meta)
    {
        if (!$this->locator->getProduct()->getId() ||
            !$this->getModuleManager()->isOutputEnabled('Sirv_Magento2')) {
            return $meta;
        }

        $version = $this->getProductMetadata()->getVersion();
        if (version_compare($version, '2.2.0', '<')) {
            return $meta;
        }

        $meta[static::CODE_SIRV_ASSETS_GROUP] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'htmlContent',
                    ],
                    'wrapper' => [
                        'label' => __('Sirv images, videos, spins & models'),
                        'collapsible' => true,
                        'opened' => false,
                        'canShow' => true,
                        'componentType' => \Magento\Ui\Component\Form\Fieldset::NAME,
                        'sortOrder' => 24,
                    ],
                ],
                'block' => [
                    'name' => 'sirv_assets',
                ],
            ],
        ];

        return $meta;
    }

    /**
     * {@inheritdoc}
     * @since 100.1.0
     */
    public function modifyData(array $data)
    {
        return $data;
    }

    /**
     * Retrieve module manager instance
     *
     * @return \Magento\Framework\Module\Manager
     */
    protected function getModuleManager()
    {
        if ($this->moduleManager === null) {
            $this->moduleManager = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Module\Manager::class
            );
        }

        return $this->moduleManager;
    }

    /**
     * The getter function to get the ProductMetadata
     *
     * @return \Magento\Framework\App\ProductMetadataInterface
     */
    protected function getProductMetadata()
    {
        if ($this->productMetadata === null) {
            $this->productMetadata = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\App\ProductMetadataInterface::class
            );
        }

        return $this->productMetadata;
    }
}
