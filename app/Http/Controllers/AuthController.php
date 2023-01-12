<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Objects\NotificationsPush;
use App\Http\Traits\HelpersTraits;
use App\Mail\CodeConfirmation;
use App\Mail\RecoverPassword;
use App\Models\Client;
use App\Models\Coupons;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use HelpersTraits;

    public $campos;
    private $url = 'https://www.modatex.com.ar/ntadministrator/router/Router.php/user';
    

    public function __construct(){
      $this->campos = Client::CAMPOS;
    }

    public function login(Request $request){
        // phpinfo();

        $this->validate($request, [
            'password' => 'required|max:20',
            'email'    => 'required|email',
        ]);
        
        if ($request->isJson()) {
          try{
            
            $client = Client::select($this->campos)->where('client_id',$request->email)->first();
            
            if(in_array($client->email, ['tiendas@modatex.com.ar'])){
              return response()->json(['status'=>true,'client'=> $client],200);
            }


             //return response()->json(['status'=>true,'client'=> $client],200);

            if ($client) {

              $payload = [
                'password' => $request->password,
                'email' => $request->email,
                'action' => 'login'
              ];

              // social_method: 'Facebook|Google'
              // social_id: 'XXXXX'

              // $login = $this->sendLoginRosa($payload);
              $login = $this->ApiRosa($payload, 'login');
              // dd($login);
              if(!$login){
                return response()->json(['status'=>false,'message'=>'Ha ocurrido un error. Verifique e intente nuevamente'],401);
              }

              if($login->status != 200){
                return response()->json(['message'=> $login->response], 422);
              }

              if ($login->stat_cd != 1000) {
                return response()->json(['status'=>false,'message'=>'Usuario inactivo'],401);
              }

              $client = Client::select($this->campos)
                        ->where('client_id',$request->email)
                        ->first();

              if($client->api_token != $login->token){
                return response()->json(['status'=>false,'message'=>'Ha ocurrido un error. La clave token no coincide, comuniquese con el administrador.'],401);
              }

              
              
              return response()->json(['status'=>true,'client'=> $client],200);

            }else{
              return response()->json(['status'=>false,'message'=>'No se encontraron registros'],401);
            }
           
          }catch(ModelNotFoundException $e){
            return response()->json(['status'=>false,'message'=>'No se encontraron registros'],401);
          }
        }
        return response()->json(['message'=>'Unauthorized'],401);
    }

    public function LoginSocial(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'email'    => 'required|email',
            'social_method'    => 'required',
            // 'verified_email'    => 'required',
        ]);

        $client = Client::select($this->campos)->where('client_id',$request->email)->first();

        if($client){ //LOGUEAR
            return response()->json(['status'=>true,'client'=> $client],200);
        }else{ //CREAR
            $dataRegistro = ['email' => $request->email ];
            if($request->social_method == 'Google'){
                $dataRegistro['first_name'] = $request->given_name;
                $dataRegistro['last_name'] = $request->family_name;
            }
            $dataRegistro['password'] = $request->id;
            $dataRegistro['phone'] = '3468852';
            $dataRegistro['cod_area'] = '247';

            $register = $this->ApiRosa($dataRegistro, 'newuser', false);

            if(json_decode($register)->status == 200){
              $client = Client::where('client_id',$request->email)->first();
              return response()->json(['status'=>true, 'message'=>'Registro realizado.','client'=>$client]);
            }

            return response()->json(['message'=>'Error'],422);
        }
    }

    private function ApiRosa($payload, $action, $isdecode = true)
    {
        try {

            $jwt = JWT::encode($payload, env('KEY_JWT'), 'HS256');
            
            $response = Http::asForm()
                ->post($this->url, [
                    'data' => $jwt,
                    'action' => $action
                ]);
            $token = str_replace("\n", "",$response->body());
            $decode = false;
            
            try {
              if($isdecode){
                $decode = JWT::decode($token, new Key(env('KEY_JWT'), 'HS256'));
                // dd($decode);  
              }else{
                $decode = $token;
              }
            } catch (\Exception $e) {
                \Log::info($e->getMessage());
                $decode = false;
            }

            return $decode;

        } catch (\Exception $e) {
            \Log::info($e->getMessage());
            return false;
        }
    }


    public function register(Request $request){

        //TODO: validacion de codigo
        $this->validate($request, [
            'first_name' => 'required|max:50',
            'last_name'  => 'required|max:50',
            'cod_area'   => 'required|max:4',
            'phone'      => 'required|max:20|unique:oracle.CLIENT,mobile',
            'email'      => 'required|email|unique:oracle.CLIENT,client_id',
            'password'   => 'required|max:20'
        ]);

        $payload = [
            "name"     => $request->first_name,
            "lastname" => $request->last_name,
            "email"    => $request->email,
            "password" => $request->password,
            "codarea"  => $request->cod_area,
            "mobile"   => $request->phone
        ];


        $register = $this->ApiRosa($payload, 'newuser', false);

        if(json_decode($register)->status == 200){
          $client = Client::where('client_id',$request->email)->first();
          return response()->json(['status'=>true, 'message'=>'Registro realizado.','client'=>$client]);
        }

        return response()->json(['message'=>'Error'],422);
    }

    public function code_validation(Request $request){
        $this->validate($request, [
            'code'   => 'required|max:4|min:4',
            'email'  => 'required|email',
        ]);
        $client = Client::where('client_id',$request->email)->first();
        if ($client && $client->code_confirm == $request->code) {
            $client->verification_status = 1;
            $client->save();
            return response()->json(['status'=> true,'token'=> $client->api_token]);
        }else{
            return response()->json(['message'=>'Código incorrecto, intente nuevamente'],422);
        }
    }

    public function resend_code($email){
        if (!$email) {
            return response()->json(['email'=>['Email is required']],422);
        }
        if ($this->isEmail($email)) {
            $client = Client::where('client_id', $email)->first();
            $client->code_confirm = $this->generateCode();
            $client->save();

            Mail::to($client->email)->send(new CodeConfirmation($client));
            return response()->json(['status'=> true, 'message'=>'Se ha enviado el codigo a su cuenta de email, porfavor verifique su bandeja de entrada']);

        }else{
            return response()->json(['status'=>false,'message'=>'Email no se encuentra registrado'],422);
        }
    }

    public function recover_password(Request $request){

      $this->validate($request, [
            'newpass'   => 'required',
            'email'  => 'required|email',
        ]);

        if ($this->isEmail($request->email)) {
            $client = Client::where('client_id', $request->email)->first();

            if($client){
              $response = $this->ApiRosa(['client_num' => $client->num, 'newpass'=> $request->newpass ], 'recoverypass');

              if($response->status == 200){
                return response()->json(['status'=> true,'message'=> 'Contrasena cambiada con exito']);
              }
            }else{
              return response()->json(['status'=>false,'message'=>'Email no se encuentra registrado'],422);  
            }

        }else{
            return response()->json(['status'=>false,'message'=>'Email no se encuentra registrado'],422);
        }
    }

    

    public function createCoupon($client, $definitions)
    {
        $consulta = Coupons::where('coupon_str','201710')
                                ->where('client_num',$client->num)
                                ->count();

        if ($consulta == 0) {
            $cupones = [];
            for ($i=0; $i < $definitions['count']; $i++) { 
                $coupons = new Coupons();
                $coupons->fill([
                    'coupon_str'    => '201710',
                    'coupon_price'  => $definitions['monto'],
                    'client_num'    => $client->num,
                    'stat_cd'       => '1000',
                    'register_date' => Carbon::now()->format('d/m/y'),
                    'expire_date'   => Carbon::now()->addMonth()->format('d/m/y')
                ]);
                $coupons->save();

                $cupones[] = $coupons;
            }

            return ['status'=> true, 'message' => 'Cupones creados','cupones'=> collect($cupones)];

        }

        return ['status'=> false, 'message' => 'Ya existen cupones de registro'];
    }

}