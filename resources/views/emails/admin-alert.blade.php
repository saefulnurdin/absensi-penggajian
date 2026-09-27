<x-emails::layout>
    <h2 style="margin:0 0 6px;font-size:18px;color:#dc2626;">{{ $alertSubject }}</h2>
    <p>{{ $alertMessage }}</p>

    @if (! empty($context))
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px;">
            @foreach ($context as $key => $value)
                <tr style="background:#f8fafc;">
                    <td style="padding:7px 12px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                    <td style="padding:7px 12px;border-bottom:1px solid #e2e8f0;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;padding:10px 12px;font-size:13px;border-radius:8px;margin-top:14px;">
        Mohon tinjau segera pada halaman Data Absensi.
    </div>
</x-emails::layout>