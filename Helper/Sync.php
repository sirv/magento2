<?php

namespace MagicToolbox\Sirv\Helper;

/**
 * Sync helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Sync extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Sync statuses
     */
    const IS_UNDEFINED = 0;
    const IS_NEW = 1;
    const IS_PROCESSING = 2;
    const IS_SYNCED = 3;
    const IS_FAILED = 4;

    /**
     * Path types
     */
    const UNKNOWN_PATH = 0;
    const ABSOLUTE_PATH = 1;
    const DOCUMENT_ROOT_PATH = 2;
    const MAGENTO_ROOT_PATH = 3;
    const MAGENTO_MEDIA_PATH = 4;
    const MAGENTO_PRODUCT_MEDIA_PATH = 5;
    const MAGENTO_CATEGORY_MEDIA_PATH = 6;

    /**
     * Data helper
     *
     * @var \MagicToolbox\Sirv\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Cache model
     *
     * @var \MagicToolbox\Sirv\Model\Cache
     */
    protected $cacheModel = null;

    /**
     * Sirv client
     *
     * @var \MagicToolbox\Sirv\Model\Api\Sirv
     */
    protected $sirvClient = null;

    /**
     * Whether the host is local
     *
     * @var bool
     */
    protected $isLocalHost = false;

    /**
     * Authentication flag
     *
     * @var bool
     */
    protected $isAuth = false;

    /**
     * Sirv base URL
     *
     * @var string
     */
    protected $baseUrl = '';

    /**
     * Sirv base direct URL
     *
     * @var string
     */
    protected $baseDirectUrl = '';

    /**
     * Folder name on Sirv
     *
     * @var string
     */
    protected $imageFolder = '';

    /**
     * Folder name on Sirv (encoded)
     *
     * @var string
     */
    protected $encodedImageFolder = '';

    /**
     * Absolute path to the document root directory
     *
     * @var string
     */
    protected $rootDirAbsPath = '';

    /**
     * Absolute path to the Magento base directory
     *
     * @var string
     */
    protected $baseDirAbsPath = '';

    /**
     * Absolute path to the media directory
     *
     * @var string
     */
    protected $mediaDirAbsPath = '';

    /**
     * Path to product images relative to media directory
     *
     * @var string
     */
    protected $productMediaRelPath = '';

    /**
     * Path to category images relative to media directory
     *
     * @var string
     */
    protected $categoryMediaRelPath = '';

    /**
     * Path to 360 images relative to media directory
     *
     * @var string
     */
    protected $magic360MediaRelPath = '/magic360';

    /**
     * Base url for media files
     *
     * @var string
     */
    protected $mediaBaseUrl = '';

    /**
     * Images to fetch
     *
     * @var array
     */
    protected $imagesToFetch = [];

    /**
     * Join data with MySQL
     *
     * @var bool
     */
    protected $joinWithMySQL = false;

    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Model\Product\Media\Config $catalogProductMediaConfig
     * @param \MagicToolbox\Sirv\Helper\Data $dataHelper
     * @param \MagicToolbox\Sirv\Model\CacheFactory $cacheModelFactory
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Model\Product\Media\Config $catalogProductMediaConfig,
        \MagicToolbox\Sirv\Helper\Data $dataHelper,
        \MagicToolbox\Sirv\Model\CacheFactory $cacheModelFactory
    ) {
        parent::__construct($context);

        $this->dataHelper = $dataHelper;
        $this->cacheModel = $cacheModelFactory->create();

        $this->logger = $context->getLogger();
        $this->sirvClient = $dataHelper->getSirvClient();

        $bucket = $dataHelper->getConfig('bucket') ?: $dataHelper->getConfig('account');

        $this->baseUrl = $this->baseDirectUrl = 'https://' . $bucket . '.sirv.com';

        $cdnUrl = $dataHelper->getConfig('cdn_url');
        $cdnUrl = is_string($cdnUrl) ? trim($cdnUrl) : '';
        if ($dataHelper->getConfig('network') == 'cdn') {
            if (!empty($cdnUrl)) {
                $this->baseUrl = 'https://' . $cdnUrl;
            } else {
                $customDomain = $dataHelper->getConfig('custom_domain');
                if (is_string($customDomain)) {
                    $customDomain = trim($customDomain);
                    //NOTE: cut protocol
                    $customDomain = preg_replace('#^(?:[a-zA-Z0-9]+:)?//#', '', $customDomain);
                    //NOTE: cut path with query
                    $customDomain = preg_replace('#^([^/]+)/.*$#', '$1', $customDomain);
                    //NOTE: cut query without path
                    $customDomain = preg_replace('#^([^\?]+)\?.*$#', '$1', $customDomain);
                    if (!empty($customDomain)) {
                        $this->baseUrl = 'https://' . $customDomain;
                    }
                }
            }
        }

        $imageFolder = $dataHelper->getConfig('image_folder');
        if (is_string($imageFolder)) {
            $imageFolder = trim($imageFolder);
            $imageFolder = trim($imageFolder, '\\/');
            if (!empty($imageFolder)) {
                $this->imageFolder = '/' . $imageFolder;
                $this->encodedImageFolder = '/' . rawurlencode($imageFolder);
            }
        }

        $request = $context->getRequest();

        $this->rootDirAbsPath = $request->getServer('DOCUMENT_ROOT');
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $this->rootDirAbsPath = realpath($this->rootDirAbsPath);
        $this->rootDirAbsPath = rtrim($this->rootDirAbsPath, '\\/');

        /** @var \Magento\Framework\Filesystem\Directory\ReadInterface $baseDirectory */
        $baseDirectory = $filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::ROOT);
        $this->baseDirAbsPath = $baseDirectory->getAbsolutePath();
        $this->baseDirAbsPath = rtrim($this->baseDirAbsPath, '\\/');

        /** @var \Magento\Framework\Filesystem\Directory\ReadInterface $mediaDirectory */
        $mediaDirectory = $filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        //NOTE: absolute path to pub/media folder
        $this->mediaDirAbsPath = $mediaDirectory->getAbsolutePath();
        $this->mediaDirAbsPath = rtrim($this->mediaDirAbsPath, '\\/');

        $this->productMediaRelPath = $catalogProductMediaConfig->getBaseMediaPath();
        $this->productMediaRelPath = trim($this->productMediaRelPath, '\\/');
        $this->productMediaRelPath = '/' . $this->productMediaRelPath;

        if (class_exists('\Magento\Catalog\Model\Category\FileInfo', false)) {
            $this->categoryMediaRelPath = \Magento\Catalog\Model\Category\FileInfo::ENTITY_MEDIA_PATH;
            $this->categoryMediaRelPath = trim($this->categoryMediaRelPath, '\\/');
            $this->categoryMediaRelPath = '/' . $this->categoryMediaRelPath;
        } else {
            $this->categoryMediaRelPath = '/catalog/category';
        }

        //NOTE: URL of pub/media folder
        $this->mediaBaseUrl = $catalogProductMediaConfig->getBaseMediaUrl();
        $this->mediaBaseUrl = rtrim($this->mediaBaseUrl, '\\/');
        $this->mediaBaseUrl = preg_replace('#' . preg_quote($this->productMediaRelPath, '#') . '$#', '', $this->mediaBaseUrl);
        if (!empty($cdnUrl) && strpos($this->mediaBaseUrl, $cdnUrl) !== false) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
            $this->mediaBaseUrl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            $this->mediaBaseUrl .= $filesystem->getUri(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        }

        $httpHost = $request->getServer('HTTP_HOST') ?: '';
        $this->isLocalHost = preg_match('#localhost|127\.\d+\.\d+\.\d+#i', $httpHost);

        if ($dataHelper->isSirvEnabled() || $dataHelper->isBackend()) {
            $this->isAuth = (
                $dataHelper->getConfig('account') &&
                $dataHelper->getConfig('client_id') &&
                $dataHelper->getConfig('client_secret')
            );
        }

        $this->joinWithMySQL = $dataHelper->getConfig('join_with_mysql') === 'true' ? true : false;
    }

    /**
     * Is authenticated
     *
     * @return bool
     */
    public function isAuth()
    {
        return $this->isAuth;
    }

    /**
     * Check the file is synced
     *
     * @param string $path
     * @return bool
     */
    public function isSynced($path)
    {
        $status = self::IS_UNDEFINED;
        try {
            /** @var \MagicToolbox\Sirv\Model\Cache $cacheModel */
            $cacheModel = $this->cacheModel->clearInstance()->load($path, 'path');
            $status = $cacheModel->getStatus();
            if ($status == self::IS_PROCESSING && $this->fileExists($path)) {
                $cacheModel->setStatus(self::IS_SYNCED);
                $cacheModel->save();
                $status = self::IS_SYNCED;
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return $status == self::IS_SYNCED;
    }

    /**
     * Check the file is in cache table
     *
     * @param string $path
     * @param int $modificationTime
     * @return bool
     */
    public function isCached($path, $modificationTime = null)
    {
        $isCached = false;
        try {
            /** @var \MagicToolbox\Sirv\Model\Cache $cacheModel */
            $cacheModel = $this->cacheModel->clearInstance()->load($path, 'path');
            $timestamp = $cacheModel->getModificationTime();
            if ($timestamp !== null) {
                $isCached = ($modificationTime === null) || ($modificationTime <= (int)$timestamp);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return $isCached;
    }

    /**
     * Update or insert cache table data
     *
     * @param string $path
     * @param int $pathType
     * @param int $status
     * @param int|null $modificationTime
     * @return bool
     */
    public function updateCacheData($path, $pathType, $status, $modificationTime = null)
    {
        try {
            /** @var \MagicToolbox\Sirv\Model\Cache $cacheModel */
            $cacheModel = $this->cacheModel->clearInstance()->load($path, 'path');
            $cacheModel->setPath($path);
            $cacheModel->setPathType($pathType);
            $cacheModel->setStatus($status);
            if ($modificationTime !== null) {
                $cacheModel->setModificationTime($modificationTime);
            }
            $cacheModel->save();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }

        return true;
    }

    /**
     * Remove cache table data
     *
     * @param string $path
     * @return bool
     */
    public function removeCacheData($path)
    {
        try {
            /** @var \MagicToolbox\Sirv\Model\Cache $cacheModel */
            $cacheModel = $this->cacheModel->clearInstance()->load($path, 'path');
            $id = $cacheModel->getId();
            if ($id !== null) {
                $cacheModel->delete();
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }

        return true;
    }

    /**
     * Save file
     *
     * @param string $absPath
     * @param int $pathType
     * @return bool
     */
    public function save($absPath, $pathType = self::UNKNOWN_PATH)
    {
        if (!$this->isAuth) {
            return false;
        }

        if (!is_file($absPath)) {
            return false;
        }

        $relPath = $this->getRelativePath($absPath, $pathType);

        if ($this->isLocalHost) {
            try {
                $result = $this->sirvClient->uploadFile($this->imageFolder . $relPath, $absPath);
            } catch (\Exception $e) {
                $result = false;
                $this->updateCacheData($relPath, $pathType, self::IS_FAILED, 0);
                $this->logger->critical($e);
            }

            if ($result) {
                $modificationTime = filemtime($absPath);
                $this->updateCacheData($relPath, $pathType, self::IS_SYNCED, $modificationTime);
            }
        } else {
            $this->imagesToFetch[] = $relPath;
            $modificationTime = filemtime($absPath);
            $this->updateCacheData($relPath, $pathType, self::IS_NEW, $modificationTime);
            $result = false;
        }

        return $result;
    }

    /**
     * Remove file from Sirv and database
     *
     * @param string $path
     * @return bool
     */
    public function remove($path)
    {
        if (!$this->isAuth) {
            return false;
        }

        try {
            $result = $this->sirvClient->deleteFile($this->imageFolder . $path);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result = false;
        }

        if ($result) {
            $this->removeCacheData($path);
        }

        return $result;
    }

    /**
     * Get file URL
     *
     * @param string $path
     * @return string
     */
    public function getUrl($path)
    {
        return $this->baseUrl . $this->imageFolder . $path;
    }

    /**
     * Get file direct URL
     *
     * @param string $path
     * @return string
     */
    public function getDirectUrl($path)
    {
        return $this->baseDirectUrl . $this->imageFolder . $path;
    }

    /**
     * Get file relative URL
     *
     * @param string $path
     * @return string
     */
    public function getRelUrl($path)
    {
        return $this->imageFolder . $path;
    }

    /**
     * Get file relative path
     *
     * @param string $path
     * @param int $pathType
     * @return string
     */
    public function getRelativePath($path, $pathType = self::UNKNOWN_PATH)
    {
        $regExp = null;
        switch ($pathType) {
            case self::DOCUMENT_ROOT_PATH:
                $regExp = '#^' . preg_quote($this->rootDirAbsPath, '#') . '#';
                break;
            case self::MAGENTO_ROOT_PATH:
                $regExp = '#^' . preg_quote(BP, '#') . '#';
                break;
            case self::MAGENTO_MEDIA_PATH:
                $regExp = '#^' . preg_quote($this->mediaDirAbsPath, '#') . '#';
                break;
            case self::MAGENTO_PRODUCT_MEDIA_PATH:
                $regExp = '#^' . preg_quote($this->mediaDirAbsPath . $this->productMediaRelPath, '#') . '#';
                break;
            case self::MAGENTO_CATEGORY_MEDIA_PATH:
                $regExp = '#^' . preg_quote($this->mediaDirAbsPath . $this->categoryMediaRelPath, '#') . '#';
                break;
            default:
                //$this->logger->info(sprintf('Media type not recognized: "%s"', $path));
        }

        if ($regExp) {
            $path = preg_replace($regExp, '', $path);
        }

        return $path;
    }

    /**
     * Check if file exists on Sirv
     *
     * @param string $path
     * @return bool
     */
    public function fileExists($path)
    {
        static $fileExists = [];

        if (!$this->isAuth) {
            return false;
        }

        if (!isset($fileExists[$path])) {
            $fileExists[$path] = false;
            try {
                $result = $this->sirvClient->getFileStats($this->imageFolder . $path);
                if ($result && isset($result->size) && (int)$result->size) {
                    $fileExists[$path] = true;
                }
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $fileExists[$path];
    }

    /**
     * Check for S3 upload usage
     *
     * @return bool
     */
    public function isS3UploadUsed()
    {
        return $this->isLocalHost;
    }

    /**
     * Fetch files
     *
     * @return void
     */
    public function doFetch()
    {
        if (!$this->isAuth || empty($this->imagesToFetch)) {
            return;
        }

        $wait = $this->dataHelper->isBackend();

        $this->imagesToFetch = array_unique($this->imagesToFetch);

        $imagesData = [];
        foreach ($this->imagesToFetch as $image) {
            $imagesData[] = [
                //NOTE: source link
                'url' => $this->mediaBaseUrl . str_replace('%2F', '/', rawurlencode($image)),
                //NOTE: destination path
                'filename' => $this->imageFolder . $image,
                //NOTE: wait flag
                'wait' => $wait
            ];
        }

        $chunkedData = array_chunk($imagesData, 20);
        foreach ($chunkedData as $imagesData) {
            if (($result = $this->sirvClient->fetchImages($imagesData)) && is_array($result)) {
                foreach ($result as $data) {
                    $relPath = preg_replace('#^'.preg_quote($this->imageFolder, '#').'#', '', $data->filename);
                    $pathType = self::UNKNOWN_PATH;
                    if (strpos($relPath, $this->productMediaRelPath . '/') === 0 ||
                        strpos($relPath, $this->categoryMediaRelPath . '/') === 0 ||
                        strpos($relPath, $this->magic360MediaRelPath . '/') === 0 ||
                        strpos($relPath, '/catalog/') === 0 ||
                        strpos($relPath, '/wysiwyg/') === 0
                    ) {
                        $pathType = self::MAGENTO_MEDIA_PATH;
                    }
                    $status = $wait ? ($data->success ? self::IS_SYNCED : self::IS_FAILED) : self::IS_PROCESSING;
                    $modificationTime = ($status == self::IS_FAILED ? 0 : null);
                    $this->updateCacheData($relPath, $pathType, $status, $modificationTime);
                }
            }
        }

        $this->imagesToFetch = [];
    }

    /**
     * Get sync data
     *
     * @param bool $force
     * @return array
     */
    public function getSyncData($force = false)
    {
        static $data = null;

        if (!($force || $data === null)) {
            return $data;
        }

        $cacheId = 'sirv_sync_data';
        $appCache = $this->dataHelper->getAppCache();
        $data = $force ? false : $appCache->load($cacheId);
        if (false !== $data) {
            $data = $this->dataHelper->getUnserializer()->unserialize($data);
            if (is_array($data)) {
                return $data;
            }
        }

        /** @var \MagicToolbox\Sirv\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_with_slash' => $this->productMediaRelPath . '/',
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        if ($this->joinWithMySQL) {
            $getData = function () use (
                &$connection,
                &$mtSelect,
                &$mediaTable,
                &$ctSelect,
                &$cacheTable,
                &$bind
            ) {
                $mtSelect->reset()
                    ->from(
                        ['mt' => $mediaTable],
                        ['total' => 'COUNT(DISTINCT BINARY(`mt`.`value`))']
                    )
                    ->where('`mt`.`value` IS NOT NULL')
                    ->where('`mt`.`value` != ?', '');

                /** @var int $total */
                $total = (int)$connection->fetchOne($mtSelect);

                $mtSelect->reset()
                    ->distinct()
                    ->from(
                        ['mt' => $mediaTable],
                        ['unique_value' => 'BINARY(`mt`.`value`)']
                    )
                    ->where('`mt`.`value` IS NOT NULL')
                    ->where('`mt`.`value` != ?', '');

                $ctSelect->reset()
                    ->from(
                        ['ct' => $cacheTable],
                        [
                            'ct.status',
                            'sum' => 'COUNT(`ct`.`status`)',
                        ]
                    )
                    ->joinInner(
                        ['tt' => new \Zend_Db_Expr("({$mtSelect})")],
                        '`tt`.`unique_value` = `ct`.`path` OR ' .
                        'CONCAT(:pm_rel_path, `tt`.`unique_value`) = `ct`.`path` OR ' .
                        'CONCAT(:pm_rel_path_with_slash, `tt`.`unique_value`) = `ct`.`path`',
                        []
                    )
                    ->where('(`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp))')
                    ->group('ct.status');

                /** @var array $pairs */
                $pairs = $connection->fetchPairs($ctSelect, $bind);

                $data = [];
                $data['total'] = $total;
                $data['synced'] = isset($pairs[\MagicToolbox\Sirv\Helper\Sync::IS_SYNCED]) ? (int)$pairs[\MagicToolbox\Sirv\Helper\Sync::IS_SYNCED] : 0;
                $data['new'] = isset($pairs[\MagicToolbox\Sirv\Helper\Sync::IS_NEW]) ? (int)$pairs[\MagicToolbox\Sirv\Helper\Sync::IS_NEW] : 0;
                $data['processing'] = isset($pairs[\MagicToolbox\Sirv\Helper\Sync::IS_PROCESSING]) ? (int)$pairs[\MagicToolbox\Sirv\Helper\Sync::IS_PROCESSING] : 0;
                $data['queued'] = $data['new'] + $data['processing'];
                $data['failed'] = isset($pairs[\MagicToolbox\Sirv\Helper\Sync::IS_FAILED]) ? (int)$pairs[\MagicToolbox\Sirv\Helper\Sync::IS_FAILED] : 0;

                return $data;
            };
        } else {
            unset($bind[':pm_rel_path_with_slash']);

            $getData = function () use (
                &$connection,
                &$mtSelect,
                &$mediaTable,
                &$ctSelect,
                &$cacheTable,
                &$bind
            ) {
                $mtSelect->reset()
                    ->distinct()
                    ->from(
                        ['mt' => $mediaTable],
                        ['unique_value' => 'BINARY(`mt`.`value`)']
                    )
                    ->where('`mt`.`value` IS NOT NULL')
                    ->where('`mt`.`value` != ?', '');

                $mediaPathes = $connection->fetchCol($mtSelect, []);
                foreach ($mediaPathes as &$path) {
                    $path = '/' . ltrim($path, '\\/');
                }
                unset($path);

                $ctSelect->reset()
                    ->distinct()
                    ->from(
                        ['ct' => $cacheTable],
                        [
                            'short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")',
                            'status'
                        ]
                    )
                    ->where('(`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp))');

                /** @var array $cachedData */
                $cachedData = $connection->fetchAll($ctSelect, $bind);

                $pairs = [
                    \MagicToolbox\Sirv\Helper\Sync::IS_UNDEFINED => 0,
                    \MagicToolbox\Sirv\Helper\Sync::IS_NEW => 0,
                    \MagicToolbox\Sirv\Helper\Sync::IS_PROCESSING => 0,
                    \MagicToolbox\Sirv\Helper\Sync::IS_SYNCED => 0,
                    \MagicToolbox\Sirv\Helper\Sync::IS_FAILED => 0,
                ];
                $mediaPathes = array_flip($mediaPathes);
                foreach ($cachedData as $row) {
                    if (isset($mediaPathes[$row['short_path']])) {
                        $pairs[$row['status']]++;
                    }
                }

                $data = [];
                $data['total'] = count($mediaPathes);
                $data['synced'] = $pairs[\MagicToolbox\Sirv\Helper\Sync::IS_SYNCED];
                $data['new'] = $pairs[\MagicToolbox\Sirv\Helper\Sync::IS_NEW];
                $data['processing'] = $pairs[\MagicToolbox\Sirv\Helper\Sync::IS_PROCESSING];
                $data['queued'] = $data['new'] + $data['processing'];
                $data['failed'] = $pairs[\MagicToolbox\Sirv\Helper\Sync::IS_FAILED];

                return $data;
            };
        }

        $data = $getData();
        $cached = $data['synced'] + $data['queued'] + $data['failed'];

        if ($data['total'] < $cached) {
            $this->fixSyncData();
            $data = $getData();
        }

        $data['completed'] = true;

        $appCache->save($this->dataHelper->getSerializer()->serialize($data), $cacheId, [], 120);

        return $data;
    }

    /**
     * Method to get media pathes that are not cached
     *
     * @param int $limit
     * @return array
     */
    protected function getNotCachedPathes($limit = 0)
    {
        /** @var \MagicToolbox\Sirv\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        if ($this->joinWithMySQL) {
            $ctSelect->reset()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)')
                ->where('`ct`.`status` != ?', self::IS_UNDEFINED);

            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->joinLeft(
                    ['tt' => new \Zend_Db_Expr("({$ctSelect})")],
                    '`tt`.`short_path` = `mt`.`value` OR TRIM(LEADING "/" FROM `tt`.`short_path`) = `mt`.`value`',
                    []
                )
                ->where('`tt`.`short_path` IS NULL')
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            if ($limit) {
                $mtSelect->limit($limit);
            }

            /** @var array $result */
            $result = $connection->fetchCol($mtSelect, $bind);
        } else {
            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            $mediaPathes = $connection->fetchCol($mtSelect, []);
            foreach ($mediaPathes as &$path) {
                $path = '/' . ltrim($path, '\\/');
            }
            unset($path);

            $ctSelect->reset()
                ->distinct()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('(`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp))')
                ->where('`ct`.`status` != ?', self::IS_UNDEFINED);

            $cachedPathes = $connection->fetchCol($ctSelect, $bind);
            $cachedPathes = array_flip($cachedPathes);

            $result = [];
            $i = 0;
            foreach ($mediaPathes as $path) {
                if (isset($cachedPathes[$path])) {
                    continue;
                }
                $result[] = $path;
                $i++;
                if ($limit && $i == $limit) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Method to get media pathes that are not cached (alternative method using a temporary table)
     *
     * @param int $limit
     * @return array
     */
    public function getNotCachedPathesAlt($limit = 0)
    {
        /** @var \MagicToolbox\Sirv\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $cacheTableTemp = "{$cacheTable}_temp";

        $table = $connection->createTableByDdl($cacheTable, $cacheTableTemp);
        $connection->createTable($table);

        $connection->truncateTable($cacheTableTemp);

        $ctSelect->reset()
            ->from(
                ['ct' => $cacheTable],
                ['path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
            )
            ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)')
            ->where('`ct`.`status` != ?', self::IS_UNDEFINED);

        $query = $ctSelect->insertIgnoreFromSelect($cacheTableTemp, ['path']);

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        $connection->query($query, $bind);

        $mtSelect->reset()
            ->distinct()
            ->from(
                ['mt' => $mediaTable],
                ['unique_value' => 'BINARY(`mt`.`value`)']
            )
            ->joinLeft(
                ['tt' => "{$cacheTableTemp}"],
                '`tt`.`path` = `mt`.`value` OR TRIM(LEADING "/" FROM `tt`.`path`) = `mt`.`value`',
                []
            )
            ->where('`tt`.`path` IS NULL')
            ->where('`mt`.`value` IS NOT NULL')
            ->where('`mt`.`value` != ?', '');

        if ($limit) {
            $mtSelect->limit($limit);
        }

        /** @var array $result */
        $result = $connection->fetchCol($mtSelect, []);

        return $result;
    }

    /**
     * Method to get media pathes that are queued
     *
     * @param int $limit
     * @return array
     */
    protected function getQueuedPathes($limit = 0)
    {
        /** @var \MagicToolbox\Sirv\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        if ($this->joinWithMySQL) {
            $ctSelect->reset()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('`ct`.`status` IN (?)', [self::IS_NEW, self::IS_PROCESSING])
                ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)');

            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->joinLeft(
                    ['tt' => new \Zend_Db_Expr("({$ctSelect})")],
                    '`tt`.`short_path` = `mt`.`value` OR TRIM(LEADING "/" FROM `tt`.`short_path`) = `mt`.`value`',
                    []
                )
                ->where('`tt`.`short_path` IS NOT NULL')
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            if ($limit) {
                $mtSelect->limit($limit);
            }

            /** @var array $result */
            $result = $connection->fetchCol($mtSelect, $bind);
        } else {
            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            $mediaPathes = $connection->fetchCol($mtSelect, []);
            foreach ($mediaPathes as &$path) {
                $path = '/' . ltrim($path, '\\/');
            }
            unset($path);

            $ctSelect->reset()
                ->distinct()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('`ct`.`status` IN (?)', [self::IS_NEW, self::IS_PROCESSING])
                ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)');

            $queuedPathes = $connection->fetchCol($ctSelect, $bind);

            $result = [];
            $i = 0;
            $mediaPathes = array_flip($mediaPathes);
            foreach ($queuedPathes as $path) {
                if (isset($mediaPathes[$path])) {
                    $result[] = $path;
                    $i++;
                    if ($limit && $i == $limit) {
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Method to get media pathes that are failed
     *
     * @param int $limit
     * @return array
     */
    public function getFailedPathes($limit = 0)
    {
        /** @var \MagicToolbox\Sirv\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $mediaTable = $resource->getTable(\Magento\Catalog\Model\ResourceModel\Product\Gallery::GALLERY_TABLE);
        /** @var \Magento\Framework\DB\Select $mtSelect */
        $mtSelect = clone $connection->select();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $bind = [
            ':mmp_type' => self::MAGENTO_MEDIA_PATH,
            ':mpmp_type' => self::MAGENTO_PRODUCT_MEDIA_PATH,
            ':pm_rel_path' => $this->productMediaRelPath,
            ':pm_rel_path_regexp' => '^' . $this->productMediaRelPath,
        ];

        if ($this->joinWithMySQL) {
            $ctSelect->reset()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('`ct`.`status` = ?', self::IS_FAILED)
                ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)');

            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->joinLeft(
                    ['tt' => new \Zend_Db_Expr("({$ctSelect})")],
                    '`tt`.`short_path` = `mt`.`value` OR TRIM(LEADING "/" FROM `tt`.`short_path`) = `mt`.`value`',
                    []
                )
                ->where('`tt`.`short_path` IS NOT NULL')
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            if ($limit) {
                $mtSelect->limit($limit);
            }

            /** @var array $result */
            $result = $connection->fetchCol($mtSelect, $bind);
        } else {
            $mtSelect->reset()
                ->distinct()
                ->from(
                    ['mt' => $mediaTable],
                    ['unique_value' => 'BINARY(`mt`.`value`)']
                )
                ->where('`mt`.`value` IS NOT NULL')
                ->where('`mt`.`value` != ?', '');

            $mediaPathes = $connection->fetchCol($mtSelect, []);
            foreach ($mediaPathes as &$path) {
                $path = '/' . ltrim($path, '\\/');
            }
            unset($path);

            $ctSelect->reset()
                ->distinct()
                ->from(
                    ['ct' => $cacheTable],
                    ['short_path' => 'REPLACE(`ct`.`path`, :pm_rel_path, "")']
                )
                ->where('`ct`.`status` = ?', self::IS_FAILED)
                ->where('`ct`.`path_type` = :mpmp_type OR (`ct`.`path_type` = :mmp_type AND `ct`.`path` REGEXP :pm_rel_path_regexp)');

            $failedPathes = $connection->fetchCol($ctSelect, $bind);

            $result = [];
            $i = 0;
            $mediaPathes = array_flip($mediaPathes);
            foreach ($failedPathes as $path) {
                if (isset($mediaPathes[$path])) {
                    $result[] = $path;
                    $i++;
                    if ($limit && $i == $limit) {
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Fix sync data
     *
     * @return array
     */
    protected function fixSyncData()
    {
        $data = [
            self::IS_UNDEFINED => 0,
            self::IS_NEW => 0,
            self::IS_PROCESSING => 0,
            self::IS_SYNCED => 0,
            self::IS_FAILED => 0,
        ];

        //NOTE: try to clear duplicates
        $duplicates = $this->getDuplicateData();

        $toDelete = [];
        foreach ($duplicates as $pair) {
            if ($pair[1]['status'] == self::IS_SYNCED && $pair[2]['status'] != self::IS_SYNCED) {
                $toDelete[] = $pair[2]['id'];
                $data[$pair[2]['status']]++;
            } elseif ($pair[1]['status'] != self::IS_SYNCED && $pair[2]['status'] == self::IS_SYNCED) {
                $toDelete[] = $pair[1]['id'];
                $data[$pair[1]['status']]++;
            } else {
                if ($pair[1]['path_type'] == self::MAGENTO_MEDIA_PATH) {
                    $toDelete[] = $pair[2]['id'];
                    $data[$pair[2]['status']]++;
                } else {
                    $toDelete[] = $pair[1]['id'];
                    $data[$pair[1]['status']]++;
                }
            }
        }

        if (!empty($toDelete)) {
            /** @var \MagicToolbox\Sirv\Model\ResourceModel\Cache $resource */
            $resource = $this->cacheModel->getResource();
            $resource->deleteByIds($toDelete);
        }

        return $data;
    }

    /**
     * Method to get media data with dublicated pathes
     *
     * @return array
     */
    protected function getDuplicateData()
    {
        /** @var \MagicToolbox\Sirv\Model\ResourceModel\Cache $resource */
        $resource = $this->cacheModel->getResource();
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $resource->getConnection();
        $cacheTable = $resource->getMainTable();
        /** @var \Magento\Framework\DB\Select $ctSelect */
        $ctSelect = clone $connection->select();

        $ctSelect->reset()
            ->from(
                ['ct1' => $cacheTable],
                [
                    'id_1' => 'ct1.id',
                    'id_2' => 'ct2.id',
                    'path_1' => 'ct1.path',
                    'path_2' => 'ct2.path',
                    'path_type_1' => 'ct1.path_type',
                    'path_type_2' => 'ct2.path_type',
                    'status_1' => 'ct1.status',
                    'status_2' => 'ct2.status',
                    'modification_time_1' => 'ct1.modification_time',
                    'modification_time_2' => 'ct2.modification_time',
                ]
            )
            ->joinInner(
                ['ct2' => $cacheTable],
                '((`ct1`.`id` != `ct2`.`id`) AND (CONCAT(:pm_rel_path, `ct1`.`path`) = `ct2`.`path` OR CONCAT(:cm_rel_path, `ct1`.`path`) = `ct2`.`path`))',
                []
            )
            ->order('ct1.id ASC');
        /*
        SELECT
            `ct1`.`id` AS `id_1`,
            `ct2`.`id` AS `id_2`,
            `ct1`.`path` AS `path_1`,
            `ct2`.`path` AS `path_2`,
            `ct1`.`path_type` AS `path_type_1`,
            `ct2`.`path_type` AS `path_type_2`,
            `ct1`.`status` AS `status_1`,
            `ct2`.`status` AS `status_2`,
            `ct1`.`modification_time` AS `modification_time_1`,
            `ct2`.`modification_time` AS `modification_time_2`
        FROM
            `m2_sirv_cache` AS `ct1`
        INNER JOIN `m2_sirv_cache` AS `ct2` ON
            `ct1`.`id` != `ct2`.`id`
            AND
            (CONCAT('/catalog/product', `ct1`.`path`) = `ct2`.`path` OR CONCAT('/catalog/category/', `ct1`.`path`) = `ct2`.`path`)
        ORDER BY `ct1`.`id` ASC
        */

        $bind = [
            ':pm_rel_path' => $this->productMediaRelPath,
            ':cm_rel_path' => $this->categoryMediaRelPath . '/',
        ];

        /** @var array $result */
        $result = $connection->fetchAll($ctSelect, $bind);

        $duplicates = [];
        foreach ($result as $data) {
            $pair = [1 => [], 2 => []];
            foreach ($data as $key => $value) {
                $i = substr($key, -1);
                $name = substr($key, 0, -2);
                $pair[$i][$name] = $value;
            }
            $duplicates[] = $pair;
        }

        return $duplicates;
    }

    /**
     * Method to synchronize media gallery
     *
     * @param int $stage
     * @return array
     */
    public function syncMediaGallery($stage)
    {
        if (!$this->isAuth) {
            return ['error' => 'Not authenticated!'];
        }

        $startTime = time();
        $maxExecutionTime = (int)ini_get('max_execution_time');
        if (!$maxExecutionTime) {
            $maxExecutionTime = 60;
        }

        //NOTE: 10 seconds to complete
        $breakTime = $maxExecutionTime + $startTime - 10;
        $limit = 100;

        $data = [
            'synced' => 0,
            'queued' => 0,
            'failed' => 0,
            'aborted' => false,
            'completed' => false,
            'ratelimit' => null,
        ];

        if ($stage == 1) {
            $images = $this->getNotCachedPathes($limit);
        } else {
            $images = $this->getQueuedPathes($limit);
        }

        $imagesCount = count($images);
        if ($imagesCount == 0) {
            $data['completed'] = true;
            return $data;
        }

        if ($this->isLocalHost) {
            $result = $this->syncWithUploading($images, $breakTime);
        } else {
            $result = $this->syncWithFetching($images, $breakTime);
        }

        $data = array_merge($data, $result);

        if (!$data['aborted'] && ($imagesCount < $limit)) {
            $data['completed'] = true;
        }

        return $data;
    }

    /**
     * Synchronize images using uploading method
     *
     * @param array $images
     * @param int $breakTime
     * @return array
     */
    protected function syncWithUploading($images, $breakTime)
    {
        $synced = 0;
        $failed = 0;
        $aborted = false;
        $rateLimit = null;

        foreach ($images as $imagePath) {
            $imagePath = '/' . ltrim($imagePath, '\\/');
            $relPath = $this->productMediaRelPath . $imagePath;
            $absPath = $this->mediaDirAbsPath . $relPath;

            if (is_file($absPath)) {
                try {
                    $result = $this->sirvClient->uploadFile($this->imageFolder . $relPath, $absPath);
                    if (!$result) {
                        $errorMsg = $this->sirvClient->getErrorMsg();
                        if (empty($errorMsg)) {
                            $this->logger->info(sprintf('Uploading "%s" file failed.', $absPath));
                        } else {
                            $this->logger->info(sprintf('Uploading "%s" file failed. %s.', $absPath, $errorMsg));
                        }
                        $expireTime = $this->sirvClient->getRateLimitExpireTime('POST', 'v2/files/upload');
                        if ($expireTime) {
                            $rateLimit = [
                                'expireTime' => $expireTime,
                                'currentTime' => time(),
                                'message' => $errorMsg,
                            ];
                            $aborted = true;
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->critical('Exception on API upload:', ['exception' => $e]);
                    $result = false;
                }
            } else {
                $this->logger->info(sprintf('The "%s" file does not exist or is not readable.', $absPath));
                $result = false;
            }

            if ($this->isCached($imagePath)) {
                $this->removeCacheData($imagePath);
            }

            if ($result) {
                $modificationTime = filemtime($absPath);
                $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_SYNCED, $modificationTime);
                $synced++;
            } else {
                $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_FAILED, 0);
                $failed++;
            }

            if ($breakTime - time() <= 0) {
                $aborted = true;
                break;
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'aborted' => $aborted,
            'ratelimit' => $rateLimit,
        ];
    }

    /**
     * Synchronize images using fetching method
     *
     * @param array $images
     * @param int $breakTime
     * @return array
     */
    protected function syncWithFetching($images, $breakTime)
    {
        $synced = 0;
        $failed = 0;
        $aborted = false;
        $rateLimit = null;
        $error = false;

        //NOTE: less than or equal to 20 items
        $chunks = array_chunk($images, 20);
        foreach ($chunks as $chunk) {
            $fetchData = [];
            foreach ($chunk as $imagePath) {
                $imagePath = '/' . ltrim($imagePath, '\\/');
                $relPath = $this->productMediaRelPath . $imagePath;
                $absPath = $this->mediaDirAbsPath . $relPath;
                if (is_file($absPath)) {
                    $fetchData[] = [
                        //NOTE: source link
                        'url' => $this->mediaBaseUrl . str_replace('%2F', '/', rawurlencode($relPath)),
                        //NOTE: destination path
                        'filename' => $this->imageFolder . $relPath,
                        //NOTE: wait flag
                        'wait' => true
                    ];
                } else {
                    $this->logger->info(sprintf('The "%s" file does not exist or is not readable.', $absPath));
                    $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_FAILED, 0);
                    $failed++;
                }
            }

            if (empty($fetchData)) {
                continue;
            }

            try {
                $result = $this->sirvClient->fetchImages($fetchData);
                if (!$result) {
                    if ($expireTime = $this->sirvClient->getRateLimitExpireTime('POST', 'v2/files/fetch')) {
                        $rateLimit = [
                            'expireTime' => $expireTime,
                            'currentTime' => time(),
                            'message' => $this->sirvClient->getErrorMsg(),
                        ];
                    } else {
                        $error = $this->sirvClient->getErrorMsg();
                    }
                    $aborted = true;
                    break;
                }
            } catch (\Exception $e) {
                $this->logger->critical('Exception on fetching images with Sirv API:', ['exception' => $e]);
                $result = false;
            }

            if (is_array($result)) {
                foreach ($result as $fileData) {
                    $relPath = preg_replace('#^' . preg_quote($this->imageFolder, '#') . '#', '', $fileData->filename);
                    $absPath = $this->mediaDirAbsPath . $relPath;

                    $attempt = is_array($fileData->attempts) ? end($fileData->attempts) : false;
                    if ($attempt) {
                        if (isset($attempt->error)) {
                            if (isset($attempt->error->httpCode)) {
                                if ((int)$attempt->error->httpCode == 429) {
                                    $rateLimit = [
                                        'expireTime' => isset($attempt->error->counter, $attempt->error->counter->reset) ? (int)$attempt->error->counter->reset : 0,
                                        'currentTime' => time(),
                                        'message' => isset($attempt->error->message) ? $attempt->error->message : 'Api rate limit error!',
                                    ];
                                    continue;
                                }
                            }
                        }
                    }

                    if ($fileData->success) {
                        $modificationTime = filemtime($absPath);
                        $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_SYNCED, $modificationTime);
                        $synced++;
                    } else {
                        $this->updateCacheData($relPath, self::MAGENTO_MEDIA_PATH, self::IS_FAILED, 0);
                        $failed++;
                        if ($attempt) {
                            $this->logger->info(sprintf('The "%s" file does not exist or is not available.', $attempt->url));
                        }
                    }
                }

                if ($rateLimit) {
                    $aborted = true;
                    break;
                }
            }

            if ($breakTime - time() <= 0) {
                $aborted = true;
                break;
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'aborted' => $aborted,
            'ratelimit' => $rateLimit,
            'error' => $error,
        ];
    }

    /**
     * Method to flush cache
     *
     * @param string $flushMethod
     * @return bool
     */
    public function flushCache($flushMethod)
    {
        $resource = $this->cacheModel->getResource();
        $result = false;

        switch ($flushMethod) {
            case 'failed':
                //NOTE: clear cached data with failed status from DB table
                $resource->deleteByStatus(self::IS_FAILED);
                $result = true;
                break;
            case 'queued':
                $resource->deleteByStatus(self::IS_NEW);
                $resource->deleteByStatus(self::IS_PROCESSING);
                $result = true;
                break;
            case 'all':
                //NOTE: clear DB cache
                $resource->deleteAll();
                $result = true;
                break;
            case 'master':
                //NOTE: delete images from Sirv and clear DB cache
                $result = true;
                break;
        }

        return $result;
    }

    /**
     * Get absolute path to the document root directory
     *
     * @return string
     */
    public function getRootDirAbsPath()
    {
        return $this->rootDirAbsPath;
    }

    /**
     * Get absolute path to the media directory
     *
     * @return string
     */
    public function getMediaDirAbsPath()
    {
        return $this->mediaDirAbsPath;
    }

    /**
     * Get path to product images relative to media directory
     *
     * @return string
     */
    public function getProductMediaRelPath()
    {
        return $this->productMediaRelPath;
    }

    /**
     * Get path to category images relative to media directory
     *
     * @return string
     */
    public function getCategoryMediaRelPath()
    {
        return $this->categoryMediaRelPath;
    }

    /**
     * Get Sirv base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Get images fetch list
     *
     * @return array
     */
    public function getImagesFetchList()
    {
        return $this->imagesToFetch;
    }
}
