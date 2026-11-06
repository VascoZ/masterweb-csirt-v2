<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Models\Advisory;
use App\Support\SiteData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdvisoryController extends Controller
{
    public function index(Request $request): View
    {
        $severity = Severity::tryFrom((string) $request->query('severity'));

        return view('pages.advisories.index', [
            'severity' => $severity,
            'advisories' => Advisory::publiclyVisible()
                ->when($severity, fn ($query) => $query->where('severity', $severity->value))
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = '%' . $request->string('search') . '%';
                    $query->where(fn ($q) => $q->where('title', 'like', $search)->orWhere('cve', 'like', $search)->orWhere('code', 'like', $search));
                })
                ->latest('published_at')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function show(Advisory $advisory): View
    {
        // Advisory selain TLP:CLEAR atau yang belum terbit tidak pernah tampil di publik.
        abort_unless($advisory->isPubliclyVisible(), 404);

        return view('pages.advisories.show', ['advisory' => $advisory]);
    }

    /**
     * Feed Atom (RFC 4287) untuk advisory publik (TLP:CLEAR).
     */
    public function feedAtom(): Response
    {
        $advisories = Advisory::publiclyVisible()->latest('published_at')->take(25)->get();
        $siteName = e(SiteData::name());
        $updated = ($advisories->first()?->published_at ?? now())->toAtomString();

        $entries = $advisories->map(function (Advisory $adv) {
            $url = e(route('advisories.show', $adv));
            $title = e("[{$adv->code}] ({$adv->severity->label()}) {$adv->title}");
            $published = ($adv->published_at ?? $adv->created_at)->toAtomString();
            $summary = e($adv->summary);

            return <<<XML
              <entry>
                <id>{$url}</id>
                <title>{$title}</title>
                <link href="{$url}" rel="alternate"/>
                <updated>{$published}</updated>
                <summary>{$summary}</summary>
              </entry>
            XML;
        })->implode("\n");

        $selfUrl = e(route('advisories.feed.atom'));
        $indexUrl = e(route('advisories.index'));

        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom">
          <id>{$indexUrl}</id>
          <title>Peringatan Keamanan — {$siteName}</title>
          <link href="{$selfUrl}" rel="self" type="application/atom+xml"/>
          <link href="{$indexUrl}" rel="alternate" type="text/html"/>
          <updated>{$updated}</updated>
        {$entries}
        </feed>
        XML;

        return response($xml . "\n", 200, [
            'Content-Type' => 'application/atom+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    /**
     * JSON Feed 1.1 untuk integrasi otomatis advisory TLP:CLEAR.
     */
    public function feedJson(): JsonResponse
    {
        $advisories = Advisory::publiclyVisible()->latest('published_at')->take(25)->get();

        return response()->json([
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => 'Peringatan Keamanan — ' . SiteData::name(),
            'home_page_url' => route('advisories.index'),
            'feed_url' => route('advisories.feed.json'),
            'items' => $advisories->map(fn (Advisory $adv) => [
                'id' => $adv->code,
                'url' => route('advisories.show', $adv),
                'title' => "[{$adv->code}] {$adv->title}",
                'summary' => $adv->summary,
                'date_published' => ($adv->published_at ?? $adv->created_at)->toIso8601String(),
                'tags' => array_merge([$adv->tlp->label(), $adv->severity->value], $adv->cveList()),
            ])->all(),
        ]);
    }
}
