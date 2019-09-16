<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Tweet extends Model
{
    protected $table = 'tweets';
    protected $primaryKey = 'id_str';
    public $timestamps = false;

    protected $fillable = [
        'id_str',
        'tweet'
    ];
}
