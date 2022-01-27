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
            /** @var \Sirv\Magento2\Helper\Data $dataHelper */
            $dataHelper = $this->_objectManager::getInstance()->get(
                \Sirv\Magento2\Helper\Data::class
            );
            $productAssetsFolder = $dataHelper->getConfig('product_assets_folder') ?: '';
            $productAssetsFolder = trim($productAssetsFolder);
            $productAssetsFolder = trim($productAssetsFolder, '/');

            if (empty($productAssetsFolder)) {
                $result = ['message' => 'Product assets folder is empty!'];
            } else {
                $baseUrl = 'https://' . $dataHelper->getSirvDomain(/*false*/);

                /** @var \Sirv\Magento2\Model\Assets $assetsModel */
                $assetsModel = $this->_objectManager::getInstance()->get(
                    \Sirv\Magento2\Model\Assets::class
                );

                /** @var \Magento\Catalog\Model\ProductRepository $productRepository */
                $productRepository = $this->_objectManager::getInstance()->get(
                    \Magento\Catalog\Model\ProductRepository::class
                );

                foreach ($productIds as $productId) {
                    $product = $productRepository->getById($productId);
                    $productSku = $product->getSku();

                    $assetsFolder = str_replace(
                        ['{product-id}', '{product-sku}', '{product-sku-2-char}', '{product-sku-3-char}'],
                        [$productId, $productSku, substr($productSku, 0, 2), substr($productSku, 0, 3)],
                        $productAssetsFolder,
                        $found
                    );
                    if (!$found) {
                        $assetsFolder = $productAssetsFolder . '/' . $productSku;
                    }

                    $url = $baseUrl . '/' . $assetsFolder . '.view?info';

                    $assetsModel->load($productId, 'product_id');
                    $contents = $assetsModel->getData('contents');
                    if ($contents === null) {
                        $contents = $this->downloadContents($url);
                        $assetsModel->setData('product_id', $productId);
                        $assetsModel->setData('contents', $contents);
                    } else {
                        $contents = json_decode($contents);
                        $modified = is_object($contents) && isset($contents->modified) ? $contents->modified : '';
                        $modified = empty($modified) ? false : strtotime($modified);
                        $lastModifiedTime = $this->getLastModifiedTime($url, $code, $error);
                        $lastModifiedTime = empty($lastModifiedTime) ? false : strtotime($lastModifiedTime);
                        if ($modified) {
                            if ($lastModifiedTime) {
                                if ($lastModifiedTime - $modified > 1) {
                                    $contents = $this->downloadContents($url);
                                    $assetsModel->setData('contents', $contents);
                                }
                            } else {
                                $contents = json_encode(['curl' => ['code' => $code, 'error' => $error]]);
                                $assetsModel->setData('contents', $contents);
                            }
                        } else {
                            if ($lastModifiedTime) {
                                $contents = $this->downloadContents($url);
                                $assetsModel->setData('contents', $contents);
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
     * Download contents
     *
     * @param string $url
     * @return string
     */
    protected function downloadContents($url)
    {
        if (!isset(self::$curlHandle)) {
            self::$curlHandle = curl_init();
        }

        curl_setopt_array(
            self::$curlHandle,
            [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'GET',
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
