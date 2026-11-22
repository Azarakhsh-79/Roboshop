@props(['type' => 'success', 'id' => uniqid('alert-')])

<div class="alert-box alert-{{ $type }}" id="{{ $id }}">
    {{ $slot }}
    <button onclick="document.getElementById('{{ $id }}').style.display='none'"
        style="float:left;background:none;border:none;cursor:pointer">✖</button>
</div>
