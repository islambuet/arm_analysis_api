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
                ->addSelect('parts.name as part_name','parts.id as part_id')
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
            $query->select(DB::raw('COUNT(scsp.type_id) as total_type_entered'));
            $query->addSelect(DB::raw('COUNT(scsp.competitor_varieties) as total_type_competitor'));
            $query->addSelect(DB::raw('COUNT(scsp.arm_varieties) as total_type_arm'));
            $query->addSelect('scsp.territory_id','scsp.fiscal_year','scsp.season_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'scsp.territory_id');
            $query->addSelect('territories.name as territory_name');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.name as area_name');
            $query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            $query->addSelect('parts.name as part_name');

            $query->join(TABLE_SEASONS.' as seasons', 'seasons.id', '=', 'scsp.season_id');
            $query->addSelect('seasons.name as season_name');


            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'scsp.type_id');
            $query->addSelect(DB::raw('COUNT(DISTINCT crop_types.crop_id) as total_crop_entered'));

            $query->groupBy('scsp.territory_id');
            $query->groupBy('territories.name');
            $query->groupBy('areas.name');
            $query->groupBy('parts.name');

            $query->groupBy('scsp.fiscal_year');
            $query->groupBy('scsp.season_id');
            $query->groupBy('seasons.name');
            if($this->user->part_id>0){
                $query->where('parts.id', $this->user->part_id);
                if($this->user->area_id>0){
                    $query->where('areas.id', $this->user->area_id);
                    if($this->user->territory_id>0){
                        $query->where('territories.id', $this->user->territory_id);
                    }
                }
            }

            $query->orderBy('scsp.fiscal_year', 'DESC');
            $query->orderBy('seasons.ordering', 'DESC');
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
            $fiscal_year=0;
            $season_id=0;
            $territory_id=0;

            if($itemId==0){
                $item = $request->input('item');
                $fiscal_year=$item['fiscal_year'];
                $season_id=$item['season_id'];
                $territory_id=$item['territory_id'];
            }
            else{
                $temp= explode('_', $itemId);

                if(isset($temp[0])){
                    $fiscal_year=$temp[0];
                }
                if(isset($temp[1])){
                    $season_id=$temp[1];
                }
                if(isset($temp[2])){
                    $territory_id=$temp[2];
                }
            }

            if(!($fiscal_year>0 && $season_id>0 && $territory_id>0)){
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Invalid Item. '.$itemId]);
            }
            $results= DB::table(TABLE_LOCATION_UPAZILAS)
                ->select('id', 'name')
                ->orderBy('name', 'ASC')
                ->where('territory_id', $territory_id)
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_upazilas'] =[];
            $response['location_upazilas_ordered'] =[];
            foreach ($results as $result){
                $response['location_upazilas'][$result->id]=$result;
                $response['location_upazilas'][$result->id]->unions=[];
                $response['location_upazilas_ordered'][]=$result;
            }
            $results=DB::table(TABLE_LOCATION_UNIONS.' as unions')
                ->select('unions.*')
                ->join(TABLE_LOCATION_UPAZILAS.' as upazilas', 'upazilas.id', '=', 'unions.upazila_id')
                ->where('upazilas.territory_id', $territory_id)
                ->orderBy('unions.name', 'ASC')
                ->where('unions.status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_unions']=[];
            foreach ($results as $result){
                $response['location_upazilas'][$result->upazila_id]->unions[]=$result;
                $response['location_unions'][$result->id]=$result;
            }

            $results=DB::table(TABLE_MARKET_SIZE_TERRITORY.' as mst')
                ->select('mst.*')
                ->where('mst.fiscal_year','<=',$fiscal_year)
                ->where('mst.territory_id','=',$territory_id)
                ->orderBy('mst.fiscal_year','DESC')
                ->get();
            $response['market_size_territory'] = [];
            foreach ($results as $result){
                if(!isset($response['market_size_territory'][$result->type_id]))
                {
                    $response['market_size_territory'][$result->type_id]=$result;
                }

            }

            $results=DB::table(TABLE_SIX_CROP_SALES_PLANNING.' as scsp')
                ->select('scsp.*')
                ->where('scsp.fiscal_year','=',$fiscal_year)
                ->where('scsp.season_id','=',$season_id)
                ->where('scsp.territory_id','=',$territory_id)
                ->get();
            $response['data'] = [];
            foreach ($results as $result){
                if($result->competitor_varieties)
                {
                    $result->competitor_varieties=json_decode($result->competitor_varieties);
                }
                if($result->arm_varieties)
                {
                    $result->arm_varieties=json_decode($result->arm_varieties);
                }
                $response['data'][$result->type_id]=$result;
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

        $item = $request->input('item');
        $fiscal_year=$item['fiscal_year'];
        $season_id=$item['season_id'];
        $territory_id=$item['territory_id'];
        if(!($fiscal_year>0 && $season_id>0 && $territory_id>0)){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Invalid Item. '.$request->input('id')]);
        }

        $this->checkSaveToken();

        $itemsNew = $request->input('items');
        if (!$itemsNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Inputs was Not found']);
        }

        $results=DB::table(TABLE_SIX_CROP_SALES_PLANNING.' as scsp')
            ->select('scsp.*')
            ->where('scsp.fiscal_year','=',$fiscal_year)
            ->where('scsp.season_id','=',$season_id)
            ->where('scsp.territory_id','=',$territory_id)
            ->get();
        $data_previous=[];
        foreach ($results as $result){
            $data_previous[$result->type_id]=$result;
        }



        $rows=[];
        foreach ($itemsNew as $type_id=>$info){
            $row=[];
            $row['fiscal_year']=$fiscal_year;
            $row['season_id']=$season_id;
            $row['territory_id']=$territory_id;
            $row['type_id']=$type_id;
            $row['pocket_market_unions']=',';
            if(isset($info['pocket_market_unions'])){
                $row['pocket_market_unions']=','.implode(',',$info['pocket_market_unions']).',';
            }

            $row['competitor_varieties']=null;
            if(isset($info['competitor_varieties'])){
                $row['competitor_varieties']=json_encode($info['competitor_varieties']);
            }
            //final list for add edit
            if(isset($data_previous[$type_id])){
                $itemNew=$row;
                $itemOld = (array)$data_previous[$type_id];
                $old_id=$itemOld['id'];
                foreach ($itemOld as $key => $oldValue) {
                    if (array_key_exists($key, $itemNew)) {
                        if ($oldValue == $itemNew[$key]) {
                            //unchanged so remove from both
                            unset($itemNew[$key]);
                            unset($itemOld[$key]);
                            unset($row[$key]);
                        }
                    } else {
                        //only for select query keys
                        unset($itemOld[$key]);
                    }
                }
                if ($itemNew) {
                    $rows[]=['id'=>$old_id,'ItemOld'=>$itemOld,'ItemNew'=>$row];
                }
            }
            else{
                $rows[]=['id'=>0,'ItemOld'=>[],'ItemNew'=>$row];
            }
        }
        if(sizeof($rows)>0){
            $time = Carbon::now();
            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $itemNew=$row['ItemNew'];
                    if($row['id']>0){
                        $dataHistory = [];
                        $dataHistory['table_name'] = TABLE_SIX_CROP_SALES_PLANNING;
                        $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
                        $dataHistory['method'] = __FUNCTION__;
                        $dataHistory['table_id'] = $row['id'];
                        $dataHistory['action'] = DB_ACTION_EDIT;
                        $dataHistory['data_old'] = json_encode($row['ItemOld']);
                        $dataHistory['data_new'] = json_encode($itemNew);
                        $dataHistory['created_at'] = $time;
                        $dataHistory['created_by'] = $this->user->id;

                        $itemNew['updated_by'] = $this->user->id;
                        $itemNew['updated_at'] = $time;
                        DB::table(TABLE_SIX_CROP_SALES_PLANNING)->where('id', $row['id'])->update($itemNew);

                        $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES);
                    }
                    else{
                        $itemNew['created_by'] = $this->user->id;
                        $itemNew['created_at'] = $time;
                        DB::table(TABLE_SIX_CROP_SALES_PLANNING)->insertGetId($itemNew);
                    }
                }
                $this->updateSaveToken();
                DB::commit();
                return response()->json(['error' => '', 'messages' => 'Data Updated Successfully']);
            }
            catch (\Exception $ex) {
                DB::rollback();
                return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
            }
        }
        else{
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Nothing was Changed']);
        }
    }

}

