<x-layout title="Log Email">
    <div class="page-head">
        <div>
            <h1>Log Email</h1>
            <div class="sub">Riwayat notifikasi email yang dikirim sistem (slip gaji, rekap, peringatan)</div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('email-logs.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
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
                    @foreach (['SENT', 'FAILED'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline">
                <svg><use href="{{ asset('img/icons.svg') }}#i-filter"/></svg> Filter
            </button>
            <a href="{{ route('email-logs.index') }}" class="btn btn-ghost">Reset</a>
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
                        <th>Subjek</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</td>
                            <td><code style="font-size:12px">{{ $log->type }}</code></td>
                            <td>{{ $log->recipient }}</td>
                            <td>{{ $log->subject }}</td>
                            <td>
                                <span class="badge {{ $log->status === 'SENT' ? 'badge-success' : 'badge-danger' }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <svg><use href="{{ asset('img/icons.svg') }}#i-mail"/></svg>
                                    <div>Belum ada email terkirim.</div>
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