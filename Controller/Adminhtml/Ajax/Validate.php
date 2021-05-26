<?php

namespace Sirv\Magento2\Controller\Adminhtml\Ajax;

/**
 * Validate ajax controller
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Validate extends \Sirv\Magento2\Controller\Adminhtml\Settings
{
    /**
     * Cache model factory
     *
     * @var \Sirv\Magento2\Model\CacheFactory
     */
    protected $cacheModelFactory = null;

    /**
     * Base URL
     *
     * @var string
     */
    protected $baseUrl = '';

    /**
     * Image folder
     *
     * @var string
     */
    protected $imageFolder = '';

    /**
     * cURL resource
     *
     * @var resource
     */
    protected $curlHandle = null;

    /**
     * How many items to validate with one request
     *
     * @var integer
     */
    protected $pageSize = 200;

    /**
     * Http code
     *
     * @var integer
     */
    protected $httpCode = 0;

    /**
     * Cookie Manager
     *
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    protected $cookieManager = null;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    protected $cookieMetadataFactory = null;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    protected $sessionManager = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory
     * @param \Sirv\Magento2\Model\CacheFactory $cacheModelFactory
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param \Magento\Framework\Session\SessionManagerInterface $sessionManager
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sirv\Magento2\Helper\Data\BackendFactory $dataHelperFactory,
        \Sirv\Magento2\Model\CacheFactory $cacheModelFactory,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\Session\SessionManagerInterface $sessionManager
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelperFactory);
        $this->cacheModelFactory = $cacheModelFactory;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        /** @var \Sirv\Magento2\Helper\Data\Backend $dataHelper */
        $dataHelper = $this->getDataHelper();

        $bucket = $dataHelper->getConfig('bucket') ?: $dataHelper->getConfig('account');
        $this->baseUrl = 'https://' . $bucket . '.sirv.com';

        $imageFolder = $dataHelper->getConfig('image_folder');
        if (is_string($imageFolder)) {
            $imageFolder = trim($imageFolder);
            $imageFolder = trim($imageFolder, '\\/');
            if (!empty($imageFolder)) {
                $this->imageFolder = '/' . $imageFolder;
            }
        }

        $postData = $this->getRequest()->getPostValue();
        $action = isset($postData['dataAction']) ? $postData['dataAction'] : '';

        $result = ['error' => false];

        switch ($action) {
            case 'optimize_table':
                $data = $this->optimizeTable();
                $result = array_merge($result, $data);
                break;
            case 'get_cached_data':
                $data = $this->getCachedData();
                $result = array_merge($result, $data);
                break;
            case 'validate':
                $currentPage = isset($postData['currentPage']) ? (int)$postData['currentPage'] : 0;
                $maxId = isset($postData['maxId']) ? (int)$postData['maxId'] : 0;
                $data = $this->validateCache($currentPage, $maxId);
                $result = array_merge($result, $data);
                break;
            case 'clear_cache_items':
                $itemType = isset($postData['itemType']) ? $postData['itemType'] : '';
                $data = [];
                if ($itemType) {
                    $data = $this->clearCacheItems($itemType);
                }
                $result = array_merge($result, $data);
                break;
            default:
                break;
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $resultJson->setData($result);
        return $resultJson;
    }

    /**
     * Method to optimize table
     *
     * @return array
     */
    protected function optimizeTable()
    {
        $data = [];

        try {
            $cacheModel = $this->cacheModelFactory->create();
            $collection = $cacheModel->getCollection();

            /** @var \Sirv\Magento2\Model\ResourceModel\Cache $resource */
            $resource = $collection->getResource();
            /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
            $connection = $resource->getConnection();
            $table = $resource->getMainTable();

            /** @var \Magento\Framework\DB\Statement\Pdo\Mysql $statement */
            $statement = $connection->query("SHOW TABLES LIKE '{$table}_temp'");
            $result = $statement->fetchAll();
            if (!is_array($result)) {
                throw new Exception('Unexpected result when executing query.');
            }
            /*
            if (!empty($result)) {
                $connection->query("DROP TABLE {$table}_temp");
                $result = [];
            }
            */
            if (empty($result)) {
                $connection->query("CREATE TABLE {$table}_temp LIKE {$table}");
                $connection->query("INSERT {$table}_temp (`url`, `modification_time`) SELECT `ct`.`url`, `ct`.`modification_time` FROM {$table} AS ct");
                $collection->truncate();
                $connection->query("INSERT {$table} (`url`, `modification_time`) SELECT `ct`.`url`, `ct`.`modification_time` FROM {$table}_temp AS ct");
                $connection->query("DROP TABLE {$table}_temp");
            }
        } catch (\Exception $e) {
            $data['error'] = $e->getMessage();
        }

        return $data;
    }

    /**
     * Method to get cached data
     *
     * @return array
     */
    protected function getCachedData()
    {
        $data = [
            'maxId' => 0,
        ];

        $cacheModel = $this->cacheModelFactory->create();
        $collection = $cacheModel->getCollection()
            ->addFieldToSelect(new \Zend_Db_Expr('MAX(id) AS max_id'))
            ->getFirstItem();
        $data['maxId'] = (int)$collection->getData('max_id');

        $this->sessionManager->setSirvValidateData([]);

        return $data;
    }

    /**
     * Method to vallidate cache
     *
     * @param int $currentPage
     * @param int $maxId
     * @return array
     */
    protected function validateCache($currentPage, $maxId)
    {
        $data = [
            'currentPage' => $currentPage,
            'valid' => 0,
            'invalid' => 0,
            'failed' => 0,
            'aborted' => false,
            'completed' => false,
        ];

        $sessionData = $this->sessionManager->getSirvValidateData() ?: [];
        $items = isset($sessionData['items']) ? $sessionData['items'] : ['invalid' => [], 'failed' => []];
        $debug = ['invalid' => [], 'failed' => []];

        do {
            $firstId = $currentPage * $this->pageSize;
            if ($firstId > $maxId) {
                $data['completed'] = true;
                break;
            }
            $data['currentPage'] = $currentPage;
            $this->setSirvCookie('sirv_current_page', $currentPage);

            $lastId = $firstId + $this->pageSize;

            $cacheModel = $this->cacheModelFactory->create();
            $collection = $cacheModel->getCollection();
            $collectionSize = 0;
            $collection->addFieldToFilter('id', ['gteq' => $firstId]);
            $collection->addFieldToFilter('id', ['lt' => $lastId]);

            foreach ($collection as $item) {
                $collectionSize++;
                $path = $item->getPath();
                $id = $item->getId();
                $url = $this->baseUrl . $this->imageFolder . $path;
                $fileExists = $this->fileExists($url . '?info=' . time());
                if ($fileExists === true) {
                    $data['valid']++;
                } elseif ($fileExists === false) {
                    $data['invalid']++;
                    $items['invalid'][] = $id;
                    $debug['invalid'][$id] = ['url' => $url, 'code' => $this->httpCode];
                    //$item->delete();
                } else {
                    $data['failed']++;
                    $items['failed'][] = $id;
                    $debug['failed'][$id] = ['url' => $url, 'code' => $this->httpCode];
                }
            }

            $currentPage++;

        } while (!$collectionSize);

        $data['items'] = $items;
        $data['debug'] = $debug;

        if (isset($this->curlHandle)) {
            curl_close($this->curlHandle);
            $this->curlHandle = null;
        }

        $sessionData['items'] = $items;
        $this->sessionManager->setSirvValidateData($sessionData);

        return $data;
    }

    /**
     * Clear cache items
     *
     * @param string $itemType
     * @return array
     */
    protected function clearCacheItems($itemType)
    {
        $sessionData = $this->sessionManager->getSirvValidateData() ?: [];
        $items = isset($sessionData['items']) ? $sessionData['items'] : ['invalid' => [], 'failed' => []];
        $data = [];

        if (!empty($items[$itemType])) {
            try {
                $cacheModel = $this->cacheModelFactory->create();
                $resource = $cacheModel->getResource();
                $resource->deleteByIds($items[$itemType]);
                $items[$itemType] = [];
                $sessionData['items'] = $items;
                $this->sessionManager->setSirvValidateData($sessionData);
                $data['type'] = $itemType;
            } catch (\Exception $e) {
                $data['error'] = $e->getMessage();
            }
        }

        return $data;
    }

    /**
     * Check if file exists
     *
     * @param string $url
     * @return bool|null
     */
    protected function fileExists($url)
    {
        if (!isset($this->curlHandle)) {
            $this->curlHandle = curl_init();
        }

        curl_setopt($this->curlHandle, CURLOPT_URL, $url);
        curl_setopt($this->curlHandle, CURLOPT_NOBODY, true);
        curl_setopt($this->curlHandle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($this->curlHandle);

        if ($result === false || curl_errno($this->curlHandle)) {
            curl_close($this->curlHandle);
            return null;
        }

        $code = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
        $this->httpCode = $code;

        $result = null;
        switch ($code) {
            case 200:
                $result = true;
                break;
            case 404:
                $result = false;
                break;
        }

        return $result;
    }

    /**
     * Get cookie
     *
     * @param string $name
     * @return string
     */
    protected function getSirvCookie($name)
    {
        return $this->cookieManager->getCookie($name);
    }

    /**
     * Set cookie
     *
     * @param string $name
     * @param string $value
     * @param int $duration
     * @return void
     */
    protected function setSirvCookie($name, $value, $duration = 86400)
    {
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration($duration)
            ->setPath($this->sessionManager->getCookiePath())
            ->setDomain($this->sessionManager->getCookieDomain());

        $this->cookieManager->setPublicCookie(
            $name,
            $value,
            $metadata
        );
    }

    /**
     * Delete cookie
     *
     * @param string $name
     * @return void
     */
    protected function deleteSirvCookie($name)
    {
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration(0)
            ->setPath($this->sessionManager->getCookiePath())
            ->setDomain($this->sessionManager->getCookieDomain());

        $this->cookieManager->deleteCookie(
            $name,
            $metadata
        );
    }
}
