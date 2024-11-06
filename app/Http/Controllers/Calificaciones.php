<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Calificaciones extends Controller
{
    private $url = 'https://www.modatex.com.ar/';
    private $token;

    public function __construct()
    {
        $this->token = Auth::user()->api_token; 
    }

    public function index(Request $request)
    {
        /**
         * todo FALTA EL ID DE LA COMPRA
         */

        $storeSearch = '';

        if(isset($request->store)){
            $storeSearch = '&store='.$request->store;
        }
        
        $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'Content-Type' => 'application/json'
        ])
        ->get($this->url.'?c=Profile::califications_v2&start='.$request->start.'&offset='.$request->offset.$storeSearch);

        $response = $response->collect();

        if($response['status']=='success'){

            $data = collect($response['data']['ratings']);

            $storesCache = Cache::get('stores');
            
            $data = $data->map(function($calificacion) use ($storesCache){
                $calificacion['store'] = $storesCache->where('id',$calificacion['store_id'])->first();
                return $calificacion;
            });

            return response()->json(['status' => true, 'data' => $data, 'length' => $response['data']['length'] ]);
        }

        return response()->json(['status' => false, 'message' => 'Erro en la consulta', 'data' => $response ], 422);

    }

    public function insertOrUpdate(Request $request)
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'Content-Type' => 'application/json'
        ])
        ->get($this->url.'?c=Profile::rate&purchase_id='.$request->purchase_id.'&rate='.$request->rate.'&comments='.$request->comments);

        $response = $response->collect();

        
        /**
         * TODO esta raro el rating no devuelve el numero que es
         */
        
        if($response['status']=='success'){
            return response()->json(['status' => true, 'data' =>$response['data'] ]);
        }

        return response()->json(['status' => false, 'message' => 'Erro en la consulta', 'data' => $response ], 422);
    }

    
}
