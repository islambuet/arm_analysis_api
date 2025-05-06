<?php
namespace App\Http\Controllers\user;

use App\Helpers\ConfigurationHelper;
use App\Helpers\MobileSmsHelper;
use App\Helpers\OtpHelper;
use App\Helpers\TaskHelper;
use App\Helpers\UserHelper;
use App\Http\Controllers\RootController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class UserController extends RootController
{
    public $api_url='user';
    public $permissions;
    public function __construct()
    {
        parent::__construct();
        $this->permissions=TaskHelper::getPermissions($this->api_url,$this->user);
    }
    public function initialize(): JsonResponse
    {
        $response = [];
        $response['error'] ='';
        if($this->user){
            $response['user']=$this->getUserForApi($this->user);
        }
        $response['fiscal_year_starting_month']=ConfigurationHelper::getCurrentFiscalYearStartingMonth();
        return response()->json($response);
    }
    public function login(Request $request): JsonResponse
    {
        //input validation start
        $validation_rule = [];
        $validation_rule['username'] = ['required', 'alpha_dash'];
        $validation_rule['password'] = ['required'];
        $validation_rule['otp'] = ['min:4','max:6'];


        $itemNew =$request->input('item');

        $this->validateInputKeys($itemNew,array_keys($validation_rule));

        $this->validateInputValues($itemNew, $validation_rule);
        //input validation end
        $user = DB::table(TABLE_USERS)->where('username', $itemNew['username'])->first();
        if($user){
            $time=Carbon::now();
            if ($user->status == SYSTEM_STATUS_ACTIVE) {
                if (Hash::check($itemNew['password'], $user->password)) {
                    $mobile_verification_required=true;
                    //check mobile verification required
                    //1.if personal verification off
                    if(isset($itemNew['otp'])){
                        //check and verify otp
                        $otpCheckInfo=OtpHelper::checkOtp($user->id,$itemNew['otp']);
                        if(!$otpCheckInfo['error']){
                            OtpHelper::updateOtp($otpCheckInfo['otpInfo']);
                            $mobile_verification_required=false;
                        }
                        else{
                            return response()->json($otpCheckInfo);
                        }
                    }
                    else if($user->mobile_authentication_off_end>$time)//for user if inactive
                    {
                        $mobile_verification_required=false;
                    }
                    //2.if global verification off
                    else if(!ConfigurationHelper::isLoginMobileVerificationOn()){
                        $mobile_verification_required=false;
                    }
                    //3.check browser validated before
                    else{
                        $authTokenInfo=UserHelper::getAuthTokenInfo();
                        if($authTokenInfo){
                            //was Logged within 10 days
                            if(($authTokenInfo->user_id== $user->id) &&($authTokenInfo->expires_at> $time->copy()->subDays(10))){
                                $mobile_verification_required=false;
                            }
                        }
                    }
                    if($mobile_verification_required){
                        if(!($user->mobile_no)){
                            return response()->json(['error' => 'MOBILE_NUMBER_NOT_SET', 'messages' => 'Your mobile number is not set']);
                        }
                        if(!ConfigurationHelper::getMobileSmsApiToken()){
                            return response()->json(['error' => 'SMS_TOKEN_NOT_SET', 'messages' => 'SMS system is not set']);
                        }
                        $otpResponse=OtpHelper::setOtp($user->id);
                        if(!$otpResponse['error']){
                            MobileSmsHelper::send_sms(MobileSmsHelper::$API_SENDER_ID_MALIK_SEEDS,$user->mobile_no,'Verification code for RND login: '.$otpResponse['otpInfo']->otp,'text');
                        }

                        return response()->json(['error' => 'VERIFY_MOBILE', 'messages' => $otpResponse['messages'],'otp'=>$otpResponse['otpInfo']->otp^111111]);
                    }
                    else{
                        //user
                        $user->authToken=UserHelper::getNewAuthToken($user);
                        $user->userGroupRole = TaskHelper::getUserGroupRole($user->user_group_id);
                        //menus
                        $response['error']='';
                        $response['messages']=__('Logged in successfully');
                        $response['user']=$this->getUserForApi($user);
                        return response()->json($response);
                    }

                }
                else{
                    //TODO wrong consecutive password settings
                    return response()->json(['error' => 'INVALID_CREDENTIALS', 'messages' => __('Wrong Password')]);
                }

            }
            else{
                return response()->json(['error' => 'USER_INACTIVE', 'messages' => __('This user account has been suspended')]);
            }
        }
        else{
            return response()->json(['error' => 'USER_NOT_FOUND', 'messages' => __('This user does not exits')]);
        }
    }
    private function getUserForApi($user): object
    {
        $apiUser= (object) [];
        foreach(['id','name','authToken'] as $key){
            $apiUser->$key=$user->$key;
        }
        $apiUser->infos = (object)($user->infos ? json_decode($user->infos, true) :  []);
        $apiUser->profile_picture = property_exists($apiUser->infos,'profile_picture')?$apiUser->infos->profile_picture:'';
        $apiUser->tasks=TaskHelper::getUserGroupTasks($user->userGroupRole);
        return $apiUser;
    }
    public function logout(): JsonResponse
    {
        $authTokenInfo=UserHelper::getAuthTokenInfo();
        if($authTokenInfo){
            DB::table(TABLE_USER_AUTH_TOKENS)->where('id',$authTokenInfo->id)->update(['expires_at'=>Carbon::now()]);
        }
        return response()->json(['error' => '', 'messages' => __('Logout success')]);
    }
    public function ChangePassword(Request $request): JsonResponse{
        $itemId=$this->user->id;
        $this->checkSaveToken();

        $validation_rule=[];
        $validation_rule['password_old']=['required'];
        $validation_rule['password_new']=['required','min:4'];

        $itemNew =$request->input('item');
        $itemOld =[];

        $this->validateInputKeys($itemNew,array_keys($validation_rule));
        $this->validateInputValues($itemNew, $validation_rule);
        if($itemNew['password_old']==$itemNew['password_new']){
            return response()->json(['error'=>'VALIDATION_FAILED','messages'=>__('Old and New password are same')]);
        }
        if(!(Hash::check($itemNew['password_old'],$this->user->password))){
            return response()->json(['error'=>'VALIDATION_FAILED','messages'=>__('Old password is wrong')]);
        }

        DB::beginTransaction();
        try{
            $time=Carbon::now();
            $dataHistory=[];
            $dataHistory['table_name']=TABLE_USER_HIDDEN_COLUMNS;
            $dataHistory['controller']=(new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method']=__FUNCTION__;

            $newPasswordHash=Hash::make($itemNew['password_new']);
            DB::table(TABLE_USERS)->where('id',$itemId)->update(['updated_by'=>$this->user->id,'updated_at'=>$time,'password'=>$newPasswordHash]);
            $dataHistory['table_id']=$itemId;
            $dataHistory['action']=DB_ACTION_EDIT;


            $dataHistory['data_old']=json_encode(['password'=>$this->user->password]);
            $dataHistory['data_new']=json_encode(['password'=>$newPasswordHash]);
            $dataHistory['created_at']=$time;
            $dataHistory['created_by']=$this->user->id;
            $this->dBSaveHistory($dataHistory,TABLE_SYSTEM_HISTORIES);
            DB::table(TABLE_USER_AUTH_TOKENS)->where('user_id',$itemId)->where('expires_at','>',$time)->update(['expires_at'=>$time]);
            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '']);
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages'=>__('Failed to save.')]);
        }
    }
    public function profilePicture(Request $request): JsonResponse{

        $itemId=$this->user->id;
        $this->checkSaveToken();
        $infos = ($this->user->infos ? json_decode($this->user->infos, true) :  []);
        $itemOld['infos']['profile_picture']=array_key_exists('profile_picture',$infos)?$infos['profile_picture']:'';

        $inputData =$request->input('item',[]);

        $infos['profile_picture']=array_key_exists('profile_picture',$inputData)?$inputData['profile_picture']:'';
        DB::beginTransaction();
        try{
            $time=Carbon::now();
            $dataHistory=[];
            $dataHistory['table_name']=TABLE_USER_HIDDEN_COLUMNS;
            $dataHistory['controller']=(new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method']=__FUNCTION__;

            DB::table(TABLE_USERS)->where('id',$itemId)->update(['updated_by'=>$this->user->id,'updated_at'=>$time,'infos'=>json_encode($infos)]);
            $dataHistory['table_id']=$itemId;
            $dataHistory['action']=DB_ACTION_EDIT;

            $dataHistory['data_old']=json_encode($itemOld);
            $dataHistory['data_new']=json_encode(['infos'=>['profile_picture'=>$infos['profile_picture']]]);
            $dataHistory['created_at']=$time;
            $dataHistory['created_by']=$this->user->id;
            $this->dBSaveHistory($dataHistory,TABLE_SYSTEM_HISTORIES);
            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '']);
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages'=>__('Failed to save.')]);
        }
    }
}
