<?php
/** Real PDO/SQLite tests for connection reuse and transaction semantics; no MySQL required. */
require dirname(__DIR__) . '/src/Root/Helpers/DBHelper.php';
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
    echo "PDO reuse, commit, rollback, transaction state and independent results passed\n";
} finally {
    if (DBHelper::inTransaction()) DBHelper::rollBack();
    $connection->setValue(null, null);
}
