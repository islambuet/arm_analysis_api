<?php
namespace App\Http\Controllers\setup;

use App\Helpers\ConfigurationHelper;
use App\Helpers\TaskHelper;
use App\Http\Controllers\RootController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Http\Controllers\research\str_contains;


class IncentiveVarietiesController extends RootController
{
    public $api_url = 'setup/incentive_varieties';
    public $permissions;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
    }

    public function initialize(): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $response = [];
            $response['error'] ='';
            $response['permissions']=$this->permissions;
            $response['hidden_columns']=TaskHelper::getHiddenColumns($this->api_url,$this->user);
            $response['crops']  = DB::table(TABLE_CROPS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES)
                ->select('id', 'name','crop_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['varieties']=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id')
                ->addSelect('crop_types.name as crop_type_name')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name')
                ->where('whose', 'ARM')
                ->get();
            $response['incentive_slabs'] = DB::table(TABLE_INCENTIVE_SLABS)
                ->select('id', 'name','fiscal_year')
                ->orderBy('id', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            return response()->json($response);

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItems(Request $request,$fiscalYear): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $current_fiscal_year=date("Y");
            if(date('m')<ConfigurationHelper::getCurrentFiscalYearStartingMonth()){
                $current_fiscal_year--;
            }
            $results = DB::table(TABLE_INCENTIVE_VARIETIES)
                ->select('id', 'variety_id','incentive')
                ->where('fiscal_year', $fiscalYear)
                ->get();
            $response = [];
            $response['error'] ='';
            $response['items']=[];
            foreach ($results as $result){
                if($result->incentive)
                {
                    $response['items'][$result->variety_id]=json_decode($result->incentive);
                }

            }
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItem(Request $request,$fiscalYear, $itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $result = DB::table(TABLE_INCENTIVE_VARIETIES)
                ->select('id', 'variety_id','incentive')
                ->where('variety_id','=',$itemId)
                ->where('fiscal_year', $fiscalYear)
                ->first();
            $response = [];
            $response['error'] ='';
            $response['incentive']=(object)[];
            if($result && $result->incentive){
                $response['incentive']=json_decode($result->incentive);
            }
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function saveItem(Request $request): JsonResponse
    {
        if( ($this->permissions->action_2 != 1) ||($this->permissions->action_1 != 1)) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access')]);
        }

        //permission checking passed
        $this->checkSaveToken();
        //Input validation start
        $validation_rule = [];
        $validation_rule['fiscal_year'] = ['required','numeric'];
        $validation_rule['variety_id'] = ['required','numeric'];
        $validation_rule['incentive'] = ['required'];
        $itemNew = $request->input('item');
        $itemOld = [];
        $itemId=0;

        if (!$itemNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Inputs was Not found']);
        }
        $this->validateInputKeys($itemNew, array_keys($validation_rule));

        $itemNew['incentive']=json_encode($itemNew['incentive']);

        $result = DB::table(TABLE_INCENTIVE_VARIETIES)->where('variety_id','=',$itemNew['variety_id'])->where('fiscal_year','=',$itemNew['fiscal_year'])->first();
        if($result){
            $itemOld=(array)$result;
            $itemId=$itemOld['id'];
            foreach ($itemOld as $key => $oldValue) {
                if (array_key_exists($key, $itemNew)) {
                    if ($oldValue == $itemNew[$key]) {
                        //unchanged so remove from both
                        unset($itemNew[$key]);
                        unset($itemOld[$key]);
                    }
                } else {
                    //only for select query keys
                    unset($itemOld[$key]);
                }
            }
        }
        if (!$itemNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Nothing was Changed']);
        }
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_INCENTIVE_VARIETIES;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_INCENTIVE_VARIETIES)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_INCENTIVE_VARIETIES)->insertGetId($itemNew);
                $dataHistory['table_id'] = $newId;
                $dataHistory['action'] = DB_ACTION_ADD;
            }
            unset($itemNew['updated_by'],$itemNew['created_by'],$itemNew['created_at'],$itemNew['updated_at']);

            $dataHistory['data_old'] = json_encode($itemOld);
            $dataHistory['data_new'] = json_encode($itemNew);
            $dataHistory['created_at'] = $time;
            $dataHistory['created_by'] = $this->user->id;

            $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES);
            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '', 'messages' => 'Data (' . $newId . ')' . ($itemId > 0 ? 'Updated' : 'Created') . ')  Successfully']);
        }
        catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }

    }
}

