<?php
return ['name'=>'SIRUKUN','env'=>env('APP_ENV','production'),'debug'=>(bool)env('APP_DEBUG',false),'url'=>env('APP_URL','http://localhost/sirukun/public'),'timezone'=>'Asia/Jakarta','locale'=>'id','fallback_locale'=>'en','faker_locale'=>'id_ID','cipher'=>'AES-256-GCM','key'=>env('APP_KEY'),'previous_keys'=>[],'maintenance'=>['driver'=>'file']];
