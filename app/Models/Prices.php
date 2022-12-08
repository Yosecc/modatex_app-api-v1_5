<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prices extends Model
{
    protected $table = 'T_MODELO_VENTA';

    protected $connection   = 'oracle';

    public function __construct(){
      config(['database.connections.mysql.prefix'=> '']);
      config(['database.connections.oracle.prefix'=> '']);
    }
}
