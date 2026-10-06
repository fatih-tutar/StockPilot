<?php

namespace App\Support;

use App\Enums\LeaveStatus;
use App\Models\Leave;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\Validator;

class LeaveRules
{
    public static function dayCount(CarbonInterface $start, CarbonInterface $return): int
    {
        return (int) $start->copy()->startOfDay()->diffInDays($return->copy()->startOfDay());
    }

    public static function entitlement(?CarbonInterface $hiredOn, CarbonInterface $on): int
    {
        if ($hiredOn === null) {
            return 0;
        }

        $years = (int) $hiredOn->copy()->startOfDay()->diffInYears($on->copy()->startOfDay());

        return match (true) {
            $years >= 15 => 26,
            $years >= 5 => 20,
            $years >= 1 => 14,
            default => 0,
        };
    }

    public static function usedDays(int $userId, int $year, ?int $excludeId = null): int
    {
        return (int) Leave::query()
            ->where('user_id', $userId)
            ->where('status', LeaveStatus::Approved)
            ->whereYear('start_on', $year)
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->sum('leave_days');
    }

    public static function withinEntryWindow(CarbonInterface $today): bool
    {
        $opening = $today->copy()->startOfYear();
        $closing = $today->copy()->setDate($today->year, 3, 31)->endOfDay();

        return $today->between($opening, $closing);
    }

    public static function assertBookable(
        Validator $validator,
        User $actor,
        User $subject,
        bool $inOffice,
        ?int $companyId,
        CarbonInterface $start,
        CarbonInterface $return,
        ?int $excludeId = null,
    ): void {
        if ($actor->company_id !== null && $subject->company_id !== $actor->company_id) {
            $validator->errors()->add('user_id', 'Bu personel sizin firmanıza ait değil.');

            return;
        }

        $days = self::dayCount($start, $return);

        if ($days < 1) {
            $validator->errors()->add('return_on', 'İşe dönüş tarihi başlangıçtan sonra olmalıdır.');

            return;
        }

        if ($days > 14) {
            $validator->errors()->add('return_on', 'Tek seferde en fazla 14 günlük izin girebilirsiniz.');
        }

        if (! $actor->can('leaves.manage') && ! self::withinEntryWindow(today())) {
            $validator->errors()->add(
                'start_on',
                'Sadece 1 Ocak ile 31 Mart tarihleri arasında izin girişi yapabilirsiniz. Bu tarihler dışında lütfen yöneticinizle iletişime geçiniz.',
            );
        }

        if (! $actor->can('leaves.manage') && ! self::hasHundredDayGap($subject->id, $start, $return, $excludeId)) {
            $validator->errors()->add('start_on', 'İki izin arasında en az 100 gün olmak zorundadır.');
        }

        $remaining = self::entitlement($subject->hired_on, $start) - self::usedDays($subject->id, (int) $start->year, $excludeId);

        if ($remaining < $days) {
            $validator->errors()->add(
                'return_on',
                "Kalan izin hakkınız {$remaining} gündür. Daha fazla izin talep edemezsiniz.",
            );
        }

        if (self::overlaps($inOffice, $companyId, $start, $return, $excludeId)) {
            $validator->errors()->add(
                'start_on',
                'Sizin departmanınızda aynı tarihlerde izin alan başka bir çalışan var. Lütfen yıllık izin planından kontrol ediniz.',
            );
        }
    }

    private static function hasHundredDayGap(
        int $userId,
        CarbonInterface $start,
        CarbonInterface $return,
        ?int $excludeId,
    ): bool {
        $leaves = Leave::query()
            ->where('user_id', $userId)
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->get(['start_on', 'return_on']);

        foreach ($leaves as $leave) {
            $afterPrevious = abs($start->copy()->startOfDay()->diffInDays($leave->return_on->copy()->startOfDay()));
            $beforeNext = abs($return->copy()->startOfDay()->diffInDays($leave->start_on->copy()->startOfDay()));

            if ($afterPrevious < 100 || $beforeNext < 100) {
                return false;
            }
        }

        return true;
    }

    private static function overlaps(
        bool $inOffice,
        ?int $companyId,
        CarbonInterface $start,
        CarbonInterface $return,
        ?int $excludeId,
    ): bool {
        return Leave::query()
            ->where('in_office', $inOffice)
            ->when(
                $companyId === null,
                fn ($query) => $query->whereNull('company_id'),
                fn ($query) => $query->where('company_id', $companyId),
            )
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->whereDate('start_on', '<', $return->toDateString())
            ->whereDate('return_on', '>', $start->toDateString())
            ->exists();
    }
}
