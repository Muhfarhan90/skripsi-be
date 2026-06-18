@props(['url', 'logoUrl' => null, 'siteName' => null])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-flex; align-items: center; text-decoration: none; gap: 8px; vertical-align: middle;">
@if ($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="{{ $siteName }} Logo">
@else
<span class="brand-text" style="font-size: 22px; font-weight: 800; color: #0f7a5a; font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif; letter-spacing: -0.5px;">{{ $siteName ?? $slot }}</span>
@endif
</a>
</td>
</tr>

