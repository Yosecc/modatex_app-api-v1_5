<?php

namespace App\Http\Traits;
use Illuminate\Support\Facades\Auth;
use App\Http\Traits\ProductsTraits;
use App\Http\Traits\HelpersTraits;
use Illuminate\Http\Request;
use App\Models\Store;

trait ClientTraits {

	use HelpersTraits, ProductsTraits;

    public function products_visits($params = null)
    {
      // $params['is_store'] = true;
      return $this->productsCollection( Auth::user()->productsVisits()->limit(4)->orderBy('created_at','desc')->get(),  $params,  $params['is_store'] );
    }

    public function stores_favorites($params)
    {
      $stores_favorites = [];
      $query = Auth::user()->store_favorites($params);

      if(!isset($params['stores_favorites_limit'])){
          $query = $query->paginate($perPage = $params['stores_favorites_per_page'] ?? 15, 
                    $columns = ['*'], 
                    $pageName = $params['stores_favorites_page_name'] ?? 'stores_favorites_page');
      }else{

          $query = $query->limit($params['stores_favorites_limit'])->get();
      }

      foreach ($query as $key => $store) {
        $store = Store::Active()
                        ->GROUP_CD($store['GROUP_CD'])
                        ->LOCAL_CD($store['LOCAL_CD'])
                        ->LOCAL_NAME($params['stores_favorites_name'] ?? null)
                        ->first();
        if ($store) {
          $stores_favorites[] = $this->dataArrangement($store, $params);
        }
      }
      
      return collect($stores_favorites);
    }

}