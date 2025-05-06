<?php
namespace App\Http\Controllers\variety_configuration;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class VarietyDeliveryController extends RootController
{
    public $api_url = 'variety-configuration/delivery';
    public $permissions;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
    }

    public function initialize(): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $trial_stations=DB::table(TABLE_TRIAL_STATIONS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $seasons=DB::table(TABLE_SEASONS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            return response()->json(
                ['error' => '', 'permissions' => $this->permissions,
                    'hidden_columns' => TaskHelper::getHiddenColumns($this->api_url, $this->user,),
                    'trial_stations' => $trial_stations,
                    'seasons'=>$seasons
                ]);
        }
        else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItems(Request $request, $trialStationId, $year,$seasonId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $query=DB::table(TABLE_TRIAL_VARIETIES.' as trial_varieties');
            $query->select('trial_varieties.variety_id','trial_varieties.rnd_ordering','trial_varieties.rnd_code','trial_varieties.replica','trial_varieties.delivered_date');
            $query->where('trial_varieties.trial_station_id',$trialStationId);
            $query->where('trial_varieties.year',$year);
            $query->where('trial_varieties.season_id', $seasonId);
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'trial_varieties.variety_id');
            $query->addSelect('varieties.name as variety_name','varieties.crop_type_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name','crop_types.crop_id');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name');
            $query->where('trial_varieties.delivery_status', SYSTEM_STATUS_YES);
            $results = $query->get();
            $itemsDelivered=[];
            $deliveredVarietyIds=[];
            foreach ($results as $result){
                $itemsDelivered[$result->crop_id]['crop_id']=$result->crop_id;
                $itemsDelivered[$result->crop_id]['crop_name']=$result->crop_name;
                $itemsDelivered[$result->crop_id]['varieties'][]=$result;
                $deliveredVarietyIds[]=$result->variety_id;
            }


            $query=DB::table(TABLE_SELECTED_VARIETIES.' as selected_varieties');
            $query->select('selected_varieties.variety_id','selected_varieties.rnd_ordering','selected_varieties.rnd_code');
            $query->where('selected_varieties.year',$year);
            $query->where('selected_varieties.season_ids', 'like', '%,'.$seasonId.',%');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'selected_varieties.variety_id');
            $query->addSelect('varieties.name as variety_name','varieties.crop_type_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name','crop_types.crop_id');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name','crops.replica');
            if(count($deliveredVarietyIds)>0){
                $query->whereNotIn('selected_varieties.variety_id', $deliveredVarietyIds);
            }
            $results = $query->get();
            $itemsPending=[];
            foreach ($results as $result){
                $itemsPending[$result->crop_id]['crop_id']=$result->crop_id;
                $itemsPending[$result->crop_id]['crop_name']=$result->crop_name;
                $itemsPending[$result->crop_id]['varieties'][]=$result;
            }
            return response()->json(['error'=>'','itemsPending'=> $itemsPending,'itemsDelivered'=>$itemsDelivered]);
        }
        else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function savePending(Request $request, $trialStationId, $year,$seasonId): JsonResponse
    {
        if ($this->permissions->action_2 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
        }
        //permission checking passed
        $this->checkSaveToken();
        $delivered_date=$request->input('delivered_date');

        if(!$delivered_date){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Delivery Date required']);
        }
        $varieties=$request->input('varieties');
        $itemsNew=[];
        if(!$varieties){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Nothing was selected']);
        }
        foreach ($varieties as $variety){
            if(isset($variety['variety_id'])){
                $itemsNew[$variety['variety_id']]=$variety;
            }
        }
        if(count($itemsNew)==0){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Nothing was selected']);
        }
        $itemsOld=[];
        $results=DB::table(TABLE_TRIAL_VARIETIES)
            ->where('year',$year)
            ->where('trial_station_id',$trialStationId)
            ->where('season_id',$seasonId)
            ->get();
        foreach ($results as $result){
            $itemsOld[$result->variety_id]=$result;
        }
        //Input validation ends
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            foreach ($itemsNew as $variety_id=>$itemNew){
                if(isset($itemsOld[$variety_id])){
                    $itemNew['delivery_status']=SYSTEM_STATUS_YES;
                    $itemNew['delivered_date']=$delivered_date;
                    $itemNew['delivered_by'] = $this->user->id;
                    $itemNew['delivered_at'] = $time;
                    $itemNew['sowing_status']=SYSTEM_STATUS_NO;
                    DB::table(TABLE_TRIAL_VARIETIES)->where('id', $itemsOld[$variety_id]->id)->update($itemNew);
                    //history
                    $dataHistory = [];
                    $dataHistory['table_name'] = TABLE_CROPS;
                    $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
                    $dataHistory['method'] = __FUNCTION__;
                    $dataHistory['table_id'] = $itemsOld[$variety_id]->id;
                    $dataHistory['action'] = DB_ACTION_EDIT;
                    $dataHistory['data_old'] = json_encode($itemsOld[$variety_id]);
                    $dataHistory['data_new'] = json_encode($itemNew);
                    $dataHistory['created_at'] = $time;
                    $dataHistory['created_by'] = $this->user->id;

                    $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES);
                }
                else{
                    $itemNew['trial_station_id']=$trialStationId;
                    $itemNew['year']=$year;
                    $itemNew['season_id']=$seasonId;
                    $itemNew['delivery_status']=SYSTEM_STATUS_YES;
                    $itemNew['delivered_date']=$delivered_date;
                    $itemNew['delivered_by'] = $this->user->id;
                    $itemNew['delivered_at'] = $time;
                    $itemNew['sowing_status']=SYSTEM_STATUS_NO;
                    DB::table(TABLE_TRIAL_VARIETIES)->insertGetId($itemNew);
                }
            }
            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '', 'messages' => 'Delivered  Successfully']);
        }
        catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }
    public function saveDelivered(Request $request, $trialStationId, $year,$seasonId): JsonResponse
    {
        if ($this->permissions->action_2 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
        }
        //permission checking passed
        $this->checkSaveToken();
        $variety_ids=$request->input('variety_ids');

        if(!$variety_ids){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Nothing was selected']);
        }

        $itemsOld=[];
        $results=DB::table(TABLE_TRIAL_VARIETIES)
            ->where('year',$year)
            ->where('trial_station_id',$trialStationId)
            ->where('season_id',$seasonId)
            ->get();
        foreach ($results as $result){
            $itemsOld[$result->variety_id]=$result;
        }

        //Input validation ends
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            foreach ($variety_ids as $variety_id){
                if(isset($itemsOld[$variety_id])){
                    $itemNew=[];
                    $itemNew['delivery_status']=SYSTEM_STATUS_NO;
                    $itemNew['delivered_by'] = $this->user->id;
                    $itemNew['delivered_at'] = $time;
                    $itemNew['sowing_status']=SYSTEM_STATUS_NO;
                    DB::table(TABLE_TRIAL_VARIETIES)->where('id', $itemsOld[$variety_id]->id)->update($itemNew);
                    //history
                    $dataHistory = [];
                    $dataHistory['table_name'] = TABLE_CROPS;
                    $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
                    $dataHistory['method'] = __FUNCTION__;
                    $dataHistory['table_id'] = $itemsOld[$variety_id]->id;
                    $dataHistory['action'] = DB_ACTION_EDIT;
                    $dataHistory['data_old'] = json_encode($itemsOld[$variety_id]);
                    $dataHistory['data_new'] = json_encode($itemNew);
                    $dataHistory['created_at'] = $time;
                    $dataHistory['created_by'] = $this->user->id;

                    $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES);
                }
            }
            $this->updateSaveToken();
            DB::commit();
            return response()->json(['error' => '', 'messages' => 'Canceled Delivery Successfully']);
        }
        catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }
}

