<?php
/**
 * Pure billing calculation helpers (no DB / config side effects).
 * Included by billinglib.php and by unit tests.
 */

/**
 * Compute the raw (pre-discount, pre-exempt, pre-vacation) charge for one
 * child for one week.
 *
 * @param array $program
 * @param int   $day_count
 * @param bool  $is_enrollment_billing
 * @return float
 */
function compute_child_week_bill($program, $day_count, $is_enrollment_billing) {
    if ($is_enrollment_billing) {
        return (float)$program['fulltime'];
    }
    if ($day_count > 0) {
        if ($day_count >= (int)$program['consider_full']) {
            return (float)$program['fulltime'];
        }
        $bill = $day_count * (float)$program['perday'];
        $min_active = (float)$program['minimumactive'];
        if ($min_active > 0 && $bill < $min_active) {
            $bill = $min_active;
        }
        return $bill;
    }
    return (float)$program['minimuminactive'];
}

/**
 * Apply a child's individual discount to a raw weekly charge.
 *
 * @return float
 */
function apply_individual_discount($program, $raw_bill, $discount, $is_enrollment_billing, $day_count) {
    $final = (float)$raw_bill - (float)$discount;
    if (!$is_enrollment_billing) {
        $floor = $day_count > 0
            ? (float)$program['minimumactive']
            : (float)$program['minimuminactive'];
        if ($floor > 0 && $final < $floor) {
            $final = $floor;
        }
    }
    return max(0.0, $final);
}

/**
 * Pure multi-child discount (no DB).
 *
 * $children: id => ['final' => float, 'exempt' => 0|1, 'did_not_attend' => 0|1]
 * Did Not Attend children never receive multi-child reduction.
 *
 * @return array id => final amount after multi-child discount
 */
function apply_multi_child_discount(array $children, $multiple, $threshold = 0.0) {
    $multiple  = (float)$multiple;
    $threshold = (float)$threshold;

    $family_total = 0.0;
    foreach ($children as $info) {
        if (empty($info['exempt']) && $info['final'] > 0) {
            $family_total += (float)$info['final'];
        }
    }

    if ($multiple <= 0 || ($threshold > 0 && $family_total <= $threshold)) {
        $out = [];
        foreach ($children as $id => $info) {
            $out[$id] = (float)$info['final'];
        }
        return $out;
    }

    $i = 0;
    foreach ($children as $id => &$info) {
        $info['_ord'] = $i++;
    }
    unset($info);

    uasort($children, function ($a, $b) {
        $cmp = $b['final'] <=> $a['final'];
        return $cmp !== 0 ? $cmp : ($a['_ord'] <=> $b['_ord']);
    });

    $first  = true;
    $result = [];
    foreach ($children as $id => $info) {
        $bill = (float)$info['final'];
        if (
            !$first
            && empty($info['exempt'])
            && empty($info['did_not_attend'])
            && $bill > 0
        ) {
            $bill = max(0.0, round($bill - $multiple, 2));
        }
        $first = false;
        $result[$id] = $bill;
    }
    return $result;
}

/**
 * Does the multi-child discount apply to this specific child?
 *
 * @return bool
 */
function child_gets_multi_child_discount($child_id, array $children, $multiple, $threshold = 0.0) {
    if (!array_key_exists($child_id, $children)) {
        return false;
    }
    $after     = apply_multi_child_discount($children, $multiple, $threshold);
    $before    = (float)$children[$child_id]['final'];
    $after_amt = (float)$after[$child_id];
    return $after_amt < $before - 0.0005;
}