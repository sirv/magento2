<?php

namespace Sirv\Magento2\Helper;

/**
 * Sync helper
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
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
     * @var \Sirv\Magento2\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Cache model
     *
     * @var \Sirv\Magento2\Model\Cache
     */
    protected $cacheModel = null;

    /**
     * Messages model factory
     *
     * @var \Sirv\Magento2\Model\MessagesFactory
     */
    protected $messagesModelFactory = null;

    /**
     * Sirv client
     *
     * @var \Sirv\Magento2\Model\Api\Sirv
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
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * Media directory object
     *
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    protected $mediaDirectory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Model\Product\Media\Config $catalogProductMediaConfig
     * @param \Sirv\Magento2\Helper\Data $dataHelper
     * @param \Sirv\Magento2\Model\CacheFactory $cacheModelFactory
     * @param \Sirv\Magento2\Model\MessagesFactory $messagesModelFactory
     * @return void
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Model\Product\Media\Config $catalogProductMediaConfig,
        \Sirv\Magento2\Helper\Data $dataHelper,
        \Sirv\Magento2\Model\CacheFactory $cacheModelFactory,
        \Sirv\Magento2\Model\MessagesFactory $messagesModelFactory
    ) {
        parent::__construct($context);

        $this->dataHelper = $dataHelper;
        $this->cacheModel = $cacheModelFactory->create();
        $this->messagesModelFactory = $messagesModelFactory;

        $this->logger = $context->getLogger();
        $this->sirvClient = $dataHelper->getSirvClient();

        $this->baseDirectUrl = 'https://' . $dataHelper->getSirvDomain();
        $this->baseUrl = 'https://' . $dataHelper->getSirvDomain(false);

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

        $cdnUrl = $dataHelper->getConfig('cdn_url');
        $cdnUrl = is_string($cdnUrl) ? trim($cdnUrl) : '';
        if (!empty($cdnUrl) && strpos($this->mediaBaseUrl, $cdnUrl) !== false) {
            $storeManager = $dataHelper->getStoreManager();
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

        $this->mediaDirectory = $filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
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
     * Check the file is not excluded
     *
     * @param string $path
     * @return bool
     */
    public function isNotExcluded($path)
    {
        static $regExp = null, $list = [];

        if ($regExp === null) {
            $excludedFiles = $this->dataHelper->getConfig('excluded_files') ?: '';
            if (empty($excludedFiles)) {
                $regExp = '';
            } else {
                $excludedFiles = explode("\n", $excludedFiles);
                foreach ($excludedFiles as &$pattern) {
                    $pattern = str_replace(
                        '__ASTERISK__',
                        '.*',
                        preg_quote(
                            str_replace('*', '__ASTERISK__', $pattern),
                            '#'
                        )
                    );
                }
                $regExp = '#' . implode('|', $excludedFiles) . '#';
            }
        }

        if (!isset($list[$path])) {
            $list[$path] = empty($regExp) || !preg_match($regExp, $path);
        }

        return $list[$path];
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
            /** @var \Sirv\Magento2\Model\Cache $cacheModel */
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
            /** @var \Sirv\Magento2\Model\Cache $cacheModel */
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
            /** @var \Sirv\Magento2\Model\Cache $cacheModel */
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
     * Update or insert message table data
     *
     * @param string $path
     * @param string $message
     * @return bool
     */
    public function updateMessageData($path, $message)
    {
        static $messagesModel = null;

        if ($messagesModel === null) {
            $messagesModel = $this->messagesModelFactory->create();
        }

        try {
            $messagesModel->clearInstance()->load($path, 'path');
            $messagesModel->setPath($path);
            $messagesModel->setMessage($message);
            $messagesModel->save();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }

        return true;
    }

    /**
     * Get sync status
     *
     * @param string $path
     * @return int
     */
    public function getSyncStatus($path)
    {
        $status = self::IS_UNDEFINED;
        try {
            /** @var \Sirv\Magento2\Model\Cache $cacheModel */
            $cacheModel = $this->cacheModel->clearInstance()->load($path, 'path');
            $status = $cacheModel->getStatus();
            if ($status === null) {
                $status = self::IS_UNDEFINED;
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return $status;
    }

    /**
     * Save file
     *
     * @param string $absPath
     * @param int $pathType
     * @param bool $forcedUpload
     * @return bool
     */
    public function save($absPath, $pathType = self::UNKNOWN_PATH, $forcedUpload = false)
    {
        if (!$this->isAuth) {
            return false;
        }

        if (!is_file($absPath)) {
            return false;
        }

        $relPath = $this->getRelativePath($absPath, $pathType);

        if ($this->isLocalHost || $forcedUpload) {
            try {
                $result = $this->sirvClient->uploadFile($this->imageFolder . $relPath, $absPath);
            } catch (\Exception $e) {
                $result = false;
                $this->updateCacheData($relPath, $pathType, self::IS_FAILED, 0);
                $this->updateMessageData($relPath, $e->getMessage());
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
                    $relPath = preg_replace('#^' . preg_quote($this->imageFolder, '#') . '#', '', $data->filename);
                    $pathType = self::UNKNOWN_PATH;
                    if (strpos($relPath, '/catalog/') === 0 ||
                        strpos($relPath, '/wysiwyg/') === 0 ||
                        strpos($relPath, $this->productMediaRelPath . '/') === 0 ||
                        strpos($relPath, $this->categoryMediaRelPath . '/') === 0 ||
                        strpos($relPath, $this->magic360MediaRelPath . '/') === 0
                    ) {
                        $pathType = self::MAGENTO_MEDIA_PATH;
                    }
                    $status = $wait ? ($data->success ? self::IS_SYNCED : self::IS_FAILED) : self::IS_PROCESSING;
                    $modificationTime = ($status == self::IS_FAILED ? 0 : null);
                    $this->updateCacheData($relPath, $pathType, $status, $modificationTime);
                    if ($status == self::IS_FAILED) {
                        $errorMessage = 'Unknown error.';
                        $attempt = is_array($data->attempts) ? end($data->attempts) : false;
                        if ($attempt) {
                            if (isset($attempt->error)) {
                                $errorMessage = isset($attempt->error->message) ? $attempt->error->message : '';
                                $errorMessage = preg_replace('#(?:\s*+\.)?\s*+$#', '.', $errorMessage);
                            }
                            /*
                            if (isset($attempt->statusCode)) {
                                if ((int)$attempt->statusCode == 404) {
                                    $errorMessage = 'The file is not found on the server.';
                                }
                            }
                            */
                        }
                        $this->updateMessageData($relPath, $errorMessage);
                    }
                }
            }
        }

        $this->imagesToFetch = [];
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
