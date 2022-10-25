<?php

namespace App\Http\Controllers;

use App\Models\Coupons;
use App\Models\Store;
use Auth;
use Illuminate\Http\Request;
use App\Http\Traits\StoreTraits;

class CouponsController extends Controller
{
  use StoreTraits;
    public function index()
    {
        $cupones = Coupons::where(function($query) {
          $query->where('CLIENT_NUM',Auth::user()->num)
                ->whereIn('STAT_CD',[1000,2000]);
        })
        // ->where('COUPON_STR' != 'PROMOEMOTV916')
        ->orderBy('REGISTER_DATE','desc')
        ->get();

        $arreglo = function($cupon){
          // dd($cupon->local_cd_valid);
          $cupon->tiendas = $this->storeCupon($cupon);
          return $cupon;
        };
        $cupones = array_map($arreglo, $cupones->all());
        
        return response()->json($cupones);
    }

    public function storeCupon($cupon)
    {

      $tiendas_codes = $cupon->local_cd_valid;

      $local_cds = explode('|', trim(preg_replace('/\s+/', '',  $tiendas_codes), '|'));

      $stores = Store::whereIn('LOCAL_CD',$local_cds)->get();

      return $this->dataArrayArrangement($stores);
    }
}
