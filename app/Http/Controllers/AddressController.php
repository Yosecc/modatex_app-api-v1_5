<?php

namespace App\Http\Controllers;

use App\Models\ClientLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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

        // try {
            // dd('kiki');
           $data = $this->getDirecciones();
            return response()->json($data->all());

        // } catch (\Exception $e) {
        //     return $e->getMessage();
        // }
    }

    private function getDirecciones()
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

        // Primero, obtén todos los IDs únicos de direcciones para reducir las consultas a la base de datos
        $direccionesIds = collect($response->json()['DIRECCIONES'])
                            ->pluck('ID')
                            ->unique()
                            ->reject(function($id) { return empty($id); });

        // Luego, obtén todos los ClientLocal necesarios en una sola consulta
        $clientLocals = ClientLocal::whereIn('NUM', $direccionesIds)->get()->keyBy('NUM');

        // Ahora, procesa las direcciones
        $data = collect($response->json()['DIRECCIONES'])->map(function($direccion) use ($clientLocals) {
            // Salta las direcciones sin ID
            if (empty($direccion['ID'])) {
                return [];
            }

            // Acceso directo a ClientLocal, evitando múltiples consultas
            $detalle = isset($clientLocals[$direccion['ID']]) ? $clientLocals[$direccion['ID']] : null;

            // Construye y retorna la estructura deseada
            return [
                "direccion" => $direccion['GENERAL'] ?? '',
                "localidad" => $direccion['GENERAL_TIT'] ?? '',
                "codigo_postal" => $direccion['ZIPCODE'],
                "name" => $direccion['ALIAS'] == 0 ? '' : $direccion['ALIAS'],
                "id" => $direccion['ID'],
                "default" => !empty($direccion['SELECCIONADO']),
                "detalle" => $detalle,
            ];
        })->filter()->values(); // Filtra cualquier elemento vacío y reindexa

        return $data;
    }

    // /**
    //  * CREAR DIRECCIONES
    //  */
    public function create(Request $request)
    {
        try {
            // dd($request->all());

            $this->token = Auth::user()->api_token;
            // dd($this->token);
            $response = Http::withHeaders([
                            'x-api-key' => $this->token,
                            // 'Content-Type' => 'application/json'
                        ])
                        ->asForm()
                        ->post($this->urlAddress.'Profile::addrSave&app=1', [
                            "addrname" => $request->ADDRESS_NAME,
                            "street" => $request->CALLE_NAME,
                            "streetNumber" => $request->CALLE_NUM,
                            "floor" => $request->CALLE_PISO,
                            "dto" => $request->CALLE_DTO,
                            "zipcode" => $request->ADDRESS_ZIP,
                            "state" => $request->STAT_NUM,
                            "city" => $request->CITY,
                            "comments" => $request->COMMENTS,
                            "deliveryHour" => $request->DELIVERY_HOUR,
                            "method" => 'home_delivery',
                            "id" => '',
                            'alias' => $request->ADDRESS_NAME,
                            "first_name" => 'first name',
                            "last_name" => 'last name',
                            "group_id" => 1000,
                            "dni" => '96085695',
                            "street_name" => $request->CALLE_NAME,
                            "street_number" => $request->CALLE_NUM ,
                            "apartment" => '',
                            "area_code" => '11',
                            "mobile_phone"=> 43523445,
                            "location" =>' La Paternal',
                            "location_custom" =>  "",
                            "drop_off_time"=> 8,
                            "comments" => ''
                        ]);

                dd($response->body());
            $data = $this->getDirecciones();
            return response()->json($data->all());

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
                        "addrname" => $request->ADDRESS_NAME,
                        "street" => $request->CALLE_NAME,
                        "streetNumber" => $request->CALLE_NUM,
                        "floor" => $request->CALLE_PISO,
                        "dto" => $request->CALLE_DTO,
                        "zipCode" => $request->ADDRESS_ZIP,
                        "state" => $request->STAT_NUM,
                        "city" => $request->CITY,
                        "comments" => $request->COMMENTS,
                        "deliveryHour" => $request->DELIVERY_HOUR,
                        "num" => $adress
                    ]);

                    dd($response->body());
        $response = $response->collect();
        if($response['status'] == 'error'){
            return response()->json($response->all());
        }

                //  dd($response->body());
        $data = $this->getDirecciones();
        return response()->json($data->all());

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

    public function getComboDirecciones(Request $request)
    {
        $this->token = Auth::user()->api_token;

        $states   = $this->locationesStates($request->group_id);

        $gba      = $this->locationesGBA($request->group_id);

        $caba     = $this->locationesCABA($request->group_id);

        $integral = $this->locationesIntegral($request->group_id);

        $transportes = $this->getTransportes($request->group_id);

        $horarios = new CheckoutController();
        $horarios = $horarios->getHorarios();

        return [
            'states'      => $states,
            'gba'         => $gba,
            'caba'        => $caba,
            'integral'    => $integral ,
            'transportes' => $transportes,
            "horarios" => json_decode($horarios->getContent())
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
