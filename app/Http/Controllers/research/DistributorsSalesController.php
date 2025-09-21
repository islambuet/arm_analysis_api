<?php
namespace App\Http\Controllers\research;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class DistributorsSalesController extends RootController
{
    public $api_url = 'research/distributors_sales';
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
                ->select('id', 'name', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['location_areas'] = DB::table(TABLE_LOCATION_AREAS)
                ->select('id', 'name','part_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['location_territories'] = DB::table(TABLE_LOCATION_TERRITORIES)
                ->select('id', 'name','area_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['distributors'] = DB::table(TABLE_DISTRIBUTORS)
                ->select('id', 'name','territory_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['crops'] = DB::table(TABLE_CROPS)
                ->select('id', 'name', 'status')
                ->orderBy('ordering', 'ASC')
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES)
                ->select('id', 'name','crop_id', 'status')
                ->orderBy('ordering', 'ASC')
                ->get();
            $response['varieties']=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->where('whose', 'ARM')
                ->orderBy('varieties.id', 'DESC')
                ->get();
            $response['pack_sizes'] = DB::table(TABLE_PACK_SIZES)
                ->select('id', 'name','variety_id', 'status')
                ->orderBy('ordering', 'ASC')
                ->get();
            return response()->json($response);

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $perPage = $request->input('perPage', 50);
            //$query=DB::table(TABLE_CROP_TYPES);
            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');
            $query->select('sd.*');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'sd.distributor_id');
            $query->addSelect('d.name as distributor_name');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->addSelect('territories.name as territory_name');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name');
            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            $query->addSelect('ps.name as pack_size_name');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id');
            $query->addSelect('varieties.name as variety_name');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name');

            $query->orderBy('sd.id', 'DESC');
            $query->where('sd.status', '!=', SYSTEM_STATUS_DELETE);
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
            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');
            $query->select('sd.*');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'sd.distributor_id');
            $query->addSelect('d.name as distributor_name');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->addSelect('territories.name as territory_name','territories.id as territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name','areas.id as area_id');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name','parts.id as part_id');
            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            $query->addSelect('ps.name as pack_size_name');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id');
            $query->addSelect('varieties.name as variety_name','varieties.id as variety_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name','crop_types.id as crop_type_id');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name','crops.id as crop_id');
            $query->where('sd.id','=',$itemId);
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
        $validation_rule['sales_at'] = ['required'];
        $validation_rule['invoice_no'] = ['required'];
        $validation_rule['distributor_id'] = ['required','numeric'];
        $validation_rule['pack_size_id'] = ['required','numeric'];
        $validation_rule['quantity'] = ['required','numeric'];
        $validation_rule['unit_price'] = ['required','numeric'];
        $validation_rule['amount'] = ['required','numeric'];


        $validation_rule['status'] = [Rule::in([SYSTEM_STATUS_ACTIVE, SYSTEM_STATUS_INACTIVE])];
        $itemNew = $request->input('item');
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));

        //edit change checking
        if ($itemId > 0) {
            $result = DB::table(TABLE_DISTRIBUTORS_SALES)->select(array_keys($validation_rule))->find($itemId);
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
            $dataHistory['table_name'] = TABLE_DISTRIBUTORS_SALES;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_DISTRIBUTORS_SALES)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_DISTRIBUTORS_SALES)->insertGetId($itemNew);
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
    public function saveItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_3 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access')]);
        }
        //permission checking passed
        $this->checkSaveToken();
        $itemsNew = $request->input('items');

        if (!$itemsNew) {
            return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => 'No data found']);
        }
        $id_start=$id_end=1;
        $result = DB::table(TABLE_DISTRIBUTORS_SALES)->select('id')->orderBy('id','DESC')->first();
        if($result){
            $id_start=$id_end=($result->id+1);
        }

        DB::beginTransaction();
        try {
            $time = Carbon::now();
            foreach ($itemsNew as $itemNew){
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $id_end = DB::table(TABLE_DISTRIBUTORS_SALES)->insertGetId($itemNew);
            }
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_DISTRIBUTORS_SALES;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $dataHistory['id_start'] = $id_start;
            $dataHistory['id_end'] = $id_end;
            $dataHistory['created_at'] = $time;
            $dataHistory['created_by'] = $this->user->id;
            $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES_CSV_UPLOAD);

            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '', 'messages' => 'Data Uploaded  Successfully']);
        }
        catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }
}

