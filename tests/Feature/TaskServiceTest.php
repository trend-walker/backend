<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

use App\Services\TwitterApi;
use App\Services\TaskService;

class TaskServiceTest extends TestCase
{
  use RefreshDatabase;

  /**
   * @var TaskService
   */
  protected $taskService;

  /**
   * @var arry
   */
  protected $trendIdList;

  /**
   * テスト準備
   *
   * @return void
   */
  public function setUp()
  {
    parent::setUp();

    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Storage::deleteDirectory('testing');

    // モック作成
    $mock = Mockery::mock(TwitterApi::class);
    $mock->shouldReceive('getTrends')
      ->andReturn(json_decode(file_get_contents(base_path() . '/tests/MockData/api_trends-place.json'), true));
    $mock->shouldReceive('searchTweets')
      ->andReturn(json_decode(file_get_contents(base_path() . '/tests/MockData/api_search-tweets.json'), true));

    // モック適用
    $this->instance(TwitterApi::class, $mock);

    // モック適用インスタンス
    $this->taskService = app()->make(TaskService::class);
    
    // トレンド定期取得
    $this->trendIdList = $this->taskService->fetchTask();
  }

  /**
   * トレンド定期取得処理
   *
   * @return void
   */
  public function testTaskFetch()
  {
    // 追加データ
    $trends = DB::table('trends as t')
      ->join('trend_words as w', 'w.id', '=', 't.trend_word_id')
      ->whereIn('t.trend_word_id', $this->trendIdList)
      ->select('w.trend_word', 't.tweet_volume', 't.trend_time')
      ->get();

    // トレンド取得
    $res = $this->getJson(route('trial.api.getTresds'));

    // テスト データ構造
    $res->assertJsonStructure([
      0 => [
        'trends' => [
          '*' => [
            'name',
            'tweet_volume'
          ]
        ]
      ],
    ]);

    // テスト 値
    foreach ($res->json()[0]['trends'] as $i => $trend) {
      $this->assertEquals($trend['name'], $trends[$i]->trend_word);
      $this->assertEquals($trend['tweet_volume'], $trends[$i]->tweet_volume);
    }
  }

  /**
   * 定期取得保存ツイート
   * 
   * @return void
   */
  public function testTaskArchive()
  {
    // 追加データ
    $trends = DB::table('trends as t')
      ->join('trend_words as w', 'w.id', '=', 't.trend_word_id')
      ->whereIn('t.trend_word_id', $this->trendIdList)
      ->select('w.id', 'w.trend_word', 't.tweet_volume', 't.trend_time')
      ->get();

    // テスト Archive file
    foreach ($trends as $trend) {
      $path = sprintf(
        '%s/%s/trend_tweets%d.json.gz',
        config('constants.archive_path'),
        Carbon::parse($trend->trend_time)->format('Y-m-d'),
        $trend->id
      );
      $this->assertTrue(Storage::exists($path));
    }

    // テスト analyze file
    foreach ($trends as $trend) {
      $path = sprintf(
        '%s/%s/%d.json.gz',
        config('constants.analyze_path'),
        Carbon::parse($trend->trend_time)->format('Y-m-d'),
        $trend->id
      );
      $this->assertTrue(Storage::exists($path));
    }

    $path = sprintf(
      '%s/%s/%d.json.gz',
      config('constants.analyze_path'),
      Carbon::parse($trends[0]->trend_time)->format('Y-m-d'),
      $trends[0]->id
    );

    $data = json_decode(gzdecode(Storage::get($path)), true);

    // レスポンス生成
    $response = $this->createTestResponse(response()->json($data));

    // テスト データ構造
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
