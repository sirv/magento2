<?php

namespace MagicToolbox\Sirv\Model\Image;

/**
 * Image factory
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Factory extends \Magento\Framework\Image\Factory
{
    /**
     * Create instance
     *
     * @param string|null $fileName
     * @param string|null $adapterName
     * @return \MagicToolbox\Sirv\Model\Image
     */
    public function create($fileName = null, $adapterName = null)
    {
        $adapter = $this->adapterFactory->create($adapterName);

        return $this->objectManager->create(
            \MagicToolbox\Sirv\Model\Image::class,
            ['adapter' => $adapter, 'fileName' => $fileName]
        );
    }
}
