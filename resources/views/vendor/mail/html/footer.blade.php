@props(['websiteSetting' => null])
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
{{ Illuminate\Mail\Markdown::parse($slot) }}
@if ($websiteSetting)
<div class="footer-details" style="margin-top: 15px; font-size: 11px; color: #94a3b8; line-height: 1.6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
@if ($websiteSetting->address)
<div style="margin-bottom: 4px;">{{ $websiteSetting->address }}</div>
@endif
@if ($websiteSetting->contact_email || $websiteSetting->contact_phone)
<div>
@if ($websiteSetting->contact_email)
<a href="mailto:{{ $websiteSetting->contact_email }}" style="color: #64748b; text-decoration: underline;">{{ $websiteSetting->contact_email }}</a>
@endif
@if ($websiteSetting->contact_email && $websiteSetting->contact_phone)
<span style="color: #cbd5e1; margin: 0 5px;">&bull;</span>
@endif
@if ($websiteSetting->contact_phone)
<span style="color: #64748b;">{{ $websiteSetting->contact_phone }}</span>
@endif
</div>
@endif
</div>
@endif
</td>
</tr>
</table>
</td>
</tr>

