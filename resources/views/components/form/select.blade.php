@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null])
@php($selected = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value))
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <select name="{{ $name }}" id="{{ $name }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected($selected === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
