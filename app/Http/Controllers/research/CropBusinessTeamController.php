<?php
namespace App\Http\Controllers\research;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class CropBusinessTeamController extends RootController
{
    public $api_url = 'research/crop_business_team';
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
            $crop_types2 = DB::table(TABLE_CROP_TYPES2)
                ->select('id', 'name','crop_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $competitor_varieties = DB::table(TABLE_VARIETIES2)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('competitor_id', '>',0)
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();

            $location_parts = DB::table(TABLE_LOCATION_PARTS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $location_areas = DB::table(TABLE_LOCATION_AREAS)
                ->select('id', 'name','part_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $location_territories = DB::table(TABLE_LOCATION_TERRITORIES)
                ->select('id', 'name','area_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $user_locations=['part_id'=>$this->user->part_id,'area_id'=>$this->user->area_id,'territory_id'=>$this->user->territory_id];

            return response()->json([
                'error'=>'','permissions'=>$this->permissions,
                'hidden_columns'=>TaskHelper::getHiddenColumns($this->api_url,$this->user),
                'user_locations'=>$user_locations,
                'crops'=>$crops,
                'crop_types2'=>$crop_types2,
                'competitor_varieties'=>$competitor_varieties,
                'location_parts'=>$location_parts,
                'location_areas'=>$location_areas,
                'location_territories'=>$location_territories,

            ]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $perPage = $request->input('perPage', 50);
            //$query=DB::table(TABLE_CROP_TYPES2);
            $query=DB::table(TABLE_RESEARCH_CROPS.' as rc');
            $query->select('rc.*');
            $query->join(TABLE_CROP_TYPES2.' as crop_types2', 'crop_types2.id', '=', 'rc.crop_type2_id');
            $query->addSelect('crop_types2.name as crop_type2_name');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types2.crop_id');
            $query->addSelect('crops.name as crop_name');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'rc.territory_id');
            $query->addSelect('territories.name as territory_name');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name');

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
            $query=DB::table(TABLE_RESEARCH_CROPS.' as rc');
            $query->select('rc.*');
            $query->join(TABLE_CROP_TYPES2.' as crop_types2', 'crop_types2.id', '=', 'rc.crop_type2_id');
            $query->addSelect('crop_types2.name as crop_type2_name');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types2.crop_id');
            $query->addSelect('crops.name as crop_name');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'rc.territory_id');
            $query->addSelect('territories.name as territory_name');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name');
            $query->where('rc.id','=',$itemId);
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

        $validation_rule['characteries'] = ['nullable'];

        $itemNew = $request->input('item');

        $itemOld = [];

        $this->validateInputKeys($itemNew, array_keys($validation_rule));

        //edit change checking
        if ($itemId > 0) {
            $result = DB::table(TABLE_RESEARCH_CROPS)->select(array_keys($validation_rule))->find($itemId);
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
            $dataHistory['table_name'] = TABLE_RESEARCH_CROPS;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['business_team_updated_by'] = $this->user->id;
                $itemNew['business_team_updated_at'] = $time;
                DB::table(TABLE_RESEARCH_CROPS)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_RESEARCH_CROPS)->insertGetId($itemNew);
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

