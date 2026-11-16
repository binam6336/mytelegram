<?php

namespace App\Http\Controllers;

use App\Models\FilteredMessage;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));
        $chat = $request->query('chat');

        $messages = $user->messages()
            ->when($q !== '', fn ($w) => $w->where('text', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->when($chat, fn ($w) => $w->where('chat_id', $chat))
            ->latest('sent_at')->latest('id')
            ->paginate(30)->withQueryString();

        $filteredIds = FilteredMessage::where('user_id', $user->id)
            ->whereIn('message_id', $messages->pluck('id'))->pluck('message_id')->flip();

        $chats = $user->messages()->selectRaw('chat_id, MAX(chat_title) as title, COUNT(*) as n')
            ->groupBy('chat_id')->orderByDesc('n')->limit(100)->get();

        return view('messages.index', compact('messages', 'chats', 'q', 'chat', 'filteredIds'));
    }

    /** حذف همه‌ی پیام‌ها به‌جز آن‌هایی که فیلتر شده‌اند */
    public function destroyAll(Request $request)
    {
        $user = $request->user();
        $request->validate(['confirm' => 'required|in:حذف'], ['confirm.in' => 'برای تأیید کلمه‌ی «حذف» را بنویسید.', 'confirm.required' => 'برای تأیید کلمه‌ی «حذف» را بنویسید.']);

        $deleted = 0;
        do {
            $ids = Message::where('user_id', $user->id)
                ->whereNotExists(fn ($s) => $s->from('filtered_messages')->whereColumn('filtered_messages.message_id', 'messages.id'))
                ->limit(1000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : Message::whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        return back()->with('ok', number_format($deleted).' پیام حذف شد؛ پیام‌های فیلترشده باقی ماندند.');
    }
}
