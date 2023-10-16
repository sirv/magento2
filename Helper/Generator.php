<?php

namespace Sirv\Magento2\Helper;

/**
 * Generator class helps in generating other classes during compilation.
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Generator
{
    /**
     * Generated flag
     *
     * @var bool
     */
    public $generated;

    /**
     * Constructor
     *
     * @param \Magento\Framework\FilesystemFactory $filesystemFactory
     * @return void
     */
    public function __construct(
        \Magento\Framework\FilesystemFactory $filesystemFactory
    ) {
        //NOTE: during compilation the Magento compiler will create all the necessary files in the 'generated' folder
        $this->generated = true;
    }
}
