<?php

namespace App\Http\Controllers;

use App\Models\ClientLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;


class AddressController extends Controller
{

    private $url = 'https://www.modatex.com.ar?c=Checkout::';
    private $urlAddress = 'https://www.modatex.com.ar/?c=';


    private $token;

    /**
     * GET DIRECCIONES
     */
    public function index()
    {
        $data = $this->getDirecciones();
        return response()->json($data->all());
    }

    public function edit($id, Request $request)
    {
        $this->token = Auth::user()->api_token;

        $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'Content-Type' => 'application/json'
        ])
        ->get($this->urlAddress.'Profile::getAddress&address_id='.$id);

        $data = $response->json();

        return response()->json($data['status'] == 'success'  ? $data['data'] : []);
    }

    private function getDirecciones($id = null)
    {
        $this->token = Auth::user()->api_token;

        $response = Http::withHeaders([
                        'x-api-key' => $this->token,
                        'Content-Type' => 'application/json'
                    ])
                    ->get($this->urlAddress.'Profile::addresses&app=1');

        if(!$response->json()['DIRECCIONES']){
            throw new \Exception("No se encontraron resultados");
        }

        $DIRECCIONES = $response->json()['DIRECCIONES'];


        return collect($DIRECCIONES)->map(function($direccion) {
            return [
                "direccion" => $direccion['GENERAL'] ?? '',
                "localidad" => $direccion['GENERAL_TIT'] ?? '',
                "codigo_postal" => $direccion['ZIPCODE'] ?? '',
                "name" => isset($direccion['ALIAS']) && $direccion['ALIAS'] == 0 ? '' : $direccion['ALIAS'] ?? '',
                "id" => $direccion['ID'] ?? '',
                "default" => isset($direccion['SELECCIONADO']) ? ($direccion['SELECCIONADO'] == 1 ? true : false ) : false,
            ];
        })->where('id','!=','');

    }

    // /**
    //  * CREAR DIRECCIONES
    //  */
    public function create(Request $request)
    {
        try {

            $this->token = Auth::user()->api_token;

            $data = Http::withHeaders([
                            'x-api-key' => $this->token,
                        ])
                        ->asForm()
                        ->post($this->urlAddress.'Profile::addrSave&app=1', [
                            'group_id' => 1000,
                            'method' => 'home_delivery',
                            'alias' => $request->alias,
                            'first_name' => $request->first_name,
                            'last_name' => $request->last_name,
                            'dni' => $request->dni,
                            'street_name' => $request->street_name,
                            'street_number' => $request->street_number,
                            'floor' => $request->floor,
                            'apartment' => $request->apartment,
                            'zipcode' => $request->zipcode,
                            'area_code' => $request->area_code,
                            'mobile_phone' => $request->mobile_phone,
                            'state' => $request->state,
                            'location' => $request->location,
                            'location_custom' => $request->location_custom,
                            'drop_off_time' => $request->drop_off_time,
                            'comments' => $request->comments,
                        ]);

            return response()->json($data->json());

        } catch (\Throwable $th) {

            return response()->json($th->getMessage());
        }
    }

    public function update($adress, Request $request)
    {

        $response = Http::withHeaders([
                        'x-api-key' =>  Auth::user()->api_token,
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ])
                    ->asForm()
                    ->post($this->urlAddress.'Profile::addrSave&app=1', [
                        'group_id' => 1000,
                        'method' => 'home_delivery',
                        'id' => $adress,
                        'alias' => $request->alias,
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'dni' => $request->dni,
                        'street_name' => $request->street_name,
                        'street_number' => $request->street_number,
                        'floor' => $request->floor,
                        'apartment' => $request->apartment,
                        'zipcode' => $request->zipcode,
                        'area_code' => $request->area_code,
                        'mobile_phone' => $request->mobile_phone,
                        'state' => $request->state,
                        'location' => $request->location,
                        'location_custom' => $request->location_custom,
                        'drop_off_time' => $request->drop_off_time,
                        'comments' => $request->comments,
                    ]);

        $response = $response->json();

        if($response['status'] == 'error'){
            return response()->json($response->all());
        }

        return response()->json($response);

    }

    public function deleteDireccion(Request $request)
    {
        // dd('llega',$request->num,Auth::user());

        $response = Http::withHeaders([
            'x-api-key' => Auth::user()->api_token,
            // 'Content-Type' => 'application/x-www-form-urlencoded'
        ])
        ->asForm()///
        ->post($this->urlAddress.'Profile::deleteAddress&app=1', ['num'=> $request->num]);

        $data = $this->getDirecciones();
        return response()->json($data->all());

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

    public function getComboDireccionesProfile(Request $request)
    {
        $this->token = Auth::user()->api_token;

        return $this->getComboDirecciones($request);

    }

    public function getComboDirecciones(Request $request)
    {
        $this->token = Auth::user()->api_token;

        $states   = $this->locationesStates($request->group_id);

        $gba      = $this->locationesGBA($request->group_id);

        $caba     = $this->locationesCABA($request->group_id);

        $integral = $this->locationesIntegral($request->group_id);

        $transportes = $this->getTransportes($request->group_id);

        $horarios = $this->horarios($request->group_id);


        return [
            'states'      => $states,
            'gba'         => collect($gba)->map(function($item) {
                $item['id'] = (string) $item['id'];
                return $item;
            })->toArray(),
            'caba'        => $caba,
            'integral'    => $integral ,
            'transportes' => $transportes,
            "horarios" =>  $horarios
        ];
    }

    public function horarios($group_id)
    {
        if($group_id){
            $horarios = new CheckoutController();
            $horarios = $horarios->getHorarios();

            return json_decode($horarios->getContent());
        }else{
            try {

                $response = Http::withHeaders([ 'x-api-key' => $this->token ])->acceptJson()->get($this->urlAddress.'DropOffTime::get');

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

    public function locationesStates($group_id)
    {

        try {
            if($group_id){
                $response = Http::withHeaders([ 'x-api-key' => $this->token ])->asForm()->post($this->url.'states', ['group_id' => $group_id ]);
            }else{
                $response = Http::withHeaders([ 'x-api-key' => $this->token ])->acceptJson()->get($this->urlAddress.'State::get');
            }

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
            if($group_id){
                $response = Http::withHeaders([ 'x-api-key' => $this->token ])->asForm()->post($this->url.'locations_gba', ['group_id' => $group_id ]);
            }else{
                $response = Http::withHeaders([ 'x-api-key' => $this->token ])->acceptJson()->get($this->urlAddress.'LocationsGba::get');
            }

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
            if($group_id){
                $response = Http::withHeaders([ 'x-api-key' => $this->token ])->asForm()->post($this->url.'locations_caba', ['group_id' => $group_id ]);
            }else{
                $response = Http::withHeaders([ 'x-api-key' => $this->token ])->acceptJson()->get($this->urlAddress.'LocationsCaba::get');
            }
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
