@props(['tlp'])
<span class="tlp {{ $tlp->badgeClass() }}" title="{{ $tlp->description() }}">{{ $tlp->label() }}</span>
