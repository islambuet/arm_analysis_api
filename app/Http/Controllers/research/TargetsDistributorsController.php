<?php
namespace App\Http\Controllers\research;

use App\Helpers\ConfigurationHelper;
use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class TargetsDistributorsController extends RootController
{
    public $api_url = 'research/sales_distributors';
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
            $response['location_parts'] = DB::table(TABLE_LOCATION_PARTS)
                ->select('id', 'name')
                ->orderBy('name', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_areas'] = DB::table(TABLE_LOCATION_AREAS)
                ->select('id', 'name','part_id')
                ->orderBy('name', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_territories'] = DB::table(TABLE_LOCATION_TERRITORIES)
                ->select('id', 'name','area_id')
                ->orderBy('name', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $query=DB::table(TABLE_DISTRIBUTORS.' as d');
            $query->select('d.*');

            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->addSelect('territories.name as territory_name','territories.area_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name','areas.part_id');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name');
            $query->orderBy('d.name', 'DESC');
            $query->where('d.status', SYSTEM_STATUS_ACTIVE);
            if($this->user->territory_id>0){
                $query->where('territories.id', $this->user->territory_id);
            }
            else if($this->user->area_id>0){
                $query->where('areas.id', $this->user->area_id);
            }
            else if($this->user->part_id>0){
                $query->where('parts.id', $this->user->part_id);
            }

            $response['distributors']=$query->get();
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
            $response['varieties'] = DB::table(TABLE_VARIETIES)
                ->select('*')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['user_locations']=['part_id'=>$this->user->part_id,'area_id'=>$this->user->area_id,'territory_id'=>$this->user->territory_id];
            return response()->json($response);

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $current_fiscal_year=date("Y");
            if(date('m')<ConfigurationHelper::getCurrentFiscalYearStartingMonth()){
                $current_fiscal_year--;
            }
            $results=DB::table(TABLE_TARGETS_DISTRIBUTORS.' as td')
                ->select(DB::raw('COUNT(type_id) as total_type_entered'))
                ->addSelect('distributor_id')
                ->groupBy('distributor_id')
                ->where('td.year','=',$current_fiscal_year)
                ->get();
            $response = [];
            $response['error'] ='';
            $response['items']=[];
            foreach ($results as $result){
                $response['items'][$result->distributor_id]=$result;
            }
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItem(Request $request, $itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $item = $request->input('item');
            $query=DB::table(TABLE_TARGETS_DISTRIBUTORS.' as sd');
            $query->select('sd.*');
            $query->where('sd.distributor_id','=',$itemId);
            $query->where('sd.type_id','=',$item['type_id']);
            $query->where('sd.year','=',$item['fiscal_year']);
            $result = $query->first();
            return response()->json(['error'=>'','item'=>$result,'inputs'=>$item]);
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
        $validation_rule['year'] = ['required'];
        $validation_rule['distributor_id'] = ['required','numeric'];
        $validation_rule['variety_id'] = ['required','numeric'];
        $validation_rule['quantity'] = ['required','numeric'];
        $validation_rule['unit_price'] = ['required','numeric'];
        $validation_rule['amount'] = ['required','numeric'];


        $validation_rule['status'] = [Rule::in([SYSTEM_STATUS_ACTIVE, SYSTEM_STATUS_INACTIVE])];
        $itemNew = $request->input('item');
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));

        //edit change checking
        if ($itemId > 0) {
            $result = DB::table(TABLE_TARGETS_DISTRIBUTORS)->select(array_keys($validation_rule))->find($itemId);
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
            $dataHistory['table_name'] = TABLE_TARGETS_DISTRIBUTORS;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_TARGETS_DISTRIBUTORS)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_TARGETS_DISTRIBUTORS)->insertGetId($itemNew);
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

