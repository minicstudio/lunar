<?php

namespace Lunar\Blog\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasUrls
{
    /**
     * Boot the HasUrls trait with Blog-specific URL generator.
     *
     * @return void
     */
    public static function bootHasUrls()
    {
        static::created(function (Model $model) {
            $generator = config('lunar.blog.urlGenerator', null);

            if ($generator) {
                app($generator)->handle($model);
            }
        });

        static::deleted(function (Model $model) {
            if (! $model->deleted_at) {
                $model->urls()->delete();
            }
        });
    }
}

