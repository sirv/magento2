<?php

namespace Sirv\Magento2\Model\Image;

/**
 * Image factory
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
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
     * @return \Sirv\Magento2\Model\Image
     */
    public function create($fileName = null, $adapterName = null)
    {
        $adapter = $this->adapterFactory->create($adapterName);

        return $this->objectManager->create(
            \Sirv\Magento2\Model\Image::class,
            ['adapter' => $adapter, 'fileName' => $fileName]
        );
    }
}
