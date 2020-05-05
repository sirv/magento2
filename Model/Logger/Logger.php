<?php

namespace MagicToolbox\Sirv\Model\Logger;

/**
 * Sirv logger
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
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
