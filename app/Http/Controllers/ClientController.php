<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Stores;
use Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
class ClientController extends Controller
{
    private $url = 'https://www.modatex.com.ar/ntadministrator/router/Router.php/user';
    private $urlProfile = 'https://www.modatex.com.ar/?c=';


    public function index(Request $request){
        // dd();
      $client = Client::find(Auth::user()->num);
      // $client = Client::find(1026071);
      
      return response()->json($client);
    }

    private function ApiRosa($payload, $action, $isdecode = true)
    {
        try {
            // dd($payload);
            $jwt = JWT::encode($payload, env('KEY_JWT'), 'HS256');
            
            $response = Http::asForm()
                ->post($this->url, [
                    'data' => $jwt,
                    'action' => $action
                ]);
                // dd($response->body());
            $token = str_replace("\n", "",$response->body());
            $decode = false;
            
            try {
              if($isdecode){
                $decode = JWT::decode($token, new Key(env('KEY_JWT'), 'HS256'));
               
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

    public function change_password(Request $request)
    {
        // $this->validate($request, [
        //     'oldpass'   => 'required',
        //     'newpass'   => 'required',
        //     // 'email'  => 'required|email',
        // ]);

        $response = $this->ApiRosa([
            'client_num' =>  Auth::user()->num, 
            'newpass'=> $request->newpass || '',
            'oldpass'=> $request->oldpass || '',
            'api_token' => Auth::user()->api_token
        ], 'changepass');

        // dd($response);

        if($response->status == 200){
            return response()->json(['message'=> 'Contrasena cambiada con exito'], 200);
        }else{
            $response = collect($response->response)->map(function($item,$key){
                return [$item] ;
              });
              return response()->json( $response , 422);
            // return response()->json(['message'=> 'Ocurrió un error intente más tarde'], 422);
        }

    }

    public function update(Request $request){
        // return $request->all();

        $validator = Validator::make($request->all(), [
            "firstName" => "required",
            "lastName" => "required",
            "dni" => "required",
            "gender" => "required",
            "areaCode" => "required",
            "mobilePhone" => "required"
        ]);
 
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $response = Http::withHeaders([ 
            'x-api-key' => Auth::user()->api_token,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])
        ->asForm()
        ->post($this->urlProfile.'Profile::usrDataUpdate',[
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'dni' => $request->dni,
            "gender" => $request->gender,
            "areaCode" => $request->areaCode,
            "mobilePhone" => $request->mobilePhone
        ]);
        $response = $response->collect();

        if($response['status'] == 'error'){
            return response()->json($response->all(), 422);
        }

        $response['data'] = Client::find(Auth::user()->num);
        
        return response()->json($response);
    }
}
