<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryPreferences extends Model
{
    protected $connection = 'mysql';
    protected $table = 'API_CATEGORY_PREFERENCES';

    public function category()
    {
        return $this->hasOne(TipoModeloUno::class,'NUM','TIPOMODELO_DP1_NUM')
                        ->select('NUM','TIPO_NAME','DESCRIPTION');
    }

    public function subpreferences()
    {
        return $this->hasMany(SubcategoryPreferences::class,'API_CATEGORY_PREFERENCES_ID','id')
                                ->select('API_CATEGORY_PREFERENCES_ID','TIPOMODELO_DP2_NUM')
                                ->with(['subcategory']);
    }
}
