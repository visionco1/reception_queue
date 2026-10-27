<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

#[Signature('qms:backup')]
#[Description('نسخة احتياطية مضغوطة من قاعدة البيانات في storage/app/backups (يحتفظ بآخر نسخ حسب الإعداد)')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $dir = config('qms.backup.path');
        File::ensureDirectoryExists($dir, 0750);

        $connection = config('database.default');
        $db = config("database.connections.{$connection}");
        $stamp = now()->format('Y-m-d_His');

        try {
            $file = match ($db['driver']) {
                'sqlite' => $this->sqlite($db, $dir, $stamp),
                'mysql', 'mariadb' => $this->mysql($db, $dir, $stamp),
                default => throw new \RuntimeException("Unsupported driver {$db['driver']}"),
            };
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->prune($dir);
        $this->info('Backup saved: '.$file);

        return self::SUCCESS;
    }

    private function sqlite(array $db, string $dir, string $stamp): string
    {
        $target = "{$dir}/backup_{$stamp}.sqlite";
        // VACUUM INTO ينتج نسخة متسقة حتى أثناء الاستخدام
        DB::statement('VACUUM INTO ?', [$target]);
        $this->gzip($target);

        return $target.'.gz';
    }

    private function mysql(array $db, string $dir, string $stamp): string
    {
        $target = "{$dir}/backup_{$stamp}.sql";
        $process = new Process([
            'mysqldump', '--single-transaction', '--quick', '--routines', '--no-tablespaces',
            '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--result-file='.$target, $db['database'],
        ], null, ['MYSQL_PWD' => (string) $db['password']], null, 600);
        $process->mustRun();
        $this->gzip($target);

        return $target.'.gz';
    }

    private function gzip(string $path): void
    {
        $in = fopen($path, 'rb');
        $out = gzopen($path.'.gz', 'wb6');
        while (! feof($in)) {
            gzwrite($out, fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        unlink($path);
    }

    private function prune(string $dir): void
    {
        $files = collect(File::files($dir))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'backup_'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        $files->slice(max(1, (int) config('qms.backup.keep')))->each(fn ($f) => File::delete($f->getPathname()));
    }
}
