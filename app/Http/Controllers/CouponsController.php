<?php

namespace App\Http\Controllers;

use App\Http\Traits\StoreTraits;
use App\Models\Coupons;
use App\Models\ExclusiveDiscount;
use App\Models\Store;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

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

        if(gettype($cuponn) == 'array'){
          return count($cuponn) ? $cuponn : null;

        }elseif(gettype($cuponn) == 'object'){
          return $cuponn;
        }

        return null;
        
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

    public function descuentosExclusivos(Request $request)
    {
      $hoy = Carbon::now();
      $descuentos = ExclusiveDiscount::
                    select('code','type', 'price', 'local_cd','positioning','total_quantity','expire_date','expire_days')
                    ->where(function($query) use ($hoy) {
                      $query->whereDate('begin_date', '>=', $hoy)->whereDate('end_date', '<=', $hoy);
                    })
                    // limit(1)
                    ->latest('begin_date')
                    ->get();

        $grouped = $descuentos->groupBy('category');

        // $principioMes = Carbon::create($hoy->year, $hoy->month, 1);
        // $finalMes = Carbon::create($hoy->year, $hoy->month, $hoy->daysInMonth);
       

        // $descuentos = ExclusiveDiscount::
        //             where('category','like' ,'descexcl%')
        //             // ->whereIn('local_cd',[2126])
        //             ->where(function($query) use ($hoy, $principioMes, $finalMes) {
        //               $query->whereDate('expire_date', '>=', $principioMes)->whereDate('expire_date', '<=', $finalMes);
        //             })
        //             ->latest('expire_date')
        //             ->get()->dd();
        $datos = [];
        $datos[] = ['name'=> 'Descuentos Exclusivos', 'data' => $grouped['descexcl'] ];

      return response()->json($datos);
    }
}


 // "id" => 4569
 //      "code" => "PACCA80015DIC"
 //      "type" => "DESCEXCL17112022_PACCA80015DIC"
 //      "category" => "descexcl"
 //      "price" => 800.0
 //      "total_quantity" => null
 //      "begin_date" => "2022-11-17"
 //      "end_date" => "2022-12-15"
 //      "expire_date" => "2022-12-15"
 //      "expire_days" => null
 //      "local_cd" => "2126"
 //      "positioning" => ""
 //      "entry" => "2022-11-17 16:11:37"