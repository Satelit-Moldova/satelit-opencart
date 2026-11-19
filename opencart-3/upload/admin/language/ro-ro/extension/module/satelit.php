<?php
// Heading
$_['heading_title']     = 'Satelit Moldova';

// Text
$_['text_extension']    = 'Extensii';
$_['text_success']      = 'Setările Satelit Moldova au fost salvate.';
$_['text_edit']         = 'Editează Satelit Moldova';
$_['text_auto']         = 'Limba cumpărătorului';
$_['text_key_saved']    = 'O cheie e salvată. Lasă câmpul gol ca s-o păstrezi.';
$_['text_get_key']      = 'Chei: <a href="https://developer.satelit.md/en/console/" target="_blank" rel="noopener">developer.satelit.md/en/console</a> · planuri: <a href="https://developer.satelit.md/en/pricing/" target="_blank" rel="noopener">developer.satelit.md/en/pricing</a>';
$_['text_connected']    = 'Conectat: planul %s, %s din %s credite rămase, se reînnoiesc la %s.';
$_['text_rates']        = 'Cursurile BNM';
$_['text_rates_help']   = 'Pune valoarea fiecărei valute a magazinului pe care o publică BNM (EUR, USD, RON, RUB, UAH…) din cursurile oficiale în MDL, oricare ar fi valuta implicită.';
$_['text_attribution'] = 'Sursa: Banca Națională a Moldovei, bnm.md, CC BY 4.0';
$_['text_last']         = 'Ultima actualizare';
$_['text_never']        = 'Încă nu';
$_['text_cron']         = 'Adresa pentru cron';
$_['text_cron_help']    = 'Apeleaz-o la fiecare oră; cere API-ul doar când cursurile sunt mai vechi decât TTL-ul de mai sus (1 credit pe actualizare).';
$_['text_engine_on']    = 'Satelit (cursuri BNM) e motorul de cursuri al magazinului: îl folosește și butonul Refresh din OpenCart.';
$_['text_engine_off']   = 'Ca butonul Refresh din OpenCart să folosească cursurile BNM, instalează și pornește Satelit (BNM rates) în <a href="%s">Extensii &gt; Currency</a>.';
$_['text_engine_none']  = 'OpenCart 3.0.3.x nu are motoare de cursuri: cursurile se actualizează aici și prin adresa pentru cron. Oprește „Auto Update Currency” în System &gt; Settings &gt; Option, sursa lui nu mai există.';
$_['text_market']       = 'Prețul pe piață';
$_['text_layout']       = 'Afișează blocul: Design &gt; Layouts &gt; Product &gt; Content Top (sau Content Bottom) &gt; Satelit Moldova. Fiecare produs poate avea termenul său de căutare (Catalog &gt; Products &gt; Data &gt; Termen de căutare Satelit); altfel se folosește numele. O căutare costă 5 credite și se păstrează cât TTL-ul.';
$_['text_credits']      = 'Credite';

// Entry
$_['entry_api_key']     = 'Cheia API';
$_['entry_language']    = 'Limba datelor';
$_['entry_rates_ttl']   = 'Cursuri: actualizare după (ore)';
$_['entry_prices_ttl']  = 'Prețuri pe piață: păstrate (ore)';
$_['entry_offers']      = 'Magazine afișate';
$_['entry_status']      = 'Prețul pe piață pe pagina produsului';
$_['entry_term']        = 'Termen de căutare Satelit';
$_['help_term']         = 'Cuvintele care numesc produsul în magazinele din Moldova, pentru blocul „Prețul pe piață”. Gol: numele produsului.';
$_['placeholder_term']  = 'de ex. iPhone 15 128GB';
$_['help_api_key']      = 'Rămâne pe server: paginile magazinului n-o văd niciodată.';

// Button
$_['button_test']       = 'Verifică legătura';
$_['button_rates']      = 'Actualizează cursurile acum';

// Error
$_['error_permission']  = 'Nu ai dreptul să modifici Satelit Moldova!';
$_['error_permission_currency'] = 'Nu ai dreptul să modifici valutele!';
$_['error_form']        = 'Verifică formularul: are erori.';
$_['error_api_key']     = 'O cheie Satelit începe cu sat_ (de exemplu sat_live_…).';
$_['error_range']       = 'Un număr întreg de la %s la %s.';
$_['error_no_key']      = 'Introdu întâi cheia API.';
$_['error_no_plan']     = 'Cheia merge, dar contul n-are un plan: alege unul la developer.satelit.md/en/pricing/.';
