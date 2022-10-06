<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sources extends BaseModel
{
    use HasFactory;

    protected $table = 'tbl_sources';
    protected $guarded = [];
}
