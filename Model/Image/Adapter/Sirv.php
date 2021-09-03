<?php

namespace Sirv\Magento2\Model\Image\Adapter;

/**
 * Sirv adapter
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Sirv extends \Magento\Framework\Image\Adapter\AbstractAdapter
{
    /**
     * Sync helper
     *
     * @var \Sirv\Magento2\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Whether to use Magento watermark
     *
     * @var bool
     */
    protected $isWatermarkDisabled = false;

    /**
     * Imaging options (table of options: https://sirv.com/help/resources/dynamic-imaging/#Table_of_options )
     *
     * @var array
     */
    protected $imagingOptions = [];

    /**
     * Absolute path to the media directory
     *
     * @var string
     */
    protected $mediaDirAbsPath = '';

    /**
     * Required extensions
     *
     * @var array
     */
    protected $_requiredExtensions = ['curl'];

    /**
     * Whether image was resized or not
     *
     * @var bool
     */
    protected $_resized = false;

    /**
     * Image quality
     *
     * @var int
     */
    protected $_quality = null;

    /**
     * Sirv quality
     *
     * @var int
     */
    protected $sirvQuality = null;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Sirv\Magento2\Helper\Data $dataHelper
     * @param \Sirv\Magento2\Helper\Sync $syncHelper
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        \Psr\Log\LoggerInterface $logger,
        \Sirv\Magento2\Helper\Data $dataHelper,
        \Sirv\Magento2\Helper\Sync $syncHelper,
        array $data = []
    ) {
        parent::__construct($filesystem, $logger, $data);

        $this->isWatermarkDisabled = $dataHelper->getConfig('magento_watermark') == 'false';
        $profile = $dataHelper->getConfig('profile');
        if (!empty($profile) && !in_array($profile, ['-', 'Default'])) {
            $this->setImagingOptions('profile', $profile);
        }
        $this->syncHelper = $syncHelper;
        $this->mediaDirAbsPath = $syncHelper->getMediaDirAbsPath();
        $this->sirvQuality = $dataHelper->getConfig('image_quality');
    }

    /**
     * Open image for processing
     *
     * @param string $fileName
     * @return void
     */
    public function open($fileName)
    {
        $this->_fileName = $fileName;
        $this->_reset();
        $this->getMimeType();
        $this->_getFileAttributes();
    }

    /**
     * Reset properties
     *
     * @return void
     */
    protected function _reset()
    {
        $this->_fileMimeType = null;
        $this->_fileType = null;
    }

    /**
     * Save image to specific path.
     *
     * @param null|string $destination
     * @param null|string $newName
     * @return void
     */
    public function save($destination = null, $newName = null)
    {
        //NOTE: prepare destination
        /*
        if (empty($destination)) {
            $destination = $this->_fileSrcPath;
        } else {
            if (empty($newName)) {
                $info = pathinfo($destination);
                $newName = $info['basename'];
                $destination = $info['dirname'];
            }
        }

        if (empty($newName)) {
            $newFileName = $this->_fileSrcName;
        } else {
            $newFileName = $newName;
        }

        $fileName = $destination . '/' . $newFileName;
        */

        $absPath = $this->_fileName;

        $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_PRODUCT_MEDIA_PATH;
        $relPath = $this->syncHelper->getRelativePath($absPath, $pathType);
        if ($this->syncHelper->isCached($relPath)) {
            return;
        }

        $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;
        $relPath = $this->syncHelper->getRelativePath($absPath, $pathType);
        if ($this->syncHelper->isCached($relPath)) {
            return;
        }

        if ($this->syncHelper->isNotExcluded($absPath)) {
            $this->syncHelper->save($absPath, $pathType);
        }

        //NOTE: set image quality value
        /*
        $quality = $this->quality();
        if ($quality !== null) {
            switch ($this->_fileType) {
                case IMAGETYPE_PNG:
                    $quality = round(($quality / 100) * 10);
                    if ($quality < 1) {
                        $quality = 1;
                    } elseif ($quality > 10) {
                        $quality = 10;
                    }
                    $quality = 10 - $quality;
                    $this->setImagingOptions('png.compression', $quality);
                    break;
                case IMAGETYPE_JPEG:
                    $this->setImagingOptions('quality', $quality);
                    break;
            }
        }
        */
    }

    /**
     * Render image and return its binary contents
     *
     * @return string
     */
    public function getImage()
    {
        //NOTE: download file and return contents
        return '';
    }

    /**
     * Change the image size
     *
     * @param null|int $width
     * @param null|int $height
     * @return void
     */
    public function resize($width = null, $height = null)
    {
        $dims = $this->_adaptResizeValues($width, $height);

        $this->setImagingOptions('canvas.width', $dims['frame']['width']);
        $this->setImagingOptions('canvas.height', $dims['frame']['height']);

        //NOTE: fill new image with required color
        $this->_fillBackgroundColor();

        $this->setImagingOptions('w', $dims['dst']['width']);
        $this->setImagingOptions('h', $dims['dst']['height']);

        $this->_resized = true;
    }

    /**
     * Fill image with main background color.
     *
     * @return void
     */
    protected function _fillBackgroundColor()
    {
        list($r, $g, $b) = $this->_backgroundColor;
        $this->setImagingOptions('canvas.color', sprintf("%02s%02s%02s", dechex($r), dechex($g), dechex($b)));

        if ($this->_keepTransparency) {
            if (IMAGETYPE_GIF === $this->_fileType || IMAGETYPE_PNG === $this->_fileType) {
                $this->setImagingOptions('canvas.opacity', 0);
            }
        }
    }

    /**
     * Rotate image on specific angle
     *
     * @param int $angle
     * @return void
     */
    public function rotate($angle)
    {
        $angle = (int)$angle;
        if ($angle <= 0) {
            return;
        }
        if ($angle > 360) {
            $angle = $angle - floor($angle / 360) * 360;
        }
        if ($angle <= 180) {
            $angle = -1 * $angle;
        } else {
            $angle = 360 - $angle;
        }
        $this->setImagingOptions('rotate', $angle);
        $this->_fillBackgroundColor();
    }

    /**
     * Crop image
     *
     * @param int $top
     * @param int $left
     * @param int $right
     * @param int $bottom
     * @return bool
     */
    public function crop($top = 0, $left = 0, $right = 0, $bottom = 0)
    {
        if ($left == 0 && $top == 0 && $right == 0 && $bottom == 0) {
            return false;
        }

        $newWidth = $this->_imageSrcWidth - $left - $right;
        $newHeight = $this->_imageSrcHeight - $top - $bottom;

        $this->setImagingOptions('crop.x', (int)$top);
        $this->setImagingOptions('crop.y', (int)$left);
        $this->setImagingOptions('cw', (int)$newWidth);
        $this->setImagingOptions('ch', (int)$newHeight);

        return true;
    }

    /**
     * Add watermark to image
     *
     * @param string $absPath Filesystem path to the watermark image
     * @param int $positionX
     * @param int $positionY
     * @param int $opacity
     * @param bool $tile
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function watermark($absPath, $positionX = 0, $positionY = 0, $opacity = 30, $tile = false)
    {
        if ($this->isWatermarkDisabled) {
            return;
        }

        if (strpos($absPath, $this->mediaDirAbsPath) !== 0) {
            return;
        }

        if (!file_exists($absPath)) {
            return;
        }

        $relPath = substr($absPath, strlen($this->mediaDirAbsPath));

        $url = false;
        if ($this->syncHelper->isNotExcluded($absPath)) {
            if ($this->syncHelper->isSynced($relPath)) {
                $url = $this->syncHelper->getRelUrl($relPath);
            } elseif (!$this->syncHelper->isCached($relPath)) {
                if ($this->syncHelper->save($absPath, \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH)) {
                    $url = $this->syncHelper->getRelUrl($relPath);
                }
            }
        }

        if (!$url) {
            return;
        }

        list($watermarkSrcWidth, $watermarkSrcHeight, $watermarkFileType,) = $this->_getImageOptions($absPath);
        $this->_getFileAttributes();

        $width = $this->getWatermarkWidth();
        if (empty($width)) {
            $width = $watermarkSrcWidth;
        }
        $height = $this->getWatermarkHeight();
        if (empty($height)) {
            $height = $watermarkSrcHeight;
        }
        $opacity = $this->getWatermarkImageOpacity();
        if (empty($opacity)) {
            $opacity = 50;
        }

        $this->setImagingOptions('watermark.image', urlencode($url));
        $this->setImagingOptions('watermark.opacity', $opacity);

        if ($this->getWatermarkWidth() &&
            $this->getWatermarkHeight() &&
            $this->getWatermarkPosition() != self::POSITION_STRETCH
        ) {
            $this->setImagingOptions('watermark.scale.width', $width);
            $this->setImagingOptions('watermark.scale.height', $height);
            $this->setImagingOptions('watermark.scale.option', 'ignore');
        }

        if ($this->getWatermarkPosition() == self::POSITION_TILE) {
            $this->setImagingOptions('watermark.position', 'tile');
        } elseif ($this->getWatermarkPosition() == self::POSITION_STRETCH) {
            $this->setImagingOptions('watermark.position', 'center');
            $this->setImagingOptions('watermark.scale.width', $this->_imageSrcWidth);
            $this->setImagingOptions('watermark.scale.height', $this->_imageSrcHeight);
            $this->setImagingOptions('watermark.scale.option', 'ignore');
        } elseif ($this->getWatermarkPosition() == self::POSITION_CENTER) {
            $this->setImagingOptions('watermark.position', 'center');
        } elseif ($this->getWatermarkPosition() == self::POSITION_TOP_RIGHT) {
            $this->setImagingOptions('watermark.position', 'northeast');
        } elseif ($this->getWatermarkPosition() == self::POSITION_TOP_LEFT) {
            $this->setImagingOptions('watermark.position', 'northwest');
        } elseif ($this->getWatermarkPosition() == self::POSITION_BOTTOM_RIGHT) {
            $this->setImagingOptions('watermark.position', 'southeast');
        } elseif ($this->getWatermarkPosition() == self::POSITION_BOTTOM_LEFT) {
            $this->setImagingOptions('watermark.position', 'southwest');
        }
    }

    /**
     * Checks required dependencies
     *
     * @return void
     * @throws \Exception If some of dependencies are missing
     */
    public function checkDependencies()
    {
        foreach ($this->_requiredExtensions as $value) {
            if (!extension_loaded($value)) {
                throw new \Exception("Required PHP extension '{$value}' was not loaded.");
            }
        }
    }

    /**
     * Create Image from string
     *
     * @param string $text
     * @param string $font Path to font file
     * @return $this
     */
    public function createPngFromString($text, $font = '')
    {
        return $this;
    }

    /**
     * Reassign image dimensions
     *
     * @return void
     */
    public function refreshImageDimensions()
    {
        return;
    }

    /**
     * Returns rgba array of the specified pixel
     *
     * @param int $x
     * @param int $y
     * @return array
     */
    public function getColorAt($x, $y)
    {
        return [
           'red' => 255,
           'green' => 255,
           'blue' => 255,
           'alpha' => 0
        ];
    }

    /**
     * Set watermark position
     *
     * @param string $position
     * @return $this
     */
    public function setWatermarkPosition($position)
    {
        if ($this->isWatermarkDisabled) {
            return $this;
        }

        $this->_watermarkPosition = $position;

        $width = $this->getWatermarkWidth();
        if (empty($width)) {
            $width = '100%';
        }
        $height = $this->getWatermarkHeight();
        if (empty($height)) {
            $height = '100%';
        }

        if ($width && $height && $position != self::POSITION_STRETCH) {
            $this->setImagingOptions('watermark.scale.width', $width);
            $this->setImagingOptions('watermark.scale.height', $height);
            $this->setImagingOptions('watermark.scale.option', 'ignore');
        }

        switch ($position) {
            case self::POSITION_CENTER:
                $this->setImagingOptions('watermark.position', 'center');
                break;
            case self::POSITION_TOP_RIGHT:
                $this->setImagingOptions('watermark.position', 'northeast');
                break;
            case self::POSITION_TOP_LEFT:
                $this->setImagingOptions('watermark.position', 'northwest');
                break;
            case self::POSITION_BOTTOM_RIGHT:
                $this->setImagingOptions('watermark.position', 'southeast');
                break;
            case self::POSITION_BOTTOM_LEFT:
                $this->setImagingOptions('watermark.position', 'southwest');
                break;
            case self::POSITION_TILE:
                $this->setImagingOptions('watermark.position', 'tile');
                break;
            case self::POSITION_STRETCH:
            default:
                $this->setImagingOptions('watermark.position', 'center');
                $this->setImagingOptions('watermark.scale.width', '100%');
                $this->setImagingOptions('watermark.scale.height', '100%');
                $this->setImagingOptions('watermark.scale.option', 'ignore');
                break;
        }

        return $this;
    }

    /**
     * Set watermark opacity
     *
     * @param int $imageOpacity
     * @return $this
     */
    public function setWatermarkImageOpacity($imageOpacity)
    {
        if ($this->isWatermarkDisabled || empty($imageOpacity)) {
            return $this;
        }
        $this->_watermarkImageOpacity = $imageOpacity;
        $this->setImagingOptions('watermark.opacity', $imageOpacity);
        return $this;
    }

    /**
     * Set watermark width
     *
     * @param int $width
     * @return $this
     */
    public function setWatermarkWidth($width)
    {
        if ($this->isWatermarkDisabled || empty($width)) {
            return $this;
        }
        $this->_watermarkWidth = $width;
        if ($this->getWatermarkPosition() == self::POSITION_STRETCH) {
            $this->setImagingOptions('watermark.scale.width', '100%');
            $this->setImagingOptions('watermark.scale.option', 'ignore');
        } else {
            $this->setImagingOptions('watermark.scale.width', $width);
        }
        return $this;
    }

    /**
     * Set watermark height
     *
     * @param int $height
     * @return $this
     */
    public function setWatermarkHeight($height)
    {
        if ($this->isWatermarkDisabled || empty($height)) {
            return $this;
        }
        $this->_watermarkHeight = $height;
        if ($this->getWatermarkPosition() == self::POSITION_STRETCH) {
            $this->setImagingOptions('watermark.scale.height', '100%');
            $this->setImagingOptions('watermark.scale.option', 'ignore');
        } else {
            $this->setImagingOptions('watermark.scale.height', $height);
        }
        return $this;
    }

    /**
     * Get/set quality, values in percentage from 0 to 100
     *
     * @param int $value
     * @return int
     */
    public function quality($value = null)
    {
        if (null !== $value) {
            $this->_quality = (int)$value;
        }

        $quality = $this->sirvQuality ? $this->sirvQuality : $this->_quality;
        if ($quality) {
            switch ($this->_fileType) {
                case IMAGETYPE_JPEG:
                    $this->setImagingOptions('q', $quality);
                    break;
            }
        }

        return $this->_quality;
    }

    /**
     * Set imaging options
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    protected function setImagingOptions($name, $value)
    {
        $this->imagingOptions[$name] = $value;
    }

    /**
     * Get imaging option
     *
     * @param string $name
     * @return string|null
     */
    public function getImagingOption($name)
    {
        return isset($this->imagingOptions[$name]) ? $this->imagingOptions[$name] : null;
    }

    /**
     * Get imaging options query
     *
     * @return string
     */
    public function getImagingOptionsQuery()
    {
        $query = [];
        foreach ($this->imagingOptions as $key => $value) {
            $query[] = "{$key}={$value}";
        }

        return empty($query) ? '' : '?' . implode('&', $query);
    }
}
