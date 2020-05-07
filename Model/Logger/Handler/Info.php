<?php

namespace MagicToolbox\Sirv\Model\Logger\Handler;

/**
 * Info handler
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
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
