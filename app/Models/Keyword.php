<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Keyword extends BaseModel
{
    use HasFactory;

    protected $table = 'tbl_keywords';
    protected $guarded = [];
}
