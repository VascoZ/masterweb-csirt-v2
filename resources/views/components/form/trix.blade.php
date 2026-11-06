@props(['name', 'label', 'value' => null])
<div class="mb-3">
    <label for="{{ $name }}-editor" class="form-label">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $value) }}">
    <trix-editor id="{{ $name }}-editor" input="{{ $name }}" @class(['form-control', 'is-invalid' => $errors->has($name)])></trix-editor>
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
