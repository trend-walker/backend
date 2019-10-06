<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\TrendService;
use App\Services\TwitterApi;

/**
 * 検証用クラス
 */
class TrialController extends Controller
{
  /**
   * @var TrendService
   */
  protected $trendService;

  /**
   * @var TwitterApi
   */
  protected $twitterApi;

  /**
   * TrialController constructor.
   *
   * @param TrendService $trendService
   */
  public function __construct(TrendService $trendService, TwitterApi $twitterApi)
  {
    $this->trendService = $trendService;
    $this->twitterApi = $twitterApi;
  }

  /**
   * Twitter API トレンド取得
   */
  public function getTresds()
  {
    if (config('app.env') !== 'prod') {
      $content = $this->twitterApi->getTrends();
      return response()->json($content);
    } else {
      return response()->json(['status' => 'suspend.']);
    }
  }

  /**
   * Twitter API ツイート検索
   */
  public function searchTweets($text)
  {
    if (config('app.env') !== 'prod') {
      $content = $this->twitterApi->searchTweets($text);
      return response()->json($content);
    } else {
      return response()->json(['status' => 'suspend.']);
    }
  }

  /**
   * latest trend time
   */
  public function latestTime()
  {
    return response($this->trendService->latestTime())
      ->header('Content-Type', 'application/json');
  }

  /**
   * volumes from trendWordId
   * 
   * @param int $trendWordId
   */
  public function volumes($trendWordId)
  {
    return response($this->trendService->volumes($trendWordId))
      ->header('Content-Type', 'application/json');
  }

  /**
   * raw tweets from trendId
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
   * 重み付きトレンドワード from trendId
   * 
   * @param int $trendId
   */
  public function analyzeTweets($trendId)
  {
    return response($this->trendService->analyseTrendTweets($trendId))
      ->header('Content-Type', 'application/json');
  }
}
