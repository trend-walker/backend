<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

use \SplFileObject;
use \Datetime;
use \DateTimeZone;
use \Exception;
use \Throwabe;
use App\Model\Trend;
use App\Model\TrendWord;
use App\Model\Tweet;
use App\Model\TrendTweet;
use function GuzzleHttp\json_decode;
use Carbon\Carbon;
use SimpleXMLElement;

class TrendService
{
  /**
   * Undocumented function (検証用)
   * 
   * @param string $time
   * @return void
   */
  public function trends($time)
  {
    $dt = (new Datetime('@' . $time))->modify('+9 hours');
    $fmt = 'Y-m-d H:i:s';
    return ['trend_time' => $time, 'trends' => DB::table('trends')
      ->join('trend_words', 'trend_words.id', '=', 'trends.trend_word_id')
      ->whereBetween('trends.trend_time', [$dt->format($fmt), $dt->modify('+15 minutes')->format($fmt)])
      ->select(['trend_word_id', 'trend_word', 'tweet_volume'])
      ->get()];
  }

  /**
   * Undocumented function (検証用)
   * 
   * @param int $wordId
   * @return void
   */
  public function volumes($wordId)
  {
    $trendWord = TrendWord::where('id', $wordId)->first();
    return [
      'id' => $wordId,
      'word' => empty($trendWord) ? '' : $trendWord->trend_word,
      'volumes' => Trend::select(['tweet_volume', 'trend_time'])
        ->where('trend_word_id', $wordId)
        ->get()
      // ->map(function($v){
      //     return [$v['trend_time'], $v['tweet_volume']];
      // })
    ];
  }

  /**
   * Undocumented function (検証用)
   * 
   * @param int $trendId
   * @return void
   */
  public function thattime($trendId)
  {
    $trend = Trend::where('id', $trendId)->first();
    if (empty($trend)) {
      return ['id' => $trendId];
    };
    return [
      'id' => $trendId,
      'trend_time' => $trend->trend_time,
      'stutuses' => DB::table('trend_tweets')
        ->join('tweets', 'tweets.id_str', '=', 'trend_tweets.id_str')
        ->where('trend_tweets.trend_id', $trendId)
        ->selectRaw(
          "JSON_EXTRACT(`tweet`, '$.retweet_count') as retweet"
        )
        ->selectRaw(
          "JSON_EXTRACT(`tweet`, '$.favorite_count') as favorite"
        )
        ->selectRaw(
          "JSON_EXTRACT(`tweet`, '$.text') as text"
        )
        ->get()
      // ->pluck('tweet')
      // ->map(function ($v){return json_decode($v);})
    ];
  }

  /**
   * デイリートレンドワード
   * 
   * @param string $date
   * @param nunber $limit
   * @return string josn
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
      select DISTINCT
        first_value(id) OVER (PARTITION BY trend_word_id ORDER BY tweet_volume) AS id
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
   * 最終更新日時
   */
  public function getLatestTime()
  {
    $c = Carbon::parse(Trend::max('trend_time'));
    $c->second = 0;
    $c->minute = 15 * (int) ($c->minute / 15);
    return ['latest_time' => $c->format('Y-m-d H:i:s')];
  }

  /**
   * Trend Tweets from archive file　(検証用)
   * 
   * @param int $trendId
   * @return string josn
   */
  public function getTrendTweets($trendId)
  {
    $trend = Trend::where('id', $trendId)->first();

    $day = (new Datetime($trend->trend_time))->format('Y-m-d');
    $filePath = storage_path() . "/app/${day}/trend_tweets${trendId}.json.gz";
    if (file_exists($filePath)) {
      return join('', gzfile($filePath));
    } else {
      $data = [];
    }
  }

