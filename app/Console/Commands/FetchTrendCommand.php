<?php

namespace App\Console\Commands;

use \Throwable;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

use App\Model\Trend;
use App\Services\TaskService;
use App\Services\TrendService;

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
     * @var TrendService
     */
    protected $trendService;

    /**
     * Create a new command instance.
     *
     * @param TaskService $trendService
     * @return void
     */
    public function __construct(TaskService $taskService, TrendService $trendService)
    {
        parent::__construct();
        $this->taskService = $taskService;
        $this->trendService = $trendService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $upperPart = [];

        // トレンド定期取得
        Log::info('fetch trends start.');
        try {
            $connection = $this->taskService->getApiConnection();

            // トレンドワード取得
            $content = $connection->get("trends/place", ['id' => 23424856]);
            $list = $this->taskService->saveTrendData($content[0]);
            Log::info('save trends.');

            // トレンドワード検索
            foreach ($list as $id => $word) {
                $content = $connection->get("search/tweets", [
                    'q' => $word,
                    'lang' => 'ja',
                    'locale' => 'ja',
                    'result_type' => 'mixed',
                    'tweet_mode' => 'extended',
                    'count' => 100,
                ]);
                $this->taskService->saveTrendTweets($content, $id);

                // トレンドワード上位のID記録
                if (count($upperPart) < 10) {
                    $upperPart[] = $id;
                }
            }
            Log::info('fetch trends over.');
        } catch (Throwable $e) {
            Log::info('fetch failure.');
            Log::debug($e);
        }

        // トレンドワード上位の解析
        if (!empty($upperPart)) {
            Log::info('analyze top trends start.');
            $date = Carbon::now()->format('Y-m-d');
            $trends = Trend::whereIn('id', $upperPart)->get();
            foreach ($trends as $trend) {
                $this->trendService->analyseDailyTrendTweets($date, $trend->trend_word_id);
            }
            Log::info(sprintf('analyze top %d trends end.', count($upperPart)));
        }
    }
}
