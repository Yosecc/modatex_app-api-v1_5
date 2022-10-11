<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientLocal extends Model
{
     protected $table = 'CLIENT_LOCAL';

     protected $primaryKey   = 'NUM';

    const CREATED_AT        = 'INSERT_DATE';

    const UPDATED_AT        = 'UPDATE_DATE';
}
