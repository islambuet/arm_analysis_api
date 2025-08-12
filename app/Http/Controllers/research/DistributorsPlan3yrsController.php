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


class DistributorsPlan3yrsController extends RootController
{
    public $api_url = 'research/distributors_plan_3yrs';
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
            $results=DB::table(TABLE_DISTRIBUTORS_PLAN_3YRS.' as td')
                ->select(DB::raw('COUNT(type_id) as total_type_entered'))
                ->addSelect('distributor_id')
                ->groupBy('distributor_id')
                ->where('td.fiscal_year','=',$current_fiscal_year)
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
            $response = [];
            $response['error'] ='';

            $query=DB::table(TABLE_DISTRIBUTORS_PLAN_3YRS.' as sd');
            $query->select('sd.*');
            $query->where('sd.distributor_id','=',$itemId);
            $query->where('sd.type_id','=',$item['type_id']);
            $query->where('sd.fiscal_year','=',$item['fiscal_year']);
            $result = $query->first();
            if($result){
                $competitor_variety_major=[];
                if(strlen($result->competitor_variety_major)>3){
                    $temp=explode(",,,",$result->competitor_variety_major);
                    foreach ($temp as $t){
                        if(str_contains($t,',')){
                            $competitor_variety_id=substr($t,0,strpos($t,','));
                            $competitor_variety_major[$competitor_variety_id]=[];
                            $temp2=explode("_",substr($t,strpos($t,',')+1));;
                            foreach ($temp2 as $t2){
                                if($t2>0){
                                    $competitor_variety_major[$competitor_variety_id][]=$t2;
                                }
                            }

                        }
                    }
                }
                $result->competitor_variety_major=$competitor_variety_major;
                foreach (['forecast','dealer_meeting','farmer_meeting','num_demo','num_result_sharing','num_field_day'] as $key){
                    if($result->$key){
                        $result->$key=json_decode($result->$key,false);
                    }

                }

                $response['item']=$result;
            }
            $response['sales']['year_1']=[];
            $response['sales']['year_2']=[];
            $year_2_date=($item['fiscal_year']-2).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';
            $year_1_date=($item['fiscal_year']-1).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';
            $year0_date=($item['fiscal_year']).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';


            $results=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd')
                ->select(DB::raw('SUM(quantity) as quantity'))
                ->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id')
                ->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id')
                ->addSelect('varieties.id as variety_id')
                ->addSelect(DB::raw("IF(sd.sales_at>='".$year_2_date."' && sd.sales_at< '".$year_1_date."', 'year_2', 'year_1') as year"))
                ->groupBy('varieties.id')
                ->groupBy('year')
                ->where('sd.distributor_id','=',$itemId)
                ->where('varieties.crop_type_id','=',$item['type_id'])
                ->where('sd.sales_at','>=',$year_2_date)
                ->where('sd.sales_at','<',$year0_date)
                ->get();
            foreach ($results as $result){
                $response['sales'][$result->year][$result->variety_id]=$result;
            }


            //return response()->json(['error'=>'','item'=>$result,'inputs'=>$item]);
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => $this->permissions]);
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
        $validation_rule['distributor_id'] = ['required','numeric'];
        $validation_rule['fiscal_year'] = ['required','numeric'];
        $validation_rule['type_id'] = ['required','numeric'];
        $validation_rule['month_start'] = ['numeric'];
        $validation_rule['month_end'] = ['numeric'];
        $validation_rule['pocket_market'] = ['nullable'];
        $validation_rule['competitor_variety_major'] = ['nullable'];
        $validation_rule['distributor_recommendation'] = ['nullable'];
        $validation_rule['manager_recommendation'] = ['nullable'];
        $validation_rule['manager_suggestion'] = ['nullable'];
        $validation_rule['forecast'] = ['nullable'];
        $validation_rule['dealer_meeting'] = ['nullable'];
        $validation_rule['farmer_meeting'] = ['nullable'];
        $validation_rule['num_demo'] = ['nullable'];
        $validation_rule['num_result_sharing'] = ['nullable'];
        $validation_rule['num_field_day'] = ['nullable'];

        $itemNew = $request->input('item');
        $itemOld = [];
        $itemId=0;
        if (!$itemNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Inputs was Not found']);
        }
        $this->validateInputKeys($itemNew, array_keys($validation_rule));
        $competitor_variety_major=',,,';
        if(isset($itemNew['competitor_variety_major'])){
            foreach ($itemNew['competitor_variety_major'] as $major){
                $competitor_variety_major.=($major.',,,');
            }
        }
        $itemNew['competitor_variety_major']=$competitor_variety_major;
        foreach (['forecast','dealer_meeting','farmer_meeting','num_demo','num_result_sharing','num_field_day'] as $key){
            $itemNew[$key]=json_encode($itemNew[$key]);
        }




        $query=DB::table(TABLE_DISTRIBUTORS_PLAN_3YRS.' as sd');
        $query->select('sd.*');
        $query->where('sd.distributor_id','=',$itemNew['distributor_id']);
        $query->where('sd.type_id','=',$itemNew['type_id']);
        $query->where('sd.fiscal_year','=',$itemNew['fiscal_year']);
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
            $dataHistory['table_name'] = TABLE_DISTRIBUTORS_PLAN_3YRS;
            $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
            $dataHistory['method'] = __FUNCTION__;
            $newId = $itemId;
            if ($itemId > 0) {
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_DISTRIBUTORS_PLAN_3YRS)->where('id', $itemId)->update($itemNew);
                $dataHistory['table_id'] = $itemId;
                $dataHistory['action'] = DB_ACTION_EDIT;
            } else {
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                $newId = DB::table(TABLE_DISTRIBUTORS_PLAN_3YRS)->insertGetId($itemNew);
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

