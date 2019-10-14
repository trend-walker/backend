<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Services\TaskService;

use App\Model\Trend;

class TrialControllerTest extends TestCase
{
  use TestUtil;

  /**
   * @var TaskService
   */
  public $taskService;

  public function setUp()
  {
    parent::setUp();

    if ($this->prepareTest() == 1) {
      // スタブを有効化
      $this->activateTwitterApiStub();
      // スタブ適用インスタンス
      $this->taskService = app()->make(TaskService::class);
      // 定期取得処理
      $this->taskService->fetchTask();
    } else {
      $this->activateTwitterApiStub();
      $this->taskService = app()->make(TaskService::class);
    }
  }

  /**
   * Trial Api Mock Get Trends
   *
   * @return void
   */
  public function testTrialApiMockGetTrends()
  {
    // トレンド取得
    $response = $this->getJson(route('trial.api.getTresds'));

    // テスト 成功
    $response->assertStatus(200);

    // テスト データ構造
    $response->assertJsonStructure([
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
    foreach ($response->json()[0]['trends'] as $trend) {
      $this->assertTrue(is_string($trend['name']));
      $this->assertTrue(is_int($trend['tweet_volume']) || is_null($trend['tweet_volume']));
    }
  }

  /**
   * Twitter Api Mock Search Tweets
   *
   * @return void
   */
  public function testTrialApiMockSearchTweets()
  {
    // トレンド取得
    $response = $this->getJson(route('trial.api.searchTweets', ['text' => '検索ワード']));

    // テスト 成功
    $response->assertStatus(200);

    // テスト データ構造
    $response->assertJsonStructure([
      'statuses' => [
        '*' => [
          'id_str',
          'full_text',
          'retweet_count',
          'favorite_count',
        ]
      ]
    ]);

    // テスト 値
    foreach ($response->json()['statuses'] as $tweet) {
      $this->assertTrue(preg_match('/\A\d+\z/', $tweet['id_str']) == 1);
      $this->assertTrue(is_string($tweet['full_text']));
      $this->assertTrue(is_int($tweet['retweet_count']));
      $this->assertTrue(is_int($tweet['favorite_count']));
    }
  }

  /**
   * Trial Get Raw Tweets
   *
   * @return void
   */
  public function testTrialGetRawTweets()
  {
    // トレンド取得
    $trend = Trend::first();
    $response = $this->getJson(route('trial.get.rawTweets', ['trendId' => $trend->id]));

    // テスト 成功
    $response->assertStatus(200);

    // テスト データ構造
    $response->assertJsonStructure([
      '*' => [
        'id_str',
        'retweet_count',
        'favorite_count',
      ]
    ]);

    // テスト 値
    foreach ($response->json() as $idStr => $tweet) {
      $this->assertTrue(preg_match('/\A\d+\z/', $idStr) == 1);
      $this->assertTrue(
        array_key_exists('text', $tweet) && is_string($tweet['text']) ||
          array_key_exists('full_text', $tweet) && is_string($tweet['full_text'])
      );
      $this->assertTrue(is_int($tweet['retweet_count']));
      $this->assertTrue(is_int($tweet['favorite_count']));
    }
  }

  /**
   * Trial Analyze Trend
   *
   * @return void
   */
  public function testTrialAnalyzeTrend()
  {
    // トレンド取得
    $trend = Trend::first();
    $res = $this->getJson(route('trial.get.analyzeTrend', ['trendId' => $trend->id]));

    // テスト 成功
    $res->assertStatus(200);

    // テスト データ構造
    $res->assertJsonStructure([
      '*' => [
        'text',
        'size',
      ]
    ]);

    // テスト 値
    foreach ($res->json() as $wordInfo) {
      $this->assertTrue(is_string($wordInfo['text']));
      $this->assertTrue(is_int($wordInfo['size']));
    }
  }
}
