<?php

namespace App\Imports;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockImport implements SkipsOnError, ToModel, WithHeadingRow
{
    use SkipsErrors;

    public function model(array $row): Model|array|null
    {
        if (empty($row['symbol']) || empty($row['name'])) {
            return null;
        }

        return new Stock([
            'asset_id' => $row['asset_id'] ?: (string) Str::uuid(),
            'symbol' => $row['symbol'],
            'isin' => $this->text($row['isin'] ?? null),
            'name' => $row['name'],
            'arabic_name' => $this->text($row['arabic_name'] ?? null),
            'description' => $this->text($row['description'] ?? null),
            'market' => $this->text($row['market'] ?? null) ?: 'egypt',
            'exchange' => $this->text($row['exchange'] ?? null),
            'asset_class' => $this->text($row['asset_class'] ?? null) ?: 'STOCK',
            'industry' => $this->text($row['industry'] ?? null),
            'reuters_symbol' => $this->text($row['reuters_symbol'] ?? null),
            'logo' => $this->text($row['logo'] ?? null),
            'is_tradable' => $this->flag($row['is_tradable'] ?? null),
            'is_visible' => $this->flag($row['is_visible'] ?? null),
            'is_otc' => $this->flag($row['is_otc'] ?? null),
            'is_right' => $this->flag($row['is_right'] ?? null),
            'is_ipo' => $this->flag($row['is_ipo'] ?? null),
            'is_same_day' => $this->flag($row['is_same_day'] ?? null),
            'is_sharia_compliant' => $this->flag($row['is_sharia_compliant'] ?? null),
            'is_egx30' => $this->flag($row['is_egx30'] ?? null),
            'is_egx70' => $this->flag($row['is_egx70'] ?? null),
            'is_egx100' => $this->flag($row['is_egx100'] ?? null),
        ]);
    }

    /**
     * فاضي يصير NULL مش نص فاضي — حقل isin فريد، فلو اتخزّن '' في صفين
     * تانيتعبّط على الـ unique. وحقول market/asset_class Required فبتفضل
     * فاضية بدل ما تطلع NULL.
     */
    protected function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === '' || $value === null) ? null : (string) $value;
    }

    /** الملف بيوصل الـ booleans كأرقام 1/0، وده بيوصلها كـ true/false */
    protected function flag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
