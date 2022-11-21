<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $connection   = 'oracle';

    public const TABLE_NAME = 'CLIENT';
    protected $table        = self::TABLE_NAME;

    protected $primaryKey   = 'num';

    protected $hidden       = ['password','client_pwd','code_confirm'];


 
    const CREATED_AT        = 'insert_date';

    const UPDATED_AT        = 'update_date';

    public $sequence        = 'S_SHOP_CLIENT_NUM';

    public const CAMPOS = ['num','client_id as user','email','client_pwd as password','cuit_dni as dni','company_name','first_name','last_name','mobile_area','mobile','code_confirm','api_token','verification_status'];

    public function scopeActive($query){
        return $query->where('member_type','E')->where('level_cd','1000')->where('stat_cd','1000')->where('verification_status',1)->select(self::CAMPOS);
    }
    public function scopeSearchClient($query, $client_id){
        if($client_id)
            return $query->where('num', $client_id);
    }

    /*
     * Relations
     */
    
    public function store_favorites($request = null)
    {
        return $this->hasMany(StoreFavorite::class,'client_num','num')
                ->select(StoreFavorite::CAMPOS)
                ->where('STAT_CD',1000);

            
    }

    public function preferences()
    {
        return $this->hasManyThrough(
                    TipoModeloUno::class, 
                    CategoryPreferences::class, 
                    'CLIENT_NUM',
                    'NUM',
                    'num',
                    'TIPOMODELO_DP1_NUM')
                ->select('NUM','TIPO_NAME','DESCRIPTION')
                ->orderBy('ORDER_NUM','asc')
                ->with(['subCategoriesPreferences']);
    }

    public function productsVisits()
    {
        return $this->hasManyThrough(
                    Products::class,
                    ProductVisits::class,
                    'CLIENT_NUM',
                    'NUM',
                    'num',
                    'MODELOS_NUM');
    }
}
