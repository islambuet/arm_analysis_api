<?php
namespace App\Http\Controllers\research;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class ResearchSalesTeamController extends RootController
{
    public $api_url = 'research/research_sales_team';
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
            $response['analysis_years'] = DB::table(TABLE_ANALYSIS_YEARS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['crops'] = DB::table(TABLE_CROPS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES.' as crop_types')
                ->select('crop_types.*')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name')
                ->orderBy('crops.ordering', 'ASC')
                ->orderBy('crops.id', 'ASC')
                ->orderBy('crop_types.ordering', 'ASC')
                ->orderBy('crop_types.id', 'ASC')
                ->where('crop_types.status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_districts'] = DB::table(TABLE_LOCATION_DISTRICTS)
                ->select('id', 'name')
                ->orderBy('name', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            return response()->json($response);

        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItems(Request $request,$analysisYearId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $results=DB::table(TABLE_ANALYSIS_DATA.' as ad')
                ->select(DB::raw('COUNT(type_id) as total_type_entered'))
                ->addSelect('district_id')
                ->groupBy('ad.district_id')
                ->where('ad.analysis_year_id','=',$analysisYearId)
                ->get();
            $response = [];
            $response['error'] ='';
            $response['items']=[];
            foreach ($results as $result){
                $response['items'][$result->district_id]=$result;
            }
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItem(Request $request,$analysisYearId, $itemId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $response = [];
            $response['error'] ='';
            $results= DB::table(TABLE_LOCATION_UPAZILAS)
                ->select('id', 'name')
                ->orderBy('name', 'ASC')
                ->where('district_id', $itemId)
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_upazilas'] =[];
            $response['location_upazilas_ordered'] =[];
            foreach ($results as $result){
                $response['location_upazilas'][$result->id]=$result;
                $response['location_upazilas_ordered'][]=$result;
            }
            $results=DB::table(TABLE_LOCATION_UNIONS.' as unions')
                ->select('unions.*')
                ->join(TABLE_LOCATION_UPAZILAS.' as upazilas', 'upazilas.id', '=', 'unions.upazila_id')
                ->where('upazilas.district_id', $itemId)
                ->orderBy('unions.name', 'ASC')
                ->where('unions.status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_unions']=[];
            foreach ($results as $result){
                $response['location_unions'][$result->upazila_id][]=$result;
            }
            $results=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->join(TABLE_COMPETITORS.' as competitors', 'competitors.id', '=', 'varieties.competitor_id')
                ->addSelect('competitors.name as competitor_name')
                ->where('varieties.whose','=','Competitor')
                ->where('varieties.status', SYSTEM_STATUS_ACTIVE)
                ->orderBy('competitors.name', 'ASC')
                ->orderBy('varieties.name', 'ASC')
                ->get();
            $response['varieties_competitor']=[];
            $response['varieties_competitor_ordered']=[];
            foreach ($results as $result){
                $response['varieties_competitor'][$result->crop_type_id][$result->id]=$result;
                $response['varieties_competitor_ordered'][$result->crop_type_id][]=$result;
            }

            $results=DB::table(TABLE_ANALYSIS_DATA.' as ad')
                ->select('ad.*')
                ->where('ad.analysis_year_id','=',$analysisYearId)
                ->where('ad.district_id','=',$itemId)
                ->get();
            $response['data'] = [];
            foreach ($results as $result){
                $upazila_market_size=[];
                if(strlen($result->upazila_market_size)>1){
                    $temp=explode(",",$result->upazila_market_size);
                    foreach ($temp as $t){
                        if(str_contains($t,'_')){
                            $upazila_market_size[substr($t,0,strpos($t,"_"))]=substr($t,strpos($t,"_")+1);
                        }
                    }
                }
                $result->upazila_market_size=$upazila_market_size;

                $competitor_market_size=[];
                if(strlen($result->competitor_market_size)>1){
                    $temp=explode(",",$result->competitor_market_size);
                    foreach ($temp as $t){
                        if(str_contains($t,'_')){
                            $competitor_market_size[substr($t,0,strpos($t,"_"))]=substr($t,strpos($t,"_")+1);
                        }
                    }
                }
                $result->competitor_market_size=$competitor_market_size;

                $competitor_sales_reason=[];
                if(strlen($result->competitor_sales_reason)>3){
                    $temp=explode(",,,",$result->competitor_sales_reason);
                    foreach ($temp as $t){
                        if(str_contains($t,'_')){
                            $competitor_sales_reason[substr($t,0,strpos($t,"_"))]=substr($t,strpos($t,"_")+1);
                        }
                    }
                }
                $result->competitor_sales_reason=$competitor_sales_reason;


                $response['data'][$result->type_id]=$result;
            }
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => $this->permissions]);
        }
    }
    public function saveItem(Request $request): JsonResponse
    {
        $district_id = $request->input('district_id', 0);
        $analysis_year_id = $request->input('analysis_year_id', 0);
        //permission checking start
        //return response()->json(['error' => 'ACCESS_DENIED', 'messages' => $request->all()]);
        if (($this->permissions->action_1 != 1) || ($this->permissions->action_2 != 1)) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have Edit access')]);
        }
        //permission checking passed
        $this->checkSaveToken();


