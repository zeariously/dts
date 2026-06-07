<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsOffice extends Model
{
    protected $table = 'lu_office';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $guarded = [];
}