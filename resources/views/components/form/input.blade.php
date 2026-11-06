@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null])
@php($id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)))
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"
        @if ($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif
        {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
