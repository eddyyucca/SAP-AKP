<?php

namespace App\Support;

/** Matriks risiko 5x5 (config/ssap.php). */
class Risk
{
    public static function score(int $likelihood, int $consequence): int
    {
        return max(1, min(5, $likelihood)) * max(1, min(5, $consequence));
    }

    /** Kunci tingkat risiko (low, medium, high, extreme) dari skor 1-25. */
    public static function level(int $score): string
    {
        foreach (config('ssap.risk_levels') as $key => $level) {
            if ($score <= $level['max']) {
                return $key;
            }
        }

        return 'extreme';
    }

    /** @return array{label:string,max:int,color:string,bg:string,sla_days:int,action:string} */
    public static function meta(?string $level): array
    {
        $levels = config('ssap.risk_levels');

        return $levels[$level] ?? $levels['low'];
    }

    public static function slaDays(string $level): int
    {
        return self::meta($level)['sla_days'];
    }
}
