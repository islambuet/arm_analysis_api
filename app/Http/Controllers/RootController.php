<?php
namespace App\Http\Controllers;
use App\Helpers\ConfigurationHelper;
use App\Helpers\UserHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

abstract class RootController extends Controller
{

    public $user;
    public function __construct()
    {
        ConfigurationHelper::load_config();
        $this->checkApiOffline();
        $this->user=UserHelper::getLoggedUser();
    }
    public function sendErrorResponse($errorResponse){
        $response = response()->json($errorResponse);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->send();
        exit;
    }
    private function checkApiOffline()
    {
        if(ConfigurationHelper::isApiOffline())
        {
            /** @noinspection PhpUndefinedClassInspection */
            $path=\Request::path();
            if(!(
                str_starts_with($path, 'api/user/')||
                str_starts_with($path, 'api/system-configurations/')

            ))
            {
                $this->sendErrorResponse(['error'=>'API_OFFLINE','messages' => __('Site is Currently Offline.')]);
            }
        }
    }
    public function validateInputKeys($inputs,$keys){
        if(!is_array($inputs)){
            $this->sendErrorResponse(['error'=>'INPUT_NOT_FOUND','messages' => __('Input Not Found')]);
        }
        //checking if any invalid input
        foreach($inputs as $key=>$value){
            if( !$key || (!in_array ($key,$keys))){
                $this->sendErrorResponse(['error'=>'VALIDATION_FAILED','messages'=>__($key. ' is not a valid Input')]);
            }
        }
    }
    public function validateInputValues($inputs, $validation_rule)
    {
        $validator = Validator::make($inputs, $validation_rule);
        if ($validator->fails()) {
            $this->sendErrorResponse(['error'=>'VALIDATION_FAILED','messages'=>$validator->errors()]);
        }
    }
    public function checkSaveToken(){
        /** @noinspection PhpUndefinedClassInspection */
        $saveToken=\Request::input('save_token','');
        if(!$saveToken){
            $this->sendErrorResponse(['error'=>'VALIDATION_FAILED','messages' => __('Save Token Missing')]);
        }
        else if (!ctype_alnum( str_replace(['-','_'], '', $saveToken) ) ) {
            $this->sendErrorResponse(['error'=>'VALIDATION_FAILED','messages' => __('Save Token Invalid')]);
        }
        else if($this->user->authTokenInfo->save_token==$saveToken){
            $this->sendErrorResponse(['error'=>'DATA_ALREADY_SAVED','messages' => __('Data already Saved')]);
        }
    }
    public function updateSaveToken(){
        /** @noinspection PhpUndefinedClassInspection */
        $saveToken=\Request::input('save_token','');
        DB::table(TABLE_USER_AUTH_TOKENS)->where('id',$this->user->authTokenInfo->id)->update(['save_token'=>$saveToken]);
    }
    /*
	**$data['table_name']	:Save table name
	**$data['table_id']	 	:Action id
	**$data['controller'] 	:Controller Name of the Route
	**$data['method']: 		:Function Name of the Controller
	**$data['data_old']		:Previous data
	**$data['data_new']		:New Data
	**$data['action']		:Add/Edit/Delete
	**$data['created_at']	:Creating time
    **$data['created_by']	:Action User

	**$tableHistory			:Name of the history table:='ams_back.system_histories'

	*/
    public function dBSaveHistory($data,$tableHistory){
        DB::table($tableHistory)->insertGetId($data);
    }

}
