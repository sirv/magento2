<?php

namespace MagicToolbox\Sirv\Controller\Adminhtml\Ajax;

/**
 * Synchronize ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Synchronize extends \MagicToolbox\Sirv\Controller\Adminhtml\Settings
{
    /**
     * Data helper
     *
     * @var \MagicToolbox\Sirv\Helper\Data\Backend
     */
    protected $dataHelper = null;

    /**
     * Sync helper
     *
     * @var \MagicToolbox\Sirv\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper
     * @param \MagicToolbox\Sirv\Helper\Sync $syncHelper
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MagicToolbox\Sirv\Helper\Data\Backend $dataHelper,
        \MagicToolbox\Sirv\Helper\Sync $syncHelper
    ) {
        parent::__construct($context, $resultPageFactory);
        $this->dataHelper = $dataHelper;
        $this->syncHelper = $syncHelper;
    }

    /**
     * Synchronize action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
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

        switch ($action) {
            case 'synchronize':
                $stage = isset($postData['syncStage']) ? (int)$postData['syncStage'] : 0;
                if ($stage) {
                    $data = $this->syncHelper->syncMediaGallery($stage);
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
                $pathes = $this->syncHelper->getFailedPathes();
                $productMediaRelPath = $this->syncHelper->getProductMediaRelPath();
                $mediaDirAbsPath = $this->syncHelper->getMediaDirAbsPath();
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
                $mediaBaseUrl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                $mediaBaseUrl = rtrim($mediaBaseUrl, '\\/');
                foreach ($pathes as $i => $path) {
                    $pathes[$i] = [
                        'path' => $mediaDirAbsPath . $productMediaRelPath . '/' . ltrim($path, '\\/'),
                        'url' => $mediaBaseUrl . $productMediaRelPath . '/' . ltrim($path, '\\/'),
                    ];
                }
                $data = ['pathes' => $pathes];
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
