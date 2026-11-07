<?php
namespace App\Services\Crypto;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
class SwingEligibilityJsonStorage {
 private const DIR='crypto/swing'; private const ASSETS=self::DIR.'/assets.json'; private const PROGRESS=self::DIR.'/progress.json';
 public function readAssets(): array { return $this->read(self::ASSETS,[]); }
 public function readProgress(?string $id=null): array { $v=$this->read(self::PROGRESS,[]); return $id!==null && ($v['id']??null)!==$id ? ['id'=>$id,'status'=>'not_found','progress'=>0,'stage'=>'No matching sync'] : $v; }
 public function writeProgress(array $v): void { $this->atomicWrite(self::PROGRESS,$v); }
 public function replaceAssets(array $v): void { $this->atomicWrite(self::ASSETS,$v); }
 private function read(string $p,array $default): array { if(!Storage::disk('local')->exists($p)) return $default; $v=json_decode(Storage::disk('local')->get($p),true); return is_array($v)?$v:$default; }
 private function atomicWrite(string $p,array $v): void { $d=Storage::disk('local'); $d->makeDirectory(self::DIR); $tmp=$p.'.'.bin2hex(random_bytes(6)).'.tmp'; $json=json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); if($json===false||!$d->put($tmp,$json)) throw new RuntimeException("Unable to write {$p}"); if(!@rename($d->path($tmp),$d->path($p))) { $d->delete($tmp); throw new RuntimeException("Unable to atomically replace {$p}"); } }
}
