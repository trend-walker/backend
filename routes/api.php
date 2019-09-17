<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['api']], function () {
    // Route::get('/trends', 'TrendWalkerController@trends')->name('api.trends.get');
    // Route::get('/volumes', 'TrendWalkerController@volumes')->name('api.volumes.get');
    // Route::get('/thattime', 'TrendWalkerController@thattime')->name('api.thattime.get');

    // user gen svg
    // Route::post('/gen_svg', 'TrendWalkerController@generateSvg')->name('tweets.generateSvg')->middleware(\Barryvdh\Cors\HandleCors::class);
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
