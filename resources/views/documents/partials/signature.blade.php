{{-- One signature block: place, role, the image if it was signed, name and number. --}}
<td>
    <div>{{ $place ?? '' }}</div>
    <div>{{ $role }}</div>
    <div class="space">
        @if (! empty($image))
            <img src="{{ $image }}" alt="">
        @endif
    </div>
    <div class="name">{{ $name }}</div>
    <div>{{ $identifierLabel ?? 'NIP' }}. {{ $identifier ?: '—' }}</div>
</td>
