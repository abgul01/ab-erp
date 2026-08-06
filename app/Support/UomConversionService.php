<?php

namespace App\Support;

/**
 * Dual UoM conversion for pipe RM (length mm + weight kg).
 * Actual per-serial ratio (kg_per_mm = weight / length) rather than a
 * master conversion factor. Master factors are for PO estimation & MRP only.
 *
 * LLD §4.5
 */
class UomConversionService
{
    /**
     * kg per mm for a given serial.
     */
    public function kgPerMm(float $weight, float $length): float
    {
        return $length > 0 ? $weight / $length : 0;
    }

    /**
     * Proportional kg used given consumed length.
     */
    public function usedKg(float $lengthUsed, float $serialWeight, float $serialLength): float
    {
        return $serialLength > 0
            ? round($serialWeight * ($lengthUsed / $serialLength), 4)
            : $serialWeight;
    }

    /**
     * Proportional kg remaining.
     */
    public function remKg(float $lengthRem, float $serialWeight, float $serialLength): float
    {
        return $serialLength > 0
            ? round($serialWeight * ($lengthRem / $serialLength), 4)
            : 0;
    }

    /**
     * Convert kg to metric tonnes (for quota booking).
     */
    public function kgToTon(float $kg): float
    {
        return $kg / 1000;
    }

    /**
     * Average unit weight from total weight and quantity.
     */
    public function unitWeight(float $totalWeight, int $qty): ?float
    {
        return $qty > 0 ? round($totalWeight / $qty, 2) : null;
    }
}
