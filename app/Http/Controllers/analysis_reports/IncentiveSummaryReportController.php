<?php
namespace App\Http\Controllers\analysis_reports;

use App\Helpers\ConfigurationHelper;
use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class IncentiveSummaryReportController extends RootController
{
    public $api_url = 'analysis_reports/incentive_summary';
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
            $response['location_areas'] = DB::table(TABLE_LOCATION_AREAS.' as areas')
                ->select('areas.*')
                ->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id')
                ->addSelect('parts.name as part_name')
                ->orderBy('parts.name', 'ASC')
                ->orderBy('name', 'ASC')
                ->get();
            $response['location_territories'] = $query=DB::table(TABLE_LOCATION_TERRITORIES.' as territories')
                ->select('territories.*')
                ->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id')
                ->addSelect('areas.name as area_name','areas.part_id')
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
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES)
                ->select('id', 'name','crop_id', 'status')
                ->orderBy('ordering', 'ASC')
                ->orderBy('id', 'ASC')
                ->get();
            $response['varieties']=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->where('varieties.whose', '=', 'ARM')
                ->orderBy('crops.ordering', 'ASC')
                ->orderBy('crops.id', 'ASC')
                ->orderBy('crop_types.ordering', 'ASC')
                ->orderBy('crop_types.id', 'ASC')
                ->orderBy('varieties.ordering', 'ASC')
                ->orderBy('varieties.id', 'ASC')
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
            $net_sale_adjustment=0;
            $manager_incentive=0;
            $results = DB::table(TABLE_INCENTIVE_CONFIGURATIONS)->where('fiscal_year',$options['fiscal_year'])->get();
            foreach ($results as $result){
                if($result->purpose=='NET_SALE_ADJUSTMENT'){
                    $net_sale_adjustment=$result->config_value;
                }
                if(($options['report_format']=='part')&&($result->purpose=='INCENTIVE_PART')){
                    $manager_incentive=$result->config_value;
                }
                else if(($options['report_format']=='area')&&($result->purpose=='INCENTIVE_AREA')){
                    $manager_incentive=$result->config_value;
                }
                else if(($options['report_format']=='territory')&&($result->purpose=='INCENTIVE_TERRITORY')){
                    $manager_incentive=$result->config_value;
                }
                else if(($options['report_format']=='hom')&&($result->purpose=='INCENTIVE_HOM')){
                    $manager_incentive=$result->config_value;
                }
            }

            $incentive_slabs = DB::table(TABLE_INCENTIVE_SLABS)
                ->select('id', 'name')
                ->orderByRaw('CAST(name as INTEGER) DESC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->where('fiscal_year',$options['fiscal_year'])
                ->get();

            $incentive_varieties=[];
            $results = DB::table(TABLE_INCENTIVE_VARIETIES)->where('fiscal_year',$options['fiscal_year'])->get();
            foreach ($results as $result){
                if($result->incentive){
                    $result->incentive=json_decode($result->incentive);
                }
                $incentive_varieties[$result->variety_id]=$result;
            }
            $start_date=($options['fiscal_year']).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';
            $end_date=($options['fiscal_year']+1).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';
            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');

            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'sd.distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');

            $query->select(DB::raw('SUM(quantity) as quantity'),DB::raw('SUM(amount) as amount'));
            $query->addSelect('varieties.id as variety_id');

            $query->where('sd.sales_at','>=',$start_date);
            $query->where('sd.sales_at','<',$end_date);
            if($options['crop_id']>0){
                $query->where('crop_types.crop_id','=',$options['crop_id']);
                if($options['crop_type_id']>0){
                    $query->where('crop_types.id','=',$options['crop_type_id']);
                    if($options['variety_id']>0){
                        $query->where('varieties.id','=',$options['variety_id']);
                    }
                }
            }
            if($options['part_id']>0){
                $query->where('areas.part_id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                    }
                }
            }
            $query->whereIn('varieties.id',array_keys($incentive_varieties));
            if($options['report_format']=='territory')
            {
                $query->addSelect('territories.id as location_id');
                $query->groupBy('territories.id');
            }
            elseif($options['report_format']=='area')
            {
                $query->addSelect('areas.id as location_id');
                $query->groupBy('areas.id');
            }
            elseif($options['report_format']=='part')
            {
                $query->addSelect('areas.part_id as location_id');
                $query->groupBy('areas.part_id');
            }
            $query->groupBy('varieties.id');

            $results=$query->get();
            //$response['sales']=$results;

            $location_sales_items=[];
            foreach ($results as $result){
                $result->quantity=round($result->quantity,4);
                $result->amount=round($result->amount,3);
                $result->unit_price=0;
                $result->unit_price_net=0;
                if($result->quantity>0){
                    $result->unit_price=($result->amount/$result->quantity);
                    $result->unit_price_net=round($result->unit_price-($net_sale_adjustment*$result->unit_price/100),3);
                }
                $result->quantity_target=0;
                $location_sales_items[$result->location_id][$result->variety_id]=$result;
            }

            $query=DB::table(TABLE_DISTRIBUTORS_TARGETS.' as sd');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'sd.variety_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'sd.distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');

            $query->select(DB::raw('SUM(quantity) as quantity'));
            $query->addSelect('varieties.id as variety_id');

            $query->where('sd.fiscal_year','=',$options['fiscal_year']);
            if($options['crop_id']>0){
                $query->where('crop_types.crop_id','=',$options['crop_id']);
                if($options['crop_type_id']>0){
                    $query->where('crop_types.id','=',$options['crop_type_id']);
                    if($options['variety_id']>0){
                        $query->where('varieties.id','=',$options['variety_id']);
                    }
                }
            }
            if($options['part_id']>0){
                $query->where('areas.part_id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                    }
                }
            }
            $query->whereIn('varieties.id',array_keys($incentive_varieties));
            if($options['report_format']=='territory')
            {
                $query->addSelect('territories.id as location_id');
                $query->groupBy('territories.id');
            }
            elseif($options['report_format']=='area')
            {
                $query->addSelect('areas.id as location_id');
                $query->groupBy('areas.id');
            }
            elseif($options['report_format']=='part')
            {
                $query->addSelect('areas.part_id as location_id');
                $query->groupBy('areas.part_id');
            }
            $query->groupBy('varieties.id');
            $results=$query->get();
            //$response['target']=$results;
            foreach ($results as $result){
                if(isset($location_sales_items[$result->location_id])){
                    if(isset($location_sales_items[$result->location_id][$result->variety_id])){
                        $location_sales_items[$result->location_id][$result->variety_id]->quantity_target=$result->quantity;
                    }
                }
            }
            $location_incentive_items=[];
            foreach ($location_sales_items as $location_id=>$location_data){
                if(!isset($location_incentive_items[$location_id])){
                    $location_incentive_items[$location_id]=['amount_incentive'=>0];
                }
                foreach ($location_data as $variety_id=>$result){

                    $achievement=0;
                    if($result->quantity_target>0){
                        $achievement=round($result->quantity*100/$result->quantity_target,3);
                    }
                    else if($result->quantity>0){
                        $achievement=100;
                    }
                    if($achievement>0){
                        $amount_incentive=0;
                        foreach ($incentive_slabs as $slab){
                            if($achievement>=$slab->name){
                                $incentive_data=$incentive_varieties[$variety_id]->incentive;//must exits
                                if($incentive_data->{$slab->id}){
                                    $amount_incentive=round($result->quantity*$result->unit_price_net*$incentive_data->{$slab->id}*$manager_incentive/10000,3);
                                }
                                break;
                            }
                        }
                        $location_incentive_items[$location_id]['amount_incentive']+=$amount_incentive;
                    }
                }
            }

            $response['items']=$location_incentive_items;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }


}

