<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsDistribution extends Model
{
    protected $table = 'distribution';
    protected $primaryKey = 'IDdist';
    public $timestamps = false;

    public $incrementing = false;
    protected $keyType = 'int';

    protected $guarded = [];

    public function document()
    {
        return $this->belongsTo(DtsDocument::class, 'IDdoc', 'IDdoc');
    }

    public function office()
    {
        return $this->belongsTo(DtsOffice::class, 'IDoffice', 'ID');
    }
}