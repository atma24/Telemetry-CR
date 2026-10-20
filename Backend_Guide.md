# TeraPublic — Backend Development Guide
**Versi 1.0 — Rancangan Awal**

## 1. Gambaran Umum

TeraPublic adalah platform civic-tech untuk transparansi data publik, monitoring wilayah, fasilitas publik, pembangunan, dan insight berbasis data.

### Teknologi utama

- **Frontend:** Next.js App Router
- **Backend utama:** FastAPI (Python)
- **Database utama:** PostgreSQL
- **Vector storage:** pgvector, sebagai opsi awal untuk RAG
- **Machine Learning:** Python, jenis model ditentukan setelah audit dataset
- **RAG:** Python service terpisah dari backend utama

### Prinsip arsitektur

1. Backend utama menangani business logic, autentikasi, otorisasi, CRUD, import, validasi, publikasi, dan feedback.
2. Next.js menangani UI, routing halaman, formulir, serta komunikasi dengan API.
3. RAG dan ML dipisahkan sebagai layanan khusus agar tidak mencampur tanggung jawab.
4. Satu database utama menjadi sumber data aplikasi. Data untuk training ML disiapkan melalui pipeline khusus, bukan otomatis melatih semua data.
5. Struktur kode menggunakan modular architecture yang terinspirasi MVC Laravel, tanpa harus meniru struktur Laravel secara persis.

## 2. Hak Akses

### Masyarakat tanpa login

- Melihat dashboard monitoring publik.
- Menjelajahi katalog dan detail dataset yang diterbitkan.
- Melihat informasi wilayah, fasilitas, dan pembangunan yang tersedia.
- Melihat grafik dan insight yang didukung data.

### Masyarakat dengan akun

- Seluruh akses publik.
- Mengirim feedback dan masukan.
- Melihat riwayat serta status feedback miliknya.
- Menggunakan chatbot RAG.

### Admin

Satu role Admin dengan akses penuh terhadap fungsi administrasi:

- Mengelola master data.
- Mengimpor dan mengoreksi dataset.
- Memvalidasi serta menerbitkan data.
- Mengelola feedback masyarakat.
- Mengelola sumber pengetahuan chatbot.
- Melihat riwayat perubahan dan aktivitas administrasi.

Semua operasi yang mengubah data harus divalidasi dan dilindungi dengan otorisasi di backend, bukan hanya disembunyikan pada frontend.

## 3. Modul Backend

### 3.1 Authentication & Authorization

Tanggung jawab:
- Registrasi masyarakat.
- Login dan logout.
- Pengelolaan sesi atau token autentikasi.
- Pemeriksaan hak akses Admin.
- Perlindungan endpoint berdasarkan role.

### 3.2 Region Management

Mengelola referensi wilayah administratif.

Fitur:
- Tambah dan edit wilayah.
- Import data wilayah.
- Pencarian dan filter.
- Validasi hierarki wilayah.
- Pencegahan duplikasi.

### 3.3 Facility Management

Mengelola data fasilitas publik.

Fitur:
- Tambah fasilitas secara manual.
- Import dari dataset.
- Edit data dan simpan riwayat perubahan.
- Menghubungkan fasilitas dengan wilayah.
- Menyimpan kategori, lokasi, sumber, dan atribut yang tersedia.
- Draft, validasi, publikasi, dan arsip.

### 3.4 Development Project Management

Mengelola informasi proyek pembangunan.

Fitur:
- Tambah proyek manual atau melalui import.
- Menghubungkan proyek dengan wilayah.
- Mencatat instansi, periode, sumber, dan status jika tersedia.
- Menyimpan progres hanya jika didukung data yang dapat dipertanggungjawabkan.
- Validasi, publikasi, koreksi, dan arsip.

### 3.5 Dataset Management

Mengelola sumber data lintas topik.

Fitur:
- Import CSV dan XLSX sebagai cakupan awal.
- Preview data sebelum disimpan.
- Pemetaan kolom.
- Validasi format dan nilai wajib.
- Pemeriksaan duplikasi.
- Pencatatan sumber dan periode dataset.
- Riwayat proses import.
- Pengelolaan draft dan publikasi.

Integrasi API eksternal otomatis dapat ditambahkan pada tahap lanjutan.

### 3.6 Publication & Data Versioning

Aturan yang telah ditetapkan:

- Data baru disimpan sebagai draft.
- Data divalidasi sebelum diterbitkan.
- Koreksi pada data terbit membuat versi baru atau revisi yang tercatat.
- Versi publik lama tetap tersedia sampai versi koreksi divalidasi dan diterbitkan.
- Data tidak valid dapat ditarik dari publikasi tanpa menghapus riwayat audit.

### 3.7 Public Monitoring

Menyediakan data yang sudah diterbitkan untuk dashboard publik.

Fitur:
- Ringkasan indikator.
- Katalog dan detail dataset.
- Filter wilayah dan kategori.
- Grafik, tabel, dan peta jika data lokasi memadai.
- Insight berdasarkan indikator yang tersedia.

Endpoint publik hanya boleh mengembalikan data yang berstatus terbit dan tidak memuat informasi privat.

### 3.8 Feedback Management

Fitur:
- Masyarakat mengirim feedback setelah login.
- Masyarakat melihat riwayat dan status feedback miliknya.
- Admin meninjau, memberikan tanggapan, dan memperbarui status.
- Sistem mencatat perubahan status dan tanggapan.
- Data pribadi pelapor hanya dapat diakses sesuai kewenangan.

