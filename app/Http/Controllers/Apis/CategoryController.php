<?php

namespace App\Http\Controllers\Apis;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class CategoryController extends Controller
{
    /**
     * عرض جميع التصنيفات مع روابط الصور.
     */
    public function index()
    {
        $categories = Category::all();

        $categories->transform(function ($category) {
            $category->image = url('categorys/' . $category->image);
            return $category;
        });

        return response()->json([
            'categories' => $categories
        ], 200);
    }

    /**
     * إضافة تصنيف جديد.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'image'   => 'required|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        $category = new Category();
        $category->name_ar = $request->name_ar;
        $category->name_en = $request->name_en;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('categorys'), $imageName);
            $category->image = $imageName;
        }

        $category->save();

        return response()->json([
            'message' => 'تمت إضافة التصنيف بنجاح',
            'category' => [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'image' => url('categorys/' . $category->image)
            ]
        ], 201);
    }

    /**
     * تحديث تصنيف موجود.
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        $category->name_ar = $request->name_ar;
        $category->name_en = $request->name_en;

        if ($request->hasFile('image')) {
            $oldImage = public_path('categorys/' . $category->image);
            if (File::exists($oldImage)) {
                File::delete($oldImage);
            }

            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('categorys'), $imageName);
            $category->image = $imageName;
        }

        $category->save();

        return response()->json([
            'message' => 'تم تحديث التصنيف بنجاح',
            'category' => [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'image' => url('categorys/' . $category->image)
            ]
        ], 200);
    }

    /**
     * حذف تصنيف.
     */
    public function destroy($id)
    {
        $category = category::findOrFail($id);

        $imagePath = public_path('categorys/' . $category->image);
        if (File::exists($imagePath)) {
            File::delete($imagePath);
        }

        $category->delete();

        return response()->json([
            'message' => 'تم حذف التصنيف بنجاح'
        ], 200);
    }
}
