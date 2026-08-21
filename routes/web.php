<?php
declare(strict_types=1);

/**
 * Web Routes — maps ?page= parameter to controller actions.
 */

$page = $request->page();
$conn = Connection::getInstance();

// ─── Public pages ───
switch ($page) {
    case 'login':
        if ($request->isPost()) {
            (new AuthController($conn))->login();
        } else {
            (new AuthController($conn))->loginForm();
        }
        return;

    case 'logout':
        (new AuthController($conn))->logout();
        return;

    case 'admin':
        (new AdminController($conn))->index();
        return;

    case 'watch':
        (new WatchController($conn))->show();
        return;

    case 'contact':
        (new ContactController($conn))->show();
        return;

    // ─── Media delivery (poster, preview, protected) ───
    case 'media':
    case 'poster':
    case 'preview':
        (new MediaController($conn))->serve();
        return;

    case 'download':
        (new MediaController($conn))->download();
        return;

    // ─── Token verification ───
    case 'verify-token':
        if ($request->isPost()) {
            (new TokenController($conn))->verify();
        }
        return;

    case 'revoke-access':
        if ($request->isPost()) {
            (new TokenController($conn))->revoke();
        } else {
            revoke_access();
            go('.');
        }
        return;

    // ─── Admin form POSTs ───
    case 'save-settings':
        if ($request->isPost()) {
            (new AdminController($conn))->saveSettings();
        }
        return;

    case 'save-contact':
        if ($request->isPost()) {
            (new AdminController($conn))->saveContact();
        }
        return;

    case 'upload':
        if ($request->isPost()) {
            (new VideoController($conn))->upload();
        }
        return;

    case 'delete-video':
        if ($request->isPost()) {
            (new VideoController($conn))->delete();
        }
        return;

    case 'add-category':
        if ($request->isPost()) {
            (new VideoController($conn))->addCategory();
        }
        return;

    case 'delete-category':
        if ($request->isPost()) {
            (new VideoController($conn))->deleteCategory();
        }
        return;

    case 'token-create':
        if ($request->isPost()) {
            (new TokenController($conn))->create();
        }
        return;

    case 'token-toggle':
        if ($request->isPost()) {
            (new TokenController($conn))->toggle();
        }
        return;

    case 'token-delete':
        if ($request->isPost()) {
            (new TokenController($conn))->delete();
        }
        return;

    case 'account-update':
        if ($request->isPost()) {
            (new AuthController($conn))->updateProfile();
        }
        return;

    case 'password-change':
        if ($request->isPost()) {
            (new AuthController($conn))->changePassword();
        }
        return;

    case 'save-midtrans':
        if ($request->isPost()) {
            (new PaymentController($conn))->saveSettings();
        }
        return;

    // ─── Default: home page ───
    default:
        // Home page (gallery) — handled by front controller
        return;
}
