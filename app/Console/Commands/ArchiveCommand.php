<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;
use App\Model\Trend;
use App\Services\TrendService;
use \Datetime;

class ArchiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archive:manage {mode} {date} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'archive manager';

    /**
     * Undocumented variable
     *
     * @var TrendService
     */
    protected $trendService;

    /**
     * Create a new command instance.
     *
     * @param TrendService $trendService
     * @return void
     */
    public function __construct(TrendService $trendService)
    {
        parent::__construct();
        $this->trendService = $trendService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        switch ($this->argument("mode")) {
            case 'analyze':
                $this->dailyAnalyze($this->argument("date"));
                break;
            case 'migrate':
                $this->migrate($this->argument("date"));
                break;
            case 'logtest':
                $this->logtest($this->argument("date"));
                break;
        }
    }

    function logtest($date)
    {
        Log::info('info');
        Log::error('error');
        Log::debug('debug');
    }
    
    /**
     * デイリートレンド 解析データ一括生成
     *
     * @param string $date
     * @return void
     */
    private function dailyAnalyze($date)
    {
        $trends = $this->trendService->dailyTrends($date, 9999);
        foreach ($trends as $i => $trend) {
            echo sprintf("%d %d %s\n", $i + 1, $trend->trend_word_id, $trend->trend_word);
            $this->trendService->analyseDailyTrendTweets($date, $trend->trend_word_id);
        }

        echo "analyze generate over.\n";
    }

    /**
     *  7/9更新のアーカイブデータ構造修正コマンド
     *
     * @param string $date
     * @return void
     */
    private function migrate($date)
    {
        $from = (new Datetime($date))->format('Y-m-d 00:00:00');
        $to = (new Datetime($date))->format('Y-m-d 23:59:59');
        $trends = Trend::whereBetween('trends.trend_time', [$from, $to])->get();
        if (empty($trends)) {
            echo "データが見つかりません。\n";
            return;
        }

        foreach ($trends as $trend) {
            $id = $trend->id;
            $filePath = storage_path() . "/app/archive/${date}/trend_tweets${id}.json.gz";
            if (!file_exists($filePath)) {
                continue;
            }
            $data = json_decode(join('', gzfile($filePath)), true);

            $tweets = [];
            foreach ($data as $tweet) {
                $tweets[$tweet['id_str']] = $tweet;
            }
            ksort($tweets);

            // save raw tweets
            $rawPath = storage_path() . "/app/extract/${date}/trend_tweets${id}.json";
            File::makeDirectory(pathinfo($rawPath, PATHINFO_DIRNAME), 0775, true, true);
            file_put_contents($rawPath, json_encode($tweets));

            // save tweets
            $filePath = storage_path() . "/app/${date}/trend_tweets${id}.json.gz";
            File::makeDirectory(pathinfo($filePath, PATHINFO_DIRNAME), 0775, true, true);
            file_put_contents($filePath, gzencode(json_encode($tweets), 9));
        }
        echo "artive migrate over.\n";
    }
}
