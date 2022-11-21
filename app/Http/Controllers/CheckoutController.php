<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    private $url = 'https://www.modatex.com.ar/modatexrosa3/?c=';

    public function editClient(Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required',
            'last_name'  => 'required',
            'cuit_dni'   => 'required',
        ]);

        $response = Http::acceptJson()->
                    post($this->generateUrl([ 'controller' => 'User', 'method' => 'edit' ]), [
                        'user-first-name'   => $request->first_name,
                        'user-last-name'    => $request->last_name,
                        'user-dni'          => $request->cuit_dni,
                    ]);

        return response()->json('OK');
    }

    private function generateUrl($data)
    {
        return $this->url.$data['controller'].'::'.$data['method'];
    }
}
