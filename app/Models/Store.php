<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\HelpersTraits;
use App\Http\Traits\StoreTraits;
use Illuminate\Support\Collection;

class Store extends Model
{
    use HelpersTraits, StoreTraits;

    public const TABLE_NAME = 'LOCAL';
    protected $table = self::TABLE_NAME;

    protected $primaryKey   = 'num';

    public const CAMPOS = [
        self::TABLE_NAME.'.NUM',
        self::TABLE_NAME.'.GROUP_CD',
        self::TABLE_NAME.'.LOCAL_CD',
        self::TABLE_NAME.'.LOCAL_NAME',
        self::TABLE_NAME.'.ADDRESS',
        self::TABLE_NAME.'.PHONE',
        self::TABLE_NAME.'.MAIL',
        self::TABLE_NAME.'.LOGO_RECO_FILE_NAME',
        self::TABLE_NAME.'.BANNER_FILE_NAME',
        self::TABLE_NAME.'.LIMIT_PRICE',
        self::TABLE_NAME.'.BANNER_BG_COLOR',
        self::TABLE_NAME.'.PROMO_ENVIO_GRATIS',
        self::TABLE_NAME.'.PROMO_ENVIO_GRATIS_MONTO',
        self::TABLE_NAME.'.USE_MAN',
        self::TABLE_NAME.'.USE_WOMAN',
        self::TABLE_NAME.'.USE_CHILD',
        self::TABLE_NAME.'.USE_ACCESORY',
        self::TABLE_NAME.'.USE_SPECIAL',
        self::TABLE_NAME.'.USE_DEPORTIVA',
        self::TABLE_NAME.'.USE_LENCERIA',
        self::TABLE_NAME.'.LOGO_FILE_NAME'
    ];

    public $store;

    public $perPage;

    public $pageName;

    public $request;

    public function __construct($params = null){
      $this->request  = $params;
      $this->store    = $params['store']           ?? null;
      $this->pageName = $params['store_page_name'] ?? 'store_page';
      $this->perPage  = $params['store_per_page']  ?? 15;
    }
    
    /*
     * Filters
     */
    public function scopeActive($query)
    {
      return $query->select(self::CAMPOS)->whereNotIn('NUM',['1'])->where($this->table.'.STAT_CD',1000);
    }

    public function scopeSlug($query,$store)
    {
      if($store)
        $query->select(self::CAMPOS)->where('LOCAL_NAME_KEYWORD',$store);
    }

    public function scopeSTORE_ID($query, $store_num)
    {
      if($store_num)
        return $query->where('NUM',$store_num);
    }

    public function scopeGROUP_CD($query, $store_group_cd)
    {
      if($store_group_cd)
        return $query->where('GROUP_CD',$store_group_cd);
    }

    public function scopeLOCAL_CD($query, $store_local_cd)
    {
      if($store_local_cd)
        return $query->where('LOCAL_CD',$store_local_cd);
    }

    public function scopeLOCAL_NAME($query, $store_name)
    {
      if($store_name)
        return $query->where('LOCAL_NAME','LIKE','%'.$store_name.'%');
    }

    public function scopeORDER_BY($query, $orderBy, $campoName)
    {
      if ($orderBy){
        $campo = 'NUM';
        if($campoName){
          $campo = $campoName;
        }
        return $this->orderBy($campo, $orderBy);
      }
    }

    public function scopeCategory($query, $params)
    {
      if($params['name'] ?? false)
        return $query->where($params['name'], 'Y');
    }

    public function scopeallFilter($query, $params)
    {
      return $query->GROUP_CD($params['store_group_cd']??null)
                    ->LOCAL_CD($params['store_local_cd']??null)
                    ->LOCAL_NAME($params['store_name']??null)
                    ->STORE_ID($params['store_num']??null)
                    ->ORDER_BY($params['store_order_by']??null, $params['store_order_campo']??null)
                    ->Category($this->filterCategory($params['store_category']??null));
    }

    //////////////////////////////////////////////
    //////////////////////////////
    ////////////////

    /*
    * Consult Query
    * @return Collection 'store'
    */
    public function scopegetStore($query, $params){
      $store = $query->Active()->allFilter($params)->Slug($this->store)->first();
      return $this->dataArrangement($store, $params);
    }

    public function scopeGetStoreCD($query, $params){
      $store = $query->GROUP_CD($params['group_cd'])->LOCAL_CD($params['local_cd'])->first();

      return $this->dataArrangement($store, $params);
    }

    public function scopegetStores($query, $params){
      $stores = $this->Active()->allFilter($params)
                      ->paginate($perPage = $this->perPage, $columns = ['*'], $pageName = $this->pageName);
      $stores = $this->dataArrayArrangement($stores, $params);


      return $stores;
    }

    public function scopesearchStore($query, $array, $params){
      $store = $query->Active()->GROUP_CD($array['GROUP_CD'])->LOCAL_CD($array['LOCAL_CD'])->allFilter($params)->Slug($this->store)->first();
      
      return $this->dataArrangement($store, $params);
    }

   
    //////////////////////////////////////////////
    //////////////////////////////
    ////////////////

}
