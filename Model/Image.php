<?php

namespace MagicToolbox\Sirv\Model;

/**
 * Image handler library
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Image extends \Magento\Framework\Image
{
    /**
     * Retrieve original image width
     *
     * @return int|null
     */
    public function getOriginalWidth()
    {
        if (isset($this->_fileName)) {
            return $this->_adapter->getOriginalWidth();
        }

        return null;
    }

    /**
     * Retrieve original image height
     *
     * @return int|null
     */
    public function getOriginalHeight()
    {
        if (isset($this->_fileName)) {
            return $this->_adapter->getOriginalHeight();
        }

        return null;
    }

    /**
     * Get get imaging options query
     *
     * @return string
     */
    public function getImagingOptionsQuery()
    {
        //NOTE: only for Sirv adapter
        return $this->_adapter->getImagingOptionsQuery();
    }

    /**
     * Get image size
     *
     * @return array
     */
    public function getImageSize()
    {
        $size = [
            $this->_adapter->getImagingOption('w'),
            $this->_adapter->getImagingOption('h')
        ];

        return $size;
    }
}
