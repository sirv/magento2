<?php

namespace Sirv\Magento2\Block\Adminhtml\Product\Edit;

/**
 * Sirv assets fieldset
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class SirvAssets extends \Magento\Framework\View\Element\Template
{
    /**
     * Path to template file
     *
     * @var string
     */
    protected $_template = 'Sirv_Magento2::product/edit/sirv_assets.phtml';

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Get product assets folder
     *
     * @return string
     */
    public function getProductAssetsFolder()
    {
        static $assetsFolder = null;

        if ($assetsFolder !== null) {
            return $assetsFolder;
        }

        $product = $this->_coreRegistry->registry('current_product');

        if (!$product) {
            $assetsFolder = '';
            return '';
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->get(\Sirv\Magento2\Helper\Data::class);

        $assetsFolder = $dataHelper->getConfig('product_assets_folder') ?: '';
        $assetsFolder = trim(trim($assetsFolder), '/');

        if (empty($assetsFolder)) {
            return '';
        }

        //NOTE: product assets folder must contain a unique pattern
        if (!preg_match('#{product-(?:sku|id)}#', $assetsFolder)) {
            $assetsFolder = $assetsFolder . '/{product-sku}';
        }

        $productId = $product->getId();
        $productSku = $product->getSku();

        $assetsFolder = str_replace(
            ['{product-id}', '{product-sku}', '{product-sku-2-char}', '{product-sku-3-char}'],
            [$productId, $productSku, substr($productSku, 0, 2), substr($productSku, 0, 3)],
            $assetsFolder
        );

        $matches = [];
        if (preg_match_all('#{attribute:(admin:)?([a-zA-Z0-9_]++)}#', $assetsFolder, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrValue = $product->getData($match[2]);
                if (is_string($attrValue)) {
                    $attrValue = trim($attrValue);
                    if (empty($attrValue)) {
                        $attrValue = false;
                    } else {
                        if (empty($match[1])) {
                            $attrTextValue = $product->getAttributeText($match[2]);
                        } else {
                            $pAttr = $product->getResource()->getAttribute($match[2]);
                            $storeId = $pAttr->getStoreId();
                            $attrTextValue = $pAttr->setStoreId(0)->getSource()->getOptionText($attrValue);
                            $pAttr->setStoreId($storeId);
                        }
                        if (is_string($attrTextValue)) {
                            $attrTextValue = trim($attrTextValue);
                            if (!empty($attrTextValue)) {
                                $attrValue = $attrTextValue;
                            }
                        }
                    }
                } else {
                    $attrValue = false;
                }

                if ($attrValue) {
                    $assetsFolder = str_replace('{attribute:' . $match[1] . $match[2] . '}', $attrValue, $assetsFolder);
                } else {
                    $pattern = '{attribute:' . $match[1] . $match[2] . '}';
                    $assetsFolder = preg_replace(
                        [
                            '#/' . $pattern . '/#',
                            '#^' . $pattern . '/|/' . $pattern . '$|' . $pattern . '#'
                        ],
                        [
                            '/',
                            ''
                        ],
                        $assetsFolder
                    );
                }
            }
        }

        return $assetsFolder;
    }

    /**
     * Get product assets folder URL
     *
     * @return string
     */
    public function getProductAssetsFolderUrl()
    {
        $assetsFolder = $this->getProductAssetsFolder();

        return 'https://my.sirv.com/#/browse/' . $assetsFolder;
    }

    /**
     * Can show block
     *
     * @return boolean
     */
    public function canShowBlock()
    {
        if (!$this->_request->getParam('id')) {
            return false;
        }

        return true;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->canShowBlock()) {
            return 'To use this section save the new product first please.';
        }

        return parent::_toHtml();
    }
}
