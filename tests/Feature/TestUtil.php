<?php

namespace Tests\Feature;

use Faker;
use Mockery;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

use App\Services\TwitterApi;
use App\Model\Trend;
use App\Model\TrendWord;

trait TestUtil
{
  /**
   * @var int
   */
  static $setUpCount = 0;

  /**
   * @var array
   */
  private $trends = [];

  /**
   * @return array
   */
  public function getCurrentTrends()
  {
    return $this->trends;
  }

  /**
   * @var array
   */
  private $tweets = [];

  /**
   * @return array
   */
  public function getCurrentTweets()
  {
    return $this->tweets;
  }

  /**
   * テスト準備
   *
   * @return int 呼び出し回数
   */
  public function prepareTest($init = false)
  {
    // 初回のみ初期化
    if (self::$setUpCount == 0 || $init) {
      Artisan::call('cache:clear');
      Artisan::call('config:clear');
      Artisan::call('migrate:fresh');
      Storage::deleteDirectory('testing');

      $faker = Faker\Factory::create();

      // シーケンスに乱数
      $trends = new Trend();
      $trends->id = $faker->numberBetween(10000, 20000);
      $trends->trend_word_id = 1;
      $trends->trend_time = Carbon::now();
      $trends->save();
      $trends->delete();
      $trendword = new TrendWord();
      $trendword->id = $faker->numberBetween(1000, 2000);
      $trendword->trend_word = '';
      $trendword->save();
      $trendword->delete();
    }
    return ++self::$setUpCount;
  }

  /**
   * TwitterApi スタブを有効化
   * 
   * @param boolen $dataFromFaker
   */
  public function activateTwitterApiStub($dataFromFaker = true)
  {
    // Faker ja_JPは要手動GC
    gc_collect_cycles();
    $faker = $dataFromFaker ? Faker\Factory::create('ja_JP') : null;

    // スタブ作成
    $stub = Mockery::mock(TwitterApi::class);
    $stub->shouldReceive('getTrends')->andReturnUsing(function () use ($faker) {
      $this->trends = $this->createTrendsStub($faker);
      return $this->trends;
    });
    $stub->shouldReceive('searchTweets')->andReturnUsing(function ($word) use ($faker) {
      $this->tweets = $this->createTweetsStub($faker);
      return $this->tweets;
    });

    // スタブ適用
    $this->instance(TwitterApi::class, $stub);
  }

  /**
   * createTrendsStub
   *
   * @param Faker\Generator $faker
   * @return array
   */
  public function createTrendsStub($faker = null)
  {
    if (is_null($faker)) {
      return json_decode(file_get_contents(base_path() . '/tests/MockData/api_trends-place.json'), true);
    } else {
      $trends = [];
      for ($i = 0; $i < 50; ++$i) {
        $trends[] = [
          'name' => $faker->realText($faker->numberBetween(10, 20)),
          'tweet_volume' => $faker->numberBetween(0, 1) == 0 ? $faker->numberBetween(10000, 100000) : null
        ];
      }
      return [['trends' => $trends]];
    }
  }

  /**
   * createTweetsStub
   *
   * @param Faker\Generator $faker
   * @return array
   */
  public function createTweetsStub($faker = null)
  {
    if (is_null($faker)) {
      return json_decode(file_get_contents(base_path() . '/tests/MockData/api_search-tweets.json'), true);
    } else {
      $textpool = $faker->realText(600);
      $statuses = [];
      for ($i = 0; $i < 100; ++$i) {
        $date = Carbon::parse($faker->dateTimeBetween('-1 days', 'now')->format('D M d H:i:s +0000 Y'));
        $statuses[] = [
          'created_at' => $date->format('D M d H:i:s +0000 Y'),
          'id' => TwitterApi::timestampToId($date->getTimestamp()),
          'id_str' => (string) TwitterApi::timestampToId($date->getTimestamp()),
          'full_text' => mb_substr($textpool, $i * 5, 100) . "\n",
          'retweet_count' => $faker->numberBetween(0, 100),
          'favorite_count' => $faker->numberBetween(0, 100)
        ];
      }
      return ['statuses' => $statuses];
    }
  }
}
