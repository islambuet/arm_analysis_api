<?php
namespace App\Http\Controllers\analysis_data_entry;

use App\Helpers\ConfigurationHelper;
use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class MarketSizeSetupTerritoryController extends RootController
{
    public $api_url = 'analysis_data_entry/market_size_setup_territory';
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
                ->addSelect('parts.name as part_name')
                ->orderBy('parts.name', 'ASC')
                ->orderBy('areas.name', 'ASC')
                ->orderBy('territories.name', 'ASC')
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


            $response['user_locations']=['part_id'=>$this->user->part_id,'area_id'=>$this->user->area_id,'territory_id'=>$this->user->territory_id];
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
            $query = DB::table(TABLE_MARKET_SIZE_TERRITORY . ' as mst');
            $query->select('mst.*');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'mst.territory_id');
            $query->addSelect('territories.name as territory_name','territories.id as territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name','areas.id as area_id');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name','parts.id as part_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'mst.type_id');
            $query->addSelect('crop_types.name as type_name');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name','crops.id as crop_id');

            if($this->user->part_id>0){
                $query->where('parts.id', $this->user->part_id);
                if($this->user->area_id>0){
                    $query->where('areas.id', $this->user->area_id);
                    if($this->user->territory_id>0){
                        $query->where('territories.id', $this->user->territory_id);
                    }
                }
            }

            $query->orderBy('mst.id', 'DESC');
            $query->where('mst.status', '!=', SYSTEM_STATUS_DELETE);
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
            $response = [];
            $response['error'] = '';
            $response['item'] = [];
            if($itemId>0){
                $query = DB::table(TABLE_MARKET_SIZE_TERRITORY . ' as mst');
                $query->select('mst.*');
                $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'mst.territory_id');
                $query->addSelect('territories.name as territory_name','territories.id as territory_id');
                $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
                $query->addSelect('areas.name as area_name','areas.id as area_id');
                $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
                $query->addSelect('parts.name as part_name','parts.id as part_id');
                $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'mst.type_id');
                $query->addSelect('crop_types.name as type_name');
                $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
                $query->addSelect('crops.name as crop_name','crops.id as crop_id');
                $query->where('mst.id','=',$itemId);
                $result = $query->first();
                if (!$result) {
                    return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
                }
                else{
                    $response['item'] = $result;
                }
            }
            else {
                $item = $request->input('item');
                $query = DB::table(TABLE_MARKET_SIZE_TERRITORY . ' as mst');
                $query->select('mst.*');
                $query->where('mst.territory_id','=',$item['territory_id']);
                $query->where('mst.type_id','=',$item['type_id']);
                $query->where('mst.fiscal_year','=',$item['fiscal_year']);

                $query->where('status', '=', SYSTEM_STATUS_ACTIVE);
                $result = $query->first();
                if ($result) {
                    $response['item']=$result;
                }
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
        $validation_rule['territory_id'] = ['required','numeric'];
        $validation_rule['fiscal_year'] = ['required','numeric'];
        $validation_rule['type_id'] = ['required','numeric'];
        $validation_rule['market_size_total'] = ['required','numeric'];

        $itemNew = $request->input('item');
        $itemOld = [];
        $itemId=0;
        if (!$itemNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Inputs was Not found']);
        }
        $this->validateInputKeys($itemNew, array_keys($validation_rule));


        $query = DB::table(TABLE_MARKET_SIZE_TERRITORY . ' as mst');
        $query->select('mst.*');
        $query->where('mst.territory_id','=',$itemNew['territory_id']);
        $query->where('mst.type_id','=',$itemNew['type_id']);
        $query->where('mst.fiscal_year','=',$itemNew['fiscal_year']);
        $result = $query->first();
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
            $dataHistory['table_name'] = TABLE_MARKET_SIZE_TERRITORY;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_MARKET_SIZE_TERRITORY)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_MARKET_SIZE_TERRITORY)->insertGetId($itemNew);
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
        if ($this->permissions->action_7 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access')]);
        }
        //permission checking passed
        $this->checkSaveToken();
        $itemsNew = $request->input('items');
        $file_name = $request->input('file_name');
        $fiscal_year = $request->input('fiscal_year');
        if (!$itemsNew) {
            return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => 'No data found']);
        }

        $id_start=$id_end=1;
        $result = DB::table(TABLE_MARKET_SIZE_TERRITORY)->select('id')->orderBy('id','DESC')->first();
        if($result){
            $id_start=$id_end=($result->id+1);
        }

        DB::beginTransaction();
        try {
            $time = Carbon::now();

            foreach ($itemsNew as $row){
                $itemNew=json_decode($row,true);
                $itemNew['fiscal_year'] = $fiscal_year;
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $id_end = DB::table(TABLE_MARKET_SIZE_TERRITORY)->insertGetId($itemNew);
            }
            $dataHistory = [];
            $dataHistory['table_name'] = TABLE_MARKET_SIZE_TERRITORY;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $dataHistory['file_name'] = $file_name;
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

