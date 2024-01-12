<?php

namespace Sirv\Magento2\Helper;

/**
 * Asset's cache helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class AssetsCache extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Object manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

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
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        parent::__construct($context);

        $this->objectManager = $objectManager;
    }

    /**
     * Update assets cache
     *
     * @param array $productIds
     * @param boolean $force
     * @return string
     */
    public function updateAssetsCache($productIds, $force = false)
    {
        $message = '';

        if (is_array($productIds) && !empty($productIds)) {
            /** @var \Sirv\Magento2\Helper\Data $dataHelper */
            $dataHelper = $this->objectManager->get(\Sirv\Magento2\Helper\Data::class);

            $productAssetsFolder = $dataHelper->getConfig('product_assets_folder') ?: '';
            $productAssetsFolder = trim(trim($productAssetsFolder), '/');

            if (empty($productAssetsFolder)) {
                $message = 'Product assets folder is empty!';
            } else {
                /** @var \Sirv\Magento2\Model\Assets $assetsModel */
                $assetsModel = $this->objectManager->get(\Sirv\Magento2\Model\Assets::class);
                /** @var \Magento\Catalog\Model\ProductRepository $productRepository */
                $productRepository = $this->objectManager->get(\Magento\Catalog\Model\ProductRepository::class);

                $baseUrl = 'https://' . $dataHelper->getSirvDomain();

                foreach ($productIds as $productId) {
                    $product = $productRepository->getById($productId);
                    $assetsFolder = $dataHelper->getProductAssetsFolderPath($product);
                    $url = $baseUrl . '/' . $assetsFolder . '.view?info';

                    $assetsModel->load($productId, 'product_id');
                    $contents = $assetsModel->getData('contents');
                    $assetsModel->setData('product_id', $productId);

                    if ($contents === null || $force) {
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

                $message = 'Content updated.';
            }
        } else {
            $message = 'Product IDs list is empty!';
        }

        return $message;
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

        if (($code == 200) && preg_match('#Last-Modified: ([^\n]++)\n#i', $contents, $match)) {
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
