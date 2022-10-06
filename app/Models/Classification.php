<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classification extends BaseModel
{
    use HasFactory;

    protected $table = 'tbl_user_classifications';
    protected $guarded = [];
}
