<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Configurator;

use Psr\Log\LoggerInterface;

interface LoggerFactoryInterface
{
    public function create(string $channel = 'app'): LoggerInterface;
}
