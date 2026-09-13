<?php

namespace KLXM\ModuleOrganizer\Api;

use KLXM\ModuleOrganizer\Repository\CustomIconRepository;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_request;
use rex_response;

class DeleteCustomIcon extends rex_api_function
{
    protected $published = false;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user || !$user->isAdmin()) {
            throw new rex_api_exception('Admin login required.');
        }

        $id = rex_request('id', 'int', 0);
        if ($id <= 0) {
            rex_response::sendJson(['success' => false]);
            exit;
        }

        CustomIconRepository::delete($id);

        rex_response::sendJson(['success' => true]);
        exit;
    }
}
