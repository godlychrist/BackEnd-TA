<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

try {
    $count = User::count();
    echo "--- REPORTE DE USUARIOS ---\n";
    echo "Total de usuarios encontrados: " . $count . "\n";

    if ($count > 0) {
        $lastUser = User::latest()->first();
        echo "Último usuario registrado: " . $lastUser->email . " (Nombre: " . $lastUser->name . ")\n";
    }

    $dbName = DB::connection('mongodb')->getDatabaseName();
    echo "Base de datos conectada: " . $dbName . "\n";

    // Listar colecciones
    $collections = DB::connection('mongodb')->listCollections();
    echo "Colecciones existentes:\n";
    foreach ($collections as $col) {
        echo "- " . $col->getName() . "\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
