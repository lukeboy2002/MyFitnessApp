{{-- Kop van elke mail: blauwe band met het app-icoon (public/icons, staat in git) en de naam --}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('icons/icon-192.png') }}" class="logo" width="72" height="72" alt="">
<br>
<span class="header-name">{!! $slot !!}</span>
</a>
</td>
</tr>
