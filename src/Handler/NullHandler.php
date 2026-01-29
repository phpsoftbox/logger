<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;

final class NullHandler extends AbstractProcessingHandler
{
    /**
     * @param list<ProcessorInterface> $processors
     */
    public function __construct(
        LogLevel $level = LogLevel::Debug,
        FormatterInterface $formatter = new LineFormatter(),
        array $processors = [],
    ) {
        parent::__construct($level, $formatter, $processors);
    }

    public function handle(LogRecord $record): void
    {
        if (!$this->isHandling($record->severity)) {
            return;
        }

        $this->processRecord($record);
    }
}
