<?php

namespace SEOChangeMonitor\Services;

if (!\defined('ABSPATH')) {
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
                $sets[] = "`{$column}` = NULL";

                continue;
            }

            $sets[]     = "`{$column}` = " . self::placeholder($value);
            $bindings[] = $value;
        }

        $conditions = [];
        foreach ($where as $column => $value) {
            $conditions[] = "`{$column}` = " . self::placeholder($value);
            $bindings[]   = $value;
        }

        $sql = 'UPDATE `' . self::table($table) . '` SET ' . implode(', ', $sets)
             . ' WHERE ' . implode(' AND ', $conditions);

        // A non-empty $where always contributes at least one binding.
        return $wpdb->query($wpdb->prepare($sql, $bindings));
    }

    public static function query($sql, array $bindings = [])
    {
        global $wpdb;

        return $wpdb->query($bindings === [] ? $sql : $wpdb->prepare($sql, $bindings));
    }

    private static function placeholder($value)
    {
        if (\is_int($value) || \is_bool($value)) {
            return '%d';
        }

        return \is_float($value) ? '%f' : '%s';
    }
}
