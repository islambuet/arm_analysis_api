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


class SixCropSalesPlanningAMSController extends RootController
{
    public $api_url = 'analysis_data_entry/six_crop_sales_planning_ams';
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

            $response['varieties']=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id')
                ->addSelect('crop_types.name as crop_type_name')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name')
                ->orderBy('crops.ordering', 'ASC')
                ->orderBy('crops.id', 'ASC')
                ->orderBy('crop_types.ordering', 'ASC')
                ->orderBy('crop_types.id', 'ASC')
                ->orderBy('varieties.ordering', 'ASC')
                ->orderBy('varieties.id', 'ASC')
                ->get();
            $response['seasons'] = DB::table(TABLE_SEASONS)
                ->select('*')
                ->orderBy('ordering', 'ASC')
                ->orderBy('id', 'ASC')
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

            $query=DB::table(TABLE_SIX_CROP_SALES_PLANNING.' as scsp');
            $query->select('scsp.id','scsp.market_size_total','scsp.fiscal_year','scsp.season_id');
            $query->join(TABLE_SEASONS.' as seasons', 'seasons.id', '=', 'scsp.season_id');
            $query->addSelect('seasons.name as season_name');

            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'scsp.territory_id');
            $query->addSelect('territories.name as territory_name');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'scsp.type_id');
            $query->addSelect('crop_types.name as type_name');


            $query->orderBy('scsp.id', 'DESC');
            $query->where('scsp.status', '!=', SYSTEM_STATUS_DELETE);
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
                $query=DB::table(TABLE_SIX_CROP_SALES_PLANNING.' as scsp');
                $query->select('scsp.*');
                $query->join(TABLE_SEASONS.' as seasons', 'seasons.id', '=', 'scsp.season_id');
                $query->addSelect('seasons.name as season_name');

                $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'scsp.territory_id');
                $query->addSelect('territories.name as territory_name');
                $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
                $query->addSelect('areas.name as area_name','areas.id as area_id');
                $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
                $query->addSelect('parts.name as part_name','parts.id as part_id');
                $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'scsp.type_id');
                $query->addSelect('crop_types.name as type_name');
                $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
                $query->addSelect('crops.name as crop_name');

                $query->where('scsp.id','=',$itemId);

                $result = $query->first();
                if (!$result) {
                    return response()->json(['error' => 'ITEM_NOT_FOUND', 'messages' => __('Invalid Id ' . $itemId)]);
                }
                else{
                    if ($result->competitor_varieties) {
                        $result->competitor_varieties = json_decode($result->competitor_varieties);
                    }
                    $response['item'] = $result;
                }
            }
            else {
                $itemNew = $request->input('item');

                $query=DB::table(TABLE_SIX_CROP_SALES_PLANNING.' as scsp');
                $query->select('scsp.*');
                $query->where('fiscal_year', '=', $itemNew['fiscal_year']);
                $query->where('season_id', '=', $itemNew['season_id']);
                $query->where('territory_id', '=', $itemNew['territory_id']);
                $query->where('type_id', '=', $itemNew['type_id']);
                $query->where('status', '=', SYSTEM_STATUS_ACTIVE);
                $result = $query->first();
                if ($result) {
                    if ($result->competitor_varieties) {
                        $result->competitor_varieties = json_decode($result->competitor_varieties);
                    }
                    $response['item'] = $result;
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
        $validation_rule['fiscal_year'] = ['required','numeric'];
        $validation_rule['season_id'] = ['required','numeric'];
        $validation_rule['territory_id'] = ['required','numeric'];
        $validation_rule['type_id'] = ['required','numeric'];
        $validation_rule['market_size_total'] = ['nullable'];
        $validation_rule['pocket_market'] = ['nullable','numeric'];
        $validation_rule['competitor_varieties'] = ['nullable'];
        $validation_rule['dealer_meeting'] = ['nullable','numeric'];
        $validation_rule['farmer_meeting'] = ['nullable','numeric'];
        $validation_rule['num_demo'] = ['nullable','numeric'];

        $itemNew = $request->input('item');

        $itemOld = [];
        $itemId=0;
        if (!$itemNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Inputs was Not found']);
        }
        $this->validateInputKeys($itemNew, array_keys($validation_rule));
        if(isset($itemNew['competitor_varieties'])){
            $itemNew['competitor_varieties']=json_encode($itemNew['competitor_varieties']);
        }

        $query=DB::table(TABLE_SIX_CROP_SALES_PLANNING.' as scsp');
        $query->select('scsp.*');
        $query->where('scsp.fiscal_year','=',$itemNew['fiscal_year']);
        $query->where('scsp.season_id','=',$itemNew['season_id']);
        $query->where('scsp.territory_id','=',$itemNew['territory_id']);
        $query->where('scsp.type_id','=',$itemNew['type_id']);
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
            $dataHistory['table_name'] = TABLE_SIX_CROP_SALES_PLANNING;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_SIX_CROP_SALES_PLANNING)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_SIX_CROP_SALES_PLANNING)->insertGetId($itemNew);
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

