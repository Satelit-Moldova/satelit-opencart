<?php
/**
 * Satelit (BNM rates) currency rate engine for OpenCart 4.x.
 *
 * OpenCart calls currency($default) when a currency or the settings are saved and
 * from System > Localisation > Currencies > Refresh; the storefront copy runs from
 * the built-in "currency" cron task. The key and TTLs live in Satelit Moldova.
 */
namespace Opencart\Admin\Controller\Extension\Satelit\Currency;

require_once DIR_EXTENSION . 'satelit/system/library/satelit_opencart.php';

class Satelit extends \Opencart\System\Engine\Controller {
	private function link(string $route, string $args = ''): string {
		return $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . $args);
	}

	public function index(): void {
		$this->load->language('extension/satelit/currency/satelit');
		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->link('common/dashboard')],
			['text' => $this->language->get('text_extension'), 'href' => $this->link('marketplace/extension', '&type=currency')],
			['text' => $this->language->get('heading_title'), 'href' => $this->link('extension/satelit/currency/satelit')],
		];
		$data['save'] = $this->link('extension/satelit/currency/satelit.save');
		$data['back'] = $this->link('marketplace/extension', '&type=currency');
		$data['settings'] = $this->link('extension/satelit/module/satelit');
		$data['currency_satelit_status'] = $this->config->get('currency_satelit_status');
		$data['key_saved'] = trim((string)$this->config->get('module_satelit_api_key')) !== '';
		$data['engine_on'] = $this->config->get('config_currency_engine') == 'satelit';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/satelit/currency/satelit', $data));
	}

	public function save(): void {
		$this->load->language('extension/satelit/currency/satelit');
		$json = [];
		if (!$this->user->hasPermission('modify', 'extension/satelit/currency/satelit')) {
			$json['error'] = $this->language->get('error_permission');
		}
		if (!$json) {
			$status = !empty($this->request->post['currency_satelit_status']) ? 1 : 0;
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('currency_satelit', ['currency_satelit_status' => $status]);
			// Enabling makes Satelit the store's rate engine (the form says so).
			if ($status) {
				\SatelitOpenCart::setEngine($this->db, 'satelit');
			}
			$json['success'] = $this->language->get('text_success');
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		\SatelitOpenCart::install($this->db);
	}

	public function uninstall(): void {
		if ($this->config->get('config_currency_engine') == 'satelit') {
			$this->load->model('setting/setting');
			\SatelitOpenCart::setEngine($this->db, '');
		}
	}

	/** Called by OpenCart with the default currency code. */
	public function currency(string $default = ''): void {
		if (!$this->config->get('currency_satelit_status')) {
			return;
		}
		$route = (string)($this->request->get['route'] ?? '');
		// Refresh and saving a currency call the API (1 credit). Other callers (any
		// settings save) only update when the last rates are older than the TTL,
		// unless the default currency changed, which changes every value.
		$force = str_starts_with($route, 'localisation/currency');
		if ($default && strtoupper($default) !== strtoupper((string)$this->config->get('config_currency'))) {
			$force = true;
		}
		try {
			(new \SatelitOpenCart($this->db, $this->config, $this->cache, $this->log))->refreshRates($force, $default);
		} catch (\Throwable $e) {
			$this->log->write('Satelit rates: ' . $e->getMessage());
		}
	}
}
