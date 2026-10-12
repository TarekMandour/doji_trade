# Crypto Spot Analysis

This is an isolated first-release module for manual Binance Spot analysis. It does not alter the EGX analysis services, stock tables, or existing watchlists.

## Initial scope

- Binance Spot USDT pairs only.
- Admin manually synchronizes the available Spot pairs and creates a crypto watchlist.
- A Scan creates a new UUID-based run directory under Laravel's private local storage.
- Candle caches are shared across scans; each scan owns its metadata, results, and trade-based volume profiles.
- Analysis checks 1h, 4h, and 1d trend alignment and uses aggregate trades for a 24-hour price/volume profile.
- No orders are submitted. Bearish signals are informational only because this module does not short Spot assets.

## Storage layout

`storage/app/private/crypto/` (the Laravel `local` disk):

- `candles/{symbol}/{interval}/{date}.json`: reusable candle cache.
- `scans/{uuid}/metadata.json`: scan state and settings.
- `scans/{uuid}/results.json`: scan result summary.
- `scans/{uuid}/results/{symbol}.json`: per-pair result details.
- `scans/{uuid}/volume_profiles/{symbol}.json`: trade-derived volume profile.

Each JSON payload carries a schema version and data/update timestamps. Scan deletion is constrained to its own `crypto/scans/{uuid}` directory; shared candle caches are retained.

## Install

1. Deploy the branch and run `php artisan migrate --force`.
2. Sign in to the admin panel and open **Crypto Spot Analysis** in the sidebar.
3. Select **Sync Spot Assets**.
4. Create a watchlist with the pairs to analyze.
5. Press **Scan** and review the run history/results.

## Current limitations

The first implementation executes a scan synchronously in the web request. Aggregate-trade pagination is capped at 100 pages per pair; if the full 24-hour window cannot be covered, that pair is recorded as an error rather than silently calculating an incomplete profile. Large watchlists should be moved to queued jobs before production use. Scores are heuristic indicators, not a guarantee of future performance.
