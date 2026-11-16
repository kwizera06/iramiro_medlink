<?php
// includes/asset_rules.php — pure rules for fixed-asset accounting (ported from Michel's asset_rules.php).
// Trimmed on purpose: no procurement scoring and no serial-number schema fix (those UPDATE/ALTER existing medlink tables).

// IAS 16 revaluation. $diff = new carrying value - old carrying value.
// Increase: first reverses an earlier revaluation loss (profit), the rest goes to the revaluation surplus.
// Decrease: first uses up this asset's surplus, only the rest is a loss.
if (!function_exists('asset_reval_plan')) {
function asset_reval_plan(float $diff, float $surplus, float $lossPl): array {
    $diff = round($diff, 2);
    if ($diff > 0) {
        $rev = round(min($diff, $lossPl), 2);
        $toSurplus = round($diff - $rev, 2);
        return ['reverse_loss' => $rev, 'to_surplus' => $toSurplus, 'use_surplus' => 0.0, 'new_loss' => 0.0,
                'surplus' => round($surplus + $toSurplus, 2), 'loss_pl' => round($lossPl - $rev, 2)];
    }
    $dec = -$diff;
    $use = round(min($dec, $surplus), 2);
    $loss = round($dec - $use, 2);
    return ['reverse_loss' => 0.0, 'to_surplus' => 0.0, 'use_surplus' => $use, 'new_loss' => $loss,
            'surplus' => round($surplus - $use, 2), 'loss_pl' => round($lossPl + $loss, 2)];
}
}

// Journal lines for removing an asset: Cr cost, Dr accumulated depreciation, Dr proceeds, then the gain (Cr) or loss (Dr).
// $acct maps role => account id: asset, acc_dep, proceeds, gain, loss. Returns [lines, gain (negative = loss)].
if (!function_exists('asset_disposal_lines')) {
function asset_disposal_lines(float $cost, float $accDep, float $proceeds, array $acct, string $code): array {
    $cost = round($cost, 2); $accDep = round($accDep, 2); $proceeds = round($proceeds, 2);
    $gain = round($proceeds - ($cost - $accDep), 2);
    $lines = [];
    if ($cost > 0)     $lines[] = ['account_id' => $acct['asset'], 'credit' => $cost, 'desc' => "Asset removed $code"];
    if ($accDep > 0)   $lines[] = ['account_id' => $acct['acc_dep'], 'debit' => $accDep, 'desc' => "Accumulated depreciation cleared $code"];
    if ($proceeds > 0) $lines[] = ['account_id' => $acct['proceeds'], 'debit' => $proceeds, 'desc' => "Disposal proceeds $code"];
    if ($gain > 0)     $lines[] = ['account_id' => $acct['gain'], 'credit' => $gain, 'desc' => "Gain on disposal $code"];
    if ($gain < 0)     $lines[] = ['account_id' => $acct['loss'], 'debit' => -$gain, 'desc' => "Loss on disposal $code"];
    return [$lines, $gain];
}
}
