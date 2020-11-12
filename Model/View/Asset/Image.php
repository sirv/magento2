<?php

namespace MagicToolbox\Sirv\Model\View\Asset;

/**
 * Image file asset
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Image implements \Magento\Framework\View\Asset\LocalInterface
{
    /**
     * Image type (thumbnail, small_image, image, swatch_image, swatch_thumb)
     *
     * @var string
     */
    protected $sourceContentType;

    /**
     * File path
     *
     * @var string
     */
    protected $filePath;

    /**
     * Default image type
     *
     * @var string
     */
    protected $contentType = 'image';

    /**
     * Context
     *
     * @var \MagicToolbox\Sirv\Model\View\Asset\Image\Context
     */
    protected $context;

    /**
     * Misc image params
     *
     * @var array
     */
    protected $miscParams;

    /**
     * Config interface
     *
     * @var \Magento\Catalog\Model\Product\Media\ConfigInterface
     */
    protected $mediaConfig;

    /**
     * Encryptor interface
     *
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptor;

    /**
     * Determine if the data has been initialized or not
     *
     * @var bool
     */
    protected static $isInitialized = false;

    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected static $isSirvEnabled = false;

    /**
     * Media Directory
     *
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    protected static $mediaDirectory;

    /**
     * Path to product images relative to media directory
     *
     * @var string
     */
    protected static $productMediaRelPath = '';

    /**
     * Sync helper
     *
     * @var \MagicToolbox\Sirv\Helper\Sync
     */
    protected static $syncHelper = null;

    /**
     * Image handler factory
     *
     * @var \Magento\Framework\Image\Factory
     */
    protected static $imageFactory;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected static $storeManager;

    /**
     * File System
     *
     * @var \Magento\Framework\View\FileSystem
     */
    protected static $viewFileSystem;

    /**
     * Flag, outdated Magento version
     *
     * @var bool
     */
    protected static $outdatedMagentoVersion;

    /**
     * Constructor
     *
     * @param \Magento\Catalog\Model\Product\Media\ConfigInterface $mediaConfig
     * @param \MagicToolbox\Sirv\Model\View\Asset\Image\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param string $filePath
     * @param array $miscParams
     * @return void
     */
    public function __construct(
        \Magento\Catalog\Model\Product\Media\ConfigInterface $mediaConfig,
        \MagicToolbox\Sirv\Model\View\Asset\Image\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        $filePath,
        array $miscParams = []
    ) {
        if (isset($miscParams['image_type'])) {
            $this->sourceContentType = $miscParams['image_type'];
            unset($miscParams['image_type']);
        } else {
            $this->sourceContentType = $this->contentType;
        }
        $this->mediaConfig = $mediaConfig;
        $this->context = $context;
        $this->filePath = $filePath;
        $this->miscParams = $miscParams;
        $this->encryptor = $encryptor;

        if (static::$isInitialized === false) {
            $this->initializeData();
        }
    }

    /**
     * Initialize the data
     *
     * @return void
     */
    protected function initializeData()
    {
        static::$isInitialized = true;

        static::$productMediaRelPath = $this->mediaConfig->getBaseMediaPath();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $dataHelper = $objectManager->get(\MagicToolbox\Sirv\Helper\Data::class);
        static::$isSirvEnabled = $dataHelper->isSirvEnabled();

        static::$syncHelper = $objectManager->get(\MagicToolbox\Sirv\Helper\Sync::class);
        static::$imageFactory = $objectManager->get(\MagicToolbox\Sirv\Model\Image\Factory::class);

        $filesystem = $objectManager->get(\Magento\Framework\Filesystem::class);
        static::$mediaDirectory = $filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

        static::$storeManager = $objectManager->get(\Magento\Store\Model\StoreManager::class);
        static::$viewFileSystem = $objectManager->get(\Magento\Framework\View\FileSystem::class);

        $productMetadata = $objectManager->get(\Magento\Framework\App\ProductMetadataInterface::class);
        $version = $productMetadata->getVersion();
        static::$outdatedMagentoVersion = version_compare($version, '2.3.4', '<') && version_compare($version, '2.3.0', '>=');
    }

    /**
     * Get resource URL
     *
     * @return string
     */
    public function getUrl()
    {
        if (!static::$isSirvEnabled) {
            return $this->context->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getRelativePath();
        }

        //NOTE: path relative to the media folder
        $srcPath = $this->getSourceFile();

        //NOTE: absolute file path
        $absPath = static::$mediaDirectory->getAbsolutePath($srcPath);

        $isFileCached = true;
        $isFileSynced = false;

        $pathType = \MagicToolbox\Sirv\Helper\Sync::MAGENTO_MEDIA_PATH;
        $relPath = static::$syncHelper->getRelativePath($absPath, $pathType);
        if (!static::$syncHelper->isCached($relPath)) {
            $pathTypeOld = \MagicToolbox\Sirv\Helper\Sync::MAGENTO_PRODUCT_MEDIA_PATH;
            $relPathOld = static::$syncHelper->getRelativePath($absPath, $pathTypeOld);
            if (static::$syncHelper->isCached($relPathOld)) {
                $pathType = $pathTypeOld;
                $relPath = $relPathOld;
            } else {
                $isFileCached = false;
            }
        }

        if ($isFileCached) {
            $isFileSynced = static::$syncHelper->isSynced($relPath);
        } else {
            $isFileSynced = static::$syncHelper->save($absPath, $pathType);
        }

        //NOTE: to sync watermark file with product image
        if (isset($this->miscParams['watermark_file'])) {
            $watermarkAbsPath = $this->getWatermarkFilePath($this->miscParams['watermark_file']);
            if ($watermarkAbsPath) {
                $watermarkRelPath = static::$syncHelper->getRelativePath($watermarkAbsPath, \MagicToolbox\Sirv\Helper\Sync::MAGENTO_MEDIA_PATH);
                if (!static::$syncHelper->isCached($watermarkRelPath)) {
                    static::$syncHelper->save($watermarkAbsPath, \MagicToolbox\Sirv\Helper\Sync::MAGENTO_MEDIA_PATH);
                }
            }
        }

        if (!$isFileSynced) {
            return $this->context->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getRelativePath();
        }

        if (!is_file($absPath)) {
            return $this->context->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getRelativePath();
        }

        $url = static::$syncHelper->getUrl($relPath);
        $url .= $this->getUrlQuery($absPath);

        return $url;
    }

    /**
     * Get type of contents
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Get a "context" path to the asset file
     *
     * @return string
     */
    public function getPath()
    {
        return $this->context->getPath() . DIRECTORY_SEPARATOR . $this->getRelativePath();
    }

    /**
     * Get original source file
     *
     * @return string
     */
    public function getSourceFile()
    {
        return static::$productMediaRelPath . DIRECTORY_SEPARATOR . ltrim($this->getFilePath(), DIRECTORY_SEPARATOR);
    }

    /**
     * Get source content type
     *
     * @return string
     */
    public function getSourceContentType()
    {
        return $this->sourceContentType;
    }

    /**
     * Get content of a local asset
     *
     * @return string
     */
    public function getContent()
    {
        return null;
    }

    /**
     * Get an invariant relative path to file
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Get context of the asset
     *
     * @return \Magento\Framework\View\Asset\ContextInterface
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Get the module context of file path
     *
     * @return string
     */
    public function getModule()
    {
        return 'cache';
    }

    /**
     * Retrieve part of path based on misc params
     *
     * @return string
     */
    protected function getMiscPath()
    {
        return $this->encryptor->hash(
            implode('_', $this->convertToReadableFormat($this->miscParams)),
            \Magento\Framework\Encryption\Encryptor::HASH_VERSION_MD5
        );
    }

    /**
     * Generate relative path
     *
     * @return string
     */
    protected function getRelativePath()
    {
        return preg_replace(
            '#\Q'. DIRECTORY_SEPARATOR . '\E+#',
            DIRECTORY_SEPARATOR,
            $this->getModule() . DIRECTORY_SEPARATOR . $this->getMiscPath() . DIRECTORY_SEPARATOR . $this->getFilePath()
        );
    }

    /**
     * Converting non-string values into a string representation
     *
     * @param array $params
     * @return array
     */
    protected function convertToReadableFormat($params)
    {
        if (!method_exists('\Magento\Catalog\Model\View\Asset\Image', 'convertToReadableFormat')) {
            return $params;
        }

        $params['image_height'] = 'h:' . ($params['image_height'] ?? 'empty');
        $params['image_width'] = 'w:' . ($params['image_width'] ?? 'empty');
        $params['quality'] = 'q:' . ($params['quality'] ?? 'empty');
        $params['angle'] = 'r:' . ($params['angle'] ?? 'empty');

        /* NOTE: for Magento version 2.3.0 - 2.3.3 */
        if (static::$outdatedMagentoVersion) {
            $params['keep_aspect_ratio'] = (isset($params['keep_aspect_ratio']) ? '' : 'non') . 'proportional';
            $params['keep_frame'] = (isset($params['keep_frame']) ? '' : 'no') . 'frame';
            $params['keep_transparency'] = (isset($params['keep_transparency']) ? '' : 'no') . 'transparency';
            $params['constrain_only'] = (isset($params['constrain_only']) ? 'do' : 'not') . 'constrainonly';
            $params['background'] = isset($params['background'])
                ? 'rgb' . implode(',', $params['background'])
                : 'nobackground';

            return $params;
        }

        $params['keep_aspect_ratio'] = (!empty($params['keep_aspect_ratio']) ? '' : 'non') . 'proportional';
        $params['keep_frame'] = (!empty($params['keep_frame']) ? '' : 'no') . 'frame';
        $params['keep_transparency'] = (!empty($params['keep_transparency']) ? '' : 'no') . 'transparency';
        $params['constrain_only'] = (!empty($params['constrain_only']) ? 'do' : 'not') . 'constrainonly';
        if (!empty($params['background'])) {
            $params['background'] = 'rgb' . (is_array($params['background']) ? implode(',', $params['background']) : $params['background']);
        } else {
            $params['background'] = 'nobackground';
        }

        return $params;
    }

    /**
     * Get url query
     *
     * @param string $absPath
     * @return string
     */
    protected function getUrlQuery($absPath)
    {
        try {
            /** @var \MagicToolbox\Sirv\Model\Image $processor */
            $processor = static::$imageFactory->create($absPath, 'SIRV');
        } catch (\Exception $e) {
            $this->context->getLogger()->critical($e);
        }

        if (isset($this->miscParams['keep_aspect_ratio'])) {
            $processor->keepAspectRatio($this->miscParams['keep_aspect_ratio']);
        }
        if (isset($this->miscParams['keep_frame'])) {
            $processor->keepFrame($this->miscParams['keep_frame']);
        }
        if (isset($this->miscParams['keep_transparency'])) {
            $processor->keepTransparency($this->miscParams['keep_transparency']);
        }
        if (isset($this->miscParams['constrain_only'])) {
            $processor->constrainOnly($this->miscParams['constrain_only']);
        }
        if (isset($this->miscParams['background'])) {
            $processor->backgroundColor($this->miscParams['background']);
        }
        if (isset($this->miscParams['quality'])) {
            $processor->quality($this->miscParams['quality']);
        }

        $width = isset($this->miscParams['image_width']) ? $this->miscParams['image_width'] : null;
        $height = isset($this->miscParams['image_height']) ? $this->miscParams['image_height'] : null;
        if ($width !== null || $height !== null) {
            $processor->resize($width, $height);
        }

        if (isset($this->miscParams['angle'])) {
            $processor->rotate((int)$this->miscParams['angle']);
        }

        if (isset($this->miscParams['watermark_file'])) {
            $filePath = $this->getWatermarkFilePath($this->miscParams['watermark_file']);
            if ($filePath) {
                $processor->watermark($filePath);
                $processor->setWatermarkPosition($this->miscParams['watermark_position']);
                $processor->setWatermarkImageOpacity($this->miscParams['watermark_image_opacity']);
                $processor->setWatermarkWidth($this->miscParams['watermark_width']);
                $processor->setWatermarkHeight($this->miscParams['watermark_height']);
            }
        }

        return $processor->getImagingOptionsQuery();
    }

    /**
     * Get absolute watermark file path or false if the file is not found
     *
     * @param string $watermarkFile
     * @return string | bool
     */
    protected function getWatermarkFilePath($watermarkFile)
    {
        static $watermarks = [];

        if (isset($watermarks[$watermarkFile])) {
            return $watermarks[$watermarkFile];
        }

        $watermarks[$watermarkFile] = false;

        $candidates = [
            static::$productMediaRelPath . '/watermark/stores/' . static::$storeManager->getStore()->getId() . $watermarkFile,
            static::$productMediaRelPath . '/watermark/websites/' . static::$storeManager->getWebsite()->getId() . $watermarkFile,
            static::$productMediaRelPath . '/watermark/default/' . $watermarkFile,
            static::$productMediaRelPath . '/watermark/' . $watermarkFile,
        ];
        foreach ($candidates as $candidate) {
            if (static::$mediaDirectory->isExist($candidate)) {
                $watermarks[$watermarkFile] = static::$mediaDirectory->getAbsolutePath($candidate);
                break;
            }
        }

        if (!$watermarks[$watermarkFile]) {
            $watermarks[$watermarkFile] = static::$viewFileSystem->getStaticFileName($watermarkFile);
        }

        return $watermarks[$watermarkFile];
    }
}
