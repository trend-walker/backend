<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class TrendWord extends Model
{
    protected $table = 'trend_words';
    public $timestamps = false;
    
    protected $fillable = [
        'id',
        'trend_word'
    ];

    public function trendWords()
    {
        return $this->hasMany('App\Model\Trend', 'trend_word_id', 'id');
    }
}
