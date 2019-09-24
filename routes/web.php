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
    // api search (local)
    Route::get('/search/{text}', 'TrendWalkerController@search')->name('trial.get.search');
    // tweets from trend_id
    Route::get('/latest_time', 'TrendWalkerController@latestTime')->name('trial.get.latestTrend');
    // volume from word id
    Route::get('/volumes/{trendWordId}', 'TrendWalkerController@volumes')->name('trial.get.wordVolumes');
    // tweets from trend_id
    Route::get('/get_tweets/{trendId}', 'TrendWalkerController@getTweets')->name('trial.get.rawTweets');
    // analyze from trend_id
    Route::get('/analyze_tweets/{trendId}', 'TrendWalkerController@analyzeTweets')->name('trial.get.analyzeTrend');
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
  });
});

Route::get('/', function () {
  return view('welcome');
});
