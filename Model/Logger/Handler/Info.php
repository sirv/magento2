<?php

namespace MagicToolbox\Sirv\Model\Logger\Handler;

/**
 * Info handler
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Info extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * File name
     *
     * @var string
     */
    protected $fileName = '/var/log/sirv.log';

    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = \Magento\Framework\Logger\Monolog::INFO;
}
