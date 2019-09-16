<?php

namespace App\GraphQL\Builder;

class Builder
{
  /*
   * Add a limit constrained upon the query.
   *
   * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
   * @param  mixed  $value
   * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
   */
  public function limit($builder, int $value)
  {
    return $builder->limit($value);
  }
}
