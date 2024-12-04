<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Objects\NotificationsPush;
use App\Models\NotificationsApp;
use App\Models\Links;
use App\Models\NotificationsUserApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;


class NotificationsUserAppController extends Controller
{
    private $token;
    private  $url = 'https://www.modatex.com.ar/modatexrosa3/helpers/Notifications_app_endpoint.php';

    public function notification_send(Request $request)
    {
        $this->validate($request, [
            'notification' => 'required'
        ]);

        $validator = Validator::make($request->notification, [
            'title' => 'required',
            'body' => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }


        // dd(Auth::user()->num);
        $notification = new NotificationsPush($request->all());
        $notificacion = $notification->sendUserNotification(Auth::user()->num);


        if($notification->fails()){
             return response()->json($notification->getErrors(), 422);
        }

        return response()->json($notificacion);
    }

    public function save_token(Request $request)
    {

        $this->validate($request, [
            'token' => 'required'
        ]);

        $notification = NotificationsUserApp::updateOrInsert([
            'client_num' => Auth::user()->num ,
            'platform' => 'app',
            'device' => $request->device
        ],[
            'token' => $request->token
        ]);

        return response()->json(NotificationsUserApp::where('client_num',Auth::user()->num)->get());
    }

    public function getTokens()
    {
        return NotificationsUserApp::where('client_num',Auth::user()->num)->get();
    }

    public function get_notifications()
    {
        $this->token = Auth::user()->api_token;
        // dd(Auth::user()->num);
        $array = array(
            "redirect" => array(
                "route" => "order",
                "params" => array(
                    "id" => Auth::user()->num
                )
            )
        );
        // dd($this->url, $this->token);
        $response = Http::
        withHeaders([
            'x-api-key' => $this->token,
        ])
        ->acceptJson()
        ->post('https://www.modatex.com.ar/modatexrosa3/helpers/Notifications_app_endpoint.php',
            $array
        );

        return response()->json($response->collect()->map(function($not){
            $not['redirect'] = json_decode($not['redirect'])->redirect;
            return $not;
        }));

        // dd($response->status(s),$response->json());
    }

    /**
     * DEPRECADO
     */

    // public function get_notifications()
    // {
    //     $noti = NotificationsApp::
    //                 where(function ($query) {
    //                     $query->where('type', 'LIKE', '%massive_msg.Mensaje%')
    //                           ->orWhere('client_num', Auth::user()->num);
    //                 })
    //                 ;
    //     if($noti->count()){
    //         $notificationsIds = $noti->pluck('num');
    //         $links = Links::whereIn('section_id', $notificationsIds->toArray() )->get();

    //         $noti = $noti->map(function($item) use ($links) {
    //             $redirect = $links->where('section_id', $item['num'])->first();
    //             return [
    //                 'body' => html_entity_decode($item['msg']),
    //                 'title' => html_entity_decode($item['title']),
    //                 'id' => $item['num'],
    //                 'created_at' => $item['created_at'],
    //                 'image' => 'https://netivooregon.s3.amazonaws.com/'.$item['img'],
    //                 'redirect' =>  $redirect ? json_decode($redirect->data_json) : NULL,
    //             ];
    //         });

    //     }


    //     return response()->json($noti);
    // }
}
