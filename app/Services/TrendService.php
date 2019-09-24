<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

use \SplFileObject;
use \Datetime;
use \Exception;
use \Throwabe;
use App\Model\Trend;
use App\Model\TrendWord;
use Carbon\Carbon;

class TrendService
{
  /**
   * latest trend time (検証用)
   */
  public function latestTime()
  {
    $c = Carbon::parse(Trend::max('trend_time'));
    $c->second = 0;
    $c->minute = 15 * (int) ($c->minute / 15);
    return ['latest_time' => $c->format('Y-m-d H:i:s')];
  }

  /**
   * volumes from wordId (検証用)
   * 
   * @param int $trendWordId
   */
  public function volumes($trendWordId)
  {
    $trendWord = TrendWord::where('id', $trendWordId)->first();
    if (empty($trendWord)) {
      return ['status' => 'error', 'message' => 'trend_word_id not found'];
    }
    return [
      'id' => $trendWordId,
      'word' => $trendWord->trend_word,
      'volumes' => Trend::select(['tweet_volume', 'trend_time'])
        ->where('trend_word_id', $trendWordId)
        ->get()
    ];
  }

  /**
   * trend tweets from archive file (検証用)
   * 
   * @param int $trendId
   * @return string json
   */
  public function getTrendTweets($trendId)
  {
    $trend = Trend::where('id', $trendId)->first();
    if (empty($trend)) {
      return ['status' => 'error', 'message' => 'trend_id not found'];
    }

    $date = (new Datetime($trend->trend_time))->format('Y-m-d');
    $filePath = storage_path() . "/app/archive/${date}/trend_tweets${trendId}.json.gz";
    if (file_exists($filePath)) {
      return join('', gzfile($filePath));
    } else {
      return ['status' => 'error', 'message' => 'trend arcive not found'];
    }
  }

  /**
   * 重み付きトレンドワード (検証用)
   * data from archive file
   * 
   * @param int $trendId
   * @return array
   */
  public function analyseTrendTweets($trendId)
  {
    $trend = Trend::where('id', $trendId)->first();
    if (empty($trend)) {
      return ['status' => 'error', 'message' => 'trend_id not found'];
    }
    $date = (new Datetime($trend->trend_time))->format('Y-m-d');
    $filePath = storage_path() . "/app/archive/${date}/trend_tweets${trendId}.json.gz";
    if (file_exists($filePath)) {
      $data = json_decode(join('', gzfile($filePath)), true);
    } else {
      return ['status' => 'error', 'message' => 'trend arcive not found'];
    }

    $list = [];
    foreach ($data as $tweet) {
      $list[] = preg_replace(
        '/https\:\/\/t\.co\/\w+/',
        '',
        array_key_exists('full_text', $tweet) ? $tweet['full_text'] : $tweet['text']
      );
    }

    $src = tempnam(storage_path(), 'src_');
    file_put_contents($src, join("", $list));

    try {
      $list = $this->analyseText($src);
    } catch (Exception $e) {
      Log::debug($e);
      $list = ['status' => 'error', 'message' => 'trend analyse failed'];
    }
    if (file_exists($src)) {
      unlink($src);
    }

    return $list;
  }

  /**
   * デイリートレンドワード
   * 
   * @param string $date
   * @param nunber $limit
   * @return array
   */
  public function dailyTrends($date, $limit = 10)
  {
    $sql = <<<SQL
    select
      a.*,
      b.trend_word
    from trends a
    join trend_words b on a.trend_word_id=b.id
    where a.id in (
      select distinct
        first_value(id) OVER (partition by trend_word_id order by tweet_volume desc) AS id
      from trends
      where trend_time between :from and :to)
    order by tweet_volume desc limit :limit
SQL;
    return DB::select($sql, [
      'from' => (new Datetime($date))->format('Y-m-d 00:00:00'),
      'to' => (new Datetime($date))->format('Y-m-d 23:59:59'),
      'limit' => $limit,
    ]);
  }

  /**
   * トレンドワード
   * SSR用なのでHTTPステータスで返す
   * 
   * @param int $trendId
   * @return array|null
   */
  public function trendWord($trendWordId)
  {
    $trendWord = TrendWord::find($trendWordId);
    if (empty($trendWord)) {
      return ['status' => 404, 'message', 'データが見つかりません。'];
    } else {
      return [
        'status' => 200,
        'trend_word' => $trendWord->trend_word
      ];
    }
  }

  /**
   * デイリー・個別トレンドワード
   * SSR用なのでHTTPステータスで返す
   * 
   * @param string $date
   * @param int $trendId
   * @return array|null
   */
  public function dailyTrendWord($date, $trendWordId)
  {
    $from = (new Datetime($date))->format('Y-m-d 00:00:00');
    $to = (new Datetime($date))->format('Y-m-d 23:59:59');

    $trend = Trend::where('trend_word_id', $trendWordId)
      ->whereBetween('trends.trend_time', [$from, $to])
      ->first();

    $trendWord = TrendWord::find($trendWordId);

    if (empty($trend) || empty($trendWord)) {
      return ['status' => 404, 'message', 'データが見つかりません。'];
    } else {
      return [
        'status' => 200,
        'date' => $date,
        'trend_word' => $trendWord->trend_word
      ];
    }
  }

