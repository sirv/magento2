<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Assets gallery controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class AssetsGallery extends \Magento\Backend\App\Action
{
    /**
     * Assets cache helper factory
     *
     * @var \Sirv\Magento2\Helper\AssetsCacheFactory
     */
    protected $assetsCacheHelperFactory = null;

    /**
     * Product model factory
     *
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productModelFactory = null;

    /**
     * Data helper factory
     *
     * @var \Sirv\Magento2\Helper\Data\BackendFactory
     */
    protected $dataHelperFactory = null;

    /**
     * Magento asset repository factory
     *
     * @var \Magento\Framework\View\Asset\RepositoryFactory
     */
    protected $assetRepoFactory = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Sirv\Magento2\Helper\AssetsCacheFactory $assetsCacheHelperFactory
     * @param \Magento\Catalog\Model\ProductFactory $productModelFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Magento\Framework\View\Asset\RepositoryFactory $assetRepoFactory
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Sirv\Magento2\Helper\AssetsCacheFactory $assetsCacheHelperFactory,
        \Magento\Catalog\Model\ProductFactory $productModelFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Magento\Framework\View\Asset\RepositoryFactory $assetRepoFactory
    ) {
        parent::__construct($context);
        $this->assetsCacheHelperFactory = $assetsCacheHelperFactory;
        $this->productModelFactory = $productModelFactory;
        $this->dataHelperFactory = $dataHelperFactory;
        $this->assetRepoFactory = $assetRepoFactory;
    }

    /**
     * Execute action
     *
     * @return string
     */
    public function execute()
    {
        $queryData = $this->getRequest()->getQueryValue();
        $action = $queryData['action'] ?? '';

        $result = [
            'success' => false,
            'data' => []
        ];
        $data = [];

        switch ($action) {
            case 'get_assets_data':
                $data['assets'] = [];
                $data['folderPath'] = '';
                $data['folderExists'] = false;

                $productId = $queryData['productId'] ?? 0;
                if (!$productId) {
                    $data['error'] = __('Product ID not specified!');
                    break;
                }

                $assetsCacheHelper = $this->assetsCacheHelperFactory->create();
                $assetsCacheHelper->updateAssetsCache([$productId], true);

                /** @var \Magento\Catalog\Model\Product $productModel */
                $productModel = $this->productModelFactory->create()->load($productId);

                $dataHelper = $this->dataHelperFactory->create();

                $data['folderPath'] = $dataHelper->getProductAssetsFolderPath($productModel);

                $assetsData = $dataHelper->getAssetsData($productModel);
                if (empty($assetsData)) {
                    $data['error'] = __('No assets data found!');
                    break;
                }

                $data['folderExists'] = isset($assetsData['dirname']) ? !empty($assetsData['dirname']) : false;

                $assets = $assetsData['assets'] ?? [];
                if (empty($assets)) {
                    $result['success'] = true;
                    break;
                }

                $baseUrl = 'https://' . $dataHelper->getSirvDomain();

                $assetRepository = $this->assetRepoFactory->create();
                $modelThumbUrl = $assetRepository->createAsset('Sirv_Magento2::images/icon.3d.3.svg')->getUrl();

                $position = 0;
                foreach ($assets as $asset) {
                    $_asset = [];
                    $_asset['file'] = $assetsData['dirname'] . '/' . $asset['name'];
                    $_asset['url'] = $_asset['viewUrl'] = $baseUrl . $_asset['file'];
                    $_asset['url'] = str_replace(' ', '%20', $_asset['url']);
                    if ($asset['type'] == 'video') {
                        $_asset['url'] .= '?thumb';
                    } elseif ($asset['type'] == 'spin') {
                        $_asset['url'] .= '?thumb';
                    } elseif ($asset['type'] == 'model') {
                        $_asset['url'] = $modelThumbUrl;
                        $_asset['viewUrl'] .= '?embed';
                    } elseif ($asset['type'] == 'image') {
                        //
                    } else {
                        continue;
                    }

                    $_asset['size'] = $asset['size'] ?? 0;
                    $_asset['width'] = $asset['width'] ?? 0;
                    $_asset['height'] = $asset['height'] ?? 0;

                    $_asset['position'] = $position;
                    $position++;

                    $data['assets'][] = $_asset;
                }

                $result['success'] = true;
                break;
            case 'create_folder':
                $folderPath = $queryData['folderPath'] ?? false;
                if (!$folderPath) {
                    $data['error'] = __('Folder path not specified!');
                    break;
                }

                $dataHelper = $this->dataHelperFactory->create();
                $dataHelper->createFolder($folderPath);

                $result['success'] = true;
                break;
            default:
                $data['error'] = __('Unknown action: "%1"', $action);
                break;
        }

        $result['data'] = $data;

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData($result);

        return $resultJson;
    }
}
