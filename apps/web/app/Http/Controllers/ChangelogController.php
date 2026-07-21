<?php

namespace App\Http\Controllers;

use App\Services\ChangelogService;
use App\Support\ApplicationBuild;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    public function __invoke(Request $request, ChangelogService $changelog): View
    {
        // One canonical page: guests see public entries; signed-in users see the full feed.
        $entries = $request->user()
            ? $changelog->entries($request->user())
            : $changelog->publicEntries();

        return view('changelog.public', [
            'entries' => $entries,
            'latestDate' => $entries->first()['date'] ?? null,
            'stage' => ApplicationBuild::stage(),
            'displayVersion' => ApplicationBuild::displayVersion(),
            'canonicalUrl' => url('/changelog'),
            'pageTitle' => 'Changelog — Finba.se',
            'metaDescription' => 'Histórico público das evoluções do produto e da plataforma Finba.se, em ordem cronológica.',
        ]);
    }
}