  /**
   * トレンドワード
   * SSR用なのでHTTPステータスで返す
   * 
   * @param int $trendId
   * @return array|null josn
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
   * デイリー・個別トレンドワード・画像状態
   * SSR用なのでHTTPステータスで返す
   * 
   * @param string $date
   * @param int $trendId
   * @return array|null josn
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
   * 重み付きトレンドワード from archive file (検証用)
   * 
   * @param int $trendId
   * @return string josn
   */
  public function analyseTrendTweets($trendId)
  {
    $trend = Trend::where('id', $trendId)->first();
    $day = (new Datetime($trend->trend_time))->format('Y-m-d');
    $filePath = storage_path() . "/app/${day}/trend_tweets${trendId}.json.gz";
    if (file_exists($filePath)) {
      $data = json_decode(join('', gzfile($filePath)), true);
    } else {
      $data = [];
    }

    $list = [];
    foreach ($data as $tw) {
      $list[] = preg_replace('/https\:\/\/t\.co\/\w+/', '', $tw['text']);
    }

    $src = tempnam(storage_path(), 'src_');
    file_put_contents($src, join("", $list));

    try {
      $list = $this->analyseText($src);
    } catch (Exception $e) { }
    if (file_exists($src)) {
      unlink($src);
    }

    return $list;
  }

  /**
   * ツイート件数
   * 
   * @param string $date
   * @param int $trendId
   * @return array josn
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
   * トレンドワードごとのツイートリスト from archive file
   * 
   * @param string $date
   * @param int $trendId
   * @return array josn
   */
  public function dailyTrendTweets($date, $trendWordId)
  {
    $from = (new Datetime($date))->format('Y-m-d 00:00:00');
    $to = (new Datetime($date))->format('Y-m-d 23:59:59');

    $trends = Trend::where('trend_word_id', $trendWordId)
      ->whereBetween('trends.trend_time', [$from, $to])
      ->get();

    $trendWord = TrendWord::find($trendWordId);

    if (empty($trends) || empty($trendWord)) {
      return ['status' => 'notfound', 'message', 'データが見つかりません。'];
    }
    // 範囲内のファイルをデコードし重複を取り除く
    $tweets = [];
    foreach ($trends as $trend) {
      $id = $trend->id;
      $filePath = storage_path() . "/app/${date}/trend_tweets${id}.json.gz";
      if (file_exists($filePath)) {
        $data = json_decode(join('', gzfile($filePath)), true);
      } else {
        $data = [];
      }
      foreach ($data as $idStr => $tw) {
        $tweets[$idStr] = $tw;
      }
    }

    $list = [];
    foreach ($tweets as $idStr => $tw) {
      $list[] = ['id_str' => (string) $idStr, 'favorite' => (int) $tw['favorite_count'], 'retweet' => (int) $tw['retweet_count']];
    }

    return ['status' => 'success', 'date' => $date, 'trend_word' => $trendWord->trend_word, 'tweets' => $list];
  }

