<?php
namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class MobileSmsHelper {
    public static $API_URL='http://bangladeshsms.com/smsapi';
    public static $API_SENDER_ID_MALIK_SEEDS='Malik Seeds';
    //$type= text for normal SMS/unicode for Bangla SMS

    /** @noinspection PhpComposerExtensionStubsInspection */
    public static function send_sms($sender_id, $contacts, $msg, $type='unicode')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$API_URL);

        curl_setopt($ch, CURLOPT_POST,TRUE);
        $data = array();
        $data['api_key']=ConfigurationHelper::getMobileSmsApiToken();
        $data['senderid']=$sender_id;
        $data['type']=$type;
        $data['contacts']=$contacts;
        $data['msg']=$msg;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,TRUE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);//wait for response
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout in seconds 2min
        $response = curl_exec($ch);

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $itemNew=array();
        $itemNew['sender_id']=$sender_id;
        $itemNew['contacts']=$contacts;
        $itemNew['msg']=$msg;
        $itemNew['status_http']=$http_status;
        $itemNew['status_sms']=$response;
        $itemNew['created_at']=Carbon::now();
        DB::table(TABLE_MOBILE_SMS_HISTORIES)->insertGetId($itemNew);
    }
}
