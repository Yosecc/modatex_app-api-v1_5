<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Preguntas extends Controller
{
    private $url = 'https://www.modatex.com.ar/';
    private $token;

    public function __construct()
    {
        $this->token = Auth::user()->api_token; 
    }

    public function index(Request $request)
    {
        $storeSearch = '';

        if(isset($request->store)){
            $storeSearch = '&store='.$request->store;
        }
        $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'Content-Type' => 'application/json'
        ])
        ->post($this->url.'?c=Profile::questions_v2&start='.$request->start.'&offset='.$request->offset.$storeSearch);

        $response = $response->collect();

        if($response['status']=='success'){

            $storesCache = Cache::get('stores');

            $data = collect($response['data']['dialogs'])->groupBy('store_id')->map(function($group, $key) use ($storesCache){
                return [
                    'store' => $storesCache->where('id',$key)->first(),
                    'messages' => $group
                ];
            })->values();

            return response()->json(['status' => true, 'data' => $data , 'length' => $response['data']['length'] ]);
        }

        return response()->json(['status' => false, 'message' => 'Erro en la consulta', 'data' => $response ], 422);

    }

    public function insert(Request $request)
    {
        // dd(Auth::user()->num);
        
        $data = [
            'store_id' => $request->store_id,
            'private'=> $request->private,
            'comments' => $request->comments
        ];
        
        $queryString = http_build_query($data);
        // dd();
        $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'Content-Type' => 'application/json'
        ])
        // ->asForm()
        ->post($this->url.'?c=Dialogs::add&'.$queryString);
        // dd($response->body() );

        $response = $response->collect();

        if($response['status']=='success'){
            return response()->json(['status' => true, 'data' => $response]);
        }

        return response()->json(['status' => false, 'message'  => $response['message'] ], 422);
        
    }
}
