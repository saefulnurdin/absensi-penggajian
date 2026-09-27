@props(['type' => 'success'])

@if (session('success'))
    <div class="alert alert-success alert-auto">
        <svg><use href="{{ asset('img/icons.svg') }}#i-check"/></svg>
        <div>{{ session('success') }}</div>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-info alert-auto">
        <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
        <div>{{ session('warning') }}</div>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-auto">
        <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
        <div>{{ session('error') }}</div>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <svg><use href="{{ asset('img/icons.svg') }}#i-alert"/></svg>
        <div>
            <strong>Terjadi kesalahan:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif