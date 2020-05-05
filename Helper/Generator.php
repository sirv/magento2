<?php

namespace MagicToolbox\Sirv\Helper;

/**
 * Generator class helps in generating other classes during compilation.
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
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
