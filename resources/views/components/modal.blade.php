@props(['id', 'title', 'subtitle' => null])

<div id="{{ $id }}" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('{{ $id }}')">&times;</span>
        <div class="modal-header">
            <h3 class="modal-title">{{ $title }}</h3>
            @if($subtitle)
                <p class="modal-subtitle">{{ $subtitle }}</p>
            @endif
        </div>

        {{ $slot }}

    </div>
</div>
