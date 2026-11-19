<?php
/**
 * Satelit Moldova for OpenCart 3.0.x: settings, BNM rates and market prices.
 *
 * Admin routes (OpenCart checks user_token on every one of them):
 *   extension/module/satelit         settings form (POST saves)
 *   extension/module/satelit/test    "Test connection" (AJAX, GET /v1/me, 0 credits)
 *   extension/module/satelit/rates   "Update rates now" (AJAX POST, /v1/rates, 1 credit)
 */
require_once DIR_SYSTEM . 'library/satelit_opencart.php';

class ControllerExtensionModuleSatelit extends Controller {
	private $error = array();

	private function satelit() {
		return new SatelitOpenCart($this->db, $this->config, $this->cache, $this->log);
	}

	private function link($route, $args = '') {
		return $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . $args, true);
	}

	public function index() {
		$this->load->language('extension/module/satelit');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			$post = $this->request->post;
			// An empty key field keeps the saved key: the form never shows it.
			if (trim((string)$post['module_satelit_api_key']) === '') {
				$post['module_satelit_api_key'] = (string)$this->config->get('module_satelit_api_key');
			} else {
				$post['module_satelit_api_key'] = trim($post['module_satelit_api_key']);
			}
			$post['module_satelit_cron_token'] = $this->cronToken();
			$this->model_setting_setting->editSetting('module_satelit', $post);
			$this->satelit()->clearCache();
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->link('extension/module/satelit'));
		}

		$data = array();
		foreach (array('warning', 'api_key', 'rates_ttl', 'prices_ttl', 'offers') as $k) {
			$data['error_' . $k] = isset($this->error[$k]) ? $this->error[$k] : '';
		}
		$data['success'] = '';
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}

		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'), 'href' => $this->link('common/dashboard')),
			array('text' => $this->language->get('text_extension'), 'href' => $this->link('marketplace/extension', '&type=module')),
			array('text' => $this->language->get('heading_title'), 'href' => $this->link('extension/module/satelit')),
		);
		$data['action'] = $this->link('extension/module/satelit');
		$data['cancel'] = $this->link('marketplace/extension', '&type=module');
		$data['test'] = html_entity_decode($this->link('extension/module/satelit/test'), ENT_QUOTES, 'UTF-8');
		$data['rates'] = html_entity_decode($this->link('extension/module/satelit/rates'), ENT_QUOTES, 'UTF-8');

		$defaults = array('language' => 'auto', 'rates_ttl' => 3, 'prices_ttl' => 6, 'offers' => 5, 'status' => 0);
		foreach ($defaults as $k => $v) {
			$name = 'module_satelit_' . $k;
			if (isset($this->request->post[$name])) {
				$data[$name] = $this->request->post[$name];
			} else {
				$saved = $this->config->get($name);
				$data[$name] = ($saved === null || $saved === '') ? $v : $saved;
			}
		}
		$data['key_saved'] = trim((string)$this->config->get('module_satelit_api_key')) !== '';
		$data['languages'] = array('auto' => $this->language->get('text_auto'), 'ro' => 'Română', 'ru' => 'Русский', 'en' => 'English', 'uk' => 'Українська');

		$data['last_log'] = $this->satelit()->lastLog();
		$data['cron_url'] = HTTPS_CATALOG . 'index.php?route=extension/module/satelit/cron&token=' . $this->cronToken();

		// OpenCart 3.0.4+ has currency rate engines; 3.0.3.x does not.
		$data['has_engines'] = is_file(DIR_APPLICATION . 'controller/extension/extension/currency.php');
		$data['engine_on'] = $this->config->get('config_currency_engine') == 'satelit' && $this->config->get('currency_satelit_status');
		$data['currency_link'] = $this->link('marketplace/extension', '&type=currency');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/module/satelit', $data));
	}

	private function cronToken() {
		$t = (string)$this->config->get('module_satelit_cron_token');
		return $t !== '' ? $t : bin2hex(random_bytes(16));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/satelit')) {
			$this->error['warning'] = $this->language->get('error_permission');
			return false;
		}
		$p = $this->request->post;
		$key = isset($p['module_satelit_api_key']) ? trim((string)$p['module_satelit_api_key']) : '';
		if ($key !== '' && !preg_match('~^sat_[A-Za-z0-9_]{8,}$~', $key)) {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}
		$ranges = array('rates_ttl' => array(1, 24), 'prices_ttl' => array(1, 48), 'offers' => array(1, 10));
		foreach ($ranges as $k => $r) {
			$v = isset($p['module_satelit_' . $k]) ? $p['module_satelit_' . $k] : '';
			if (!ctype_digit((string)$v) || (int)$v < $r[0] || (int)$v > $r[1]) {
				$this->error[$k] = sprintf($this->language->get('error_range'), $r[0], $r[1]);
			}
		}
		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}
		return !$this->error;
	}

	private function json($out) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($out));
	}

	/** Test connection: the key typed in the form, or the saved one. */
	public function test() {
		$this->load->language('extension/module/satelit');
		if (!$this->user->hasPermission('modify', 'extension/module/satelit')) {
			return $this->json(array('error' => $this->language->get('error_permission')));
		}
		$key = isset($this->request->post['key']) ? trim((string)$this->request->post['key']) : '';
		if ($key === '') {
			$key = (string)$this->config->get('module_satelit_api_key');
		}
		if ($key === '') {
			return $this->json(array('error' => $this->language->get('error_no_key')));
		}
		$res = $this->satelit()->client('en', $key)->me();
		if (!$res['ok']) {
			return $this->json(array('error' => $res['error']['code'] . ': ' . $res['error']['message']));
		}
		$d = $res['data'];
		if (empty($d['plan'])) {
			return $this->json(array('error' => $this->language->get('error_no_plan')));
		}
		$left = (int)$d['credits']['limit'] - (int)$d['credits']['used'];
		return $this->json(array('success' => sprintf($this->language->get('text_connected'),
			$d['plan'], number_format($left, 0, '.', ' '), number_format((int)$d['credits']['limit'], 0, '.', ' '), substr((string)$d['credits']['reset'], 0, 10))));
	}

	/** Update rates now (always calls the API, 1 credit). */
	public function rates() {
		$this->load->language('extension/module/satelit');
		if ($this->request->server['REQUEST_METHOD'] != 'POST' || !$this->user->hasPermission('modify', 'extension/module/satelit')) {
			return $this->json(array('error' => $this->language->get('error_permission')));
		}
		if (!$this->user->hasPermission('modify', 'localisation/currency')) {
			return $this->json(array('error' => $this->language->get('error_permission_currency')));
		}
		$r = $this->satelit()->refreshRates(true);
		return $this->json($r['ok'] ? array('success' => $r['message']) : array('error' => $r['message']));
	}

	// ---- per-product search term (events added at install) ----

	/** admin/view/catalog/product_form/after */
	public function productForm(&$route, &$data, &$output) {
		$this->load->language('extension/module/satelit');
		$id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
		$term = $id ? $this->satelit()->productTerm($id) : '';
		$field = '<div class="form-group"><label class="col-sm-2 control-label" for="input-satelit-term"><span data-toggle="tooltip" title="'
			. htmlspecialchars($this->language->get('help_term'), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($this->language->get('entry_term'), ENT_QUOTES, 'UTF-8') . '</span></label>'
			. '<div class="col-sm-10"><input type="text" name="satelit_term" maxlength="100" value="' . htmlspecialchars($term, ENT_QUOTES, 'UTF-8')
			. '" placeholder="' . htmlspecialchars($this->language->get('placeholder_term'), ENT_QUOTES, 'UTF-8') . '" id="input-satelit-term" class="form-control"/></div></div>';
		$output = SatelitOpenCart::injectField($output, $field);
	}

	/** admin/model/catalog/product/addProduct/after and editProduct/after */
	public function productSave(&$route, &$args, &$output) {
		if (!isset($this->request->post['satelit_term']) || !$this->user->hasPermission('modify', 'catalog/product')) {
			return;
		}
		$id = (strpos($route, 'addProduct') !== false) ? (int)$output : (int)$args[0];
		$this->satelit()->saveProductTerm($id, $this->request->post['satelit_term']);
	}

	public function install() {
		SatelitOpenCart::install($this->db);
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('satelit');
		$this->model_setting_event->addEvent('satelit', 'admin/view/catalog/product_form/after', 'extension/module/satelit/productForm');
		$this->model_setting_event->addEvent('satelit', 'admin/model/catalog/product/addProduct/after', 'extension/module/satelit/productSave');
		$this->model_setting_event->addEvent('satelit', 'admin/model/catalog/product/editProduct/after', 'extension/module/satelit/productSave');
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('satelit');
		SatelitOpenCart::uninstall($this->db);
	}
}
