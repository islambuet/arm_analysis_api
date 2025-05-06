<?php
namespace App\Http\Controllers\trial;

use App\Helpers\CommonHelper;
use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;
use function PHPUnit\Framework\isNull;


class TrialReportDataController extends RootController
{
    public $api_url = 'trial/data';
    public $permissions;
    public $cropInfo;
    public $reportInfo;

    public function __construct()
    {
        parent::__construct();
        $cropId=\Route::current()->parameter('cropId',0);
        $reportId=\Route::current()->parameter('reportId',0);
        $this->cropInfo = DB::table(TABLE_CROPS)->find($cropId);
        $this->reportInfo = DB::table(TABLE_TRIAL_REPORT_FORMS)->where('crop_id',$cropId)->find($reportId);
        if($this->cropInfo && $this->reportInfo){
            $this->permissions = TaskHelper::getPermissions($this->api_url, $this->user);
        }
        else{
            $this->permissions = TaskHelper::getAllPermissions(false);
        }
    }

    public function initialize(Request $request,$cropId,$reportId): JsonResponse
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
                    'reportInfo'=>$this->reportInfo,
                    'trial_stations' => $trial_stations,
                    'seasons'=>$seasons
                ]);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    //Variety List
    public function getVarieties(Request $request, $cropId,$reportId,$trialStationId, $year,$seasonId): JsonResponse
    {
        if ($this->permissions->action_0 == 1) {
            $query=DB::table(TABLE_TRIAL_VARIETIES.' as trial_varieties');
            $query->select('trial_varieties.variety_id','trial_varieties.rnd_ordering','trial_varieties.rnd_code','trial_varieties.replica','trial_varieties.delivered_date','trial_varieties.sowing_date');
            $query->where('trial_varieties.trial_station_id',$trialStationId);
            $query->where('trial_varieties.year',$year);
            $query->where('trial_varieties.season_id', $seasonId);
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'trial_varieties.variety_id');
            $query->addSelect('varieties.name as variety_name','varieties.crop_type_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.name as crop_type_name','crop_types.crop_id');
            $query->where('trial_varieties.delivery_status', SYSTEM_STATUS_YES);
            $query->where('trial_varieties.sowing_status', SYSTEM_STATUS_YES);
            $query->where('crop_types.crop_id', $cropId);
            $results = $query->get();
            $varieties=array();
            foreach ($results as $result){
                $result->rnd_code=CommonHelper::get_display_rnd_code($result->rnd_code,$this->permissions);
                $varieties[]=$result;
            }
            return response()->json(['error'=>'','varieties'=> $varieties]);
        }
        else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    public function getItems(Request $request, $cropId,$reportId,$trialStationId, $year,$seasonId): JsonResponse
    {
        $varietyIds = $request->input('variety_ids');
        if(!is_array($varietyIds)){
            return response()->json(['error' => 'VALIDATION_FAILED', 'messages' =>'Please select Variety']);
        }
        if ($this->permissions->action_0 == 1) {
            //getting variety info
            $query=DB::table(TABLE_TRIAL_VARIETIES.' as trial_varieties');
            $query->select('trial_varieties.variety_id','trial_varieties.rnd_ordering','trial_varieties.rnd_code','trial_varieties.replica','trial_varieties.delivered_date','trial_varieties.sowing_date');
            $query->where('trial_varieties.trial_station_id',$trialStationId);
            $query->where('trial_varieties.year',$year);
            $query->where('trial_varieties.season_id', $seasonId);
            $query->whereIn('trial_varieties.variety_id',$varietyIds);
            $varieties = $query->get();

            //$reportInfo;
            //$inputs
            $query=DB::table(TABLE_TRIAL_FORM_INPUTS.' as inputs');
            $query->select('inputs.id','inputs.type');
            $query->join(TABLE_TRIAL_FORMS.' as forms', 'forms.id', '=', 'inputs.trial_form_id');
            $query->addSelect('forms.entry_count');
            $query->where('forms.crop_id', $cropId);
            $results=$query->get();
            $inputs=[];
            foreach ($results as $result){
                $result->max_entry_no=1;
                $inputs[$result->id]=$result;
            }
            //trial data
            $query=DB::table(TABLE_TRIAL_DATA.' as data');
            $query->select('data.variety_id','data.entry_no','data.data_1','data.data_2');
            $query->where('data.trial_station_id',$trialStationId);
            $query->where('data.year',$year);
            $query->where('data.season_id', $seasonId);
            $query->whereIn('data.variety_id',$varietyIds);
            $results=$query->get();
            $trial_data_1=[];
            $trial_data_2=[];
            foreach ($results as $result){
                if($result->data_1){
                    $data_1=json_decode($result->data_1);
                    foreach ($data_1 as $input_id=>$data){
                        $trial_data_1[$result->variety_id][$input_id][$result->entry_no]=$data;
                        if($inputs[$input_id]->max_entry_no<$result->entry_no){
                            $inputs[$input_id]->max_entry_no=$result->entry_no;
                        }
                    }

                }
                if($result->data_2){
                    $data_2=json_decode($result->data_2);
                    foreach ($data_2 as $input_id=>$data){
                        $trial_data_2[$result->variety_id][$input_id][$result->entry_no]=$data;
                        if(isset($inputs[$input_id]))
                        {
                            if($inputs[$input_id]->max_entry_no<$result->entry_no){
                                $inputs[$input_id]->max_entry_no=$result->entry_no;
                            }
                        }

                    }

                }
            }
            //report
            $rows=[];
            $fields=json_decode($this->reportInfo->fields,true);
            usort($fields,function ($a, $b){ return $a['ordering']>$b['ordering'];});
            $reportFields=[];
            foreach ($fields as $index=>$field){
                $reportFields[$index]['index']=$index;
                $reportFields[$index]['label']=$field['name'];
                $reportFields[$index]['max_entry_no']=1;
                $reportFields[$index]['type']='text';
                if($field['formula']=='none'){
                    if(isset($inputs[$field['inputId']]))
                    {
                        $reportFields[$index]['max_entry_no']=$inputs[$field['inputId']]->max_entry_no;
                        $reportFields[$index]['type']=$inputs[$field['inputId']]->type;
                    }
                }
                elseif(in_array($field['formula'],['total','average','min','max'])){
                    $reportFields[$index]['type']='number';
                }
            }
            foreach ($varieties as $variety){
                $row=$this->getItem($variety,$fields,$trial_data_1);
                $rows[]=$row;
                if($variety->replica==SYSTEM_STATUS_YES){
                    $row=$this->getItem($variety,$fields,$trial_data_2);
                    $row['rnd_code'].='-R';
                    $rows[]=$row;
                }
            }
            return response()->json(['error'=>'','reportFields'=>$reportFields,'data'=>$rows]);
            //return response()->json(['error'=>'','reportFields'=>$reportFields,'data'=>$rows,'inputs'=>$inputs]);
            //return response()->json(['error'=>'','columns'=>$inputs,'data'=>$trial_data]);
        }
        else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
    private function getItem($variety,$fields,$data): array
    {
        $row['rnd_code']=CommonHelper::get_display_rnd_code($variety->rnd_code,$this->permissions);
        if(isset($data[$variety->variety_id]))
        {
            foreach ($fields as $index=>$field){
                if($field['formula']=='none'){
                  if(isset($data[$variety->variety_id][$field['inputId']])){
                      foreach ($data[$variety->variety_id][$field['inputId']] as $entry_no=>$entry_data){
                          if(!is_null($entry_data)){
                              $row[$index.'_'.$entry_no]=$entry_data;
                          }
                      }
                  }
                }
                else if($field['formula']=='total'){
                    if(isset($data[$variety->variety_id][$field['inputId']])){
                        $row[$index.'_1']=0;
                        foreach ($data[$variety->variety_id][$field['inputId']] as $entry_no=>$entry_data){
                            if(is_numeric($entry_data)){
                                $row[$index.'_1']+=$entry_data;
                            }
                        }
                    }
                }
                else if($field['formula']=='average'){
                    if(isset($data[$variety->variety_id][$field['inputId']])){
                        $total=0;
                        $entry_counts=0;
                        foreach ($data[$variety->variety_id][$field['inputId']] as $entry_no=>$entry_data){
                            if(is_numeric($entry_data)){
                                $total+=$entry_data;
                                $entry_counts++;
                            }
                        }
                        if($entry_counts>1){
                            $row[$index.'_1']=number_format($total/$entry_counts,2);
                        }
                    }
                }
            }
        }
        return $row;
    }
}

