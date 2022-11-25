<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    private $url = 'https://www.modatex.com.ar/modatexrosa3/?c=';
    private $token;

    public function editClient(Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required',
            'last_name'  => 'required',
            'cuit_dni'   => 'required',
        ]);

        $this->token = Auth::user()->api_token; 

        try {
            
        Client::where('num',Auth::user()->num)->update([
                            'first_name'   => $request->first_name,
                            'last_name'    => $request->last_name,
                            'cuit_dni'     => $request->cuit_dni]);
         return response()->json('OK');
       
        } catch (\Exception $e) {
            return response()->json($e->getMessage(),422);
        }

    }

    public function selectMethodEnvio(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'method'  => 'required',
        ]);

        $this->token = Auth::user()->api_token; 

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_select_method']), $request->all());

              return response()->json($response->json());
            
        } catch (\Exception $e) {
            return response()->json($e->getMessage(),422);
        }
    }

    public function searchSucursales(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'zipcode'  => 'required',
        ]);

        $this->token = Auth::user()->api_token;

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_branches']), 
                $request->all());

            $response = $response->json();

            if($response['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }

              return response()->json($response['data']);
            
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function datosEnvio(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'method'     => 'required',
        ]);

        $this->token = Auth::user()->api_token;

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_linked_data']), 
                $request->all());

            $response = $response->json();

            if($response['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
                // throw new \Exception($response->json());
            }

              return response()->json($response['data']);
            
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }    

    public function envioDetail(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'method'     => 'required',
        ]);

        $this->token = Auth::user()->api_token;

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_edit']), 
                $request->all());

            $response = $response->json();

            if($response['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
                // throw new \Exception($response->json());
            }

              return response()->json($response['data']);
            
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function deleteShipping(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'id'         => 'required',
            'method'     => 'required',
        ]);

        $this->token = Auth::user()->api_token;

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_remove']), 
                $request->all());

            $response = $response->json();

            if($response['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response['data'])){
              return response()->json($response['data']);
            }
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function homeDeliveryProviders(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'home_delivery_providers']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function editServiceProvider(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'edit_service_provider']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function datosFacturacion(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'billing_edit']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function selectMethodPayment(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'payment_select']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function getResumen(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'summary']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    private function generateUrl($data)
    {
        return $this->url.$data['controller'].'::'.$data['method'];
    }
}
