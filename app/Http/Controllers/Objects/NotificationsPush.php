<?php

namespace App\Http\Controllers\Objects;

use App\Models\NotificationsApp;
use App\Models\NotificationsUserApp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class NotificationsPush 
{
    private $notification;
    private $errors = [];
    private $server_key;
    private $user_token;
    private $url = 'https://fcm.googleapis.com/v1/projects/modatex-e6ba5/messages:send'; // API HTTP v1
    private $user_id;
    private $tokens;
    private $request;

    public function __construct($request)
    {
        try {
            $validator = Validator::make($request['notification'], [
                'title' => 'required',
                'body' => 'required',
            ]);

            if ($validator->fails()) {
                $this->errors = $validator->errors();
                \Log::info($this->errors);
                throw new \Exception('Error validate');
            }

            $this->server_key = env('TOKEN_SERVER_FIREBASE_PUSH');

            if(!$this->server_key || $this->server_key == ''){
                $this->errors['server_key'] = 'The firebase server key is required';
                \Log::info($this->errors);
                throw new \Exception('Error validate');
            }

            $this->notification = $request['notification'];
            $this->request = $request;

        } catch (\Exception $e) {
            return $e->getMessage(); 
        }  
    }

    public function sendUserNotification($user_id)
    {
        $this->tokens = $this->getUserToken($user_id);

        if($this->fails()){
            \Log::info($this->errors);
            return $this->getErrors();
        }

        $this->request['data']['image'] = isset($this->notification['image']) ? $this->notification['image']:"";

        // Enviar notificaciones a múltiples tokens en una sola solicitud
        $response = Http::withHeaders([
            'Authorization' => 'key='.$this->server_key,
            'Content-Type' => 'application/json',
        ])->post($this->url, [
            "message" => [
                "notification" => [
                    "title" => $this->notification['title'],
                    "body"  => $this->notification['body'],
                    "sound" => "default",
                    "image" => isset($this->notification['image']) ? $this->notification['image']:"",
                ],
                "data"=> $this->request['data'],
                "android" => [
                    "priority" => "high",
                ],
                "registration_ids" => $this->tokens, // Enviar a múltiples tokens
            ]
        ]);

        return $response->status();
    }

    // ... (resto del código)
}
