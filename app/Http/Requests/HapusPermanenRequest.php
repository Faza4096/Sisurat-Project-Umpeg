<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Konfirmasi hapus permanen: nomor surat harus diketik persis. Dipakai untuk
 * surat masuk (nomor agenda) dan surat keluar (nomor surat).
 */
class HapusPermanenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'konfirmasi' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'konfirmasi.required' => 'Ketik nomor surat untuk mengonfirmasi penghapusan.',
            'konfirmasi.string' => 'Nomor surat harus berupa teks.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('konfirmasi')) {
                return;
            }

            $surat = $this->route('suratMasuk') ?? $this->route('suratKeluar');
            $nomor = $surat?->no_agenda ?? $surat?->no_surat;

            if ($nomor === null || trim((string) $this->input('konfirmasi')) !== $nomor) {
                $validator->errors()->add(
                    'konfirmasi',
                    'Nomor yang diketik tidak sama dengan nomor surat. Salin persis dari kolom di atas.',
                );
            }
        }];
    }
}
