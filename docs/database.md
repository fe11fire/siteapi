# База данных

[Обзор проекта](../readme.md)

DBHelper — небольшой слой над PDO для MySQL, без ORM и миграций схемы. Соединение создаётся при первом запросе или начале транзакции и используется до завершения обычного HTTP-запроса.

## Подключение

Настройки db.json или окружение DB_SERVER, DB_NAME, DB_USER, DB_PASSWORD:

```json
{
    "server": "127.0.0.1",
    "name": "my_site",
    "user": "my_site",
    "password": "..."
}
```

DSN использует utf8mb4, исключения PDO, ассоциативную выборку и отключённую эмуляцию prepared statements. Изменение Settings после подключения не переключает соединение. Автоматических переподключений и повторов нет.

## Прямой запрос

```php
<?php
use SiteApi\Root\Helpers\DBHelper;

$users = DBHelper::universal(
    'SELECT id, name FROM users WHERE enabled = ? AND age >= ?',
    [true, 18],
    fetch: true,
);
```

Аргументы — позиционный список. Возвращается массив строк при fetch=true, lastInsertId при last_id=true, иначе пустой массив. Результаты SELECT полностью считываются в PHP и остаются независимыми от следующих запросов.

| PHP-тип | Тип PDO |
|---|---|
| null | PARAM_NULL |
| bool | PARAM_BOOL |
| int | PARAM_INT |
| string / float | PARAM_STR |

У PDO нет PARAM_FLOAT. Строки `false` и `025` остаются строками. Массивы, объекты и ресурсы как одно значение отклоняются. JSON кодируйте явно до вызова.

## Методы выборки

```php
<?php
DBHelper::select('id, name', ['users'], '', ['enabled' => true], 'ORDER BY id', $rows);

$found = DBHelper::selectExactlyOne(
    'id, name', ['users'], '', ['id' => 42], '', $user,
);
```

selectExactlyOne(): одна строка → true и строка; ноль → false и []; больше одной → UnexpectedValueException и []. LIMIT 1 скрывает неоднозначность, поэтому не добавляйте его при проверке уникальности. Уникальность данных лучше закрепить ограничением БД.

selectCount() считает строки исходного SELECT на сервере через обёртку COUNT(*) над подзапросом. Сохраняет GROUP BY, HAVING, DISTINCT, LIMIT и OFFSET. Это не обязательно число всех записей для пагинации.

MySQL требует уникальных имён колонок подзапроса: при JOIN используйте `orders.id AS order_id, users.id AS user_id`, а не `*` с дублирующимися именами.

## Условия

```php
<?php
use SiteApi\Root\Helpers\RawCondition;

$where = [
    'enabled' => true,
    'deleted_at' => null,
    'age' => [18, '>='],
    'verified_at' => [null, 'IS NOT NULL'],
    new RawCondition('updated_at > created_at AND score >= ?', [10]),
];
```

Обычные условия объединяются AND. Null означает IS NULL и не добавляет параметр. Для null разрешены только IS NULL / IS NOT NULL. RawCondition содержит доверенную SQL-структуру и позиционные bindings.

В старом сборщике также сохранён вложенный формат оператора для группировки. Для новых сложных условий предпочтительнее явный RawCondition с параметрами. Имена таблиц, колонок, операторы и части beforeWhere/afterWhere не являются пользовательскими значениями и не экранируются автоматически.

## Изменение данных

```php
<?php
DBHelper::insert('users', ['name' => 'Иван', 'enabled' => false], $id);
DBHelper::update('users', ['enabled' => true], ['id' => $id]);
DBHelper::delete('users', ['id' => $id]);
```

Пустое where не запрещено: update/delete могут затронуть всю таблицу. Ограничение области изменения — ответственность приложения. Не передавайте пользовательские данные как имена полей или SQL-операторы.

## Транзакции

```php
<?php
DBHelper::beginTransaction();
try {
    DBHelper::universal('UPDATE accounts SET balance = balance - ? WHERE id = ?', [100, 1]);
    DBHelper::universal('UPDATE accounts SET balance = balance + ? WHERE id = ?', [100, 2]);
    DBHelper::commit();
} catch (Throwable $error) {
    if (DBHelper::inTransaction()) DBHelper::rollBack();
    throw $error;
}
```

Вложенных транзакций нет. inTransaction() не создаёт соединение. Используйте транзакционные таблицы, например InnoDB; DDL MySQL может автоматически завершать транзакцию. Одно соединение не предотвращает конкуренцию разных посетителей за одну запись — нужны соответствующие ограничения и блокировки БД.

## Ошибки и журнал

Исключения передаются без замены на общий Exception. Обычная запись содержит операцию и количество параметров, без значений. При debug=true добавляется SQL, но не bindings. Литералы, вставленные непосредственно в SQL, будут видны в debug-журнале.

DatabaseErrorContext оставляет тип PDOException, SQLSTATE и числовой код драйвера, исключая сообщение и errorInfo[2]. ErrorHandler использует ту же политику, включая цепочку previous. Отказ логгера не превращает успешную запись в ошибку БД; безопасные метаданные уходят в error_log().

[Проверки PDO и журналов](testing.md)
