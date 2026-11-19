<?php
/**
 * Store side of the Satelit extension, shared by OpenCart 3.0.x and 4.x.
 *
 * Both versions have the same database and config objects ($db->query/escape,
 * $config->get), so the rate update, the cache and the per-product search terms
 * live here once. The version folders only hold controllers, views and languages.
 *
 * Copyright (c) 2026 Satelit (satelit.md). MIT licence.
 */

require_once __DIR__ . '/satelit_client.php';

if (!class_exists('SatelitOpenCart', false)) {
    class SatelitOpenCart
    {
        const DOWN_SECONDS = 600; // after a failed call, wait 10 min before the next one

        private $db;
        private $config;
        private $cache;
        private $log;
        private $transport;

        /**
         * @param object        $db        OpenCart DB (query, escape)
         * @param object        $config    OpenCart Config (get)
         * @param object|null   $cache     OpenCart Cache: 'currency' is dropped after an update
         * @param object|null   $log       OpenCart Log (write)
         * @param callable|null $transport HTTP transport for tests
         */
        public function __construct($db, $config, $cache = null, $log = null, $transport = null)
        {
            $this->db = $db;
            $this->config = $config;
            $this->cache = $cache;
            $this->log = $log;
            $this->transport = $transport;
        }

        public static function install($db)
        {
            $db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "satelit_cache` (
                `cache_key` VARCHAR(100) NOT NULL,
                `data` MEDIUMTEXT NOT NULL,
                `expires` INT NOT NULL DEFAULT 0,
                `updated` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`cache_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "satelit_product` (
                `product_id` INT NOT NULL,
                `term` VARCHAR(255) NOT NULL DEFAULT '',
                PRIMARY KEY (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        public static function uninstall($db)
        {
            $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "satelit_cache`");
            $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "satelit_product`");
        }

        /**
         * Sets the store's currency rate engine (config_currency_engine). Fresh
         * OpenCart 3 installs have no such row, so it is written, not updated.
         */
        public static function setEngine($db, $code)
        {
            $db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_currency_engine'");
            $db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0, `code` = 'config', `key` = 'config_currency_engine', `value` = '" . $db->escape($code) . "', `serialized` = 0");
        }

        private function setting($name, $default = null)
        {
            $v = $this->config->get('module_satelit_' . $name);
            return ($v === null || $v === '') ? $default : $v;
        }

        /** API language: the setting, or the shopper's language when set to auto. */
        public function lang($storeLangCode = '')
        {
            $lang = (string)$this->setting('language', 'auto');
            if ($lang === 'auto') {
                return SatelitMoldovaClient::langFromLocale($storeLangCode ? $storeLangCode : (string)$this->config->get('config_language'));
            }
            return SatelitMoldovaClient::langFromLocale($lang);
        }

        public function client($lang = 'ro', $key = null, $timeout = 8)
        {
            return new SatelitMoldovaClient($key === null ? (string)$this->setting('api_key', '') : $key, $lang, $timeout, $this->transport, null, 'satelit-opencart');
        }

        public function hasKey()
        {
            return trim((string)$this->setting('api_key', '')) !== '';
        }

        // ---- cache (own table: OpenCart 3 file cache has one global expiry) ----

        /** Fresh value, or null. A stale copy, when there is one, goes into $stale. */
        public function cacheGet($key, &$stale = null)
        {
            $stale = null;
            $q = $this->db->query("SELECT `data`, `expires` FROM `" . DB_PREFIX . "satelit_cache` WHERE `cache_key` = '" . $this->db->escape($key) . "'");
            if (!$q->num_rows) {
                return null;
            }
            $data = json_decode($q->row['data'], true);
            if ((int)$q->row['expires'] > time()) {
                return $data;
            }
            $stale = $data;
            return null;
        }

        public function cacheSet($key, $data, $ttl)
        {
            $json = $this->db->escape(json_encode($data));
            $exp = time() + (int)$ttl;
            $this->db->query("INSERT INTO `" . DB_PREFIX . "satelit_cache` SET `cache_key` = '" . $this->db->escape($key) . "', `data` = '" . $json . "', `expires` = " . $exp . ", `updated` = " . time()
                . " ON DUPLICATE KEY UPDATE `data` = '" . $json . "', `expires` = " . $exp . ", `updated` = " . time());
        }

        public function clearCache()
        {
            // Market prices and the "API down" pause; the rates log and timer stay.
            $this->db->query("DELETE FROM `" . DB_PREFIX . "satelit_cache` WHERE `cache_key` LIKE 'm:%' OR `cache_key` = 'down'");
        }

        private function isDown()
        {
            return $this->cacheGet('down') !== null;
        }

        private function markDown($why)
        {
            $this->cacheSet('down', $why, self::DOWN_SECONDS);
            $this->write('Satelit: ' . $why);
        }

        private function write($line)
        {
            if ($this->log && method_exists($this->log, 'write')) {
                $this->log->write($line);
            }
        }

        // ---- BNM rates ----

        public function lastLog()
        {
            $stale = null;
            $v = $this->cacheGet('rates_log', $stale);
            return (string)($v !== null ? $v : $stale);
        }

        private function logRates($line)
        {
            $line = date('Y-m-d H:i') . ' · ' . $line;
            $this->cacheSet('rates_log', $line, 10 * 365 * 86400);
            return $line;
        }

        /**
         * Update the store's currency values from /v1/rates.
         *
         * @param bool   $force   true for "Update now"; false skips while the last fetch is younger than the TTL
         * @param string $default the default currency (OpenCart 4 passes the new one while settings are saved)
         * @return array{ok:bool, message:string, values:array}
         */
        public function refreshRates($force = false, $default = '')
        {
            $default = strtoupper($default ? $default : (string)$this->config->get('config_currency'));
            if (!$this->hasKey()) {
                return array('ok' => false, 'message' => $force ? $this->logRates('no API key') : 'no API key', 'values' => array());
            }
            if (!$force && $this->cacheGet('rates_fetched') !== null) {
                return array('ok' => true, 'message' => 'fresh', 'values' => array());
            }
            $res = $this->client('en')->rates();
            if (!$res['ok']) {
                $msg = $this->logRates('error ' . $res['error']['code'] . ': ' . $res['error']['message']);
                $this->write('Satelit rates: ' . $msg);
                return array('ok' => false, 'message' => $msg, 'values' => array());
            }
            $codes = array();
            foreach ($this->db->query("SELECT `code` FROM `" . DB_PREFIX . "currency`")->rows as $row) {
                $codes[] = $row['code'];
            }
            $calc = SatelitMoldovaClient::storeValues(is_array($res['data']) ? $res['data'] : array(), $codes, $default);
            if ($calc['error']) {
                $msg = $this->logRates($calc['error'] === 'default_not_listed'
                    ? 'default currency ' . $default . ' has no BNM rate: nothing changed'
                    : 'the API sent no rates: nothing changed');
                return array('ok' => false, 'message' => $msg, 'values' => array());
            }
            $parts = array();
            foreach ($calc['values'] as $code => $value) {
                $this->db->query("UPDATE `" . DB_PREFIX . "currency` SET `value` = '" . (float)$value . "', `date_modified` = NOW() WHERE `code` = '" . $this->db->escape($code) . "'");
                if ($code !== $default) {
                    $parts[] = $code . ' ' . rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
                }
            }
            if ($this->cache) {
                $this->cache->delete('currency');
            }
            $ttl = max(1, (int)$this->setting('rates_ttl', 3)) * 3600;
            $this->cacheSet('rates_fetched', $calc['date'], $ttl);
            $line = 'BNM ' . $calc['date'] . ', default ' . $default . ': ' . ($parts ? implode(', ', $parts) : 'only the default');
            if ($calc['skipped']) {
                $line .= ' (no BNM rate: ' . implode(', ', $calc['skipped']) . ')';
            }
            return array('ok' => true, 'message' => $this->logRates($line), 'values' => $calc['values']);
        }

        // ---- market prices ----

        public function productTerm($productId)
        {
            $q = $this->db->query("SELECT `term` FROM `" . DB_PREFIX . "satelit_product` WHERE `product_id` = " . (int)$productId);
            return $q->num_rows ? (string)$q->row['term'] : '';
        }

        public function saveProductTerm($productId, $term)
        {
            $productId = (int)$productId;
            if ($productId <= 0) {
                return;
            }
            $term = SatelitMoldovaClient::cleanTerm($term);
            if ($term === '') {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "satelit_product` WHERE `product_id` = " . $productId);
            } else {
                $t = $this->db->escape($term);
                $this->db->query("INSERT INTO `" . DB_PREFIX . "satelit_product` SET `product_id` = " . $productId . ", `term` = '" . $t . "' ON DUPLICATE KEY UPDATE `term` = '" . $t . "'");
            }
        }

        /**
         * Market prices for a product page, from the cache when it can.
         * Returns the summary (see SatelitMoldovaClient::summarize) or null.
         */
        public function market($productId, $productName, $lang)
        {
            if (!$this->hasKey()) {
                return null;
            }
            $term = $this->productTerm($productId);
            $term = SatelitMoldovaClient::cleanTerm($term !== '' ? $term : html_entity_decode((string)$productName, ENT_QUOTES, 'UTF-8'));
            if ($term === '') {
                return null;
            }
            $lower = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
            $key = 'm:' . $lang . ':' . md5($lower);
            $stale = null;
            $hit = $this->cacheGet($key, $stale);
            if ($hit !== null) {
                return empty($hit['none']) ? $hit : null;
            }
            if ($this->isDown()) {
                return ($stale && empty($stale['none'])) ? $stale : null;
            }
            // Shoppers wait for this call: keep it short.
            $res = $this->client($lang, null, 5)->market($term, max(1, min(10, (int)$this->setting('offers', 5))));
            if (!$res['ok']) {
                $this->markDown('market prices: ' . $res['error']['code'] . ' (' . $res['error']['message'] . ')');
                return ($stale && empty($stale['none'])) ? $stale : null;
            }
            $ttl = max(1, (int)$this->setting('prices_ttl', 6)) * 3600;
            $this->cacheSet($key, $res['found'] ? $res['item'] : array('none' => true), $ttl);
            return $res['found'] ? $res['item'] : null;
        }

        /** Product form field, injected into the admin product form's Data tab. */
        public static function injectField($html, $field)
        {
            if (!preg_match('~<div[^>]*\bid="tab-data"[^>]*>~', $html, $m, PREG_OFFSET_CAPTURE)) {
                return $html; // unknown theme: leave the form alone
            }
            $at = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $at) . $field . substr($html, $at);
        }
    }
}
