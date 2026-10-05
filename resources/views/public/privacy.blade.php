@extends('layouts.public')

@section('title', 'Kebijakan Privasi')
@section('heading', 'Kebijakan Privasi')
@section('subtitle', 'Terakhir diperbarui: ' . now()->locale('id')->translatedFormat('d F Y'))

@section('meta_description', 'Kebijakan Privasi GaweTracker: bagaimana kami mengakses, menggunakan, menyimpan, dan melindungi data Anda, termasuk data dari Gmail.')

@section('content')
    <p>
        Kebijakan Privasi ini menjelaskan bagaimana <strong>GaweTracker</strong> ("kami")
        mengumpulkan, menggunakan, menyimpan, dan melindungi informasi Anda saat menggunakan
        layanan di <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>.
    </p>

    <h2>1. Data yang kami kumpulkan</h2>
    <ul>
        <li><strong>Data akun:</strong> nama, alamat email, dan kata sandi (disimpan dalam bentuk hash) yang Anda berikan saat mendaftar.</li>
        <li><strong>Data lamaran kerja:</strong> catatan lamaran, status, riwayat perubahan, jadwal interview, checklist, dan penawaran kerja yang Anda masukkan.</li>
        <li><strong>Data kotak masuk (opsional):</strong> bila Anda menghubungkan Gmail, kami membaca email tertentu seperti dijelaskan di bagian 2.</li>
    </ul>

    <h2>2. Akses ke Gmail (opsional)</h2>
    <p>
        Anda dapat menghubungkan akun Gmail Anda untuk mengaktifkan pembaruan status lamaran secara
        otomatis. Jika Anda melakukannya:
    </p>
    <ul>
        <li>Kami meminta izin <strong>hanya-baca</strong> (<code>gmail.readonly</code>). Kami <strong>tidak dapat</strong> mengirim, menghapus, atau mengubah email Anda.</li>
        <li>Kami <strong>hanya membaca</strong> email dari pengirim papan kerja yang didukung (misalnya <code>jobstreet.com</code> dan <code>glints.com</code>). Email lain tidak diproses.</li>
        <li>Kami menyimpan <strong>token akses</strong> dan <strong>token penyegar</strong> (refresh token) secara terenkripsi untuk menjaga koneksi, serta alamat Gmail yang terhubung.</li>
        <li>Kami menyimpan cuplikan email yang relevan (subjek, pengirim, tanggal, dan potongan isi) untuk mencocokkannya dengan lamaran Anda.</li>
        <li>Anda dapat <strong>memutuskan koneksi kapan saja</strong> melalui tombol "Gmail terhubung" di aplikasi. Setelah diputuskan, token dihapus dan kami berhenti membaca email Anda.</li>
    </ul>
    <p>
        Penggunaan dan pengalihan informasi yang diterima dari Google API oleh GaweTracker akan
        mematuhi <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>,
        termasuk persyaratan <em>Limited Use</em>. Kami tidak menggunakan data Gmail Anda untuk
        periklanan, tidak menjualnya, dan tidak menggunakannya untuk melatih model kecerdasan
        buatan yang bersifat umum.
    </p>

    <h2>3. Cara kami menggunakan data</h2>
    <ul>
        <li>Menyediakan dan mengoperasikan fitur pelacakan lamaran kerja.</li>
        <li>Memperbarui status lamaran Anda secara otomatis berdasarkan email papan kerja.</li>
        <li>Menampilkan analitik pribadi mengenai progres lamaran Anda.</li>
    </ul>

    <h2>4. Penyimpanan dan keamanan</h2>
    <p>
        Data disimpan pada server yang kami kelola. Token Gmail disimpan dalam bentuk terenkripsi,
        dan kata sandi disimpan sebagai hash. Akses ke data dibatasi hanya untuk akun Anda sendiri.
    </p>

    <h2>5. Berbagi data</h2>
    <p>
        Kami <strong>tidak menjual</strong> data Anda dan tidak membagikannya kepada pihak ketiga
        untuk tujuan pemasaran. Kami hanya dapat mengungkapkan data bila diwajibkan oleh hukum yang
        berlaku.
    </p>

    <h2>6. Penyimpanan dan penghapusan data</h2>
    <p>
        Kami menyimpan data Anda selama akun Anda aktif. Anda dapat meminta penghapusan seluruh data
        akun dan data Gmail Anda kapan saja dengan menghubungi kami di alamat di bawah. Kami akan
        memproses permintaan tersebut dalam waktu yang wajar.
    </p>

    <h2>7. Perubahan kebijakan</h2>
    <p>
        Kami dapat memperbarui kebijakan ini dari waktu ke waktu. Perubahan akan ditampilkan pada
        halaman ini beserta tanggal pembaruan terbaru.
    </p>

    <h2>8. Keterbukaan implementasi</h2>
    <p>
        Untuk keterbukaan, kode sumber GaweTracker tersedia untuk ditinjau sehingga Anda dapat
        memverifikasi bagaimana data Google Anda diakses dan diproses:
        @if (config('app.source_url'))
            <a href="{{ config('app.source_url') }}" target="_blank" rel="noopener">{{ config('app.source_url') }}</a>.
        @else
            tersedia atas permintaan melalui alamat kontak di bawah.
        @endif
    </p>

    <h2>9. Kontak</h2>
    <p>
        Pertanyaan mengenai privasi atau permintaan penghapusan data dapat dikirim ke
        <a href="mailto:{{ config('app.contact_email') }}">{{ config('app.contact_email') }}</a>.
    </p>
@endsection
