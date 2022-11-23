<?php

namespace App\Http\Controllers;

use App\Models\ClientLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Auth;

class AddressController extends Controller
{

    private $url = 'https://www.modatex.com.ar?c=Checkout::';

    private $token;

    public function index(Request $request)
    {
        $d =  ClientLocal::where('CLIENT_NUM', Auth::user()->num)->whereIn('STAT_CD',[1000,2000])->orderBy('NUM', 'desc')->get();
        $this->token = Auth::user()->api_token;

        $arreglo = function($data){
            return $this->getData($data);
        };

        $d = array_map($arreglo, $d->all());
        // dd($d);

        return response()->json($d);
    }

    public function update($adress, Request $request)
    {
        $id = $adress;
        $adress = ClientLocal::find($adress);

        if(!$adress){
            return response()->json(['status'=> false, 'message'=> 'Adress not found'], 401);
        }

        $adress = ClientLocal::where('NUM', $id)->update($request->all());
        $adress = ClientLocal::find($id);

        return response()->json($this->getData($adress));
    }

    public function create(Request $request)
    {

        $adress                = new ClientLocal();
        $adress->COUNTRY_NUM   = $request->COUNTRY_NUM;   
        $adress->STAT_NUM      = $request->STAT_NUM;      
        $adress->STAT_STR      = $request->STAT_STR;      
        $adress->LOCALIDAD     = $request->LOCALIDAD;     
        $adress->CALLE_NAME    = $request->CALLE_NAME;    
        $adress->CALLE_NUM     = $request->CALLE_NUM;     
        $adress->CALLE_DTO     = $request->CALLE_DTO;     
        $adress->CALLE_PISO    = $request->CALLE_PISO;    
        $adress->ADDRESS_ZIP   = $request->ADDRESS_ZIP;   
        $adress->AREA_CODE     = $request->AREA_CODE;     
        $adress->ADDRESS_NAME  = $request->ADDRESS_NAME;  
        $adress->COMMENTS      = $request->COMMENTS;      
        $adress->save();

        return response()->json($this->getData($adress));
    }

    public function changePrincipalAddress(Request $request)
    {
        ClientLocal::where('CLIENT_NUM', Auth::user()->num)->whereIn('STAT_CD',[2000])->update(['STAT_CD' => 1000]);

        ClientLocal::where('CLIENT_NUM', Auth::user()->num)->where('NUM',$request->id)->update(['STAT_CD' => 2000]);

        return response()->json(['message'=>'Direccion actualizada']);

    }

    public function getData($data)
    {
        return [
            'direccion'    => $data['CALLE_NAME'].' '.$data['CALLE_NUM'].', '.$data['STAT_STR'],
            'localidad'    => $data['LOCALIDAD'],
            'codigo_postal'          => $data['ADDRESS_ZIP'],
            'name'         => $data['ADDRESS_NAME'],
            'id'           => $data['NUM'],
            'default' => $data['STAT_CD'] == 2000 ? true:false,
            'detalle'      => $data,
            // 'provincias' => $this->getProvincias(),
            // 'locaciones' => $this->getLocacionesBGA()
        ];
    }

    public function getComboDirecciones(Request $request)
    {
         $this->token = Auth::user()->api_token;
         // dd($this->token);
        $response = Http::withHeaders([
            'x-api-key' => $this->token,
            // 'Content-Type' => 'application/json'
        ])
        ->asForm()
        ->post($this->url.'states', ['group_id' => $request->group_id ]);

        $responseGBA = Http::withHeaders([
            'x-api-key' => $this->token,
            // 'Content-Type' => 'application/json'
        ])
        ->asForm()
        ->post($this->url.'locations_gba', ['group_id' => $request->group_id ]);

        $responseCABA = Http::withHeaders([
            'x-api-key' => $this->token,
            // 'Content-Type' => 'application/json'
        ])
        ->asForm()
        ->post($this->url.'locations_caba', ['group_id' => $request->group_id ]);


        $data = $response->json();

        if(!$data || (isset($data['status']) && $data['status'] == 'error')){
            return null;
        }

        return ['states'=>$data['data'],'gba'=>$responseGBA->json()['data'],'caba'=> $responseCABA->json()['data']];
    }

    public function getLocacionesBGA()
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'Content-Type' => 'application/json'
        ])
        ->get($this->url.'locations_gba');

        $data = $response->json();

        if(!$data || (isset($data['status']) && $data['status'] == 'error')){
            return null;
        }

        return $data['data'];
    }
}
