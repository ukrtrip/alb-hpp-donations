<?php
if (!defined('ABSPATH')) exit;

class ALB_Payments {
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'alb_hpp_payments';
    }

    /** Створення таблиці (викликати під час активації) */
    public static function create_table() {
        global $wpdb;
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            merchant_request_id VARCHAR(64) NULL,
            hpp_order_id VARCHAR(64) NULL,
            amount_coin BIGINT NULL,
            currency VARCHAR(8) NULL DEFAULT 'UAH',
            status VARCHAR(32) NULL,
            rrn VARCHAR(32) NULL,
            approval_code VARCHAR(32) NULL,
            pan_mask VARCHAR(32) NULL,
            purpose TEXT NULL,
            customer_email VARCHAR(191) NULL,
            customer_first_name VARCHAR(191) NULL,
            customer_last_name VARCHAR(191) NULL,
            raw_callback LONGTEXT NULL,
            operations LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY idx_hpp_order_id (hpp_order_id),
            KEY idx_merchant_request_id (merchant_request_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /** Вставити запис при створенні замовлення */
    public static function insert_created(array $data) {
        global $wpdb;
        $table = self::table();
        $wpdb->insert($table, [
            'merchant_request_id' => $data['merchantRequestId'] ?? null,
            'hpp_order_id'        => $data['hppOrderId'] ?? null,
            'amount_coin'         => $data['coinAmount'] ?? null,
            'status'              => 'CREATED',
            'purpose'             => $data['purpose'] ?? null,
            'customer_email'      => $data['customerEmail'] ?? null,
            'customer_first_name' => $data['firstName'] ?? null,
            'customer_last_name'  => $data['lastName'] ?? null,
        ], [
            '%s','%s','%d','%s','%s','%s','%s','%s'
        ]);
        return (int)$wpdb->insert_id;
    }

    /** Оновити/вставити за hppOrderId з callback */
    public static function upsert_from_callback(array $payload) {
        global $wpdb;
        $table = self::table();

        $hppOrderId = $payload['hppOrderId'] ?? null;
        if (!$hppOrderId) return false;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE hpp_order_id = %s LIMIT 1",
            $hppOrderId
        ), ARRAY_A);

        $update = [
            'hpp_order_id' => $hppOrderId,
            'status'       => $payload['orderStatus'] ?? null,
            'rrn'          => $payload['rrn'] ?? null,
            'approval_code'=> $payload['approvalCode'] ?? null,
            'pan_mask'     => $payload['senderPanMask'] ?? null,
            'raw_callback' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
        if (!empty($payload['coinAmount'])) $update['amount_coin'] = (int)$payload['coinAmount'];
        if (!empty($payload['currency']))   $update['currency']   = sanitize_text_field($payload['currency']);
        if (!empty($payload['purpose']))    $update['purpose']    = sanitize_text_field($payload['purpose']);

        if ($row) {
            $wpdb->update($table, $update, ['id' => (int)$row['id']], null, ['%d']);
            return (int)$row['id'];
        } else {
            $merchantRequestId = $payload['merchantRequestId'] ?? null;
            if ($merchantRequestId) {
                $row2 = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE merchant_request_id = %s LIMIT 1",
                    $merchantRequestId
                ), ARRAY_A);
                if ($row2) {
                    $wpdb->update($table, $update, ['id' => (int)$row2['id']], null, ['%d']);
                    return (int)$row2['id'];
                }
            }
            $update['merchant_request_id'] = $merchantRequestId;
            $wpdb->insert($table, $update);
            return (int)$wpdb->insert_id;
        }
    }

    /** (Опційно) оновити колонку operations з /operations */
    public static function update_operations(string $hppOrderId, array $operations) {
        global $wpdb;
        $table = self::table();
        $wpdb->update($table, [
            'operations' => wp_json_encode($operations, JSON_UNESCAPED_UNICODE)
        ], [
            'hpp_order_id' => $hppOrderId
        ]);
    }

    /** Список платежів для адмінки (простий пагінатор) */
    public static function list(int $page = 1, int $per_page = 20): array {
        global $wpdb;
        $table = self::table();
        $offset = ($page - 1) * $per_page;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset),
            ARRAY_A
        );
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        return ['rows' => $rows, 'total' => $total];
    }
}
