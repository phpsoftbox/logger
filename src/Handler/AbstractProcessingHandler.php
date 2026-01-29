<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;

abstract class AbstractProcessingHandler implements HandlerInterface
{
    /**
     * @param list<ProcessorInterface> $processors
     */
    public function __construct(
        protected LogLevel $level = LogLevel::Debug,
        protected FormatterInterface $formatter = new LineFormatter(),
        protected array $processors = [],
    ) {
    }

    public function isHandling(LogLevel $level): bool
    {
        return $level->value <= $this->level->value;
    }

    public function setFormatter(FormatterInterface $formatter): void
    {
        $this->formatter = $formatter;
    }

    public function pushProcessor(ProcessorInterface $processor): void
    {
        $this->processors[] = $processor;
    }

    protected function processRecord(LogRecord $record): LogRecord
    {
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }

        return $record;
    }

    public function close(): void
    {
    }
}
