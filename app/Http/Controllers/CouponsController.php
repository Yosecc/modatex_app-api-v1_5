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
            // 'Content-Type' => 'application/x-www-form-urlencoded'
        ])->asForm()->post($this->url, [
          'menu' => 'register_coupon',
          'cp_num' => $request->cp_num,
      ]);

        dd($response->body());

      return $response->collect()->all();
    }

    public function descuentosExclusivos(Request $request)
    {
      $hoy = Carbon::now();
      // dd($hoy);
      $descuentos = ExclusiveDiscount::
                    // select('code','type', 'price', 'local_cd','positioning','total_quantity','expire_date','expire_days', 'begin_date','category')
                    // where(function($query) use ($hoy) {
                      // $query->whereDate('begin_date', '>=', $hoy);
                      // ->whereDate('end_date', '<=', $hoy);
                    // })
                    
                    where('category','descexcl')
                    ->orderBy('begin_date','desc')
                    ->limit(10)
                    // ->latest('begin_date')
                    ->get();

        $grouped = $descuentos->groupBy('category');

        $storesIds = $descuentos->pluck('local_cd')->reject(function($id){ 
         return $id == '';
        });

        $stores = Store::whereIn('LOCAL_CD', $storesIds->all())->select('GROUP_CD','LOGO_FILE_NAME','LOCAL_NAME','LIMIT_PRICE','LOCAL_CD','GROUP_CD')->get();
        if(!isset($grouped['descexcl'])){
          return null;
        }
        $grouped['descexcl']->map(function($cupon) use ($stores){
          $store = $stores->where('LOCAL_CD',$cupon['local_cd'])->first();
          $cupon['store'] = [
            'logo' => env('URL_IMAGE').'/common/img/logo/'.$store['LOGO_FILE_NAME'],
            'name' => $store['LOCAL_NAME'],
            'min'  => $store['LIMIT_PRICE'],
            "id"   => $store['LOCAL_CD'],
            "company"     => $store['GROUP_CD'],
          ]; 
          return $cupon;
        });

        $datos = [];
        $datos[] = ['name'=> 'Descuentos Exclusivos', 'data' => $grouped['descexcl'] ];

      return response()->json($datos);
    }
}