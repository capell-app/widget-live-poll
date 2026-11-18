# Live Poll Widget

<!-- prettier-ignore-start -->

When a club wants a quick show of hands on its next outing, a poll on the page will do it. Visitors see one question with up to ten answers. They pick one and press Vote.

The totals appear when you say. Show them to everyone from the start, only to people who've voted, or only once the poll closes on a date you set. They're plain counts for each answer, without percentages or bars. They're current when the page loads and refresh when someone votes.

Each browser gets one vote per poll. Pressing Vote twice won't count twice, though someone with a second phone could vote again. Treat it as a friendly poll, not a ballot.

To remember who has voted, the poll sets one cookie in the visitor's browser. Your site keeps only a scrambled copy of it, and never stores their internet address with the vote, only a one-day scrambled count of votes from it. Clearing the cookie doesn't allow unlimited voting, because one internet address can cast 25 votes on a poll in a day. Once a poll has a closing date, voter records are deleted ninety days after it and the totals stay. If you chose to show results after closing, everyone sees the final totals from closing time.

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

- This free extension belongs to Capell Essentials and requires Layout Builder.
- The browser receives an HTTP-only, host-only voter cookie named `capell_poll_voter` that lasts one year; the database stores an HMAC derived from it, not the cookie value or an IP address.
- Vote and results responses are private and `no-store`. Voter hashes are pruned 90 days after a poll closes while aggregate option counts remain.
- Removing an option that already has votes archives it rather than deleting the recorded count.

## Screenshot status

Capture the poll fields and a public voted state with the selected option and vote counts visible.

---

For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
