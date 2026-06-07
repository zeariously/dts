<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsDocType extends Model
{
    protected $table = 'lu_doctype';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $guarded = [];
}