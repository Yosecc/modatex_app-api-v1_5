<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreFavorite extends Model
{
    protected $connection   = 'mysql';

    public const CAMPOS = ['GROUP_CD','LOCAL_CD'];

    protected $table = 'FAVORITE';
}
