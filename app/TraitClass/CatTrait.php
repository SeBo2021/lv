<?php

namespace App\TraitClass;

use App\Models\Category;
use Illuminate\Support\Facades\Log;

trait CatTrait
{
    public function getCats($parentId = 2)
    {
        $topCat = Category::query()
            ->where('parent_id',$parentId)
            ->where('is_checked',1)
            ->orderBy('sort')
            ->get(['id','name','sort'])
            ->toArray();
        Log::info('==topCat===',[$topCat]);
        $topCatIds = [];
        foreach ($topCat as $item)
        {
            $topCatIds[] = $item['id'];
        }
        if(!empty($topCatIds)){
            if ($parentId != 2) {
                return Category::query()
                    ->where('is_checked',1)
                    ->whereIn('id',$topCatIds)
                    ->orderBy('sort')
                    ->get(['id','name'])->toArray();
            }
            return Category::query()
                ->where('is_checked',1)
                ->whereIn('parent_id',$topCatIds)
                ->orderBy('sort')
                ->get(['id','name'])->toArray();
        }
        return [];
    }

    public function getCatName($cat,$parentId =2)
    {
        $topCat = $this->getCats($parentId);
        $catArr = json_decode($cat, true);
        $name = '';
        $characters = '||';
        foreach ($topCat as $item)
        {
            if(in_array($item['id'],$catArr)){
                $name .= $item['name'].$characters;
            }
        }
        return rtrim($name,$characters);
    }
}