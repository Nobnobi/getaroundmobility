<?php

namespace App;

class Controller
{
    protected function render($view, $data = [])
    {
        extract($data);

        // Capture page content (output buffering)
        ob_start();
        include __DIR__ . "/Views/$view.php";
        $content = ob_get_clean();

        include __DIR__ . "/Views/layout.php";
        //include "Views/$view.php";

        
    }

    protected function renderAdmin($view, $data = [])
    {
        extract($data);

        // Capture page content (output buffering)
        ob_start();
        include __DIR__ . "/Views/$view.php";
        $content = ob_get_clean();

        include __DIR__ . "/Views/admin/admin-layout.php";

        
    }

}