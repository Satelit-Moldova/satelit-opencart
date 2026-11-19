<?php
/**
 * Satelit Moldova storefront: the "Market price" block and the rates cron URL.
 *
 *   layout module (product page)                          market prices for the product
 *   index.php?route=extension/module/satelit/cron&token=…  BNM rates, when older than the TTL
 */
require_once DIR_SYSTEM . 'library/satelit_opencart.php';

class ControllerExtensionModuleSatelit extends Controller {
	private function satelit() {
		return new SatelitOpenCart($this->db, $this->config, $this->cache, $this->log);
	}

	public function index($setting = array()) {
		if (!$this->config->get('module_satelit_status')) {
			return '';
		}
		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';
		$productId = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
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

		$this->load->language('extension/module/satelit');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['match'] = sprintf($this->language->get('text_match'), $item['name']);
		$data['min'] = $item['min'] !== null ? sprintf($this->language->get('text_from'), $this->price($item['min'])) : '';
		$data['median'] = $item['median'] !== null ? sprintf($this->language->get('text_median'), $this->price($item['median'])) : '';
		$data['stores'] = (int)$item['stores'] > 1 ? sprintf($this->language->get('text_stores'), (int)$item['stores']) : '';
		$data['summary'] = implode(' · ', array_filter(array($data['min'], $data['median'], $data['stores'])));
		$data['offers'] = array();
		foreach ($item['offers'] as $o) {
			$data['offers'][] = array(
				'store' => $o['store'],
				'price' => $this->price($o['price']),
				'out' => $o['in_stock'] === false ? $this->language->get('text_out') : '',
			);
		}
		$data['text_source'] = $this->language->get('text_source');
		return $this->load->view('extension/module/satelit', $data);
	}

	/** Prices come in MDL: shown in the shopper's currency when the store has MDL. */
	private function price($mdl) {
		$code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
		$mdlValue = (float)$this->currency->getValue('MDL');
		if ($mdlValue > 0 && $this->currency->has($code)) {
			return $this->currency->format($mdl, $code, (float)$this->currency->getValue($code) / $mdlValue);
		}
		return number_format((float)$mdl, 2, '.', ' ') . ' lei';
	}

	/** Cron: updates the rates when the last ones are older than the TTL. */
	public function cron() {
		$this->response->addHeader('Content-Type: application/json');
		$want = (string)$this->config->get('module_satelit_cron_token');
		$got = isset($this->request->get['token']) ? (string)$this->request->get['token'] : '';
		if ($want === '' || !hash_equals($want, $got)) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 403 Forbidden');
			$this->response->setOutput(json_encode(array('ok' => false, 'message' => 'bad token')));
			return;
		}
		try {
			$r = $this->satelit()->refreshRates(!empty($this->request->get['force']));
		} catch (\Throwable $e) {
			$this->log->write('Satelit rates: ' . $e->getMessage());
			$r = array('ok' => false, 'message' => 'error');
		}
		$this->response->setOutput(json_encode(array('ok' => $r['ok'], 'message' => $r['message'])));
	}
}
