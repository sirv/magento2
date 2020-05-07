<?php

namespace MagicToolbox\Sirv\Model\Logger;

/**
 * Sirv logger
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2020 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Logger extends \Magento\Framework\Logger\Monolog
{
    /**
     * Constructor
     *
     * @param string             $name       The logging channel
     * @param HandlerInterface[] $handlers   Optional stack of handlers, the first one in the array is called first, etc.
     * @param callable[]         $processors Optional array of processors
     * @return void
     */
    public function __construct(
        $name,
        array $handlers = [],
        array $processors = []
    ) {
        $handlers = [$handlers['sirv']];
        parent::__construct($name, $handlers, $processors);
    }
}
