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

        $states   = $this->locationesStates($request->group_id);

        $gba      = $this->locationesGBA($request->group_id);

        $caba     = $this->locationesCABA($request->group_id);

        $integral = $this->locationesIntegral($request->group_id);

        $transportes = $this->getTransportes($request->group_id);

        return [
            'states'      => $states,
            'gba'         => $gba,
            'caba'        => $caba,
            'integral'    => $integral ,
            'transportes' => $transportes
        ];
    }

    public function locationesStates($group_id)
    {
        try {
            $response = Http::withHeaders([ 'x-api-key' => $this->token ])->asForm()
                            ->post($this->url.'states', ['group_id' => $group_id ]);

            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }

            $data = $response->json()['data'];

            return $data;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function locationesGBA($group_id)
    {
        try {
            $response = Http::withHeaders([ 'x-api-key' => $this->token ])->asForm()
                            ->post($this->url.'locations_gba', ['group_id' => $group_id ]);

            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }

            $data = $response->json()['data'];

            array_unshift($data,['id'=> '__other__', 'name'=>'Otro que no aparece en la lista']);

            return $data;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function locationesCABA($group_id)
    {
        try {
            $response = Http::withHeaders([ 'x-api-key' => $this->token ])->asForm()
                            ->post($this->url.'locations_caba', ['group_id' => $group_id ]);

            $arreglo = function($item){
                return [
                    'id' => $item,
                    'name' => $item
                ];
            };

            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            
            $data = array_map($arreglo, $response->json()['data']);

            return $data;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function locationesIntegral($group_id)
    {
        try {
            $response = Http::withHeaders([ 'x-api-key' => $this->token ])->asForm()
                            ->post($this->url.'integral_data', ['group_id' => $group_id ]);

            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }


            $integral = $response->json()['data'];

            $arregloIntegral = function($integral){
                return [
                    'id'           => $integral['CodigoAgencia'],
                    'name'         => ucwords(strtolower($integral['NombreLocalidad'])).' / '.$integral['Direccion'],
                    'provincia_id' => intval($integral['CodigoProvincia'])
                ];
            };

            $arreglostatesIntegral = function($integral){
                $integral['name'] = ucwords(strtolower($integral['name']));
                return $integral;
            };

            $integral['branches'] = array_map($arregloIntegral, $integral['branches']);
            $integral['states'] = array_map($arreglostatesIntegral, $integral['states']);

            return $integral;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function getTransportes($group_id)
    {
        try {
            $response = Http::withHeaders([ 'x-api-key' => $this->token ])->asForm()
                            ->post($this->url.'transports', ['group_id' => $group_id ]);

                            
            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            
            $data = $response->json()['data'];

            return $data;

        } catch (\Exception $e) {
            return null;
        }
    }


}
