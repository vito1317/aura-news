<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Overtrue\LaravelPinyin\Facades\Pinyin;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::withCount('articles')->latest()->get();
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);
        
        $slug = Pinyin::permalink($validatedData['name']);

        $count = Category::where('slug', 'LIKE', "{$slug}%")->count();
        if ($count > 0) {
            $slug = $slug . '-' . ($count + 1);
        }

        $category = Category::create([
            'name' => $validatedData['name'],
            'slug' => $slug,
        ]);

        return response()->json($category, 201);
    }
    
    public function update(Request $request, Category $category)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
        ]);
        
        $category->update([
            'name' => $validatedData['name'],
        ]);

        return response()->json($category);
    }

    public function show(Category $category)
    {
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        if ($category->articles()->count() > 0) {
            return response()->json(['message' => '無法刪除，該分類下仍有文章。'], 422);
        }

        $category->delete();
        return response()->noContent();
    }
}