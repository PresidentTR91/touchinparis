<?php
require __DIR__.'/_private/auth.php';
requireAccess();
session_write_close();
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/_private/workbook.php';
try { echo json_encode(competitionData($config),JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR); }
catch(Throwable $e) { http_response_code(503); echo json_encode(['error'=>'Les données de compétition sont momentanément indisponibles.']); }
