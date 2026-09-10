<?php

namespace SEOChangeMonitor\Services;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Connection;

/**
 * Workarounds for the bundled query builder, kept in one place.
 *
 * Writes: update() only stages a query and never runs it, and save() on a
 * hydrated model can emit malformed SQL, so every UPDATE goes through here.
 *
 * Reads: get() returns an array for many rows, a single model for one, and
 * false for none, so callers need rows() to get a list either way.
 */
class Db
{
    public static function table($name)
    {
        return Connection::wpPrefix() . Config::VAR_PREFIX . $name;
    }

    /**
     * Normalises a get() result into a list.
     *
     * @param mixed $result array of models, a single model, or false
     *
     * @return array
     */
    public static function rows($result)
    {
        if (\is_array($result)) {
            return $result;
        }

        return $result ? [$result] : [];
    }

    /**
     * @param string $table plugin table name without prefix
     * @param array  $data  column => value (null allowed)
     * @param array  $where column => value (ANDed)
     *
     * @return int|false rows affected
     */
    public static function update($table, array $data, array $where)
    {
        global $wpdb;

        if ($data === [] || $where === []) {
            return false;
        }

        $sets     = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            if ($value === null) {
                $sets[] = self::column($column) . ' = NULL';

                continue;
            }

            $sets[]     = self::column($column) . ' = ' . self::placeholder($value);
            $bindings[] = $value;
        }

        $conditions = [];
        foreach ($where as $column => $value) {
            $conditions[] = self::column($column) . ' = ' . self::placeholder($value);
            $bindings[]   = $value;
        }

        $sql = 'UPDATE `' . self::table($table) . '` SET ' . implode(', ', $sets)
             . ' WHERE ' . implode(' AND ', $conditions);

        /*
         * Every value is bound; the only interpolated parts are the table
         * name, which self::table() builds, and the column names, which
         * self::column() rejects unless they are plain identifiers. A
         * non-empty $where always contributes at least one binding.
         */
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->query($wpdb->prepare($sql, $bindings));
    }

    public static function query($sql, array $bindings = [])
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- callers pass a literal statement and bind every value.
        return $wpdb->query($bindings === [] ? $sql : $wpdb->prepare($sql, $bindings));
    }

    /**
     * Backticks a column name, refusing anything that is not a plain
     * identifier. Column names cannot be bound as parameters, so this is
     * what keeps them out of the SQL if a caller ever passes one through.
     */
    private static function column($name)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $name)) {
            throw new \InvalidArgumentException('Invalid column name.');
        }

        return "`{$name}`";
    }

    private static function placeholder($value)
    {
        if (\is_int($value) || \is_bool($value)) {
            return '%d';
        }

        return \is_float($value) ? '%f' : '%s';
    }
}