  /**
   * ツイート件数
   * 
   * @param string $date
   * @param int $trendId
   * @return array
   */
  public function tweetVolume($date, $trendWordId)
  {
    $sql = <<<SQL
      select
        max(v.tweet_volume) as tweet_volume,
        v.trend_hour
      from (
        select
          case when tweet_volume is null then 0 else tweet_volume end as tweet_volume,
          DATE_FORMAT(trend_time, '%Y-%m-%d %H') as trend_hour
        from trends
        where trend_word_id = :id and trend_time between :from and :to ) as v
      group by v.trend_hour
SQL;
    return DB::select($sql, [
      'id' => $trendWordId,
      'from' => (new Datetime($date))->format('Y-m-d 00:00:00'),
      'to' => (new Datetime($date))->format('Y-m-d 23:59:59')
    ]);
  }

  /**
   * デイリートレンド解析
   * date from archive file
   * 
   * ・重み付きトレンドワード
   * ・時間別ツイート数
   * ・人気のツイート
   * ・キャッシュ
   * 
   * @param string $date
   * @param int $trendId
   * @return array
   */
  public function analyseDailyTrendTweets($date, $trendWordId)
  {
    $cacheFile = storage_path() . "/app/public/analyze/{$date}/{$trendWordId}.json.gz";

    $from = (new Datetime($date))->format('Y-m-d 00:00:00');
    $to = (new Datetime($date))->format('Y-m-d 23:59:59');

    $trends = Trend::where('trend_word_id', $trendWordId)
      ->whereBetween('trends.trend_time', [$from, $to])
      ->get();

    $trendWord = TrendWord::find($trendWordId);

    if (empty($trends) || empty($trendWord)) {
      return ['status' => 'error', 'message' => 'データが見つかりません。'];
    }

    // キャッシュチェック
    if (file_exists($cacheFile)) {
      $dateNow = Carbon::now();
      $dateTarget = Carbon::parse($date);
      $dateCreation = Carbon::createFromTimestamp(stat($cacheFile)['ctime']);

      // 定期更新の5分後に合わせる
      $dateCreation->second = 0;
      $dateCreation->minute = 5 + 15 * (int) ($dateCreation->minute / 15);

      // キャッシュ破棄条件 指定日から25時間以内 && 現在から15分以上経過
      if ($dateTarget->gt($dateCreation->copy()->subHours(25)) && $dateNow->gt($dateCreation->copy()->addMinutes(15))) {
        unlink($cacheFile);
      } else {
        // キャッシュから返す
        return [
          'status' => 'success',
          'cache' => true,
          'date' => $date,
          'trend_word' => $trendWord->trend_word,
          'analyze' => json_decode(join('', gzfile($cacheFile)), true)
        ];
      }
    }

    // 範囲内のファイルをデコードし重複を取り除く
    $tweets = [];
    foreach ($trends as $trend) {
      $id = $trend->id;
      $filePath = storage_path() . "/app/archive/${date}/trend_tweets${id}.json.gz";
      if (file_exists($filePath)) {
        $data = json_decode(join('', gzfile($filePath)), true);
      } else {
        $data = [];
      }
      foreach ($data as $idStr => $tweet) {
        $tweets[$idStr] = $tweet;
      }
    }

    if (empty($tweets)) {
      return ['status' => 'error', 'message' => 'アーカイブが見つかりません。'];
    }

    // 重み付きトレンドワード
    $list = [];
    foreach ($tweets as $idStr => $tweet) {
      $list[] = preg_replace(
        '/https\:\/\/t\.co\/\w+/',
        '',
        array_key_exists('full_text', $tweet) ? $tweet['full_text'] : $tweet['text']
      );
    }
    $src = tempnam(storage_path(), 'src_');
    file_put_contents($src, join("\n", $list));
    try {
      $wordWeights = $this->analyseText($src);
    } catch (Exception $e) {
      Log::error("analyseText: error.");
      Log::debug($e);
    }
    if (file_exists($src)) {
      unlink($src);
    }

    // 時間別ツイート数
    $valuePerHour = array_pad([], 24, 0);
    $dateFrom = Carbon::parse($from);
    $dateTo = Carbon::parse($to);
    foreach ($tweets as $idStr => $tweet) {
      $date = Carbon::parse($tweet['created_at']);
      $date->timezone('Asia/Tokyo');
      if ($date->gte($dateFrom) && $date->lte($dateTo)) {
        $valuePerHour[$date->hour]++;
      }
    }

    // 人気のツイート
    $idList = [];
    foreach ($tweets as $idStr => $tweet) {
      $idList[] = [
        'id_str' => (string) $idStr,
        'favorite' => (int) $tweet['favorite_count'],
        'retweet' => (int) $tweet['retweet_count']
      ];
    }

    // キャッシュ作成
    $analyze = [
      'word_weights' => $wordWeights,
      'value_per_hour' => $valuePerHour,
      'id_list' => $idList
    ];
    File::makeDirectory(pathinfo($cacheFile, PATHINFO_DIRNAME), 0775, true, true);
    file_put_contents($cacheFile, gzencode(json_encode($analyze), 9));

    return [
      'status' => 'success',
      'cache' => false,
      'date' => $date,
      'trend_word' => $trendWord->trend_word,
      'analyze' => $analyze
    ];
  }

