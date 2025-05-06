<?php
$paths = explode('/', substr(url()->current(), strlen(url('/') . '/api')));
if (count($paths) > 1) {
    $folder = str_replace('-', '_', app_path('Http/Controllers/' . $paths[1]));
    if (is_dir($folder)) {
        $directory = new RecursiveDirectoryIterator($folder);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $file) {
            if ($file->getFilename() == 'route.api.php') {
                require_once $file->getpathName();
                //https://www.php.net/manual/en/class.splfileinfo.php
            }
        }
    }
}
// $routeCollection = Route::getRoutes();
//
//     echo "<table style='width:100%'>";
//     echo "<tr>";
//     echo "<td width='10%'><h4>HTTP Method</h4></td>";
//     echo "<td width='10%'><h4>Route</h4></td>";
//     echo "<td width='10%'><h4>Name</h4></td>";
//     echo "<td width='70%'><h4>Corresponding Action</h4></td>";
//     echo "</tr>";
//     foreach ($routeCollection as $value) {
//         echo "<tr>";
//         echo "<td>" ;
//         print_r ($value->methods());
//         echo "</td>";
//         echo "<td>" . $value->uri() . "</td>";
//         echo "<td>" . $value->getName() . "</td>";
//         echo "<td>" . $value->getActionName() . "</td>";
//         echo "</tr>";
//     }
//     echo "</table>";
//     die();