Status awal yang diusulkan: Dikirim, Ditinjau, Ditindaklanjuti, dan Selesai. Status penolakan beserta alasannya perlu ditetapkan dalam spesifikasi final.

### 3.9 RAG Service

Tanggung jawab layanan RAG:
- Mengelola dokumen dan sumber pengetahuan yang disetujui.
- Memproses dokumen menjadi potongan teks dan embedding.
- Mengelola indeks pencarian.
- Mengambil sumber relevan untuk menjawab pertanyaan.
- Menghasilkan jawaban beserta referensi sumber.
- Menyatakan keterbatasan ketika sumber tidak mencukupi.

Chatbot memerlukan login sesuai keputusan produk.

### 3.10 Machine Learning Service

Fungsi ML belum ditentukan.

Tahapan:
1. Audit dataset.
2. Menentukan masalah yang layak diselesaikan dengan ML.
3. Menentukan target, fitur, dan kebutuhan data.
4. Melakukan preprocessing.
5. Melatih dan mengevaluasi model.
6. Menyediakan model melalui API jika hasil evaluasinya layak.

Data training dapat berasal dari subset database utama, dataset historis tambahan, atau gabungan dataset. Tidak semua data harus digunakan untuk training.

## 4. Struktur Proyek FastAPI

Struktur awal yang disarankan:

```text
backend/
├── app/
│   ├── main.py
│   ├── core/
│   │   ├── config.py
│   │   ├── security.py
│   │   └── exceptions.py
│   ├── db/
│   │   ├── session.py
│   │   └── base.py
│   ├── modules/
│   │   ├── auth/
│   │   ├── users/
│   │   ├── regions/
│   │   ├── facilities/
│   │   ├── projects/
│   │   ├── datasets/
│   │   ├── publications/
│   │   ├── feedback/
│   │   └── audit_logs/
│   ├── common/
│   │   ├── pagination.py
│   │   └── responses.py
│   └── tests/
├── migrations/
├── requirements.txt
├── .env.example
└── README.md
```

Setiap modul domain dapat memiliki:
- `router.py`: definisi endpoint.
- `schemas.py`: validasi request dan response.
- `models.py`: representasi tabel database.
- `service.py`: business logic.
- `repository.py`: operasi akses data jika pemisahan ini diperlukan.

Struktur ini bersifat modular. Detail folder dan penggunaan repository dapat disesuaikan ketika implementasi dimulai.

RAG dan ML dapat berada di service Python terpisah dengan dependensi masing-masing.

## 5. Prinsip Database

Database utama menyimpan data aplikasi dan sumber data yang sudah dikelola.

Kelompok entitas awal:
- Pengguna dan role.
- Wilayah administratif.
- Kategori dan sumber data.
- Dataset serta riwayat import.
- Data fasilitas.
- Data proyek pembangunan.
- Versi data dan publikasi.
- Feedback dan riwayat status.
- Audit log.

Tabel detail, foreign key, serta relasi final ditentukan melalui ERD setelah alur bisnis dikunci.

Untuk RAG, penyimpanan dokumen, potongan teks, embedding, dan metadata dapat dirancang sesuai strategi retrieval. Untuk ML, artefak model dan hasil evaluasi dikelola terpisah dari tabel operasional utama.

## 6. Urutan Implementasi

1. Audit dataset DKI Jakarta dan tetapkan kandidat dataset MVP.
2. Finalisasi alur bisnis dan ERD.
3. Buat fondasi FastAPI, konfigurasi environment, koneksi database, dan migrasi.
4. Implementasikan autentikasi dan otorisasi.
5. Implementasikan master wilayah.
6. Implementasikan fasilitas dan pembangunan.
7. Implementasikan import, validasi, versioning, dan publikasi.
8. Implementasikan endpoint monitoring publik.
9. Implementasikan feedback dan audit log.
10. Integrasikan RAG.
11. Tentukan dan implementasikan ML setelah audit dataset.
12. Lakukan pengujian integrasi, keamanan, dan kualitas data.

## 7. Keputusan yang Sudah Disepakati

- Masyarakat dapat melihat informasi publik tanpa login.
- Feedback dan chatbot memerlukan login.
- Admin hanya memiliki satu role dengan akses penuh.
- Master data terdiri atas wilayah, fasilitas publik, dan pembangunan.
- Fasilitas dan pembangunan dapat ditambahkan manual maupun diimpor.
- Data import dapat dikoreksi, tetapi sumber asli dan riwayat perubahan tetap disimpan.
- Data harus melalui draft, validasi, dan publikasi.
- ML tidak melatih seluruh database secara otomatis.
- Fungsi ML dan algoritmanya ditentukan setelah audit dataset.
- Next.js menggunakan arsitektur modular yang terinspirasi MVC; FastAPI menjadi backend utama.

## 8. Hal yang Masih Perlu Diputuskan

- Dataset resmi yang benar-benar digunakan untuk MVP.
- Detail status feedback dan kebijakan penolakan.
- Strategi autentikasi dan pengelolaan sesi antara Next.js dan FastAPI.
- Skema versioning dan rollback data yang terbit.
- Sumber pengetahuan serta kebijakan sitasi chatbot.
- Use case ML berdasarkan hasil audit dataset.
- Skema ERD dan kontrak API final.
