# Changelog

## 1.0.0 (2026-11-19)

- BNM exchange rates for OpenCart currencies from `/v1/rates`, any default currency, BNM `nominal` handled.
- OpenCart 4.x and 3.0.4+ currency rate engine "Satelit (BNM rates)"; "Update rates now" and a token cron URL on every 3.0.x and 4.x.
- "Market price" layout module for the product page with min and median price and the cheapest offer per shop, per-product search term in the product form.
- Settings with Test connection, TTLs, data language. Admin and storefront strings in en-gb, ro-ro, ru-ru.
- Own cache table, stale copy and a 10-minute pause when the API fails.
