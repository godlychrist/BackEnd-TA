<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Vehicle;

try {
    $vehicle = Vehicle::create([
        'brand' => 'Ferrari',
        'model' => 'Test',
        'year' => 2024,
        'price' => 100000,
        'status' => 'available',
        'user_id' => 'system'
    ]);
    echo "Vehículo creado ID: " . $vehicle->_id . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
