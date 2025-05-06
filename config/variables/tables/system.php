<?php
$db_main=env('DB_DATABASE','rnd_2023');

define('TABLE_USER_GROUPS', $db_main.'.user_groups');
define('TABLE_USER_TYPES', $db_main.'.user_types');

define('TABLE_USER_AUTH_TOKENS', $db_main.'.user_auth_tokens');
define('TABLE_USER_SAVE_TOKENS', $db_main.'.user_save_tokens');
define('TABLE_USER_OTPS', $db_main.'.user_otps');
define('TABLE_MOBILE_SMS_HISTORIES', $db_main.'.system_history_mobile_sms');

define('TABLE_TASKS', $db_main.'.system_tasks');
define('TABLE_USER_HIDDEN_COLUMNS', $db_main.'.system_user_hidden_columns');


define('TABLE_CONFIGURATIONS', $db_main.'.system_configurations');
define('TABLE_SYSTEM_HISTORIES', $db_main.'.system_histories');


