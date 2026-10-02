<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Source;
use App\Services\Practice\StudyExportBuilder;
use App\Services\Time\LocalDayResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StudyExportController extends Controller
{
    public function __construct(
        private readonly StudyExportBuilder $builder,
        private readonly LocalDayResolver $dayResolver,
    ) {}

    public function show(Source $source): View|RedirectResponse
    {
        // If the source has no active passages, redirect
        $counts = $this->builder->counts($source);
        if ($counts['passages'] === 0) {
            return redirect()
                ->route('sources.show', $source)
                ->with('status', __('practice.export.empty'));
        }

        $text = $this->builder->build($source);

        return view('library.sources.export', [
            'source' => $source,
            'text' => $text,
            'passages' => $counts['passages'],
            'cards' => $counts['cards'],
        ]);
    }

    public function download(Request $request, Source $source): StreamedResponse
    {
        $user = $request->user();
        $text = $this->builder->build($source);

        // Get the user's local date
        $localDay = $this->dayResolver->localDayFor($user);
        $date = $localDay->format('Y-m-d');

        // Build filename: slug + date, or fallback to source-{id}
        $slug = Str::slug($source->title);
        $filename = ! empty($slug)
            ? "{$slug}-{$date}.md"
            : "source-{$source->id}-{$date}.md";

        return response()->streamDownload(
            fn () => print ($text),
            $filename,
            [
                'Content-Type' => 'text/markdown; charset=UTF-8',
            ]
        );
    }
}
