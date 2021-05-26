<?php

namespace Sirv\Magento2\Model\Template;

/**
 * Template Filter Model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Filter extends \Magento\Widget\Model\Template\Filter
{
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
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync
     */
    protected static $syncHelper = null;

    /**
     * Absolute path to the media directory
     *
     * @var string
     */
    protected static $mediaDirAbsPath = '';

    /**
     * Initialize the data
     *
     * @return void
     */
    protected function initializeData()
    {
        static::$isInitialized = true;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $dataHelper = $objectManager->get(\Sirv\Magento2\Helper\Data::class);
        static::$isSirvEnabled = $dataHelper->isSirvEnabled();

        if (static::$isSirvEnabled) {
            static::$syncHelper = $objectManager->get(\Sirv\Magento2\Helper\Sync::class);
            static::$mediaDirAbsPath = static::$syncHelper->getMediaDirAbsPath();
        }
    }

    /**
     * Retrieve media file URL directive
     *
     * @param string[] $construction
     * @return string
     */
    public function mediaDirective($construction)
    {
        if (static::$isInitialized === false) {
            $this->initializeData();
        }

        $url = false;
        if (static::$isSirvEnabled) {
            $params = $this->getParameters(html_entity_decode($construction[2], ENT_QUOTES));
            if (isset($params['url'])) {
                $relPath = '/' . ltrim($params['url'], '\\/');
                $absPath = static::$mediaDirAbsPath . $relPath;
                $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;

                if (static::$syncHelper->isNotExcluded($absPath)) {
                    if (static::$syncHelper->isSynced($relPath)) {
                        $url = static::$syncHelper->getUrl($relPath);
                    } elseif (!static::$syncHelper->isCached($relPath)) {
                        if (static::$syncHelper->save($absPath, $pathType)) {
                            $url = static::$syncHelper->getUrl($relPath);
                        }
                    }
                }
            }
        }

        if (!$url) {
            $url = parent::mediaDirective($construction);
        }

        return $url;
    }
}
