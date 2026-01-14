<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaProxyController extends Controller
{
    public function show(string $uuid, ?string $conversion = null): Response
    {
        $media = Media::query()->where('uuid', $uuid)->firstOrFail();

        $url = $conversion && $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();

        $remote = Http::get($url);

        if (! $remote->successful()) {
            abort($remote->status());
        }

        $headers = array_filter([
            'Content-Type' => $remote->header('Content-Type'),
            'Content-Length' => $remote->header('Content-Length'),
            'Cache-Control' => $remote->header('Cache-Control'),
            'Last-Modified' => $remote->header('Last-Modified'),
        ]);

        return response($remote->body(), $remote->status())
            ->withHeaders($headers);
    }
}
