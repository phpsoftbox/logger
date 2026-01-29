<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Utils;

use PhpSoftBox\Logger\Handler\HandlerInterface;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;

final class SpyHandler implements HandlerInterface
{
    /** @var list<LogRecord> */
    public array $handled = [];

    public function isHandling(LogLevel $level): bool
    {
        return true;
    }

    public function handle(LogRecord $record): void
    {
        $this->handled[] = $record;
    }

    public function close(): void
    {
    }
}
