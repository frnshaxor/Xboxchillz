<?php

declare(strict_types=1);

/**
 * Contact Controller — renders the public contact page.
 */
class ContactController
{
    public function show(): void
    {
        $db = Connection::getInstance()->db();

        $contactTitle = setting($db, 'contact_title', 'Hubungi Admin');
        $contactSubtitle = setting($db, 'contact_subtitle', 'Pilih platform yang paling nyaman untuk Anda.');
        $contactTelegram = setting($db, 'contact_telegram', '');
        $contactWhatsapp = setting($db, 'contact_whatsapp', '');
        $contactEmail = setting($db, 'contact_email', '');

        require VIEWS_DIR . '/pages/contact.php';
    }
}
