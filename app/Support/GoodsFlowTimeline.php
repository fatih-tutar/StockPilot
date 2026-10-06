<?php

namespace App\Support;

use App\Models\GoodsFlow;
use Illuminate\Support\Collection;

class GoodsFlowTimeline
{
    /**
     * @param  Collection<int, GoodsFlow>  $flows
     * @return array<int, array<string, mixed>>
     */
    public static function rows(Collection $flows, bool $daily): array
    {
        $rows = [];
        $buckets = [
            'week' => null,
            'month' => null,
            'year' => null,
        ];

        $flush = function (string $level) use (&$rows, &$buckets): void {
            if ($buckets[$level] === null) {
                return;
            }

            $rows[] = self::summary($level, $buckets[$level]);
            $buckets[$level] = null;
        };

        foreach ($flows as $flow) {
            $keys = self::keys($flow);

            foreach (['week', 'month', 'year'] as $level) {
                if ($buckets[$level] !== null && $buckets[$level]['key'] !== $keys[$level]) {
                    $flush($level);
                }
            }

            if ($daily) {
                $rows[] = self::day($flow);
            }

            foreach (['week', 'month', 'year'] as $level) {
                if ($buckets[$level] === null) {
                    $buckets[$level] = [
                        'key' => $keys[$level],
                        'label' => self::label($level, $flow),
                        'store_incoming' => 0.0,
                        'store_outgoing' => 0.0,
                        'warehouse_incoming' => 0.0,
                        'warehouse_outgoing' => 0.0,
                    ];
                }

                foreach (self::amounts($flow) as $field => $amount) {
                    $buckets[$level][$field] += $amount;
                }
            }
        }

        foreach (['week', 'month', 'year'] as $level) {
            $flush($level);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, GoodsFlow>  $flows
     * @return array<int, array<string, float|string>>
     */
    public static function chart(Collection $flows): array
    {
        $months = [];

        foreach ($flows as $flow) {
            $key = $flow->recorded_on->format('Y-m');
            if (! isset($months[$key])) {
                $months[$key] = [
                    'month' => $key,
                    'store_incoming' => 0.0,
                    'store_outgoing' => 0.0,
                    'warehouse_incoming' => 0.0,
                    'warehouse_outgoing' => 0.0,
                ];
            }

            foreach (self::amounts($flow) as $field => $amount) {
                $months[$key][$field] += $amount;
            }
        }

        ksort($months);

        return array_values(array_map(function (array $month): array {
            $month['total_incoming'] = $month['store_incoming'] + $month['warehouse_incoming'];
            $month['total_outgoing'] = $month['store_outgoing'] + $month['warehouse_outgoing'];

            return $month;
        }, $months));
    }

    public static function kilos(float|string|null $value): string
    {
        $formatted = number_format((float) $value, 3, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return ($formatted === '' ? '0' : $formatted).' kg';
    }

    /**
     * @return array{week: string, month: string, year: string}
     */
    private static function keys(GoodsFlow $flow): array
    {
        $date = $flow->recorded_on;

        return [
            'week' => $date->isoWeekYear().'-W'.$date->isoWeek(),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
        ];
    }

    private static function label(string $level, GoodsFlow $flow): string
    {
        $date = $flow->recorded_on;

        return match ($level) {
            'week' => $date->isoWeek().'. hafta '.$date->isoWeekYear(),
            'month' => self::monthName($date->month).' '.$date->year,
            'year' => $date->year.' yılı',
        };
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @return array<string, mixed>
     */
    private static function summary(string $level, array $bucket): array
    {
        return [
            'kind' => $level,
            'id' => null,
            'label' => $bucket['label'],
            'recorded_on' => null,
            'store_incoming' => self::kilos($bucket['store_incoming']),
            'store_outgoing' => self::kilos($bucket['store_outgoing']),
            'warehouse_incoming' => self::kilos($bucket['warehouse_incoming']),
            'warehouse_outgoing' => self::kilos($bucket['warehouse_outgoing']),
            'store_incoming_value' => null,
            'store_outgoing_value' => null,
            'warehouse_incoming_value' => null,
            'warehouse_outgoing_value' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function day(GoodsFlow $flow): array
    {
        return [
            'kind' => 'day',
            'id' => $flow->id,
            'label' => $flow->recorded_on->format('d.m.Y'),
            'recorded_on' => $flow->recorded_on->toDateString(),
            'store_incoming' => self::kilos($flow->store_incoming),
            'store_outgoing' => self::kilos($flow->store_outgoing),
            'warehouse_incoming' => self::kilos($flow->warehouse_incoming),
            'warehouse_outgoing' => self::kilos($flow->warehouse_outgoing),
            'store_incoming_value' => self::inputValue($flow->store_incoming),
            'store_outgoing_value' => self::inputValue($flow->store_outgoing),
            'warehouse_incoming_value' => self::inputValue($flow->warehouse_incoming),
            'warehouse_outgoing_value' => self::inputValue($flow->warehouse_outgoing),
        ];
    }

    /**
     * @return array{store_incoming: float, store_outgoing: float, warehouse_incoming: float, warehouse_outgoing: float}
     */
    private static function amounts(GoodsFlow $flow): array
    {
        return [
            'store_incoming' => (float) $flow->store_incoming,
            'store_outgoing' => (float) $flow->store_outgoing,
            'warehouse_incoming' => (float) $flow->warehouse_incoming,
            'warehouse_outgoing' => (float) $flow->warehouse_outgoing,
        ];
    }

    private static function inputValue(float|string|null $value): string
    {
        $formatted = number_format((float) $value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private static function monthName(int $month): string
    {
        return [
            1 => 'Ocak',
            2 => 'Şubat',
            3 => 'Mart',
            4 => 'Nisan',
            5 => 'Mayıs',
            6 => 'Haziran',
            7 => 'Temmuz',
            8 => 'Ağustos',
            9 => 'Eylül',
            10 => 'Ekim',
            11 => 'Kasım',
            12 => 'Aralık',
        ][$month];
    }
}
