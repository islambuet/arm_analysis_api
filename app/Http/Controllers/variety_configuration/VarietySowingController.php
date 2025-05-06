<?php
namespace App\Http\Controllers\variety_configuration;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class VarietySowingController extends RootController
{
    public $api_url = 'variety-configuration/sowing';
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
            $query->where('trial_varieties.sowing_status', SYSTEM_STATUS_NO);
            $results = $query->get();
            $itemsPending=[];
            foreach ($results as $result){
                $itemsPending[$result->crop_id]['crop_id']=$result->crop_id;
                $itemsPending[$result->crop_id]['crop_name']=$result->crop_name;
                $itemsPending[$result->crop_id]['varieties'][]=$result;
            }

            $query=DB::table(TABLE_TRIAL_VARIETIES.' as trial_varieties');
            $query->select('trial_varieties.variety_id','trial_varieties.rnd_ordering','trial_varieties.rnd_code','trial_varieties.replica','trial_varieties.delivered_date','trial_varieties.sowing_date');
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
            $query->where('trial_varieties.sowing_status', SYSTEM_STATUS_YES);
            $results = $query->get();
            $itemsSowed=[];
            foreach ($results as $result){
                $itemsSowed[$result->crop_id]['crop_id']=$result->crop_id;
                $itemsSowed[$result->crop_id]['crop_name']=$result->crop_name;
                $itemsSowed[$result->crop_id]['varieties'][]=$result;
            }
            return response()->json(['error'=>'','itemsPending'=> $itemsPending,'itemsSowed'=>$itemsSowed]);
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
        $sowing_date=$request->input('sowing_date');

        if(!$sowing_date){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Sowing Date required']);
        }

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
                    $itemNew['sowing_status']=SYSTEM_STATUS_YES;
                    $itemNew['sowing_date']=$sowing_date;
                    $itemNew['sowing_by'] = $this->user->id;
                    $itemNew['sowing_at'] = $time;

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
            return response()->json(['error' => '', 'messages' => 'Sowed Successfully']);
        }
        catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }
    public function saveSowed(Request $request, $trialStationId, $year,$seasonId): JsonResponse
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
                    $itemNew['sowing_status']=SYSTEM_STATUS_NO;
                    $itemNew['sowing_by'] = $this->user->id;
                    $itemNew['sowing_at'] = $time;

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

