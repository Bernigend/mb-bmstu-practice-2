<?php

/**
 * Задача о полиномиальной регрессии (аппроксимации табличной функции) требует большой аккуратности,
 * поскольку при обычных методах решения расчёт главного определителя СЛАУ (определителя Вандермонда)
 * приводит к вычитанию близких чисел и в итоге снижает относительную погрешность вычислений,
 * что проявляется уже для полиномов 8-10 порядка.
 *
 * От этих недостатков свободны методы разложения в ряды по ортогональным функциям
 * (например, разложение в ряды Фурье по косинусам), и хотелось бы так же просто обращаться с полиномами.
 * Однако классические ортогональные полиномы (Лежандра, Чебышёва и пр.) в дискретном варианте ортогональны
 * только на специально подобранных неравномерных сетках.
 *
 * Удачный выход из такой ситуации даёт малоизвестный метод ортогональной полиномиальной регрессии
 * (Orthogonal Polynomial Curve Fitting, Jeff Reid),
 * в котором сначала по абсциссам заданных точек конструируются ортогональные полиномы,
 * а затем с помощью МНК рассчитываются коэффициенты при этих полиномах.
 */


/**
 * Class Approximation.
 */
class Approximation
{
    /** @var float[] */
    protected $xPoints = [];
    /** @var float[] */
    protected $yPoints = [];
    /** @var int */
    protected $level = 1;
    /** @var float[][] */
    protected $values = [];
    /** @var float[] */
    protected $sums = [];
    /** @var float[] */
    protected $coefficients = [];

    /**
     * Approximation constructor.
     * @param array $xPoints
     * @param array $yPoints
     * @param int $level
     */
    public function __construct(
        array $xPoints,
        array $yPoints,
        int $level
    )
    {
        $this->xPoints = $xPoints;
        $this->yPoints = $yPoints;
        $this->level = $level;
    }

    public function init(): self
    {
        $this->calcValuesSums();
        $this->calcAllCoefficients();

        return $this;
    }

    protected function calcValuesSums(): void
    {
        for ($j = -1; $j < $this->level; $j++) {
            if (!isset($v))
            {
                $v = [array_fill(0, count($this->xPoints), 1.0)];
                $s = [(float)count($this->xPoints)];
                $q = [array_sum($this->xPoints)];
            }
            else
            {
                $v[] = $this->subtract($this->xPoints, end($q)/end($s));
                if ($j > 0) {
                    $v[$j+1] = $this->subtract(
                        $this->multi(end($v), prev($v)),
                        $this->multi(prev($v), end($s)/prev($s))
                    );
                }

                $sq = $this->multi(end($v), 'square');
                $s[] = array_sum($sq);
                $q[] = array_sum($this->multi($this->xPoints, $sq));
            }
        }

        $this->values = $v ?? [];
        $this->sums = [
            '&sum;P&sup2;'  => $s ?? [],
            '&sum;xP&sup2;' => $q ?? [],
        ];
    }

    private function calcAllCoefficients()
    {
        $s = reset($this->sums);
        $b = [];

        foreach ($this->values as $key => $v) {
            $b[] = array_sum($this->multi($this->yPoints, $v))/$s[$key];
        }

        $c = $this->calcOrthoCoeffs();
        $m = count($b) - 1;
        $a = array_fill(0, $m+1, 0.0);

        foreach ($a as $j => &$aj) {
            for ($k = $j; $k <= $m; $k++) {
                $aj += $b[$k] * $c[$k][$j];
            }
        }

        $this->coefficients = ['c' => $c, 'b'=> $b, 'a'=> $a];
    }

    /**
     * @param array $array
     * @param $subtract
     * @return array
     */
    protected function subtract(array $array, $subtract): array
    {
        if (is_array($subtract)) {
            return array_map(function($val1, $val2) {
                return $val1 - $val2;
            }, $array, $subtract);
        }

        return array_map(function($val) use($subtract) {
            return $val - $subtract;
        }, $array);
    }

    /**
     * Перемножает элементы первого массива.
     * @param array $array
     * @param $multi
     * @return float[]
     */
    protected function multi(array $array, $multi): array
    {
        if (is_array($multi))
        {
            return array_map(function($a, $b) {
                return $a * $b;
            }, $array, $multi);
        }
        elseif ($multi === 'square')
        {
            return array_map(function($a) {
                return $a * $a;
            }, $array);
        }

        return array_map(function($val) use($multi) {
            return $val * $multi;
        }, $array);
    }

    /**
     * @return float[][]
     */
    protected function calcOrthoCoeffs(): array
    {
        $prev = null;

        $ab = array_map(function($ss, $qq) use (&$prev) {
            if (is_null($prev)) {
                $bb = 0;
            } else {
                $bb = $ss / $prev;
            }
            $prev = $ss;
            return [$qq / $ss, $bb];
        }, (array)reset($this->sums), next($this->sums));

        foreach ($ab as $k => list($aa, $bb)) {
            if (!isset($c)) {
                $c = [[1.0]];
            }

            $c[] = array_fill(0, $k+2, 1.0);

            for ($j = 0; $j <= $k; $j++) {
                $c[$k+1][$j] = (($j==0 ? 0.0 : $c[$k][$j-1]) - $c[$k][$j] * $aa - (($j==$k))
                    ? 0.0
                    : $c[$k-1][$j] * $bb);
            }
        }

        return $c ?? [];
    }

    /**
     * @return float[]
     */
    private function calcRegress(): array
    {
        foreach ($this->values as $key => $arr) {
            if (!isset($p)) {
                $p = $this->multi($arr, $this->coefficients['b'][$key]);
            } else {
                $p = $this->subtract($p, $this->multi($arr, - $this->coefficients['b'][$key]));
            }
        }

        return $p ?? [];
    }

    /**
     * @return float[]
     */
    public function getRegress(): array
    {
        return $this->calcRegress();
    }

    /**
     * @return float[]
     */
    public function getXPoints(): array
    {
        return $this->xPoints;
    }

    /**
     * @return float[]
     */
    public function getYPoints(): array
    {
        return $this->yPoints;
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @return float[][]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return float[]
     */
    public function getSums(): array
    {
        return $this->sums;
    }

    /**
     * @return float[]
     */
    public function getCoefficients(): array
    {
        return $this->coefficients;
    }

    /**
     * @return float[]
     */
    public function getDiscrepancy(array $values): array
    {
        /** @var float[] $discrepancy относительная ошибка */
        $discrepancy = [];
        /** @var float[] $deviation отклонение */
        $deviation = [];

        foreach ($this->yPoints as $key => $sourceY) {
            $resultY = $values[$key];

            if (abs($sourceY) < 1 || abs($resultY) < 1) {
                $deviation[] = abs($sourceY - $resultY);
            } else {
                $discrepancy[] = abs((($sourceY - $values[$key]) / $values[$key])) * 100;
            }
        }

        return [
            'discrepancy' => max($discrepancy),
            'deviation'   => max($deviation),
        ];
    }

    /**
     * Возвращает коэффициент детерминации.
     * @param array $values
     * @return float
     */
    public function getDeterminationCoefficient(array $values): float
    {
        $sourceYAvg = array_sum($this->yPoints) / count($this->yPoints);

        $Qr = array_sum(array_map(static function ($resultY) use ($sourceYAvg) {
            return pow($resultY - $sourceYAvg, 2);
        }, $values));

        $Q = array_sum(array_map(static function ($resultY) use ($sourceYAvg) {
            return pow($resultY - $sourceYAvg, 2);
        }, $this->yPoints));

        return $Qr / $Q;
    }
}
