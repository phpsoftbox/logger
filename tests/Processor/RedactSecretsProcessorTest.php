<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Processor;

use DateTimeImmutable;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\RedactSecretsProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedactSecretsProcessor::class)]
#[CoversMethod(RedactSecretsProcessor::class, '__invoke')]
final class RedactSecretsProcessorTest extends TestCase
{
    /**
     * Проверим, что скрываются ключи, содержащие ключевое слово (access_token, client_secret, X-Auth-Token),
     * в том числе во вложенных массивах.
     *
     * @see RedactSecretsProcessor::__invoke()
     */
    #[Test]
    public function keysContainingSecretWordAreRedacted(): void
    {
        $record = $this->record([
            'access_token' => 'abc',
            'oauth'        => ['client_secret' => 'def'],
            'headers'      => ['X-Auth-Token' => 'ghi'],
            'user_id'      => 7,
        ]);

        $context = new RedactSecretsProcessor()($record)->context;

        self::assertSame([
            'access_token' => '[REDACTED]',
            'oauth'        => ['client_secret' => '[REDACTED]'],
            'headers'      => ['X-Auth-Token' => '[REDACTED]'],
            'user_id'      => 7,
        ], $context);
    }

    /**
     * Проверим, что значение секрета скрывается в строке запроса.
     *
     * @see RedactSecretsProcessor::__invoke()
     */
    #[Test]
    public function secretInQueryStringIsRedacted(): void
    {
        $record = $this->record(['url' => 'https://api.example.com/v1?access_token=abc123&page=2']);

        $context = new RedactSecretsProcessor()($record)->context;

        self::assertSame('https://api.example.com/v1?access_token=[REDACTED]&page=2', $context['url']);
    }

    /**
     * Проверим, что токен в заголовке Authorization скрывается вместе со схемой Bearer.
     *
     * @see RedactSecretsProcessor::__invoke()
     */
    #[Test]
    public function bearerAuthorizationIsRedacted(): void
    {
        $record = $this->record(['request' => 'GET /api Authorization: Bearer eyJhbGciOi.payload.sig']);

        $context = new RedactSecretsProcessor()($record)->context;

        self::assertSame('GET /api Authorization: [REDACTED]', $context['request']);
    }

    /**
     * Проверим, что секрет во фрагменте JSON скрывается.
     *
     * @see RedactSecretsProcessor::__invoke()
     */
    #[Test]
    public function secretInJsonFragmentIsRedacted(): void
    {
        $record = $this->record(['body' => '{"login":"admin","password":"qwerty"}']);

        $context = new RedactSecretsProcessor()($record)->context;

        self::assertSame('{"login":"admin","password":"[REDACTED]"}', $context['body']);
    }

    /**
     * Проверим, что обычный текст с упоминанием ключевого слова не искажается.
     *
     * @see RedactSecretsProcessor::__invoke()
     */
    #[Test]
    public function plainTextMentioningSecretWordIsKept(): void
    {
        $record = $this->record(['note' => 'Password reset token sent to user']);

        $context = new RedactSecretsProcessor()($record)->context;

        self::assertSame('Password reset token sent to user', $context['note']);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(array $context): LogRecord
    {
        return new LogRecord('info', LogLevel::Info, 'message', $context, new DateTimeImmutable());
    }
}
