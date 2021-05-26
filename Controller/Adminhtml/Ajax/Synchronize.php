<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Synchronize ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Synchronize extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync\Backend
     */
    protected $syncHelper = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Sirv\Magento2\Helper\Sync\Backend $syncHelper
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Sirv\Magento2\Helper\Sync\Backend $syncHelper
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->syncHelper = $syncHelper;
    }

    /**
     * Synchronize action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $action = isset($postData['dataAction']) ? $postData['dataAction'] : '';

        $result = [
            'success' => false,
            'data' => []
        ];
        $data = [];

        /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
        /* $dataHelper = $this->getDataHelper(); */

        switch ($action) {
            case 'synchronize':
                $stage = isset($postData['syncStage']) ? (int)$postData['syncStage'] : 0;
                if ($stage) {
                    $doClean = isset($postData['doClean']) ? $postData['doClean'] == 'true' : false;
                    $data = $this->syncHelper->syncMediaGallery($stage, $doClean);
                } else {
                    $data = $this->syncHelper->getSyncData(true);
                }
                $result['success'] = true;
                break;
            case 'flush':
                $flushMethod = isset($postData['flushMethod']) ? $postData['flushMethod'] : false;
                if ($flushMethod) {
                    $result['success'] = $this->syncHelper->flushCache($flushMethod);
                    $data = [
                        'method' => $flushMethod
                    ];
                }
                break;
            case 'get_failed':
                $productMediaRelPath = $this->syncHelper->getProductMediaRelPath();
                $mediaDirAbsPath = $this->syncHelper->getMediaDirAbsPath();
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
                $mediaBaseUrl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                $mediaBaseUrl = rtrim($mediaBaseUrl, '\\/');

                $failedPathes = $this->syncHelper->getFailedPathes();
                $failedCount = count($failedPathes);
                foreach ($failedPathes as $i => $path) {
                    $failedPathes[$i] = $productMediaRelPath . '/' . ltrim($path, '\\/');
                }
                $failedPathes = array_flip($failedPathes);

                $messageModel = $this->syncHelper->getMessageModel();
                $failedData = [];
                foreach ($messageModel->getCollection() as $modelItem) {
                    $relPath = $modelItem->getPath();

                    if (isset($failedPathes[$relPath])) {
                        unset($failedPathes[$relPath]);

                        $absPath = $mediaDirAbsPath . $relPath;
                        $isFile = is_file($absPath);
                        $fileSize = $isFile ? filesize($absPath) : 0;

                        $message = $modelItem->getMessage();
                        if (!isset($failedData[$message])) {
                            $failedData[$message] = [];
                        }

                        $failedData[$message][] = [
                            'path' => $absPath,
                            'url' => $mediaBaseUrl . $relPath,
                            'isFile' => $isFile,
                            'fileSize' => $fileSize
                        ];
                    }
                }

                if (!empty($failedPathes)) {
                    $message = 'Unknown error.';
                    if (!isset($failedData[$message])) {
                        $failedData[$message] = [];
                    }
                    foreach (array_flip($failedPathes) as $relPath) {
                        $absPath = $mediaDirAbsPath . $relPath;
                        $isFile = is_file($absPath);
                        $fileSize = $isFile ? filesize($absPath) : 0;
                        $failedData[$message][] = [
                            'path' => $absPath,
                            'url' => $mediaBaseUrl . $relPath,
                            'isFile' => $isFile,
                            'fileSize' => $fileSize
                        ];
                    }
                    $messageEx = $message .  ' See <a href="https://my.sirv.com/#/events/" target="_blank">Sirv notification section</a> for more information.';
                    $failedData[$messageEx] = $failedData[$message];
                    unset($failedData[$message]);
                }

                $failedData = [
                    'count' => $failedCount,
                    'groups' => $failedData,
                ];

                $data = ['failed' => $failedData];
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
