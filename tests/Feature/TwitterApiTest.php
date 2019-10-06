<?php

namespace Tests\Feature;

use Mockery;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

use App\Services\TwitterApi;

/**
 * TwitterApiTest class
 */
class TwitterApiTest extends TestCase
{
  /**
   * @var TwitterApi $twitterApi
   */
  protected $twitterApi;

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
    $this->twitterApi = app()->make(TwitterApi::class);
  }

  /**
   * Twitter Api Mock Get Trends
   *
   * @return void
   */
  public function testTwitterApiMockGetTrends()
  {
    // トレンド取得
    $data = $this->twitterApi->getTrends();

    // レスポンス生成
    $response = $this->createTestResponse(response()->json($data));

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
  public function testTwitterApiMockSearchTweets()
  {
    // トレンド取得
    $data = $this->twitterApi->searchTweets('検索ワード');

    // レスポンス生成
    $response = $this->createTestResponse(response()->json($data));

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
      $this->assertTrue(
        array_key_exists('text', $tweet) && is_string($tweet['text']) ||
        array_key_exists('full_text', $tweet) && is_string($tweet['full_text'])
      );
      $this->assertTrue(is_int($tweet['retweet_count']));
      $this->assertTrue(is_int($tweet['favorite_count']));
    }
  }
}
