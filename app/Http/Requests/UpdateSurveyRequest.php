<?php

namespace App\Http\Requests;

use App\Enums\SurveyResult;
use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $survey = $this->route('survey');
        $user = $this->user();

        if (! $survey || ! $user) {
            return false;
        }

        return $survey->isEditableBy($user);
    }

    public function rules(): array
    {
        return [
            'odc_id'            => ['nullable', 'integer', 'exists:odcs,id'],
            'odp_id'            => ['nullable', 'integer', 'exists:odps,id'],
            'coordinates'       => ['nullable', 'string', 'regex:/^-?\d{1,3}\.\d+,\s*-?\d{1,3}\.\d+$/'],
            'latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            'result'            => ['required', Rule::enum(SurveyResult::class)],
            'result_note'       => ['nullable', 'string', 'required_if:result,NOT_FEASIBLE', 'max:1000'],
            'items'             => ['nullable', 'array', 'max:30'],
            'items.*.material_id' => ['required', 'integer', 'exists:materials,id', 'distinct'],
            'items.*.quantity'  => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'coordinates.regex'            => 'Format koordinat tidak valid. Gunakan format "latitude, longitude".',
            'result.required'              => 'Hasil survei wajib dipilih.',
            'result_note.required_if'      => 'Catatan hasil survei wajib diisi jika hasil Tidak Layak.',
            'items.max'                    => 'Daftar barang survei maksimal 30 item.',
            'items.*.material_id.distinct' => 'Barang survei tidak boleh dipilih lebih dari sekali.',
            'items.*.quantity.min'         => 'Jumlah barang harus lebih besar dari 0.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $survey = $this->route('survey');
            if (! $survey) {
                return;
            }

            if (! $survey->status->isEditableBySurveyor()) {
                $validator->errors()->add('status', "Survei tidak dapat diubah karena status saat ini {$survey->status->label()}.");
                return;
            }

            $popId = $survey->pop_id;

            // Validasi ODC & ODP
            $odcId = $this->input('odc_id');
            $odpId = $this->input('odp_id');

            if ($odcId) {
                $odc = Odc::find($odcId);
                if (! $odc || (int) $odc->pop_id !== (int) $popId) {
                    $validator->errors()->add('odc_id', 'ODC yang dipilih tidak valid untuk POP pelanggan.');
                }
            }

            if ($odpId) {
                $odp = Odp::find($odpId);
                if (! $odp || (int) $odp->pop_id !== (int) $popId) {
                    $validator->errors()->add('odp_id', 'ODP yang dipilih tidak valid untuk POP pelanggan.');
                } elseif ($odcId && (int) $odp->odc_id !== (int) $odcId) {
                    $validator->errors()->add('odp_id', 'ODP yang dipilih bukan milik ODC yang dipilih.');
                }
            }

            // Validasi barang jika FEASIBLE
            $result = $this->input('result');
            $items = $this->input('items', []);

            if ($result === SurveyResult::FEASIBLE->value || $result === SurveyResult::FEASIBLE) {
                if (empty($items) || ! is_array($items)) {
                    $validator->errors()->add('items', 'Daftar barang survei wajib diisi minimal 1 item jika hasil survei Layak.');
                }
            }

            if (! empty($items) && is_array($items)) {
                $materialIds = array_column($items, 'material_id');
                $invalidMaterials = Material::whereIn('id', $materialIds)
                    ->where('pop_id', '!=', $popId)
                    ->count();

                if ($invalidMaterials > 0) {
                    $validator->errors()->add('items', 'Beberapa barang yang dipilih bukan milik POP pelanggan.');
                }
            }
        });
    }
}

