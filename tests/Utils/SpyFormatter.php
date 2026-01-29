<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Utils;

use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\LogRecord;

use function sprintf;
use function strtoupper;

final class SpyFormatter implements FormatterInterface
{
    /** @var list<string> */
    public array $formattedMessages = [];

    /** @var list<LogRecord> */
    public array $records = [];

    public function format(LogRecord $record): string
    {
        $this->records[] = $record;
        $formatted       = sprintf(
            '%s|%s|%s',
            $record->datetime->format('H:i:s'),
            strtoupper($record->level),
            $record->message,
        );

        $this->formattedMessages[] = $formatted;

        return $formatted;
    }
}