  /**
   * 重み付きトレンドワード from archive file
   * キャッシュあり
   * 
   * @param string $date
   * @param int $trendId
   * @return array josn
   */
  public function analyseDailyTrendTweets($date, $trendWordId)
  {
    $cacheFile = storage_path() . "/app/public/word-cloud/{$date}/{$trendWordId}.json";

    $from = (new Datetime($date))->format('Y-m-d 00:00:00');
    $to = (new Datetime($date))->format('Y-m-d 23:59:59');

    $trends = Trend::where('trend_word_id', $trendWordId)
      ->whereBetween('trends.trend_time', [$from, $to])
      ->get();

    $trendWord = TrendWord::find($trendWordId);

    if (empty($trends) || empty($trendWord)) {
      return ['status' => 'notfound', 'message', 'データが見つかりません。'];
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
          'words' => json_decode(file_get_contents($cacheFile))
        ];
      }
    }

    // 範囲内のファイルをデコードし重複を取り除く
    $tweets = [];
    foreach ($trends as $trend) {
      $id = $trend->id;
      $filePath = storage_path() . "/app/${date}/trend_tweets${id}.json.gz";
      if (file_exists($filePath)) {
        $data = json_decode(join('', gzfile($filePath)), true);
      } else {
        $data = [];
      }
      foreach ($data as $idStr => $tw) {
        $tweets[$idStr] = $tw['text'];
      }
    }

    // リンク削除
    $list = [];
    foreach ($tweets as $text) {
      $list[] = preg_replace('/https\:\/\/t\.co\/\w+/', '', $text);
    }

    $src = tempnam(storage_path(), 'src_');
    file_put_contents($src, join("", $list));

    try {
      $list = $this->analyseText($src);
    } catch (Exception $e) { }
    if (file_exists($src)) {
      unlink($src);
    }

    // キャッシュ作成
    File::makeDirectory(pathinfo($cacheFile, PATHINFO_DIRNAME), 0775, true, true);
    file_put_contents($cacheFile, json_encode($list));

    return [
      'status' => 'success',
      'cache' => false,
      'date' => $date,
      'trend_word' => $trendWord->trend_word,
      'words' => $list
    ];
  }

  /**
   * analyseText from file
   *
   * @param string $textFile php-fpm(mecab)から見えるpathを指定すること
   * @return string josn
   */
  private function analyseText($textFile)
  {
    $tmp = [];
    $dst = tempnam(storage_path(), 'dst_');
    try {
      exec("cat $textFile | mecab -d /var/www/neologd -o $dst", $output, $execRes);

      $csv = new SplFileObject($dst);
      $csv->setFlags(SplFileObject::READ_CSV);
      while (!$csv->eof()) {
        $line = $csv->current();
        if (count($line) > 1) {
          $p = explode("\t", $line[0]);
          if (
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
      // $list[$name] = $v[0];
      // $list[] = [$name, $v[0], $v[2], $v[3]];
      $list[] = ['text' => $name, 'size' => $v[0]];
    }
    usort($list, function ($a, $b) {
      return $b['size'] - $a['size'];
    });
    return $list;
  }

  /**
   * ユーザ生成SVGの取り込み (廃止)
   * チェック、フィルタして取り込み
   *
   * @param string $date
   * @param number $trendWordId
   * @param string $svgText
   * @return string josn
   */
  public function generateSvg($date, $trendWordId, $svgText)
  {
    $words = $this->analyseDailyTrendTweets($date, $trendWordId);
    if ($words['status'] !== 'success') {
      return;
    }
    $wordMap = [];
    foreach ($words['words'] as $word) {
      $wordMap[$word['text']] = 1;
    }

    $svg = [];
    $svg[] = join('', [
      '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">',
      '<svg width="960" height="480" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">',
      '<g transform="translate(480, 240)">',
    ]);
    $svgSrc = new SimpleXMLElement($svgText);
    foreach ($svgSrc->g->text as $t) {
      $text = (string) $t[0];
      if (!array_key_exists($text, $wordMap)) {
        return [];
        throw new Exception('iligal svg');
      }
      $attr = $t->attributes();
      if (preg_match('/translate\(([\-\d]+),([\-\d]+)\)/', $attr['transform'], $translate) !== 1) {
        throw new Exception('iligal svg');
      };
      if (preg_match('/rotate\(([\-\d]+)\)/', $attr['transform'], $rotate) !== 1) {
        throw new Exception('iligal svg');
      };
      if (preg_match('/font-size: (\d+px);/', $attr['style'], $size) !== 1) {
        throw new Exception('iligal svg');
      };
      if (preg_match('/rgb\((\d+), (\d+), (\d+)\);/', $attr['style'], $rgb) !== 1) {
        throw new Exception('iligal svg');
      };
      $svg[] = join('', [
        "<text text-anchor=\"middle\" transform=\"translate({$translate[1]},{$translate[2]})rotate({$rotate[1]})\"",
        " style=\"font-size: {$size[1]}; font-family: Impact; fill: rgb({$rgb[1]}, {$rgb[2]}, {$rgb[3]});\">{$text}</text>"
      ]);
    }
    $svg[] = '</g></svg>';

    $dir = storage_path() . "/app/public/word-cloud/{$date}";
    $file = "{$trendWordId}.svg";
    File::makeDirectory($dir, 0775, true, true);
    if (file_exists("$dir/$file")) {
      unlink("$dir/$file");
    }
    file_put_contents("$dir/$file", join('', $svg));

    return 'ok';
  }

  /**
   * トレンドワード検索
   * 
   * ワード一覧
   * 
   * @param string $word 検索ワード
   * @param number $page
   * @param number $maxPerPage ページ当たり件数
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
   * @param number $trendWordId
   * @param number $page
   * @param number $maxPerPage ページ当たり件数
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
