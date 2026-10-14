# Crypto rule significance research

This branch introduces a research-only statistical utility. It does not alter the production crypto analyzer, scan behavior, EGX services, or entry rules.

## Required study design

1. Export historical Binance Spot candles and compute each rule using only information available at the signal candle close.
2. Evaluate each of the four existing conditions independently, then the combined rule. Predeclare the forward-return horizon(s) before inspecting results.
3. For each timestamp, retain the rule signal, forward return, and matched benchmark return over the same horizon.
4. Run this service on a development period first; freeze the method and evaluate once on a separate out-of-sample period.
5. Correct for multiple comparisons if several rules/horizons are tested. Do not select a rule solely because it has the smallest p-value.
6. Separately test executable trade outcomes with realistic fees, spread/slippage, stop/target order, and no look-ahead.

## Interpretation

The service reports the average and median excess forward return conditional on a signal, the positive-excess rate, and a two-sided block-bootstrap p-value. The benchmark is supplied by the caller; it is not inferred by this service. Returns must be decimal fractions, not percentages.

A low p-value is not proof of profitable trades. This utility is not yet connected to historical signal generation or Binance data ingestion. No empirical claim should be made until that pipeline is implemented, run, and reviewed.
