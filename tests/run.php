<?php
/**
 * Offline tests: no network, no OpenCart. Run with `php tests/run.php`.
 *
 * HTTP is a fake transport fed from tests/fixtures (real answers of the live API,
 * trimmed); the database is a tiny in-memory fake that understands the few
 * queries SatelitOpenCart sends.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('DB_PREFIX', 'oc_');

require __DIR__ . '/../shared/satelit_opencart.php';

$failures = 0;
$count = 0;
function ok($cond, $name) {
    global $failures, $count;
    $count++;
    if ($cond) {
        echo "ok   $name\n";
    } else {
        $failures++;
        echo "FAIL $name\n";
    }
}
function near($a, $b, $eps = 1e-9) {
    return abs((float)$a - (float)$b) < $eps;
}
function fixture($name) {
    return file_get_contents(__DIR__ . '/fixtures/' . $name);
}

/** Fake transport: route prefix => [status, body, headers]; records every URL. */
class FakeHttp {
    public $routes = array();
    public $calls = array();
    public $lastHeaders = array();
    public function __invoke($url, $headers, $timeout) {
        $this->calls[] = $url;
        $this->lastHeaders = $headers;
        $path = substr(parse_url($url, PHP_URL_PATH), strlen('/v1/'));
        foreach ($this->routes as $prefix => $res) {
            if (strpos($path, $prefix) === 0) {
                if (is_array($res) && isset($res[0]) && is_array($res[0])) {
                    $res = count($res) > 1 ? array_shift($this->routes[$prefix]) : $res[0];
                }
                return array('status' => $res[0], 'body' => $res[1], 'headers' => isset($res[2]) ? $res[2] : array());
            }
        }
        return array('status' => 0, 'body' => '', 'headers' => array());
    }
}

/** In-memory stand-in for OpenCart's DB, for the queries SatelitOpenCart sends. */
class FakeDb {
    public $cache = array();     // key => [data, expires]
    public $product = array();   // id => term
    public $currency = array();  // code => value
    public $sql = array();
    public function escape($s) { return addslashes((string)$s); }
    public function query($sql) {
        $this->sql[] = $sql;
        $r = new stdClass();
        $r->rows = array();
        $r->num_rows = 0;
        $r->row = array();
        if (preg_match("~SELECT `data`, `expires` FROM `oc_satelit_cache` WHERE `cache_key` = '(.*)'~", $sql, $m)) {
            $k = stripslashes($m[1]);
            if (isset($this->cache[$k])) {
                $r->row = array('data' => $this->cache[$k][0], 'expires' => $this->cache[$k][1]);
                $r->rows = array($r->row);
                $r->num_rows = 1;
            }
        } elseif (preg_match("~INSERT INTO `oc_satelit_cache` SET `cache_key` = '(.*?)', `data` = '(.*?)', `expires` = (\d+), `updated`~s", $sql, $m)) {
            $this->cache[stripslashes($m[1])] = array(stripslashes($m[2]), (int)$m[3]);
        } elseif (strpos($sql, "DELETE FROM `oc_satelit_cache`") === 0) {
            foreach (array_keys($this->cache) as $k) {
                if (strpos($k, 'm:') === 0 || $k === 'down') {
                    unset($this->cache[$k]);
                }
            }
        } elseif (preg_match("~SELECT `term` FROM `oc_satelit_product` WHERE `product_id` = (\d+)~", $sql, $m)) {
            if (isset($this->product[(int)$m[1]])) {
                $r->row = array('term' => $this->product[(int)$m[1]]);
                $r->num_rows = 1;
            }
        } elseif (preg_match("~INSERT INTO `oc_satelit_product` SET `product_id` = (\d+), `term` = '(.*?)' ON~", $sql, $m)) {
            $this->product[(int)$m[1]] = stripslashes($m[2]);
        } elseif (preg_match("~DELETE FROM `oc_satelit_product` WHERE `product_id` = (\d+)~", $sql, $m)) {
            unset($this->product[(int)$m[1]]);
        } elseif (strpos($sql, "SELECT `code` FROM `oc_currency`") === 0) {
            foreach (array_keys($this->currency) as $c) {
                $r->rows[] = array('code' => $c);
            }
            $r->num_rows = count($r->rows);
        } elseif (preg_match("~UPDATE `oc_currency` SET `value` = '([0-9.eE+-]+)', `date_modified` = NOW\(\) WHERE `code` = '([A-Z]+)'~", $sql, $m)) {
            $this->currency[$m[2]] = (float)$m[1];
        }
        return $r;
    }
}

