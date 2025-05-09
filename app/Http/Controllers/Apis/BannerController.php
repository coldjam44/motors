<?php

namespace App\Http\Controllers\Apis;

use App\Models\banner;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::all();

        $banners->transform(function ($banner) {
            $banner->image_ar = url('image_ar/' . $banner->image_ar);
            $banner->image_en = url('image_en/' . $banner->image_en);
            return $banner;
        });

        return response()->json([
            'banners' => $banners
        ], 200);
    }

    /**
     * إضافة بانر جديد.
     */
    public function store(Request $request)
    {
        $request->validate([
            'image_ar' => 'required|image|mimes:jpeg,png,jpg,gif,svg',
            'image_en' => 'required|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        $banner = new Banner();

        if ($request->hasFile('image_ar')) {
            $image_ar = $request->file('image_ar');
            $imagename_ar = time() . '_ar.' . $image_ar->getClientOriginalExtension();
            $image_ar->move(public_path('image_ar'), $imagename_ar);
            $banner->image_ar = $imagename_ar;
        }

        if ($request->hasFile('image_en')) {
            $image_en = $request->file('image_en');
            $imagename_en = time() . '_en.' . $image_en->getClientOriginalExtension();
            $image_en->move(public_path('image_en'), $imagename_en);
            $banner->image_en = $imagename_en;
        }

        $banner->save();

        return response()->json([
            'message' => 'تمت إضافة الصورة بنجاح',
            'banner' => [
                'id' => $banner->id,
                'image_ar' => url('image_ar/' . $banner->image_ar),
                'image_en' => url('image_en/' . $banner->image_en),
                'created_at' => $banner->created_at,
                'updated_at' => $banner->updated_at
            ]
        ], 201);
    }

    /**
     * تحديث بانر موجود.
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'image_ar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
            'image_en' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        if ($request->hasFile('image_ar')) {
            // حذف الصورة القديمة
            $oldImageAr = public_path('image_ar/' . $banner->image_ar);
            if (File::exists($oldImageAr)) {
                File::delete($oldImageAr);
            }

            $image_ar = $request->file('image_ar');
            $imagename_ar = time() . '_ar.' . $image_ar->getClientOriginalExtension();
            $image_ar->move(public_path('image_ar'), $imagename_ar);
            $banner->image_ar = $imagename_ar;
        }

        if ($request->hasFile('image_en')) {
            // حذف الصورة القديمة
            $oldImageEn = public_path('image_en/' . $banner->image_en);
            if (File::exists($oldImageEn)) {
                File::delete($oldImageEn);
            }

            $image_en = $request->file('image_en');
            $imagename_en = time() . '_en.' . $image_en->getClientOriginalExtension();
            $image_en->move(public_path('image_en'), $imagename_en);
            $banner->image_en = $imagename_en;
        }

        $banner->save();

        return response()->json([
            'message' => 'تم تحديث الصورة بنجاح',
            'banner' => [
                'id' => $banner->id,
                'image_ar' => url('image_ar/' . $banner->image_ar),
                'image_en' => url('image_en/' . $banner->image_en),
                'created_at' => $banner->created_at,
                'updated_at' => $banner->updated_at
            ]
        ], 200);
    }

    /**
     * حذف بانر.
     */
    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);

        // حذف الصور من السيرفر
        $oldImageAr = public_path('image_ar/' . $banner->image_ar);
        if (File::exists($oldImageAr)) {
            File::delete($oldImageAr);
        }

        $oldImageEn = public_path('image_en/' . $banner->image_en);
        if (File::exists($oldImageEn)) {
            File::delete($oldImageEn);
        }

        // حذف السجل من قاعدة البيانات
        $banner->delete();

        return response()->json([
            'message' => 'تم حذف الصورة بنجاح'
        ], 200);
    }
}
