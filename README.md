# Тестовое задание РСО «Евроинс»: Расчет стоимости страховки ВЗР

Проект представляет собой API для расчета стоимости страховки для выезжающих за рубеж (ВЗР).

## Требования

Для запуска проекта необходимы:
- **Docker Engine** (версия ≥ 20.10)
- **Docker Compose** (в современных версиях Docker встроен как плагин `docker compose`)

---

## 1. Как запустить проект

1. Склонируйте репозиторий:
   ```bash
   git clone git@github.com:ladovod444/travel-ensurance-euroi.git
   ```

2. Перейдите в папку проекта и запустите контейнеры:
   ```bash
   cd travel-ensurance-euroi
   docker compose up -d
   ```

3. Установите зависимости Composer:
   ```bash
   docker compose exec php composer install
   ```

## 2. Стек технологий

### Backend: PHP 8.4, Symfony
### Библиотеки и компоненты:
1. `symfony/validator` - валидация входных данных на основе PHP-атрибутов (`#[Assert\...]`).
2. `symfony/serializer-pack` - преобразование входящего JSON в DTO и сериализация объектов ответа в JSON.
3. `symfony/http-client` - выполнение HTTP-запросов к внешнему API.
4. `symfony/monolog-bundle` - логирование ошибок приложения и сбоев при обращении к внешним сервисам.
5. `phpunit/phpunit` - запуск модульных и функциональных тестов.

#### Обеспечивают работу `#[MapRequestPayload]` и валидации в контроллере:
6. `symfony/property-access` - чтение и запись значений свойств DTO при десериализации и валидации.
7. `symfony/property-info` - определение типов свойств DTO для корректной денормализации JSON.
8. `symfony/type-info` - разбор современных PHP-типов (nullable, union) при маппинге данных.

#### Инструменты качества кода:
9. `friendsofphp/php-cs-fixer` - автоматическая проверка и приведение кода к стандартам PSR-12 и Symfony.


## 3. Архитектура проекта

Проект построен с соблюдением принципов **Clean Architecture** и **SOLID**, что обеспечивает высокую тестируемость, расширяемость и чистоту кода. Приложение разделено на следующие логические слои:

### 1. API Layer (`src/Controller/`, `src/EventListener/`)
Отвечает исключительно за обработку HTTP-запросов и формирование HTTP-ответов. Контроллеры сделаны максимально «тонкими» (Thin Controllers).
* **Ключевая особенность:** Использование атрибута Symfony `#[MapRequestPayload]`. Он автоматически десериализует входящий JSON в DTO и запускает валидацию на основе правил, описанных в атрибутах `#[Assert\...]`. Это избавляет контроллер от ручного вызова Serializer и Validator, делая код чище и декларативнее.
* **Обработка ошибок:** Глобальный `ApiExceptionListener` перехватывает исключения и гарантирует, что клиент всегда получит корректный JSON-ответ с соответствующим HTTP-статусом (`422` для ошибок валидации, `400` для невалидного JSON и неподдерживаемых параметров, `502` для сбоев внешних API), а не HTML-страницу ошибки Symfony.

### 2. Domain / Application Layer (`src/Dto/`, `src/Service/`, `src/ValueObject/`)
Ядро бизнес-логики приложения, полностью изолированное от фреймворка и HTTP-контекста.

* **Value Objects (Объекты-значения):** Инкапсулируют бизнес-правила и гарантируют, что объект не может находиться в невалидном состоянии. Создаются с валидацией в конструкторе:
    * `DateRange`: Проверяет, что дата начала не позже даты окончания, и автоматически рассчитывает количество дней поездки.
    * `InsuranceSum`: Валидирует допустимые страховые суммы и инкапсулирует знание о соответствующих коэффициентах (например, `30 000` → `0.6`, `50 000` → `0.8`).
* **DTO (Data Transfer Objects):** Неизменяемые (`readonly` классы) объекты, служащие строгими контрактами для передачи данных между слоями:
    * `InsuranceCalculationRequest`: Описывает структуру входящих данных.
    * `InsuranceCalculationResponse`: Описывает структуру исходящих данных. Содержит фабричный метод `fromCalculation()`, который гарантирует корректное математическое округление денежных значений перед сериализацией в JSON.
* **Service (`InsurancePremiumCalculator`):** Содержит чистую бизнес-логику расчета. Он не зависит от HTTP-классов Symfony (`Request` или `Response`), принимает DTO и Value Objects, использует интерфейсы инфраструктуры для получения данных и возвращает результирующий DTO.

