<?php

namespace App\Http\Controllers;

use App\Models\ClientLocal;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $d =  ClientLocal::where('CLIENT_NUM', 1026071)->whereIn('STAT_CD',[1000,2000])->get();

        $arreglo = function($data){
            return $this->getData($data);
        };

        $d = array_map($arreglo, $d->all());
        // dd($d);

        return response()->json($d);
    }

    public function update($adress, Request $request)
    {
        $adress = ClientLocal::find($adress);

        if(!$adress){
            return response()->json(['status'=> false, 'message'=> 'Adress not found'], 401);
        }

        // $this->validate($request, [
        //     'CALLE_NAME'    => '',
        //     'CALLE_NUM'     => '',
        //     'CALLE_PISO'    => '',
        //     'CALLE_DTO'     => '',
        //     'provincia'     => '',
        //     'ADDRESS_ZIP'   => '',
        //     'DELIVERY_HOUR' => '',
        //     'COMMENTS'      => '',
        // ]);

        $adress->update($request->all());
        
        // $adress->COUNTRY_NUM   = $request->COUNTRY_NUM;   
        // $adress->STAT_NUM      = $request->STAT_NUM;      
        // $adress->STAT_STR      = $request->STAT_STR;      
        // $adress->LOCALIDAD     = $request->LOCALIDAD;     
        // $adress->CALLE_NAME    = $request->CALLE_NAME;    
        // $adress->CALLE_NUM     = $request->CALLE_NUM;     
        // $adress->CALLE_DTO     = $request->CALLE_DTO;     
        // $adress->CALLE_PISO    = $request->CALLE_PISO;    
        // $adress->ADDRESS_ZIP   = $request->ADDRESS_ZIP;   
        // $adress->AREA_CODE     = $request->AREA_CODE;     
        // $adress->ADDRESS_NAME  = $request->ADDRESS_NAME;  
        // $adress->COMMENTS      = $request->COMMENTS;      
        // $adress->save();

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

    public function getData($data)
    {
        return [

            'direccion'    => $data['CALLE_NAME'].' '.$data['CALLE_NUM'].', '.$data['STAT_STR'],
            'localidad'    => $data['LOCALIDAD'],
            'codigo_postal'          => $data['ADDRESS_ZIP'],
            'name'         => $data['ADDRESS_NAME'],
            'id'           => $data['NUM'],
            'default' => $data['STAT_CD'] == 2000 ? true:false,
            'detalle'      => $data
            // [
            //     'calle' => $data['CALLE_NAME'],
            //     'altura' =>  $data['CALLE_NUM'],
            //     'localidad' => $data['LOCALIDAD'],
            //     'piso' => $data['CALLE_PISO'],
            //     'zip' =>  $data['ADDRESS_ZIP']
            //     'name' =>  $data['ADDRESS_NAME']
            //     'id' => $data['NUM']
            //     'stat_cd' => $data['STAT_CD']
            // ]
        ];
    }
}
