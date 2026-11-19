<?php
/**
 * Satelit (BNM rates) for OpenCart 4.x storefront: the built-in "currency" cron
 * task (Extensions > Cron Jobs) calls currency() with the default currency.
 */
namespace Opencart\Catalog\Controller\Extension\Satelit\Currency;

require_once DIR_EXTENSION . 'satelit/system/library/satelit_opencart.php';

class Satelit extends \Opencart\System\Engine\Controller {
	public function currency(string $default = ''): void {
		if (!$this->config->get('currency_satelit_status')) {
			return;
		}
		try {
			(new \SatelitOpenCart($this->db, $this->config, $this->cache, $this->log))->refreshRates(false, $default);
		} catch (\Throwable $e) {
			$this->log->write('Satelit rates: ' . $e->getMessage());
		}
	}
}
