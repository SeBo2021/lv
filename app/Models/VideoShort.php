<?php


namespace App\Models;


use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;

class VideoShort extends BaseModel
{
    use Searchable;

    protected $table = 'video_short';

    protected array $mapping = [
        'properties' => [
            'name' => [
                'type' => 'text',
            ],
            'title' => [
                'type' => 'text',
            ],
            'tag' => [
                'type' => 'text',
            ],
            'cat' => [
                'type' => 'text',
            ],
        ]
    ];

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'video_short_index';
    }

    /**
     * 获取模型的可搜索数据。
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return $this->toArray();
    }
}
