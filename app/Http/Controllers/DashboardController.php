<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $account = $user->telegramAccount;

        return view('dashboard', [
            'account' => $account,
            'stats' => [
                'messages' => $user->messages()->count(),
                'today' => $user->messages()->where('created_at', '>=', today())->count(),
                'filtered' => $user->filteredMessages()->count(),
                'keywords' => $user->keywords()->count(),
                'chats' => $user->messages()->distinct()->count('chat_id'),
            ],
            'latest' => $user->messages()->latest('sent_at')->limit(8)->get(),
        ]);
    }
}
