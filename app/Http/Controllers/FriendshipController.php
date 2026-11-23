<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FriendshipController extends Controller
{
    /**
     * إرسال طلب صداقة معرفية
     */
    public function sendRequest(Request $request, User $user)
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return redirect()->route('login');
        }

        if ($viewer->id === $user->id) {
            return back()->with('error', 'لا يمكنك إرسال طلب صداقة لنفسك.');
        }

        if ($viewer->isBlockedWith($user)) {
            return back()->with('error', 'تعذر إرسال طلب الصداقة بسبب قيود الحظر بين الحسابين.');
        }

        try {
            $friendship = $viewer->sendFriendRequest($user);
            $msg = $friendship->isAccepted()
                ? 'تم تأكيد الصداقة المعرفية بينكما مباشرة لوجود رغبة متبادلة! 🤝'
                : "تم إرسال طلب الصداقة المعرفية إلى «{$user->name}» بنجاح.";

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * قبول طلب الصداقة
     */
    public function acceptRequest(Request $request, User $user)
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return redirect()->route('login');
        }

        $accepted = $viewer->acceptFriendRequest($user);
        if ($accepted) {
            return back()->with('success', "تم قبول طلب الصداقة وبدء المؤاخاة القرائية مع «{$user->name}» 🤝");
        }

        return back()->with('error', 'لم يتم العثور على طلب صداقة معلق من هذا المستخدم.');
    }

    /**
     * رفض طلب الصداقة
     */
    public function rejectRequest(Request $request, User $user)
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return redirect()->route('login');
        }

        $rejected = $viewer->rejectFriendRequest($user);
        if ($rejected) {
            return back()->with('info', "تم رفض طلب الصداقة من «{$user->name}».");
        }

        return back()->with('error', 'تعذر تنفيذ الإجراء.');
    }

    /**
     * إلغاء الصداقة
     */
    public function unfriend(Request $request, User $user)
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return redirect()->route('login');
        }

        $unfriended = $viewer->unfriend($user);
        if ($unfriended) {
            return back()->with('info', "تم إلغاء الصداقة مع «{$user->name}».");
        }

        return back()->with('error', 'لا توجد صداقة قائمة مع هذا المستخدم.');
    }

    /**
     * حظر مستخدم ومنع أي تواصل معه
     */
    public function blockUser(Request $request, User $user)
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return redirect()->route('login');
        }

        if ($viewer->id === $user->id) {
            return back()->with('error', 'لا يمكنك حظر حسابك الخاص.');
        }

        $reason = $request->input('reason', 'حظر اختياري من قبل المستخدم');
        $viewer->blockUser($user, $reason);

        return back()->with('warning', "تم حظر المستخدم «{$user->name}» وإلغاء أي ارتباطات قائمة معه.");
    }

    /**
     * إلغاء حظر مستخدم
     */
    public function unblockUser(Request $request, User $user)
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return redirect()->route('login');
        }

        $unblocked = $viewer->unblockUser($user);
        if ($unblocked) {
            return back()->with('success', "تم إلغاء حظر المستخدم «{$user->name}».");
        }

        return back()->with('error', 'هذا المستخدم ليس محظوراً لديك.');
    }
}
