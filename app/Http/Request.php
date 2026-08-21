<?php
declare(strict_types=1);

/**
 * HTTP Request — wraps superglobals for clean input handling.
 */
class Request
{
    private array $get;
    private array $post;
    private array $files;
    private array $server;
    private string $method;
    private ?string $body = null;

    public function __construct()
    {
        $this->get    = $_GET;
        $this->post   = $_POST;
        $this->files  = $_FILES;
        $this->server = $_SERVER;
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Get a query parameter. */
    public function query(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    /** Get a POST parameter. */
    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    /** Get a string input, trimmed. */
    public function string(string $key, string $default = ''): string
    {
        return trim((string)($this->post[$key] ?? $default));
    }

    /** Get an integer input. */
    public function integer(string $key, int $default = 0): int
    {
        return (int)($this->post[$key] ?? $default);
    }

    /** Get a file upload. */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /** Get all file uploads (normalized for multi-file). */
    public function files(string $key): array
    {
        $files = $this->files[$key] ?? null;
        if (!$files) return [];

        // Normalize multi-file upload
        if (is_array($files['name'] ?? null)) {
            $list = [];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $list[] = [
                        'name'     => $files['name'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'size'     => $files['size'][$i],
                    ];
                }
            }
            return $list;
        }

        // Single file
        if (($files['error'] ?? -1) === UPLOAD_ERR_OK) {
            return [[
                'name'     => $files['name'],
                'tmp_name' => $files['tmp_name'],
                'size'     => $files['size'],
            ]];
        }

        return [];
    }

    /** Get the request method. */
    public function method(): string
    {
        return $this->method;
    }

    /** Is this a POST request? */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** Get client IP. */
    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
        return substr($ip, 0, 64);
    }

    /** Get User-Agent. */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /** Get Referer. */
    public function referer(): string
    {
        return $this->server['HTTP_REFERER'] ?? '';
    }

    /** Get raw JSON body. */
    public function jsonBody(): ?array
    {
        if ($this->body === null) {
            $this->body = file_get_contents('php://input');
        }
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : null;
    }

    /** Get a specific server variable. */
    public function server(string $key, string $default = ''): string
    {
        return $this->server[$key] ?? $default;
    }

    /** Check HTTPS. */
    public function isHttps(): bool
    {
        return !empty($this->server['HTTPS']) || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** Get the page parameter (main routing key). */
    public function page(): string
    {
        return $this->query('page', 'home');
    }

    /** Get the API operation parameter. */
    public function op(): string
    {
        return $this->query('op', '');
    }
}
