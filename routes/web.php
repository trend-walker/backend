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


Route::get('/get_trend', 'TrendWalkerController@getTrend')->name('trend.get');
Route::get('/store_trend', 'TrendWalkerController@storeTrends')->name('trend.store');
Route::get('/store_trend_tweets', 'TrendWalkerController@storeTrendTweets')->name('TrendTweet.store');

// front api
Route::group(['prefix' => 'api'], function () {
    Route::middleware(\Barryvdh\Cors\HandleCors::class)->group(function () {
        // tweets from trend_id
        Route::get('/latest_time', 'TrendWalkerController@latestTime')->name('trends.latest');
        // daily trends
        Route::get('/daily_trends/{date}', 'TrendWalkerController@dailyTrends')->name('trends.daily');
        // tweets from trend_id
        Route::get('/get_tweets/{trendId}', 'TrendWalkerController@getTweets')->name('tweets.get');
        // analyze from trend_id
        Route::get('/analyze_tweets/{trendId}', 'TrendWalkerController@analyzeTweets')->name('tweets.analyzeTrend');
        //  trend word
        Route::get('/trend_word/{trendWordId}', 'TrendWalkerController@trendWord')->name('tweets.trendWord');
        //  daily trend word
        Route::get('/daily_trend_word/{date}/{trendWordId}', 'TrendWalkerController@dailyTrendWord')->name('tweets.dailyTrendWord');
        // analyze daily trend word
        Route::get('/analyze_daily_tweets/{date}/{trendWordId}', 'TrendWalkerController@analyzeDailyTrend')->name('tweets.analyzeDailyTrend');
        // get daily trend tweets
        Route::get('/get_tweets_list/{date}/{trendWordId}', 'TrendWalkerController@dailyTrendTweets')->name('tweets.dailyTrendweets');
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
