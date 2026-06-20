<?php

namespace App\Console\Commands;

use App\Services\CommandService;
use Illuminate\Console\Command;

class TimeoutStaleCommands extends Command
{
    protected $signature = 'commands:timeout';

    protected $description = 'Mark picked/running commands that exceeded their timeout window as timed out';

    public function handle(CommandService $commands): int
    {
        $count = $commands->timeoutStaleCommands();

        $this->info("Marked {$count} stale command(s) as timeout.");

        return self::SUCCESS;
    }
}
