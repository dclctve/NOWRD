<?php
require dirname(__DIR__).'/app/bootstrap.php';
require_admin();
require_permission('settings.manage');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed']); exit; }
$raw=file_get_contents('php://input'); $body=json_decode($raw,true);
$known=(string)($_SESSION['_csrf']??''); $sent=(string)($body['_csrf']??''); if($known===''||$sent===''||!hash_equals($known,$sent)){http_response_code(419);echo json_encode(['ok'=>false,'message'=>'Sesi kedaluwarsa. Muat ulang halaman.']);exit;}
if (!is_array($body) || !isset($body['preferences']) || !is_array($body['preferences'])) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Invalid preferences']); exit; }
$defaults=['mode'=>'classic','theme'=>'light','palette'=>'classic','motion'=>'smooth','blur'=>24,'alpha'=>68,'dockSize'=>'regular','dockPosition'=>'center'];
$p=array_merge($defaults,array_intersect_key($body['preferences'],$defaults));
$allowed=['mode'=>['classic','modern'],'theme'=>['light','dark','system'],'palette'=>['classic','glass','ocean','violet','forest','sunset'],'motion'=>['off','subtle','smooth','playful'],'dockSize'=>['compact','regular','large'],'dockPosition'=>['center','left','right','left-center','right-center']];
foreach($allowed as $key=>$values) if(!in_array($p[$key],$values,true)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Invalid '.$key]);exit;}
$p['blur']=max(0,min(40,(int)$p['blur'])); $p['alpha']=max(20,min(96,(int)$p['alpha']));
$q=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
$q->execute(['nowrd_visual_preferences',json_encode($p,JSON_UNESCAPED_SLASHES)]); cache_clear();
echo json_encode(['ok'=>true,'message'=>'Pengaturan visual global tersimpan.']);
