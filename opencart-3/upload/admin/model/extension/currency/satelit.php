<?php
/**
 * OpenCart 3.0.4+ calls refresh() from System > Localisation > Currencies > Refresh.
 */
require_once DIR_SYSTEM . 'library/satelit_opencart.php';

class ModelExtensionCurrencySatelit extends Model {
	public function refresh() {
		if (!$this->config->get('currency_satelit_status') || $this->config->get('config_currency_engine') != 'satelit') {
			return false;
		}
		try {
			$s = new SatelitOpenCart($this->db, $this->config, $this->cache, $this->log);
			$r = $s->refreshRates(true);
			return $r['ok'];
		} catch (\Throwable $e) {
			$this->log->write('Satelit rates: ' . $e->getMessage());
			return false;
		}
	}
}
