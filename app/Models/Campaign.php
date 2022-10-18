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



}
