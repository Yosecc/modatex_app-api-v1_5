<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubcategoryPreferences extends Model
{
    protected $connection = 'mysql';
    
    protected $table = 'API_SUBCATEGORY_PREFERENCES';

    // public function subcategory()
    // {
    //     return $this->hasOne(TipoModeloDos::class,'NUM','TIPOMODELO_DP2_NUM')
    //                 ->select('NUM','TIPO_NAME','DESCRIPTION');
    // }
    
}
