<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductsDetail extends Model
{
    protected $table = 'MODELO_DETALE';

    protected $with = ['Size','Color','Price'];

    public function Active($query){
        $query->where('STAT_CD','1000');
    }


    public function Size(){
        return $this->hasOne(Code::class, 'NUM','SIZE_NUM');
    }
    public function Color(){
        return $this->hasOne(Code::class, 'NUM','COLOR_NUM');
    }
    
    public function Price(){
        return $this->hasOne(History::class, 'MODELO_DETALE_NUM','MODA_NUM');
    }

}
