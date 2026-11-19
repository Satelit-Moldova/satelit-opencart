<?php
/**
 * Satelit (BNM rates) currency rate engine for OpenCart 3.0.4+.
 *
 * OpenCart calls currency() from System > Localisation > Currencies > Refresh and,
 * with "Auto Update Currency" on, from the dashboard. The API key and TTLs live in
 * Extensions > Modules > Satelit Moldova.
 */
require_once DIR_SYSTEM . 'library/satelit_opencart.php';

class ControllerExtensionCurrencySatelit extends Controller {
	private $error = array();

	private function link($route, $args = '') {
		return $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . $args, true);
	}

	public function index() {
		$this->load->language('extension/currency/satelit');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			$status = !empty($this->request->post['currency_satelit_status']) ? 1 : 0;
			$this->model_setting_setting->editSetting('currency_satelit', array('currency_satelit_status' => $status));
			// Enabling makes Satelit the store's rate engine (the form says so).
			if ($status) {
				SatelitOpenCart::setEngine($this->db, 'satelit');
			}
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->link('marketplace/extension', '&type=currency'));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'), 'href' => $this->link('common/dashboard')),
			array('text' => $this->language->get('text_extension'), 'href' => $this->link('marketplace/extension', '&type=currency')),
			array('text' => $this->language->get('heading_title'), 'href' => $this->link('extension/currency/satelit')),
		);
		$data['action'] = $this->link('extension/currency/satelit');
		$data['cancel'] = $this->link('marketplace/extension', '&type=currency');
		$data['settings'] = $this->link('extension/module/satelit');
		$data['currency_satelit_status'] = isset($this->request->post['currency_satelit_status']) ? $this->request->post['currency_satelit_status'] : $this->config->get('currency_satelit_status');
		$data['key_saved'] = trim((string)$this->config->get('module_satelit_api_key')) !== '';
		$data['engine_on'] = $this->config->get('config_currency_engine') == 'satelit';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/currency/satelit', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/currency/satelit')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}

	public function install() {
		SatelitOpenCart::install($this->db);
	}

	public function uninstall() {
		if ($this->config->get('config_currency_engine') == 'satelit') {
			$this->load->model('setting/setting');
			SatelitOpenCart::setEngine($this->db, '');
		}
	}

	/** Dashboard auto update: respects the rates TTL, so a busy dashboard costs nothing. */
	public function currency() {
		if (!$this->config->get('currency_satelit_status')) {
			return null;
		}
		try {
			$s = new SatelitOpenCart($this->db, $this->config, $this->cache, $this->log);
			$s->refreshRates(false);
		} catch (\Throwable $e) {
			$this->log->write('Satelit rates: ' . $e->getMessage());
		}
		return null;
	}
}
