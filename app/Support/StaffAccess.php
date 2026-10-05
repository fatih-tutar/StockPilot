<?php

namespace App\Support;

class StaffAccess
{
    /**
     * Legacy permission string indexes, in the old screen's order.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public static function flags(): array
    {
        return [
            ['key' => 'purchase', 'label' => 'Alış'],
            ['key' => 'factory', 'label' => 'Fabrika'],
            ['key' => 'quotes', 'label' => 'Teklif'],
            ['key' => 'orders', 'label' => 'Sipariş'],
            ['key' => 'editing', 'label' => 'Düzenleme'],
            ['key' => 'movements', 'label' => 'İşlemler'],
            ['key' => 'cashflow', 'label' => 'Gelen giden'],
            ['key' => 'sale_price', 'label' => 'Satış'],
            ['key' => 'totals', 'label' => 'Toplam görme'],
            ['key' => 'visits', 'label' => 'Ziyaretler'],
            ['key' => 'shipments', 'label' => 'Sevkiyat'],
            ['key' => 'piece_quantity', 'label' => 'Adet'],
            ['key' => 'pallet_quantity', 'label' => 'Palet'],
            ['key' => 'alkop', 'label' => 'Alkop'],
            ['key' => 'office', 'label' => 'Ofis'],
            ['key' => 'vehicles', 'label' => 'Araçlar'],
            ['key' => 'count_report', 'label' => 'Sayım raporu'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::flags(), 'key');
    }

    /**
     * @return array<string, bool>
     */
    public static function fromLegacy(?string $permissions): array
    {
        $parts = array_pad(explode(',', (string) $permissions), count(self::keys()), '0');
        $flags = [];

        foreach (self::keys() as $index => $key) {
            $flags[$key] = trim((string) ($parts[$index] ?? '0')) === '1';
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public static function fromInput(array $input): array
    {
        $flags = [];

        foreach (self::keys() as $key) {
            $flags[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $flags;
    }
}
