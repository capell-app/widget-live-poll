# Voting after a temporary failure

If preparation fails temporarily, visitors can press Vote again to make a fresh preparation request without reloading. Concurrent attempts share preparation and submit once. An already-counted vote shows its result without posting another vote; the server still enforces duplicate-vote protection.

## Authored JavaScript and local checks

[`resources/dist/widget.js`](../resources/dist/widget.js) is the canonical authored JavaScript source and the asset shipped to browsers. It is maintained directly as readable JavaScript. Despite the `dist` directory name, it is not a generated bundle: there is no separate JavaScript source or regeneration step. Edit this file directly when changing browser behaviour.

From the monorepo root, check syntax and run the browser regression tests against that same file with controlled fetch responses:

```sh
node --check packages/widget-live-poll/resources/dist/widget.js
node --test packages/widget-live-poll/tests/JavaScript/retry.test.mjs
```
