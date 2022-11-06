<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Stores;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index(Request $request){
        // dd();
      $client = Client::find(Auth::user()->num);
      // $client = Client::find(1026071);
      

      return response()->json($client);
    }

    public function change_password(Request $request)
    {
        $this->validate($request, [
            'oldpass'   => 'required',
            'newpass'   => 'required',
            // 'email'  => 'required|email',
        ]);

        if (Auth::user()->email) {
            $client = Client::where('client_id', Auth::user()->email)->first();

            if($client){
              $response = $this->ApiRosa([
                'client_num' => $client->num, 
                'newpass'=> $request->newpass,
                'oldpass'=> $request->oldpass ], 'changepass');

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
}