        $itemsNew = $request->input('items');
        if (!$itemsNew) {
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Inputs was Not found']);
        }
        $results=DB::table(TABLE_ANALYSIS_DATA.' as ad')
            ->select('ad.*')
            ->where('ad.analysis_year_id','=',$analysis_year_id)
            ->where('ad.district_id','=',$district_id)
            ->get();
        $analysis_data=[];
        foreach ($results as $result){
            $analysis_data[$result->type_id]=$result;
        }
        $rows=[];
        foreach ($itemsNew as $type_id=>$info){
            $row=[];
            $row['analysis_year_id']=$analysis_year_id;
            $row['type_id']=$type_id;
            $row['district_id']=$district_id;
            $row['market_size_total']=0;
            $row['market_size_competitor']=0;
            $row['upazila_market_size']=',';
            if(isset($info['upazila_market_size'])){
                foreach ($info['upazila_market_size'] as $upzila_id=>$market_size){
                    $row['upazila_market_size'].=($upzila_id.'_'.($market_size>0?$market_size:'0').',');
                    $row['market_size_total']+=($market_size>0?(+$market_size):0);
                }
            }
            $row['union_ids_running']=',';
            if(isset($info['union_ids_running'])){
                $row['union_ids_running']=','.implode(',',$info['union_ids_running']).',';
            }
            $row['price_approximate']=$info['price_approximate'];
            $row['sowing_periods']=',';
            if(isset($info['sowing_periods'])){
                $row['sowing_periods']=','.implode(',',$info['sowing_periods']).',';
            }

            $row['competitor_market_size']=',';
            if(isset($info['competitor_market_size'])){
                foreach ($info['competitor_market_size'] as $variety_id=>$market_size){
                    $row['competitor_market_size'].=($variety_id.'_'.($market_size>0?$market_size:'0').',');
                    $row['market_size_competitor']+=($market_size>0?(+$market_size):0);
                }
            }
            $row['market_size_arm']=$row['market_size_total']-$row['market_size_competitor'];

            $row['competitor_sales_reason']=',,,';
            if(isset($info['competitor_sales_reason'])){
                foreach ($info['competitor_sales_reason'] as $variety_id=>$reason){
                    $row['competitor_sales_reason'].=($variety_id.'_'.trim($reason,',').',,,');
                }
            }

            //final list for add edit
            if(isset($analysis_data[$type_id])){
                $itemNew=$row;
                $itemOld = (array)$analysis_data[$type_id];
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
                        $dataHistory['table_name'] = TABLE_ANALYSIS_DATA;
                        $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
                        $dataHistory['method'] = __FUNCTION__;
                        $dataHistory['table_id'] = $row['id'];
                        $dataHistory['action'] = DB_ACTION_EDIT;
                        $dataHistory['data_old'] = json_encode($row['ItemOld']);
                        $dataHistory['data_new'] = json_encode($itemNew);
                        $dataHistory['created_at'] = $time;
                        $dataHistory['created_by'] = $this->user->id;

                        $itemNew['updated_sales_team_by'] = $this->user->id;
                        $itemNew['updated_sales_team_at'] = $time;
                        DB::table(TABLE_ANALYSIS_DATA)->where('id', $row['id'])->update($itemNew);

                        $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES);
                    }
                    else{
                        $itemNew['created_by'] = $this->user->id;
                        $itemNew['created_at'] = $time;
                        DB::table(TABLE_ANALYSIS_DATA)->insertGetId($itemNew);
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

