<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Abraham\TwitterOAuth\TwitterOAuth;

use \Datetime;
use \DateTimeZone;
use \Exception;
use \Throwabe;
use App\Model\Trend;
use App\Model\TrendWord;
use App\Model\Tweet;
use App\Model\TrendTweet;
use Carbon\Carbon;

class TaskService
{
  /**
   * get twitter api Connection
   *
   * @return void
   */
  public function getApiConnection()
  {
    $connection = new TwitterOAuth(
      config('env.TWITTER_CONSUMER_KEY'),
      config('env.TWITTER_CONSUMER_SECRET'),
      config('env.TWITTER_ACCESS_TOKEN'),
      config('env.TWITTER_ACCESS_TOKEN_SECRET')
    );
    $connection->setDecodeJsonAsArray(true);
    return $connection;
  }

  /**
   * トレンドを保存
   *
   * @param [type] $data
   * @return void
   */
  public function saveTrendData($data)
  {
    $tmp = [];
    try {
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
        $tmp[$trend->id] = $json['name'];
      }
      DB::commit();
      return $tmp;
    } catch (Throwable $e) {
      DB::rollback();
    }
    return [];
  }

  /**
   * Undocumented function
   *
   * @param [type] $data
   * @param integer $trendId
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
    $date = (new Datetime())->format('Y-m-d');
    if (!file_exists(storage_path() . "/app/${date}")) {
      mkdir(storage_path() . "/app/${date}");
    }
    file_put_contents(storage_path() . "/app/${date}/trend_tweets${trendId}.json.gz", gzencode(json_encode($tweets), 9));

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