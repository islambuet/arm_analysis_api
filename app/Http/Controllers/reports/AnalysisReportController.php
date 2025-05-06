<?php
namespace App\Http\Controllers\reports;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class AnalysisReportController extends RootController
{
    public $api_url = 'reports/analysis';
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
            $response['location_divisions'] = DB::table(TABLE_LOCATION_DIVISIONS)
                ->select('id', 'name')
                ->orderBy('name', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_districts'] = DB::table(TABLE_LOCATION_DISTRICTS)
                ->select('id', 'name', 'division_id')
                ->orderBy('name', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();

            $response['location_upazilas'] = DB::table(TABLE_LOCATION_UPAZILAS)
                ->select('id', 'name','district_id','territory_id')
                ->orderBy('name', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['location_unions'] = DB::table(TABLE_LOCATION_UNIONS)
                ->select('id', 'name','upazila_id')
                ->orderBy('name', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['crops'] = DB::table(TABLE_CROPS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES)
                ->select('id', 'name','crop_id')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['varieties']=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->leftJoin(TABLE_COMPETITORS.' as competitors', 'competitors.id', '=', 'varieties.competitor_id')
                ->addSelect('competitors.name as competitor_name')
                ->orderBy('varieties.id', 'DESC')
                ->where('varieties.status', '!=', SYSTEM_STATUS_DELETE)
                ->get();
            $response['principals'] = DB::table(TABLE_PRINCIPALS)
                ->select('id', 'name')
                ->orderBy('ordering', 'ASC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->get();
            $response['competitors'] = DB::table(TABLE_COMPETITORS)
                ->select('id', 'name')
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
            $response = [];
            $response['error'] ='';
            $options = $request->input('options');
            $district_ids=[];
            $district_ids[0]=0;
            $upazila_ids=[];
            if($options['upazila_id']>0){
                $upazila_ids[$options['upazila_id']]=$options['upazila_id'];
                $result = DB::table(TABLE_LOCATION_UPAZILAS)->find($options['upazila_id']);
                if ($result) {
                    $district_ids[$result->district_id]=$result->district_id;
                }
            }
            else if($options['district_id']>0){
                $district_ids[$options['district_id']]=$options['district_id'];
            }
            else if($options['division_id']>0){
                $results=DB::table(TABLE_LOCATION_DISTRICTS)->where('division_id',$options['division_id'])->get();
                if($results){
                    foreach ($results as $result){
                        $district_ids[$result->id]=$result->id;
                    }
                }
            }
            else{
                $results=DB::table(TABLE_LOCATION_DISTRICTS)->get();
                if($results){
                    foreach ($results as $result){
                        $district_ids[$result->id]=$result->id;
                    }
                }
            }
            if($options['territory_id']>0){
                $territory_upazila_ids=[];
                $territory_upazila_ids[0]=0;
                $territory_district_ids=[];
                $territory_district_ids[0]=0;

                $results=DB::table(TABLE_LOCATION_UPAZILAS)->select('district_id','id')->where('territory_id',$options['territory_id'])->get();
                if($results){
                    foreach ($results as $result){
                        $territory_district_ids[$result->district_id]=$result->district_id;
                        $territory_upazila_ids[$result->id]=$result->id;
                    }
                    //intersect
                    if($options['upazila_id']>0){
                        if(!in_array($options['upazila_id'],$territory_upazila_ids)){
                            unset($upazila_ids[$options['upazila_id']]);
                        }
                    }
                    else{
                        $upazila_ids=$territory_upazila_ids;
                    }
                }
                foreach ($district_ids as $district_id){
                    if(!in_array($district_id,$territory_district_ids)){
                        unset($district_ids[$district_id]);
                    }
                }

            }
            else if($options['area_id']>0){
                $area_district_ids=[];
                $area_district_ids[0]=0;

                $query=DB::table(TABLE_LOCATION_UPAZILAS.' as upazilas');
                $query->select('upazilas.district_id');
                $query->distinct();
                $query->leftJoin(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'upazilas.territory_id');
                $query->where('territories.area_id','=',$options['area_id']);
                $results=$query->get();
                if($results){
                    foreach ($results as $result){
                        $area_district_ids[$result->district_id]=$result->district_id;
                    }
                }
                //intersect
                foreach ($district_ids as $district_id){
                    if(!in_array($district_id,$area_district_ids)){
                        unset($district_ids[$district_id]);
                    }
                }
            }
            else if($options['part_id']>0){
                $part_district_ids=[];
                $part_district_ids[0]=0;
                $query = DB::table(TABLE_LOCATION_UPAZILAS . ' as upazilas');
                $query->select('upazilas.district_id');
                $query->distinct();
                $query->leftJoin(TABLE_LOCATION_TERRITORIES . ' as territories', 'territories.id', '=', 'upazilas.territory_id');
                $query->leftJoin(TABLE_LOCATION_AREAS . ' as areas', 'areas.id', '=', 'territories.area_id');
                $query->where('areas.part_id', '=', $options['part_id']);
                $results = $query->get();
                if ($results) {
                    foreach ($results as $result) {
                        $part_district_ids[$result->district_id] = $result->district_id;
                    }
                }
                foreach ($district_ids as $district_id){
                    if(!in_array($district_id,$part_district_ids)){
                        unset($district_ids[$district_id]);
                    }
                }
            }
            $query=DB::table(TABLE_ANALYSIS_DATA.' as ad');
            $query->select('ad.*');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'ad.type_id');
            $query->where('crop_types.status', SYSTEM_STATUS_ACTIVE);
            $query->addSelect('crop_types.crop_id');
            if($options['crop_id']>0){
                $query->where('crop_types.crop_id','=',$options['crop_id']);
                if($options['crop_type_id']>0){
                    $query->where('ad.type_id','=',$options['crop_type_id']);
                }
            }
            $query->whereIn('ad.district_id',$district_ids);//minimum [0]=0
            $results=$query->get();
            $items=[];
            foreach ($results as $result) {
                if(sizeof($upazila_ids)>0){
                    $result->market_size_total=0;
                    $result->market_size_arm=0;
                    $result->market_size_competitor=0;
                }
                $upazila_info = [];
                if (strlen($result->upazila_market_size) > 1) {
                    $temp = explode(",", $result->upazila_market_size);
                    foreach ($temp as $t) {
                        if (str_contains($t, '_')) {
                            $upazila_id=substr($t, 0, strpos($t, "_"));
                            $market_size=substr($t, strpos($t, "_") + 1);
                            if(sizeof($upazila_ids)>0){
                                if(in_array($upazila_id,$upazila_ids)){
                                    $upazila_info[$upazila_id]=['upazila_market_size'=>$market_size,'unions'=>[]];
                                    $result->market_size_total+=(+$market_size);
                                }
                            }
                            else{
                                $upazila_info[$upazila_id]=['upazila_market_size'=>$market_size,'unions'=>[]];
                            }

                        }
                    }
                }
                $result->upazila_info = $upazila_info;

                unset($result->upazila_market_size);

                $competitor_info=[];
                if(strlen($result->competitor_market_size)>1){
                    $temp=explode(",",$result->competitor_market_size);
                    foreach ($temp as $t){
                        if(str_contains($t,'_')){
                            $competitor_info[substr($t,0,strpos($t,"_"))]=['competitor_variety_market_size'=>substr($t,strpos($t,"_")+1),'competitor_sales_reason'=>''];
                        }
                    }
                }
                if(strlen($result->competitor_sales_reason)>3){
                    $temp=explode(",,,",$result->competitor_sales_reason);
                    foreach ($temp as $t){
                        if(str_contains($t,'_')){
                            $competitor_info[substr($t,0,strpos($t,"_"))]['competitor_variety_sales_reason']=substr($t,strpos($t,"_")+1);
                        }
                    }
                }
                unset($result->competitor_market_size);
                unset($result->competitor_sales_reason);
                $result->competitor_info = $competitor_info;
                $items[]=$result;
            }
            $response['items']=$items;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

