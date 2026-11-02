# To Do List PHP

Tugas 5 Pemrograman Berbasis Web. Bikin halaman to do list pakai PHP dan MySQL, harus login dulu baru bisa dibuka.

Bisa tambah kegiatan, tandai selesai (jadi dicoret dan pindah ke bawah), sama hapus. Di atas ada nama, NIM, dan foto.

## Cara jalanin

Pakai XAMPP. Nyalain Apache sama MySQL, terus taruh semua file di satu folder di `htdocs` (misal `htdocs/todo`). Habis itu buka `http://localhost/todo/`.

Databasenya nggak perlu import, bikin sendiri waktu halaman pertama dibuka.

## Login

Username `admin`, password sama kayak NIM (diatur di `ADMIN_PASS` di `index.php`). Passwordnya disimpan di database dalam bentuk hash.

Kalau `ADMIN_PASS` diganti setelah halaman pernah dibuka, password lamanya masih nyangkut di database. Hapus baris `admin` di tabel `users` lewat phpMyAdmin, nanti dibuat ulang.

## File

- `index.php` : login dan to do list
- `style.css` : tampilan
- `foto_profil.jpeg` : foto buat header

Nama, NIM, sama nama file foto bisa diganti di bagian paling atas `index.php`.
