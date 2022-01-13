<?php

namespace App\TraitClass;

use App\Models\Tag;

trait TagTrait
{

    public function getTagData($usage = 1)
    {
        return Tag::query()->where('usage',$usage)->get(['id','name'])->toArray();
    }

    public function getTagName($tag,$usage = 1)
    {
        $tagData = $this->getTagData($usage);
        $tagArr = json_decode($tag, true);
        $name = '';
        $characters = '||';
        foreach ($tagData as $item)
        {
            if(in_array($item['id'],$tagArr)){
                $name .= $item['name'].$characters;
            }
        }
        return rtrim($name,$characters);
    }
}