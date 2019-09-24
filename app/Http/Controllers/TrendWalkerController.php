<?php

namespace App\Http\Controllers;

use Request;

use App\Services\TaskService;
use App\Services\TrendService;

class TrendWalkerController extends Controller
{
  /**
   * @var TrendService
   */
  protected $trendService;

  /**
   * @var TaskService
   */
  protected $taskService;

  /**
   * DesignController constructor.
   *
   * @param TrendService $trendService
   */
  public function __construct(TrendService $trendService, TaskService $taskService)
  {
    $this->taskService = $taskService;
    $this->trendService = $trendService;
  }

  /**
   * search (検証用)
   */
  public function search($text)
  {
    if (config('app.APP_ENV')=='local') {
      $connection = $this->taskService->getApiConnection();
      $content = $connection->get("search/tweets", [
        'q' => $text,
        'lang' => 'ja',
        'locale' => 'ja',
        'result_type' => 'mixed',
        'tweet_mode' => 'extended',
        'count' => 100,
      ]);
      return response()->json($content);
    } else {
      return response()->json(['status' => 'suspend.']);
    }
  }

  /**
   * latest trend time (検証用)
   */
  public function latestTime()
  {
    return response($this->trendService->latestTime())
      ->header('Content-Type', 'application/json');
  }

  /**
   * volumes from trendWordId (検証用)
   * 
   * @param int $trendWordId
   */
  public function volumes($trendWordId)
  {
    return response($this->trendService->volumes($trendWordId))
      ->header('Content-Type', 'application/json');
  }

  /**
   * raw tweets from trendId (検証用)
   * data from archive file
   * 
   * @param int $trendId
   */
  public function getTweets($trendId)
  {
    return response($this->trendService->getTrendTweets($trendId))
      ->header('Content-Type', 'application/json');
  }

  /**
   * 重み付きトレンドワード from trendId (検証用)
   * 
   * @param int $trendId
   */
  public function analyzeTweets($trendId)
  {
    return response($this->trendService->analyseTrendTweets($trendId))
      ->header('Content-Type', 'application/json');
  }

  /**
   * デイリートレンドワード
   * 
   * @param string $date 日付
   */
  public function dailyTrends($date)
  {
    $limit = ctype_digit(Request::get('limit')) ? (int) Request::get('limit') : 10;
    return response($this->trendService->dailyTrends($date, $limit))
      ->header('Content-Type', 'application/json');
  }

  /**
   * トレンドワード
   * SSR用
   *
   * @param int $trendWordId
   */
  public function trendWord($trendWordId)
  {
    return response($this->trendService->trendWord($trendWordId))
      ->header('Content-Type', 'application/json');
  }

  /**
   * デイリー・個別トレンドワード
   * SSR用
   *
   * @param string $date 日付
   * @param int $trendWordId
   */
  public function dailyTrendWord($date, $trendWordId)
  {
    return response($this->trendService->dailyTrendWord($date, $trendWordId))
      ->header('Content-Type', 'application/json');
  }

  /**
   * デイリートレンド解析
   *
   * @param string $date 日付
   * @param int $trendWordId
   */
  public function analyzeDailyTrend($date, $trendWordId)
  {
    return response($this->trendService->analyseDailyTrendTweets($date, $trendWordId))
      ->header('Content-Type', 'application/json');
  }

  /**
   * トレンドワード検索
   *
   * @return void
   */
  public function searchTrendWord()
  {
    return $this->trendService->searchTrendWord(
      Request::get('word') ?? '',
      ctype_digit(Request::get('page')) ? (int) Request::get('page') : 1
    );
  }

  /**
   * トレンドワードが含まれる日
   *
   * @return void
   */
  public function searchTrendWordDate($trendWordId)
  {
    return $this->trendService->searchTrendWordDate(
      (int) $trendWordId,
      ctype_digit(Request::get('page')) ? (int) Request::get('page') : 1
    );
  }

  /**
   * ツイート件数
   *
   * @return void
   */
  public function tweetVolume($date, $trendWordId)
  {
    return response($this->trendService->tweetVolume($date, $trendWordId))
      ->header('Content-Type', 'application/json');
  }
}
