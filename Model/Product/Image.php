<?php

namespace Sirv\Magento2\Model\Product;

/**
 * Product image model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Image extends \Magento\Catalog\Model\Product\Image
{
    /**
     * Data helper
     *
     * @var \Sirv\Magento2\Helper\Data
     */
    protected $dataHelper = null;

    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Model construct for object initialization
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->dataHelper = $objectManager->get(\Sirv\Magento2\Helper\Data::class);
        $this->syncHelper = $objectManager->get(\Sirv\Magento2\Helper\Sync::class);
        $this->isSirvEnabled = $this->dataHelper->isSirvEnabled();
    }

    /**
     * Set watermark file
     *
     * @param string $file
     * @return $this
     */
    public function setWatermarkFile($file)
    {
        parent::setWatermarkFile($file);

        $absPath = $this->_getWatermarkFilePath();
        if ($absPath) {
            if ($this->syncHelper->isNotExcluded($absPath)) {
                $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;
                $relPath = $this->syncHelper->getRelativePath($absPath, $pathType);
                if (!$this->syncHelper->isCached($relPath)) {
                    $this->syncHelper->save($absPath, $pathType);
                }
            }
        }

        return $this;
    }

    /**
     * Get watermark file path
     *
     * @return string | bool
     */
    protected function _getWatermarkFilePath()
    {
        //NOTICE: to reduce the number of the parent function calls
        static $watermarks = [];

        if (!($file = $this->getWatermarkFile())) {
            return false;
        }

        if (!isset($watermarks[$file])) {
            $watermarks[$file] = parent::_getWatermarkFilePath();
        }

        return $watermarks[$file];
    }

    /**
     * Save file
     *
     * @return $this
     */
    public function saveFile()
    {
        if ($this->isSirvEnabled && !$this->_isBaseFilePlaceholder) {
            $baseFile = $this->getBaseFile();
            $absPath = $baseFile ? $this->_mediaDirectory->getAbsolutePath($baseFile) : null;

            if ($this->syncHelper->isNotExcluded($absPath)) {
                $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;
                $relPath = $this->syncHelper->getRelativePath($absPath, $pathType);
                if (!$this->syncHelper->isCached($relPath)) {
                    $pathTypeOld = \Sirv\Magento2\Helper\Sync::MAGENTO_PRODUCT_MEDIA_PATH;
                    $relPathOld = $this->syncHelper->getRelativePath($absPath, $pathTypeOld);
                    if (!$this->syncHelper->isCached($relPathOld)) {
                        $isFileSynced = $this->syncHelper->save($absPath, $pathType);
                        if ($isFileSynced) {
                            //NOTICE: check case when file is synced but not exists in Magento cache
                            return $this;
                        }
                    }
                }
            }
        }

        return parent::saveFile();
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        if ($this->_isBaseFilePlaceholder || !$this->isSirvEnabled) {
            return parent::getUrl();
        }

        $baseFile = $this->getBaseFile();
        $absPath = $baseFile ? $this->_mediaDirectory->getAbsolutePath($baseFile) : null;

        $isFileCached = true;
        $isFileSynced = false;

        $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;
        $relPath = $this->syncHelper->getRelativePath($absPath, $pathType);
        if (!$this->syncHelper->isCached($relPath)) {
            $pathTypeOld = \Sirv\Magento2\Helper\Sync::MAGENTO_PRODUCT_MEDIA_PATH;
            $relPathOld = $this->syncHelper->getRelativePath($absPath, $pathTypeOld);
            if ($this->syncHelper->isCached($relPathOld)) {
                $pathType = $pathTypeOld;
                $relPath = $relPathOld;
            } else {
                //NOTICE: check if image should be saved if it is not cached
                $isFileCached = false;
            }
        }

        if ($this->syncHelper->isNotExcluded($absPath)) {
            if ($isFileCached) {
                $isFileSynced = $this->syncHelper->isSynced($relPath);
            } else {
                $isFileSynced = $this->syncHelper->save($absPath, $pathType);
            }
        }

        if (!$isFileSynced) {
            return parent::getUrl();
        }

        $url = $this->syncHelper->getUrl($relPath);
        $url .= $this->getUrlQuery($absPath);

        return $url;
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
            /** @var \Sirv\Magento2\Model\Image $processor */
            $processor = $this->_imageFactory->create($absPath, 'SIRV');
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }

        $processor->keepAspectRatio($this->_keepAspectRatio);
        $processor->keepFrame($this->_keepFrame);
        $processor->keepTransparency($this->_keepTransparency);
        $processor->constrainOnly($this->_constrainOnly);
        $processor->backgroundColor($this->_backgroundColor);
        $processor->quality($this->getQuality());

        if ($this->getWidth() !== null || $this->getHeight() !== null) {
            $processor->resize($this->_width, $this->_height);
        }

        if ($this->_angle) {
            $processor->rotate((int)$this->_angle);
        }

        $filePath = $this->_getWatermarkFilePath();
        if ($filePath) {
            $processor->watermark($filePath);
            $processor->setWatermarkPosition($this->getWatermarkPosition());
            $processor->setWatermarkImageOpacity($this->getWatermarkImageOpacity());
            $processor->setWatermarkWidth($this->getWatermarkWidth());
            $processor->setWatermarkHeight($this->getWatermarkHeight());
        }

        return $processor->getImagingOptionsQuery();
    }
}
