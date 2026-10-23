<?php
return ['default'=>'encrypted','channels'=>['encrypted'=>['driver'=>'monolog','handler'=>App\Encryption\EncryptedLogHandler::class,'level'=>'error','replace_placeholders'=>true],'emergency'=>['path'=>'php://memory']]];
