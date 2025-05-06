<?php
namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class OtpHelper {
    //reason 0=login
    public static function setOtp($user_id,$reason=0): array
    {
        $time=Carbon::now();
        $result = DB::table(TABLE_USER_OTPS)->where('user_id',$user_id)->orderBy('id','DESC')->first();
        if($result && is_null($result->last_used_at) && ($result->expires_at>$time)){
                return ['error'=>'OLD_OTP','messages'=>'old otp','otpInfo'=>$result];
        }
        else{
            $itemNew=[];
            $itemNew['user_id']=$user_id;
            $itemNew['reason']=$reason;
            $itemNew['otp']=rand(1000,999999);
            $itemNew['created_at']=$time;
            $itemNew['expires_at']=$time->copy()->addSeconds(ConfigurationHelper::getOtpExpireDuration());
            $itemNew['id'] = DB::table(TABLE_USER_OTPS)->insertGetId($itemNew);
            return ['error'=>'','messages'=>'new otp','otpInfo'=>(object)$itemNew];
        }
    }
    public static function checkOtp($user_id,$otp): array
    {
        $result = DB::table(TABLE_USER_OTPS)->where('user_id',$user_id)->orderBy('id','DESC')->first();
        if($result)
        {
            if($result->otp!= $otp){
                return ['error'=>'OTP_MISMATCHED','messages'=>__('OTP did not matched')];
            }
            if($result->expires_at<Carbon::now()){
                return ['error'=>'OTP_EXPIRED','messages'=>__('OTP expired')];
            }
            if(!(is_null($result->last_used_at))){
                return ['error'=>'OTP_USED','messages'=>__('OTP already used')];
            }
        }
        else{
            return ['error'=>'OTP_INVALID','messages'=>__('OTP not found')];
        }
        return ['error'=>'','otpInfo'=>$result];
    }
    public static function updateOtp($otpInfo){
        $itemNew=[];
        $itemNew['last_used_at']=Carbon::now();
        DB::table(TABLE_USER_OTPS)->where('id',$otpInfo->id)->update($itemNew);
    }

}
