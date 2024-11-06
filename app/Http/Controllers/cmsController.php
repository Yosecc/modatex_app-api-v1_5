<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PagesCms;
use Illuminate\Support\Facades\Http;
use Auth;
use Illuminate\Support\Facades\Cache;

class cmsController extends Controller
{
    private $url = 'https://www.modatex.com.ar/?c=';

    public function get($id)
    {   
        $cms = PagesCms::find($id);
        $cms = [
            'name' => $cms->title,  
            'editor' => $cms->data_json,  
        ];
        
        return response()->json($cms);
    }

    public static function searchCms($params = ['slug' => null, 'id' => 'null'])
    {
        if(isset($params['id']) && $params['id']!=null){
            $cms = PagesCms::find($params['id']);
        }

        if(isset($params['slug']) &&  $params['slug']!=null){
            $cms = PagesCms::where('slug','like','%'.$params['slug'].'%')->first();
        }

        $cms = [    
            'name' => $cms ? ($cms->title_app != '' ? $cms->title_app : $cms->title ) : null,  
            'id' => $cms ? $cms->id : null,  
            'editor' => $cms ? $cms->data_json  : null,  
        ];

        return $cms;
    }

    private function utf8_decode_recursive($mixed) {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8_decode_recursive($value);
            }
        } elseif (is_object($mixed)) {
            $vars = get_object_vars($mixed);
            foreach ($vars as $key => $value) {
                $mixed->$key = $this->utf8_decode_recursive($value);
            }
        } elseif (is_string($mixed)) {
            // Verificar si la cadena es binaria
            if (strpos($mixed, 'b"') === 0) {
                // Eliminar el prefijo 'b"' y decodificar la cadena binaria
                $mixed = substr($mixed, 2); // Elimina el prefijo 'b"'
                $mixed = utf8_decode($mixed); // Decodificar la cadena binaria
            } else {
                // La cadena no es binaria, decodificarla como UTF-8
                $mixed = utf8_decode($mixed);
            }
        }
        return $mixed;
    }
    
    
      private function coding($json_text){
    
        // Función para aplicar utf8_decode recursivamente
    
        // Decodificar el JSON
        $data = json_decode($json_text);

        $this->token = Auth::user()->api_token;

        $data = collect([$data])->map( function($datos) {
            $datos->blocks = collect($datos->blocks)->map(function($block) {
                if($block->type == 'Marcas'){
                    $response = Http::withHeaders([
                        'x-api-key' => $this->token,
                        'x-api-device' => 'APP'
                    ])
                    ->acceptJson()
                    ->get('https://www.modatex.com.ar/?c=Cms::grupoMarcas&type='.$block->data->group.'&max='.$block->data->max.'&min='.$block->data->min.'');
               
                    $block->data->marcas = $response->collect()->map(function($marca){
                        $storesCache = Cache::get('stores');
                        $marcaCache = $storesCache->where('id',$marca['id'])->first();
                        if(isset($marca['envio'])){
                            $marcaCache['envio'] = $marca['envio'];
                        }
                        return $marcaCache;
                    });
                }
                return $block;
            })->all();
            return $datos;
        })->first();


        // Aplicar utf8_decode a los valores de cadena
        $data = $this->utf8_decode_recursive($data);
        
        // Codificar el objeto PHP como JSON nuevamente
        $json_output = json_encode($data);
        // dd($json_output, $data);
    
        // Imprimir el resultado
        return $json_output;
      }
}
