<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $query = Vehicle::query();

        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        if ($request->filled('search')) {
            $query->where('model', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('year_min')) {
            $query->where('year', '>=', (int) $request->year_min);
        }

        if ($request->filled('year_max')) {
            $query->where('year', '<=', (int) $request->year_max);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('price_range')) {
            $range = explode('-', $request->price_range);
            $min = (int) $range[0];
            $max = (int) $range[1];
            $query->whereBetween('price', [$min, $max]);
        }

        $vehicles = $query->paginate(8);

        return response()->json($vehicles);
    }

    public function show($id)
    {
        $vehicle = Vehicle::with('user')->findOrFail($id);
        return response()->json($vehicle);
    }

    public function createVehicle(Request $request) {
        $validator = Validator::make($request->all(), [
            'brand' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|numeric',
            'price' => 'required|numeric',
            'status' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp',
            'user_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error al crear el vehículo',
                'error' => $validator->errors()
            ], 422);
        }

        if($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('vehicles', 'public');
        } else {
            $imagePath = null;
        }

        $vehicle = Vehicle::create([
            'brand' => $request->brand,
            'model' => $request->model,
            'year' => (int) $request->year,
            'price' => (float) $request->price,
            'status' => $request->status,
            'image' => $imagePath,
            'user_id' => $request->user_id
        ]);

        return response()->json([
            'message' => 'Vehículo creado exitosamente',
            'vehicle' => $vehicle
        ], 201);
    }

    public function editVehicle(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'brand' => 'string',
            'model' => 'string',
            'year' => 'numeric',
            'price' => 'numeric',
            'status' => 'string',
            'image' => 'image|mimes:jpeg,png,jpg,webp',
            'user_id' => 'string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error al editar el vehículo',
                'error' => $validator->errors()
            ], 422);
        }

        $vehicle = Vehicle::findOrFail($id);

        $vehicle->update($request->only([
            'brand',
            'model',
            'year',
            'price',
            'status',
            'image',
            'user_id'
        ]));

        if($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('vehicles', 'public');
            $vehicle->update([
                'image' => $imagePath
            ]);
        }
        return response()->json([
            'message' => 'Vehículo editado exitosamente',
            'vehicle' => $vehicle
        ], 200);
    }

    public function deleteVehicle(Request $request, $id) {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();
        return response()->json([
            'message' => 'Vehículo eliminado exitosamente',
            'vehicle' => $vehicle
        ], 200);
    }
}
