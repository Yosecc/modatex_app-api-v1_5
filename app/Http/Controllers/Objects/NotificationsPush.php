<?php

namespace App\Http\Controllers\Objects;

use App\Models\NotificationsApp;
use App\Models\NotificationsUserApp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;

class NotificationsPush 
{
    private $notification;
    private $errors = [];
    private $server_key;
    private $user_token;
    private $url = 'https://fcm.googleapis.com/fcm/send';
    private $user_id;

    public function __construct($request)
    {
        try {


            $validator = Validator::make($request['notification'], [
                'title' => 'required',
                'body' => 'required',
            ]);

            if ($validator->fails()) {
                $this->errors = $validator->errors();
                throw new \Exception('Error validate');
            }

            $this->server_key = env('TOKEN_SERVER_FIREBASE_PUSH');

            if(!$this->server_key || $this->server_key == ''){
                $this->errors['server_key'] = 'The firebase server key is required';
                throw new \Exception('Error validate');
            }

            
            $this->notification = $request['notification'];

           
        } catch (\Exception $e) {

           return $e->getMessage(); 
        }  
    }

    public function sendUserNotification($user_id)
    {

        $this->user_token = $this->getUserToken($user_id);
        
        if($this->fails()){
            return $this->getErrors();
        }

        $this->saveNotification();
        
        $response = Http::withHeaders([
            'Authorization' => 'key='.$this->server_key,
        ])->acceptJson()->post($this->url, [
            
             "notification" => [
               "title" => $this->notification['title'],
               "body"  => $this->notification['body'],
               "sound" => "default",
               "image" => $this->notification['image'],
             ],
             // "data"=> {"value"=> "si"},
             "priority"=> "High",
             "to"=> $this->user_token
        ]);

        return $response->status();

    }

    private function saveNotification(): void
    {
        $notificacion = new NotificationsApp();
        $notificacion->title      =  $this->notification['title'];
        $notificacion->body       =  $this->notification['body'];
        $notificacion->image      =  $this->notification['image'];
        $notificacion->data       =  NULL;
        $notificacion->client_num =  $this->user_id;
        $notificacion->save();
        
    }

    public function getUserToken($user_id): string
    {
        try {
            $this->user_id = $user_id;
            $consulta = NotificationsUserApp::where('client_num', $user_id)->first();

            if(!$consulta){
                $this->errors['user_token'] = 'Client does not have app token';
                throw new \Exception('Error validate');
            }

            return $consulta->token;

        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function fails()
    {
        return count($this->errors) ? true : false;
    }
}
