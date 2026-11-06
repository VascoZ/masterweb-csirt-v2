@props(['status'])
<span class="badge {{ $status->badgeClass() }}">{{ $status->label() }}</span>
