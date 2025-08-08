<?php
namespace App\Http\Controllers\reports;

use App\Http\Controllers\RootController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\TaskHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;


class SalesReportController extends RootController
{
    public $api_url = 'reports/sales';
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
                ->get();
            $response['crop_types'] = DB::table(TABLE_CROP_TYPES)
                ->select('id', 'name','crop_id', 'status')
                ->orderBy('ordering', 'ASC')
                ->get();
            $response['varieties']=DB::table(TABLE_VARIETIES.' as varieties')
                ->select('varieties.*')
                ->leftJoin(TABLE_COMPETITORS.' as competitors', 'competitors.id', '=', 'varieties.competitor_id')
                ->addSelect('competitors.name as competitor_name')
                ->orderBy('varieties.id', 'DESC')
                ->get();
            $response['pack_sizes'] = DB::table(TABLE_PACK_SIZES)
                ->select('id', 'name','variety_id', 'status')
                ->orderBy('ordering', 'ASC')
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

            $query=DB::table(TABLE_DISTRIBUTORS_SALES.' as sd');
            $query->select('sd.*');
            $query->join(TABLE_DISTRIBUTORS.' as d', 'd.id', '=', 'sd.distributor_id');
            //$query->addSelect('d.territory_id as territory_id');
            $query->join(TABLE_LOCATION_TERRITORIES.' as territories', 'territories.id', '=', 'd.territory_id');
            $query->addSelect('territories.id as territory_id');
            $query->join(TABLE_LOCATION_AREAS.' as areas', 'areas.id', '=', 'territories.area_id');
            $query->addSelect('areas.id as area_id','areas.part_id');
            //$query->join(TABLE_LOCATION_PARTS.' as parts', 'parts.id', '=', 'areas.part_id');
            //$query->addSelect('parts.id as part_id');
            $query->join(TABLE_PACK_SIZES.' as ps', 'ps.id', '=', 'sd.pack_size_id');
            //$query->addSelect('ps.id as pack_size_id');
            $query->join(TABLE_VARIETIES.' as varieties', 'varieties.id', '=', 'ps.variety_id');
            $query->addSelect('varieties.id as variety_id');
            $query->join(TABLE_CROP_TYPES.' as crop_types', 'crop_types.id', '=', 'varieties.crop_type_id');
            $query->addSelect('crop_types.id as crop_type_id','crop_types.crop_id');
            //$query->join(TABLE_CROPS.' as crops', 'crops.id', '=', 'crop_types.crop_id');
            //$query->addSelect('crops.id as crop_id');

            $query->where('sd.status', '!=', SYSTEM_STATUS_DELETE);
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
            if($options['crop_id']>0){
                $query->where('crop_types.crop_id','=',$options['crop_id']);
                if($options['crop_type_id']>0){
                    $query->where('crop_types.id','=',$options['crop_type_id']);
                    if($options['variety_id']>0){
                        $query->where('varieties.id','=',$options['variety_id']);
                        if($options['pack_size_id']>0){
                            $query->where('ps.id','=',$options['pack_size_id']);
                        }
                    }
                }
            }
            if($options['sales_from']){
                //$query->where('sd.sales_at','>=',$options['sales_from'].' 00:00:00');
                $query->whereDate('sd.sales_at','>=',$options['sales_from']);
            }
            if($options['sales_to']){
                //$query->where('sd.sales_at','<=',$options['sales_to'].' 23:59:59');
                $query->whereDate('sd.sales_at','<=',$options['sales_to']);
            }
            if($options['month']>0){
                $query->whereMonth('sd.sales_at','=',$options['month']);
            }

            $results=$query->get();

            $response['items']=$results;
            return response()->json($response);
        } else {
            return response()->json(['error' => 'ACCESS_DENIED', 'messages' => __('You do not have access on this page')]);
        }
    }
}

