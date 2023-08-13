<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\HelpersTraits;
use App\Http\Traits\TipoModeloUnoTraits;

class TipoModeloUno extends Model
{
    use HelpersTraits, TipoModeloUnoTraits;

    protected $connection = 'mysql';
    protected $table = 'TIPOMODELO_DP1';

    public $modelCategory;

    public $idsCategorias;

    public $request;

    public const CATEGORIES = [
        ['name'=> 'USE_WOMAN'     ,'table_id' => 1],
        ['name'=> 'USE_ACCESORY'  ,'table_id' => 2],
        ['name'=> 'USE_MAN'       ,'table_id' => 3],
        ['name'=> 'USE_CHILD'     ,'table_id' => 4],
        ['name'=> 'USE_SPECIAL'   ,'table_id' => 6],
        ['name'=> 'USE_DEPORTIVA' ,'table_id' => 7],
        ['name'=> 'USE_LENCERIA'  ,'table_id' => 10],
    ];

    public function __construct($params = null){
        $this->request = $params;
        $this->modelCategory = $params['categorias'] ?? null;
    }
    
    public function scopeActive($query){
        return $query->select()->where('STAT_CD', '1000');
    }

    public function scopeNUM($query, $num)
    {
        if($num)
            return $query->where('NUM', $num);
    }
    public function scopeNAME($query, $name){
        if($name)
            return $query->where('TIPO_NAME', $name)->orWhere('DESCRIPTION',$name);
    }

    public function scopegetCategories($query){
        $query = $query->Active()->allFilter($this->request)->with(['subCategories'])->get();
        $query = $this->dataCategoriesArray($query, $this->params);
        return $query;
    }

    public function scopeallFilter($query, $request)
    {
        if($request)
            return $query->NUM($request['category_id']??null)->NAME($request['category_name']??null);
    }

    public function scopegetCategory($query, $params)
    {
        // dd($this->modelCategory);
        $query = $query->Active()->whereIn('NUM',$this->modelCategory)->get();
        return $this->dataCategoriesArray($query,$params);
    }

    public function subCategories()
    {
        return $this->hasMany(TipoModeloDos::class, 'PARENT_NUM', 'NUM')->where('STAT_CD','1000')->orderBy('ORDER_NUM','asc');
    }

    public function subCategoriesPreferences()
    {
        return $this->hasManyThrough(
                    TipoModeloDos::class, 
                    SubcategoryPreferences::class,
                    'TIPOMODELO_DP1_NUM',
                    'NUM',
                    'NUM',
                    'TIPOMODELO_DP2_NUM')
                ->select('NUM','TIPO_NAME','DESCRIPTION')->orderBy('ORDER_NUM','asc');
    }

    // public function products()
    // {
    //     return $this->hasMany(Products::class, 'TIPO_MODELO_NUM1','NUM');
    // }


}
