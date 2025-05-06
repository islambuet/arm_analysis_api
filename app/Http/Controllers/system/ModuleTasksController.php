<?php
namespace App\Http\Controllers\system;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class ModuleTasksController extends RootController
{
    public $api_url = 'system/module-tasks';
    public $permissions;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
    }

    public function initialize(): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            return response()->json(['error'=>'','permissions'=>$this->permissions,'hidden_columns'=>TaskHelper::getHiddenColumns($this->api_url,$this->user)]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            return response()->json(['error'=>'','items'=>TaskHelper::getTasksTree()]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItem(Request $request, $itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $result = DB::table(TABLE_TASKS)->find($itemId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
            }
            return response()->json(['error'=>'','item'=>$result]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => $this->permissions]);
        }
    }

    public function saveItem(Request $request): JsonResponse
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
        $validation_rule['type'] = [Rule::in(['MODULE','TASK'])];
        $validation_rule['parent'] = ['nullable','numeric'];
        $validation_rule['url'] = ['nullable'];
        $validation_rule['ordering'] = ['numeric'];
        $validation_rule['status'] = [Rule::in([SYSTEM_STATUS_ACTIVE, SYSTEM_STATUS_INACTIVE])];
        $itemNew = $request->input('item');
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));
        if(!$itemNew['parent']){
            $itemNew['parent']=0;
        }
        //edit change checking
        if ($itemId > 0) {
            $result = DB::table(TABLE_TASKS)->select(array_keys($validation_rule))->find($itemId);
            if (!$result) {
                return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
            }
            $itemOld = (array)$result;
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
        //Input validation ends
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_TASKS;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_TASKS)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_TASKS)->insertGetId($itemNew);
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
}

