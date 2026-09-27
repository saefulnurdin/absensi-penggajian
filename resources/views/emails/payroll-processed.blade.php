<x-emails::layout>
    <h2 style="margin:0 0 6px;font-size:18px;color:#16a34a;">Gaji Telah Diproses</h2>
    <p>Halo <strong>{{ $employee->name }}</strong>,</p>
    <p>Gaji Anda untuk periode <strong>{{ \Carbon\Carbon::createFromFormat('Y-m', $payroll->period)->translatedFormat('F Y') }}</strong> telah diproses oleh admin.</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px;">
        <tr><td style="padding:8px 12px;background:#f8fafc;color:#64748b;">Gaji Pokok</td><td style="padding:8px 12px;">Rp{{ number_format($payroll->base_salary, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:8px 12px;background:#f8fafc;color:#64748b;">Potongan Tidak Hadir</td><td style="padding:8px 12px;color:#dc2626;">- Rp{{ number_format($payroll->deduction, 0, ',', '.') }}</td></tr>
        <tr style="background:#eef2ff;"><td style="padding:10px 12px;color:#4338ca;font-weight:700;">Gaji Bersih</td><td style="padding:10px 12px;color:#16a34a;font-weight:700;font-size:16px;">Rp{{ number_format($payroll->net_salary, 0, ',', '.') }}</td></tr>
    </table>

    <p style="font-size:12px;color:#64748b;margin-top:14px;">
        Slip gaji lengkap beserta rekap absensi dikirimkan terpisah pada email sebelumnya.
    </p>
</x-emails::layout>