<?php

namespace App\Jobs;

use App\Models\Carousel;
use App\TraitClass\AboutEncryptTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCarousel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AboutEncryptTrait, PHPRedisTrait;

    public $carousel;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row)
    {
        //
        $this->carousel = $row;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle(): void
    {
        //
        $model = $this->carousel;
        $carousels = Carousel::query()
            ->where('cid', $model->cid)
            ->get(['id','title','img','url','action_type','vid','status','end_at'])->toArray();
        $domain = env('API_RESOURCE_DOMAIN2');
        foreach ($carousels as &$carousel){
            $carousel['img'] = $this->transferImgOut($carousel['img'],$domain,date('Ymd'),'auto');
            $carousel['action_type'] = (string) $carousel['action_type'];
            $carousel['vid'] = (string) $carousel['vid'];
        }
        $this->redis()->set('api_carousel_'.$model->cid,json_encode($carousels,JSON_UNESCAPED_UNICODE));
        $this->syncUpload($model->img);
    }
}
