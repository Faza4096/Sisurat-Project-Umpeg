<?php

return [

    /*
    | Nilai awal identitas instansi. Sumber yang dipakai halaman adalah baris
    | tabel pengaturan_instansi (menu Pengaturan Instansi); nilai di sini hanya
    | dipakai saat baris itu belum ada atau kolomnya dikosongkan.
    */
    'instansi' => [
        'nama' => env('INSTANSI_NAMA', 'Pemerintah Kabupaten Bogor'),
        'unit' => env('INSTANSI_UNIT', 'Kecamatan Ciomas'),
    ],

];
