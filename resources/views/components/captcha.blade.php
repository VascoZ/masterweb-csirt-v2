<div class="mb-3">
    <label for="captcha" class="form-label">Kode Captcha</label>
    <div class="d-flex align-items-center gap-2 mb-2 captcha-box">
        <img src="{{ captcha_src() }}" alt="Captcha" id="captcha-image" width="150" height="40">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-captcha-reload="#captcha-image" data-url="{{ route('captcha.reload') }}" aria-label="Muat ulang captcha">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
    <input type="text" name="captcha" id="captcha" autocomplete="off" required
        @class(['form-control', 'is-invalid' => $errors->has('captcha')]) placeholder="Ketik kode di atas">
    @error('captcha')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
