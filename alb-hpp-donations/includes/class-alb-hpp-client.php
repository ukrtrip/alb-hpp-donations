<?php
namespace ALB;
if (!defined('ABSPATH')) exit;

class AlbHppClient {
    private string $baseUrl;
    private string $apiVersion;
    private string $merchantId;
    private ?string $deviceId;
    private ?string $refreshToken;

    public function __construct(array $opt) {
        $this->baseUrl      = rtrim($opt['baseUrl'] ?? ALB_HPP_PROD_BASE,'/');
        $this->apiVersion   = $opt['apiVersion'] ?? 'v1';
        $this->merchantId   = $opt['merchantId'] ?? '';
        $this->deviceId     = $opt['deviceId'] ?? null;
        $this->refreshToken = $opt['refreshToken'] ?? null;

        if (!$this->merchantId) {
//            throw new \RuntimeException('Не задано merchantId у налаштуваннях плагіна.');
			throw new \RuntimeException(_e('MerchantId is not set in the plugin settings.','alb-hpp-donations'));
			
        }
        if (!$this->deviceId || !$this->refreshToken) {
//            throw new \RuntimeException('Не задано deviceId/refreshToken у налаштуваннях плагіна.');
			throw new \RuntimeException(_e('DeviceId/refreshToken not set in plugin settings.','alb-hpp-donations'));
			
        }
    }

    /** Create HPP order */
    public function create_hpp_order(array $body): array {
        $body['merchantId']     = $this->merchantId;
        $body['hppPayType']     = 'PURCHASE';
        $body['statusPageType'] = 'STATUS_PAGE';

        return $this->http('POST', '/ecom/execute_request/hpp/v1/create-order', $body, true);
    }

    /** Get order info by hppOrderId */
    public function get_order(string $hppOrderId): array {
        return $this->http('POST', '/ecom/execute_request/hpp/v1/operations', ['hppOrderId' => $hppOrderId], true);
    }

    private function http(string $method, string $path, array $json, bool $auth): array {
        $url = $this->baseUrl.$path;

        $headers = [
            'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
        ];
		$headers['x-api_version'] = $this->apiVersion;
        if ($auth) {
            $headers['x-device_id'] = $this->deviceId;
            $headers['x-refresh_token'] = $this->refreshToken;
        }

		// === ALB_REQ_LOG: log outgoing request ===
		if (defined('ALB_HPP_DEBUG') && ALB_HPP_DEBUG) {
			$dir = WP_CONTENT_DIR . '/uploads/alb-logs';
			if (!is_dir($dir)) @wp_mkdir_p($dir);
			@file_put_contents(
				$dir.'/debug.log',
				date('c')." BANK REQUEST {$method} {$url} headers=".wp_json_encode($headers, JSON_UNESCAPED_UNICODE).
				" body=".wp_json_encode($json, JSON_UNESCAPED_UNICODE).PHP_EOL,
				FILE_APPEND
			);
		}

        $resp = wp_remote_request($url, [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 20,
            'body'    => $json ? wp_json_encode($json, JSON_UNESCAPED_UNICODE) : null,
        ]);
		
		// === ALB_CREATE_ORDER_LOG: extended logging of raw response ===
		if (defined('ALB_HPP_DEBUG') && ALB_HPP_DEBUG) {
			$dir = WP_CONTENT_DIR . '/uploads/alb-logs';
			if (!is_dir($dir)) @wp_mkdir_p($dir);
			$reqId = wp_remote_retrieve_header($resp, 'x-request_id');
			@file_put_contents(
				$dir . '/debug.log',
				date('c') . " HTTP {$method} {$path} reqId={$reqId} resp=" . wp_json_encode($resp) . PHP_EOL,
				FILE_APPEND
			);
		}


        if (is_wp_error($resp)) {
            throw new \RuntimeException($resp->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($code === 401 && $auth) {
            throw new \RuntimeException('401 Unauthorized від API. Check deviceId/refreshToken.');
        }

        if ($code >= 300) {
            throw new \RuntimeException("API {$path} HTTP {$code}: {$body}");
        }

        return is_array($data) ? $data : [];
    }
}
