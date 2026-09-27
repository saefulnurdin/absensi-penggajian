@extends('layouts.auth')

@section('content')
<div class="auth-card card">
    <div class="auth-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 13V6a2 2 0 0 1 4 0v7"/><path d="M9 13v-2"/><circle cx="9" cy="15" r="1"/>
            <path d="M15 8a8 8 0 0 0-12 0"/><circle cx="17" cy="12" r="5"/>
        </svg>
    </div>
    <div class="auth-title">Sistem Absensi & Penggajian RFID</div>
    <div class="auth-sub">Login Administrator</div>

    <form method="POST" action="{{ route('login.attempt') }}" style="padding:0 20px 20px">
        @csrf
        <div class="form-group" style="margin-bottom:14px">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" placeholder="admin@absensi.local" required autofocus autocomplete="username">
        </div>
        <div class="form-group" style="margin-bottom:14px">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="••••••••" required autocomplete="current-password">
        </div>
        <label class="remember" style="margin-bottom:16px">
            <input type="checkbox" name="remember" value="1"> Ingat saya
        </label>
        <button type="submit" class="btn btn-primary" style="width:100%">
            Masuk
        </button>
    </form>

    @if ($errors->any())
        <div class="alert alert-danger" style="margin:0 20px 18px">
            <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
            <div>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="auth-meta" style="padding-bottom:18px">
        Akun demo: admin@absensi.local / admin1234<br>
        Mode lokal &bull; Asia/Jakarta
    </div>
</div>
@endsection