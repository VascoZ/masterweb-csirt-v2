@props(['severity'])
<span class="badge {{ $severity->badgeClass() }}">{{ $severity->label() }}</span>
