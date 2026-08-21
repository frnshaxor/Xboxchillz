<?php

declare(strict_types=1);

class PaymentOrder
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /** Create a new payment order. */
    public function create(array $data): int
    {
        return $this->conn->insert(
            'INSERT INTO payment_orders(order_id,buyer_name,buyer_contact,amount,status,snap_token,access_secret_hash,client_ip) VALUES(?,?,?,?,?,?,?,?)',
            [$data['order_id'], $data['buyer_name'], $data['buyer_contact'], $data['amount'], $data['status'], $data['snap_token'], $data['access_secret_hash'], $data['client_ip']],
            'sssiisss'
        );
    }

    /** Find order by order_id (with FOR UPDATE for transaction safety). */
    public function findByOrderIdForUpdate(string $orderId): ?array
    {
        $this->conn->beginTransaction();
        $row = $this->conn->selectOne(
            'SELECT * FROM payment_orders WHERE order_id=? FOR UPDATE',
            [$orderId],
            's'
        );

        return $row;
    }

    /** Find order by order_id. */
    public function findByOrderId(string $orderId): ?array
    {
        return $this->conn->selectOne(
            'SELECT * FROM payment_orders WHERE order_id=?',
            [$orderId],
            's'
        );
    }

    /** Update snap_token after checkout creation. */
    public function updateSnapToken(string $orderId, string $snapToken): void
    {
        $this->conn->execute(
            'UPDATE payment_orders SET snap_token=? WHERE order_id=?',
            [$snapToken, $orderId],
            'ss'
        );
    }

    /** Update order after webhook notification. */
    public function updateAfterNotification(int $id, array $data): void
    {
        $this->conn->execute(
            'UPDATE payment_orders SET status=?, token_id=?, midtrans_transaction_id=?, payment_type=?, notification_json=?, paid_at=IF(?="settlement",NOW(),paid_at) WHERE id=?',
            [$data['status'], $data['token_id'], $data['transaction_id'], $data['payment_type'], $data['notification_json'], $data['status'], $id],
            'sissssi'
        );
    }

    /** List recent orders. */
    public function all(): array
    {
        return $this->conn->selectAll(
            'SELECT o.*, at.token FROM payment_orders o LEFT JOIN access_tokens at ON at.id=o.token_id ORDER BY o.created_at DESC'
        );
    }

    /** Commit transaction. */
    public function commit(): void
    {
        $this->conn->commit();
    }

    /** Rollback transaction. */
    public function rollback(): void
    {
        $this->conn->rollback();
    }
}
