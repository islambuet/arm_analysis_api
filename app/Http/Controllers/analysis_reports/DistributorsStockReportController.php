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


class DistributorsStockReportController extends RootController
{
    public $api_url = 'analysis_reports/distributors_stock';
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
                ->addSelect('crops.name as crop_name')
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
            //$options['distributor_id']=102;

            if (!($options['distributor_id']>0)) {
                return response()->json(['error' => 'VALIDATION_FAILED', 'messages' => 'Select a Distributor']);
            }

            $response['stock_open_quantity']=[];
            $query=DB::table(TABLE_DISTRIBUTORS_STOCK.' as ds');
            $query->select('ds.*');
            $query->where('distributor_id','=',$options['distributor_id']);
            $query->where('fiscal_year','=',$options['fiscal_year']);
            $query->where('month','=',$options['month']);
            $query->where('status', '=', SYSTEM_STATUS_ACTIVE);
            $result = $query->first();
            if($result){
                if($result->stock){
                    $response['stock_open_quantity']=json_decode($result->stock);
                }
            }
            $month_next=($options['month']==12?1:$options['month']+1);
            $month_next_fiscal_year=($month_next==ConfigurationHelper::getCurrentFiscalYearStartingMonth()?$options['fiscal_year']+1:$options['fiscal_year']);

            $response['stock_end_quantity']=[];
            $query=DB::table(TABLE_DISTRIBUTORS_STOCK.' as ds');
            $query->select('ds.*');
            $query->where('distributor_id','=',$options['distributor_id']);
            $query->where('fiscal_year','=',$month_next_fiscal_year);
            $query->where('month','=',$month_next);
            $query->where('status', '=', SYSTEM_STATUS_ACTIVE);
            $result = $query->first();
            if($result){
                if($result->stock){
                    $response['stock_end_quantity']=json_decode($result->stock);
                }
            }

            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');
            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id');
            $query->select(DB::raw('SUM(quantity) as quantity'));
            $query->addSelect('varieties.id as variety_id');

            $query->whereYear('sd.sales_at','=',($options['month']<ConfigurationHelper::getCurrentFiscalYearStartingMonth()?$options['fiscal_year']+1:$options['fiscal_year']));
            $query->whereMonth('sd.sales_at','=',$options['month']);
            $query->where('sd.distributor_id','=',$options['distributor_id']);
            $query->groupBy('varieties.id');
            $results=$query->get();
            $response['purchase_months']=$results;

            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');
            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->select(DB::raw('SUM(quantity) as quantity'));
            $query->addSelect('varieties.id as variety_id');
            $query->where('sd.sales_at','>=',($options['fiscal_year']).'-'.ConfigurationHelper::getCurrentFiscalYearStartingMonth().'-01');
            $query->where('sd.sales_at','<',($month_next<ConfigurationHelper::getCurrentFiscalYearStartingMonth()?$options['fiscal_year']+1:$options['fiscal_year']).'-'.$month_next.'-01');
            $query->where('sd.distributor_id','=',$options['distributor_id']);
            if($options['crop_id']>0){
                $query->where('crop_types.crop_id','=',$options['crop_id']);
                if($options['crop_type_id']>0){
                    $query->where('crop_types.id','=',$options['crop_type_id']);
                    if($options['variety_id']>0){
                        $query->where('varieties.id','=',$options['variety_id']);
                    }
                }
            }
            $query->groupBy('varieties.id');
            $results=$query->get();
            $response['sales']=$results;


            $query=DB::table(TABLE_DISTRIBUTORS_TARGETS.' as sd');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'sd.variety_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->select(DB::raw('SUM(quantity) as quantity'));
            $query->addSelect('varieties.id as variety_id');
            $query->where('sd.fiscal_year','=',$options['fiscal_year']);
            $query->where('sd.distributor_id','=',$options['distributor_id']);
            if($options['crop_id']>0){
                $query->where('crop_types.crop_id','=',$options['crop_id']);
                if($options['crop_type_id']>0){
                    $query->where('crop_types.id','=',$options['crop_type_id']);
                    if($options['variety_id']>0){
                        $query->where('varieties.id','=',$options['variety_id']);
                    }
                }
            }
            $query->groupBy('varieties.id');
            $results=$query->get();
            $response['target']=$results;

            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

