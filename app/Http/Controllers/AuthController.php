<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

use App\Http\Traits\HelpersTraits;

use Illuminate\Http\Request;

use App\Mail\CodeConfirmation;
use App\Mail\RecoverPassword;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

use App\Models\Coupons;
use App\Models\Client;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use Carbon\Carbon;

class AuthController extends Controller
{
    use HelpersTraits;

    public $campos;
    private $url = '';
    

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

             return response()->json(['status'=>true,'client'=> $client],200);

            if ($client) {

              $payload = [
                'password' => $request->password,
                'email' => $request->email,
                'action' => 'login'
              ];

              $login = $this->sendLoginRosa($payload);

              if ($login->status != 200) {
                return response()->json(['status'=>false,'message'=>'Contraseña incorrecta'],401);
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

    public function sendLoginRosa($payload)
    {
      try {

        $jwt = JWT::encode($payload, env('KEY_JWT'), 'HS256');

        $response = Http::asForm()->post($this->url, [
            'jwt' => $jwt,
        ]);

        $token = $response->collect()->all()['token'];

        $decode = JWT::decode($token, new Key(env('KEY_JWT'), 'HS256'));

        return $decode;

      } catch (\Exception $e) {
          \Log::info($e->getMessage());
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

        if ($request->isJson()) {

            $code = $this->generateCode();

            $client               = new Client();
            $client->client_id    = $request->email;
            $client->email        = $request->email;
            $client->client_pwd   = password_hash($request->password, PASSWORD_DEFAULT,['cost'=> 11]);;
            $client->member_type  = 'E' ;
            $client->sex          = null;
            $client->first_name   = $request->last_name;
            $client->last_name    = $request->first_name;
            $client->mobile       = $request->phone;
            $client->mobile_area  = $request->cod_area;
            $client->code_confirm = $code;
            $client->verification = md5($request->email.time());
            $client->verification_status = 0;
            $client->save();

            if ($client) {
                Mail::to($client->email)->send(new CodeConfirmation($client));
                return response()->json(['status'=>true, 'client'=>$client],201);
            }
        }
        
        return response()->json(['status'=>false, 'message'=>'Ha ocurrido un errror'],422);
    }

    public function code_validation(Request $request){
        $this->validate($request, [
            'code'   => 'required|max:4|min:4',
            'email'  => 'required|email',
        ]);
        $client = Client::where('client_id',$request->email)->first();
        if ($client->code_confirm == $request->code) {
            $client->verification_status = 1;
            $client->api_token           = Str::random(40);
            $client->save();

            $cupons_defintion = [
                'count' => 2,
                'monto' => 250
            ];
            
            $coupons = $this->createCoupon($client, $cupons_defintion);

            return response()->json(['status'=> true,'token'=> $client->api_token, 'coupons' => $coupons]);
        }else{
            return response()->json(['status'=> false,'message'=>'C&oacutedigo incorrecto, intente nuevamente'],422);
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

    public function recover_password($email){
        if (!$email) {
            return response()->json(['email'=>['Email is required']],422);
        }
        if ($this->isEmail($email)) {
            $client = Client::where('client_id', $email)->first();

            Mail::to($client->email)->send(new RecoverPassword($client));
            return response()->json(['status'=> true, 'message'=>'Se ha enviado su contrasena a su cuenta de email, porfavor verifique su bandeja de entrada']);

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
