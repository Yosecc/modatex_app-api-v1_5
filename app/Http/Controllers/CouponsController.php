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

    // public function redeemCoupon(Request $request)
    // {

    //   $this->token = Auth::user()->api_token;

    //   $this->validate($request, [
    //       'cp_num' => 'required',
    //   ]);

    //    $response = Http::withHeaders([
    //         'x-api-key' => $this->token,
    //         // 'Content-Type' => 'application/x-www-form-urlencoded'
    //     ])->asForm()->post($this->url, [
    //       'menu' => 'register_coupon',
    //       'cp_num' => $request->cp_num,
    //   ]);

    //     dd($response->body());

    //   return $response->collect()->all();
    // }

    public function canjearCupon(Request $request)
    {
      try {
        
        $this->validate($request, [
          'code' => 'required',
        ]);

        $cupon = ExclusiveDiscount::whereRaw('upper(code) = upper("'.$request->code.'")')->first();
        $hoy = Carbon::now('America/Argentina/Buenos_Aires');

        if($cupon->expire_days){
          $fechaVencimiento = Carbon::now()->addDays($cupon->expire_days);
        }

        if($cupon->expire_date){
          $fechaVencimiento = $cupon->expire_date;
        }

        if(Carbon::create($hoy->year,$hoy->month, $hoy->day,0,0,0) > Carbon::parse($fechaVencimiento)){
          throw new \Exception('cupón expirado'); 
          return response()->json('cupón expirado', 422);
        }

        $cuponesClient =  Coupons::where('client_num',Auth::user()->num)->get();

        $validate = $cuponesClient->where('coupon_str', $cupon->type)->first();
        if($validate){
          throw new \Exception('Ya canjeaste un cupón de este tipo.');
          return response()->json('Ya canjeaste un cupón de este tipo.', 422);
        }

        if($cupon->total_quantity){
          $cuponesCanjeadosDelMismoTipo = Coupons::where('coupon_str', 'like',$cupon->type)->count();
          if($cuponesCanjeadosDelMismoTipo >= $cupon->total_quantity){
            throw new \Exception('Cupones agotados.');
            return response()->json('Cupones agotados.', 422);
          }
        }

        $LOCAL_CD_VALID = '';

        

        if(!$cupon->local_cd || $cupon->local_cd == ''){
          if ($cupon->positioning){
            $positioning		= explode( '|', $cupon->positioning );
            $stores = new StoresController();
            $stores = $stores->searchStoresSegunPlanes($positioning);
            
            $LOCAL_CD_VALID = implode( '|', $stores->pluck('local_cd')->all() );
          }
        }else{
          $LOCAL_CD_VALID 	= '|'.$cupon->local_cd.'|';
        }

        // dd( Carbon::parse($fechaVencimiento)->format('d/m/Y'));
        $cuponNew = new Coupons();
        $cuponNew->COUPON_STR = $cupon->type;
        $cuponNew->COUPON_PRICE = $cupon->price;
        $cuponNew->CLIENT_NUM = Auth::user()->num;
        $cuponNew->COUPON_TYPE = 'N';
        $cuponNew->EXPIRE_DATE = $fechaVencimiento;
        $cuponNew->LOCAL_CD_VALID = $LOCAL_CD_VALID;
        $cuponNew->MIN_MONEY = 0;
        $cuponNew->save();

        return response()->json('Felicitaciones, tu cupón fue agregado exitosamente.');
      
      } catch (\Exception $e) {
        return response()->json($e->getMessage(), 422);
      }
    }

    public function descuentosExclusivos(Request $request)
    {
      $hoy = Carbon::now();
      // dd($hoy);
      $descuentos = ExclusiveDiscount::where('category','descexcl')
                                      ->orderBy('begin_date','desc')
                                      ->limit(10)
                                      ->get();

        $grouped = $descuentos->groupBy('category');

        $storesIds = $descuentos->pluck('local_cd')->reject(function($id){ 
         return $id == '';
        });

        $cuponesClient =  Coupons::where('client_num',Auth::user()->num)->get();

        // dd($cuponesClient);

        $stores = Store::whereIn('LOCAL_CD', $storesIds->all())->select('GROUP_CD','LOGO_FILE_NAME','LOCAL_NAME','LIMIT_PRICE','LOCAL_CD','GROUP_CD')->get();
        if(!isset($grouped['descexcl'])){
          return null;
        }
        $grouped['descexcl']->map(function($cupon) use ($stores,$cuponesClient){
          $store = $stores->where('LOCAL_CD',$cupon['local_cd'])->first();
          $cupon['store'] = [
            'logo' => env('URL_IMAGE').'/common/img/logo/'.$store['LOGO_FILE_NAME'],
            'name' => $store['LOCAL_NAME'],
            'min'  => $store['LIMIT_PRICE'],
            "id"   => $store['LOCAL_CD'],
            "company"     => $store['GROUP_CD'],
          ]; 

          $cupon['isAdd'] = $cuponesClient->where('coupon_str', $cupon->type)->count();
          
          return $cupon;
        });



        $datos = [];
        $datos[] = ['name'=> 'Descuentos Exclusivos', 'data' => $grouped['descexcl'] ];

      return response()->json($datos);
    }
}