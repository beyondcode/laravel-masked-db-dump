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
        $definition->load();

        $this->info('Starting Database dump');

        $dumper = new LaravelMaskedDump($definition, $this->output);
        $dump = $dumper->dump();

        $this->output->writeln('');
        $this->writeOutput($dump);
    }

    protected function writeOutput(string $dump)
    {
        if ($this->option('gzip')) {
            $gz = gzopen($this->argument('output') . '.gz', 'w9');
            gzwrite($gz, $dump);
            gzclose($gz);

            $this->info('Wrote database dump to ' . $this->argument('output') . '.gz');
        } else {
            file_put_contents($this->argument('output'), $dump);
            $this->info('Wrote database dump to ' . $this->argument('output'));
        }
    }
}
