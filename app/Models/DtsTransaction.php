<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsTransaction extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $guarded = [];
}