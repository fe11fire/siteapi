<?php
/** Real PDO/SQLite tests for connection reuse and transaction semantics; no MySQL required. */
require dirname(__DIR__) . '/src/Root/Helpers/DBHelper.php';
require dirname(__DIR__) . '/src/Root/Helpers/RawCondition.php';
use SiteApi\Root\Helpers\DBHelper;
function check(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
$connection = new ReflectionProperty(DBHelper::class, 'connection');
check(!DBHelper::inTransaction(), 'Checking state must not create a connection');
$pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$connection->setValue(null, $pdo);
try {
    DBHelper::universal('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
    DBHelper::universal('INSERT INTO items (name) VALUES (?)', ['original']);
    $earlier = DBHelper::universal('SELECT * FROM items', fetch: true);
    DBHelper::beginTransaction();
    check(DBHelper::inTransaction(), 'Transaction started');
    DBHelper::universal('UPDATE items SET name = ?', ['temporary']);
    check(DBHelper::universal('SELECT name FROM items', fetch: true)[0]['name'] === 'temporary', 'Read own changes');
    DBHelper::rollBack();
    check(!DBHelper::inTransaction(), 'Rollback ended transaction');
    check(DBHelper::universal('SELECT name FROM items', fetch: true)[0]['name'] === 'original', 'Rollback restored data');
    DBHelper::beginTransaction();
    DBHelper::universal('UPDATE items SET name = ?', ['committed']);
    DBHelper::commit();
    check(DBHelper::universal('SELECT name FROM items', fetch: true)[0]['name'] === 'committed', 'Commit persisted data');
    check($earlier[0]['name'] === 'original', 'Earlier result remains independent');
    check($connection->getValue() === $pdo, 'Same PDO reused');
    DBHelper::beginTransaction();
    try {
        DBHelper::beginTransaction();
        throw new RuntimeException('Nested transaction should be rejected');
    } catch (PDOException) {}
    DBHelper::rollBack();
    DBHelper::universal('CREATE TABLE nullable_items (id INTEGER PRIMARY KEY, deleted_at TEXT, age INTEGER)');
    DBHelper::universal('INSERT INTO nullable_items VALUES (1, NULL, 20), (2, ?, 30)', ['2026-01-01']);
    DBHelper::select('*', ['nullable_items'], '', ['deleted_at' => null, 'age' => [18, '>=']], '', $rows);
    check(count($rows) === 1 && $rows[0]['id'] === 1, 'NULL with regular comparison');
    DBHelper::select('*', ['nullable_items'], '', ['deleted_at' => [null, 'IS NOT NULL']], '', $rows);
    check(count($rows) === 1 && $rows[0]['id'] === 2, 'IS NOT NULL');
    DBHelper::select('*', ['nullable_items'], '', [new \SiteApi\Root\Helpers\RawCondition('age > ? OR id = ?', [25, 99]), 'deleted_at' => [null, 'IS NOT NULL']], '', $rows);
    check(count($rows) === 1 && $rows[0]['id'] === 2, 'Raw condition with positional values');
    DBHelper::update('nullable_items', ['age' => 21], ['deleted_at' => null], false);
    check(DBHelper::universal('SELECT age FROM nullable_items WHERE id = 1', fetch: true)[0]['age'] === 21, 'UPDATE NULL condition');
    DBHelper::delete('nullable_items', ['deleted_at' => [null, 'IS NOT NULL']], false);
    check(count(DBHelper::universal('SELECT * FROM nullable_items', fetch: true)) === 1, 'DELETE NOT NULL condition');
    DBHelper::universal('CREATE TABLE orders (id INTEGER PRIMARY KEY, status TEXT)');
    DBHelper::universal("INSERT INTO orders VALUES (1, 'new'), (2, 'new'), (3, 'paid')");
    foreach ([
        ['*', [], '', 3],
        ['*', ['status' => 'new'], '', 2],
        ['*', ['status' => 'missing'], '', 0],
        ['status', [], 'GROUP BY status', 2],
        ['status, COUNT(*) AS total', [], 'GROUP BY status HAVING COUNT(*) > 1', 1],
        ['DISTINCT status', [], '', 2],
        ['*', [], 'ORDER BY id LIMIT 1 OFFSET 1', 1],
        ['*', [], 'LIMIT 0', 0],
    ] as [$columns, $where, $after, $expected]) {
        check(DBHelper::selectCount($columns, ['orders'], '', $where, $after) === $expected, 'Count preserves SELECT semantics: ' . $after);
    }
    check(DBHelper::selectCount('o.id AS order_id, i.id AS item_id', ['orders o'], 'JOIN items i ON i.id = o.id', [], '') === 1, 'JOIN with distinct column aliases');
    $prepare = new ReflectionMethod(DBHelper::class, 'prepare_Where');
    foreach ([['deleted_at IS NULL' => null], ['deleted_at' => [null, '=']]] as $invalid) {
        $args = [];
        try {
            $prepare->invokeArgs(null, [$invalid, &$args]);
            throw new RuntimeException('Expected invalid null expression');
        } catch (InvalidArgumentException) {}
    }
    echo "PDO reuse, transactions, NULL, RawCondition, SELECT/UPDATE/DELETE checks passed\n";
} finally {
    if (DBHelper::inTransaction()) DBHelper::rollBack();
    $connection->setValue(null, null);
}
