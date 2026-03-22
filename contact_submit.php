<?php
/**
 * Public endpoint for [cms_contact_form] submissions (POST only).
 */
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly'  => true,
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/config.php';
cms_contact_handle_post();
