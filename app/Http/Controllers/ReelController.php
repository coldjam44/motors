<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReelLikesLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReelController extends Controller
{
  public function toggleLike(Request $request, $reelId)
{
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized: You must be logged in to react.'], 401);
    }

    $reaction = $request->input('reaction'); // 'like' or 'dislike'
    if (!in_array($reaction, ['like', 'dislike'])) {
        return response()->json(['message' => 'Invalid reaction type. Allowed values: like, dislike.'], 422);
    }

    $reel = \DB::table('reels')->where('reels_id', $reelId)->first();
    if (!$reel) {
        return response()->json(['message' => 'Reel not found. Invalid reel ID provided.'], 404);
    }

    $existing = ReelLikesLog::where('reel_id', $reelId)
        ->where('user_id', $user->id)
        ->first();

    // دالة لمساعدة تحديث العداد
    $updateLikeCount = function($increment) use ($reelId) {
        \DB::table('reels')->where('reels_id', $reelId)->increment('reels_like_count', $increment);
    };

    if ($existing) {
        if ($existing->reaction === $reaction) {
            // حذف التفاعل الحالي
            $existing->delete();
            if ($reaction === 'like') {
                $updateLikeCount(-1);
            }
            // لو عايز تتعامل مع dislike count تضيف منطق هنا كمان
            return response()->json(['message' => 'Your reaction has been removed.']);
        } else {
            // تغيير التفاعل (مثلاً dislike -> like)
            if ($existing->reaction === 'like' && $reaction === 'dislike') {
                $updateLikeCount(-1);
            } elseif ($existing->reaction === 'dislike' && $reaction === 'like') {
                $updateLikeCount(1);
            }
            $existing->reaction = $reaction;
            $existing->save();
            return response()->json(['message' => 'Your reaction has been updated.']);
        }
    } else {
        // إضافة تفاعل جديد
        ReelLikesLog::create([
            'reel_id' => $reelId,
            'user_id' => $user->id,
            'reaction' => $reaction,
        ]);
        if ($reaction === 'like') {
            $updateLikeCount(1);
        }
        // لو عايز تضيف منطق للديسلايك برضه هنا
        return response()->json(['message' => 'Your reaction has been added.']);
    }
}

public function getAllReels()
{
    $reels = DB::table('reels')
        ->leftJoin('ads', 'reels.reels_ad_id', '=', 'ads.id')
        ->leftJoin('userauths', 'ads.user_id', '=', 'userauths.id')
        ->select(
            'reels.reels_id as reel_id',
            'reels.reels_ad_id as ad_id',
            'reels.reels_video_url',
            'reels.reels_thumbnail_url',
            'reels.reels_like_count',
            'reels.created_at',
            'userauths.id as user_id',
            DB::raw("CONCAT(userauths.first_name, ' ', userauths.last_name) as user_name"),
            'userauths.profile_image'
        )
        ->paginate(10);

    foreach ($reels as $reel) {
        $reel->liked_users = DB::table('reel_likes_logs')
            ->join('userauths', 'reel_likes_logs.user_id', '=', 'userauths.id')
            ->where('reel_likes_logs.reel_id', $reel->reel_id)
            ->where('reel_likes_logs.reaction', 'like')
            ->select(
                'userauths.id as user_id',
                DB::raw("CONCAT(userauths.first_name, ' ', userauths.last_name) as name"),
                'userauths.profile_image'
            )
            ->get()
            ->map(function ($user) {
                $user->profile_image = $user->profile_image
                    ? url('profile_images/' . $user->profile_image)
                    : null;
                return $user;
            });
    }

    return response()->json($reels);
}


}
