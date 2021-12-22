<?php

namespace App\Models;

class ShortComment extends BaseModel
{
    protected $table = 'comments_short';
    public const CREATED_AT = 'reply_at';

    public const UPDATED_AT = NULL;
}