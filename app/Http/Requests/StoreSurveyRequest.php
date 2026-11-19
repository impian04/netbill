<?php

namespace App\Http\Requests;

use App\Enums\SurveyResult;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Survey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSurveyor() || $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'customer_id'       => ['required', 'integer', 'exists:customers,id'],
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
            'customer_id.required'         => 'Pelanggan wajib dipilih.',
            'customer_id.exists'           => 'Pelanggan tidak valid.',
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
            $user = $this->user();
            $customerId = $this->input('customer_id');
            $customer = Customer::find($customerId);

            if (! $customer) {
                return;
            }

            // Tenant scoping: surveyor hanya boleh survei pelanggan di POP miliknya
            if ($user->isSurveyor() && (int) $customer->pop_id !== (int) $user->pop_id) {
                $validator->errors()->add('customer_id', 'Anda tidak berhak mengakses pelanggan di luar POP Anda.');
                return;
            }

            // Pelanggan harus belum memiliki survei
            if (Survey::where('customer_id', $customer->id)->exists()) {
                $validator->errors()->add('customer_id', 'Pelanggan ini sudah disurvei oleh surveyor lain.');
                return;
            }

            $popId = $customer->pop_id;

            // Validasi ODC & ODP jika diisi
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

