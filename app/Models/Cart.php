<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\CartTraits;

class Cart extends Model
{
    use CartTraits; 

    protected $table = 'CART';

    public $request;

    public $cart;

    protected $primaryKey   = 'NUM';

    const CREATED_AT        = 'INSERT_DATE';

    const UPDATED_AT        = 'UPDATE_DATE';

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    /*
     * Filters 
     */
    public function scopeActive($query)
    {
        return $query->where('STAT_CD','1000');
    }

    public function scopeClient($query,$client_id)
    {
        if($client_id)
            return $query->where('CLIENT_NUM',$client_id);
    }

    public function scopeStore($query){
        if(isset($this->request['store_group_cd']) && isset($this->request['store_local_cd']))
            return $query->where('GROUP_CD',$this->request['store_group_cd'])
                        ->where('LOCAL_CD',$this->request['store_local_cd']);
    }

    /////////////////////////////
    //////////////////
    /////////
    
    public function scopegetCarts($query)
    {   
        $query = $query->Active()->Store()->get();
        $query = $this->dataCartsColelction($query);
        return $query;
    }


}