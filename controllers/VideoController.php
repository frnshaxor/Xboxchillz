<?php

declare(strict_types=1);

/**
 * Video Controller — upload, delete, video management.
 */
class VideoController
{
    private VideoUpload $uploadService;
    private Video $videoModel;
    private Category $categoryModel;
    private ActivityLog $activityModel;

    public function __construct(Connection $conn)
    {
        $this->uploadService = new VideoUpload($conn);
        $this->videoModel = new Video($conn);
        $this->categoryModel = new Category($conn);
        $this->activityModel = new ActivityLog($conn);
    }

    /** Handle video upload. */
    public function upload(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $title = trim((string) ($_POST['title'] ?? ''));
        $cat = (int) ($_POST['category_id'] ?? 0);
        $files = (new Request())->files('video');

        if (!$files) {
            go('?page=admin&err=upload');
        }

        $uploaded = $this->uploadService->process($title, $cat, $files);

        if ($uploaded > 0) {
            go('?page=admin&uploaded=1');
        } else {
            go('?page=admin&err=upload');
        }
    }

    /** Handle video delete. */
    public function delete(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $id = (int) ($_POST['id'] ?? 0);
        $this->uploadService->delete($id);
        go('?page=admin');
    }

    /** Add category. */
    public function addCategory(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $n = trim((string) ($_POST['name'] ?? ''));
        if ($n) {
            $this->categoryModel->create($n);
            $this->activityModel->record((int) $_SESSION['admin_id'], 'category_add', $n);
        }
        go('?page=admin');
    }

    /** Delete category. */
    public function deleteCategory(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $this->categoryModel->delete($id);
            $this->activityModel->record((int) $_SESSION['admin_id'], 'category_delete', (string) $id);
        }
        go('?page=admin');
    }

    // ─── Chunked Upload API (Bulk Upload) ───

    /** Initialize a chunked upload session. */
    public function uploadInit(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $filename = trim((string) ($_POST['filename'] ?? ''));
        $totalSize = (int) ($_POST['total_size'] ?? 0);

        if (!$filename || $totalSize <= 0) {
            Response::json(['error' => 'Nama file dan ukuran wajib diisi.'], 422);
        }

        $result = $this->uploadService->createUploadSession($filename, $totalSize);
        Response::json($result, isset($result['error']) ? 422 : 200);
    }

    /** Upload a single chunk. */
    public function uploadChunk(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $uploadId = trim((string) ($_POST['upload_id'] ?? ''));
        $chunkNumber = (int) ($_POST['chunk_number'] ?? -1);

        if (!$uploadId || $chunkNumber < 0 || !isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'Parameter chunk tidak valid.'], 422);
        }

        $result = $this->uploadService->saveChunk($uploadId, $chunkNumber, $_FILES['chunk']['tmp_name']);
        Response::json($result, isset($result['error']) ? 422 : 200);
    }

    /** Assemble chunks and process video. */
    public function uploadComplete(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $uploadId = trim((string) ($_POST['upload_id'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));

        if (!$uploadId) {
            Response::json(['error' => 'Upload ID wajib diisi.'], 422);
        }

        $result = $this->uploadService->assembleAndProcess($uploadId, $categoryId, $title);
        Response::json($result, isset($result['error']) ? 422 : 200);
    }

    /** Get upload status (list already-uploaded chunks). */
    public function uploadStatus(): void
    {
        AuthMiddleware::requireAdmin();

        $uploadId = trim((string) ($_GET['upload_id'] ?? ''));
        if (!$uploadId) {
            Response::json(['error' => 'Upload ID wajib diisi.'], 422);
        }

        $chunks = $this->uploadService->listChunks($uploadId);
        $dir = UPLOADS_DIR . '/' . preg_replace('#[^a-f0-9]#', '', $uploadId);
        $meta = is_dir($dir) ? @json_decode(file_get_contents($dir . '/meta.json'), true) ?: [] : [];
        $totalChunks = (int) ceil(($meta['total_size'] ?? 0) / VideoUpload::CHUNK_SIZE);
        Response::json([
            'ok' => true,
            'uploaded_chunks' => $chunks,
            'total_chunks' => $totalChunks,
            'filename' => $meta['filename'] ?? '',
        ]);
    }

    /** Abort/cancel a chunked upload. */
    public function uploadAbort(): void
    {
        AuthMiddleware::requireAdmin();
        CsrfMiddleware::validate();

        $uploadId = trim((string) ($_POST['upload_id'] ?? ''));
        if ($uploadId) {
            $this->uploadService->cleanup($uploadId);
        }
        Response::json(['ok' => true]);
    }
}
