<?php
namespace App\Http\Controllers\users;

use App\Helpers\TaskHelper;
use App\Http\Controllers\RootController;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;


class UsersController extends RootController
{
    public $api_url='users';
    public $permissions;
    public function __construct()
    {
        parent::__construct();
        $this->permissions=TaskHelper::getPermissions($this->api_url,$this->user);
    }

    public function initialize(): JsonResponse
    {
        if ($this->permissions->action_0 == 1){
            $response= [];
            $response['error'] = '';
            $response['permissions'] = $this->permissions;
            $response['hidden_columns'] =TaskHelper::getHiddenColumns($this->api_url,$this->user);
            if($this->user->user_group_id==ID_USERGROUP_SUPERADMIN)
            {
                $response['user_groups']= DB::table(TABLE_USER_GROUPS)->select('id','name')->orderBy('id', 'ASC')->get()->toArray();
            }
            else{
                $response['user_groups']= DB::table(TABLE_USER_GROUPS)->select('id','name')->where('id','!=',ID_USERGROUP_SUPERADMIN)->orderBy('id', 'ASC')->get()->toArray();
            }
            $response['location_parts'] = DB::table(TABLE_LOCATION_PARTS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_areas'] = DB::table(TABLE_LOCATION_AREAS)
                ->select('id', 'name','part_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_territories'] = DB::table(TABLE_LOCATION_TERRITORIES)
                ->select('id', 'name','area_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            return response()->json($response);
        }
        else{
            return response()->json(['error'=>'ACCESS_DENIED','messages'=>__('You do not have access on this page')]);
        }
    }
    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1){
            $response=[];
            $response['error'] = '';
            $perPage=$request->input('perPage',2);
            /** @noinspection DuplicatedCode */
            $query=DB::table(TABLE_USERS.' as users');
            $query->select('users.id','users.employee_id','users.username','users.user_group_id','users.name','users.email','users.mobile_no','users.ordering','users.status','users.created_at');
            $query->join(TABLE_USER_GROUPS.' as user_groups', 'user_groups.id', '=', 'users.user_group_id');
            $query->addSelect('user_groups.name as user_group_name');
            $query->join(TABLE_USER_TYPES.' as user_types', 'user_types.id', '=', 'users.user_type_id');
            $query->addSelect('user_types.name as user_type_name');
            $query->orderBy('users.id', 'DESC');
            $query->orderBy('users.ordering', 'ASC');
            $query->where('users.status','!=',SYSTEM_STATUS_DELETE);//
            if($perPage==-1){
                $perPage=$query->count();
            }
            $results=$query->paginate($perPage)->toArray();
            $response['items'] = $results;
            return response()->json($response);
        }
        else{
            return response()->json(['error'=>'ACCESS_DENIED','messages'=>__('You do not have access on this page')]);
        }
    }
    public function getItem(Request $request,$itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1){
            /** @noinspection DuplicatedCode */
            $query=DB::table(TABLE_USERS.' as users');
            $query->select('users.id','users.employee_id','users.username','users.user_group_id','users.name','users.email','users.mobile_no',
                'users.part_id','users.area_id','users.territory_id',
                'users.ordering','users.status','users.max_logged_browser','users.mobile_authentication_off_end','users.created_at');
            $query->join(TABLE_USER_GROUPS.' as user_groups', 'user_groups.id', '=', 'users.user_group_id');
            $query->addSelect('user_groups.name as user_group_name');
            $query->join(TABLE_USER_TYPES.' as user_types', 'user_types.id', '=', 'users.user_type_id');
            $query->addSelect('user_types.name as user_type_name');
            $query->where('users.id','=',$itemId);
            $result = $query->first();
            if(!$result){
                return response()->json(['error'=>'ITEM_NOT_FOUND','messages'=>__('Invalid Id '.$itemId)]);
            }
            if($result->mobile_authentication_off_end< Carbon::now()){
                $result->mobile_authentication_off_end=null;
            }
            $response=[];
            $response['error'] = '';
            $response['item'] = $result;
            return response()->json($response);
        }
        else{
            return response()->json(['error'=>'ACCESS_DENIED','messages'=>$this->permissions]);
        }
    }
    public function saveItem(Request $request): JsonResponse{
        $itemId = $request->input('id',0);
        //permission checking start
        if($itemId>0){
            if ($this->permissions->action_2 != 1){
                return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
            }
        }
        else{
            if ($this->permissions->action_1 != 1){
                return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
            }
        }
        //permission checking passed
        $this->checkSaveToken();
        //Input validation start
        $validation_rule = [];
        $validation_rule['employee_id'] = ['required', 'alpha_dash'];
        $validation_rule['username'] = ['required', 'alpha_dash'];
        $validation_rule['password'] = ['required','min:4'];
        $validation_rule['user_group_id'] = ['required'];
        $validation_rule['part_id'] = ['numeric'];
        $validation_rule['area_id'] = ['numeric'];
        $validation_rule['territory_id'] = ['numeric'];
        $validation_rule['name'] = ['required'];
        $validation_rule['email'] = ['required','email'];
        $validation_rule['mobile_no'] = ['required'];

        $validation_rule['ordering']=['numeric'];
        $validation_rule['status']=[Rule::in([SYSTEM_STATUS_ACTIVE, SYSTEM_STATUS_INACTIVE])];
        $validation_rule['mobile_authentication_off_end']=['nullable','date'];
        $validation_rule['max_logged_browser']=['numeric'];

        $itemNew =$request->input('item');
        if(array_key_exists('part_id',$itemNew) && !($itemNew['part_id']>0)){

            $itemNew['part_id']=0;
        }
        if(array_key_exists('area_id',$itemNew) && !($itemNew['area_id']>0)){

            $itemNew['area_id']=0;
        }
        if(array_key_exists('territory_id',$itemNew) && !($itemNew['territory_id']>0)){

            $itemNew['territory_id']=0;
        }

        $itemOld =[];

        $this->validateInputKeys($itemNew,array_keys($validation_rule));

        //edit change checking
        if($itemId>0){
            $result = DB::table(TABLE_USERS)->select(array_keys($validation_rule))->find($itemId);
            if(!$result){
                return response()->json(['error'=>'ITEM_NOT_FOUND','messages'=>__('Invalid Id '.$itemId)]);
            }
            $itemOld=(array)$result;
            foreach($itemOld as $key=>$oldValue){
                if(array_key_exists($key,$itemNew)){
                    if($key=='password'){
                        //if password is blank means no change
                        if(!$itemNew[$key]){
                            unset($itemNew[$key]);
                            unset($itemOld[$key]);
                            unset($validation_rule[$key]);
                        }
                    }
                    else if($oldValue==$itemNew[$key]){
                        //unchanged so remove from both
                        unset($itemNew[$key]);
                        unset($itemOld[$key]);
                        unset($validation_rule[$key]);
                    }
                }
                else{
                    //will not happen if it comes form vue. removing rule and key for not change
                    unset($validation_rule[$key]);
                    unset($itemOld[$key]);
                }
            }
        }
        //if itemNew Empty
        if(!$itemNew){
            return response()->json(['error'=>'VALIDATION_FAILED','messages'=> 'Nothing was Changed']);
        }
        $this->validateInputValues($itemNew, $validation_rule);

        if(array_key_exists('user_group_id',$itemNew)){
            //checking super admin group
            if(($itemNew['user_group_id']==ID_USERGROUP_SUPERADMIN) && ($this->user->user_group_id!=ID_USERGROUP_SUPERADMIN)){
                return response()->json(['error'=>'VALIDATION_FAILED','messages'=> 'Invalid user group']);
            }
        }
        if(array_key_exists('username',$itemNew)){
            $result = DB::table(TABLE_USERS)->where('username', $itemNew['username'])->first();
            if ($result) {
                return response()->json(['error'=>'VALIDATION_FAILED','messages'=> 'username exist']);
            }
        }
        //hashing password
        if(array_key_exists('password',$itemNew)){
            $itemNew['password']=Hash::make($itemNew['password']);
        }
        //Input validation ends
        DB::beginTransaction();
        try{
            $time=Carbon::now();
            $dataHistory=[];
            $dataHistory['table_name']=TABLE_USERS;
            $dataHistory['controller']=(new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method']=__FUNCTION__;
            $newId=$itemId;
            if($itemId>0){
                $itemNew['updated_by']=$this->user->id;
                $itemNew['updated_at']=$time;
                DB::table(TABLE_USERS)->where('id',$itemId)->update($itemNew);
                $dataHistory['table_id']=$itemId;
                $dataHistory['action']=DB_ACTION_EDIT;
                //logout from all device
                if(array_key_exists('password',$itemNew)){
                    //logout from all device
                    DB::table(TABLE_USER_AUTH_TOKENS)->where('user_id',$itemId)->where('expires_at','>',$time)->update(['expires_at'=>$time]);
                }
            }
            else{
                $itemNew['created_by']=$this->user->id;
                $itemNew['created_at']=$time;
                $newId = DB::table(TABLE_USERS)->insertGetId($itemNew);
                $dataHistory['table_id']=$newId;
                $dataHistory['action']=DB_ACTION_ADD;
            }

            $dataHistory['data_old']=json_encode($itemOld);
            $dataHistory['data_new']=json_encode($itemNew);
            $dataHistory['created_at']=$time;
            $dataHistory['created_by']=$this->user->id;

            $this->dBSaveHistory($dataHistory,TABLE_SYSTEM_HISTORIES);
            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '','messages' =>'User ('.$newId.')'.($itemId>0?'Updated':'Created').')  Successfully']);
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages'=>__('Failed to save.')]);
        }
    }
}
