<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger;

use Psr\Log\InvalidArgumentException;

use function sprintf;

enum LogLevel: int
{
    case Emergency = 0;
    case Alert     = 1;
    case Critical  = 2;
    case Error     = 3;
    case Warning   = 4;
    case Notice    = 5;
    case Info      = 6;
    case Debug     = 7;

    public static function fromPsrLevel(string $level): self
    {
        return match ($level) {
            'emergency' => self::Emergency,
            'alert'     => self::Alert,
            'critical'  => self::Critical,
            'error'     => self::Error,
            'warning'   => self::Warning,
            'notice'    => self::Notice,
            'info'      => self::Info,
            'debug'     => self::Debug,
            default     => throw new InvalidArgumentException(sprintf('Unknown log level "%s"', $level)),
        };
    }

    public function toPsrLevel(): string
    {
        return match ($this) {
            self::Emergency => 'emergency',
            self::Alert     => 'alert',
            self::Critical  => 'critical',
            self::Error     => 'error',
            self::Warning   => 'warning',
            self::Notice    => 'notice',
            self::Info      => 'info',
            self::Debug     => 'debug',
        };
    }
}
