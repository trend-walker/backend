<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Trend extends Model
{
    protected $table = 'trends';
    public $timestamps = false;
    
    public function trendWord()
    {
        return $this->hasOne('App\Model\TrendWord', 'id', 'trend_word_id');
    }

    public function trendTweets()
    {
        return $this->hasMany('App\Model\TrendTweet', 'trend_id', 'id');
    }
}
