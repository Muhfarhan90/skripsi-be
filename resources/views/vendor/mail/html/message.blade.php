@php
    $websiteSetting = \App\Models\WebsiteSetting::first();
    $siteName = $websiteSetting?->site_name ?? config('app.name');
    $logoUrl = null;
    if ($websiteSetting?->logo_url) {
        $logoUrl = $websiteSetting->logo_url;
        if (!str_starts_with($logoUrl, 'http://') && !str_starts_with($logoUrl, 'https://')) {
            if (str_starts_with(ltrim($logoUrl, '/'), 'storage/')) {
                $logoUrl = asset($logoUrl);
            } else {
                $logoUrl = asset(\Storage::url($logoUrl));
            }
        }
    }
    $frontendUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$frontendUrl" :logoUrl="$logoUrl" :siteName="$siteName">
{{ $siteName }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer :websiteSetting="$websiteSetting">
© {{ date('Y') }} {{ $siteName }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

