<?php

namespace App\Http\Controllers;

use App\Models\FilteredMessage;
use App\Services\KeywordFilter;
use Illuminate\Http\Request;

/** صفحه‌ی فیلتر پیام: کلیدواژه‌ها + پیام‌های فیلترشده (جدول جدا) */
class FilterController extends Controller
{
    public function __construct(private KeywordFilter $filter) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $kw = $request->query('kw');
        $q = trim((string) $request->query('q'));

        $items = $user->filteredMessages()
            ->when($kw, fn ($w) => $w->whereJsonContains('matched_keywords', $kw))
            ->when($q !== '', fn ($w) => $w->where('text', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->latest('sent_at')->latest('id')
            ->paginate(30)->withQueryString();

        $keywords = $user->keywords()->orderBy('word')->get();
        $counts = [];
        foreach ($user->filteredMessages()->pluck('matched_keywords') as $list) {
            foreach ((array) $list as $w) {
                $counts[$w] = ($counts[$w] ?? 0) + 1;
            }
        }

        return view('filters.index', compact('items', 'keywords', 'counts', 'kw', 'q'));
    }

    public function storeKeyword(Request $request)
    {
        $user = $request->user();
        $words = collect(preg_split('/[\n,،]+/u', (string) $request->input('words')))
            ->map(fn ($w) => trim(preg_replace('/\s+/u', ' ', $w)))
            ->filter(fn ($w) => mb_strlen($w) >= 2 && mb_strlen($w) <= 100)
            ->unique()->values();

        if ($words->isEmpty()) {
            return back()->withErrors(['words' => 'کلیدواژه باید دست‌کم ۲ حرف باشد (چند کلمه را با ویرگول یا خط جدید جدا کنید).']);
        }
        if ($user->keywords()->count() + $words->count() > 200) {
            return back()->withErrors(['words' => 'حداکثر ۲۰۰ کلیدواژه مجاز است.']);
        }
        $existing = $user->keywords()->pluck('word')->map(fn ($w) => KeywordFilter::normalize($w))->all();
        $added = 0;
        foreach ($words as $w) {
            if (! in_array(KeywordFilter::normalize($w), $existing, true)) {
                $user->keywords()->create(['word' => $w]);
                $existing[] = KeywordFilter::normalize($w);
                $added++;
            }
        }
        // کلیدواژه‌ی تازه روی پیام‌های قبلی هم اعمال می‌شود
        $matched = $added ? $this->filter->rescan($user->id) : 0;

        return back()->with('ok', $added
            ? $added.' کلیدواژه اضافه شد؛ '.number_format($matched).' پیام با کلیدواژه‌ها منطبق است.'
            : 'این کلیدواژه‌ها از قبل وجود داشتند.');
    }

    public function destroyKeyword(Request $request, int $keyword)
    {
        $request->user()->keywords()->whereKey($keyword)->delete();

        return back()->with('ok', 'کلیدواژه حذف شد. (پیام‌هایی که قبلاً فیلتر شده‌اند باقی می‌مانند.)');
    }

    public function rescan(Request $request)
    {
        $n = $this->filter->rescan($request->user()->id);

        return back()->with('ok', 'همه‌ی پیام‌ها دوباره بررسی شد؛ '.number_format($n).' پیام منطبق.');
    }

    public function destroyFiltered(Request $request, int $item)
    {
        FilteredMessage::where('user_id', $request->user()->id)->whereKey($item)->delete();

        return back()->with('ok', 'از فیلترشده‌ها حذف شد.');
    }
}
