@extends('layouts.public')

@section('title', 'Ketentuan Layanan')
@section('heading', 'Ketentuan Layanan')
@section('subtitle', 'Terakhir diperbarui: ' . date('d F Y'))

@section('meta_description', 'Ketentuan Layanan penggunaan GaweTracker, pelacak lamaran kerja pribadi.')

@section('content')
    <p>
        Dengan menggunakan <strong>GaweTracker</strong> ("layanan"), Anda menyetujui ketentuan
        berikut. Mohon baca dengan saksama.
    </p>

    <h2>1. Penerimaan ketentuan</h2>
    <p>
        Dengan membuat akun atau menggunakan layanan, Anda menyatakan telah membaca, memahami, dan
        menyetujui Ketentuan Layanan ini.
    </p>

    <h2>2. Akun Anda</h2>
    <ul>
        <li>Anda bertanggung jawab menjaga kerahasiaan kredensial akun Anda.</li>
        <li>Anda bertanggung jawab atas seluruh aktivitas yang terjadi melalui akun Anda.</li>
        <li>Anda harus memberikan informasi yang akurat saat mendaftar.</li>
    </ul>

    <h2>3. Penggunaan yang wajar</h2>
    <p>Anda setuju untuk tidak:</p>
    <ul>
        <li>Menggunakan layanan untuk tujuan melanggar hukum.</li>
        <li>Mengakses data milik pengguna lain tanpa izin.</li>
        <li>Mengganggu, membebani berlebihan, atau mencoba merusak layanan.</li>
        <li>Menghubungkan kotak masuk email yang bukan milik Anda.</li>
    </ul>

    <h2>4. Hubungan dengan pihak ketiga</h2>
    <p>
        Layanan dapat membaca email notifikasi dari papan kerja pihak ketiga (seperti JobStreet dan
        Glints) dan menampilkan lowongan dari sumber tersebut. Kami tidak berafiliasi dengan
        papan kerja mana pun. Ketersediaan dan keakuratan data pihak ketiga sepenuhnya menjadi
        tanggung jawab penyedianya.
    </p>

    <h2>5. Ketersediaan layanan</h2>
    <p>
        Layanan disediakan "sebagaimana adanya". Kami berupaya menjaga ketersediaan, namun tidak
        menjamin layanan bebas gangguan atau kesalahan. Kami dapat mengubah atau menghentikan
        layanan sewaktu-waktu.
    </p>

    <h2>6. Batasan tanggung jawab</h2>
    <p>
        Sejauh diizinkan hukum, kami tidak bertanggung jawab atas kerugian tidak langsung atau
        konsekuensial yang timbul dari penggunaan layanan, termasuk keputusan karier yang Anda buat
        berdasarkan informasi di dalamnya.
    </p>

    <h2>7. Penghentian</h2>
    <p>
        Anda dapat berhenti menggunakan layanan kapan saja. Kami dapat menangguhkan atau menghentikan
        akses bila terjadi pelanggaran terhadap ketentuan ini.
    </p>

    <h2>8. Perubahan ketentuan</h2>
    <p>
        Kami dapat memperbarui ketentuan ini dari waktu ke waktu. Versi terbaru akan selalu
        ditampilkan pada halaman ini.
    </p>

    <h2>9. Kontak</h2>
    <p>
        Pertanyaan mengenai ketentuan ini dapat dikirim ke
        <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
    </p>
@endsection
