<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

use function Illuminate\Support\php_binary;

/**
 * {@see BaseServeCommand} + Laravel Reverb trong cùng tiến trình (shutdown dừng cả hai).
 */
final class ServeCommand extends BaseServeCommand
{
    private ?Process $reverbProcess = null;

    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['without-reverb', null, InputOption::VALUE_NONE, 'Không khởi chạy Laravel Reverb cùng PHP built-in server'],
        ];
    }

    public function handle()
    {
        if (! $this->option('without-reverb')) {
            $this->startReverb();
        }

        return parent::handle();
    }

    private function startReverb(): void
    {
        $process = new Process(
            [php_binary(), base_path('artisan'), 'reverb:start'],
            base_path(),
            null,
            null,
            null,
        );
        $process->setTimeout(null);

        $process->start(function ($type, $buffer) {
            $tag = $type === Process::ERR ? 'fg=red' : 'fg=cyan';
            foreach (preg_split("/\r\n|\n|\r/", rtrim($buffer, "\r\n")) as $line) {
                if ($line !== '') {
                    $this->output->writeln("  <{$tag}>[reverb]</> {$line}");
                }
            }
        });

        $this->reverbProcess = $process;

        $handle = $process;
        register_shutdown_function(static function () use ($handle): void {
            if ($handle->isRunning()) {
                $handle->stop(10);
            }
        });

        $this->components->info('Reverb WebSocket chạy chung với server (Ctrl+C dừng cả hai).');
    }
}
