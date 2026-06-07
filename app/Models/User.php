<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'username';

    protected $primaryKey = 'ID';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'loginname',
        'name',
        'username',
        'password',
        'rights',
        'idoffice',
        'idmapagency',
        'lastlogin',
    ];

    protected $hidden = [
        'password',
    ];

    public function getRememberTokenName()
    {
        return null;
    }
}