class FakeConfig {
    public $v;
    public function __construct($v) { $this->v = $v; }
    public function get($k) { return isset($this->v[$k]) ? $this->v[$k] : null; }
}
class FakeCache {
    public $deleted = array();
    public function delete($k) { $this->deleted[] = $k; }
}
class FakeLog {
    public $lines = array();
    public function write($l) { $this->lines[] = $l; }
}

function store($http, $currency, $default = 'MDL', $extra = array()) {
    $db = new FakeDb();
    $db->currency = $currency;
    $config = new FakeConfig(array_merge(array('config_currency' => $default, 'config_language' => 'ro-ro',
        'module_satelit_api_key' => 'sat_live_TESTKEY000', 'module_satelit_rates_ttl' => 3, 'module_satelit_prices_ttl' => 6,
        'module_satelit_offers' => 5, 'module_satelit_language' => 'auto'), $extra));
    $cache = new FakeCache();
    $log = new FakeLog();
    return array(new SatelitOpenCart($db, $config, $cache, $log, $http), $db, $cache, $log);
}

$rates = json_decode(fixture('rates.json'), true);
$eur = null;
$usd = null;
foreach ($rates['data'] as $r) {
    if ($r['code'] === 'EUR') { $eur = $r['value'] / $r['nominal']; }
    if ($r['code'] === 'USD') { $usd = $r['value'] / $r['nominal']; }
}

// ---- client ----
$http = new FakeHttp();
$http->routes['rates'] = array(200, fixture('rates.json'));
$c = new SatelitMoldovaClient('sat_live_TESTKEY000', 'ru', 30, $http);
$res = $c->rates();
ok($res['ok'] && count($res['data']) === 5, 'rates: parsed 5 rows');
ok(strpos($http->calls[0], 'https://api.satelit.md/v1/rates?lang=ru') === 0, 'rates: URL and lang');
ok(in_array('Authorization: Bearer sat_live_TESTKEY000', $http->lastHeaders, true), 'client: bearer header');
ok(strpos($http->calls[0], 'sat_live') === false, 'client: key never in the URL');
ok($res['meta']['attribution'] === 'Sursa: Banca Națională a Moldovei, bnm.md, CC BY 4.0', 'rates: attribution kept');

$http = new FakeHttp();
$http->routes['me'] = array(401, '{"error":{"code":"key_invalid","message":"This key is not valid.","docs":"x"}}');
$res = (new SatelitMoldovaClient('sat_live_BAD00000', 'en', 5, $http))->me();
ok(!$res['ok'] && $res['status'] === 401 && $res['error']['code'] === 'key_invalid', 'errors: 401 key_invalid');

$http = new FakeHttp();
$res = (new SatelitMoldovaClient('k', 'en', 5, $http))->rates();
ok(!$res['ok'] && $res['error']['code'] === 'network', 'errors: dead API is a result, not an exception');

$http = new FakeHttp();
$http->routes['rates'] = array(array(429, '{"error":{"code":"rate_limited","message":"slow down"}}', array('Retry-After' => '1')), array(200, fixture('rates.json')));
$res = (new SatelitMoldovaClient('k', 'en', 5, $http))->rates();
ok($res['ok'] && count($http->calls) === 2, 'retry: one retry after 429 rate_limited');

$http = new FakeHttp();
$http->routes['rates'] = array(array(429, '{"error":{"code":"quota_exceeded","message":"no credits"}}', array('Retry-After' => '1')), array(200, fixture('rates.json')));
$res = (new SatelitMoldovaClient('k', 'en', 5, $http))->rates();
ok(!$res['ok'] && count($http->calls) === 1 && $res['error']['code'] === 'quota_exceeded', 'retry: never on quota_exceeded');

$http = new FakeHttp();
$http->routes['rates'] = array(503, '<html>busy</html>');
$res = (new SatelitMoldovaClient('k', 'en', 5, $http))->rates();
ok(!$res['ok'] && $res['error']['code'] === 'bad_response' && count($http->calls) === 1, 'errors: non-JSON 503 without Retry-After');

ok(SatelitMoldovaClient::langFromLocale('ru-ru') === 'ru' && SatelitMoldovaClient::langFromLocale('en-gb') === 'en'
    && SatelitMoldovaClient::langFromLocale('fr-fr') === 'ro', 'lang: store locale to API language');
ok(SatelitMoldovaClient::cleanTerm(" <b>iPhone</b>\n 15 ") === 'iPhone 15' && SatelitMoldovaClient::cleanTerm('a') === '', 'terms: cleaned, 2 characters at least');
ok(mb_strlen(SatelitMoldovaClient::cleanTerm(str_repeat('ă', 150)), 'UTF-8') === 100, 'terms: cut to 100 characters');

