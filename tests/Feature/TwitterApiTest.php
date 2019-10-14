<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Carbon;

use App\Services\TwitterApi;

/**
 * TwitterApiTest class
 */
class TwitterApiTest extends TestCase
{
  use TestUtil;

  /**
   * テスト準備
   *
   * @return void
   */
  public function setUp()
  {
    parent::setUp();

    // スタブを有効化
    $this->activateTwitterApiStub();
    // スタブ適用インスタンス
    $this->twitterApi = app()->make(TwitterApi::class);
  }

  /**
   * 日付ID相互変換チェック
   */
  public function testTwitterApiIdTimestamp()
  {
    /**
     * id, timestamp 相互変比較
     * 
     * @param int $id
     * @param string $timeText
     * @return boolean
     */
    function comp($id, $text)
    {
      $timestampId = TwitterApi::idToimestamp($id);
      $dateId = Carbon::createFromTimestampUTC($timestampId);
      $dateText = Carbon::parse($text);
      $textId = TwitterApi::timestampToId($dateText->getTimestamp());
      return $id == $textId &&
        $dateId->format('Y-m-d H:i:s') == $dateText->format('Y-m-d H:i:s');
    };

    // Fakerデータ
    $this->activateTwitterApiStub(true);
    $stabTweets = app()->make(TwitterApi::class)->searchTweets('');
    foreach ($stabTweets['statuses'] as $tweet) {
      $this->assertTrue(comp($tweet['id'], $tweet['created_at']));
    }

    // ファイルデータ
    $this->activateTwitterApiStub(true);
    $fileTweets = app()->make(TwitterApi::class)->searchTweets('');
    foreach ($fileTweets['statuses'] as $tweet) {
      $this->assertTrue(comp($tweet['id'], $tweet['created_at']));
    }
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
