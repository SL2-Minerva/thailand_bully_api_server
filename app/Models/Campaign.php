<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends BaseModel
{
    use HasFactory;

    protected $table = 'user_campaigns';
    protected $guarded = [];

    public const DOMAIN_ID = 'domain_id';
    public const LABEL = 'label';
    public const  KEYWORD_OR = 'keyword_or';
    public const  KEYWORD_AND = 'keyword_and';
    public const  KEYWORD_EXCLUDE = 'keyword_exclude';

    protected $casts = [
        self::KEYWORD_OR => array(),
        self::KEYWORD_AND => array(),
        self::KEYWORD_EXCLUDE => array(),
    ];
}
