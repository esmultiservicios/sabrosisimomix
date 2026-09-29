<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function public_list(string $table,string $where='active=1',string $order='sort_order,id'): array {try{return db()->query("SELECT * FROM $table WHERE $where ORDER BY $order")->fetchAll();}catch(Throwable){return [];}}
function content_block(string $key,string $fallback=''): string {try{$st=db()->prepare('SELECT content_value FROM content_blocks WHERE content_key=? LIMIT 1');$st->execute([$key]);$v=$st->fetchColumn();return $v!==false?(string)$v:$fallback;}catch(Throwable){return $fallback;}}
