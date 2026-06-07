<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsDocTransaction extends Model
{
    protected $table = 'docs_transactions';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    public $incrementing = false;
    protected $keyType = 'int';

    protected $guarded = [];

    public function document()
    {
        return $this->belongsTo(DtsDocument::class, 'IDdoc', 'IDdoc');
    }

    public function transaction()
    {
        return $this->belongsTo(DtsTransaction::class, 'IDtransac', 'ID');
    }
}