### 3. Infrastructure Layer (`src/Infrastructure/`)
Отвечает за реализацию внешних зависимостей и взаимодействие с внешним миром. Построен на принципе **Dependency Inversion** (зависимость от абстракций, а не от реализаций).
* **`ExchangeRateProviderInterface`:** Контракт (интерфейс), определяющий способ получения курса валют. Бизнес-логика зависит только от него.
* **`CbrXmlExchangeRateProvider`:** Конкретная реализация интерфейса для работы с XML-фидом Центробанка РФ.
    * Самостоятельно обрабатывает особенности внешнего API: добавляет `User-Agent`, конвертирует кодировку ответа (`Windows-1251` в `UTF-8`) и учитывает номинал валюты.
    * В случае сбоев выбрасывает доменные исключения (`ExchangeRateException`), которые корректно обрабатываются на уровне API Layer.
    * *Расширяемость:* Благодаря интерфейсу добавление нового провайдера (например, альтернативного API или заглушки для тестов) не потребует изменения бизнес-логики (принцип Open/Closed).

## 4. Дерево файлов

```text
src/
├── Controller/
│   └── InsuranceCalculationController.php    # Тонкий контроллер (Single Action)
├── Dto/
│   ├── Request/
│   │   └── InsuranceCalculationRequest.php   # Входящие данные + Symfony Validator
│   └── Response/
│       └── InsuranceCalculationResponse.php  # Исходящие данные (с автоматическим округлением)
├── EventListener/
│   └── ApiExceptionListener.php              # Глобальный перехватчик для возврата JSON при ошибках
├── Infrastructure/
│   └── ExchangeRate/
│       ├── CbrXmlExchangeRateProvider.php    # Реализация получения курса с ЦБ РФ
│       ├── ExchangeRateProviderInterface.php # Контракт (Interface) для провайдера
│       └── Exceptions/                       # Кастомные исключения (ExchangeRateException и др.)
├── Service/
│   └── InsurancePremiumCalculator.php        # Ядро бизнес-логики расчета
└── ValueObject/
    ├── DateRange.php                         # Инкапсуляция логики дат и расчета дней
    └── InsuranceSum.php                      # Инкапсуляция логики страховых сумм и коэффициентов
```

## 5. Реализация API

### Описание эндпоинта
**POST `/api/insurance/calculate`** - рассчитывает стоимость страховки на основе переданных параметров.

#### Пример успешного запроса (cURL)
```bash
curl -X POST http://localhost:8080/api/insurance/calculate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "insuranceSum": 30000,
    "startDate": "2026-11-18",
    "endDate": "2026-11-20",
    "currencyCode": "EUR"
  }'
```

#### Пример успешного ответа (`200 OK`)
```json
{
    "totalCostInCurrency": 1.8,
    "totalCostInRubles": 163.86,
    "tripDays": 3,
    "dailyCoefficient": 0.6,
    "currencyRate": 91.0322,
    "insuranceSum": 30000
}
```

#### Пример запроса с ошибками валидации
```bash
curl -X POST http://localhost:8080/api/insurance/calculate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "insuranceSum": -30000,
    "startDate": "2026-11-26",
    "endDate": "2026-11-21",
    "currencyCode": "EURR"
  }'
```

#### Пример ответа с ошибками (`422 Unprocessable Entity`)
```json
{
    "error": "Ошибки валидации",
    "violations": {
        "startDate": [
            "Дата начала не может быть позже даты окончания"
        ],
        "insuranceSum": [
            "Страховая сумма должна быть положительным числом"
        ],
        "currencyCode": [
            "Код валюты должен состоять ровно из 3 символов",
            "Код валюты должен содержать только 3 заглавные латинские буквы (например, EUR, USD)"
        ]
    }
}
```

## 6. Запуск тестов

Проект покрыт Unit-тестами (для Value Objects, DTO, провайдера курсов и сервиса расчета) и Functional-тестами (для контроллера и проверки HTTP-статусов).

Для запуска всех тестов выполните:
```bash
docker compose exec php php bin/phpunit
```

## 7. Проверка и исправление стиля кода (PHP CS Fixer)

Проект следует стандартам **PSR-12** и **Symfony**.

* Проверить код на соответствие стандартам (`dry-run`):
  ```bash
  docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
  ```

* Автоматически исправить нарушения стиля кода:
  ```bash
  docker compose exec php vendor/bin/php-cs-fixer fix
  ```
