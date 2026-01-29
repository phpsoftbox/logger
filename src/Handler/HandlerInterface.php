<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;

interface HandlerInterface
{
    public function isHandling(LogLevel $level): bool;

    public function handle(LogRecord $record): void;

    public function close(): void;
}
