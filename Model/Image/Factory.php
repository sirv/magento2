<?php

namespace MagicToolbox\Sirv\Model\Image;

/**
 * Image factory
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
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
