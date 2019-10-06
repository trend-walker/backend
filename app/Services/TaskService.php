<?php

namespace App\Services;

use \Datetime;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Model\Trend;
use App\Model\TrendWord;
use App\Model\TrendTweet;

use App\Services\TwitterApi;

/**
 * 定期処理用クラス
 */
class TaskService
{
  /**
   * @var TwitterApi
   */
  protected $twiterApi;

  /**
   * @var TrendService
   */
  protected $trendService;

  /**
   * TaskService コンストラクタ
   *
   * @param TwitterApi $twiterApi
   * @param TrendService $trendService
   * @return void
   */
  public function __construct(TwitterApi $twiterApi, TrendService $trendService)
  {
    $this->twiterApi = $twiterApi;
    $this->trendService = $trendService;
  }

  /**
   * 定期処理
   * 
   * @return array [int trend_id]
   */
  public function fetchTask()
  {
    $list = [];

    // トレンド定期取得
    Log::info('fetch trends start.');
    try {
      // トレンド取得
      $content = $this->twiterApi->getTrends();
      $list = $this->saveTrendData($content[0]);
      Log::info('save trends.');

      // トレンドワード検索
      foreach ($list as $id => $word) {
        $content = $this->twiterApi->searchTweets($word);
        $this->saveTrendTweets($content, $id);
      }
      Log::info('fetch trends over.');
    } catch (Throwable $e) {
      Log::info('fetch failure.');
      Log::debug($e);
    }

    $idList = array_keys($list);

    // トレンドワード解析
    if (!empty($idList)) {
      Log::info('analyze top trends start.');
      $date = Carbon::now()->format('Y-m-d');
      $trends = Trend::whereIn('id', $idList)->get();
      foreach ($trends as $trend) {
        $this->trendService->analyseDailyTrendTweets($date, $trend->trend_word_id);
      }
      Log::info(sprintf('analyze top %d trends end.', count($idList)));
    }

    return $idList;
  }

  /**
   * トレンドを保存
   *
   * @param array $data
   * @return array [int trend_id => string trend_word]
   */
  public function saveTrendData($data)
  {
    try {
      $res = [];
      DB::beginTransaction();
      foreach ($data['trends'] as $json) {
        $trendWord = TrendWord::where('trend_word', $json['name'])->first();
        if (empty($trendWord)) {
          $trendWord = new TrendWord();
          $trendWord->trend_word = $json['name'];
          $trendWord->save();
        }
        $trend = new Trend();
        $trend->trend_word_id = $trendWord->id;
        $trend->tweet_volume = $json['tweet_volume'];
        $trend->trend_time = new Datetime();
        $trend->save();
        $res[$trend->id] = $json['name'];
      }
      DB::commit();
      return $res;
    } catch (Throwable $e) {
      DB::rollback();
    }
    return [];
  }

  /**
   * ツイートを保存
   *
   * @param array $data
   * @param int $trendId
   * @return void
   */
  public function saveTrendTweets($data, int $trendId)
  {
    $tweets = [];
    // リツイートを排除
    foreach ($data['statuses'] as $status) {
      if (array_key_exists('retweeted_status', $status)) {
        $status = $status['retweeted_status'];
      }
      $tweets[$status['id_str']] = $status;
    }
    //ID順にソート
    ksort($tweets);

    // ストレージに保存
    $arcPath = sprintf(
      '%s/%s/trend_tweets%d.json.gz',
      config('constants.archive_path'),
      (new Datetime())->format('Y-m-d'),
      $trendId
    );
    Storage::makeDirectory(pathinfo($arcPath, PATHINFO_DIRNAME));
    Storage::put($arcPath, gzencode(json_encode($tweets), 9));

    // 以下、現状使用しない
    if (true) {
      return;
    }

    try {
      DB::beginTransaction();
      foreach ($tweets as $status) {
        // 同じトレンドに同じツイートは弾く
        $trendTweet = TrendTweet::where('trend_id', $trendId)
          ->where('id_str', $status['id_str'])
          ->first();
        if (empty($trendTweet)) {
          $trendTweet = new TrendTweet();
          $trendTweet->trend_id = $trendId;
          $trendTweet->id_str = $status['id_str'];
          $trendTweet->save();
        }
        // ツイート updateOrCreate
        // Tweet::updateOrCreate(['id_str' => $status['id_str']], ['tweet' => json_encode($status)]);
      }
      DB::commit();
    } catch (Throwable $e) {
      DB::rollback();
    }
  }
}