  /**
   * analyse text from file
   *
   * @param string $textFile php-fpm(mecab)から見えるpathを指定すること
   * @return array
   */
  private function analyseText($textFile)
  {
    $dst = tempnam(storage_path(), 'dst_');
    exec("cat $textFile | mecab -d /var/www/neologd -o $dst", $output, $execRes);
    if ($execRes != 0 || !file_exists($dst)) {
      Log::error("analyseText: mecab error.");
      return [];
    }

    $tmp = [];
    try {
      $csv = new SplFileObject($dst);
      $csv->setFlags(SplFileObject::READ_CSV);
      while (!$csv->eof()) {
        $line = $csv->current();
        if (count($line) > 1) {
          $p = explode("\t", $line[0]);
          if (
            count($p) > 1 &&
            in_array($p[1], ['名詞', '形容詞']) && !in_array($line[1], ['非自立', '数', '接尾', '代名詞', '特殊'])
          ) {
            $name = strtolower($p[0]);
            if (array_key_exists($name, $tmp)) {
              $tmp[$name][0]++;
              if (array_key_exists($p[0], $tmp[$name][1])) {
                $tmp[$name][1][$p[0]]++;
              } else {
                $tmp[$name][1][$p[0]] = 1;
              }
            } else {
              $tmp[$name] = [1, [$p[0] => 1], $p[1], $line[1]];
            }
          }
        }
        $csv->next();
      }
    } catch (Exception $e) {
      Log::error("analyseText: analyse error.");
      Log::debug($e);
    }

    if (file_exists($dst)) {
      unlink($dst);
    }

    $list = [];
    foreach ($tmp as $k => $v) {
      $count = 0;
      $name = '';
      foreach ($v[1] as $key => $value) {
        if ($value > $count) {
          $name = $key;
          $count = $value;
        }
      }
      $list[] = ['text' => $name, 'size' => $v[0]];
    }
    usort($list, function ($a, $b) {
      return $b['size'] - $a['size'];
    });

    return $list;
  }

  /**
   * トレンドワード検索
   * ワード一覧
   * 
   * @param string $word 検索ワード
   * @param int $page
   * @param int $maxPerPage ページ当たり件数
   * @return array
   */
  function searchTrendWord($word, $page, $maxPerPage = 15)
  {
    Log::debug("searchTrendWord: $word");

    $total = <<<SQL
      select count(id) as count
      from trend_words
      where trend_word like :word
SQL;

    $data = <<<SQL
      select
        w.id as trend_word_id,
        w.trend_word,
        max(t.trend_time) as latest_trend_time
      from trend_words w
      join trends t on w.id = t.trend_word_id
      where w.trend_word like :word
      group by w.id
      order by latest_trend_time desc
      limit :limit
      offset :offset
SQL;

    return [
      'status' => 'success',
      'request' => ['word' => $word],
      'total' => DB::select($total, ['word' => "%${word}%"])[0]->count,
      'page' => $page,
      'max_per_page' => $maxPerPage,
      'result' => DB::select($data, ['word' => "%${word}%", 'limit' => $maxPerPage, 'offset' => ($page - 1) * $maxPerPage])
    ];
  }

  /**
   * トレンドワードが含まれる日
   * 
   * @param int $trendWordId
   * @param int $page
   * @param int $maxPerPage ページ当たり件数
   * @return array
   */
  function searchTrendWordDate($trendWordId, $page, $maxPerPage = 15)
  {
    $trendWord = TrendWord::find($trendWordId);

    if (empty($trendWord)) {
      return ['status' => 'notfound', 'message', 'データが見つかりません。'];
    }

    $total = <<<SQL
      select count(a.id) as count
      from (
        select max(t.id) as id
        from trends t
        where t.trend_word_id = 1
        group by DATE_FORMAT(t.trend_time, '%Y-%m-%d')
      ) a
SQL;

    $data = <<<SQL
      select
        max(t.tweet_volume) as tweet_volume,
        DATE_FORMAT(t.trend_time, '%Y-%m-%d') as trend_time
      from trends t
      where t.trend_word_id = :id
      group by DATE_FORMAT(t.trend_time, '%Y-%m-%d')
      order by DATE_FORMAT(t.trend_time, '%Y-%m-%d') desc
      limit :limit
      offset :offset
SQL;

    return [
      'status' => 'success',
      'trend_word_id' => $trendWordId,
      'trend_word' => $trendWord,
      'total' => DB::select($total, ['id' => $trendWordId]),
      'page' => $page,
      'max_per_page' => $maxPerPage,
      'result' => DB::select($data, ['id' => $trendWordId, 'limit' => $maxPerPage, 'offset' => ($page - 1) * $maxPerPage])
    ];
  }
}
