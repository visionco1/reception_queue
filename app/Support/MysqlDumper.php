<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * نسخة احتياطية لقاعدة MySQL/MariaDB بلغة PHP فقط.
 *
 * تُستخدم على الاستضافات المشتركة (مثل Hostinger) التي تعطّل proc_open فلا يمكن
 * تشغيل mysqldump. الناتج ملف SQL مضغوط يُستعاد بنفس طريقة ملف mysqldump:
 *   gunzip < backup.sql.gz | mysql -u USER -p DATABASE
 * أو من phpMyAdmin ← Import.
 */
class MysqlDumper
{
    private const ROWS_PER_INSERT = 200;

    private const BINARY_TYPES = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit', 'geometry', 'point', 'linestring', 'polygon'];

    public function __construct(private readonly array $config) {}

    public function dump(string $gzPath): int
    {
        // اتصال مستقل حتى لا تتأثر جلسة التطبيق بتغيير المنطقة الزمنية أو وضع القراءة
        $unbuffered = PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
        $connection = DB::build(array_merge($this->config, [
            'options' => [$unbuffered => false] + ($this->config['options'] ?? []),
        ]));
        $pdo = $connection->getPdo();

        $pdo->exec("SET NAMES utf8mb4, time_zone = '+00:00'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        $out = gzopen($gzPath, 'wb6');
        if ($out === false) {
            throw new \RuntimeException("Cannot write {$gzPath}");
        }

        $rows = 0;

        try {
            $this->write($out, implode("\n", [
                '-- QMS database backup (PHP dumper)',
                '-- Database: '.$this->config['database'],
                '-- Created: '.now()->toDateTimeString(),
                '',
                'SET NAMES utf8mb4;',
                "SET time_zone = '+00:00';",
                'SET FOREIGN_KEY_CHECKS = 0;',
                'SET UNIQUE_CHECKS = 0;',
                "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';",
                '',
            ]));

            foreach ($this->tables($pdo) as $table) {
                $rows += $this->dumpTable($pdo, $out, $table);
            }

            $this->write($out, "\nSET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n-- Dump completed\n");
            $pdo->exec('COMMIT');
        } finally {
            gzclose($out);
            DB::purge($connection->getName());
        }

        return $rows;
    }

    /** @return list<string> */
    private function tables(PDO $pdo): array
    {
        $tables = [];
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'", PDO::FETCH_NUM) as $row) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    private function dumpTable(PDO $pdo, $out, string $table): int
    {
        $q = $this->ident($table);

        $create = $pdo->query("SHOW CREATE TABLE {$q}")->fetchAll(PDO::FETCH_NUM)[0][1];
        $this->write($out, "\n-- Table {$table}\nDROP TABLE IF EXISTS {$q};\n{$create};\n");

        $columns = $this->columns($pdo, $table);
        if ($columns === []) {
            return 0;
        }

        $names = array_keys($columns);
        $binary = array_values($columns);
        $prefix = "INSERT INTO {$q} (".implode(', ', array_map($this->ident(...), $names)).') VALUES';
        $select = 'SELECT '.implode(', ', array_map($this->ident(...), $names))." FROM {$q}";

        $stmt = $pdo->query($select, PDO::FETCH_NUM);
        $batch = [];
        $count = 0;

        while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
            $values = [];
            foreach ($row as $i => $value) {
                $values[] = match (true) {
                    $value === null => 'NULL',
                    $binary[$i] => $value === '' ? "''" : '0x'.bin2hex($value),
                    default => $pdo->quote((string) $value),
                };
            }
            $batch[] = '('.implode(',', $values).')';
            $count++;

            if (count($batch) >= self::ROWS_PER_INSERT) {
                $this->write($out, $prefix."\n".implode(",\n", $batch).";\n");
                $batch = [];
            }
        }
        $stmt->closeCursor();

        if ($batch !== []) {
            $this->write($out, $prefix."\n".implode(",\n", $batch).";\n");
        }

        return $count;
    }

    /**
     * أعمدة الجدول القابلة للإدخال (بدون الأعمدة المحسوبة) مع تحديد الأعمدة الثنائية.
     *
     * @return array<string, bool>
     */
    private function columns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute([$table]);

        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (str_contains(strtolower((string) $col['EXTRA']), 'generated')) {
                continue;
            }
            $columns[$col['COLUMN_NAME']] = in_array(strtolower($col['DATA_TYPE']), self::BINARY_TYPES, true);
        }

        return $columns;
    }

    private function ident(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private function write($out, string $sql): void
    {
        if (gzwrite($out, $sql) === false) {
            throw new \RuntimeException('Failed writing backup file');
        }
    }
}
