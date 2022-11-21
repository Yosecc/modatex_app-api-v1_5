<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    private $url = 'https://www.modatex.com.ar/modatexrosa3/?c=';
    private $token;

    public function editClient(Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required',
            'last_name'  => 'required',
            'cuit_dni'   => 'required',
        ]);

        $this->token = Auth::user()->api_token; 

        Client::where('num',Auth::user()->num)->update([
                            'first_name'   => $request->first_name,
                            'last_name'    => $request->last_name,
                            'cuit_dni'     => $request->cuit_dni]);

        return response()->json('OK');
    }

    private function generateUrl($data)
    {
        return $this->url.$data['controller'].'::'.$data['method'];
    }
}
