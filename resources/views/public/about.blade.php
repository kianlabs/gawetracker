@extends('layouts.public')

@section('title', 'Tentang')
@section('heading', 'Tentang GaweTracker')
@section('subtitle', 'Pelacak lamaran kerja pribadi — catat, kelola, dan pantau status lamaran Anda di satu tempat.')

@section('meta_description', 'GaweTracker adalah pelacak lamaran kerja pribadi untuk mencatat dan memantau status lamaran ke JobStreet, Glints, dan lainnya.')

@section('content')
    <p>
        <strong>GaweTracker</strong> membantu Anda mengelola proses mencari kerja tanpa spreadsheet.
        Semua lamaran, jadwal interview, checklist, dan penawaran kerja tersimpan rapi dan hanya
        dapat diakses oleh akun Anda sendiri.
    </p>

    <h2>Apa yang bisa dilakukan</h2>
    <ul>
        <li>Mencatat lamaran kerja beserta statusnya: wishlist, dilamar, screening, interview, offer, diterima, atau ditolak.</li>
        <li>Melihat seluruh lamaran dalam tampilan papan Kanban dan daftar.</li>
        <li>Mencatat riwayat perubahan status, catatan interview, dan membandingkan penawaran kerja.</li>
        <li>Melihat analitik sederhana: jumlah lamaran, tingkat respons, dan progres mingguan.</li>
        <li>Mencari lowongan dari papan kerja eksternal seperti Glints dan JobStreet.</li>
    </ul>

    <h2>Pembaruan status otomatis dari email</h2>
    <p>
        Secara opsional, Anda dapat menghubungkan kotak masuk Gmail Anda agar GaweTracker membaca
        email notifikasi dari JobStreet dan Glints, lalu memperbarui status lamaran secara otomatis.
        Fitur ini <strong>hanya membaca</strong> email dari pengirim papan kerja tersebut, tidak
        pernah mengirim atau menghapus email, dan dapat diputuskan kapan saja. Selengkapnya lihat
        <a href="{{ route('privacy') }}">Kebijakan Privasi</a>.
    </p>

    <h2>Privasi</h2>
    <p>
        Data lamaran Anda bersifat pribadi dan terpisah per akun. Kami tidak menjual data Anda dan
        tidak membagikannya kepada pihak ketiga. Rincian lengkap tersedia di
        <a href="{{ route('privacy') }}">Kebijakan Privasi</a>.
    </p>

    <h2>Kontak</h2>
    <p>
        Ada pertanyaan atau masukan? Hubungi kami di
        <a href="mailto:{{ config('app.contact_email') }}">{{ config('app.contact_email') }}</a>.
    </p>
@endsection
