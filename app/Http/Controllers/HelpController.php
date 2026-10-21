<?php

namespace App\Http\Controllers;

use App\Support\HelpTopics;
use Illuminate\Http\Request;

/** Help: step-by-step instructions with screenshots, showing the topics for the user's role first. */
class HelpController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $all = $request->boolean('all');
        $topics = collect(HelpTopics::all())
            ->map(fn ($t) => $t + ['mine' => HelpTopics::relevant($t, $user)])
            ->filter(fn ($t) => $all || $t['mine'])
            ->map(function ($t) {
                $path = isset($t['image']) ? public_path('images/help/'.$t['image']) : null;
                $size = $path && is_file($path) ? @getimagesize($path) : false;

                return $t + ['has_image' => (bool) $size, 'narrow' => $size && ($size[0] < 800 || $size[1] > $size[0])];
            });

        return view('help.index', [
            'groups' => $topics->groupBy('group'),
            'all' => $all,
            'hidden' => count(HelpTopics::all()) - $topics->count(),
        ]);
    }
}
