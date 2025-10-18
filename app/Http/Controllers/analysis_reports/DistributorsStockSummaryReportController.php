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


class DistributorsStockSummaryReportController extends RootController
{
    public $api_url = 'analysis_reports/distributors_stock_summary';
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
            $response['distributors'] = DB::table(TABLE_DISTRIBUTORS.' as d')
                ->select('d.*')
                ->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id')
                ->addSelect('territories.area_id')
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
                ->addSelect('crop_types.name as type_name')
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
            $location_key='part_id';
            if($options['part_id']>0){
                $location_key='area_id';
                if($options['area_id']>0){
                    $location_key='distributor_id';
                }
            }

            $month_next=($options['month']==12?1:$options['month']+1);

            //Sales Current month start

            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');
            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'sd.distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');

            $query->select(DB::raw('SUM(quantity) as quantity'));
            $query->addSelect('varieties.id as variety_id');
            $query->addSelect($location_key.' as location_id');

            $query->whereYear('sd.sales_at','=',($options['month']<ConfigurationHelper::getCurrentFiscalYearStartingMonth()?$options['fiscal_year']+1:$options['fiscal_year']));
            $query->whereMonth('sd.sales_at','=',$options['month']);
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
                        if($options['distributor_id']>0){
                            $query->where('d.id','=',$options['distributor_id']);
                        }
                    }
                }
            }

            $query->groupBy('varieties.id');
            $query->groupBy($location_key);
            $results=$query->get();
            $response['purchase_month']=$results;
            //Sales Current month end

            // open stock start

            $query=DB::table(TABLE_DISTRIBUTORS_STOCK.' as ds');
            $query->select('ds.*');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'ds.distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.id as area_id','areas.part_id');


            $query->where('ds.fiscal_year','=',$options['fiscal_year']);
            $query->where('ds.month','=',$options['month']);
            $query->where('ds.status', '=', SYSTEM_STATUS_ACTIVE);
            if($options['part_id']>0){
                $query->where('areas.part_id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                        if($options['distributor_id']>0){
                            $query->where('d.id','=',$options['distributor_id']);
                        }
                    }
                }
            }

            $results=$query->get();
            $response['stock_open_quantity']=[];
            foreach ($results as $result){
                if($result){
                    if($result->stock){
                        $stock=json_decode($result->stock);
                        foreach ($stock as $variety_id=>$quantity){
                            if(isset($response['stock_open_quantity'][$variety_id])){
                                if(isset($response['stock_open_quantity'][$variety_id][$result->{$location_key}])){
                                    $response['stock_open_quantity'][$variety_id][$result->{$location_key}]+=$quantity;
                                }
                                else{
                                    $response['stock_open_quantity'][$variety_id][$result->{$location_key}]=$quantity;
                                }
                            }
                            else{
                                $response['stock_open_quantity'][$variety_id]=[];
                                $response['stock_open_quantity'][$variety_id][$result->{$location_key}]=$quantity;
                            }
                        }
                    }
                }
            }
            // open stock end
            // end stock start

            $month_next_fiscal_year=($month_next==ConfigurationHelper::getCurrentFiscalYearStartingMonth()?$options['fiscal_year']+1:$options['fiscal_year']);

            $query=DB::table(TABLE_DISTRIBUTORS_STOCK.' as ds');
            $query->select('ds.*');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'ds.distributor_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.id as area_id','areas.part_id');

            $query->where('ds.fiscal_year','=',$month_next_fiscal_year);
            $query->where('ds.month','=',$month_next);
            $query->where('ds.status', '=', SYSTEM_STATUS_ACTIVE);
            if($options['part_id']>0){
                $query->where('areas.part_id','=',$options['part_id']);
                if($options['area_id']>0){
                    $query->where('areas.id','=',$options['area_id']);
                    if($options['territory_id']>0){
                        $query->where('territories.id','=',$options['territory_id']);
                        if($options['distributor_id']>0){
                            $query->where('d.id','=',$options['distributor_id']);
                        }
                    }
                }
            }

            $results=$query->get();
            $response['stock_end_quantity']=[];
            foreach ($results as $result){
                if($result){
                    if($result->stock){
                        $stock=json_decode($result->stock);
                        foreach ($stock as $variety_id=>$quantity){
                            if(isset($response['stock_end_quantity'][$variety_id])){
                                if(isset($response['stock_end_quantity'][$variety_id][$result->{$location_key}])){
                                    $response['stock_end_quantity'][$variety_id][$result->{$location_key}]+=$quantity;
                                }
                                else{
                                    $response['stock_end_quantity'][$variety_id][$result->{$location_key}]=$quantity;
                                }
                            }
                            else{
                                $response['stock_end_quantity'][$variety_id]=[];
                                $response['stock_end_quantity'][$variety_id][$result->{$location_key}]=$quantity;
                            }
                        }
                    }
                }
            }
            //end stock end

            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

