<?php

namespace App\Http\Controllers;

use App\Models\Coupons;
use App\Models\Store;
use Auth;
use Illuminate\Http\Request;
use App\Http\Traits\StoreTraits;
use Illuminate\Support\Facades\Http;

class CouponsController extends Controller
{
  use StoreTraits;
  private $token;
  private $url = 'https://www.modatex.com.ar/main/home_db.php';

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

    public function getCupones($local_cd)
    {
        $cupones = Coupons::where(function($query) {
          $query->where('CLIENT_NUM',Auth::user()->num)
                ->whereIn('STAT_CD',[1000,2000]);
        })
        // ->where('COUPON_STR' != 'PROMOEMOTV916')
        ->orderBy('REGISTER_DATE','desc')
        ->get();

        if(!$cupones){
          return null;
        }

        $cuponesTiendas = $cupones->pluck('local_cd_valid','num');

        $cuponesTiendas = $cuponesTiendas->map(function($tiendas){
          return explode('|', trim(preg_replace('/\s+/', '',  $tiendas), '|'));
        });

        $cuponn = [];

        foreach ($cuponesTiendas->all() as $ct => $cupon) {
          foreach ($cupon as $t => $tienda) {
            if($tienda == $local_cd){
              $cuponn = $cupones->where('num',$ct)->first();
            }
          }
        }

        // dd($cuponesTiendas);
        
        return count($cuponn) ? $cuponn : null;
    }

    public function storeCupon($cupon)
    {

      $tiendas_codes = $cupon->local_cd_valid;

      $local_cds = explode('|', trim(preg_replace('/\s+/', '',  $tiendas_codes), '|'));

      $stores = Store::whereIn('LOCAL_CD',$local_cds)->get();

      return $this->dataArrayArrangement($stores);
    }

    public function redeemCoupon(Request $request)
    {

      $this->token = Auth::user()->api_token;

      $this->validate($request, [
          'cp_num' => 'required',
      ]);

       $response = Http::withHeaders([
            'x-api-key' => $this->token,
        ])->asForm()->post($this->url, [
          'menu' => 'register_coupon',
          'venta_num' => $request->cp_num,
      ]);

        dd($response->body());

      return $response->collect()->all();
    }
}
