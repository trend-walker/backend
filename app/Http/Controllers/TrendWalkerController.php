<?php

namespace App\Http\Controllers;

use Request;

use App\Services\TrendService;

class TrendWalkerController extends Controller
{
  /**
   * @var TrendService
   */
  protected $trendService;

  /**
   * TrendWalkerController constructor.
   *
   * @param TrendService $trendService
   */
  public function __construct(TrendService $trendService)
  {
    $this->trendService = $trendService;
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
  
  /**
   * 関連トレンド
   *
   * @return void
   */
  public function relatedDailyTrend($date, $trendWordId)
  {
    return response($this->trendService->relatedDailyTrend($date, $trendWordId))
      ->header('Content-Type', 'application/json');
  }
}
