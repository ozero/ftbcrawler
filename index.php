<?php

//compat
if ( !function_exists('mime_content_type') ) {
  function mime_content_type($filename) {
    $mime_type = exec('file -Ib '.$filename);
    return $mime_type;
  }
}

require_once("downloadLib.class.php");
require_once("collectLib.class.php");

require_once("collectSiteFutaba.class.php");

//収集キーワード
//->単純に枚数でフィルタした方がよかったのでコメントアウト
//$s_futaba_dec=array(
//"エロ",
//"耳",
//"色",
//"ケモノ",
//"獣",
//"スレ"
//);//

//除外キーワード
$ng_futaba_dec=array(
"ゆっくり",
"ルール",
"極上",
"東方",
"会話",
"ガンダム",
);//


//ふたば二次裏junからの画像収集
$arg=array(
  'service'=>'futaba_jun',
  'url'=>'http://jun.2chan.net/b/futaba.htm',
  'keyword'=>$s_futaba_dec,
  'ngword'=>$ng_futaba_dec,
  'imgnum_min'=>15,//取得対象とするスレを絞る閾値：貼り画像枚数
);
$collect = new collectSiteFutaba;
$collect->conf();
$collect->collect($arg);

