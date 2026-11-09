<?php
namespace SiteApi\Root\Providers\Cache;

/** Adapters transport encoded strings; null means the key is absent. */
interface CacheContract
{
    /**
     * Записывает строку в кэш с TTL в секундах; 0 означает отсутствие срока.
     *
     * Отрицательный период недопустим; реализация должна сообщать об отказе операции исключением.
     */
    public function set(string $key, string $value, int $period): void;
    /**
     * Возвращает сохранённую строку либо null при промахе.
     *
     * Сбой сервера не должен смешиваться с отсутствием записи.
     */
    public function get(string $key): ?string;
    /**
     * Удаляет ключ; отсутствие ключа допустимо, отказ операции должен быть явным.
     */
    public function delete(string $key): void;
    /**
     * Возвращает имя реализации кэша без проверки доступности сервера.
     */
    public function info(): string;
    /**
     * Проверяет доступность сервера; при операционной ошибке возвращает false.
     */
    public function status(): bool;
}
