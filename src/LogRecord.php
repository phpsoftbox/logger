<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger;

use DateTimeImmutable;

final class LogRecord
{
    public function __construct(
        public readonly string $level,
        public readonly LogLevel $severity,
        public readonly string $message,
        /** @var array<string, mixed> */
        public readonly array $context,
        public readonly DateTimeImmutable $datetime,
        /** @var array<string, mixed> */
        public readonly array $extra = [],
        public readonly ?string $channel = null,
    ) {
    }

    /**
     * Возвращает новую запись с обновлённым набором контекста.
     *
     * @param array<string, mixed> $context Новые значения контекста.
     */
    public function withContext(array $context): self
    {
        return new self(
            level: $this->level,
            severity: $this->severity,
            message: $this->message,
            context: $context,
            datetime: $this->datetime,
            extra: $this->extra,
            channel: $this->channel,
        );
    }

    /**
     * Возвращает новую запись с изменённым сообщением.
     */
    public function withMessage(string $message): self
    {
        return new self(
            level: $this->level,
            severity: $this->severity,
            message: $message,
            context: $this->context,
            datetime: $this->datetime,
            extra: $this->extra,
            channel: $this->channel,
        );
    }

    /**
     * Возвращает новую запись с обновлённым набором extra-полей.
     *
     * @param array<string, mixed> $extra Произвольные дополнительные данные.
     */
    public function withExtra(array $extra): self
    {
        return new self(
            level: $this->level,
            severity: $this->severity,
            message: $this->message,
            context: $this->context,
            datetime: $this->datetime,
            extra: $extra,
            channel: $this->channel,
        );
    }

    /**
     * Возвращает новую запись с другим каналом.
     */
    public function withChannel(?string $channel): self
    {
        return new self(
            level: $this->level,
            severity: $this->severity,
            message: $this->message,
            context: $this->context,
            datetime: $this->datetime,
            extra: $this->extra,
            channel: $channel,
        );
    }

    /**
     * Возвращает новую запись с изменённым временем.
     */
    public function withDatetime(DateTimeImmutable $datetime): self
    {
        return new self(
            level: $this->level,
            severity: $this->severity,
            message: $this->message,
            context: $this->context,
            datetime: $datetime,
            extra: $this->extra,
            channel: $this->channel,
        );
    }
}
