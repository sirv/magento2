<?php

namespace MagicToolbox\Sirv\Block\Product\Renderer;

/**
 * Swatch renderer block
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Configurable extends \Magento\Swatches\Block\Product\Renderer\Configurable
{
    /**
     * Get additional values for js config
     *
     * @return array
     */
    protected function _getAdditionalConfig()
    {
        $config = parent::_getAdditionalConfig();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $viewerHelper = $objectManager->get(\MagicToolbox\Sirv\Helper\MediaViewer::class);
        $assetsData = [];

        foreach ($this->getAllowProducts() as $product) {
            $assetsData[$product->getId()] = $viewerHelper->getSirvAssetsData($product);
        }

        $config['sirvConfig'] = [
            'baseUrl' => $viewerHelper->getBaseUrl(),
            'viewerContentsSource' => $viewerHelper->getViewerContentsSource(),
            'assetsData' => $assetsData
        ];

        return $config;
    }
}
