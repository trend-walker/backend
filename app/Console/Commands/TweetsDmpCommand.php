<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

use \Datetime;

class TweetsDmpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tweets:dmp {date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'dmp tweets';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $date = $this->argument("date");
        $start = new Datetime($date);
        $end  = (new Datetime($date))->modify('+1 day');

        $date = $start->format('Y-m-d');
        mkdir(storage_path() . "/app/${date}");

        $sql = <<<SQL
        select min(id) as min ,max(id) as max
        from trends
        where trend_time between ? and ?
SQL;
        $range = DB::select($sql, [$start->format('Y-m-d'), $end->format('Y-m-d')])[0];
        echo "$range->min - $range->max\n";

        $sql = <<<SQL
            select tweets.tweet
            from trends
            join trend_tweets on trends.id=trend_tweets.trend_id
            join tweets on tweets.id_str=trend_tweets.id_str
            where trends.id=?
SQL;
        for ($id = $range->min; $id <= $range->max; ++$id) {
            $res = [];
            foreach (DB::select($sql, [$id]) as &$v) {
                $res[] = json_decode($v->tweet, true);
            }
            file_put_contents(storage_path() . "/app/${date}/trend_tweets${id}.json.gz", gzencode(json_encode($res), 9));
            if ($id % 100 == 0) {
                echo "\r" . intval(($id - $range->min) * 100 / ($range->max - $range->min)). "% > $id";
            }
        }
        echo "\r100%\n";
    }
}
