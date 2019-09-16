<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class TrendTweet extends Model
{
    protected $table = 'trend_tweets';
    public $timestamps = false;
    
    public function trend()
    {
        return $this->hasOne('App\Model\Trend', 'id', 'trend_id');
    }
    
    public function tweet()
    {
        return $this->hasOne('App\Model\Tweet', 'id_str', 'id_str');
    }
}
