<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Processor;

use PhpSoftBox\Logger\LogRecord;

interface ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord;
}
