<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use RuntimeException;

use function file_exists;
use function filesize;
use function rename;
use function sprintf;
use function unlink;

final class RotatingFileHandler extends StreamHandler
{
    public function __construct(
        private readonly string $path,
        private readonly int $maxFiles = 5,
        private readonly int $maxBytes = 1048576,
        LogLevel $level = LogLevel::Debug,
        FormatterInterface $formatter = new LineFormatter(),
        array $processors = [],
    ) {
        parent::__construct($path, 'a', $level, $formatter, $processors);
    }

    public function handle(LogRecord $record): void
    {
        $this->rotateIfNeeded();
        parent::handle($record);
    }

    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->path)) {
            return;
        }

        if (filesize($this->path) < $this->maxBytes) {
            return;
        }

        $this->close();

        for ($i = $this->maxFiles - 1; $i >= 0; --$i) {
            $source = $i === 0 ? $this->path : sprintf('%s.%d', $this->path, $i);
            $target = sprintf('%s.%d', $this->path, $i + 1);

            if (!file_exists($source)) {
                continue;
            }

            if ($i + 1 > $this->maxFiles) {
                unlink($source);
                continue;
            }

            if (!rename($source, $target)) {
                throw new RuntimeException(sprintf('Unable to rotate log file "%s"', $source));
            }
        }
    }
}
