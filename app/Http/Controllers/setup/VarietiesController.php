<?php
namespace App\Http\Controllers\setup;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class VarietiesController extends RootController
{
    public $api_url = 'setup/varieties';
    public $permissions;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
    }

    public function initialize(): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $crops = DB::table(TABLE_CROPS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $crop_types = DB::table(TABLE_CROP_TYPES)
                ->select('id', 'name','crop_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $principals = DB::table(TABLE_PRINCIPALS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $competitors = DB::table(TABLE_COMPETITORS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            return response()->json([
                'error'=>'','permissions'=>$this->permissions,
                'hidden_columns'=>TaskHelper::getHiddenColumns($this->api_url,$this->user),
                'crops'=>$crops,
                'crop_types'=>$crop_types,
                'principals'=>$principals,
                'competitors'=>$competitors,

            ]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $perPage = $request->input('perPage', 50);
            //$query=DB::table(TABLE_CROP_TYPES);
            $query=DB::table(TABLE_VARIETIES.' as varieties');
            $query->select('varieties.*');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name');
            $query->leftJoin(TABLE_PRINCIPALS.' as principals', 'principals.id', '=', 'varieties.principal_id');
            $query->addSelect('principals.name as principal_name');
            $query->leftJoin(TABLE_COMPETITORS.' as competitors', 'competitors.id', '=', 'varieties.competitor_id');
            $query->addSelect('competitors.name as competitor_name');
            $query->orderBy('varieties.id', 'DESC');
            $query->where('varieties.status', '!=', SYSTEM_STATUS_DELETE);//
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

    public function getItem(Request $request, $itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $query=DB::table(TABLE_VARIETIES.' as varieties');
            $query->select('varieties.*');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name','crops.id as crop_id');
            $query->where('varieties.id','=',$itemId);
            $query->leftJoin(TABLE_PRINCIPALS.' as principals', 'principals.id', '=', 'varieties.principal_id');
            $query->addSelect('principals.name as principal_name');
            $query->leftJoin(TABLE_COMPETITORS.' as competitors', 'competitors.id', '=', 'varieties.competitor_id');
            $query->addSelect('competitors.name as competitor_name');
            $result = $query->first();
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
                return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have Edit access')]);
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
        $validation_rule['crop_type_id'] = ['required','numeric'];
        $validation_rule['whose'] = [Rule::in(['ARM', 'Principal','Competitor'])];
        $validation_rule['principal_id']=['numeric','nullable'];
        $validation_rule['competitor_id']=['numeric','nullable'];
        $validation_rule['ordering']=['numeric'];
        $validation_rule['status'] = [Rule::in([SYSTEM_STATUS_ACTIVE, SYSTEM_STATUS_INACTIVE])];
        $validation_rule['retrial'] = [Rule::in([SYSTEM_STATUS_YES, SYSTEM_STATUS_NO])];
        $itemNew = $request->input('item');
        if($itemNew['whose']=='Principal'){
            if(!($itemNew['principal_id']>0)){
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => __('Principal Required')]);
            }
        }
        else if($itemNew['whose']=='Competitor'){
            if(!($itemNew['competitor_id']>0)){
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => __('Competitor Required')]);
            }
        }
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));

        //edit change checking
        if ($itemId > 0) {
            $result = DB::table(TABLE_VARIETIES)->select(array_keys($validation_rule))->find($itemId);
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
//                    else if($key=='crop_id'){
//                        return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Cannot Change Crop']);
//                    }
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
        //TODO validate crop_id
        //Input validation ends
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_VARIETIES;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_VARIETIES)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_VARIETIES)->insertGetId($itemNew);
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

