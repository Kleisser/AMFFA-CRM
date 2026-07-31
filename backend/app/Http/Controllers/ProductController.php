<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('createdBy')->where('is_active', true);
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            });
        }
        return response()->json($query->orderBy('name')->paginate($request->get('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|max:3',
            'category' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:50|unique:products',
        ]);
        $validated['created_by'] = $request->user()->id;
        $product = Product::create($validated);
        return response()->json($product->load('createdBy'), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load('createdBy'));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:3',
            'category' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:50|unique:products,sku,' . $product->id,
            'is_active' => 'sometimes|boolean',
        ]);
        $product->update($validated);
        return response()->json($product->load('createdBy'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);
        return response()->json(['message' => 'Product archived']);
    }
}
