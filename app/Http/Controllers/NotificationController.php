<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
 public function getUserNotifications()
{
    $user = auth()->user();

    $notifications = Notification::where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->with(['ad', 'fromUser']) // جلب بيانات الإعلان والمستخدم الذي أرسل الإشعار
        ->get();

    $notifications->transform(function ($notification) {
        $fromUser = optional($notification->fromUser);
        $fromUserName = trim($fromUser->first_name . ' ' . $fromUser->last_name);

        // تحديد الرسالة بناءً على نوع الإشعار
        if ($notification->type === 'follow') {
            $message_ar = "$fromUserName بدأ بمتابعتك";
            $message_en = "$fromUserName started following you";
        } elseif ($notification->type === 'new_ad') {
            $message_ar = "$fromUserName نشر إعلان جديد!";
            $message_en = "$fromUserName posted a new ad!";
        } else {
            $message_ar = $notification->message_ar;
            $message_en = $notification->message_en;
        }

        // تحديد الصورة
        $image = null;
        if ($notification->type === 'follow') {
            $image = $fromUser->profile_image ? url('profile_images/' . $fromUser->profile_image) : null;
        } elseif (in_array($notification->type, ['ad_status', 'new_ad'])) {
            $image = optional($notification->ad)->main_image ? url($notification->ad->main_image) : null;
        }

        return [
            'id' => $notification->id,
            'user_id' => $notification->user_id,
            'from_user_id' => $notification->from_user_id,
            'type' => $notification->type,
            'ad_id' => $notification->ad_id,
            'message_ar' => $message_ar,
            'message_en' => $message_en,
            'is_read' => $notification->is_read,
            'created_at' => $notification->created_at,
            'updated_at' => $notification->updated_at,
            'image' => $image,
        ];
    });

    return response()->json($notifications);
}

   public function markAsRead(Request $request)
{
    $user = auth()->user();

    // التأكد من أن الطلب يحتوي على مصفوفة صحيحة
    $notificationIds = $request->input('notification_ids', []);

   

    // جلب الإشعارات الخاصة بالمستخدم فقط
    $notifications = Notification::whereIn('id', $notificationIds)
        ->where('user_id', $user->id)
        ->get();

    if ($notifications->isEmpty()) {
        return response()->json(['message' => 'No valid notifications found.'], 404);
    }

    // تحديث الإشعارات لتكون مقروءة
    Notification::whereIn('id', $notifications->pluck('id'))->update(['is_read' => 1]);

    return response()->json(['message' => 'Notifications marked as read']);
}

}
