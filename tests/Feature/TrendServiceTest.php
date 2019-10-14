<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use App\Services\TaskService;
use App\Services\TrendService;
use App\Model\Trend;

class TrendServiceTest extends TestCase
{
  use TestUtil;
  use MakesGraphQLRequests;

  public function setUp()
  {
    parent::setUp();

    $this->prepareTest();
  }

  /**
   * 定期取得処理
   */
  public function testFetchTask()
  {
    // ラッパー内TestCase
    $self = $this;
    // 実行時引数
    $args = [];
    // デイリートレンド解析 ラッパー
    $wrap = function ($date, $trendWordId) use (&$args, $self) {
      $args[] = [$date, $trendWordId];
      $res = $this->analyseDailyTrendTweets($date, $trendWordId);
      // キャッシュ不使用
      $self->assertTrue(!$res['cache']);
      return $res;
    };

    // ラッパー適用
    uopz_set_return(TrendService::class, 'analyseDailyTrendTweets', $wrap, true);

    // スタブを有効化
    $this->activateTwitterApiStub();
    // スタブ適用インスタンスで定期取得
    app()->make(TaskService::class)->fetchTask();

    // ラッパー解除
    uopz_unset_return(TrendService::class, 'analyseDailyTrendTweets');

    // 呼び出しあり
    $this->assertTrue(!empty($args));

    // 更新のない呼び出し
    $trendService = new TrendService();
    foreach ($args as $arg) {
      $res = $trendService->analyseDailyTrendTweets($arg[0], $arg[1]);
      // キャッシュ使用
      $this->assertTrue($res['cache']);
    }
  }

  /**
   * 取得データ
   *
   * @depends testFetchTask
   * @return void
   */
  public function testFetchData()
  {
    // 最終更新時間API取得
    $res = $this->postGraphQL([
      'query' => '
        query getTimeTrend($column: String!, $order: Order!) {
          tip(queryOrder: { column: $column, order: $order }) {
            trend_time
          }
        }
      ',
      'variables' => [
        "column" => "trend_time",
        "order" => "DESC",
      ]
    ]);

    // 最終更新時間
    $latest = Carbon::parse($res->json()['data']['tip']['trend_time']);

    // DBから最新取得
    $lastTrend = Trend::orderBy('trend_time', 'desc')->first();

    // テスト DBと比較
    $res->assertJson([
      'data' => [
        'tip' => [
          'trend_time' => $lastTrend->trend_time,
        ]
      ]
    ]);

    // 最新トレンドAPI取得
    $res = $this->postGraphQL([
      'query' => '
        query getTimeTrend($from: String!, $to: String!, $limit: Int) {
          timeTrend(trend_time: { from: $from, to: $to }, limit: $limit) {
            id
            trend_word_id
            trendWord {
              trend_word
            }
            tweet_volume
            trend_time
          }
        }
      ',
      'variables' => [
        "from" => $latest->copy()->subSeconds(60)->format('Y-m-d H:i:s'),
        "to" => $latest->format('Y-m-d H:i:s'),
        "limit" => 100
      ],
    ]);

    // テスト データ構造
    $res->assertJsonStructure([
      'data' => [
        'timeTrend' => [
          '*' => [
            "id",
            "trend_word_id",
            "trendWord" => [
              "trend_word",
            ],
            "tweet_volume",
            "trend_time",
          ]
        ]
      ],
    ]);

    // trend_word_id 抽出
    $trendWordIdList = collect($res->json()['data']['timeTrend'])->map(function ($v) {
      return $v["trend_word_id"];
    });

    // API取得IDでDBから
    $trends = DB::table('trends as t')
      ->join('trend_words as w', 'w.id', '=', 't.trend_word_id')
      ->whereIn('t.trend_word_id', $trendWordIdList)
      ->select('t.id', 't.trend_word_id', 'w.trend_word', 't.tweet_volume', 't.trend_time')
      ->get();

    // テスト DBから取得
    $this->assertNotEmpty($trends);

    // テスト 値
    foreach ($res->json()['data']['timeTrend'] as $i => $trend) {
      $this->assertEquals($trend['trendWord']['trend_word'], $trends[$i]->trend_word);
      $this->assertEquals($trend['tweet_volume'], $trends[$i]->tweet_volume);
    }

    // テスト ツイートログ
    foreach ($trends as $trend) {
      $path = sprintf(
        '%s/%s/trend_tweets%d.json.gz',
        config('constants.archive_path'),
        Carbon::parse($trend->trend_time)->format('Y-m-d'),
        $trend->id
      );
      $this->assertTrue(Storage::exists($path));
    }

    // ツイートログファイル取得
    $path = sprintf(
      '%s/%s/trend_tweets%d.json.gz',
      config('constants.archive_path'),
      Carbon::parse($trend->trend_time)->format('Y-m-d'),
      $trends[0]->id
    );
    $data = json_decode(gzdecode(Storage::get($path)), true);

    // テスト用レスポンス生成
    $response = $this->createTestResponse(response()->json($data));

    // テスト ツイートログ構造
    $response->assertJsonStructure([
      '*' => [
        'id_str',
        'full_text',
        'retweet_count',
        'favorite_count',
      ]
    ]);

    // テスト 解析ファイル
    foreach ($trends as $trend) {
      $path = sprintf(
        '%s/%s/%d.json.gz',
        config('constants.analyze_path'),
        Carbon::parse($trend->trend_time)->format('Y-m-d'),
        $trend->trend_word_id
      );
      $this->assertTrue(Storage::exists($path));
    }

    // 解析ファイル取得
    $path = sprintf(
      '%s/%s/%d.json.gz',
      config('constants.analyze_path'),
      Carbon::parse($trends[0]->trend_time)->format('Y-m-d'),
      $trends[0]->trend_word_id
    );
    $data = json_decode(gzdecode(Storage::get($path)), true);

    // テスト用レスポンス生成
    $response = $this->createTestResponse(response()->json($data));

    // テスト 解析データ構造
    $response->assertJsonStructure([
      'word_weights' => [
        '*' => [
          'text',
          'size'
        ]
      ],
      'value_per_hour' => [
        '*' => []
      ],
      'id_list' => [
        '*' => [
          'id_str',
          'favorite',
          'retweet'
        ]
      ]
    ]);
  }
}
