<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\HelpersTraits;
use App\Http\Traits\TipoModeloUnoTraits;


class TipoModeloDos extends Model
{
    use TipoModeloUnoTraits;

    protected $connection = 'mysql';
    
    protected $table = 'TIPOMODELO_DP2';

    public $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function scopeActive($query)
    {
        return $query->where('STAT_CD','1000');
    }

    public function scopeCategory($query, $PARENT_NUM)
    {
        if($PARENT_NUM)
            return $query->where('PARENT_NUM',$PARENT_NUM);
    }
    public function scopeNUM($query, $num)
    {
        if($num)
            return $query->where('NUM', $num);
    }
    public function scopeNAME($query, $name)
    {
        if($name)
            return $query->where('TIPO_NAME', $name)->orWhere('DESCRIPTION',$name);
    }

    public function scopeallFilter($query, $request){
        if ($request) {
            return $query->Category($request['category_id']??null)
                        ->NAME($request['subcategory_name']??null)
                        ->NUM($request['subcategory_id']??null)
                        ->orderBy('ORDER_NUM','asc')
                        ->orderBy('TIPO_NAME','asc');
        }
    }
    public function scopegetSubCategories($query)
    {
        $query = $query->Active()->allFilter($this->request);

        if ($this->request['subcategory_limit'] ?? false) {
            $query = $query->limit(intVal($this->request['subcategory_limit']??null))->get();
            return $this->dataSubcategories($query);
        }
        if ($this->request['is_paginate'] ?? false) {

            $query = $query->paginate($perPage = intVal($this->request['subcategory_per_page']?? null) , $columns = ['*'], $pageName = $this->request['subcategory_page_name'] ?? 'page');
        }else{
            $query = $query->get();
        }

        return $this->dataSubcategories($query);
        
    }

    
    
}
