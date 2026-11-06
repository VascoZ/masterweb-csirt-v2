import './common';
import 'trix';

// Unggah file lewat editor dinonaktifkan; gambar artikel memakai field terpisah.
document.addEventListener('trix-file-accept', (event) => event.preventDefault());
