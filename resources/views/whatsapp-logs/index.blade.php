<x-layout title="Log WhatsApp">
    <div class="page-head">
        <div>
            <h1>Log WhatsApp</h1>
            <div class="sub">Riwayat notifikasi WhatsApp (rekap harian, slip gaji) — status SENT / FAILED / SKIPPED</div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('whatsapp-logs.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group">
                <label>Jenis</label>
                <select name="type">
                    <option value="">Semua</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">Semua</option>
                    @foreach (['SENT', 'FAILED', 'SKIPPED'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-filter"/></svg> Filter
            </button>
            <a href="{{ route('whatsapp-logs.index') }}" class="btn btn-ghost">Reset</a>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Jenis</th>
                        <th>Penerima</th>
                        <th>Isi Ringkas</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</td>
                            <td><code style="font-size:12px">{{ $log->type }}</code></td>
                            <td>{{ $log->recipient }}</td>
                            <td style="max-width:280px">{{ \Illuminate\Support\Str::limit($log->body_preview, 60) }}</td>
                            <td>
                                @php
                                    $badge = match ($log->status) {
                                        'SENT' => 'badge-success',
                                        'FAILED' => 'badge-danger',
                                        default => 'badge-muted',
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ $log->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <svg><use href="{{ asset('img/icons.svg') }}#i-send"/></svg>
                                    <div>Belum ada pesan WhatsApp terkirim.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="pagination">{{ $logs->links() }}</div>
</x-layout>