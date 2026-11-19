<?php
/**
 * Satelit Moldova storefront: the "Market price" block and the rates cron URL.
 *
 *   layout module (product page)                                    market prices for the product
 *   index.php?route=extension/satelit/module/satelit.cron&token=…   BNM rates, when older than the TTL
 */
namespace Opencart\Catalog\Controller\Extension\Satelit\Module;

require_once DIR_EXTENSION . 'satelit/system/library/satelit_opencart.php';

class Satelit extends \Opencart\System\Engine\Controller {
	private function satelit(): \SatelitOpenCart {
		return new \SatelitOpenCart($this->db, $this->config, $this->cache, $this->log);
	}

	public function index(array $setting = []): string {
		if (!$this->config->get('module_satelit_status')) {
			return '';
		}
		$route = (string)($this->request->get['route'] ?? '');
		$productId = (int)($this->request->get['product_id'] ?? 0);
		if ($route !== 'product/product' || $productId <= 0) {
			return '';
		}
		try {
			$this->load->model('catalog/product');
			$product = $this->model_catalog_product->getProduct($productId);
			if (!$product) {
				return '';
			}
			$s = $this->satelit();
			$item = $s->market($productId, $product['name'], $s->lang((string)$this->config->get('config_language')));
		} catch (\Throwable $e) {
			// A broken API or table never breaks a product page.
			$this->log->write('Satelit market prices: ' . $e->getMessage());
			return '';
		}
		if (!$item) {
			return '';
		}

		$this->load->language('extension/satelit/module/satelit');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['match'] = sprintf($this->language->get('text_match'), $item['name']);
		$data['min'] = $item['min'] !== null ? sprintf($this->language->get('text_from'), $this->price($item['min'])) : '';
		$data['median'] = $item['median'] !== null ? sprintf($this->language->get('text_median'), $this->price($item['median'])) : '';
		$data['stores'] = (int)$item['stores'] > 1 ? sprintf($this->language->get('text_stores'), (int)$item['stores']) : '';
		$data['summary'] = implode(' · ', array_filter([$data['min'], $data['median'], $data['stores']]));
		$data['offers'] = [];
		foreach ($item['offers'] as $o) {
			$data['offers'][] = [
				'store' => $o['store'],
				'price' => $this->price($o['price']),
				'out'   => $o['in_stock'] === false ? $this->language->get('text_out') : '',
			];
		}
		$data['text_source'] = $this->language->get('text_source');
		return $this->load->view('extension/satelit/module/satelit', $data);
	}

	/** Prices come in MDL: shown in the shopper's currency when the store has MDL. */
	private function price(float $mdl): string {
		$code = $this->session->data['currency'] ?? $this->config->get('config_currency');
		$mdlValue = (float)$this->currency->getValue('MDL');
		if ($mdlValue > 0 && $this->currency->has($code)) {
			return $this->currency->format($mdl, $code, (float)$this->currency->getValue($code) / $mdlValue);
		}
		return number_format($mdl, 2, '.', ' ') . ' lei';
	}

	/** Cron: updates the rates when the last ones are older than the TTL. */
	public function cron(): void {
		$this->response->addHeader('Content-Type: application/json');
		$want = (string)$this->config->get('module_satelit_cron_token');
		$got = (string)($this->request->get['token'] ?? '');
		if ($want === '' || !hash_equals($want, $got)) {
			$this->response->addHeader(($this->request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
			$this->response->setOutput(json_encode(['ok' => false, 'message' => 'bad token']));
			return;
		}
		try {
			$r = $this->satelit()->refreshRates(!empty($this->request->get['force']));
		} catch (\Throwable $e) {
			$this->log->write('Satelit rates: ' . $e->getMessage());
			$r = ['ok' => false, 'message' => 'error'];
		}
		$this->response->setOutput(json_encode(['ok' => $r['ok'], 'message' => $r['message']]));
	}
}
