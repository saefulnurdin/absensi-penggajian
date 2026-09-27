@props(['name', 'size' => 16])

<svg style="width:{{ $size }}px;height:{{ $size }}px">
    <use href="{{ asset('img/icons.svg') }}#{{ $name }}"/>
</svg>