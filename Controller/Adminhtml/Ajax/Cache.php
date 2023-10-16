<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Cache ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Cache extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Sync helper factory
     *
     * @var \Sirv\Magento2\Helper\Sync\BackendFactory
     */
    protected $syncHelperFactory = null;

    /**
     * Cache model factory
     *
     * @var \Sirv\Magento2\Model\CacheFactory
     */
    protected $cacheModelFactory = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory
     * @param \Sirv\Magento2\Model\CacheFactory $cacheModelFactory
     * @return void
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Sirv\Magento2\Helper\Sync\BackendFactory $syncHelperFactory,
        \Sirv\Magento2\Model\CacheFactory $cacheModelFactory
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->syncHelperFactory = $syncHelperFactory;
        $this->cacheModelFactory = $cacheModelFactory;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $action = isset($postData['dataAction']) ? $postData['dataAction'] : 'view-failed';
        $status = isset($postData['status']) ? $postData['status'] : 'synced';
        $pageNum = isset($postData['pageNum']) ? (int)$postData['pageNum'] : 0;
        $pageSize = isset($postData['pageSize']) ? (int)$postData['pageSize'] : 100;
        $pId = isset($postData['pId']) ? (int)$postData['pId'] : 0;

        $result = [
            'success' => false,
            'data' => []
        ];
        $data = [];

        switch ($action) {
            case 'view':
                $data = $this->getCachedImagesData($status, $pageNum, $pageSize, $pId);
                $result['success'] = true;
                break;
            case 'view-failed':
                $data = $this->getFailedImagesData();
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

    /**
     * Method to get cached image data
     *
     * @param string $status
     * @param int $page
     * @param int $size
     * @param int $pId
     * @return array
     */
    public function getCachedImagesData($status, $page, $size, $pId)
    {
        /** @var \Sirv\Magento2\Model\Cache $cacheModel */
        $cacheModel = $this->cacheModelFactory->create();
        /** @var \Sirv\Magento2\Model\ResourceModel\Cache $resource */
        $resource = $cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        /** @var \Magento\Framework\DB\Select $select */
        $select = clone $connection->select();
        $table = $resource->getMainTable();

        switch ($status) {
            case 'failed':
                $_status = \Sirv\Magento2\Helper\Sync::IS_FAILED;
                break;
            case 'queued':
                $_status = [\Sirv\Magento2\Helper\Sync::IS_NEW, \Sirv\Magento2\Helper\Sync::IS_PROCESSING];
                break;
            case 'synced':
            default:
                $_status = \Sirv\Magento2\Helper\Sync::IS_SYNCED;
                break;
        }

        /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();

        $cache = $dataHelper->getAppCache();
        $cacheId = 'sirv_cache_' . $status . '_data_' . $size . '_' . $pId;
        $cacheData = $cache->load($cacheId);
        if (false === $cacheData) {
            $cacheData = [];
            $select->reset()
                ->from(
                    ['t' => $table],
                    ['id' => 't.id']
                )->where(
                    't.status ' . (is_array($_status) ? 'IN (?)' : ' = ?'),
                    $_status
                );
            $result = $connection->fetchCol($select, []);
            $cacheData['total'] = count($result);
            $cacheData['map'] = array_chunk($result, $size);
            $cache->save($dataHelper->getSerializer()->serialize($cacheData), $cacheId, [], null);
        } else {
            $cacheData = $dataHelper->getUnserializer()->unserialize($cacheData);
        }
        if (!is_array($cacheData)) {
            $cacheData = ['total' => 0, 'map' => []];
        }

        $items = [];
        if (isset($cacheData['map'][$page])) {
            $select->reset()
                ->from(
                    ['t' => $table]
                )->where(
                    't.id IN (?)',
                    $cacheData['map'][$page]
                );
            $rows = $connection->fetchAll($select, []);
            /** @var \Sirv\Magento2\Helper\Sync\Backend $syncHelper */
            $syncHelper = $this->syncHelperFactory->create();
            $baseUrl = $syncHelper->getMediaBaseUrl();
            $mediaDirAbsPath = $syncHelper->getMediaDirAbsPath();
            foreach ($rows as $row) {
                $filePath = $mediaDirAbsPath . $row['path'];
                $fileModificationTime = 0;
                $fileWidth = 0;
                $fileHeight = 0;
                $fileSize = 0;

                if (is_file($filePath)) {
                    $fileModificationTime = filemtime($filePath);
                    list($fileWidth, $fileHeight,) = getimagesize($filePath);
                    $fileSize = filesize($filePath);
                }
                $units = 'B';
                if ($fileSize > 1024) {
                    $fileSize = $fileSize / 1024;
                    $units = 'KB';
                    if ($fileSize > 1024) {
                        $fileSize = $fileSize / 1024;
                        $units = 'MB';
                    }
                }

                $url = $thumbUrl = $baseUrl . $row['path'];
                if ($status == 'synced') {
                    $url = $syncHelper->getUrl($row['path']);
                    $thumbUrl = $url . '?w=183&h=183&q=60';
                }
                $items[$row['id']] = [
                    'name' => basename($row['path']),
                    'url' => $url,
                    'thumburl' => $thumbUrl,
                    'mtime' => $fileModificationTime,
                    'width' => $fileWidth,
                    'height' => $fileHeight,
                    'size' => round($fileSize, 2) . ' ' . $units,
                ];
            }
        }

        $data = [
            'status' => $status,
            'page' => $page,
            'total' => $cacheData['total'],
            'next' => isset($cacheData['map'][$page + 1]),
            'items' => $items,
            'count' => count($items)
        ];

        return $data;
    }

    /**
     * Method to get failed image data
     *
     * @return array
     */
    public function getFailedImagesData()
    {
        /** @var \Sirv\Magento2\Helper\Sync\Backend $syncHelper */
        $syncHelper = $this->syncHelperFactory->create();

        $productMediaRelPath = $syncHelper->getProductMediaRelPath();
        $mediaDirAbsPath = $syncHelper->getMediaDirAbsPath();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $mediaBaseUrl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $mediaBaseUrl = rtrim($mediaBaseUrl, '\\/');

        $failedPathes = $syncHelper->getCachedPathes(\Sirv\Magento2\Helper\Sync::IS_FAILED);
        $failedCount = count($failedPathes);
        foreach ($failedPathes as $i => $path) {
            $failedPathes[$i] = $productMediaRelPath . '/' . ltrim($path, '\\/');
        }
        $failedPathes = array_flip($failedPathes);

        $messageModel = $syncHelper->getMessageModel();
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
            $messageEx = $message .  ' See <a href="https://my.sirv.com/#/events/" target="_blank" class="sirv-open-in-new-window">Sirv notification section</a> for more information.';
            $failedData[$messageEx] = $failedData[$message];
            unset($failedData[$message]);
        }

        $failedData = [
            'count' => $failedCount,
            'groups' => $failedData,
        ];

        return ['failed' => $failedData];
    }
}
