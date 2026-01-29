<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;

use function closelog;
use function openlog;
use function syslog;

use const LOG_ALERT;
use const LOG_CONS;
use const LOG_CRIT;
use const LOG_DEBUG;
use const LOG_EMERG;
use const LOG_ERR;
use const LOG_INFO;
use const LOG_NOTICE;
use const LOG_PID;
use const LOG_USER;
use const LOG_WARNING;

final class SyslogHandler extends AbstractProcessingHandler
{
    private const DEFAULT_FACILITY = LOG_USER;

    /**
     * @param list<ProcessorInterface> $processors
     */
    public function __construct(
        private readonly string $ident = 'phpsoftbox',
        private readonly int $facility = self::DEFAULT_FACILITY,
        LogLevel $level = LogLevel::Debug,
        FormatterInterface $formatter = new LineFormatter('%message%'),
        array $processors = [],
    ) {
        parent::__construct($level, $formatter, $processors);
        openlog($this->ident, LOG_PID | LOG_CONS, $this->facility);
    }

    public function handle(LogRecord $record): void
    {
        if (!$this->isHandling($record->severity)) {
            return;
        }

        $processed = $this->processRecord($record);
        syslog($this->mapPriority($processed->severity), $this->formatter->format($processed));
    }

    public function close(): void
    {
        parent::close();
        closelog();
    }

    private function mapPriority(LogLevel $level): int
    {
        return match ($level) {
            LogLevel::Emergency => LOG_EMERG,
            LogLevel::Alert     => LOG_ALERT,
            LogLevel::Critical  => LOG_CRIT,
            LogLevel::Error     => LOG_ERR,
            LogLevel::Warning   => LOG_WARNING,
            LogLevel::Notice    => LOG_NOTICE,
            LogLevel::Info      => LOG_INFO,
            LogLevel::Debug     => LOG_DEBUG,
        };
    }
}
