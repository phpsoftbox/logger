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
