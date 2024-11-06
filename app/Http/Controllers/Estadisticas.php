<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class Estadisticas extends Controller
{
    private $url = 'https://www.modatex.com.ar/';
    private $token;

    public function __construct()
    {
        $this->token = Auth::user()->api_token; 
    }

    public function index(Request $request)
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'Content-Type' => 'application/json'
        ])
        ->post($this->url.'?c=Profile::stats');

        $response = $response->collect();

        if($response['status']=='success'){
            return response()->json(['status' => true, 'data' =>$response['data'] ]);
        }

        return response()->json(['status' => false, 'message' => 'Erro en la consulta', 'data' => $response ], 422);

    }
}
