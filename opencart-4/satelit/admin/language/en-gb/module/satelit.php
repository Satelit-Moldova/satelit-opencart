<?php
// Heading
$_['heading_title']     = 'Satelit Moldova';

// Text
$_['text_extension']    = 'Extensions';
$_['text_success']      = 'Success: Satelit Moldova settings saved.';
$_['text_edit']         = 'Edit Satelit Moldova';
$_['text_auto']         = 'Shopper\'s language';
$_['text_key_saved']    = 'A key is saved. Leave this field empty to keep it.';
$_['text_get_key']      = 'Keys: <a href="https://developer.satelit.md/en/console/" target="_blank" rel="noopener">developer.satelit.md/en/console</a> · plans: <a href="https://developer.satelit.md/en/pricing/" target="_blank" rel="noopener">developer.satelit.md/en/pricing</a>';
$_['text_connected']    = 'Connected: %s plan, %s of %s credits left, resets %s.';
$_['text_rates']        = 'BNM exchange rates';
$_['text_rates_help']   = 'Sets the value of every store currency BNM publishes (EUR, USD, RON, RUB, UAH…) from the official MDL rates, whatever your default currency is.';
$_['text_attribution'] = 'Sursa: Banca Națională a Moldovei, bnm.md, CC BY 4.0';
$_['text_last']         = 'Last update';
$_['text_never']        = 'Not yet';
$_['text_cron']         = 'Cron URL';
$_['text_cron_help']    = 'Call it every hour; it asks the API only when the last rates are older than the TTL above (1 credit per update).';
$_['text_engine_on']    = 'Satelit (BNM rates) is the store\'s currency rate engine: OpenCart\'s Refresh button uses it too.';
$_['text_engine_off']   = 'To let OpenCart\'s own Refresh button use BNM rates, install and enable Satelit (BNM rates) under <a href="%s">Extensions &gt; Currency</a>.';
$_['text_engine_none']  = 'OpenCart 3.0.3.x has no currency rate engines: rates are updated here and by the cron URL. Turn off "Auto Update Currency" in System &gt; Settings &gt; Option, the feed it used is gone.';
$_['text_market']       = 'Market prices';
$_['text_layout']       = 'Show the block: Design &gt; Layouts &gt; Product &gt; Content Top (or Content Bottom) &gt; Satelit Moldova. Each product can have its own search term (Catalog &gt; Products &gt; Data &gt; Satelit search term); otherwise its name is used. One lookup costs 5 credits and is cached for the TTL.';
$_['text_credits']      = 'Credits';

// Entry
$_['entry_api_key']     = 'API key';
$_['entry_language']    = 'Data language';
$_['entry_rates_ttl']   = 'Rates: refresh after (hours)';
$_['entry_prices_ttl']  = 'Market prices: cache for (hours)';
$_['entry_offers']      = 'Shops shown';
$_['entry_status']      = 'Market prices on product pages';
$_['entry_term']        = 'Satelit search term';
$_['help_term']         = 'Words that name this product in Moldovan shops, for the market price block. Empty: the product name.';
$_['placeholder_term']  = 'e.g. iPhone 15 128GB';
$_['help_api_key']      = 'Stays on the server: shop pages never see it.';

// Button
$_['button_test']       = 'Test connection';
$_['button_rates']      = 'Update rates now';

// Error
$_['error_permission']  = 'Warning: You do not have permission to modify Satelit Moldova!';
$_['error_permission_currency'] = 'Warning: You do not have permission to modify currencies!';
$_['error_form']        = 'Warning: Please check the form carefully for errors!';
$_['error_api_key']     = 'A Satelit key starts with sat_ (for example sat_live_…).';
$_['error_range']       = 'A whole number from %s to %s.';
$_['error_no_key']      = 'Enter an API key first.';
$_['error_no_plan']     = 'The key works, but its account has no plan: choose one at developer.satelit.md/en/pricing/.';
