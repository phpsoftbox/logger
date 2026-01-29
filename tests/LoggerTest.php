<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests;

use PhpSoftBox\Logger\Handler\HandlerInterface;
use PhpSoftBox\Logger\Logger;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;
use PhpSoftBox\Logger\Processor\RedactSecretsProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;

#[CoversClass(Logger::class)]
final class LoggerTest extends TestCase
{
    /**
     * Проверяет, что логгер создаёт дефолтный StreamHandler и имя канала.
     */
    #[Test]
    public function defaultHandlerIsStreamHandler(): void
    {
        $logger = new Logger();

        self::assertCount(1, $logger->getHandlers());
        self::assertSame('app', $logger->getName());
    }

    /**
     * Проверяет, что withChannel возвращает клон с новым именем.
     */
    #[Test]
    public function withChannelReturnsClonedLogger(): void
    {
        $logger = new Logger('default');

        $custom = $logger->withChannel('custom');

        self::assertNotSame($logger, $custom);
        self::assertSame('default', $logger->getName());
        self::assertSame('custom', $custom->getName());
    }

    /**
     * Проверяет валидацию имени канала.
     */
    #[Test]
    public function invalidChannelNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Logger()->withChannel('');
    }

    /**
     * Убеждаемся, что контекст интерполируется в сообщение.
     */
    #[Test]
    public function logInterpolatesContext(): void
    {
        $handler = new class () implements HandlerInterface {
            public ?LogRecord $handled = null;

            public function isHandling(LogLevel $level): bool
            {
                return true;
            }

            public function handle(LogRecord $record): void
            {
                $this->handled = $record;
            }

            public function close(): void
            {
            }
        };

        $logger = new Logger(handlers: [$handler]);

        $logger->info('User {user} logged in', ['user' => 'Alice']);

        self::assertNotNull($handler->handled);
        self::assertSame('User Alice logged in', $handler->handled->message);
    }

    /**
     * Проверяет, что процессор редактирует контекст, но не меняет шаблон сообщения.
     */
    #[Test]
    public function processorsDoNotAlterMessageTemplate(): void
    {
        $handler = new class () implements HandlerInterface {
            public ?LogRecord $handled = null;

            public function isHandling(LogLevel $level): bool
            {
                return true;
            }

            public function handle(LogRecord $record): void
            {
                $this->handled = $record;
            }

            public function close(): void
            {
            }
        };

        $logger = new Logger(handlers: [$handler], processors: [new RedactSecretsProcessor(replacement: '[HIDDEN]')]);

        $logger->alert('My password is {password}', ['password' => 'secret']);

        self::assertSame('My password is [HIDDEN]', $handler->handled?->message);
        self::assertSame('[HIDDEN]', $handler->handled?->context['password']);
    }

    /**
     * Проверяет, что глобальные процессоры изменяют запись до обработчиков.
     */
    #[Test]
    public function processorsAreAppliedBeforeHandlers(): void
    {
        $processor = new class () implements ProcessorInterface {
            public function __invoke(LogRecord $record): LogRecord
            {
                return $record->withMessage('processed');
            }
        };

        $handler = new class () implements HandlerInterface {
            public ?LogRecord $handled = null;

            public function isHandling(LogLevel $level): bool
            {
                return true;
            }

            public function handle(LogRecord $record): void
            {
                $this->handled = $record;
            }

            public function close(): void
            {
            }
        };

        $logger = new Logger(handlers: [$handler], processors: [$processor]);

        $logger->info('original');

        self::assertSame('processed', $handler->handled?->message);
    }

    /**
     * Проверяет, что неверный уровень вызывает исключение.
     */
    #[Test]
    public function invalidLevelThrows(): void
    {
        $logger = new Logger();
        $this->expectException(InvalidArgumentException::class);
        $logger->log('invalid', 'test');
    }

    /**
     * Проверяет фильтрацию обработчиком по уровню серьёзности.
     */
    #[Test]
    public function handlerRespectsSeverity(): void
    {
        $handler = new class () implements HandlerInterface {
            public int $calls = 0;

            public function isHandling(LogLevel $level): bool
            {
                return $level->value <= LogLevel::Error->value;
            }

            public function handle(LogRecord $record): void
            {
                ++$this->calls;
            }

            public function close(): void
            {
            }
        };

        $logger = new Logger(handlers: [$handler]);

        $logger->info('skip');
        $logger->error('logged');

        self::assertSame(1, $handler->calls);
    }
}