// ---- store values ----
$v = SatelitMoldovaClient::storeValues($rates['data'], array('MDL', 'EUR', 'USD', 'GBP'), 'MDL');
ok($v['error'] === null && $v['values']['MDL'] === 1.0, 'values: MDL default stays 1');
ok(near($v['values']['EUR'], 1 / $eur), 'values: MDL default, EUR = 1 / value');
ok($v['skipped'] === array('GBP'), 'values: a currency BNM does not list is skipped');

$v = SatelitMoldovaClient::storeValues($rates['data'], array('MDL', 'EUR', 'USD'), 'EUR');
ok($v['values']['EUR'] === 1.0 && near($v['values']['MDL'], $eur) && near($v['values']['USD'], $eur / $usd), 'values: EUR default, MDL = value, USD = EUR/USD');

$v = SatelitMoldovaClient::storeValues(array(array('code' => 'JPY', 'value' => 12.5, 'nominal' => 100)), array('MDL', 'JPY'), 'MDL');
ok(near($v['values']['JPY'], 100 / 12.5), 'values: nominal (100 JPY = 12.5 MDL)');

$v = SatelitMoldovaClient::storeValues($rates['data'], array('GBP', 'EUR'), 'GBP');
ok($v['error'] === 'default_not_listed' && !$v['values'], 'values: default without a BNM rate changes nothing');
ok(SatelitMoldovaClient::storeValues(array(), array('EUR'), 'MDL')['error'] === 'no_rates', 'values: empty answer changes nothing');

// ---- market summary ----
$item = json_decode(fixture('item.json'), true)['data'];
$s = SatelitMoldovaClient::summarize($item, 5);
$names = array();
foreach ($s['offers'] as $o) { $names[] = $o['store']; }
ok(count($s['offers']) === 5 && count(array_unique($names)) === 5, 'summary: 5 offers, one per shop');
ok($s['offers'][0]['price'] <= $s['offers'][4]['price'], 'summary: cheapest first');
ok($s['min'] === (float)$item['min_price'] && $s['median'] === (float)$item['median_price'], 'summary: min and median from the API');
ok(SatelitMoldovaClient::median(array(3, 1, 2, 10)) === 2.5 && SatelitMoldovaClient::median(array()) === null, 'summary: median helper');
$s = SatelitMoldovaClient::summarize(array('offers' => array(
    array('store' => array('name' => 'A'), 'price' => 10, 'in_stock' => false),
    array('store' => array('name' => 'A'), 'price' => 12, 'in_stock' => true),
    array('store' => array('name' => 'B'), 'price' => 11, 'in_stock' => null),
)));
ok($s['offers'][0]['store'] === 'B' && $s['offers'][1]['price'] === 12.0 && $s['offers'][1]['in_stock'] === true, 'summary: in-stock offer of a shop wins over a cheaper sold-out one');
ok($s['median'] === 11.5, 'summary: median computed when the API gives none');

// ---- SatelitOpenCart: rates ----
$http = new FakeHttp();
$http->routes['rates'] = array(200, fixture('rates.json'));
list($sat, $db, $cache, $log) = store($http, array('MDL' => 1, 'EUR' => 0.05, 'USD' => 0.05, 'GBP' => 0.04));
$r = $sat->refreshRates(true);
ok($r['ok'] && near($db->currency['EUR'], 1 / $eur, 1e-8) && $db->currency['MDL'] == 1, 'refresh: EUR stored as 1/value with MDL default');
ok($db->currency['GBP'] == 0.04, 'refresh: unlisted GBP untouched');
ok(in_array('currency', $cache->deleted, true), 'refresh: OpenCart currency cache dropped');
ok(strpos($sat->lastLog(), 'BNM 2026-') !== false && strpos($sat->lastLog(), 'no BNM rate: GBP') !== false, 'refresh: log line');
$r = $sat->refreshRates(false);
ok($r['message'] === 'fresh' && count($http->calls) === 1, 'refresh: TTL respected (no second call)');
$r = $sat->refreshRates(true);
ok(count($http->calls) === 2, 'refresh: "Update now" always calls');

$http = new FakeHttp();
$http->routes['rates'] = array(200, fixture('rates.json'));
list($sat, $db) = store($http, array('MDL' => 20, 'EUR' => 1, 'USD' => 1.1), 'EUR');
$sat->refreshRates(true);
ok(near($db->currency['MDL'], $eur, 1e-8) && $db->currency['EUR'] == 1 && near($db->currency['USD'], $eur / $usd, 1e-8), 'refresh: EUR default store');

$http = new FakeHttp();
$http->routes['rates'] = array(401, '{"error":{"code":"key_invalid","message":"This key is not valid."}}');
list($sat, $db, $cache, $log) = store($http, array('MDL' => 1, 'EUR' => 0.05));
$r = $sat->refreshRates(true);
ok(!$r['ok'] && $db->currency['EUR'] == 0.05 && strpos($sat->lastLog(), 'key_invalid') !== false && $log->lines, 'refresh: bad key keeps values, logs a line');

