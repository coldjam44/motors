<?php

namespace App\Http\Controllers;

use App\Models\VisitorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VisitorLogController extends Controller
{

    public function track(Request $request)
    {
        $ip = $request->ip();
        $countryId = $request->input('country_id');
        $categoryId = $request->input('category_id');
        $now = now();
    
        $responses = [];
    
        // أغلق كل السجلات المفتوحة لنفس IP بدون left_at
        VisitorLog::where('ip_address', $ip)
            ->whereNull('left_at')
            ->update(['left_at' => $now]);
    
        $responses[] = [
            'message_en' => 'Previous open visitor sessions closed for this IP.',
            'message_ar' => 'تم إغلاق جميع الجلسات المفتوحة السابقة لهذا الـ IP.',
        ];
    
        // Helper لتسجيل دخول جديد
        $logVisitor = function ($country, $category, $enMessage, $arMessage) use ($ip, $now, &$responses) {
            VisitorLog::create([
                'ip_address' => $ip,
                'country_id' => $country,
                'category_id' => $category,
                'general_visitor' => is_null($country) && is_null($category),
                'visited_at' => $now,
            ]);
    
            $responses[] = [
                'message_en' => $enMessage,
                'message_ar' => $arMessage,
            ];
        };
    
        // تسجيل الدخول حسب المدخلات
        if (is_null($countryId) && is_null($categoryId)) {
            $logVisitor(null, null, 'Visitor tracked for the whole site.', 'تم تسجيل زائر للموقع كامل.');
        } elseif (!is_null($countryId) && is_null($categoryId)) {
            $logVisitor($countryId, null, 'Visitor tracked browsing ads from a specific country.', 'تم تسجيل زائر يتصفح إعلانات من دولة محددة.');
        } elseif (is_null($countryId) && !is_null($categoryId)) {
            $logVisitor(null, $categoryId, 'Visitor tracked browsing ads in a specific category.', 'تم تسجيل زائر يتصفح إعلانات في تصنيف محدد.');
        } else {
            $logVisitor($countryId, null, 'Visitor tracked browsing ads from a specific country.', 'تم تسجيل زائر يتصفح إعلانات من دولة محددة.');
            $logVisitor(null, $categoryId, 'Visitor tracked browsing ads in a specific category.', 'تم تسجيل زائر يتصفح إعلانات في تصنيف محدد.');
        }
    
        // إحصائيات الزوار
        $statisticsData = app(\App\Http\Controllers\VisitorLogController::class)->statistics($request)->getData(true);
    
        return response()->json([
            'messages' => $responses,
            'statistics' => $statisticsData,
        ]);
    }
    
     public function statistics(Request $request)
{
    $countryId = $request->input('country_id');
    $categoryId = $request->input('category_id');

    if ($categoryId) {
        // إحصائيات الزيارات حسب category_id فقط
        $liveCount = \App\Models\VisitorLog::where('category_id', $categoryId)
            ->whereNull('left_at')
            ->count();

        $endedCount = \App\Models\VisitorLog::where('category_id', $categoryId)
            ->whereNotNull('left_at')
            ->count();

        return response()->json([
            'category_id' => (int)$categoryId,
            'live_visitors' => $liveCount,
            'ended_visitors' => $endedCount,
            'total_visitors' => $liveCount + $endedCount,
        ]);
    }

    if ($countryId) {
        // إحصائيات الزيارات حسب country_id فقط
        $liveCount = \App\Models\VisitorLog::where('country_id', $countryId)
            ->whereNull('left_at')
            ->count();

        $endedCount = \App\Models\VisitorLog::where('country_id', $countryId)
            ->whereNotNull('left_at')
            ->count();

        return response()->json([
            'country_id' => (int)$countryId,
            'live_visitors' => $liveCount,
            'ended_visitors' => $endedCount,
            'total_visitors' => $liveCount + $endedCount,
        ]);
    }

    // لو ما فيش فلتر، احسب كل الإحصائيات بدون فلتر

    $total = \App\Models\VisitorLog::count();

    $generalVisitors = \App\Models\VisitorLog::where('general_visitor', true)->count();

    $liveGeneralVisitors = \App\Models\VisitorLog::where('general_visitor', true)
        ->whereNull('left_at')
        ->count();

    $endedGeneralVisitors = \App\Models\VisitorLog::where('general_visitor', true)
        ->whereNotNull('left_at')
        ->count();

    $leftVisitors = \App\Models\VisitorLog::whereNotNull('left_at')->count();
    $liveVisitors = \App\Models\VisitorLog::whereNull('left_at')->count();

    $liveCategoryCounts = \App\Models\VisitorLog::whereNull('left_at')
        ->whereNotNull('category_id')
        ->select('category_id', \DB::raw('COUNT(*) as visitors'))
        ->groupBy('category_id')
        ->get();

    $endedCategoryCounts = \App\Models\VisitorLog::whereNotNull('left_at')
        ->whereNotNull('category_id')
        ->select('category_id', \DB::raw('COUNT(*) as visitors'))
        ->groupBy('category_id')
        ->get();

    $liveCountryCounts = \App\Models\VisitorLog::whereNull('left_at')
        ->whereNotNull('country_id')
        ->select('country_id', \DB::raw('COUNT(*) as visitors'))
        ->groupBy('country_id')
        ->get();

    $endedCountryCounts = \App\Models\VisitorLog::whereNotNull('left_at')
        ->whereNotNull('country_id')
        ->select('country_id', \DB::raw('COUNT(*) as visitors'))
        ->groupBy('country_id')
        ->get();

    return response()->json([
        'total' => $total,
        'general_visitors' => $generalVisitors,

        // live first
        'live_general_visitors' => $liveGeneralVisitors,
        'live_visitors' => $liveVisitors,
        'live_categories' => $liveCategoryCounts,
        'live_countries' => $liveCountryCounts,

        // ended next
        'ended_general_visitors' => $endedGeneralVisitors,
        'left_visitors' => $leftVisitors,
        'ended_categories' => $endedCategoryCounts,
        'ended_countries' => $endedCountryCounts,
    ]);
}

    

    public function trackExit(Request $request)
    {
        $ip = $request->ip();
        $now = now()->format('Y-m-d H:i:s'); // الصيغة المتوافقة مع MySQL
    
        $visitorLog = VisitorLog::where('ip_address', $ip)
            ->whereNull('left_at')
            ->orderByDesc('visited_at')
            ->first();
    
        if ($visitorLog) {
            $visitorLog->update(['left_at' => $now]);
    
            return response()->json([
                'message_en' => 'Visitor exit tracked successfully.',
                'message_ar' => 'تم تسجيل خروج الزائر بنجاح.',
                'left_at' => $now,
            ]);
        } else {
            return response()->json([
                'message_en' => 'No open visitor session found.',
                'message_ar' => 'لم يتم العثور على جلسة مفتوحة للزائر.',
            ], 404);
        }
    }
        
    

}
