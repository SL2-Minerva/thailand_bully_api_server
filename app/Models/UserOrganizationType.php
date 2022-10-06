<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOrganizationType extends BaseModel
{
    use HasFactory;

    protected $table = 'tbl_user_organization_types';
    protected $guarded = [];
}
