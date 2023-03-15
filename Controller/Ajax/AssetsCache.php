<?php

namespace Sirv\Magento2\Controller\Ajax;

/**
 * Assets cache controller
 *
 */
class AssetsCache extends \Magento\Framework\App\Action\Action
{
    /**
     * cURL resource
     *
     * @var resource
     */
    protected static $curlHandle = null;

    /**
     * Update assets cache
     *
     * @return string
     */
    public function execute()
    {
        $result = [];
        $productIds = $this->getRequest()->getParam('ids');

        if (is_array($productIds) && !empty($productIds)) {
            /** @var \Magento\Framework\ObjectManagerInterface $objectManager */
            $objectManager = $this->_objectManager::getInstance();
            /** @var \Sirv\Magento2\Helper\Data $dataHelper */
            $dataHelper = $objectManager->get(\Sirv\Magento2\Helper\Data::class);

            $productAssetsFolder = $dataHelper->getConfig('product_assets_folder') ?: '';
            $productAssetsFolder = trim(trim($productAssetsFolder), '/');

            if (empty($productAssetsFolder)) {
                $result = ['message' => 'Product assets folder is empty!'];
            } else {
                /** @var \Sirv\Magento2\Model\Assets $assetsModel */
                $assetsModel = $objectManager->get(\Sirv\Magento2\Model\Assets::class);
                /** @var \Magento\Catalog\Model\ProductRepository $productRepository */
                $productRepository = $objectManager->get(\Magento\Catalog\Model\ProductRepository::class);

                $baseUrl = 'https://' . $dataHelper->getSirvDomain();

                $attrMatches = [];
                preg_match_all('#{attribute:(admin:)?([a-zA-Z0-9_]++)}#', $productAssetsFolder, $attrMatches, PREG_SET_ORDER);

                //NOTE: product assets folder must contain a unique pattern
                if (!preg_match('#{product-(?:sku|id)}#', $productAssetsFolder)) {
                    $productAssetsFolder = $productAssetsFolder . '/{product-sku}';
                }

                foreach ($productIds as $productId) {
                    $product = $productRepository->getById($productId);

                    $productSku = $product->getSku();
                    $assetsFolder = str_replace(
                        ['{product-id}', '{product-sku}', '{product-sku-2-char}', '{product-sku-3-char}'],
                        [$productId, $productSku, substr($productSku, 0, 2), substr($productSku, 0, 3)],
                        $productAssetsFolder
                    );
                    foreach ($attrMatches as $match) {
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
                    $url = $baseUrl . '/' . $assetsFolder . '.view?info';

                    $assetsModel->load($productId, 'product_id');
                    $contents = $assetsModel->getData('contents');
                    $assetsModel->setData('product_id', $productId);
                    if ($contents === null) {
                        $assetsInfo = $dataHelper->downloadAssetsInfo($url);
                        $assetsInfo = json_decode($assetsInfo, true);
                        if (is_array($assetsInfo)) {
                            $contents = $dataHelper->prepareAssetsInfo($assetsInfo);
                            $assetsModel->setData('contents', json_encode($contents));
                        }
                    } else {
                        $contents = json_decode($contents, true);
                        $modified = isset($contents['modified']) ? (int)$contents['modified'] : false;
                        $lastModifiedTime = $this->getLastModifiedTime($url, $code, $error);
                        $lastModifiedTime = empty($lastModifiedTime) ? false : strtotime($lastModifiedTime);

                        if ($modified) {
                            if ($lastModifiedTime) {
                                if ($lastModifiedTime - $modified > 1) {
                                    $assetsInfo = $dataHelper->downloadAssetsInfo($url);
                                    $assetsInfo = json_decode($assetsInfo, true);
                                    if (is_array($assetsInfo)) {
                                        $contents = $dataHelper->prepareAssetsInfo($assetsInfo);
                                        $assetsModel->setData('contents', json_encode($contents));
                                    }
                                }
                            } else {
                                $assetsInfo = ['curl' => ['code' => $code, 'error' => $error]];
                                $contents = $dataHelper->prepareAssetsInfo($assetsInfo);
                                $assetsModel->setData('contents', json_encode($contents));
                            }
                        } else {
                            if ($lastModifiedTime) {
                                $assetsInfo = $dataHelper->downloadAssetsInfo($url);
                                $assetsInfo = json_decode($assetsInfo, true);
                                if (is_array($assetsInfo)) {
                                    $contents = $dataHelper->prepareAssetsInfo($assetsInfo);
                                    $assetsModel->setData('contents', json_encode($contents));
                                }
                            }
                        }
                    }
                    $assetsModel->setData('timestamp', time());
                    $assetsModel->save();
                }

                $result = ['message' => 'Content updated.'];
            }
        } else {
            $result = ['message' => 'Product IDs list is empty!'];
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData($result);

        return $resultJson;
    }

    /**
     * Get last modified time
     *
     * @param string $url
     * @param integer $code
     * @param integer $error
     * @return string
     */
    protected function getLastModifiedTime($url, &$code, &$error)
    {
        if (!isset(self::$curlHandle)) {
            self::$curlHandle = curl_init();
        }

        curl_setopt_array(
            self::$curlHandle,
            [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'HEAD',
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]
        );

        $contents = curl_exec(self::$curlHandle);
        $code = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);
        $error = curl_errno(self::$curlHandle);

        if (($code == 200) && preg_match('#Last-Modified: ([^\n]++)\n#', $contents, $match)) {
            return $match[1];
        }

        return '';
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
