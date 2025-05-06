<?php
namespace App\Http\Controllers\trial;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class TrialReportFormsController extends RootController
{
    public $api_url = 'trial/report-forms';
    public $permissions;
    public $cropInfo;

    public function __construct()
    {
        parent::__construct();
        $cropId=\Route::current()->parameter('cropId',0);
        $this->cropInfo = DB::table(TABLE_CROPS)->find($cropId);
        if($this->cropInfo){
            $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
        }
        else{
            $this->permissions = TaskHelper::getAllPermissions(false);
        }
    }

    public function initialize(Request $request, $cropId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $query=DB::table(TABLE_TRIAL_FORM_INPUTS);
            $results=DB::table(TABLE_TRIAL_FORM_INPUTS.' as form_inputs')
                ->select('form_inputs.*')
                ->where('form_inputs.status', SYSTEM_STATUS_ACTIVE)
                ->join(TABLE_TRIAL_FORMS.' as form', 'form.id', '=', 'form_inputs.trial_form_id')
                ->addSelect('form.name as trial_form_name','form.entry_count as entry_count')
                ->orderBy('form.ordering', 'ASC')
                ->orderBy('form_inputs.ordering', 'ASC')
                ->where('form.crop_id', $cropId)
                ->get();
            $trialFormInputs=[];
            foreach ($results as $result){
                $trialFormInputs[$result->trial_form_id][]=$result;
            }
            return response()->json(
                ['error'=>'','permissions'=>$this->permissions,
                    'hidden_columns' => TaskHelper::getHiddenColumns($this->api_url, $this->user,),
                    'cropInfo'=>$this->cropInfo,
                    'trialFormInputs'=>$trialFormInputs,
                ]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request, $cropId): JsonResponse
    {

        if ($this->permissions->action_0 == 1) {
            $perPage = $request->input('perPage', 50);
            $query=DB::table(TABLE_TRIAL_REPORT_FORMS);
            $query->where('crop_id', $cropId);//
            $query->orderBy('id', 'DESC');
            $query->where('status', '!=', SYSTEM_STATUS_DELETE);//
            if ($perPage == -1) {
                $perPage = $query->count();
                if($perPage<1){
                    $perPage=50;
                }
            }
            $results = $query->paginate($perPage)->toArray();
            return response()->json(['error'=>'','items'=>$results]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItem(Request $request,$cropId, $formId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $result = DB::table(TABLE_TRIAL_REPORT_FORMS)->find($formId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Form Id ' . $formId)]);
            }
            if ($result->crop_id !=$cropId) {
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => __('Invalid Crop Id ' . $cropId)]);
            }
            if($result->fields){
                $result->fields=array_values(json_decode($result->fields,true));
            }
            else{
                $result->fields=[];
            }

            return response()->json(['error'=>'','item'=>$result]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => $this->permissions]);
        }
    }
    public function saveItem(Request $request, $cropId): JsonResponse
    {
        $itemId = $request->input('id', 0);
        //permission checking start
        if ($itemId > 0) {
            if ($this->permissions->action_2 != 1) {
                return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
            }
        } else {
            if ($this->permissions->action_1 != 1) {
                return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
            }
        }
        //permission checking passed
        $this->checkSaveToken();
        //Input validation start
        $validation_rule = [];
        $validation_rule['name'] = ['required'];
        $validation_rule['crop_id'] = ['required'];
        $validation_rule['ordering']=['numeric'];
        $validation_rule['status'] = [Rule::in([SYSTEM_STATUS_ACTIVE, SYSTEM_STATUS_INACTIVE])];

        $itemNew = $request->input('item');
        $itemNew['crop_id']=$cropId;
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));

        //edit change checking
        if ($itemId > 0) {
            $result = DB::table(TABLE_TRIAL_REPORT_FORMS)->select(array_keys($validation_rule))->find($itemId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Form Id ' . $itemId)]);
            }
            $itemOld = (array)$result;
            if($itemNew['crop_id']!=$itemOld['crop_id']){
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => __('Invalid Crop Id ' . $cropId)]);
            }
            foreach ($itemOld as $key => $oldValue) {
                if (array_key_exists($key, $itemNew)) {
                    if ($oldValue == $itemNew[$key]) {
                        //unchanged so remove from both
                        unset($itemNew[$key]);
                        unset($itemOld[$key]);
                        unset($validation_rule[$key]);
                    }
                } else {
                    //will not happen if it comes form vue. removing rule and key for not change
                    unset($validation_rule[$key]);
                    unset($itemOld[$key]);
                }
            }
        }
        //if itemNew Empty
        if (!$itemNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Nothing was Changed']);
        }
        $this->validateInputValues($itemNew, $validation_rule);
//        Input validation ends
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_TRIAL_REPORT_FORMS;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_TRIAL_REPORT_FORMS)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_TRIAL_REPORT_FORMS)->insertGetId($itemNew);
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
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }
    public function saveIFields(Request $request, $cropId,$formId): JsonResponse
    {
        if ($this->permissions->action_2 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
        }
        //permission checking passed
        $this->checkSaveToken();


        $items = $request->input('items');
        if(!$items){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => __('Columns Missing')]);
        }
        $result = DB::table(TABLE_TRIAL_REPORT_FORMS)->where('crop_id',$cropId)->find($formId);
        if (!$result) {
            return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Form Id ' .$cropId.'-'. $formId)]);
        }
        $itemOld=$result->fields;
//        Input validation ends
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_TRIAL_REPORT_FORMS;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $dataHistory['table_id'] = $formId;
            $dataHistory['action'] = DB_ACTION_EDIT;

            $itemNew['updated_by'] = $this->user->id;
            $itemNew['updated_at'] = $time;
            $itemNew['fields']=json_encode($items);
            DB::table(TABLE_TRIAL_REPORT_FORMS)->where('id', $formId)->update($itemNew);
            $dataHistory['data_old'] = $itemOld;
            $dataHistory['data_new'] = json_encode($items);
            $dataHistory['created_at'] = $time;
            $dataHistory['created_by'] = $this->user->id;

            $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES);
            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '', 'messages' => 'Data (' . $formId . ')' . ($formId > 0 ? 'Updated' : 'Created') . ')  Successfully']);
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }
}

