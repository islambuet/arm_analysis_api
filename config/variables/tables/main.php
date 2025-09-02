<?php

$db_main=env('DB_DATABASE','rnd_2023');

//users
define('TABLE_USERS', $db_main.'.users');
define('TABLE_PRINCIPALS', $db_main.'.principals');
define('TABLE_COMPETITORS', $db_main.'.competitors');

define('TABLE_CROPS', $db_main.'.crops');
define('TABLE_CROP_TYPES', $db_main.'.crop_types');
define('TABLE_VARIETIES', $db_main.'.varieties');
define('TABLE_PACK_SIZES', $db_main.'.pack_sizes');


define('TABLE_LOCATION_PARTS', $db_main.'.location_parts');
define('TABLE_LOCATION_AREAS', $db_main.'.location_areas');
define('TABLE_LOCATION_TERRITORIES', $db_main.'.location_territories');
define('TABLE_DISTRIBUTORS', $db_main.'.distributors');
define('TABLE_LOCATION_DIVISIONS', $db_main.'.location_divisions');
define('TABLE_LOCATION_DISTRICTS', $db_main.'.location_districts');
define('TABLE_LOCATION_UPAZILAS', $db_main.'.location_upazilas');
define('TABLE_LOCATION_UNIONS', $db_main.'.location_unions');

define('TABLE_ANALYSIS_YEARS', $db_main.'.analysis_years');

define('TABLE_MARKET_SIZE_DATA', $db_main.'.market_size_data');
define('TABLE_DISTRIBUTORS_SALES', $db_main.'.distributors_sales');
define('TABLE_DISTRIBUTORS_TARGETS', $db_main.'.distributors_targets');
define('TABLE_DISTRIBUTORS_STOCK', $db_main.'.distributors_stock');
define('TABLE_DISTRIBUTORS_PLAN_3YRS', $db_main.'.distributors_plan_3yrs');

define('TABLE_INCENTIVE_SLABS', $db_main.'.incentive_slabs');
define('TABLE_INCENTIVE_CONFIGURATIONS', $db_main.'.incentive_configurations');
define('TABLE_INCENTIVE_VARIETIES', $db_main.'.incentive_varieties');

define('TABLE_TYPE_MONTHS_COLOR', $db_main.'.type_months_color');
define('TABLE_TYPE_MONTHS', $db_main.'.type_months');
