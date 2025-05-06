<?php
namespace App\Http\Controllers\trial;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\CommonHelper;
use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class TrialDataController extends RootController
{
    public $api_url = 'trial/data';
    public $permissions;
    public $cropInfo;
    public $formInfo;

    public function __construct()
    {
        parent::__construct();
        $cropId=\Route::current()->parameter('cropId',0);
        $formId=\Route::current()->parameter('formId',0);
        $this->cropInfo = DB::table(TABLE_CROPS)->find($cropId);
        $this->formInfo = DB::table(TABLE_TRIAL_FORMS)->where('crop_id',$cropId)->find($formId);
        if($this->cropInfo && $this->formInfo){
            $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
        }
        else{
            $this->permissions = TaskHelper::getAllPermissions(false);
        }
    }

    public function initialize(Request $request,$cropId,$formId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $inputFields=DB::table(TABLE_TRIAL_FORM_INPUTS)
                ->where('trial_form_id', $formId)
                ->orderBy('ordering', 'ASC')
                ->orderBy('id', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();

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
            $results = DB::table(TABLE_CROP_FEATURES)
                ->select('id', 'name','crop_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->where('crop_id', $cropId)
                ->get();
            $crop_features=[];
            foreach ($results as $result){
                $crop_features[$result->id]=$result;
            }

            return response()->json(
                ['error'=>'','permissions'=>$this->permissions,
                    'cropInfo'=>$this->cropInfo,
                    'crop_features'=>$crop_features,
                    'formInfo'=>$this->formInfo,
                    'inputFields'=>$inputFields,
                    'trial_stations' => $trial_stations,
                    'seasons'=>$seasons
                ]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }

    public function getItems(Request $request, $cropId,$formId,$trialStationId, $year,$seasonId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $results=DB::table(TABLE_TRIAL_DATA)
                ->where('trial_station_id',$trialStationId)
                ->where('year',$year)
                ->where('season_id',$seasonId)
                ->where('trial_form_id', $formId)
                ->select(DB::raw('GROUP_CONCAT(entry_no) as entries'))
                ->addSelect('variety_id')
                ->groupBy('variety_id')
                ->get();
            $trial_data=[];
            foreach ($results as $result){
                $trial_data[$result->variety_id]=$result;
            }

            $query=DB::table(TABLE_TRIAL_VARIETIES.' as trial_varieties');
            $query->select('trial_varieties.variety_id','trial_varieties.rnd_ordering','trial_varieties.rnd_code','trial_varieties.replica','trial_varieties.delivered_date','trial_varieties.sowing_date');
            $query->where('trial_varieties.trial_station_id',$trialStationId);
            $query->where('trial_varieties.year',$year);
            $query->where('trial_varieties.season_id', $seasonId);
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'trial_varieties.variety_id');
            $query->addSelect('varieties.name as variety_name','varieties.crop_type_id','varieties.crop_feature_ids');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name','crop_types.crop_id');
            $query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            $query->addSelect('crops.name as crop_name');
            $query->where('trial_varieties.delivery_status', SYSTEM_STATUS_YES);
            $query->where('trial_varieties.sowing_status', SYSTEM_STATUS_YES);
            $query->where('crops.id', $cropId);
            $results = $query->get();
            $items=[];
            foreach ($results as $result){
                $result->rnd_code=CommonHelper::get_display_rnd_code($result->rnd_code,$this->permissions);
                if(isset($trial_data[$result->variety_id])){
                    $result->entries=explode(',',$trial_data[$result->variety_id]->entries);
                    $result->num_entry=count($result->entries);
                }
                else{
                    $result->entries=[];
                    $result->num_entry=0;
                }

                $items[]=$result;
            }
            return response()->json(['error'=>'','items'=> $items]);
        }
        else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItem(Request $request, $cropId,$formId,$trialStationId, $year,$seasonId,$varietyId,$entryNo): JsonResponse{
        $inputFields=DB::table(TABLE_TRIAL_FORM_INPUTS)
            ->where('trial_form_id', $formId)
            ->orderBy('ordering', 'ASC')
            ->orderBy('id', 'ASC')
            ->where('status', SYSTEM_STATUS_ACTIVE)
            ->get();
        $defaults=[];
        foreach ($inputFields as $field){
            if($field->type=='checkbox'){
                $defaults[$field->id]=[];
            }
            else{
                $defaults[$field->id]=$field->default;
            }
        }

        $trial_data=DB::table(TABLE_TRIAL_DATA)
            ->where('trial_station_id',$trialStationId)
            ->where('year',$year)
            ->where('season_id',$seasonId)
            ->where('trial_form_id', $formId)
            ->where('variety_id', $varietyId)
            ->where('entry_no', $entryNo)
            ->first();
        if($trial_data){
            $response['error']='';
            if($trial_data->data_1){
                $response['data_1']=json_decode($trial_data->data_1);
            }
            else{
                $response['data_1']=(object)[];
            }
            if($trial_data->data_2){
                $response['data_2']=json_decode($trial_data->data_2);
            }
            else{
                $response['data_2']=(object)[];
            }
            return response()->json($response);
        }
        else{
            return response()->json(['error' => '', 'data_1' =>$defaults,'data_2'=>$defaults]);
        }
    }
    public function saveItem(Request $request,$cropId,$formId): JsonResponse{
        if ($this->permissions->action_2 != 1) {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have add access')]);
        }

        $this->checkSaveToken();
        $validation_rule = [];

        $validation_rule['trial_station_id'] = ['required'];
        $validation_rule['year'] = ['required','numeric'];
        $validation_rule['season_id'] = ['required'];
        $validation_rule['variety_id'] = ['required'];
        $validation_rule['entry_no'] = ['required','numeric'];
        $validation_rule['data_1'] = ['nullable'];
        $validation_rule['data_2'] = ['nullable'];

        $itemNew = $request->input('item');
        $itemOld = [];
        $this->validateInputKeys($itemNew, array_keys($validation_rule));
        $this->validateInputValues($itemNew, $validation_rule);


        //variety info and sowed checking
        $trial_variety=DB::table(TABLE_TRIAL_VARIETIES)
            ->where('trial_station_id',$itemNew['trial_station_id'])
            ->where('year',$itemNew['year'])
            ->where('season_id',$itemNew['season_id'])
            ->where('variety_id', $itemNew['variety_id'])
            ->where('sowing_status', SYSTEM_STATUS_YES)
            ->first();
        if(!$trial_variety){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Invalid Variety Id: '.$itemNew['variety_id']]);
        }
        //variety info and sowed checking
        if(!isset($itemNew['data_1'])){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Normal Data missing']);
        }
        if($trial_variety->replica==SYSTEM_STATUS_YES){
            if(!isset($itemNew['data_2'])){
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Replica Data missing']);
            }
        }

        //entry no checking
        if($itemNew['entry_no']<1){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Invalid Entry No: '.$itemNew['entry_no']]);
        }
        if($this->formInfo->entry_count==-1){
            $result=DB::table(TABLE_TRIAL_DATA)
                ->where('trial_station_id',$itemNew['trial_station_id'])
                ->where('year',$itemNew['year'])
                ->where('season_id',$itemNew['season_id'])
                ->where('trial_form_id', $formId)
                ->where('variety_id', $itemNew['variety_id'])
                ->max('entry_no');
            if($itemNew['entry_no']>($result+1)){
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Invalid Entry No: '.$itemNew['entry_no']]);
            }
        }
        else{
            if($itemNew['entry_no']>$this->formInfo->entry_count){
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Invalid Entry No: '.$itemNew['entry_no']]);
            }
        }
        //entry no checking end
        //mandatory checking
        $inputFields=DB::table(TABLE_TRIAL_FORM_INPUTS)
            ->where('trial_form_id', $formId)
            ->orderBy('ordering', 'ASC')
            ->orderBy('id', 'ASC')
            ->where('status', SYSTEM_STATUS_ACTIVE)
            ->get();
        foreach ($inputFields as $field){
            if($field->mandatory==SYSTEM_STATUS_YES){
                if($field->type !='checkbox'){
                    if((!isset($itemNew['data_1'][$field->id]))||(!($itemNew['data_1'][$field->id]))){
                        return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>$field->name.' for Normal Required']);
                    }
                    if($trial_variety->replica==SYSTEM_STATUS_YES){
                        if((!isset($itemNew['data_2'][$field->id]))||(!($itemNew['data_2'][$field->id]))){
                            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>$field->name.' for Replica Required']);
                        }
                    }
                }
            }
        }
        //mandatory checking end
        $result=DB::table(TABLE_TRIAL_DATA)
            ->where('trial_station_id',$itemNew['trial_station_id'])
            ->where('year',$itemNew['year'])
            ->where('season_id',$itemNew['season_id'])
            ->where('trial_form_id', $formId)
            ->where('variety_id', $itemNew['variety_id'])
            ->where('entry_no', $itemNew['entry_no'])
            ->select(array_keys($validation_rule))
            ->addSelect('id')
            ->first();
        if($result){
            $itemOld=(array)$result;
        }
        if(isset($itemNew['data_1'])){
            $itemNew['data_1']=json_encode($itemNew['data_1']);
        }
        if(isset($itemNew['data_2'])){
            $itemNew['data_2']=json_encode($itemNew['data_2']);
        }
        DB::beginTransaction();
        try {
            $time = Carbon::now();
            if($itemOld){
                $itemNew['updated_by'] = $this->user->id;
                $itemNew['updated_at'] = $time;
                DB::table(TABLE_TRIAL_DATA)->where('id', $itemOld['id'])->update($itemNew);
                $dataHistory = [];
                $dataHistory['table_name'] = TABLE_CROP_TYPES;
                $dataHistory['controller'] = (new \ReflectionClass(__CLASS__))->getShortName();
                $dataHistory['method'] = __FUNCTION__;
                $dataHistory['table_id'] = $itemOld['id'];
                $dataHistory['action'] = DB_ACTION_EDIT;
                unset($itemNew['updated_by'],$itemNew['updated_at'],$itemNew['trial_station_id'],$itemNew['year'],$itemNew['season_id'],$itemNew['trial_form_id'],$itemNew['variety_id'],$itemNew['entry_no']);
                $dataHistory['data_old'] = json_encode(['data_1'=>$itemOld['data_1'],'data_2'=>$itemOld['data_2']]);
                $dataHistory['data_new'] = json_encode($itemNew);
                $dataHistory['created_at'] = $time;
                $dataHistory['created_by'] = $this->user->id;
                $this->dBSaveHistory($dataHistory, TABLE_SYSTEM_HISTORIES);
            }
            else{
                $itemNew['trial_form_id'] = $formId;
                $itemNew['created_by'] = $this->user->id;
                $itemNew['created_at'] = $time;
                DB::table(TABLE_TRIAL_DATA)->insertGetId($itemNew);
            }
            $this->updateSaveToken();
            DB::commit();

            return response()->json(['error' => '', 'messages' => 'Data Saved Successfully']);
        }
        catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => 'DB_SAVE_FAILED', 'messages' => __('Failed to save.')]);
        }
    }
}

