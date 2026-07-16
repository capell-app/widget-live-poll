## What it does

Live Poll adds a locale-specific, single-choice question to a Layout Builder page. It keeps each option's stable identity when the page is updated, records aggregate counts, and accepts one vote per browser identity for each poll.

## Do I need to do anything?

Add **Live Poll** in Layout Builder, enter a question, and add between two and ten options. Optionally set when voting opens and closes, then choose when totals are shown:

- **Always show results**
- **Show after voting** (the default)
- **Show after the poll closes**

Save the page in the relevant site and language. Saving creates or updates the published poll; removing the widget from that page/language deactivates its poll.

## Where it shows up

Editors configure it as a Layout Builder widget. On the public page, visitors can choose one option while the poll is open. They see totals only under the visibility policy you selected.

## Good to know

- This free beta extension belongs to Capell Foundation and requires Layout Builder.
- The browser receives an HTTP-only, host-only voter cookie; the database stores an HMAC derived from it, not the cookie value or an IP address.
- Vote and results responses are private and `no-store`. Voter hashes are pruned 90 days after a poll closes while aggregate option counts remain.
- Removing an option that already has votes archives it rather than deleting the recorded count.

## Screenshot status

Capture the poll fields and a public voted state with the selected option and aggregate result bars visible. The committed Marketplace card is interim beta artwork until these two fixture captures are recorded.
