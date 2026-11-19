<?php
/**
 * Satelit Moldova for OpenCart 4.x: settings, BNM rates and market prices.
 *
 * Admin routes (OpenCart checks user_token on every one of them):
 *   extension/satelit/module/satelit         settings form
 *   extension/satelit/module/satelit.save    save (AJAX)
 *   extension/satelit/module/satelit.test    "Test connection" (AJAX, GET /v1/me, 0 credits)
 *   extension/satelit/module/satelit.rates   "Update rates now" (AJAX, /v1/rates, 1 credit)
 */
namespace Opencart\Admin\Controller\Extension\Satelit\Module;

require_once DIR_EXTENSION . 'satelit/system/library/satelit_opencart.php';

class Satelit extends \Opencart\System\Engine\Controller {
	private function satelit(): \SatelitOpenCart {
		return new \SatelitOpenCart($this->db, $this->config, $this->cache, $this->log);
	}

	private function link(string $route, string $args = ''): string {
		return $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . $args);
	}

	public function index(): void {
		$this->load->language('extension/satelit/module/satelit');
		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->link('common/dashboard')],
			['text' => $this->language->get('text_extension'), 'href' => $this->link('marketplace/extension', '&type=module')],
			['text' => $this->language->get('heading_title'), 'href' => $this->link('extension/satelit/module/satelit')],
		];
		$data['save'] = $this->link('extension/satelit/module/satelit.save');
		$data['back'] = $this->link('marketplace/extension', '&type=module');
		$data['test'] = $this->link('extension/satelit/module/satelit.test');
		$data['rates'] = $this->link('extension/satelit/module/satelit.rates');

		$defaults = ['language' => 'auto', 'rates_ttl' => 3, 'prices_ttl' => 6, 'offers' => 5, 'status' => 0];
		foreach ($defaults as $k => $v) {
			$saved = $this->config->get('module_satelit_' . $k);
			$data['module_satelit_' . $k] = ($saved === null || $saved === '') ? $v : $saved;
		}
		$data['key_saved'] = trim((string)$this->config->get('module_satelit_api_key')) !== '';
		$data['languages'] = ['auto' => $this->language->get('text_auto'), 'ro' => 'Română', 'ru' => 'Русский', 'en' => 'English', 'uk' => 'Українська'];
		$data['last_log'] = $this->satelit()->lastLog();
		$data['cron_url'] = HTTP_CATALOG . 'index.php?route=extension/satelit/module/satelit.cron&token=' . $this->cronToken();
		$data['engine_on'] = $this->config->get('config_currency_engine') == 'satelit' && $this->config->get('currency_satelit_status');
		$data['currency_link'] = $this->link('marketplace/extension', '&type=currency');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/satelit/module/satelit', $data));
	}

	private function cronToken(): string {
		$t = (string)$this->config->get('module_satelit_cron_token');
		return $t !== '' ? $t : bin2hex(random_bytes(16));
	}

	public function save(): void {
		$this->load->language('extension/satelit/module/satelit');
		$json = [];
		$p = $this->request->post;

		if (!$this->user->hasPermission('modify', 'extension/satelit/module/satelit')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		} else {
			$key = isset($p['module_satelit_api_key']) ? trim((string)$p['module_satelit_api_key']) : '';
			if ($key !== '' && !preg_match('~^sat_[A-Za-z0-9_]{8,}$~', $key)) {
				$json['error']['api_key'] = $this->language->get('error_api_key');
			}
			foreach (['rates_ttl' => [1, 24], 'prices_ttl' => [1, 48], 'offers' => [1, 10]] as $k => $r) {
				$v = isset($p['module_satelit_' . $k]) ? (string)$p['module_satelit_' . $k] : '';
				if (!ctype_digit($v) || (int)$v < $r[0] || (int)$v > $r[1]) {
					$json['error'][$k] = sprintf($this->language->get('error_range'), $r[0], $r[1]);
				}
			}
			if ($json && !isset($json['error']['warning'])) {
				$json['error']['warning'] = $this->language->get('error_form');
			}
		}

		if (!$json) {
			$languages = ['auto', 'ro', 'ru', 'en', 'uk'];
			$settings = [
				// An empty key field keeps the saved key: the form never shows it.
				'module_satelit_api_key' => $key !== '' ? $key : (string)$this->config->get('module_satelit_api_key'),
				'module_satelit_language' => in_array($p['module_satelit_language'] ?? '', $languages, true) ? $p['module_satelit_language'] : 'auto',
				'module_satelit_rates_ttl' => (int)$p['module_satelit_rates_ttl'],
				'module_satelit_prices_ttl' => (int)$p['module_satelit_prices_ttl'],
				'module_satelit_offers' => (int)$p['module_satelit_offers'],
				'module_satelit_status' => !empty($p['module_satelit_status']) ? 1 : 0,
				'module_satelit_cron_token' => $this->cronToken(),
			];
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('module_satelit', $settings);
			$this->satelit()->clearCache();
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/** Test connection: the key typed in the form, or the saved one. */
	public function test(): void {
		$this->load->language('extension/satelit/module/satelit');
		$json = [];
		$key = trim((string)($this->request->post['module_satelit_api_key'] ?? ''));
		if ($key === '') {
			$key = (string)$this->config->get('module_satelit_api_key');
		}
		if (!$this->user->hasPermission('modify', 'extension/satelit/module/satelit')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif ($key === '') {
			$json['error'] = $this->language->get('error_no_key');
		} else {
			$res = $this->satelit()->client('en', $key)->me();
			if (!$res['ok']) {
				$json['error'] = $res['error']['code'] . ': ' . $res['error']['message'];
			} elseif (empty($res['data']['plan'])) {
				$json['error'] = $this->language->get('error_no_plan');
			} else {
				$c = $res['data']['credits'];
				$json['success'] = sprintf($this->language->get('text_connected'), $res['data']['plan'],
					number_format((int)$c['limit'] - (int)$c['used'], 0, '.', ' '), number_format((int)$c['limit'], 0, '.', ' '), substr((string)$c['reset'], 0, 10));
			}
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/** Update rates now (always calls the API, 1 credit). */
	public function rates(): void {
		$this->load->language('extension/satelit/module/satelit');
		$json = [];
		if ($this->request->server['REQUEST_METHOD'] != 'POST' || !$this->user->hasPermission('modify', 'extension/satelit/module/satelit')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!$this->user->hasPermission('modify', 'localisation/currency')) {
			$json['error'] = $this->language->get('error_permission_currency');
		} else {
			$r = $this->satelit()->refreshRates(true);
			$json[$r['ok'] ? 'success' : 'error'] = $r['message'];
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	// ---- per-product search term (events added at install) ----

	/** admin/view/catalog/product_form/after */
	public function productForm(string &$route, array &$args, string &$output): void {
		$this->load->language('extension/satelit/module/satelit');
		$id = (int)($this->request->get['product_id'] ?? 0);
		$term = $id ? $this->satelit()->productTerm($id) : '';
		$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
		$field = '<fieldset><legend>Satelit</legend><div class="row mb-3"><label for="input-satelit-term" class="col-sm-2 col-form-label">' . $e($this->language->get('entry_term')) . '</label>'
			. '<div class="col-sm-10"><input type="text" name="satelit_term" maxlength="100" value="' . $e($term) . '" placeholder="' . $e($this->language->get('placeholder_term'))
			. '" id="input-satelit-term" class="form-control"/><div class="form-text">' . $e($this->language->get('help_term')) . '</div></div></div></fieldset>';
		$output = \SatelitOpenCart::injectField($output, $field);
	}

	/** admin/model/catalog/product.addProduct/after and .editProduct/after */
	public function productSave(string &$route, array &$args, &$output): void {
		if (!isset($this->request->post['satelit_term']) || !$this->user->hasPermission('modify', 'catalog/product')) {
			return;
		}
		$id = str_contains($route, 'addProduct') ? (int)$output : (int)$args[0];
		$this->satelit()->saveProductTerm($id, (string)$this->request->post['satelit_term']);
	}

	public function install(): void {
		\SatelitOpenCart::install($this->db);
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('satelit');
		$events = [
			['admin/view/catalog/product_form/after', 'productForm', 'Satelit search term field in the product form'],
			['admin/model/catalog/product.addProduct/after', 'productSave', 'Saves the Satelit search term of a new product'],
			['admin/model/catalog/product.editProduct/after', 'productSave', 'Saves the Satelit search term of a product'],
		];
		foreach ($events as $ev) {
			$this->model_setting_event->addEvent([
				'code' => 'satelit', 'description' => $ev[2], 'trigger' => $ev[0],
				'action' => 'extension/satelit/module/satelit.' . $ev[1], 'status' => 1, 'sort_order' => 0,
			]);
		}
	}

	public function uninstall(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('satelit');
		\SatelitOpenCart::uninstall($this->db);
	}
}
