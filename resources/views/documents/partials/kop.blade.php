<table class="kop">
    <tr>
        @if ($institution['logo'])
            <td class="logo"><img src="{{ $institution['logo'] }}" alt=""></td>
        @endif
        <td>
            <div class="name">{{ $institution['name'] }}</div>
            @if ($institution['unit'])
                <div class="unit">{{ $institution['unit'] }}</div>
            @endif
            @if ($institution['address'] || $institution['phone'] || $institution['website'])
                <div class="meta">
                    {{ collect([$institution['address'], $institution['phone'], $institution['website']])
                        ->filter()->implode(' · ') }}
                </div>
            @endif
        </td>
    </tr>
</table>
