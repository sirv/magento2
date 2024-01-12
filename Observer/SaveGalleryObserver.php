<?php

namespace Sirv\Magento2\Observer;

/**
 * Observer that saves Sirv assets gallery after saving a product
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class SaveGalleryObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Data helper
     *
     * @var \Sirv\Magento2\Helper\Data\Backend
     */
    protected $dataHelper = null;

    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync\Backend
     */
    protected $syncHelper = null;

    /**
     * Assets cache helper factory
     *
     * @var \Sirv\Magento2\Helper\AssetsCacheFactory
     */
    protected $assetsCacheHelperFactory = null;

    /**
     * Manually added assets model factory
     *
     * @var \Sirv\Magento2\Model\ManuallyAddedAssetsFactory
     */
    protected $manuallyAddedAssetsModelFactory = null;

    /**
     * Constructor
     *
     * @param \Sirv\Magento2\Helper\Data\Backend $dataHelper
     * @param \Sirv\Magento2\Helper\Sync\Backend $syncHelper
     * @param \Sirv\Magento2\Helper\AssetsCacheFactory $assetsCacheHelperFactory
     * @param \Sirv\Magento2\Model\ManuallyAddedAssetsFactory $manuallyAddedAssetsModelFactory
     */
    public function __construct(
        \Sirv\Magento2\Helper\Data\Backend $dataHelper,
        \Sirv\Magento2\Helper\Sync\Backend $syncHelper,
        \Sirv\Magento2\Helper\AssetsCacheFactory $assetsCacheHelperFactory,
        \Sirv\Magento2\Model\ManuallyAddedAssetsFactory $manuallyAddedAssetsModelFactory
    ) {
        $this->dataHelper = $dataHelper;
        $this->syncHelper = $syncHelper;
        $this->assetsCacheHelperFactory = $assetsCacheHelperFactory;
        $this->manuallyAddedAssetsModelFactory = $manuallyAddedAssetsModelFactory;
    }

    /**
     * Execute method
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     *
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Catalog\Controller\Adminhtml\Product\Save $controller */
        $controller = $observer->getController();

        $productId = $controller->getRequest()->getParam('id');
        if ($productId === null) {
            return $this;
        }

        $data = $controller->getRequest()->getPostValue();
        $data = isset($data['sirv_assets_gallery']) ? $data['sirv_assets_gallery'] : [];

        $automaticallyAddedAssets = isset($data['automatically_added']) ? $data['automatically_added'] : [];
        if (!empty($automaticallyAddedAssets)) {
            //$count = 0;
            foreach ($automaticallyAddedAssets as $assetData) {
                if (!empty($assetData['removed'])) {
                    $path = ltrim($assetData['file'], '/');
                    $removed = $this->syncHelper->removeAsset($path);
                    //$count++;
                }
            }
            //NOTE: update cache when loading a section to avoid issue with outdated .view file that contain deleted items
            /*
            if ($count > 0) {
                $assetsCacheHelper = $this->assetsCacheHelperFactory->create();
                $assetsCacheHelper->updateAssetsCache([$productId], true);
            }
            */
        }

        $manuallyAddedAssets = isset($data['manually_added']) ? $data['manually_added'] : [];
        if (!empty($manuallyAddedAssets)) {
            $manuallyAddedAssetsModel = $this->manuallyAddedAssetsModelFactory->create();
            $resource = $manuallyAddedAssetsModel->getResource();
            foreach ($manuallyAddedAssets as $assetData) {
                if (empty($assetData['id'])) {
                    //NOTE: new asset
                    if (empty($assetData['removed'])) {
                        if ($assetData['type'] == 'image') {
                            $type = \Sirv\Magento2\Model\ManuallyAddedAssets::IMAGE_ASSET;
                        } else if ($assetData['type'] == 'video') {
                            $type = \Sirv\Magento2\Model\ManuallyAddedAssets::VIDEO_ASSET;
                        } else if ($assetData['type'] == 'spin') {
                            $type = \Sirv\Magento2\Model\ManuallyAddedAssets::SPIN_ASSET;
                        } else if ($assetData['type'] == 'model') {
                            $type = \Sirv\Magento2\Model\ManuallyAddedAssets::MODEL_ASSET;
                        } else {
                            $type = \Sirv\Magento2\Model\ManuallyAddedAssets::UNKNOWN_TYPE;
                        }

                        $resource->insertAssetData([
                            'product_id' => $productId,
                            'path' => $assetData['file'],
                            'position' => $assetData['position'],
                            'type' => $type,
                            'width' => $assetData['width'],
                            'height' => $assetData['height'],
                            'size' => $assetData['size']
                        ]);
                    }
                } else {
                    if (empty($assetData['removed'])) {
                        $manuallyAddedAssetsModel->clearInstance()->load($assetData['id'], 'id');
                        $manuallyAddedAssetsModel->setPosition($assetData['position']);
                        $manuallyAddedAssetsModel->save();
                    } else {
                        //NOTE: asset to remove
                        $resource->deleteById($assetData['id'], 'id');
                        $path = ltrim($assetData['file'], '/');
                        $removed = $this->syncHelper->removeAsset($path);
                    }
                }
            }

            $contents = $manuallyAddedAssetsModel->getData('contents');
        }

        return $this;
    }
}
