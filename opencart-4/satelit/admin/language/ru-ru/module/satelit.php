<?php
// Heading
$_['heading_title']     = 'Satelit Moldova';

// Text
$_['text_extension']    = 'Расширения';
$_['text_success']      = 'Настройки Satelit Moldova сохранены.';
$_['text_edit']         = 'Редактировать Satelit Moldova';
$_['text_auto']         = 'Язык покупателя';
$_['text_key_saved']    = 'Ключ сохранён. Оставьте поле пустым, чтобы его сохранить.';
$_['text_get_key']      = 'Ключи: <a href="https://developer.satelit.md/en/console/" target="_blank" rel="noopener">developer.satelit.md/en/console</a> · тарифы: <a href="https://developer.satelit.md/en/pricing/" target="_blank" rel="noopener">developer.satelit.md/en/pricing</a>';
$_['text_connected']    = 'Подключено: тариф %s, осталось %s из %s кредитов, обновление %s.';
$_['text_rates']        = 'Курсы НБМ';
$_['text_rates_help']   = 'Задаёт курс каждой валюты магазина, которую публикует НБМ (EUR, USD, RON, RUB, UAH…), по официальным курсам к MDL, какой бы ни была основная валюта.';
$_['text_attribution'] = 'Sursa: Banca Națională a Moldovei, bnm.md, CC BY 4.0';
$_['text_last']         = 'Последнее обновление';
$_['text_never']        = 'Ещё нет';
$_['text_cron']         = 'Адрес для cron';
$_['text_cron_help']    = 'Вызывайте раз в час; запрос к API идёт, только когда курсы старше TTL выше (1 кредит за обновление).';
$_['text_engine_on']    = 'Satelit (курсы НБМ) — источник курсов магазина: его использует и кнопка Refresh в OpenCart.';
$_['text_engine_off']   = 'Чтобы кнопка Refresh в OpenCart брала курсы НБМ, установите и включите Satelit (BNM rates) в <a href="%s">Расширения &gt; Currency</a>.';
$_['text_engine_none']  = 'В OpenCart 3.0.3.x нет источников курсов: курсы обновляются здесь и по адресу для cron. Отключите «Auto Update Currency» в System &gt; Settings &gt; Option: его источник больше не работает.';
$_['text_market']       = 'Цена на рынке';
$_['text_layout']       = 'Показать блок: Design &gt; Layouts &gt; Product &gt; Content Top (или Content Bottom) &gt; Satelit Moldova. У каждого товара может быть свой поисковый запрос (Catalog &gt; Products &gt; Data &gt; Поисковый запрос Satelit), иначе берётся название. Один поиск стоит 5 кредитов и хранится в течение TTL.';
$_['text_credits']      = 'Кредиты';

// Entry
$_['entry_api_key']     = 'Ключ API';
$_['entry_language']    = 'Язык данных';
$_['entry_rates_ttl']   = 'Курсы: обновлять через (часов)';
$_['entry_prices_ttl']  = 'Цены на рынке: хранить (часов)';
$_['entry_offers']      = 'Сколько магазинов показывать';
$_['entry_status']      = 'Цена на рынке на странице товара';
$_['entry_term']        = 'Поисковый запрос Satelit';
$_['help_term']         = 'Слова, которыми товар называется в магазинах Молдовы, для блока «Цена на рынке». Пусто: название товара.';
$_['placeholder_term']  = 'например iPhone 15 128GB';
$_['help_api_key']      = 'Хранится на сервере: страницы магазина его не видят.';

// Button
$_['button_test']       = 'Проверить подключение';
$_['button_rates']      = 'Обновить курсы сейчас';

// Error
$_['error_permission']  = 'У вас нет прав на изменение Satelit Moldova!';
$_['error_permission_currency'] = 'У вас нет прав на изменение валют!';
$_['error_form']        = 'Проверьте форму: в ней есть ошибки.';
$_['error_api_key']     = 'Ключ Satelit начинается с sat_ (например sat_live_…).';
$_['error_range']       = 'Целое число от %s до %s.';
$_['error_no_key']      = 'Сначала введите ключ API.';
$_['error_no_plan']     = 'Ключ работает, но у аккаунта нет тарифа: выберите его на developer.satelit.md/en/pricing/.';
