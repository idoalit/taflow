@if (! empty($verification['qr']))
    <table class="verify">
        <tr>
            <td style="width: 84px"><img src="{{ $verification['qr'] }}" alt=""></td>
            <td>
                Pindai untuk memverifikasi keaslian dokumen ini.<br>
                {{ $verification['url'] }}
            </td>
        </tr>
    </table>
@endif