list($sat, $db) = store(new FakeHttp(), array('MDL' => 1, 'EUR' => 0.05), 'MDL', array('module_satelit_api_key' => ''));
$r = $sat->refreshRates(false);
ok(!$r['ok'] && $r['message'] === 'no API key' && !$db->cache, 'refresh: no key, no call, no log on automatic runs');

// ---- SatelitOpenCart: market ----
$http = new FakeHttp();
$http->routes['prices/search'] = array(200, fixture('search.json'));
$http->routes['prices/items/'] = array(200, fixture('item.json'));
list($sat, $db) = store($http, array('MDL' => 1));
$m = $sat->market(40, 'iPhone', 'ro');
ok($m && $m['name'] === 'Apple iPhone 15 128 GB' && $m['offers'][0]['store'] !== '', 'market: product name used as the term');
ok(strpos($http->calls[0], 'prices/search?q=iPhone&lang=ro') !== false && strpos($http->calls[1], 'prices/items/6981') !== false, 'market: search, then the best match');
$m2 = $sat->market(40, 'iPhone', 'ro');
ok($m2 == $m && count($http->calls) === 2, 'market: second view from the cache');

$sat->saveProductTerm(40, 'iPhone 15 128GB');
ok($db->product[40] === 'iPhone 15 128GB' && $sat->productTerm(40) === 'iPhone 15 128GB', 'terms: saved per product');
$sat->market(40, 'iPhone', 'ro');
ok(strpos(end($http->calls), 'prices/items/') !== false && strpos($http->calls[2], 'q=iPhone%2015%20128GB') !== false, 'market: the product term wins over the name');
$sat->saveProductTerm(40, '');
ok(!isset($db->product[40]), 'terms: empty term removed');

$http = new FakeHttp();
$http->routes['prices/search'] = array(200, '{"data":[],"meta":{"credits":3}}');
list($sat, $db) = store($http, array('MDL' => 1));
ok($sat->market(41, 'Nimic deloc', 'ro') === null && $sat->market(41, 'Nimic deloc', 'ro') === null && count($http->calls) === 1, 'market: "no match" is cached too');

$http = new FakeHttp();
list($sat, $db, $cache, $log) = store($http, array('MDL' => 1));
ok($sat->market(42, 'iPhone', 'ro') === null && $sat->market(43, 'Samsung', 'ro') === null && count($http->calls) === 1, 'market: dead API, one call then a 10 min pause');
ok(isset($db->cache['down']) && $log->lines, 'market: pause stored, short log line');

$http = new FakeHttp();
$http->routes['prices/search'] = array(200, fixture('search.json'));
$http->routes['prices/items/'] = array(200, fixture('item.json'));
list($sat, $db) = store($http, array('MDL' => 1));
$sat->market(44, 'iPhone', 'ro');
foreach ($db->cache as $k => $row) { $db->cache[$k][1] = time() - 1; }  // expire it
$http->routes = array();                                                // API dies
$stale = $sat->market(44, 'iPhone', 'ro');
ok($stale && $stale['name'] === 'Apple iPhone 15 128 GB', 'market: dead API shows the last cached copy');

list($sat) = store(new FakeHttp(), array('MDL' => 1), 'MDL', array('module_satelit_api_key' => ''));
ok($sat->market(45, 'iPhone', 'ro') === null, 'market: no key, nothing shown');

ok($sat->lang('ru-ru') === 'ru', 'lang: auto follows the shopper');
list($sat) = store(new FakeHttp(), array(), 'MDL', array('module_satelit_language' => 'en'));
ok($sat->lang('ru-ru') === 'en', 'lang: fixed language');

// ---- product form injection ----
$html = '<form><div class="tab-content"><div class="tab-pane" id="tab-data"><div class="form-group">model</div></div></div></form>';
$out = SatelitOpenCart::injectField($html, '<div id="x"></div>');
ok(strpos($out, 'id="tab-data"><div id="x"></div><div class="form-group">model') !== false, 'form: field at the top of the Data tab (OC 3)');
$out = SatelitOpenCart::injectField('<div id="tab-data" class="tab-pane"><fieldset>', '<i></i>');
ok($out === '<div id="tab-data" class="tab-pane"><i></i><fieldset>', 'form: OC 4 attribute order');
ok(SatelitOpenCart::injectField('<div>other theme</div>', '<i></i>') === '<div>other theme</div>', 'form: unknown form left alone');

echo "\n$count tests, $failures failed\n";
exit($failures ? 1 : 0);
