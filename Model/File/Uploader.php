<?php

namespace Sirv\Magento2\Model\File;

/**
 * Sirv file uploader model
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Uploader extends \Magento\Framework\File\Uploader
{
    /**
     * Correct filename
     *
     * @param string $fileName
     * @return string
     */
    public static function getCorrectFileName($fileName)
    {
        return $fileName;
    }
}
