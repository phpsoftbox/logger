# PhpSoftBox Logger

PSR-3 совместимый логгер с модульной архитектурой для проектов PhpSoftBox. Поддерживает настраиваемые обработчики, форматтеры и процессоры, строгую типизацию и единый конфигуратор.

## Возможности
- Реализация `Psr\Log\LoggerInterface`.
- Обработчики: Stream, Buffer, Console, RotatingFile, Syslog, InMemory (тестовый), Null.
- Форматтеры: Line, JSON.
- Процессоры: глобальные и локальные, встроен `RedactSecretsProcessor`.
- Конфигуратор `LoggerFactory` создаёт каналы из PHP-конфигураций.

## Установка
```bash
composer require phpsoftbox/logger
```

Пакет объявляет `provide: psr/log-implementation` — удовлетворяет зависимостям, требующим реализацию PSR-3.

## Быстрый пример
```php
use PhpSoftBox\Logger\Logger;
use PhpSoftBox\Logger\Handler\StreamHandler;
use PhpSoftBox\Logger\Processor\RedactSecretsProcessor;

$logger = new Logger(
    name: 'api',
    handlers: [new StreamHandler(__DIR__.'/var/log/api.log')],
    processors: [new RedactSecretsProcessor()],
);

$logger->info('User {user} logged in', ['user' => 'Alice', 'password' => 'secret']);
```

## Конфигуратор
```php
use PhpSoftBox\Logger\Configurator\LoggerFactory;
use PhpSoftBox\Logger\Handler\StreamHandler;
use PhpSoftBox\Logger\Handler\NullHandler;

$factory = new LoggerFactory([
    'channels' => [
        'default' => [
            'name' => 'app',
            'processors' => [\PhpSoftBox\Logger\Processor\RedactSecretsProcessor::class],
            'handlers' => [
                new StreamHandler(__DIR__ . '/var/log/app.log'),
                new NullHandler(),
                [
                    'type' => 'buffer',
                    'buffer_size' => 100,
                    'handler' => new StreamHandler(__DIR__ . '/var/log/buffered.log'),
                ],
            ],
        ],
    ],
]);

$logger = $factory->create('default');
```

## HTTP middleware

```php
use PhpSoftBox\Logger\LoggerMiddleware;

$middleware = new LoggerMiddleware($logger);
```

## Интеграция с DI (пример PHP-DI)
```php
use DI\ContainerBuilder;
use PhpSoftBox\Logger\Configurator\LoggerFactory;
use PhpSoftBox\Logger\Configurator\LoggerFactoryInterface;
use PhpSoftBox\Logger\Handler\StreamHandler;
use PhpSoftBox\Logger\Logger;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    LoggerFactoryInterface::class => static function (): LoggerFactoryInterface {
        return new LoggerFactory([
            'channels' => [
                'default' => [
                    'handlers' => [new StreamHandler(__DIR__.'/var/log/app.log')],
                ],
            ],
        ]);
    },
    Logger::class => static function (LoggerFactoryInterface $factory): Logger {
        return $factory->create('default');
    },
    'logger.audit' => static function (Logger $logger): Logger {
        return $logger->withChannel('audit');
    },
]);

$container = $builder->build();
$appLogger = $container->get(Logger::class);
$auditLogger = $container->get('logger.audit');
```

## Форматтеры и контекст

`LineFormatter` и `JsonFormatter` приводят context/extra через `ContextNormalizer`, поэтому запись в лог не падает
из-за содержимого контекста:

- некорректный UTF-8 заменяется на `U+FFFD`, `NAN`/`INF` — строками `"NAN"`/`"INF"`;
- исключение — `class`, `message`, `code`, `file`, `line`, `trace` и цепочка `previous`;
- `DateTimeInterface` — строка RFC 3339, `JsonSerializable` — результат `jsonSerialize()`, enum — значение (имя для
  чистого enum), `Stringable` — строка, прочие объекты — `[object Class]`, ресурсы — `[resource type]`;
- вложенность глубже 9 уровней обрезается до `[max depth]`.

Конфигурация форматтера в `LoggerFactory`:

```php
'formatter' => 'line',                                        // LineFormatter::DEFAULT_FORMAT
'formatter' => ['type' => 'line', 'format' => '...', 'date_format' => DATE_ATOM, 'stacktrace_multiline' => true],
'formatter' => ['type' => 'json', 'format' => '...', 'flags' => JSON_UNESCAPED_UNICODE],
```

Формат строки по умолчанию одинаков для `'line'` и массива без `format`:
`[%datetime%] %level_name%: %message% %context% %extra%`.

## RedactSecretsProcessor

- Ключ context/extra скрывается целиком, если его имя **содержит** одно из ключевых слов
  (`RedactSecretsProcessor::DEFAULT_KEYS`: `password`, `passwd`, `token`, `secret`, `authorization`, `api_key`,
  `apikey`, `private_key`, `cookie`), без учёта регистра: `access_token`, `client_secret`, `X-Auth-Token`.
- В строковых значениях скрываются пары `ключ=значение` / `ключ: значение` / `"ключ":"значение"` и
  `Authorization: Bearer …` — строка запроса, заголовок, фрагмент JSON. Обычный текст не меняется.
- Сообщение не обрабатывается: передавайте секреты через плейсхолдеры (`'{password}'`) — процессоры выполняются до
  интерполяции, и подставляется уже скрытое значение.

## Файлы и ротация

- `StreamHandler` открывает файл один раз (режим `a`, `fflush` после каждой записи). При внешней ротации
  (logrotate) в долгоживущих процессах используйте `copytruncate`, иначе запись продолжится в переименованный файл.
- `RotatingFileHandler` ротирует по размеру (`maxBytes`, хранит `maxFiles` архивов `path.1…path.N`). Если файл уже
  ротировал другой процесс (FPM, несколько воркеров с одним логом), обработчик замечает это перед записью и
  переоткрывает файл по исходному пути.

## Долгоживущие процессы

`BufferHandler` без вложенного обработчика и с `buffer_size: 0` и `InMemoryHandler` накапливают записи без предела.
В воркерах очищайте их после задачи/запроса через хуки `ServicesResetter`
(`['logger.buffer' => 'clear']`) или задавайте `buffer_size`.

## BufferHandler
- `buffer_size`: 0 = без лимита.
- `flush_on_overflow`: true — сбрасывает при переполнении, false — удаляет старейшую запись.
- Методы `flush()`, `drain()`, `clear()` позволяют контролировать буфер вручную.

## LineFormatter
По умолчанию `LineFormatter` выводит stack trace в несколько строк (удобно для чтения).
Это поведение можно отключить:

```php
use PhpSoftBox\Logger\Formatter\LineFormatter;

$formatter = new LineFormatter(stacktraceMultiline: false);
```

Через конфиг `LoggerFactory`:

```php
[
    'type' => 'line',
    'stacktrace_multiline' => false,
]
```

## Использование LogRecord::withChannel/withDatetime
Процессоры и обработчики должны возвращать новые экземпляры `LogRecord` при изменении канала или времени записи. Методы `withChannel()` и `withDatetime()` гарантируют неизменяемость и корректную передачу данных по цепочке.

## Тесты
```bash
composer install
./vendor/bin/phpunit
```

## Лицензия
MIT
