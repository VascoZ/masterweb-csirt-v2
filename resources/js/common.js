// Perilaku interaktif berbasis atribut data-*, tanpa jQuery dan tanpa
// inline script/handler sehingga kompatibel dengan Content Security Policy.
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// <button data-confirm="Yakin?"> : minta konfirmasi sebelum submit/klik.
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-confirm]');
    if (trigger && !window.confirm(trigger.dataset.confirm)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
});

// <button data-captcha-reload> : ganti gambar captcha.
document.querySelectorAll('[data-captcha-reload]').forEach((button) => {
    button.addEventListener('click', async () => {
        const image = document.querySelector(button.dataset.captchaReload);
        const response = await fetch(button.dataset.url, { headers: { Accept: 'application/json' } });
        if (image && response.ok) {
            image.src = (await response.json()).captcha;
        }
    });
});

// <input type="checkbox" data-toggle-password="#password"> : tampilkan/sembunyikan password.
document.querySelectorAll('[data-toggle-password]').forEach((toggle) => {
    toggle.addEventListener('change', () => {
        document.querySelectorAll(toggle.dataset.togglePassword).forEach((input) => {
            input.type = toggle.checked ? 'text' : 'password';
        });
    });
});

// <input data-slug-url="/..." data-slug-target="#slug"> : isi slug otomatis dari judul.
document.querySelectorAll('[data-slug-url]').forEach((input) => {
    input.addEventListener('change', async () => {
        const target = document.querySelector(input.dataset.slugTarget);
        const url = `${input.dataset.slugUrl}?title=${encodeURIComponent(input.value)}`;
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (target && response.ok) {
            target.value = (await response.json()).slug;
        }
    });
});

// <input type="file" data-image-preview="#preview"> : pratinjau gambar sebelum diunggah.
document.querySelectorAll('[data-image-preview]').forEach((input) => {
    input.addEventListener('change', () => {
        const preview = document.querySelector(input.dataset.imagePreview);
        if (preview && input.files[0]) {
            preview.src = URL.createObjectURL(input.files[0]);
            preview.classList.remove('d-none');
        }
    });
});

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
