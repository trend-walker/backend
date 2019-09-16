<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\TaskService;
use \Throwable;

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
        $this->taskService=$taskService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('fetch trends start.');
        try {
            $connection=$this->taskService->getApiConnection();
            
            $content = $connection->get("trends/place", ['id' => 23424856]);
            $list=$this->taskService->saveTrendData($content[0]);
            Log::info('save trends.');
            
            foreach ($list as $id => $word) {
                $content = $connection->get("search/tweets", [
                    'q' => $word,
                    'lang' => 'ja',
                    'locale' => 'ja',
                    'result_type' => 'mixed',
                    'count' => 100,
                ]);
                $this->taskService->saveTrendTweets($content, $id);
            }
            Log::info('fetch trends over.');
        } catch (Throwable $e) {
            Log::info($e);
        }
    }
}
