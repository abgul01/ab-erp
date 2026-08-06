<?php

namespace App\Support;

use App\Exceptions\BizException;
use Illuminate\Support\Facades\DB;

/**
 * Satu pintu untuk pertanyaan "part ini sudah boleh diproduksi massal atau belum".
 *
 * Dikumpulkan di sini, bukan disebar sebagai `where('lifecycle', 'MASSPRO')` di
 * belasan query, karena penjagaan yang tersebar adalah penjagaan yang cepat atau
 * lambat terlewat di satu tempat — dan satu tempat yang terlewat sudah cukup
 * untuk membuat part yang belum lulus uji masuk ke rencana produksi.
 *
 * Pesannya sengaja menyebut part mana yang menghalangi. "Item tidak valid" akan
 * membuat orang menebak; menyebut kodenya membuat mereka tahu harus ke mana.
 */
class ItemLifecycle
{
    public const TRIAL = 'TRIAL';

    public const MASSPRO = 'MASSPRO';

    public const OBSOLETE = 'OBSOLETE';

    public const ALL = [self::TRIAL, self::MASSPRO, self::OBSOLETE];

    /**
     * Tolak kalau ada part yang belum lulus uji coba.
     *
     * @param  array<int, int|string>|int  $itemIds
     * @param  string  $context  disebut dalam pesan, mis. "rencana produksi"
     */
    public static function assertMassPro(array|int $itemIds, string $context): void
    {
        $ids = array_filter((array) $itemIds);

        if (empty($ids)) {
            return;
        }

        $blocked = DB::table('m_item')
            ->whereIn('id', $ids)
            ->where('lifecycle', '<>', self::MASSPRO)
            ->get(['code', 'part_name', 'lifecycle']);

        if ($blocked->isEmpty()) {
            return;
        }

        $trial = $blocked->where('lifecycle', self::TRIAL);
        $obsolete = $blocked->where('lifecycle', self::OBSOLETE);

        $parts = [];
        if ($trial->isNotEmpty()) {
            $parts[] = 'masih uji coba: '.$trial->map(fn ($i) => $i->code)->implode(', ')
                .' — part baru dapat masuk '.$context.' setelah diserahterimakan ke produksi';
        }
        if ($obsolete->isNotEmpty()) {
            $parts[] = 'sudah tidak diproduksi: '.$obsolete->map(fn ($i) => $i->code)->implode(', ');
        }

        throw BizException::make('ITEM_NOT_MASSPRO', ucfirst($context).' ditolak — '.implode('; ', $parts).'.');
    }

    /** Kebalikannya: memastikan sebuah part memang masih berstatus uji coba. */
    public static function isTrial(int $itemId): bool
    {
        return DB::table('m_item')->where('id', $itemId)->value('lifecycle') === self::TRIAL;
    }

    /**
     * Naikkan part ke produksi massal.
     *
     * Inilah arti serah terima yang sesungguhnya: sebelum ini part tidak bisa
     * direncanakan, dijual, atau dipesan pelanggan — sesudahnya bisa. Karena itu
     * yang boleh menekannya adalah SPV, bukan pembuat proyeknya.
     */
    public static function graduate(int $itemId): void
    {
        DB::table('m_item')->where('id', $itemId)->update([
            'lifecycle' => self::MASSPRO,
            'active' => 1,
            'updated_at' => now(),
        ]);
    }
}
