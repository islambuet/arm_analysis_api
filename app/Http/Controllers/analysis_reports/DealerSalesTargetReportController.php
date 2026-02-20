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


class DealerSalesTargetReportController extends RootController
{
    public $api_url = 'analysis_reports/dealer_sales_target';
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
            $response['distributors'] = DB::table(TABLE_DISTRIBUTORS)
                ->select('id', 'name','territory_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['dealers'] = DB::table(TABLE_DEALERS)
                ->select('id', 'name','distributor_id', 'status')
                ->orderBy('name', 'ASC')
                ->get();
            $response['crops'] = DB::table(TABLE_CROPS)
                ->select('id', 'name', 'status')
                ->orderBy('ordering', 'ASC')
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES.' as crop_types')
                ->select('crop_types.*')
                ->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id')
                ->addSelect('crops.name as crop_name')
                ->orderBy('ordering', 'ASC')
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
            $response['sales_targets']=[];
            //sales start
            $query=DB::table(TABLE_DEALERS_SALES.' as ds');
            $query->select('ds.*');
            $query->join(TABLE_DEALERS.' as dealers', 'dealers.id', '=', 'ds.dealer_id');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'dealers.distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');

            $query->where('ds.sales_at','>=',$start_date);
            $query->where('ds.sales_at','<',$end_date);
            $query->where('ds.status', '=', SYSTEM_STATUS_ACTIVE);
            if($options['part_id']>0){
                $query->where('areas.part_id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                        if($options['distributor_id']>0){
                            $query->where('d.id','=',$options['distributor_id']);
                            if($options['dealer_id']>0){
                                $query->where('dealers.id','=',$options['dealer_id']);
                            }
                        }
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
                                if(!isset($response['sales_targets'][$variety_id])){
                                    $response['sales_targets'][$variety_id]=[];
                                    $response['sales_targets'][$variety_id]['unit_price']=0;
                                    if(isset($varieties_unit_price_per_kg[$variety_id])){
                                        $response['sales_targets'][$variety_id]['unit_price']=$varieties_unit_price_per_kg[$variety_id];
                                    }
                                    $response['sales_targets'][$variety_id]['dealers']=[];
                                }
                                if(isset($response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]))
                                {
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_sales_gross']+=$quantity;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_sales_gross']+=($quantity*$response['sales_targets'][$variety_id]['unit_price']);
                                }
                                else{
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]=[];
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_target']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_target']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_sales_gross']=$quantity;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_sales_gross']=$quantity*$response['sales_targets'][$variety_id]['unit_price'];
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_sales_cancel']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_sales_cancel']=0;
                                }
                            }
                        }
                    }
                }
            }
            //sales end

            //sales cancel start

            $query=DB::table(TABLE_DEALERS_SALES_RETURN.' as ds');
            $query->select('ds.*');
            $query->join(TABLE_DEALERS.' as dealers', 'dealers.id', '=', 'ds.dealer_id');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'dealers.distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');

            $query->where('ds.sales_return_at','>=',$start_date);
            $query->where('ds.sales_return_at','<',$end_date);
            $query->where('ds.status', '=', SYSTEM_STATUS_ACTIVE);
            if($options['part_id']>0){
                $query->where('areas.part_id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                        if($options['distributor_id']>0){
                            $query->where('d.id','=',$options['distributor_id']);
                            if($options['dealer_id']>0){
                                $query->where('dealers.id','=',$options['dealer_id']);
                            }
                        }
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
                                if(!isset($response['sales_targets'][$variety_id])){
                                    $response['sales_targets'][$variety_id]=[];
                                    $response['sales_targets'][$variety_id]['unit_price']=0;
                                    if(isset($varieties_unit_price_per_kg[$variety_id])){
                                        $response['sales_targets'][$variety_id]['unit_price']=$varieties_unit_price_per_kg[$variety_id];
                                    }
                                    $response['sales_targets'][$variety_id]['dealers']=[];
                                }
                                if(isset($response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]))
                                {
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_sales_cancel']+=$quantity;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_sales_cancel']+=($quantity*$response['sales_targets'][$variety_id]['unit_price']);
                                }
                                else{
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]=[];
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_target']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_target']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_sales_gross']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_sales_gross']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_sales_cancel']=$quantity;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_sales_cancel']=$quantity*$response['sales_targets'][$variety_id]['unit_price'];
                                }
                            }
                        }
                    }
                }
            }

            //sales cancel end
            //Target fiscal year start

            $query=DB::table(TABLE_DEALERS_TARGETS.' as ds');
            $query->select('ds.*');
            $query->join(TABLE_DEALERS.' as dealers', 'dealers.id', '=', 'ds.dealer_id');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'dealers.distributor_id');
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
                        if($options['distributor_id']>0){
                            $query->where('d.id','=',$options['distributor_id']);
                            if($options['dealer_id']>0){
                                $query->where('dealers.id','=',$options['dealer_id']);
                            }
                        }
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
                                if(!isset($response['sales_targets'][$variety_id])){
                                    $response['sales_targets'][$variety_id]=[];
                                    $response['sales_targets'][$variety_id]['unit_price']=0;
                                    if(isset($varieties_unit_price_per_kg[$variety_id])){
                                        $response['sales_targets'][$variety_id]['unit_price']=$varieties_unit_price_per_kg[$variety_id];
                                    }
                                    $response['sales_targets'][$variety_id]['dealers']=[];
                                }
                                if(isset($response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]))
                                {
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_target']=$quantity;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_target']=$quantity*$response['sales_targets'][$variety_id]['unit_price'];
                                }
                                else{
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]=[];
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_target']=$quantity;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_target']=$quantity*$response['sales_targets'][$variety_id]['unit_price'];
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_sales_gross']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_sales_gross']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['quantity_sales_cancel']=0;
                                    $response['sales_targets'][$variety_id]['dealers'][$result->dealer_id]['amount_sales_cancel']=0;
                                }
                            }
                        }
                    }
                }
            }

            //Target fiscal year end
            //$response['items']=[];
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

