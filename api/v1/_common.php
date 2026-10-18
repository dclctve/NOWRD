<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: public, max-age=60, stale-while-revalidate=120'); header('X-API-Version: 1');
function api_out(array $payload, int $status=200): never { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function api_method_get(): void { if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');api_out(['error'=>['code'=>'method_not_allowed','message'=>'Only GET requests are supported.']],405);} }
function api_slug(string $slug): bool { return $slug!=='' && strlen($slug)<=240 && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/',$slug)===1; }
