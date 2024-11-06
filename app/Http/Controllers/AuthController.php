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
      
      // $this->validate($request, [
      //   'password' => 'required|max:20',
      //   'email'    => 'required|email',
      // ]);
      
      if ($request->isJson()) {
        // try{
          
          $client = Client::select($this->campos)->where('client_id',$request->email)->where('stat_cd', 1000)->first();

            if ($client) {
             
              $payload = [
                'password' => $request->password,
                'email' => $request->email,
                'action' => 'login'
              ];

              $login = $this->ApiRosa($payload, 'login');
      
              if(!$login){
                return response()->json(['status'=>false,'response'=>'Ha ocurrido un error. Verifique e intente nuevamente'],401);
              }
              // dd($login);
              if($login->status != 200){
                $response = collect($login->response)->map(function($item,$key){
                  return [$item] ;
                });
                return response()->json( [ 'status'=> false, 'errors' => $response  ], 422);
              }

              if (intval($login->response->stat_cd) != 1000) {
                return response()->json(['status'=>false,'response'=>'Usuario inactivo'],401);
              }

              if($client->verification_status=="1" || $client->verification_status == 1){
            
                $client = Client::select($this->campos)
                          ->where('client_id',$request->email)
                          ->first();

                if($client->api_token != $login->response->token){
                  return response()->json(['status'=>false,'response'=>'Ha ocurrido un error. La clave token no coincide, comuniquese con el administrador.'],401);
                }
                
                return response()->json(['status'=>true,'client'=> $client],200);
              }else{
                return response()->json(['status'=> 'code_validation','response'=>'Cliente no validado','client'=> $client],200);
              }

            }else{
              return response()->json(['status'=>false,'response'=>'No se encontraron registros'],401);
            }
          

        // }catch(\Exception $e){
        //     return response()->json(['status'=>false,'response'=>'No se encontraron registros'],401);
        // }
      }
      return response()->json(['response'=>'Unauthorized'],401);
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

    /**
     * @param $payload : Array
     * @param $action : String
     * @param $isdecode : Boolean (Asume que el respuesta siempre viene codificada debe decodificarse de lo contrario se retorna el mismo resultado de la peticion)
     * 
     */
    private function ApiRosa($payload, $action, $isdecode = true)
    {
        // try {

          
          $jwt = JWT::encode($payload, env('KEY_JWT'), 'HS256');
          
            // dd($jwt);
            $response = Http::asForm()
                              ->post($this->url, [
                                  'data' => $jwt,
                                  'action' => $action
                              ]);

            $token = str_replace("\n", "",$response->body());

            // dd($response->body());
            
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

        // } catch (\Exception $e) {
        //     \Log::info($e->getMessage());
        //     return false;
        // }
    }

    /**
     * return { status: string, message: string, client: object Client } 
     * errors { key: []string }
     */

    public function register(Request $request){


      $client = Client::select($this->campos)->where('client_id',$request->email)->where('stat_cd', 1000)->first();
      
      if($client){
        if($client->verification_status=="0" || $client->verification_status == 0){
          // return response()->json(['status'=> 'code_validation','message'=>'Cliente no validado','client'=> $client],200);`
          return response()->json(['status'=> false,'response'=> 'Cliente no validado' ,'client'=> $client],200);
        }
      }

      // $this->validate($request, [
      //     'first_name' => 'required|max:50',
      //     // 'last_name'  => 'required|max:50',
      //     'cod_area'   => 'required|max:4',
      //     // 'phone'      => 'required|max:20|unique:oracle.T_SHOP_CLIENT,mobile',
      //     // 'email'      => 'required|email|unique:oracle.T_SHOP_CLIENT,client_id',
      //     'password'   => 'required|max:20'
      // ]);

      
      $payload = [
          "name"     => $request->first_name,
          "lastname" => isset($request->last_name) ? $request->last_name : '',
          "email"    => $request->email,
          "password" => $request->password,
          "codarea"  => $request->cod_area,
          "mobile"   => $request->phone
      ];

 
      $register = $this->ApiRosa($payload, 'newuser', true);
     

      if(gettype($register) == 'object' && !empty($register->status) && $register->status == 200){
        $client = Client::where('client_id',$request->email)->first();
        return response()->json(['status'=> true , 'response'=>'Registro realizado.','client'=>$client], 200);
      }
//  dd($register);

      return response()->json(['status'=>false,'errors'=>$register->response], 422);
     
    }

    public function code_validation(Request $request){
        $this->validate($request, [
            'code'   => 'required|max:4|min:4',
            'email'  => 'required|email',
        ],[
          'code' => [
            'required' => 'El código es requerido',
            'max' => 'El código debe tener 4 dígitos',
            'min' => 'El código debe tener al menos 4 dígitos'
          ],
          'email' => [
            'required' => 'El email es requerido',
            'email' => 'El email es incorrecto'
          ]
        ]);
        $client = Client::where('client_id',$request->email)->first();

        if ($client && $client->code_confirm == $request->code) {
            $client->verification_status = 1;
            $client->save();
            if(isset($request->istoken) && !filter_var($request->istoken, FILTER_VALIDATE_BOOLEAN)){
              return response()->json(['status'=> true]);
            }
            return response()->json(['status'=> true,'token'=> $client->api_token]);
        }else{
            return response()->json(['status'=> false,'message'=>'Código incorrecto, intente nuevamente'],422);
        }
    }

    public function resend_code($email){
        if (!$email) {
            return response()->json(['email'=>['Email is required']],422);
        }
        // if ($this->isEmail($email)) { 

          $resend = $this->ApiRosa(['email'=> $email, 'asunto' => 'Código de validación' ], 'resendcodigo');

          if($resend->status != 200){
            // Obtener los valores del objeto
            $valores = array_values((array)$resend->response);

            // Convertir los valores en una cadena separada por comas
            $cadena = implode(', ', $valores);

            return response()->json(['status'=> false, 'message'=> $cadena], 422);
          }

          return response()->json(['status'=> true, 'message'=>  $resend->response->status_mail ]);

        // }else{
        //     return response()->json(['status'=>false,'message'=> ['email' => 'Email no se encuentra registrado']],422);
        // }
    }

    public function resendcode(Request $request){

      $this->validate($request, [
          'email'  => 'required|email',
      ],[
        'email' => [
          'required' => 'El email es requerido',
          'email' => 'El email es incorrecto'
        ]
      ]);


      if (!$request->email) {
          return response()->json(['email'=>['Email is required']],422);
      }
      // if ($this->isEmail($email)) { 

        $resend = $this->ApiRosa(['email'=> $request->email, 'asunto' => 'Código de validación-' ], 'resendcodigo');

        if($resend->status != 200){
          // Obtener los valores del objeto
          $valores = array_values((array)$resend->response);

          // Convertir los valores en una cadena separada por comas
          $cadena = implode(', ', $valores);

          return response()->json(['status'=> false, 'message'=> $cadena], 422);
        }

        return response()->json(['status'=> true, 'message'=>  $resend->response->status_mail ]);

      // }else{
      //     return response()->json(['status'=>false,'message'=> ['email' => 'Email no se encuentra registrado']],422);
      // }
    }

    public function recover_password(Request $request){

      $this->validate($request, [
            'newpass'   => 'required',
            'email'  => 'required|email',
        ],[
          'newpass' => [
            'required' => 'La contrasena es requerida',
          ],
          'email' => [
            'required' => 'El email es requerido',
            'email' => 'El email es incorrecto'
          ]
        ]);

        // if ($this->isEmail($request->email)) {
            $client = Client::where('client_id', $request->email)->first();

            if($client){
              $response = $this->ApiRosa([ 
                'num_cliente' => $client->num, 
                'nuevo_pass'=> $request->newpass 
              ], 'recoverypass');

              // dd($response);

              if($response->status == 200 || $response->status == 201){
                return response()->json(['status'=> true,'message'=> 'Contrasena cambiada con éxito']);
              }else{
                $valores = array_values((array)$response->response);

                // Convertir los valores en una cadena separada por comas
                $cadena = implode(', ', $valores);
    
                return response()->json(['status'=> false, 'message'=> $cadena], 401);
              }
            }else{
              return response()->json(['status'=>false,'message'=>'Email no se encuentra registrado'],401);  
            }

        // }else{
        //     return response()->json(['status'=>false,'message'=>'Email no se encuentra registrado'],422);
        // }
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