<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;

use function array_map;

final class InMemoryHandler extends AbstractProcessingHandler
{
    /** @var list<LogRecord> */
    private array $records = [];

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

        $this->records[] = $this->processRecord($record);
    }

    /**
     * @return list<LogRecord>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @return list<string>
     */
    public function getFormatted(): array
    {
        return array_map(fn (LogRecord $record): string => $this->formatter->format($record), $this->records);
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
