<?php

namespace BeyondCode\LaravelMaskedDumper\Console;

use Illuminate\Console\Command;
use BeyondCode\LaravelMaskedDumper\LaravelMaskedDump;

class DumpDatabaseCommand extends Command
{
    protected $signature = 'db:masked-dump {output} {--definition=default} {--gzip}';

    protected $description = 'Create a new database dump';

    public function handle()
    {
        $definition = config('masked-dump.' . $this->option('definition'));
        $definition = is_callable($definition) ? call_user_func($definition) : $definition;
        $definition->load();

        $this->info('Starting Database dump');

        $outputPath = $this->option('gzip') ? $this->argument('output') . '.gz' : $this->argument('output');
        $partialOutputPath = $outputPath . '.part';
        $stream = $this->option('gzip') ? gzopen($partialOutputPath, 'w9') : fopen($partialOutputPath, 'w');

        try {
            $dumper = new LaravelMaskedDump($definition, $this->output);
            $dumper->dumpToStream($stream);
        } catch (\Throwable $exception) {
            fclose($stream);
            unlink($partialOutputPath);

            throw $exception;
        }

        fclose($stream);
        rename($partialOutputPath, $outputPath);

        $this->output->writeln('');
        $this->info('Wrote database dump to ' . $outputPath);
    }
}
