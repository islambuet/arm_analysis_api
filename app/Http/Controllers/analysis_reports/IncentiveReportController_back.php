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


class IncentiveReportController extends RootController
{
    public $api_url = 'analysis_reports/incentive';
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
            $response['location_territories'] = DB::table(TABLE_LOCATION_TERRITORIES)
                ->select('id', 'name','area_id', 'status')
                ->orderBy('name', 'ASC')
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
                ->addSelect('crop_types.name as crop_type_name')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name','crop_types.crop_id')
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
            $start_date=($options['fiscal_year']).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';
            $end_date=($options['fiscal_year']+1).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01';
            $net_sale_adjustment=0;
            $manager_incentive=0;

            $results = DB::table(TABLE_INCENTIVE_CONFIGURATIONS)->where('fiscal_year',$options['fiscal_year'])->get();
            foreach ($results as $result){
                if($result->purpose=='NET_SALE_ADJUSTMENT'){
                    $net_sale_adjustment=$result->config_value;
                }
                if($options['territory_id']>0){
                    if($result->purpose=='INCENTIVE_TERRITORY'){
                        $manager_incentive=$result->config_value;
                    }
                }
                else if($options['area_id']>0){
                    if($result->purpose=='INCENTIVE_AREA'){
                        $manager_incentive=$result->config_value;
                    }
                }
                else if($options['part_id']>0){
                    if($result->purpose=='INCENTIVE_PART'){
                        $manager_incentive=$result->config_value;
                    }
                }
                else {
                    if($result->purpose=='INCENTIVE_HOM'){
                        $manager_incentive=$result->config_value;
                    }
                }
            }
            $response['net_sale_adjustment']=$net_sale_adjustment;
            $response['manager_incentive']=$manager_incentive;

            $incentive_slabs = DB::table(TABLE_INCENTIVE_SLABS)
                ->select('id', 'name')
                ->orderByRaw('CAST(name as INTEGER) DESC')
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->where('fiscal_year',$options['fiscal_year'])
                ->get();
            $response['incentive_slabs']=$incentive_slabs;
            $incentive_varieties=[];
            $results = DB::table(TABLE_INCENTIVE_VARIETIES)->where('fiscal_year',$options['fiscal_year'])->get();
            foreach ($results as $result){
                if($result->incentive){
                    $result->incentive=json_decode($result->incentive);
                }
                $incentive_varieties[$result->variety_id]=$result;
            }

            $response['incentive_varieties']=$incentive_varieties;

            //varieties unit price setup for target start
            $results = DB::table(TABLE_PACK_SIZES)
                ->select('variety_id')
                ->addSelect(DB::raw('AVG(unit_price_per_kg) as unit_price_per_kg'))
                ->where('status', SYSTEM_STATUS_ACTIVE)
                ->groupBy('variety_id')
                ->get();
            $varieties_unit_price_per_kg=[];
            foreach ($results as $result){
                $varieties_unit_price_per_kg[$result->variety_id]=round($result->unit_price_per_kg,2);
            }

            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');
            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            $query->select(DB::raw('SUM(quantity) as quantity'),DB::raw('SUM(amount) as amount'));
            $query->addSelect('ps.variety_id as variety_id');
            $query->where('sd.sales_at','>=',$start_date);
            $query->where('sd.sales_at','<',$end_date);
            $query->groupBy('ps.variety_id');
            $results=$query->get();
            foreach ($results as $result){
                if($result->quantity>0){//always true
                    $varieties_unit_price_per_kg[$result->variety_id]=round($result->amount/$result->quantity,3);
                }

            }
            //varieties unit price setup for target end

            //Sales start

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
            $query->groupBy('varieties.id');

            $results=$query->get();
            $sales_targets_incentives=[];

            foreach ($results as $result){
                $item=[];
                $item['quantity_sales']=$result->quantity;
                $item['amount_sales']=$result->amount;
                $item['quantity_target']=0;
                $sales_targets_incentives[$result->variety_id]=$item;
            }

            //sales end

            //Target fiscal year start

            $query=DB::table(TABLE_DISTRIBUTORS_TARGETS.' as ds');
            $query->select('ds.*');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'ds.distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');


            $query->where('ds.fiscal_year','=',$options['fiscal_year']);
            $query->where('ds.status', '=', SYSTEM_STATUS_ACTIVE);
            if($options['part_id']>0){
                $query->where('areas.part_id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                    }
                }
            }
            $results=$query->get();
            foreach ($results as $result){
                if($result){
                    if($result->varieties){
                        $varieties=json_decode($result->varieties);
                        foreach ($varieties as $variety_id=>$quantity){
                            if(is_numeric($quantity)){
                                if(!isset($sales_targets_incentives[$variety_id])){
                                    $item=[];
                                    $item['quantity_sales']=0;
                                    $item['amount_sales']=0;
                                    $item['quantity_target']=0;
                                    $sales_targets_incentives[$variety_id]=$item;
                                }
                                $sales_targets_incentives[$variety_id]['quantity_target']+=$quantity;
                            }
                        }
                    }
                }
            }
            //Target fiscal year end

            //incentive calculation
            foreach ($sales_targets_incentives as $variety_id=>$result){
                $sales_targets_incentives[$variety_id]['unit_price']=0;
                if(isset($varieties_unit_price_per_kg[$variety_id])){
                    $sales_targets_incentives[$variety_id]['unit_price']=$varieties_unit_price_per_kg[$variety_id];
                }
                $sales_targets_incentives[$variety_id]['unit_price_net']=round($sales_targets_incentives[$variety_id]['unit_price']-($sales_targets_incentives[$variety_id]['unit_price']*$net_sale_adjustment/100),3);
                $sales_targets_incentives[$variety_id]['amount_target']=$sales_targets_incentives[$variety_id]['quantity_target']*$sales_targets_incentives[$variety_id]['unit_price'];

                $achievement=0;
                if($sales_targets_incentives[$variety_id]['quantity_target']>0){
                    $achievement=round($sales_targets_incentives[$variety_id]['quantity_sales']*100/$sales_targets_incentives[$variety_id]['quantity_target'],3);
                }
                else if($sales_targets_incentives[$variety_id]['quantity_sales']>0){
                    $achievement=100;
                }
                $sales_targets_incentives[$variety_id]['achievement']=$achievement;

                $quantity_incentive=0;
                $amount_incentive=0;
                if($achievement>0){
                    foreach ($incentive_slabs as $slab){
                        if($achievement>=$slab->name){
                            $quantity_incentive=$sales_targets_incentives[$variety_id]['quantity_sales'];
                            if(isset($incentive_varieties[$variety_id]))
                            {
                                $incentive_data=$incentive_varieties[$variety_id]->incentive;
                                if($incentive_data->{$slab->id}){
                                    $amount_incentive=round($sales_targets_incentives[$variety_id]['quantity_sales']*$sales_targets_incentives[$variety_id]['unit_price_net']*$incentive_data->{$slab->id}*$manager_incentive/10000,3);
                                }
                            }
                            break;
                        }
                    }
                }

                $sales_targets_incentives[$variety_id]['quantity_incentive']=$quantity_incentive;
                $sales_targets_incentives[$variety_id]['amount_incentive']=$amount_incentive;

            }
            //incentive calculation end





            $response['sales_targets_incentives']=$sales_targets_incentives;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

