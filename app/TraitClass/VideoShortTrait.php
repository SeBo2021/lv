<?php

namespace App\TraitClass;

use App\Models\AdminVideoShort;

trait VideoShortTrait
{
    use PHPRedisTrait;

    public function resetRedisVideoShort($model)
    {
        $mapNum = $model->id % 300;
        $cacheKey = "short_video_$mapNum";
        $this->redis()->hSet($cacheKey, $model->id, json_encode([
            "id" => $model->id,
            "name" => $model->name,
            "cid" => $model->cid,
            "cat" => $model->cat,
            "tag" => $model->tag,
            "restricted" => $model->restricted,
            "sync" => $model->sync,
            "title" => $model->title,
            "url" => $model->url,
            "dash_url" => $model->dash_url,
            "hls_url" => $model->hls_url,
            "gold" => $model->gold,
            "duration" => $model->duration,
            "type" => $model->type,
            "views" => $model->views,
            "likes" => $model->likes,
            "comments" => $model->comments,
            "cover_img" => $model->cover_img,
            "updated_at" => $model->updated_at,
        ]));
        $ids = AdminVideoShort::where('status',1)->pluck('id')->toArray();
        $this->redis()->set('shortVideoIds',implode(',',$ids));
    }
}