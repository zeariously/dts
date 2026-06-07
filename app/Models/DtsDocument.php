<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsDocument extends Model
{
    protected $table = 'document';
    protected $primaryKey = 'IDdoc';
    public $timestamps = false;

    public $incrementing = false;
    protected $keyType = 'int';

    protected $guarded = [];

    public function docType()
    {
        return $this->belongsTo(DtsDocType::class, 'IDdoctype', 'ID');
    }

    public function status()
    {
        return $this->belongsTo(DtsDocStatus::class, 'IDdocstatus', 'ID');
    }

    public function fromOffice()
    {
        return $this->belongsTo(DtsOffice::class, 'IDfrom', 'ID');
    }

    public function forOffice()
    {
        return $this->belongsTo(DtsOffice::class, 'IDfor', 'ID');
    }

    public function distributions()
    {
        return $this->hasMany(DtsDistribution::class, 'IDdoc', 'IDdoc');
    }

    public function docTransactions()
    {
        return $this->hasMany(DtsDocTransaction::class, 'IDdoc', 'IDdoc');
    }
}