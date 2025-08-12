<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripePlan extends Model
{
    protected $guarded = [];

    protected $hidden = [
        "created_at","deleted_at","updated_at"
    ];
}
