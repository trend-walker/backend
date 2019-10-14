<?php

namespace App\Services;

use Abraham\TwitterOAuth\TwitterOAuth;

/**
 * TwitterApi class
 * APIアクセスはココに集約
 */
class TwitterApi
{
  /**
   * get twitter api Connection
   * API集約のためprivate
   *
   * @return TwitterOAuth
   */
  private function getApiConnection()
  {
    $connection = new TwitterOAuth(
      config('env.TWITTER_CONSUMER_KEY'),
      config('env.TWITTER_CONSUMER_SECRET'),
      config('env.TWITTER_ACCESS_TOKEN'),
      config('env.TWITTER_ACCESS_TOKEN_SECRET')
    );
    $connection->setDecodeJsonAsArray(true);
    return $connection;
  }

  /**
   * トレンド取得
   * 
   * @param int $id 地域ID (初期値は日本:23424856)
   * @return array
   */
  public function getTrends($id = 23424856)
  {
    $connection = $this->getApiConnection();
    return $connection->get("trends/place", ['id' => $id]);
  }

  /**
   * ツイート検索
   * 
   * @param string $word
   * @param string $lang
   * @param string $locale
   * @param string $resultType
   * @param int $count
   * @return array
   */
  public function searchTweets(
    $word,
    $lang = 'ja',
    $locale = 'ja',
    $resultType = 'mixed',
    $count = 100
  ) {
    $connection = $this->getApiConnection();
    return $connection->get("search/tweets", [
      'q' => $word,
      'tweet_mode' => 'extended',
      'lang' => $lang,
      'locale' => $locale,
      'result_type' => $resultType,
      'count' => $count,
    ]);
  }

  /**
   * timestampToId
   *
   * @param int $timestamp
   * @return int
   */
  static public function timestampToId($timestamp)
  {
    $id = ($timestamp * 1000 - 1288834974657) << 22;
    return $id;
  }

  /**
   * idToimestamp
   *
   * @param int $id
   * @return int
   */
  static public function idToimestamp($id)
  {
    $timestamp = (($id >> 22) + 1288834974657) / 1000;
    return (int) $timestamp;
  }
}
