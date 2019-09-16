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
     * DesignController constructor.
     *
     * @param TrendService $trendService
     */
    public function __construct(TrendService $trendService)
    {
        $this->trendService = $trendService;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function trends()
    {
        $time = Request::get('t');
        return response()->json($this->trendService->trends($time));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function volumes()
    {
        $wordId = Request::get('id');
        return response()->json($this->trendService->volumes($wordId));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function latestTime()
    {
        return response($this->trendService->getLatestTime())
            ->header('Content-Type', 'application/json');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function dailyTrends($date)
    {
        $limit = ctype_digit(Request::get('limit')) ? (int) Request::get('limit') : 10;
        return response($this->trendService->dailyTrends($date, $limit))
            ->header('Content-Type', 'application/json');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getTweets($trendId)
    {
        return response($this->trendService->getTrendTweets($trendId))
            ->header('Content-Type', 'application/json');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function analyzeTweets($trendId)
    {
        return response($this->trendService->analyseTrendTweets($trendId))
            ->header('Content-Type', 'application/json');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function trendWord($trendWordId)
    {
        return response($this->trendService->trendWord($trendWordId))
            ->header('Content-Type', 'application/json');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function dailyTrendWord($date, $trendWordId)
    {
        return response($this->trendService->dailyTrendWord($date, $trendWordId))
            ->header('Content-Type', 'application/json');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function analyzeDailyTrend($date, $trendWordId)
    {
        return response($this->trendService->analyseDailyTrendTweets($date, $trendWordId))
            ->header('Content-Type', 'application/json');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function dailyTrendTweets($date, $trendWordId)
    {
        return response($this->trendService->dailyTrendTweets($date, $trendWordId))
            ->header('Content-Type', 'application/json');
    }

    /**
     * ユーザ生成SVGの取り込み
     *
     * @return void
     */
    public function generateSvg()
    {
        return $this->trendService->generateSvg(
            Request::get('date'),
            Request::get('trendWordId'),
            Request::get('svg')
        );
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
