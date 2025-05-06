<?php
namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserHelper {

	public static $loggedUser = null;
	public static function getLoggedUser(){
        if (!UserHelper::$loggedUser) {
            $authTokenInfo=self::getAuthTokenInfo();
            if($authTokenInfo){
                $time=Carbon::now();
                if($authTokenInfo->expires_at>$time){
                    $user = DB::table(TABLE_USERS)->where('id',$authTokenInfo->user_id)->where('status',SYSTEM_STATUS_ACTIVE)->first();
                    if($user){
                        //AuthToken ='id_token'
                        $user->authToken=$authTokenInfo->id.'_'.$authTokenInfo->token;
                        $user->authTokenInfo=$authTokenInfo;
                        //update AuthToken
                        DB::table(TABLE_USER_AUTH_TOKENS)->where('id',$authTokenInfo->id)->update(['last_used_at'=>$time,'expires_at'=>$time->copy()->addHours(ConfigurationHelper::getLoginSessionExpireHours())]);
                        $user->userGroupRole = TaskHelper::getUserGroupRole($user->user_group_id);
                        self::$loggedUser=$user;
                    }
                }
            }
        }
        return UserHelper::$loggedUser;
    }
    public static function getAuthTokenInfo(){
        $tokenInfo=null;
        //AuthToken ='id_token'
        $bearerToken= explode('_',\Request::bearerToken(),2);
        if(count($bearerToken)==2){
            $query=DB::table(TABLE_USER_AUTH_TOKENS);
            $query->where('id',$bearerToken[0]);
            $query->where('token',$bearerToken[1]);
            $result = $query->first();
            if($result){
                $tokenInfo=$result;
            }
        }
        return $tokenInfo;

    }
    public static function getNewAuthToken($user): string
    {
        //generate token
        $authToken=Hash::make(bin2hex(random_bytes(rand(10,15))));
        $clientInfo=array();
        $clientInfo['REMOTE_ADDR']=\Request::server('REMOTE_ADDR');
        $clientInfo['HTTP_USER_AGENT']=\Request::server('HTTP_USER_AGENT');

        $time=Carbon::now();
        //inactive browsers ids
        $removeTokenIds=array();
        $query=DB::table(TABLE_USER_AUTH_TOKENS);
        $query->where('user_id',$user->id);
        $query->where('expires_at','>=',$time);
        $query->orderBy('id','DESC');
        $query->offset($user->max_logged_browser-1);
        $query->limit(500);
        $results = $query->get();
        foreach ($results as $result) {
            $removeTokenIds[]=$result->id;
        }

        DB::beginTransaction();
        try{
            //save token with client info

            $itemNew=array();
            $itemNew['user_id']=$user->id;
            $itemNew['token']=$authToken;
            $itemNew['device_info']=json_encode($clientInfo);
            $itemNew['created_at']=$time;
            $itemNew['last_used_at']=$time;
            $itemNew['expires_at']=$time->copy()->addHours(ConfigurationHelper::getLoginSessionExpireHours());
            $id = DB::table(TABLE_USER_AUTH_TOKENS)->insertGetId($itemNew);
            // and inactive max browser token
            if($removeTokenIds){
                DB::table(TABLE_USER_AUTH_TOKENS)->whereIn('id',$removeTokenIds)->update(['expires_at'=>$time]);
            }
            DB::commit();
        }
        catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages'=>__('Failed to save.')]);
        }
        //AuthToken ='id_token'
        return $id.'_'.$authToken;
    }
}
