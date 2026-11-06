@props(['name', 'label', 'checked' => false, 'help' => null])
<div class="form-check mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" id="{{ $name }}" value="1" @checked(old($name, $checked))
        {{ $attributes->class(['form-check-input', 'is-invalid' => $errors->has($name)]) }}>
    <label for="{{ $name }}" class="form-check-label">{{ $label }}</label>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
