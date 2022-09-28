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
    public function getData($data)
    {
        return [
            'direccion'    => $data['CALLE_NAME'].' '.$data['CALLE_NUM'].' '.$data['LOCALIDAD'].' '.$data['STAT_STR'],
            'zip'          => $data['ADDRESS_ZIP'],
            'name'         => $data['ADDRESS_NAME'],
            'id'           => $data['NUM'],
            'seleccionado' => $data['STAT_CD'] == 2000 ? true:false,
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
