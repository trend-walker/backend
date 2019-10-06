<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Services\TaskService;

class FetchTrendCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trend:fetch {code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'fetch curent trends and tweets';

    /**
     * @var TaskService
     */
    protected $taskService;

    /**
     * Create a new command instance.
     *
     * @param TaskService $trendService
     * @return void
     */
    public function __construct(TaskService $taskService)
    {
        parent::__construct();
        $this->taskService = $taskService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->taskService->fetchTask();
    }
}
