<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsDocStatus extends Model
{
    protected $table = 'lu_docstatus';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $guarded = [];
}