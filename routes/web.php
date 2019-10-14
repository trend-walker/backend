<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// 検証用
Route::group(['prefix' => 'trial'], function () {
  Route::middleware(\Barryvdh\Cors\HandleCors::class)->group(function () {
    // twittter api trends (dev only)
    Route::get('/twitter_api_get_trends', 'TrialController@getTresds')->name('trial.api.getTresds');
    // twitter api search (dev only)
    Route::get('/twitter_api_search_tweets/{text}', 'TrialController@searchTweets')->name('trial.api.searchTweets');
    // tweets from trend_id
    Route::get('/latest_time', 'TrialController@latestTime')->name('trial.get.latestTrend');
    // volume from word id
    Route::get('/volumes/{trendWordId}', 'TrialController@volumes')->name('trial.get.wordVolumes');
    // tweets from trend_id
    Route::get('/get_tweets/{trendId}', 'TrialController@getTweets')->name('trial.get.rawTweets');
    // analyze from trend_id
    Route::get('/analyze_tweets/{trendId}', 'TrialController@analyzeTweets')->name('trial.get.analyzeTrend');
    // related trends
    Route::get('/related_trends/{date}', 'TrialController@relatedTrends')->name('trial.get.relatedTrends');
  });
});

// front api
Route::group(['prefix' => 'api'], function () {
  Route::middleware(\Barryvdh\Cors\HandleCors::class)->group(function () {
    // daily trends
    Route::get('/daily_trends/{date}', 'TrendWalkerController@dailyTrends')->name('trends.daily');
    // trend word
    Route::get('/trend_word/{trendWordId}', 'TrendWalkerController@trendWord')->name('tweets.trendWord');
    // daily trend word
    Route::get('/daily_trend_word/{date}/{trendWordId}', 'TrendWalkerController@dailyTrendWord')->name('tweets.dailyTrendWord');
    // analyze daily trend word
    Route::get('/analyze_daily_tweets/{date}/{trendWordId}', 'TrendWalkerController@analyzeDailyTrend')->name('tweets.analyzeDailyTrend');
    // search trend word
    Route::get('/search_trend_word', 'TrendWalkerController@searchTrendWord')->name('trends.searchTrendWord');
    // search trend word Date
    Route::get('/search_trend_word_date/{trendWordId}', 'TrendWalkerController@searchTrendWordDate')->name('trends.searchTrendWordDate');
    // search trend tweet volume
    Route::get('/tweet_volume/{date}/{trendWordId}', 'TrendWalkerController@tweetVolume')->name('trends.tweetVolume');
    // related trends
    Route::get('/related_daily_trends/{date}/{trendWordId}', 'TrendWalkerController@relatedDailyTrend')->name('tweets.relatedDailyTrend');
  });
});

Route::get('/', function () {
  return view('welcome');
});
