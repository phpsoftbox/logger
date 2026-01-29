<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Formatter;

use PhpSoftBox\Logger\LogRecord;

interface FormatterInterface
{
    public function format(LogRecord $record): string;
}
