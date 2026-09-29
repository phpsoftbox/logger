<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Processor;

use PhpSoftBox\Logger\LogRecord;

use function array_map;
use function implode;
use function is_array;
use function is_string;
use function preg_quote;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function strtolower;

/**
 * Скрывает секреты в context и extra записи.
 *
 * - Ключ скрывается целиком, если его имя содержит одно из ключевых слов (`access_token`, `client_secret`,
 *   `X-Auth-Token`, `new_password` и т. п.).
 * - В строковых значениях скрываются пары вида `token=…`, `password: …`, `"secret":"…"`, `Authorization: Bearer …`
 *   (строка запроса, заголовок, фрагмент JSON).
 *
 * Сообщение не обрабатывается: секреты передавайте через context (`{password}`) — плейсхолдер подставляется уже
 * скрытым значением.
 */
final class RedactSecretsProcessor implements ProcessorInterface
{
    public const array DEFAULT_KEYS = ['password', 'passwd', 'token', 'secret', 'authorization', 'api_key', 'apikey', 'private_key', 'cookie'];

    /** @var list<string> */
    private array $keys;

    private ?string $valuePattern;

    /**
     * @param list<string> $keys Ключевые слова (подстроки имени ключа).
     */
    public function __construct(
        array $keys = self::DEFAULT_KEYS,
        private readonly string $replacement = '[REDACTED]',
        private readonly bool $caseInsensitive = true,
    ) {
        $this->keys = array_map(
            fn (string $key): string => $this->caseInsensitive ? strtolower($key) : $key,
            $keys,
        );

        $this->valuePattern = $this->keys === [] ? null : sprintf(
            '/((?:%s)[\w-]*"?\s*[=:]\s*"?)(?:(?:Bearer|Basic)\s+)?[^\s&"\',;]+/%s',
            implode('|', array_map(static fn (string $key): string => preg_quote($key, '/'), $this->keys)),
            $this->caseInsensitive ? 'i' : '',
        );
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record
            ->withContext($this->redactArray($record->context))
            ->withExtra($this->redactArray($record->extra));
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSecretKey($key)) {
                $data[$key] = $this->replacement;
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactArray($value);
                continue;
            }

            if (is_string($value) && $this->valuePattern !== null) {
                $data[$key] = preg_replace_callback(
                    $this->valuePattern,
                    fn (array $matches): string => $matches[1] . $this->replacement,
                    $value,
                ) ?? $value;
            }
        }

        return $data;
    }

    private function isSecretKey(string $key): bool
    {
        $key = $this->caseInsensitive ? strtolower($key) : $key;
        foreach ($this->keys as $secret) {
            if ($secret !== '' && str_contains($key, $secret)) {
                return true;
            }
        }

        return false;
    }
}
