<?php

namespace Sirv\Magento2\Model;

/**
 * Catalog category model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Category extends \Magento\Catalog\Model\Category
{
    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Absolute path to the document root directory
     *
     * @var string
     */
    protected $rootDirAbsPath = '';

    /**
     * Absolute path to the media directory
     *
     * @var string
     */
    protected $mediaDirAbsPath = '';

    /**
     * Path to category images relative to media directory
     *
     * @var string
     */
    protected $categoryMediaRelPath = '';

    /**
     * Model construct for object initialization
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $dataHelper = $objectManager->get(\Sirv\Magento2\Helper\Data::class);
        $this->isSirvEnabled = $dataHelper->isSirvEnabled();

        if ($this->isSirvEnabled) {
            $this->syncHelper = $objectManager->get(\Sirv\Magento2\Helper\Sync::class);
            $this->rootDirAbsPath = $this->syncHelper->getRootDirAbsPath();
            $this->mediaDirAbsPath = $this->syncHelper->getMediaDirAbsPath();
            $this->categoryMediaRelPath = $this->syncHelper->getCategoryMediaRelPath();
        }
    }

    /**
     * Get category image url
     *
     * @param string $attributeCode
     * @return bool|string
     */
    public function getImageUrl($attributeCode = 'image')
    {
        $imageUrl = null;

        if ($this->isSirvEnabled) {
            $image = $this->getData($attributeCode);

            if ($image && is_string($image)) {
                if (substr($image, 0, 1) === '/') {
                    $pathType = \Sirv\Magento2\Helper\Sync::DOCUMENT_ROOT_PATH;
                    $relPath = $image;
                    $absPath = $this->rootDirAbsPath . $relPath;
                    if (strpos($absPath, $this->mediaDirAbsPath . '/') === 0) {
                        $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;
                        $relPath = $this->syncHelper->getRelativePath($absPath, $pathType);
                    }
                } else {
                    $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;
                    $relPath = $this->categoryMediaRelPath . '/' . $image;
                    $absPath = $this->mediaDirAbsPath . $relPath;
                }

                if ($this->syncHelper->isNotExcluded($absPath)) {
                    if ($this->syncHelper->isSynced($relPath)) {
                        $imageUrl = $this->syncHelper->getUrl($relPath);
                    } elseif (!$this->syncHelper->isCached($relPath)) {
                        if ($this->syncHelper->save($absPath, $pathType)) {
                            $imageUrl = $this->syncHelper->getUrl($relPath);
                        }
                    }
                }
            }
        }

        if (!$imageUrl) {
            $imageUrl = parent::getImageUrl($attributeCode);
        }

        return $imageUrl;
    }
}
