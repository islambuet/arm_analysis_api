<?php
namespace App\Http\Controllers\product_presentation_data_entry;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class ProductInformationController extends RootController
{
    public $api_url = 'product_presentation_data_entry/product_information';
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
            $response['location_territories'] = DB::table(TABLE_LOCATION_TERRITORIES.' as territories')
                ->select('territories.*')
                ->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id')
                ->addSelect('areas.name as area_name')
                ->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id')
                ->addSelect('parts.name as part_name','parts.id as part_id')
                ->orderBy('parts.name', 'ASC')
                ->orderBy('areas.name', 'ASC')
                ->orderBy('territories.name', 'ASC')
                ->get();
            $response['location_upazilas'] = DB::table(TABLE_LOCATION_UPAZILAS.' as upazilas')
                ->select('upazilas.id', 'upazilas.name','upazilas.district_id','upazilas.territory_id')
                ->join(TABLE_LOCATION_DISTRICTS.' as districts', 'districts.id', '=', 'upazilas.district_id')
                ->addSelect('districts.name as district_name')
                ->orderBy('upazilas.ordering', 'ASC')
                ->where('upazilas.status', SYSTEM_STATUS_ACTIVE)
                ->get();

            $response['crops'] = DB::table(TABLE_CROPS)
                ->select('id', 'name', 'status')
                ->orderBy('ordering', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES.' as crop_types')
                ->select('crop_types.*')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name')
                ->orderBy('crops.ordering', 'ASC')
                ->orderBy('crops.id', 'ASC')
                ->orderBy('crop_types.ordering', 'ASC')
                ->orderBy('crop_types.id', 'ASC')
                ->get();

            $results=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->join(TABLE_COMPETITORS.' as competitors', 'competitors.id', '=', 'varieties.competitor_id')
                ->addSelect('competitors.name as competitor_name')
                ->where('varieties.whose','=','Competitor')
                ->where('varieties.status', SYSTEM_STATUS_ACTIVE)
                ->orderBy('competitors.name', 'ASC')
                ->orderBy('varieties.name', 'ASC')
                ->get();
            $response['varieties_competitor_typewise']=[];
            $response['varieties_competitor_typewise_ordered']=[];
            foreach ($results as $result){
                $response['varieties_competitor_typewise'][$result->crop_type_id][$result->id]=$result;
                $response['varieties_competitor_typewise_ordered'][$result->crop_type_id][]=$result;
            }

            $results=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->join(TABLE_VARIETY_SUB_TYPES.' as vst', 'vst.id', '=', 'varieties.variety_sub_type_id')
                ->addSelect('vst.name as variety_sub_type_name')
                ->where('varieties.whose','=','ARM')
                ->where('varieties.status', SYSTEM_STATUS_ACTIVE)
                ->orderBy('varieties.name', 'ASC')
                ->get();
            $response['varieties_arm_typewise']=[];
            $response['varieties_arm_typewise_ordered']=[];
            foreach ($results as $result){
                $response['varieties_arm_typewise'][$result->crop_type_id][$result->id]=$result;
                $response['varieties_arm_typewise_ordered'][$result->crop_type_id][]=$result;
            }

            $response['user_locations']=['part_id'=>$this->user->part_id,'area_id'=>$this->user->area_id,'territory_id'=>$this->user->territory_id];
            return response()->json($response);

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $response=[];
            $response['error'] = '';
            $perPage=$request->input('perPage',2);
            $options = $request->input('options');
            $response['options'] = $options;

            $query=DB::table(TABLE_CROPS);
            $query->orderBy('id', 'DESC');
            $query->where('status', '!=', SYSTEM_STATUS_DELETE);//
            if ($perPage == -1) {
                $perPage = $query->count();
                if($perPage<1){
                    $perPage=50;
                }
            }
            $results = $query->paginate($perPage)->toArray();
            $response['items'] = $results;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItem(Request $request, $itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $result = DB::table(TABLE_CROPS)->find($itemId);
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
        //return response()->json(['error' => 'ACCESS_DENIED', 'messages' =>$request->input()]);
        //permission checking passed
        $this->checkSaveToken();
        //Input validation start
        $validation_rule = [];
        $validation_rule['upazila_id'] = ['required'];
        $validation_rule['variety_id_arm'] = ['required'];
        $validation_rule['variety_id_competitor'] = ['required'];
        $validation_rule['farmer_name'] = ['nullable'];
        $validation_rule['mobile_no'] = ['nullable'];
        $validation_rule['sowing_date_arm'] = ['nullable'];
        $validation_rule['sowing_date_competitor'] = ['nullable'];
        $validation_rule['pictures'] = ['nullable'];

        $itemNew = $request->input('item');
        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));
        if(isset($itemNew['pictures'])){
            $itemNew['pictures']=json_encode($itemNew['pictures']);
        }
        else{
            $itemNew['pictures']=null;
        }

        //edit change checking
        if ($itemId > 0) {
            $result = DB::table(TABLE_PRODUCTS_INFORMATION)->select(array_keys($validation_rule))->find($itemId);
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
        //try {
            $time = Carbon::now();
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_PRODUCTS_INFORMATION;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_PRODUCTS_INFORMATION)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_PRODUCTS_INFORMATION)->insertGetId($itemNew);
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
//        } catch (\Exception $ex) {
//            DB::rollback();
//            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
//        }
    }
}

