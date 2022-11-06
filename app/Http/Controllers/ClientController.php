<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Stores;
use Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;

class ClientController extends Controller
{
    private $url = 'https://www.modatex.com.ar/ntadministrator/router/Router.php/user';

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

    public function change_password(Request $request)
    {
        $this->validate($request, [
            'oldpass'   => 'required',
            'newpass'   => 'required',
            // 'email'  => 'required|email',
        ]);

        $response = $this->ApiRosa([
            'client_num' =>  Auth::user()->num, 
            'newpass'=> $request->newpass,
            'oldpass'=> $request->oldpass ], 'changepass');

        if($response->status == 200){
            return response()->json(['message'=> 'Contrasena cambiada con exito'], 200);
        }elseif($response->status == 500){
            return response()->json(['message'=> 'Ocurrió un error intente más tarde'], 422);
        }

    }
}
