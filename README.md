# Live Poll Widget

Locale-specific, single-choice polls with 2 to 10 stable options. Publication sync preserves option identities and archives voted options. Vote insertion and durable counter increments share one transaction, with a unique poll and voter hash constraint making duplicate races idempotent.

The bootstrap route starts the Laravel session only after explicit interaction and sets a host-only, one-year, `HttpOnly`, `SameSite=Lax` cookie (`Secure` in production). Per-poll HMACs are persisted instead of cookie values or IP addresses. IP addresses are used only by expiring Laravel rate limiters, so production must configure trusted proxies correctly.

Vote and bootstrap responses are `private, no-store`; aggregate-only results use a 30-second public cache and ETag. Voter hashes are pruned 90 days after closure while option counters remain. Static exports use `CAPELL_POLL_LIVE_ORIGIN` when configured, otherwise they show an unavailable state.
